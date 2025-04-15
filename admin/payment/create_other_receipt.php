<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
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
    $apartment_id = mysqli_real_escape_string($conn, $_POST['apartment_id']);
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $quantity = mysqli_real_escape_string($conn, $_POST['quantity']);
    $price = mysqli_real_escape_string($conn, $_POST['price']);
    $payer = mysqli_real_escape_string($conn, $_POST['payer']);
    $total = $quantity * $price;
    $accounting_date = mysqli_real_escape_string($conn, $_POST['accounting_date']);
    $content = mysqli_real_escape_string($conn, $_POST['content']);
    
    // Tạo mã phiếu thu theo định dạng PT_YYYYMMDD_XXX
    $current_date = date('Ymd', strtotime($accounting_date)); // Sử dụng ngày hạch toán thay vì ngày hiện tại
    $last_receipt_query = mysqli_query($conn, "
        SELECT OtherReceiptID 
        FROM OtherReceipt 
        WHERE OtherReceiptID LIKE 'PT\_$current_date\_%' 
        ORDER BY OtherReceiptID DESC 
        LIMIT 1
    ");

    if (mysqli_num_rows($last_receipt_query) > 0) {
        $last_receipt = mysqli_fetch_assoc($last_receipt_query);
        $last_id = $last_receipt['OtherReceiptID'];
        $last_sequence = intval(substr($last_id, -3));
        $new_sequence = $last_sequence + 1;
        if ($new_sequence > 999) {
            $error_msg = "Đã đạt đến giới hạn số phiếu thu trong ngày!";
            // Có thể thêm xử lý khác ở đây nếu cần
        }
    } else {
        $new_sequence = 1;
    }

    if (!isset($error_msg)) {
    $sequence_str = str_pad($new_sequence, 3, '0', STR_PAD_LEFT);
    $receipt_id = "PT_" . $current_date . "_" . $sequence_str;
    
        // Kiểm tra xem mã đã tồn tại chưa
        $check_exists = mysqli_query($conn, "
            SELECT OtherReceiptID 
            FROM OtherReceipt 
            WHERE OtherReceiptID = '$receipt_id'
        ");

        if (mysqli_num_rows($check_exists) > 0) {
            $error_msg = "Mã phiếu thu đã tồn tại!";
        } else {
            // Thêm đoạn này ngay trước câu lệnh INSERT
        mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");
        
            // Thực hiện INSERT như bình thường
            $insert_query = mysqli_query($conn, "
                INSERT INTO OtherReceipt (
                    OtherReceiptID, ApartmentID, Quantity, Price, PaymentMethod, 
                    Payer, Total, AccountingDate, Content, StaffID
                ) VALUES (
                    '$receipt_id', '$apartment_id', $quantity, $price, '$payment_method',
                    '$payer', $total, '$accounting_date', '$content', $admin_id
                )
            ");

            // Bật lại kiểm tra khóa ngoại sau khi hoàn thành
            mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");

            if($insert_query) {
                $success_msg = "Tạo phiếu thu thành công!";
                } else {
                $error_msg = "Có lỗi xảy ra: " . mysqli_error($conn);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lập phiếu thu khác</title>
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
            background: #6b8b7b;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .required:after {
            content: " *";
            color: red;
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
                    <h2 class="text-uppercase fw-bold" style="color: #476a52;">Lập phiếu thu khác</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a style="text-decoration: none; color: #476a52;" href="/webquanlytoanha/admin/dashboard.php">Trang chủ</a></li>
                            <li class="breadcrumb-item"><a style="text-decoration: none; color: #476a52;" href="payment_receipt.php">Quản lý thu/chi</a></li>
                            <li class="breadcrumb-item active">Lập phiếu thu khác</li>
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

                <form method="POST" id="otherReceiptForm">
                    <div class="form-section">
                        <div class="section-header">
                            Thông tin phiếu thu
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label required">Dự án</label>
                                <select class="form-select" name="project_id" id="project_id" required>
                                    <option value="">Chọn dự án</option>
                                    <?php while($project = mysqli_fetch_assoc($select_projects)) { ?>
                                        <option value="<?php echo $project['ProjectID']; ?>">
                                            <?php echo $project['Name']; ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Căn hộ</label>
                                <select class="form-select" name="apartment_id" id="apartment_id" required disabled>
                                    <option value="">Chọn căn hộ</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Đơn giá</label>
                                <input type="number" class="form-control" name="price" required min="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Số lượng</label>
                                <input type="number" class="form-control" name="quantity" value="1" min="1">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Hình thức thu</label>
                                <select class="form-select" name="payment_method" required>
                                    <option value="">Chọn hình thức thu</option>
                                    <option value="Tiền mặt">Tiền mặt</option>
                                    <option value="Chuyển khoản">Chuyển khoản</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Người nộp tiền</label>
                                <select class="form-select" name="payer" id="payer" required>
                                    <option value="">Chọn người nộp tiền</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Địa chỉ</label>
                                <input type="text" class="form-control" name="address">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Ngày hạch toán</label>
                                <input type="date" class="form-control" name="accounting_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label required">Nội dung thu tiền</label>
                                <textarea class="form-control" name="content" rows="3" required></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Tổng tiền thanh toán</label>
                                <input type="number" class="form-control" id="total" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="d-flex justify-content-end gap-2">
                            <button type="submit" name="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Lập phiếu
                            </button>
                            <a href="payment_receipt.php" class="btn btn-danger">
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
            // Xử lý khi chọn dự án
            $('#project_id').change(function() {
                const projectId = $(this).val();
                const apartmentSelect = $('#apartment_id');
                
                // Reset và disable căn hộ dropdown nếu không có dự án được chọn
                if (!projectId) {
                    apartmentSelect.html('<option value="">Chọn căn hộ</option>').prop('disabled', true);
                    return;
                }

                // Lấy danh sách căn hộ theo dự án
                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: {
                        get_apartments: true,
                        project_id: projectId
                    },
                    success: function(response) {
                        try {
                            const apartments = JSON.parse(response);
                            let options = '<option value="">Chọn căn hộ</option>';
                            
                            apartments.forEach(apt => {
                                options += `<option value="${apt.ApartmentID}">
                                    ${apt.Code} - ${apt.Name} (${apt.BuildingName})
                                </option>`;
                            });
                            
                            apartmentSelect
                                .html(options)
                                .prop('disabled', false);
                        } catch (e) {
                            console.error('Error parsing apartments:', e);
                            apartmentSelect
                                .html('<option value="">Lỗi khi tải danh sách căn hộ</option>')
                                .prop('disabled', true);
                        }
                    },
                    error: function() {
                        apartmentSelect
                            .html('<option value="">Lỗi khi tải danh sách căn hộ</option>')
                            .prop('disabled', true);
                    }
                });
            });

            // Tính tổng tiền khi thay đổi số lượng hoặc đơn giá
            $('input[name="quantity"], input[name="price"]').on('input', function() {
                const quantity = parseFloat($('input[name="quantity"]').val()) || 0;
                const price = parseFloat($('input[name="price"]').val()) || 0;
                $('#total').val(quantity * price);
            });

            // Validate form trước khi submit
            $('#otherReceiptForm').submit(function(e) {
                if (!$('#project_id').val()) {
                    alert('Vui lòng chọn dự án');
                    e.preventDefault();
                    return false;
                }

                if (!$('#apartment_id').val()) {
                    alert('Vui lòng chọn căn hộ');
                    e.preventDefault();
                    return false;
                }

                // Các validation khác giữ nguyên
            });

            // Thêm vào phần document.ready
            $('#apartment_id').change(function() {
                const apartmentId = $(this).val();
                const payerSelect = $('#payer');
                
                if (!apartmentId) {
                    payerSelect.html('<option value="">Chọn người nộp tiền</option>');
                    return;
                }

                // Lấy danh sách cư dân
                    $.ajax({
                        url: window.location.href,
                    type: 'POST',
                        data: { 
                        get_residents: true,
                            apartment_id: apartmentId 
                        },
                        success: function(response) {
                        try {
                            const residents = JSON.parse(response);
                            let options = '<option value="">Chọn người nộp tiền</option>';
                            
                            residents.forEach(resident => {
                                const relationship = resident.Relationship ? ` (${resident.Relationship})` : '';
                                options += `<option value="${resident.DisplayName}"${resident.Relationship === 'Chủ hộ' ? ' selected' : ''}>
                                    ${resident.DisplayName}${relationship}
                                </option>`;
                            });
                            
                            payerSelect.html(options);
                        } catch (e) {
                            console.error('Error parsing residents:', e);
                            payerSelect.html('<option value="">Lỗi khi tải danh sách cư dân</option>');
                        }
                    },
                    error: function() {
                        payerSelect.html('<option value="">Lỗi khi tải danh sách cư dân</option>');
                    }
                });
            });
        });
    </script>
</body>
</html>

