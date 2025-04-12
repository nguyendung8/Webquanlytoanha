<?php
include '../../database/DBController.php';
require_once '../utils/Mailer.php';

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

// Thêm điều kiện mặc định cho trạng thái Chờ xác nhận
$where_conditions[] = "d.Status = 'Chờ xác nhận'";

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

// Xử lý duyệt bảng kê
if(isset($_POST['approve_bill'])) {
    $invoice_code = mysqli_real_escape_string($conn, $_POST['invoice_code']);
    
    // Cập nhật trạng thái bảng kê
    $update_query = "UPDATE debtstatements 
                    SET Status = 'Chờ thanh toán',
                        ApprovalDate = NOW()
                    WHERE InvoiceCode = '$invoice_code' 
                    AND Status = 'Chờ xác nhận'";
    
    if(mysqli_query($conn, $update_query)) {
        // Lấy thông tin bảng kê để gửi email
        $invoice_query = mysqli_query($conn, "
            SELECT d.*, a.Code as ApartmentCode, a.Name as ApartmentName, a.ApartmentID,
                   b.Name as BuildingName, b.ProjectId,
                   p.ManagerId, s.Name as ManagerName
            FROM debtstatements d
            LEFT JOIN apartment a ON d.ApartmentID = a.ApartmentID
            LEFT JOIN Buildings b ON a.BuildingId = b.ID
            LEFT JOIN Projects p ON b.ProjectId = p.ProjectID
            LEFT JOIN staffs s ON p.ManagerId = s.ID
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
                    // Tạo nội dung email
                    $email_content = createInvoiceEmailContent($invoice, $details, $resident);
                    
                    // Gửi email
                    $mailer = new Mailer();
                    $email_subject = "Giấy báo phí tháng " . $invoice['InvoicePeriod'] . " - Căn hộ " . $invoice['ApartmentCode'];
                    $emailSent = $mailer->sendInvoiceEmail($resident['Email'], $resident['UserName'] ?? $resident['Name'], $email_subject, $email_content);
                    
                    if($emailSent) {
                        $success_msg[] = 'Duyệt bảng kê thành công và đã gửi email thông báo!';
                    } else {
                        $success_msg[] = 'Duyệt bảng kê thành công nhưng không gửi được email!';
                    }
                } else {
                    $success_msg[] = 'Duyệt bảng kê thành công nhưng không tìm thấy email chủ hộ!';
                }
            } else {
                $success_msg[] = 'Duyệt bảng kê thành công nhưng không tìm thấy thông tin chủ hộ!';
            }
        } else {
            $success_msg[] = 'Duyệt bảng kê thành công!';
        }
        
        // Giữ lại các tham số tìm kiếm hiện tại
        $current_url = $_SERVER['REQUEST_URI'];
        header("Location: $current_url");
        exit();
    } else {
        $error_msg[] = 'Có lỗi xảy ra khi duyệt bảng kê! ' . mysqli_error($conn);
        header("Location: $current_url");
        exit();
    }
}

// Xử lý AJAX lấy dữ liệu bảng kê
if(isset($_POST['get_invoice_data']) && isset($_POST['invoice_code'])) {
    $invoice_code = mysqli_real_escape_string($conn, $_POST['invoice_code']);
    
    // Lấy thông tin bảng kê có thêm thông tin về trưởng ban quản lý
    $invoice_query = mysqli_query($conn, "
        SELECT d.*, a.Code as ApartmentCode, a.Name as ApartmentName, a.ApartmentID,
               b.Name as BuildingName, b.ProjectId,
               p.ManagerId, s.Name as ManagerName
        FROM debtstatements d
        LEFT JOIN apartment a ON d.ApartmentID = a.ApartmentID
        LEFT JOIN Buildings b ON a.BuildingId = b.ID
        LEFT JOIN Projects p ON b.ProjectId = p.ProjectID
        LEFT JOIN staffs s ON p.ManagerId = s.ID
        WHERE d.InvoiceCode = '$invoice_code'
    ");
    
    if(mysqli_num_rows($invoice_query) > 0) {
        $invoice = mysqli_fetch_assoc($invoice_query);
        
        // Lấy thông tin căn hộ
        $apartment_query = mysqli_query($conn, "
            SELECT a.*, b.Name as BuildingName
            FROM apartment a
            LEFT JOIN Buildings b ON a.BuildingId = b.ID
            WHERE a.ApartmentID = '{$invoice['ApartmentID']}'
        ");
        $apartment = mysqli_fetch_assoc($apartment_query);
        
        // Lấy thông tin cư dân
        $resident_query = mysqli_query($conn, "
            SELECT r.*
            FROM resident r
            JOIN ResidentApartment ra ON r.ID = ra.ResidentId
            WHERE ra.ApartmentId = '{$invoice['ApartmentID']}'
            LIMIT 1
        ");
        $resident = mysqli_num_rows($resident_query) > 0 ? mysqli_fetch_assoc($resident_query) : null;
        
        // Lấy chi tiết bảng kê
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
        
        // Trả về kết quả
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'invoice' => $invoice,
            'apartment' => $apartment,
            'resident' => $resident,
            'details' => $details
        ]);
        exit;
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy bảng kê']);
        exit;
    }
}

// Hiển thị thông báo từ session
if(isset($_SESSION['success_msg'])) {
    $success_msg[] = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}

if(isset($_SESSION['error_msg'])) {
    $error_msg[] = $_SESSION['error_msg'];
    unset($_SESSION['error_msg']);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách bảng kê chờ xác nhận</title>

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
                        DANH SÁCH BẢNG KÊ CHỜ XÁC NHẬN
                    </h2>
                    <div class="breadcrumb">
                        <a href="dashboard.php">Trang chủ</a>
                        <span style="margin: 0 8px;">›</span>
                        <span>Quản lý thu phí</span>
                        <span style="margin: 0 8px;">›</span>
                        <span>Danh sách bảng kê chờ xác nhận</span>
                    </div>
                </div>

                <?php
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
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-search"></i> Tìm kiếm
                            </button>
                        </div>
                    </form>
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
                                <th>NỢ</th>
                                <th>GIẢM GIÁ</th>
                                <th>TỔNG TIỀN</th>
                                <th>ĐÃ THANH TOÁN</th>
                                <th>CÒN NỢ</th>
                                <th>NGÀY LẬP</th>
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
                                <td><?php echo number_format($bill['OutstandingDebt']); ?></td>
                                <td><?php echo number_format($bill['Discount']); ?></td>
                                <td><?php echo number_format($bill['Total']); ?></td>
                                <td><?php echo number_format($bill['PaidAmount']); ?></td>
                                <td><?php echo number_format($bill['RemainingBalance']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($bill['IssueDate'])); ?></td>
                                <td>
                                    <span class="bill-status <?php 
                                        switch($bill['Status']){
                                            case 'Chờ xác nhận': echo 'status-pending'; break;
                                            case 'Chờ thanh toán': echo 'status-pending'; break;
                                            case 'Đã thanh toán': echo 'status-paid'; break;
                                            case 'Quá hạn': echo 'status-overdue'; break;
                                        }
                                    ?>">
                                        <?php echo $bill['Status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="invoice_code" value="<?php echo $bill['InvoiceCode']; ?>">
                                        <button type="submit" name="approve_bill" 
                                                class="btn btn-sm btn-success" 
                                                onclick="return confirm('Bạn có chắc chắn muốn duyệt bảng kê này?')">
                                            <i class="fas fa-check"></i> Duyệt
                                        </button>
                                    </form>
                                    
                                    <button class="btn btn-sm btn-info view-invoice" title="Xem giấy báo phí" 
                                            data-invoice-code="<?php echo $bill['InvoiceCode']; ?>">
                                        <i class="fas fa-file-invoice"></i>
                                    </button>
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function() {
        // Xử lý khi click vào nút xem giấy báo phí
        $(document).on('click', '.view-invoice', function() {
            const invoiceCode = $(this).data('invoice-code');
            
            // Gọi AJAX để lấy thông tin giấy báo phí
            $.ajax({
                url: window.location.href, // Gửi đến chính file hiện tại
                method: 'POST',
                data: { 
                    get_invoice_data: true,
                    invoice_code: invoiceCode 
                },
                dataType: 'json',
                success: function(data) {
                    if (data.success) {
                        // Điền thông tin vào modal
                        $('#invoiceTitle').text('GIẤY BÁO PHÍ THÁNG ' + data.invoice.InvoicePeriod);
                        $('#invoiceNumber').text(data.invoice.InvoiceCode);
                        $('#residentName').text(data.resident ? data.resident.Name : data.apartment.Name);
                        $('#apartmentCode').text(data.apartment.Code);
                        $('#invoiceTotal').text(formatCurrency(data.invoice.Total) + ' VNĐ');
                        
                        // Điền tên trưởng ban quản lý
                        $('#managerName').text(data.invoice.ManagerName || 'Hoàng Văn Nam_Trưởng ban');
                        
                        // Điền dữ liệu tổng hợp
                        let summaryHtml = '';
                        let totalAmount = 0;
                        
                        summaryHtml += `<tr>
                            <td>1</td>
                            <td>Phí quản lý</td>
                            <td>${formatCurrency(data.invoice.OutstandingDebt)}</td>
                            <td>${formatCurrency(data.invoice.Total - data.invoice.OutstandingDebt - data.invoice.Discount)}</td>
                            <td>${formatCurrency(data.invoice.PaidAmount)}</td>
                            <td>${formatCurrency(data.invoice.Total)}</td>
                            <td></td>
                        </tr>`;
                        
                        summaryHtml += `<tr>
                            <td>2</td>
                            <td>Tổng</td>
                            <td>${formatCurrency(data.invoice.OutstandingDebt)}</td>
                            <td>${formatCurrency(data.invoice.Total - data.invoice.OutstandingDebt - data.invoice.Discount)}</td>
                            <td>${formatCurrency(data.invoice.PaidAmount)}</td>
                            <td>${formatCurrency(data.invoice.Total)}</td>
                            <td></td>
                        </tr>`;
                        
                        $('#invoiceSummaryBody').html(summaryHtml);
                        
                        // Điền chi tiết dịch vụ
                        let detailsHtml = '';
                        
                        if (data.details && data.details.length > 0) {
                            data.details.forEach(function(service, index) {
                                detailsHtml += `
                                    <div class="service-detail mb-4">
                                        <h6>${index + 1}/ ${service.ServiceName}</h6>
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
                                                    <td>${formatCurrency(0)}</td>
                                                </tr>
                                                <tr>
                                                    <td>Tháng ${data.invoice.InvoicePeriod}</td>
                                                    <td>${service.Quantity}</td>
                                                    <td>${formatCurrency(service.UnitPrice)}</td>
                                                    <td>${formatCurrency(service.Quantity * service.UnitPrice)}</td>
                                                </tr>
                                                <tr>
                                                    <td>Giảm giá/ Discount</td>
                                                    <td></td>
                                                    <td></td>
                                                    <td>${formatCurrency(service.Discount)}</td>
                                                </tr>
                                                <tr>
                                                    <td>Thanh toán/ Paid</td>
                                                    <td></td>
                                                    <td></td>
                                                    <td>${formatCurrency(service.PaidAmount)}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                `;
                            });
                        } else {
                            detailsHtml = '<p>Không có dữ liệu chi tiết</p>';
                        }
                        
                        $('#invoiceDetails').html(detailsHtml);
                        
                        // Cập nhật ngày hiện tại
                        const today = new Date();
                        $('#currentDate').text(today.getDate() + ' tháng ' + (today.getMonth() + 1) + ' năm ' + today.getFullYear());
                        
                        // Hiển thị modal
                        $('#invoiceModal').modal('show');
                    } else {
                        alert('Không thể lấy thông tin giấy báo phí!');
                    }
                },
                error: function() {
                    alert('Đã xảy ra lỗi khi lấy thông tin giấy báo phí!');
                }
            });
        });
        
        // Xử lý in giấy báo phí
        $('#printInvoice').click(function() {
            const content = document.getElementById('invoiceModalBody').innerHTML;
            const printWindow = window.open('', '_blank');
            
            printWindow.document.write(`
                <html>
                <head>
                    <title>Giấy báo phí</title>
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                    <style>
                        body {
                            font-size: 14px;
                            padding: 20px;
                        }
                        @media print {
                            body {
                                width: 21cm;
                                height: 29.7cm;
                                margin: 0;
                            }
                        }
                    </style>
                </head>
                <body onload="window.print(); window.close();">
                    ${content}
                </body>
                </html>
            `);
            
            printWindow.document.close();
        });
        
        // Hàm format số tiền
        function formatCurrency(amount) {
            return new Intl.NumberFormat('vi-VN').format(amount);
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
                                            <p>Details Of Payment: "Apartment\'s code - Charge code - Noted". EX:"B1002 - Management fee, Water, Parking fee - T4/19 "</p>
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
</body>
</html>
<?php
function createInvoiceEmailContent($invoice, $details, $resident) {
    // Lấy thông tin trưởng ban quản lý từ $invoice nếu có
    $manager_name = $invoice['ManagerName'] ?? 'Hoàng Văn Nam';
    $today = date('d/m/Y');
    
    $total_amount = number_format($invoice['Total']);
    
    // Tạo bảng tổng hợp
    $summary_table = '
    <table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse; width: 100%;">
        <thead>
            <tr style="background-color: #f2f2f2;">
                <th>STT</th>
                <th>Diễn giải (Explanation)</th>
                <th>Nợ trước (Prior debt)</th>
                <th>Phát sinh trong tháng (Addition on a month)</th>
                <th>Thanh toán (Paid)</th>
                <th>Tổng (Total)</th>
                <th>Ghi chú</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1</td>
                <td>Phí quản lý</td>
                <td>'.number_format($invoice['OutstandingDebt']).'</td>
                <td>'.number_format($invoice['Total'] - $invoice['OutstandingDebt'] - $invoice['Discount']).'</td>
                <td>'.number_format($invoice['PaidAmount']).'</td>
                <td>'.number_format($invoice['Total']).'</td>
                <td></td>
            </tr>
            <tr>
                <td>2</td>
                <td>Tổng</td>
                <td>'.number_format($invoice['OutstandingDebt']).'</td>
                <td>'.number_format($invoice['Total'] - $invoice['OutstandingDebt'] - $invoice['Discount']).'</td>
                <td>'.number_format($invoice['PaidAmount']).'</td>
                <td>'.number_format($invoice['Total']).'</td>
                <td></td>
            </tr>
        </tbody>
    </table>';
    
    // Tạo chi tiết dịch vụ
    $details_content = '';
    foreach($details as $index => $service) {
        $service_amount = $service['Quantity'] * $service['UnitPrice'];
        
        $details_content .= '
        <div style="margin-bottom: 20px;">
            <h4>'.($index+1).'/'.($service['ServiceName'] ?? 'Dịch vụ').'</h4>
            <table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse; width: 100%;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
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
                        <td>Tháng '.$invoice['InvoicePeriod'].'</td>
                        <td>'.$service['Quantity'].'</td>
                        <td>'.number_format($service['UnitPrice']).'</td>
                        <td>'.number_format($service_amount).'</td>
                    </tr>
                    <tr>
                        <td>Giảm giá/ Discount</td>
                        <td></td>
                        <td></td>
                        <td>'.number_format($service['Discount']).'</td>
                    </tr>
                    <tr>
                        <td>Thanh toán/ Paid</td>
                        <td></td>
                        <td></td>
                        <td>'.number_format($service['PaidAmount']).'</td>
                    </tr>
                </tbody>
            </table>
        </div>';
    }
    
    // Tạo nội dung email hoàn chỉnh
    $content = '
    <div style="font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto;">
        <div style="text-align: center; margin-bottom: 20px;">
            <h2>VĂN PHÒNG BAN QUẢN LÝ BUILDMATE</h2>
            <p>12 Chùa Bộc, Quang Trung, Đống Đa, Hà Nội</p>
            <p>Hotline CSKH: 0978343328 - Hotline kỹ thuật: 0978343328 - Hotline bảo vệ: 0978343328</p>
        </div>
        
        <div style="text-align: center; margin-bottom: 20px;">
            <h2>GIẤY BÁO PHÍ THÁNG '.$invoice['InvoicePeriod'].'</h2>
        </div>
        
        <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
            <div>
                <p><strong>Kính gửi/Respectfully:</strong> '.($resident['Name'] ?? $invoice['ApartmentName']).'</p>
                <p><strong>Mã số căn hộ/Apartment code:</strong> '.$invoice['ApartmentCode'].'</p>
            </div>
            <div style="text-align: right;">
                <p><strong>Số:</strong> '.$invoice['InvoiceCode'].'</p>
                <p><strong>Tổng tiền thanh toán/Total:</strong> '.number_format($invoice['Total']).' VNĐ</p>
            </div>
        </div>
        
        '.$summary_table.'
        
        <div style="margin-top: 20px;">
            <h3>THÔNG TIN CHI TIẾT/THE INFORMATION IN DETAIL</h3>
            '.$details_content.'
        </div>
        
        <div style="margin-top: 20px;">
            <h3>PHƯƠNG THỨC THANH TOÁN/PAYMENT METHODS</h3>
            
            <div style="margin-bottom: 15px;">
                <strong>1/Thanh toán tiền mặt/ By cash:</strong>
                <p>Tại Văn phòng ban quản lý Buildmate</p>
            </div>
            
            <div>
                <strong>2/Thanh toán chuyển khoản/ ByTranfer to:</strong>
                <table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse; width: 100%;">
                    <tr style="background-color: #f2f2f2;">
                        <th>Chủ tài khoản (Name)</th>
                        <th>Số tài khoản (Account number)</th>
                        <th>Ngân hàng (Bank)</th>
                    </tr>
                    <tr>
                        <td>Trần Thị Kim Anh</td>
                        <td>0888738572</td>
                        <td>Ngân hàng Quân đội MB Bank</td>
                    </tr>
                    <tr>
                        <td colspan="3">
                            <p>Nội dung chuyển khoản: "Căn hộ - Loại phí - Diễn giải". Ví dụ: "B1002 - PQL, Nước, Xe - T4/19"</p>
                            <p>Details Of Payment: "Apartment\'s code - Charge code - Noted". EX:"B1002 - Management fee, Water, Parking fee - T4/19 "</p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div style="font-size: 16px; margin-bottom: 20px;">
            <p><strong>Lưu ý:</strong></p>
            <ul>
                <li>Quý cư dân vui lòng thanh toán đúng hạn để tránh bị phạt chậm trả hoặc ngắt dịch vụ.</li>
                <li>Sau thời hạn thanh toán, Ban quản lý sẽ liên hệ trực tiếp với các căn hộ chưa đóng phí để nhắc nhở và hỗ trợ.</li>
                <li>Trường hợp quý cư dân có khó khăn về tài chính, vui lòng liên hệ với Ban quản lý để được hỗ trợ.</li>
                <li>Nếu sau 30 ngày kể từ ngày hết hạn thanh toán mà quý cư dân vẫn chưa đóng phí, Ban quản lý sẽ buộc phải ngắt một số dịch vụ tiện ích như điện, nước, wifi, v.v... cho đến khi quý cư dân thanh toán đầy đủ.</li>
            </ul>
        </div>

        <div style="display: flex; margin-top: 20px;">
            <div style="width: 70%;">
                <p style="font-size: 16px; margin-bottom: 20px;">Trân trọng cảm ơn!</p>
            </div>
            <div style="width: 30%; text-align: center;">
                <p>Hà Nội, Ngày '.$today.'</p>
                <p><strong>Trưởng ban quản lý</strong></p>
                <div style="height: 80px;"></div>
                <p><strong>'.$manager_name.'</strong></p>
            </div>
        </div>

        <p style="font-size: 16px; font-weight: bold;">Ban Quản Lý Tòa nhà Buildmate.</p>
    </div>';
    
    return $content;
}
?>
