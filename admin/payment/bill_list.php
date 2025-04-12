<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

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
    LEFT JOIN staffs s ON d.StaffID = s.ID
    $where_clause
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
                        <a href="service_calculation.php" class="btn btn-success" style="margin-right: 10px;">
                            <i class="fas fa-calculator"></i> Tính phí dịch vụ
                        </a>
                        <a href="payment_receipt.php" class="btn btn-success">
                            <i class="fas fa-plus"></i> Lập phiếu thu
                        </a>
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
                                    <!-- Thêm nút gửi thông báo -->
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Xử lý sự kiện gửi thông báo qua email
    $(document).on('click', '.send-notification', function() {
        const invoiceCode = $(this).data('invoice-code');
        const button = $(this);
        
        // Hiển thị hộp thoại xác nhận
        if (confirm('Bạn có chắc chắn muốn gửi thông báo nhắc thu phí cho căn hộ này?')) {
            button.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);
            
            // Gọi AJAX để gửi thông báo
            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: {
                    send_notification: true,
                    invoice_code: invoiceCode
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Hiển thị thông báo thành công
                        alert(response.message);
                        button.html('<i class="fas fa-bell"></i>').prop('disabled', false);
                    } else {
                        // Hiển thị thông báo lỗi
                        alert(response.message);
                        button.html('<i class="fas fa-bell"></i>').prop('disabled', false);
                    }
                },
                error: function() {
                    alert('Đã xảy ra lỗi khi gửi thông báo!');
                    button.html('<i class="fas fa-bell"></i>').prop('disabled', false);
                }
            });
        }
    });
    </script>
</body>
</html>