<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Lấy thông tin phiếu chi cần sửa
if(isset($_GET['id'])) {
    $payment_id = mysqli_real_escape_string($conn, $_GET['id']);
    $payment_query = mysqli_query($conn, "
        SELECT 
            p.*,
            a.Code as ApartmentCode,
            b.ProjectId,
            COALESCE(p.AccountingDate, CURRENT_DATE()) as AccountingDate,
            COALESCE(u.UserName, r.NationalId) as ReceiverName
        FROM payments p
        JOIN apartment a ON p.ApartmentID = a.ApartmentID
        JOIN Buildings b ON a.BuildingId = b.ID
        LEFT JOIN ResidentApartment ra ON a.ApartmentID = ra.ApartmentId AND ra.Relationship = 'Chủ hộ'
        LEFT JOIN resident r ON ra.ResidentId = r.ID
        LEFT JOIN users u ON r.ID = u.ResidentID
        WHERE p.PaymentID = '$payment_id'
    ");
    
    if(mysqli_num_rows($payment_query) > 0) {
        $payment_data = mysqli_fetch_assoc($payment_query);
        // Đảm bảo ngày hạch toán luôn có giá trị
        if (empty($payment_data['AccountingDate']) || $payment_data['AccountingDate'] == '0000-00-00') {
            $payment_data['AccountingDate'] = date('Y-m-d');
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
    $accounting_date = mysqli_real_escape_string($conn, $_POST['accounting_date']);
    $content = mysqli_real_escape_string($conn, $_POST['content']);

    // Kiểm tra và format ngày hạch toán
    if (empty($accounting_date) || $accounting_date == '0000-00-00') {
        $accounting_date = date('Y-m-d');
    }

    // Tắt kiểm tra khóa ngoại
    mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");

    // Cập nhật thông tin phiếu chi
    $update_query = mysqli_query($conn, "
        UPDATE payments SET
            AccountingDate = '$accounting_date',
            Content = '$content',
            UpdatedAt = CURRENT_TIMESTAMP
        WHERE PaymentID = '$payment_id'
    ");

    // Bật lại kiểm tra khóa ngoại
    mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");

    if($update_query) {
        $success_msg = "Cập nhật phiếu chi thành công!";
        // Refresh data
        $payment_query = mysqli_query($conn, "
            SELECT 
                p.*,
                a.Code as ApartmentCode,
                b.ProjectId,
                COALESCE(p.AccountingDate, CURRENT_DATE()) as AccountingDate,
                COALESCE(u.UserName, r.NationalId) as ReceiverName
            FROM payments p
            JOIN apartment a ON p.ApartmentID = a.ApartmentID
            JOIN Buildings b ON a.BuildingId = b.ID
            LEFT JOIN ResidentApartment ra ON a.ApartmentID = ra.ApartmentId AND ra.Relationship = 'Chủ hộ'
            LEFT JOIN resident r ON ra.ResidentId = r.ID
            LEFT JOIN users u ON r.ID = u.ResidentID
            WHERE p.PaymentID = '$payment_id'
        ");
        $payment_data = mysqli_fetch_assoc($payment_query);
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
    <title>Chỉnh sửa phiếu chi</title>
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
                    <h2 class="text-uppercase fw-bold" style="color: #476a52;">Chỉnh sửa phiếu chi</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a style="text-decoration: none; color: #476a52;" href="/admin/dashboard.php">Trang chủ</a></li>
                            <li class="breadcrumb-item"><a style="text-decoration: none; color: #476a52;" href="payment_receipt.php">Quản lý thu/chi</a></li>
                            <li class="breadcrumb-item active">Chỉnh sửa phiếu chi</li>
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

                <form method="POST" id="editPaymentForm">
                    <div class="form-section">
                        <div class="section-header">
                            PHIẾU CHI
                        </div>
                        <div class="receipt-date">
                            Ngày <?php echo date('d'); ?> tháng <?php echo date('m'); ?> năm <?php echo date('Y'); ?>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Căn hộ</label>
                                <a style="text-decoration: none; color: #476a52;" href="/webquanlytoanha/admin/apartment/detail_apartment.php?id=<?php echo $payment_data['ApartmentID']; ?>" class="form-control" value="<?php echo $payment_data['ApartmentCode']; ?>" disabled>
                                    <?php echo $payment_data['ApartmentCode']; ?>
                                </a>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Người nhận tiền</label>
                                <input type="text" class="form-control" name="receiver" value="<?php echo $payment_data['ReceiverName']; ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Hình thức chi</label>
                                <input type="text" class="form-control" value="<?php echo $payment_data['PaymentMethod']; ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Số tiền chi</label>
                                <input type="text" class="form-control" value="<?php echo number_format($payment_data['Total']); ?> VNĐ" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Ngày hạch toán</label>
                                <input type="date" class="form-control" name="accounting_date" id="accounting_date"
                                       value="<?php echo $payment_data['AccountingDate']; ?>" required
                                       <?php echo ($payment_data['PaymentMethod'] == 'Tiền mặt') ? 'disabled' : ''; ?>>
                            </div>
                            <div class="col-12">
                                <label class="form-label required">Nội dung chi tiền</label>
                                <textarea class="form-control" name="content" rows="3" required><?php echo $payment_data['Content']; ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="d-flex justify-content-end gap-2">
                            <button type="submit" name="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Cập nhật
                            </button>
                            <a href="payment_management.php" class="btn btn-secondary">
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
            // Xử lý enable/disable ngày hạch toán theo hình thức chi
            const paymentMethod = '<?php echo $payment_data['PaymentMethod']; ?>';
            const isDisabled = paymentMethod === 'Tiền mặt';
            $('#accounting_date').prop('disabled', isDisabled);
        });
    </script>
</body>
</html>

