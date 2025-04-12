<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Lấy thông tin phiếu thu cần sửa
if(isset($_GET['id'])) {
    $receipt_id = mysqli_real_escape_string($conn, $_GET['id']);
    $receipt_query = mysqli_query($conn, "
        SELECT 
            r.*,
            a.Code as ApartmentCode,
            b.ProjectId,
            COALESCE(r.AccountingDate, CURRENT_DATE()) as AccountingDate
        FROM Receipt r
        JOIN apartment a ON r.ApartmentID = a.ApartmentID
        JOIN Buildings b ON a.BuildingId = b.ID
        WHERE r.ReceiptID = '$receipt_id'
    ");
    
    if(mysqli_num_rows($receipt_query) > 0) {
        $receipt_data = mysqli_fetch_assoc($receipt_query);
        // Đảm bảo ngày hạch toán luôn có giá trị
        if (empty($receipt_data['AccountingDate']) || $receipt_data['AccountingDate'] == '0000-00-00') {
            $receipt_data['AccountingDate'] = date('Y-m-d');
        }
    } else {
        header('location: payment_receipt.php');
        exit();
    }
}

// Lấy danh sách căn hộ có bảng kê chờ thanh toán
$select_apartments = mysqli_query($conn, "
    SELECT DISTINCT 
        a.ApartmentID, 
        a.Code, 
        a.Name, 
        b.Name as BuildingName,
        d.InvoicePeriod,
        SUM(dd.RemainingBalance) as TotalDebt
    FROM apartment a 
    JOIN Buildings b ON a.BuildingId = b.ID
    JOIN debtstatements d ON a.ApartmentID = d.ApartmentID
    JOIN debtstatementdetail dd ON d.InvoiceCode = dd.InvoiceCode
    WHERE d.Status = 'Chờ thanh toán'
    AND dd.RemainingBalance > 0
    GROUP BY a.ApartmentID, d.InvoicePeriod
    ORDER BY b.Name, a.Code
");

// Lấy danh sách dịch vụ
$select_services = mysqli_query($conn, "SELECT ServiceCode, Name FROM services WHERE Status = 'active'");

// Lấy danh sách dự án
$select_projects = mysqli_query($conn, "
    SELECT ProjectID, Name 
    FROM Projects 
    WHERE Status = 'active'
    ORDER BY Name
");

// Thêm function này sau phần khai báo database
function getResidentsByApartment($conn, $apartment_id) {
    $query = "
        SELECT 
            r.ID,
            r.NationalId,
            u.UserName,
            u.Email,
            u.PhoneNumber,
            ra.Relationship
        FROM resident r
        JOIN ResidentApartment ra ON r.ID = ra.ResidentId
        LEFT JOIN users u ON r.ID = u.ResidentID
        WHERE ra.ApartmentId = '$apartment_id'
        ORDER BY CASE WHEN ra.Relationship = 'Chủ hộ' THEN 0 ELSE 1 END
    ";
    return mysqli_query($conn, $query);
}

// Xử lý AJAX lấy danh sách dịch vụ
if(isset($_POST['get_services']) && isset($_POST['apartment_id'])) {
    $apartment_id = mysqli_real_escape_string($conn, $_POST['apartment_id']);
    
    // Lấy danh sách dịch vụ
    $services_query = "
        SELECT DISTINCT 
            s.ServiceCode,
            s.Name as ServiceName,
            dd.Quantity,
            dd.UnitPrice,
            dd.Discount,
            dd.RemainingBalance,
            d.InvoiceCode,
            d.InvoicePeriod
        FROM debtstatements d
        JOIN debtstatementdetail dd ON d.InvoiceCode = dd.InvoiceCode
        JOIN services s ON dd.ServiceCode = s.ServiceCode
        WHERE d.ApartmentID = '$apartment_id'
        AND d.Status = 'Chờ thanh toán'
        ORDER BY d.IssueDate DESC
    ";
    
    $services_result = mysqli_query($conn, $services_query);
    $services = [];
    while($row = mysqli_fetch_assoc($services_result)) {
        $services[] = $row;
    }

    // Lấy danh sách cư dân
    $residents_result = getResidentsByApartment($conn, $apartment_id);
    $residents = [];
    while($row = mysqli_fetch_assoc($residents_result)) {
        $residents[] = $row;
    }
    
    echo json_encode([
        'services' => $services,
        'residents' => $residents
    ]);
    exit;
}

// Thêm vào phần xử lý AJAX
if(isset($_POST['get_project_services']) && isset($_POST['apartment_id'])) {
    $apartment_id = mysqli_real_escape_string($conn, $_POST['apartment_id']);
    
    // Lấy thông tin căn hộ và dịch vụ của dự án với giá từ bảng PriceList
    $query = "
        SELECT 
            a.Code as ApartmentCode,
            COALESCE(u.UserName, r.NationalId) as OwnerName,
            s.ServiceCode,
            s.Name as ServiceName,
            s.Cycle,
            s.SwitchDay,
            pl.Price as ServicePrice
        FROM apartment a
        JOIN Buildings b ON a.BuildingId = b.ID
        JOIN Projects p ON b.ProjectId = p.ProjectID
        LEFT JOIN ResidentApartment ra ON a.ApartmentID = ra.ApartmentId AND ra.Relationship = 'Chủ hộ'
        LEFT JOIN resident r ON ra.ResidentId = r.ID
        LEFT JOIN users u ON r.ID = u.ResidentID
        JOIN services s ON p.ProjectID = s.ProjectId
        LEFT JOIN ServicePrice sp ON s.ServiceCode = sp.ServiceId
        LEFT JOIN pricelist pl ON sp.PriceId = pl.ID AND pl.Status = 'active'
        WHERE a.ApartmentID = '$apartment_id'
        AND s.Status = 'active'
        GROUP BY s.ServiceCode
        ORDER BY s.Name
    ";
    
    $result = mysqli_query($conn, $query);
    $services = [];
    $apartment = null;
    
    while($row = mysqli_fetch_assoc($result)) {
        if(!$apartment) {
            $apartment = [
                'ApartmentCode' => $row['ApartmentCode'],
                'OwnerName' => $row['OwnerName']
            ];
        }
        $services[] = [
            'ServiceCode' => $row['ServiceCode'],
            'Name' => $row['ServiceName'],
            'StartPrice' => $row['ServicePrice'] ?? 0, // Sử dụng giá từ bảng PriceList
            'Cycle' => $row['Cycle'],
            'SwitchDay' => $row['SwitchDay']
        ];
    }
    
    echo json_encode([
        'apartment' => $apartment,
        'services' => $services
    ]);
    exit;
}

// Thêm AJAX endpoint để lấy danh sách căn hộ theo dự án
if(isset($_POST['get_apartments'])) {
    $project_id = mysqli_real_escape_string($conn, $_POST['project_id']);
    
    $apartments_query = mysqli_query($conn, "
        SELECT a.ApartmentID, a.Code, a.Name, b.Name as BuildingName
        FROM apartment a 
        JOIN Buildings b ON a.BuildingId = b.ID
        WHERE b.ProjectId = '$project_id'
        AND a.ContractCode IS NOT NULL
        AND a.ContractCode != ''
        ORDER BY b.Name, a.Code
    ");
    
    $apartments = array();
    while($row = mysqli_fetch_assoc($apartments_query)) {
        $apartments[] = $row;
    }
    
    echo json_encode($apartments);
    exit;
}

// Thêm endpoint để lấy danh sách cư dân theo căn hộ
if(isset($_POST['get_residents'])) {
    $apartment_id = mysqli_real_escape_string($conn, $_POST['apartment_id']);
    
    $residents_query = "
        SELECT 
            r.ID,
            r.NationalId,
            COALESCE(u.UserName, r.NationalId) as DisplayName,
            ra.Relationship
        FROM resident r
        JOIN ResidentApartment ra ON r.ID = ra.ResidentId
        LEFT JOIN users u ON r.ID = u.ResidentID
        WHERE ra.ApartmentId = '$apartment_id'
        ORDER BY 
            CASE WHEN ra.Relationship = 'Chủ hộ' THEN 0 ELSE 1 END,
            ra.Relationship
    ";
    
    $result = mysqli_query($conn, $residents_query);
    $residents = array();
    while($row = mysqli_fetch_assoc($result)) {
        $residents[] = $row;
    }
    
    echo json_encode($residents);
    exit;
}

// Xử lý khi submit form
if(isset($_POST['submit'])) {
    $accounting_date = @mysqli_real_escape_string($conn, $_POST['accounting_date']);
    $content = mysqli_real_escape_string($conn, $_POST['content']);

    // Kiểm tra và format ngày hạch toán
    if (empty($accounting_date) || $accounting_date == '0000-00-00') {
        $accounting_date = date('Y-m-d');
    }

    // Cập nhật thông tin phiếu thu
    $update_query = mysqli_query($conn, "
        UPDATE Receipt SET
            AccountingDate = '$accounting_date',
            Content = '$content'
        WHERE ReceiptID = '$receipt_id'
    ");

    if($update_query) {
        $success_msg = "Cập nhật phiếu thu thành công!";
        // Refresh data
        $receipt_query = mysqli_query($conn, "
            SELECT 
                r.*,
                a.Code as ApartmentCode,
                b.ProjectId,
                COALESCE(r.AccountingDate, CURRENT_DATE()) as AccountingDate
            FROM Receipt r
            JOIN apartment a ON r.ApartmentID = a.ApartmentID
            JOIN Buildings b ON a.BuildingId = b.ID
            WHERE r.ReceiptID = '$receipt_id'
        ");
        $receipt_data = mysqli_fetch_assoc($receipt_query);
    } else {
        $error_msg = "Có lỗi xảy ra: " . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chỉnh sửa phiếu thu</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .page-header {
            background-color: #f5f5f5;
            padding: 15px 20px;
            margin-bottom: 20px;
        }
        .form-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .section-header {
            text-align: center;
            font-size: 24px;
            margin-bottom: 20px;
            color: #333;
        }
        .required:after {
            content: " *";
            color: red;
        }
        .receipt-date {
            text-align: center;
            margin-bottom: 20px;
        }
        .receipt-info {
            margin-top: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-label {
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <?php include '../admin_navbar.php'; ?>
        <div class="flex-grow-1">
            <?php include '../admin_header.php'; ?>
            
            <div class="container-fluid p-4">
                <!-- Page Header -->
                <div class="page-header">
                    <h2 class="text-uppercase fw-bold" style="color: #476a52;">Chỉnh sửa phiếu thu</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a style="text-decoration: none; color: #476a52;" href="/admin/dashboard.php">Trang chủ</a></li>
                            <li class="breadcrumb-item"><a style="text-decoration: none; color: #476a52;" href="payment_receipt.php">Quản lý thu/chi</a></li>
                            <li class="breadcrumb-item active">Chỉnh sửa phiếu thu</li>
                        </ol>
                    </nav>
                </div>

                <?php
                if(isset($success_msg)) {
                    echo '<div class="alert alert-success">'.$success_msg.'</div>';
                }
                if(isset($error_msg)) {
                    echo '<div class="alert alert-danger">'.$error_msg.'</div>';
                }
                ?>

                <form method="POST" id="editReceiptForm">
                    <div class="form-section">
                        <div class="section-header">
                            PHIẾU THU
                        </div>
                        <div class="receipt-date">
                            Ngày <?php echo date('d'); ?> tháng <?php echo date('m'); ?> năm <?php echo date('Y'); ?>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Người nộp tiền</label>
                                <input type="text" class="form-control" value="<?php echo $receipt_data['Payer']; ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Căn hộ</label>
                                <a style="text-decoration: none; color: #476a52;" href="/webquanlytoanha/admin/apartment/detail_apartment.php?id=<?php echo $receipt_data['ApartmentID']; ?>" class="form-control" value="<?php echo $receipt_data['ApartmentCode']; ?>" disabled>
                                    <?php echo $receipt_data['ApartmentCode']; ?>
                                </a>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Hình thức thu</label>
                                <input type="text" class="form-control" value="<?php echo $receipt_data['PaymentMethod']; ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Số tiền nộp</label>
                                <input type="text" class="form-control" value="<?php echo number_format($receipt_data['Total']); ?> VNĐ" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Ngày hạch toán</label>
                                <input type="date" class="form-control" name="accounting_date" id="accounting_date"
                                       value="<?php echo $receipt_data['AccountingDate']; ?>" required
                                       <?php echo ($receipt_data['PaymentMethod'] == 'Tiền mặt') ? 'disabled' : ''; ?>>
                            </div>
                            <div class="col-12">
                                <label class="form-label required">Nội dung</label>
                                <textarea class="form-control" name="content" rows="3" required><?php echo $receipt_data['Content']; ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="d-flex justify-content-end gap-2">
                            <button type="submit" name="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Cập nhật
                            </button>
                            <a href="payment_receipt.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Quay lại
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Xử lý enable/disable ngày hạch toán theo hình thức thu
            $('#payment_method').change(function() {
                const isDisabled = $(this).val() === 'Tiền mặt';
                $('#accounting_date').prop('disabled', isDisabled);
                if (isDisabled) {
                    $('#accounting_date').val('<?php echo date('Y-m-d'); ?>');
                }
            });

            // Tính tổng tiền khi thay đổi số lượng hoặc đơn giá
            $('input[name="quantity"], input[name="price"]').on('input', function() {
                const quantity = parseFloat($('input[name="quantity"]').val()) || 0;
                const price = parseFloat($('input[name="price"]').val()) || 0;
                $('#total').val(quantity * price);
            });
        });
    </script>
</body>
</html>

