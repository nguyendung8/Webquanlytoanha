<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];
$selected_project = isset($_GET['project_id']) ? $_GET['project_id'] : '';

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Lấy danh sách dự án của nhân viên
$projects_query = "SELECT DISTINCT p.ProjectID, p.Name 
                  FROM Projects p
                  JOIN StaffProjects sp ON p.ProjectID = sp.ProjectId
                  JOIN staffs s ON sp.StaffId = s.ID
                  JOIN users u ON s.DepartmentId = u.DepartmentId
                  WHERE u.UserId = '$admin_id' 
                  AND p.Status = 'active'
                  ORDER BY p.Name";
$projects_result = mysqli_query($conn, $projects_query);

// Lấy danh sách tòa nhà cho filter
$select_buildings = mysqli_query($conn, "SELECT ID, Name FROM Buildings WHERE Status = 'active'");

// Xử lý các tham số tìm kiếm và phân trang
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$building_filter = isset($_GET['building']) ? mysqli_real_escape_string($conn, $_GET['building']) : '';
$apartment_filter = isset($_GET['apartment']) ? mysqli_real_escape_string($conn, $_GET['apartment']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';

// Thiết lập phân trang
$records_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 7;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Xây dựng câu query với điều kiện tìm kiếm
$where_conditions = [];
if (!empty($search)) {
    $where_conditions[] = "d.InvoiceCode LIKE '%$search%'";
}
if (!empty($building_filter)) {
    $where_conditions[] = "a.BuildingId = '$building_filter'";
}
if (!empty($apartment_filter)) {
    $where_conditions[] = "d.ApartmentID = '$apartment_filter'";
}
if (!empty($status_filter)) {
    $where_conditions[] = "d.Status = '$status_filter'";
}
// Thêm điều kiện lọc trạng thái khác 'Chờ xác nhận'
$where_conditions[] = "d.Status != 'Chờ xác nhận'";

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Query đếm tổng số bản ghi
$count_query = mysqli_query($conn, "
    SELECT COUNT(*) as total 
    FROM debtstatements d
    LEFT JOIN apartment a ON d.ApartmentID = a.ApartmentID 
    $where_clause
");
$total_records = mysqli_fetch_assoc($count_query)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Query lấy danh sách bảng kê
$query = "
    SELECT d.*, a.Code as ApartmentCode, a.Name as ApartmentName, s.Name as StaffName,
           (SELECT SUM(dd.PaidAmount) FROM debtstatementdetail dd WHERE dd.InvoiceCode = d.InvoiceCode) as TotalPaid
    FROM debtstatements d
    LEFT JOIN apartment a ON d.ApartmentID = a.ApartmentID
    LEFT JOIN Buildings b ON a.BuildingId = b.ID
    LEFT JOIN staffs s ON d.StaffID = s.ID
    WHERE " . ($selected_project ? "b.ProjectId = '$selected_project'" : "1=1") . "
    " . (!empty($where_conditions) ? "AND " . implode(" AND ", $where_conditions) : "") . "
    ORDER BY d.IssueDate DESC
    LIMIT $offset, $records_per_page
";

$select_bills = mysqli_query($conn, $query);

// Lấy danh sách căn hộ cho filter
$select_apartments = mysqli_query($conn, "SELECT ApartmentID, Code, Name FROM apartment");

// Xử lý xóa bảng kê
if(isset($_POST['delete_bill'])) {
    $invoice_code = mysqli_real_escape_string($conn, $_POST['invoice_code']);
    
    // Bắt đầu transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Xóa chi tiết bảng kê trước
        mysqli_query($conn, "DELETE FROM debtstatementdetail WHERE InvoiceCode = '$invoice_code'");
        
        // Sau đó xóa bảng kê chính
        mysqli_query($conn, "DELETE FROM debtstatements WHERE InvoiceCode = '$invoice_code'");
        
        // Commit nếu mọi thứ OK
        mysqli_commit($conn);
        $success_msg[] = 'Đã xóa bảng kê thành công!';
    } catch (Exception $e) {
        // Rollback nếu có lỗi
        mysqli_rollback($conn);
        $error_msg[] = 'Có lỗi xảy ra khi xóa bảng kê!';
    }
}

// Xử lý gửi email thông báo
if(isset($_POST['send_notification'])) {
    $invoice_code = mysqli_real_escape_string($conn, $_POST['invoice_code']);
    
    // Lấy thông tin bảng kê
    $invoice_query = mysqli_query($conn, "
        SELECT d.*, a.Code as ApartmentCode, a.Name as ApartmentName, a.ApartmentID,
               b.Name as BuildingName
        FROM debtstatements d
        LEFT JOIN apartment a ON d.ApartmentID = a.ApartmentID
        LEFT JOIN Buildings b ON a.BuildingId = b.ID
        WHERE d.InvoiceCode = '$invoice_code'
    ");
    
    if(mysqli_num_rows($invoice_query) > 0) {
        $invoice = mysqli_fetch_assoc($invoice_query);
        $apartment_id = $invoice['ApartmentID'];
        
        // Lấy thông tin chi tiết bảng kê
        $details_query = mysqli_query($conn, "
            SELECT d.*, s.Name as ServiceName, s.TypeOfService
            FROM debtstatementdetail d
            LEFT JOIN services s ON d.ServiceCode = s.ServiceCode
            WHERE d.InvoiceCode = '$invoice_code'
        ");
        
        $details = [];
        while($detail = mysqli_fetch_assoc($details_query)) {
            $details[] = $detail;
        }
        
        // Lấy thông tin chủ hộ và email
        $resident_query = mysqli_query($conn, "
            SELECT r.*, u.Email, u.UserName
            FROM resident r
            JOIN ResidentApartment ra ON r.ID = ra.ResidentId
            LEFT JOIN users u ON r.ID = u.ResidentID
            WHERE ra.ApartmentId = '$apartment_id'
            AND ra.Relationship = 'Chủ hộ'
            LIMIT 1
        ");
        
        // Nếu không tìm thấy chủ hộ, lấy bất kỳ cư dân nào của căn hộ
        if(mysqli_num_rows($resident_query) == 0) {
            $resident_query = mysqli_query($conn, "
                SELECT r.*, u.Email, u.UserName
                FROM resident r
                JOIN ResidentApartment ra ON r.ID = ra.ResidentId
                LEFT JOIN users u ON r.ID = u.ResidentID
                WHERE ra.ApartmentId = '$apartment_id'
                LIMIT 1
            ");
        }
        
        if(mysqli_num_rows($resident_query) > 0) {
            $resident = mysqli_fetch_assoc($resident_query);
            
            if(!empty($resident['Email'])) {
                // Tạo nội dung email thông báo
                $apartment_code = $invoice['ApartmentCode'];
                $building_name = $invoice['BuildingName'];
                $period = $invoice['InvoicePeriod'];
                $due_date = date('d - m - Y', strtotime($invoice['DueDate']));
                $total_amount = number_format($invoice['Total']);
                
                // Tạo bảng chi tiết dịch vụ
                $service_details = '';
                foreach($details as $detail) {
                    $service_amount = $detail['Quantity'] * $detail['UnitPrice'] - $detail['Discount'];
                    $service_details .= '<tr>
                        <td>'.$detail['ServiceName'].'</td>
                        <td style="text-align: right;">'.$detail['Quantity'].'</td>
                        <td style="text-align: right;">'.number_format($detail['UnitPrice']).' VNĐ</td>
                        <td style="text-align: right;">'.number_format($service_amount).' VNĐ</td>
                    </tr>';
                }
                
                // Nội dung email
                $email_content = '
                <div style="font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; line-height: 1.6;">
                    <h1 style="text-align: center; color: #333;">THÔNG BÁO NHẮC THU PHÍ DỊCH VỤ</h1>
                    
                    <p style="font-size: 16px; margin-bottom: 20px;">Kính gửi căn hộ <strong>'.$apartment_code.'</strong> thuộc tòa nhà <strong>'.$building_name.'</strong>.</p>
                    
                    <p style="font-size: 16px; margin-bottom: 20px;">Ban quản lý tòa nhà xin thông báo đến quý cư dân về việc thu phí dịch vụ kỳ tháng <strong>'.$period.'</strong> của căn hộ <strong>'.$apartment_code.'</strong>.</p>
                    
                    <ul style="font-size: 16px; margin-bottom: 20px;">
                        <li><strong>Hạn đóng phí dịch vụ: '.$due_date.'</strong></li>
                        <li><strong>Số tiền cần thanh toán: '.$total_amount.' VNĐ</strong></li>
                    </ul>
                    
                    <div style="font-size: 16px; margin-bottom: 20px;">
                        <p><strong>Chi tiết phí dịch vụ:</strong></p>
                        <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
                            <tr style="background-color: #f2f2f2;">
                                <th style="text-align: left;">Dịch vụ</th>
                                <th style="text-align: right;">Số lượng</th>
                                <th style="text-align: right;">Đơn giá</th>
                                <th style="text-align: right;">Thành tiền</th>
                            </tr>
                            '.$service_details.'
                            <tr style="font-weight: bold; background-color: #f9f9f9;">
                                <td colspan="3" style="text-align: right;">Tổng cộng:</td>
                                <td style="text-align: right;">'.number_format($invoice['Total']).' VNĐ</td>
                            </tr>
                        </table>
                    </div>
                    
                    <p style="font-size: 16px; margin-bottom: 20px;">Xin quý cư dân vui lòng thanh toán đầy đủ và đúng hạn để đảm bảo quyền lợi và sự thuận tiện trong quá trình sinh hoạt. Quý khách hàng có thể thực hiện thanh toán qua 2 hình thức:</p>
                    
                    <p style="font-size: 16px; margin-bottom: 10px;"><strong>+ Tiền mặt: Thanh toán trực tiếp với Ban quản lý.</strong></p>
                    
                    <p style="font-size: 16px; margin-bottom: 10px;"><strong>+ Thanh toán qua ngân hàng điện tử:</strong></p>
                    <ul style="font-size: 16px; margin-bottom: 20px;">
                        <li><strong>Ngân hàng:</strong> Ngân hàng Quân đội MB bank</li>
                        <li><strong>Số tài khoản:</strong> 09879990000</li>
                        <li><strong>Đơn vị thụ hưởng:</strong> BQL tòa nhà Buildmate</li>
                    </ul>
                    
                    <p style="font-size: 16px; margin-bottom: 20px;">Nếu có bất kỳ thắc mắc hoặc cần hỗ trợ, quý cư dân vui lòng liên hệ với Ban Quản lý qua:</p>
                    <p style="font-size: 16px; margin-bottom: 20px;">Số điện thoại 0384125722 hoặc email <a href="mailto:Buildmate@gmail.com">Buildmate@gmail.com</a>.</p>
                    
                    <div style="font-size: 16px; margin-bottom: 20px;">
                        <p><strong>Lưu ý:</strong></p>
                        <ul>
                            <li>Quý cư dân vui lòng thanh toán đúng hạn để tránh bị phạt chậm trả hoặc ngắt dịch vụ.</li>
                            <li>Sau thời hạn thanh toán, Ban quản lý sẽ liên hệ trực tiếp với các căn hộ chưa đóng phí để nhắc nhở và hỗ trợ.</li>
                            <li>Trường hợp quý cư dân có khó khăn về tài chính, vui lòng liên hệ với Ban quản lý để được hỗ trợ.</li>
                            <li>Nếu sau 30 ngày kể từ ngày hết hạn thanh toán mà quý cư dân vẫn chưa đóng phí, Ban quản lý sẽ buộc phải ngắt một số dịch vụ tiện ích như điện, nước, wifi, v.v... cho đến khi quý cư dân thanh toán đầy đủ.</li>
                        </ul>
                    </div>
                    
                    <p style="font-size: 16px; margin-bottom: 20px;">Trân trọng cảm ơn!</p>
                    
                    <p style="font-size: 16px; font-weight: bold;">Ban Quản Lý Tòa nhà Buildmate.</p>
                </div>';
                
                // Import class Mailer để gửi email
                include '../utils/Mailer.php';
                
                // Gửi email
                $mailer = new Mailer();
                $email_subject = "Thông báo nhắc thu phí dịch vụ - Căn hộ " . $apartment_code;
                
                $emailSent = $mailer->sendInvoiceEmail($resident['Email'], $resident['UserName'] ?? $resident['Name'], $email_subject, $email_content);
                
                if($emailSent) {
                    $success_msg[] = 'Đã gửi email thông báo thành công cho ' . ($resident['UserName'] ?? $resident['Name']) . ' (' . $resident['Email'] . ')';
                } else {
                    $error_msg[] = 'Không gửi được email thông báo!';
                }
            } else {
                $error_msg[] = 'Không tìm thấy email chủ hộ!';
            }
        } else {
            $error_msg[] = 'Không tìm thấy thông tin chủ hộ!';
        }
    } else {
        $error_msg[] = 'Không tìm thấy bảng kê!';
    }
    
    // Giữ lại các tham số tìm kiếm hiện tại
    $current_url = $_SERVER['REQUEST_URI'];
    header("Location: $current_url");
    exit();
}

if(isset($success_msg)){
    foreach($success_msg as $msg){
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
            '.$msg.'
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    }
}

if(isset($error_msg)){
    foreach($error_msg as $msg){
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            '.$msg.'
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách bảng kê</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="css/admin_style.css">
    <style>
        .stats-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .stats-card .icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            margin-right: 15px;
        }

        .stats-number {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
        }

        .stats-label {
            color: #666;
            margin: 0;
        }

        .stats-link {
            color: #476a52;
            text-decoration: none;
            font-size: 14px;
        }

        .search-container {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .apartment-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }

        .apartment-table th {
            background: #6b8b7b !important;
            color: white;
            font-weight: 500;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-occupied {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-pending {
            background: #fff3e0;
            color: #ef6c00;
        }

        .status-renovating {
            background: #e3f2fd;
            color: #1565c0;
        }

        .status-empty {
            background: #ffebee;
            color: #c62828;
        }

        .status-away {
            background: #f3e5f5;
            color: #6a1b9a;
        }

        .page-header {
            background-color: #f5f5f5;
            padding: 15px 20px;
            color: #4a5568;
            border-bottom: 1px solid #eaeaea;
        }
        
        .breadcrumb {
            display: flex;
            align-items: center;
            margin-bottom: 0;
            padding: 0;
            background-color: transparent;
            font-size: 14px;
        }
        
        .breadcrumb a {
            color: #4a5568;
            text-decoration: none;
        }

        .add-btn {
            margin-bottom: 10px;
            width: fit-content;
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            background-color: #476a52 !important;
            color: white !important;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .bill-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-paid {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .status-overdue {
            background: #ffebee;
            color: #c62828;
        }
        .action-buttons button {
            padding: 4px 8px;
            margin: 0 2px;
        }

        .custom-alert {
            position: fixed;
            top: 80px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            min-width: 300px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            animation: slideDown 0.5s ease-in-out;
            text-align: center;
        }

        @keyframes slideDown {
            from {
                transform: translate(-50%, -100%);
                opacity: 0;
            }
            to {
                transform: translate(-50%, 0);
                opacity: 1;
            }
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <?php include '../admin_navbar.php'; ?>
        <div style="width: 100%;">
            <?php include '../admin_header.php'; ?>
            <div class="container-fluid p-4">
                <div class="page-header mb-4">
                    <h2 style="font-weight: bold; color: #476a52; margin-bottom: 10px; text-transform: uppercase;">
                        DANH SÁCH BẢNG KÊ
                    </h2>
                    <div class="breadcrumb">
                        <a href="dashboard.php">Trang chủ</a>
                        <span style="margin: 0 8px;">›</span>
                        <span>Quản lý thu phí</span>
                        <span style="margin: 0 8px;">›</span>
                        <span>Danh sách bảng kê</span>
                    </div>
                </div>

                <!-- Search Form -->
                <div class="search-container mb-4">
                    <form class="row g-3">
                        <div class="col-md-2">
                            <select class="form-select" name="project_id" onchange="this.form.submit()">
                                <option value="">Chọn dự án</option>
                                <?php while($project = mysqli_fetch_assoc($projects_result)) { ?>
                                    <option value="<?php echo $project['ProjectID']; ?>" 
                                            <?php echo ($selected_project == $project['ProjectID']) ? 'selected' : ''; ?>>
                                        <?php echo $project['Name']; ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="text" class="form-control" name="search" placeholder="Mã bảng kê">
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="building">
                                <option value="">Chọn tòa nhà</option>
                                <?php while($building = mysqli_fetch_assoc($select_buildings)) { ?>
                                    <option value="<?php echo $building['ID']; ?>">
                                        <?php echo $building['Name']; ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="apartment">
                                <option value="">Chọn căn hộ</option>
                                <?php while($apt = mysqli_fetch_assoc($select_apartments)) { ?>
                                    <option value="<?php echo $apt['ApartmentID']; ?>">
                                        <?php echo $apt['Code'] . ' - ' . $apt['Name']; ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="month" class="form-control" name="month">
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="status">
                                <option value="">Trạng thái</option>
                                <option value="Chờ thanh toán">Chờ thanh toán</option>
                                <option value="Đã thanh toán">Đã thanh toán</option>
                                <option value="Quá hạn">Quá hạn</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-search"></i> Tìm kiếm
                            </button>
                        </div>
                    </form>
                    <div class="mt-3 d-flex justify-content-end">
                        <?php if(empty($selected_project)): ?>
                            <button type="button" class="btn btn-success btn-calculate action-btn" style="margin-right: 10px;">
                                <i class="fas fa-calculator"></i> Tính phí dịch vụ
                            </button>
                            <button type="button" class="btn btn-success btn-receipt action-btn">
                                <i class="fas fa-plus"></i> Lập phiếu thu
                            </button>
                        <?php else: ?>
                            <a href="service_calculation.php" class="btn btn-success btn-calculate action-btn" style="margin-right: 10px;">
                                <i class="fas fa-calculator"></i> Tính phí dịch vụ
                            </a>
                            <a href="payment_receipt.php" class="btn btn-success btn-receipt action-btn">
                                <i class="fas fa-plus"></i> Lập phiếu thu
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Bills Table -->
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>STT</th>
                                <th>MÃ BẢNG KÊ</th>
                                <th>KỲ BẢNG KÊ</th>
                                <th>CĂN HỘ</th>
                                <th>HẠN TT</th>
                                <th>TỔNG TIỀN</th>
                                <th>ĐÃ THANH TOÁN</th>
                                <th>CÒN NỢ</th>
                                <th>NGÀY DUYỆT</th>
                                <th>TRẠNG THÁI</th>
                                <th>THAO TÁC</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if(mysqli_num_rows($select_bills) > 0){
                                $i = $offset + 1;
                                while($bill = mysqli_fetch_assoc($select_bills)){
                            ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo $bill['InvoiceCode']; ?></td>
                                <td><?php echo $bill['InvoicePeriod']; ?></td>
                                <td><?php echo $bill['ApartmentName']; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($bill['DueDate'])); ?></td>
                                <td><?php echo number_format($bill['Total']); ?></td>
                                <td><?php echo number_format($bill['PaidAmount']); ?></td>
                                <td><?php echo number_format($bill['RemainingBalance']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($bill['ApprovalDate'])); ?></td>
                                <td>
                                    <span class="bill-status <?php 
                                        switch($bill['Status']){
                                            case 'Đã thanh toán': echo 'status-paid'; break;
                                            case 'Chờ thanh toán': echo 'status-pending'; break;
                                            case 'Quá hạn': echo 'status-overdue'; break;
                                        }
                                    ?>">
                                        <?php echo $bill['Status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-info view-invoice" title="Xem giấy báo phí" 
                                            data-invoice-code="<?php echo $bill['InvoiceCode']; ?>">
                                        <i class="fas fa-file-invoice"></i>
                                    </button>
                                    <a href="detail_bill.php?invoice_code=<?php echo $bill['InvoiceCode']; ?>" class="btn btn-sm btn-info" title="Xem chi tiết">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="invoice_code" value="<?php echo $bill['InvoiceCode']; ?>">
                                        <button type="submit" name="delete_bill" 
                                                class="btn btn-sm btn-danger" 
                                                onclick="return confirm('Bạn có chắc chắn muốn xóa bảng kê này?')"
                                                title="Xóa">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                    <?php if($bill['Status'] == 'Chờ thanh toán' || $bill['Status'] == 'Quá hạn'){ ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="invoice_code" value="<?php echo $bill['InvoiceCode']; ?>">
                                            <button type="submit" name="send_notification" 
                                                    class="btn btn-sm btn-warning" 
                                                    onclick="return confirm('Bạn có chắc chắn muốn gửi thông báo nhắc thu phí cho căn hộ này?')"
                                                    title="Gửi thông báo">
                                                <i class="fas fa-bell"></i>
                                            </button>
                                        </form>
                                    <?php } ?>
                                </td>
                            </tr>
                            <?php
                                }
                            } else {
                                echo '<tr><td colspan="15" class="text-center">Không có dữ liệu</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <div>Tổng số: <?php echo $total_records; ?> bản ghi</div>
                    <nav>
                        <ul class="pagination mb-0">
                            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php 
                                    echo !empty($search) ? "&search=$search" : '';
                                    echo !empty($building_filter) ? "&building=$building_filter" : '';
                                    echo !empty($apartment_filter) ? "&apartment=$apartment_filter" : '';
                                    echo !empty($status_filter) ? "&status=$status_filter" : '';
                                ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                    <div class="d-flex align-items-center">
                        <span class="me-2">Hiển thị</span>
                        <select class="form-select" style="width: auto;" onchange="window.location.href=this.value">
                            <?php foreach ([7, 10, 25, 50] as $per_page): ?>
                                <option value="?per_page=<?php echo $per_page; ?>" 
                                        <?php echo ($records_per_page == $per_page) ? 'selected' : ''; ?>>
                                    <?php echo $per_page; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function showProjectAlert() {
        // Xóa thông báo cũ nếu có
        $('.custom-alert').remove();
        
        // Tạo thông báo mới
        const alertHtml = `
            <div class="custom-alert alert alert-warning alert-dismissible fade show" role="alert">
                <strong>Thông báo!</strong> Vui lòng chọn dự án trước khi thực hiện thao tác này.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        
        // Thêm thông báo vào sau header
        $('.page-header').before(alertHtml);
        
        // Tự động ẩn sau 3 giây
        setTimeout(function() {
            $('.custom-alert').fadeOut('slow', function() {
                $(this).remove();
            });
        }, 3000);
    }

    $(document).ready(function() {
        // Gắn event cho các nút thao tác khi chưa chọn dự án
        if (!<?php echo $selected_project ? 'true' : 'false'; ?>) {
            // Chặn nút tính phí và lập phiếu thu
            $('.btn-calculate, .btn-receipt' ).on('click', function(e) {
                e.preventDefault();
                showProjectAlert();
            });

            // Chặn các nút thao tác trong bảng
            $('.action-btn').on('click', function(e) {
                e.preventDefault();
                showProjectAlert();
            });
        }
    });
    </script>

    <!-- Modal Giấy báo phí -->
    <div class="modal fade" id="invoiceModal" tabindex="-1" aria-labelledby="invoiceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="invoiceModalLabel">Giấy báo phí</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="invoiceModalBody">
                    <div class="invoice-content">
                        <div class="row mb-3 text-center">
                            <div class="col-4">
                                <img src="/webquanlytoanha/assets/logo.png" alt="Logo" height="80">
                            </div>
                            <div class="col-8 text-start">
                                <h5>VĂN PHÒNG BAN QUẢN LÝ BUILDMATE</h5>
                                <p class="mb-0">12 Chùa Bộc, Quang Trung, Đống Đa, Hà Nội</p>
                                <p class="mb-0">Hotline CSKH: 0978343328 - Hotline kỹ thuật: 0978343328 - Hotline bảo vệ: 0978343328</p>
                            </div>
                        </div>
                        
                        <div class="row mt-4 mb-3 text-center">
                            <div class="col-12">
                                <h4 class="fw-bold" id="invoiceTitle">GIẤY BÁO PHÍ THÁNG</h4>
                            </div>
                        </div>
                        
                        <div class="row justify-content-between mb-3">
                            <div class="col-6">
                                <p class="mb-0"><strong>Kính gửi/Respectfully:</strong> <span id="residentName"></span></p>
                                <p class="mb-0"><strong>Mã số căn hộ/Apartment code:</strong> <span id="apartmentCode"></span></p>
                            </div>
                            <div class="col-6 text-end">
                                <p class="mb-0"><strong>Số:</strong> <span id="invoiceNumber"></span></p>
                                <p class="mb-0"><strong>Tổng tiền thanh toán/Total:</strong> <span id="invoiceTotal"></span></p>
                            </div>
                        </div>
                        
                        <div class="table-responsive mb-3">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>STT</th>
                                        <th>Diễn giải (Explanation)</th>
                                        <th>Nợ trước (Prior debt)</th>
                                        <th>Phát sinh trong tháng (Addition on a month)</th>
                                        <th>Thanh toán (Paid)</th>
                                        <th>Tổng (Total)</th>
                                        <th>Ghi chú</th>
                                    </tr>
                                </thead>
                                <tbody id="invoiceSummaryBody">
                                    <!-- Dữ liệu tổng hợp sẽ được điền vào đây -->
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mb-3">
                            <h5>THÔNG TIN CHI TIẾT/THE INFORMATION IN DETAIL</h5>
                            <div id="invoiceDetails">
                                <!-- Các chi tiết dịch vụ sẽ được điền vào đây -->
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <h5>PHƯƠNG THỨC THANH TOÁN/PAYMENT METHODS</h5>
                            <div class="mb-2">
                                <strong>1/Thanh toán tiền mặt/ By cash:</strong>
                                <p>Tại Văn phòng ban quản lý Buildmate</p>
                            </div>
                            
                            <div>
                                <strong>2/Thanh toán chuyển khoản/ ByTranfer to:</strong>
                                <table class="table table-bordered">
                                    <tr>
                                        <th>Chủ tài khoản (Name)</th>
                                        <th>Số tài khoản (Account number)</th>
                                        <th>Ngân hàng (Bank)</th>
                                        <th></th>
                                    </tr>
                                    <tr>
                                        <td>Trần Thị Kim Anh</td>
                                        <td>0888738572</td>
                                        <td>Ngân hàng Quân đội MB Bank</td>
                                        <td>
                                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=0888738572" alt="QR Code" height="100">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="4">
                                            <p>Nội dung chuyển khoản: "Căn hộ - Loại phí - Diễn giải". Ví dụ: "B1002 - PQL, Nước, Xe - T4/19"</p>
                                            <p>Details Of Payment: "Apartment's code - Charge code - Noted". EX:"B1002 - Management fee, Water, Parking fee - T4/19 "</p>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-8">
                                <strong>Ghi chú/Note:</strong>
                                <ul>
                                    <li>Thời hạn nộp các khoản phí là 45 ngày kể từ ngày phát sinh phí chưa thanh toán.</li>
                                    <li>Ban Quản lý sẽ cảnh thông báo trước nợ 05 ngày trước khi tiến hành ngưng cung cấp dịch vụ đối với các căn hộ nợ các khoản phí quá 45 ngày.</li>
                                    <li>Nếu quý khách hàng đã thanh toán phí, xin vui lòng bỏ qua thông báo này.</li>
                                    <li>Trường hợp Quý cư dân vì lý do đặc biệt chưa thanh toán kịp thời thì gian quy định, xin vui lòng thông báo cho Ban Quản lý để được hỗ trợ.</li>
                                </ul>
                            </div>
                            <div class="col-4 text-center">
                                <p>Hà Nội, Ngày <span id="currentDate"></span></p>
                                <p><strong>Trưởng ban quản lý</strong></p>
                                <div style="height: 80px;"></div>
                                <p><strong id="managerName">Hoàng Văn Nam_Trưởng ban</strong></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        // Xử lý sự kiện click nút xem giấy báo phí
        $('.view-invoice').on('click', function() {
            const invoiceCode = $(this).data('invoice-code');
            
            // Gọi AJAX để lấy dữ liệu bảng kê
            $.ajax({
                url: 'bill_approval.php',
                method: 'POST',
                data: {
                    get_invoice_data: true,
                    invoice_code: invoiceCode
                },
                success: function(response) {
                    if(response.success) {
                        const data = response;
                        const invoice = data.invoice;
                        const details = data.details;
                        const resident = data.resident;
                        
                        // Cập nhật thông tin trong modal
                        $('#invoiceTitle').text('GIẤY BÁO PHÍ THÁNG ' + invoice.InvoicePeriod);
                        $('#residentName').text(resident ? resident.Name : invoice.ApartmentName);
                        $('#apartmentCode').text(invoice.ApartmentCode);
                        $('#invoiceNumber').text(invoice.InvoiceCode);
                        $('#invoiceTotal').text(new Intl.NumberFormat('vi-VN').format(invoice.Total) + ' VNĐ');
                        
                        // Cập nhật bảng tổng hợp
                        $('#invoiceSummaryBody').html(`
                            <tr>
                                <td>1</td>
                                <td>Phí quản lý</td>
                                <td>${new Intl.NumberFormat('vi-VN').format(invoice.OutstandingDebt)}</td>
                                <td>${new Intl.NumberFormat('vi-VN').format(invoice.Total - invoice.OutstandingDebt - invoice.Discount)}</td>
                                <td>${new Intl.NumberFormat('vi-VN').format(invoice.PaidAmount)}</td>
                                <td>${new Intl.NumberFormat('vi-VN').format(invoice.Total)}</td>
                                <td></td>
                            </tr>
                            <tr>
                                <td>2</td>
                                <td>Tổng</td>
                                <td>${new Intl.NumberFormat('vi-VN').format(invoice.OutstandingDebt)}</td>
                                <td>${new Intl.NumberFormat('vi-VN').format(invoice.Total - invoice.OutstandingDebt - invoice.Discount)}</td>
                                <td>${new Intl.NumberFormat('vi-VN').format(invoice.PaidAmount)}</td>
                                <td>${new Intl.NumberFormat('vi-VN').format(invoice.Total)}</td>
                                <td></td>
                            </tr>
                        `);
                        
                        // Cập nhật chi tiết dịch vụ
                        let detailsHtml = '';
                        details.forEach((service, index) => {
                            const serviceAmount = service.Quantity * service.UnitPrice;
                            detailsHtml += `
                                <div class="mb-4">
                                    <h5>${index + 1}/${service.ServiceName || 'Dịch vụ'}</h5>
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Tháng (Month)</th>
                                                <th>Diện tích (SQM) (1)</th>
                                                <th>Đơn giá (Unit price) (2)</th>
                                                <th>Thành tiền (Amount)(3)=(1)x(2)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>Nợ trước/ Debt</td>
                                                <td></td>
                                                <td></td>
                                                <td>0</td>
                                            </tr>
                                            <tr>
                                                <td>Tháng ${invoice.InvoicePeriod}</td>
                                                <td>${service.Quantity}</td>
                                                <td>${new Intl.NumberFormat('vi-VN').format(service.UnitPrice)}</td>
                                                <td>${new Intl.NumberFormat('vi-VN').format(serviceAmount)}</td>
                                            </tr>
                                            <tr>
                                                <td>Giảm giá/ Discount</td>
                                                <td></td>
                                                <td></td>
                                                <td>${new Intl.NumberFormat('vi-VN').format(service.Discount)}</td>
                                            </tr>
                                            <tr>
                                                <td>Thanh toán/ Paid</td>
                                                <td></td>
                                                <td></td>
                                                <td>${new Intl.NumberFormat('vi-VN').format(service.PaidAmount)}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            `;
                        });
                        $('#invoiceDetails').html(detailsHtml);
                        
                        // Cập nhật ngày và tên trưởng ban
                        const today = new Date().toLocaleDateString('vi-VN');
                        $('#currentDate').text(today);
                        if(invoice.ManagerName) {
                            $('#managerName').text(invoice.ManagerName);
                        }
                        
                        // Hiển thị modal
                        $('#invoiceModal').modal('show');
                    } else {
                        alert('Không thể lấy thông tin bảng kê: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    alert('Đã xảy ra lỗi khi lấy thông tin bảng kê');
                }
            });
        });
    });
    </script>
</body>
</html>