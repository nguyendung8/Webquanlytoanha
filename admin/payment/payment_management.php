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
$type_filter = isset($_GET['type']) ? mysqli_real_escape_string($conn, $_GET['type']) : '';
$payment_method = isset($_GET['payment_method']) ? mysqli_real_escape_string($conn, $_GET['payment_method']) : '';
$staff_filter = isset($_GET['staff']) ? mysqli_real_escape_string($conn, $_GET['staff']) : '';

// Thiết lập phân trang
$records_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 7;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Xây dựng câu query với điều kiện tìm kiếm
$where_conditions = [];
if (!empty($search)) {
    $where_conditions[] = "p.PaymentID LIKE '%$search%'";
}
if (!empty($building_filter)) {
    $where_conditions[] = "a.BuildingId = '$building_filter'";
}
if (!empty($apartment_filter)) {
    $where_conditions[] = "p.ApartmentID = '$apartment_filter'";
}
if (!empty($type_filter)) {
    $where_conditions[] = "r.TransactionType = '$type_filter'";
}
if (!empty($payment_method)) {
    $where_conditions[] = "p.PaymentMethod = '$payment_method'";
}
if (!empty($staff_filter)) {
    $where_conditions[] = "p.StaffID = '$staff_filter'";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Query đếm tổng số bản ghi
$count_query = mysqli_query($conn, "
    SELECT COUNT(*) as total 
    FROM payments p
    LEFT JOIN apartment a ON p.ApartmentID = a.ApartmentID 
    $where_clause
");
$total_records = mysqli_fetch_assoc($count_query)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Query lấy danh sách phiếu thu/chi
$query = "
    SELECT p.*, a.Code as ApartmentCode, b.Name as BuildingName, 
           s.Name as StaffName, s2.Name as DeletedByName
    FROM payments p
    LEFT JOIN apartment a ON p.ApartmentID = a.ApartmentID
    LEFT JOIN Buildings b ON a.BuildingId = b.ID
    LEFT JOIN staffs s ON p.StaffID = s.ID
    LEFT JOIN staffs s2 ON p.DeletedBy = s2.ID
    $where_clause
    ORDER BY p.IssueDate DESC, p.PaymentID DESC
    LIMIT $offset, $records_per_page
";

$select_payments = mysqli_query($conn, $query);

// Lấy danh sách căn hộ cho filter
$select_apartments = mysqli_query($conn, "SELECT ApartmentID, Code, Name FROM apartment");

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý phiếu thu/chi</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
        .receipt-table th {
            background: #6b8b7b !important;
            color: white;
        }

        /* Custom styling for tabs */
        .nav-tabs-custom {
            margin-bottom: 20px;
            background: #fff;
            border: none;
        }

        .nav-tabs-custom > .nav-tabs {
            margin: 0;
            border: none;
            display: flex;
            gap: 2px;
        }

        .nav-tabs-custom > .nav-tabs > li {
            margin: 0;
        }

        .nav-tabs-custom > .nav-tabs > li > a {
            margin: 0;
            padding: 12px 25px;
            color: #333;
            background: #f5f5f5;
            border: none;
            border-radius: 0;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        /* Active tab styling */
        .nav-tabs-custom > .nav-tabs > li.active > a,
        .nav-tabs-custom > .nav-tabs > li.active > a:hover,
        .nav-tabs-custom > .nav-tabs > li.active > a:focus {
            background-color: #8AA989; /* Màu xanh lá nhạt như trong hình */
            color: #fff;
            border: none;
        }

        /* Hover effect for tabs */
        .nav-tabs-custom > .nav-tabs > li > a:hover {
            background-color: #9BB99A;
            color: #fff;
        }

        /* Tab content container */
        .tab-content {
            background: #fff;
            padding: 20px;
            border: 1px solid #ddd;
            border-top: none;
        }

        /* Container for the entire tabs section */
        .tabs-section {
            margin: 20px 0;
        }

        /* Make tabs full width on mobile */
        @media (max-width: 768px) {
            .nav-tabs-custom > .nav-tabs {
                display: flex;
                flex-direction: column;
            }
            
            .nav-tabs-custom > .nav-tabs > li {
                width: 100%;
            }
            
            .nav-tabs-custom > .nav-tabs > li > a {
                text-align: center;
            }
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
                    <h2 class="text-uppercase fw-bold" style="color: #476a52;">Quản lý phiếu thu/chi</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="/admin/dashboard.php">Trang chủ</a></li>
                            <li class="breadcrumb-item active">Quản lý phiếu thu/chi</li>
                        </ol>
                    </nav>
                </div>

                <!-- Updated HTML Structure -->
                <div class="tabs-section">
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li >
                                <a style="text-decoration: none;" href="payment_receipt.php">Quản lý phiếu thu</a>
                            </li>
                            <li class="active">
                                <a style="text-decoration: none;" href="payment_management.php">Quản lý phiếu chi</a>
                            </li>
                        </ul>
                    </div>

                    <div class="tab-content">
                        <!-- Phiếu Thu Tab -->
                        <div class="tab-pane active" id="receipt-tab">
                            <div class="search-container">
                                <form class="row g-3">
                                    <div class="col-md-2">
                                        <input type="text" class="form-control" name="search" placeholder="Mã phiếu" value="<?php echo $search; ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <select class="form-select" name="building">
                                            <option value="">Chọn tòa nhà</option>
                                            <?php while($building = mysqli_fetch_assoc($select_buildings)) { ?>
                                                <option value="<?php echo $building['ID']; ?>" <?php echo ($building_filter == $building['ID']) ? 'selected' : ''; ?>>
                                                    <?php echo $building['Name']; ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select class="form-select" name="apartment">
                                            <option value="">Chọn căn hộ</option>
                                            <?php while($apt = mysqli_fetch_assoc($select_apartments)) { ?>
                                                <option value="<?php echo $apt['ApartmentID']; ?>" <?php echo ($apartment_filter == $apt['ApartmentID']) ? 'selected' : ''; ?>>
                                                    <?php echo $apt['Code'] . ' - ' . $apt['Name']; ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select class="form-select" name="type">
                                            <option value="">Hình thức</option>
                                            <option value="Thu" <?php echo ($type_filter == 'Thu') ? 'selected' : ''; ?>>Phiếu thu</option>
                                            <option value="Chi" <?php echo ($type_filter == 'Chi') ? 'selected' : ''; ?>>Phiếu chi</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select class="form-select" name="payment_method">
                                            <option value="">Chọn hình thức</option>
                                            <option value="Tiền mặt" <?php echo ($payment_method == 'Tiền mặt') ? 'selected' : ''; ?>>Tiền mặt</option>
                                            <option value="Chuyển khoản" <?php echo ($payment_method == 'Chuyển khoản') ? 'selected' : ''; ?>>Chuyển khoản</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="submit" class="btn btn-success w-100">
                                            <i class="fas fa-search"></i> Tìm kiếm
                                        </button>
                                    </div>
                                </form>
                                <div class="mt-3 d-flex justify-content-end">
                                    <a href="create_payment.php" class="btn btn-danger">
                                        <i class="fas fa-plus"></i> Lập phiếu chi
                                    </a>
                                </div>
                            </div>

                            <!-- Receipts Table -->
                            <div class="table-responsive">
                                <table class="table table-bordered receipt-table">
                                    <thead>
                                        <tr>
                                            <th>STT</th>
                                            <th>Mã chứng từ</th>
                                            <th>Hình thức</th>
                                            <th>Số tiền</th>
                                            <th>Người tạo</th>
                                            <th>Căn hộ</th>
                                            <th>Số tiền</th>
                                            <th>Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        if(mysqli_num_rows($select_payments) > 0){
                                            $i = $offset + 1;
                                            while($payment = mysqli_fetch_assoc($select_payments)){
                                        ?>
                                        <tr>
                                            <td><?php echo $i++; ?></td>
                                            <td><?php echo $payment['PaymentID']; ?></td>
                                            <td><?php echo $payment['PaymentMethod']; ?></td>
                                            <td><?php echo number_format($payment['Total']); ?> VNĐ</td>
                                            <td>
                                                <?php
                                                $staff_id = $payment['StaffID'];
                                                $select_staff = mysqli_query($conn, "SELECT * FROM users WHERE UserId = '$staff_id'");
                                                $staff = mysqli_fetch_assoc($select_staff);
                                                echo $staff['UserName'];
                                                ?>
                                            </td>
                                            <td><?php echo $payment['ApartmentCode']; ?></td>
                                            <td><?php echo number_format($payment['Total']); ?> VNĐ</td>
                                            <td>

                                                <a href="update_payment.php?id=<?php echo $payment['PaymentID']; ?>" class="btn btn-sm btn-warning" title="Sửa">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <!-- <button class="btn btn-sm btn-danger delete-receipt" data-id="<?php echo $payment['PaymentID']; ?>" title="Xóa">
                                                    <i class="fas fa-trash"></i>
                                                </button> -->

                                            </td>
                                        </tr>
                                        <?php
                                            }
                                        } else {
                                            echo '<tr><td colspan="11" class="text-center">Không có dữ liệu</td></tr>';
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
                                                echo !empty($type_filter) ? "&type=$type_filter" : '';
                                                echo !empty($payment_method) ? "&payment_method=$payment_method" : '';
                                            ?>"><?php echo $i; ?></a>
                                        </li>
                                        <?php endfor; ?>
                                    </ul>
                                </nav>
                            </div>
                        </div>

                        <!-- Phiếu Chi Tab -->
                        <div class="tab-pane" id="payment-tab">
                            <div class="search-container">
                                <!-- Search Form -->
                                <div class="row g-3 mb-4">
                                    <div class="col-md-2">
                                        <select class="form-select" name="building" id="building-filter">
                                            <option value="">Chọn tòa nhà</option>
                                            <?php while($building = mysqli_fetch_assoc($select_buildings)) { ?>
                                                <option value="<?php echo $building['ID']; ?>">
                                                    <?php echo $building['Name']; ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select class="form-select" name="apartment" id="apartment-filter">
                                            <option value="">Chọn căn hộ</option>
                                            <!-- Sẽ được populate bằng AJAX khi chọn tòa nhà -->
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select class="form-select" name="payment_method">
                                            <option value="">Chọn hình thức</option>
                                            <option value="Tiền mặt">Tiền mặt</option>
                                            <option value="Chuyển khoản">Chuyển khoản</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select class="form-select" name="staff">
                                            <option value="">Chọn người chi</option>
                                            <!-- Populate từ bảng staffs -->
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="submit" class="btn btn-success w-100">
                                            <i class="fas fa-search"></i> Tìm kiếm
                                        </button>
                                    </div>
                                </div>

                                <!-- Date Range Filters -->
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label">Ngày hạch toán</label>
                                        <div class="d-flex gap-2">
                                            <input type="date" class="form-control" name="accounting_date_from" placeholder="Từ ngày">
                                            <input type="date" class="form-control" name="accounting_date_to" placeholder="Đến ngày">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Ngày lập phiếu</label>
                                        <div class="d-flex gap-2">
                                            <input type="date" class="form-control" name="issue_date_from" placeholder="Từ ngày">
                                            <input type="date" class="form-control" name="issue_date_to" placeholder="Đến ngày">
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="d-flex justify-content-end mb-4">
                                    <a href="create_payment.php" class="btn btn-success me-2">
                                        <i class="fas fa-plus"></i> Lập phiếu chi
                                    </a>
                                    <button class="btn btn-primary">
                                        <i class="fas fa-file-export"></i> Export
                                    </button>
                                </div>

                                <!-- Payments Table -->
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>STT</th>
                                                <th>Mã chứng từ</th>
                                                <th>Hình thức</th>
                                                <th>Số phiếu</th>
                                                <th>Ngày lập phiếu</th>
                                                <th>Ngày hạch toán</th>
                                                <th>Căn hộ</th>
                                                <th>Tòa</th>
                                                <th>Số tiền</th>
                                                <th>Người tạo</th>
                                                <th>Người xóa</th>
                                                <th>Thao tác</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            if(mysqli_num_rows($select_payments) > 0){
                                                $i = $offset + 1;
                                                while($payment = mysqli_fetch_assoc($select_payments)){
                                            ?>
                                            <tr>
                                                <td><?php echo $i++; ?></td>
                                                <td>PC_<?php echo $payment['PaymentID']; ?></td>
                                                <td><?php echo $payment['PaymentMethod']; ?></td>
                                                <td><?php echo str_pad($payment['PaymentID'], 7, '0', STR_PAD_LEFT); ?></td>
                                                <td><?php echo date('Y-m-d', strtotime($payment['IssueDate'])); ?></td>
                                                <td><?php echo date('Y-m-d', strtotime($payment['AccountingDate'])); ?></td>
                                                <td><?php echo $payment['ApartmentCode']; ?></td>
                                                <td><?php echo $payment['BuildingName']; ?></td>
                                                <td class="text-end"><?php echo number_format($payment['Total'], 0, ',', '.'); ?></td>
                                                <td><?php echo $payment['StaffName']; ?></td>
                                                <td><?php echo $payment['DeletedByName'] ?? ''; ?></td>
                                                <td>
                                                    <div class="d-flex gap-1">
                                                        <a href="edit_payment.php?id=<?php echo $payment['PaymentID']; ?>" 
                                                           class="btn btn-sm btn-warning" title="Sửa">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button class="btn btn-sm btn-danger delete-payment" 
                                                                data-id="<?php echo $payment['PaymentID']; ?>" title="Xóa">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                        <a href="print_payment.php?id=<?php echo $payment['PaymentID']; ?>" 
                                                           class="btn btn-sm btn-info" title="In">
                                                            <i class="fas fa-print"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php
                                                }
                                            } else {
                                                echo '<tr><td colspan="12" class="text-center">Không có dữ liệu</td></tr>';
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
                                                    echo !empty($building_filter) ? "&building=$building_filter" : '';
                                                    echo !empty($apartment_filter) ? "&apartment=$apartment_filter" : '';
                                                    echo !empty($payment_method) ? "&payment_method=$payment_method" : '';
                                                    echo !empty($staff_filter) ? "&staff=$staff_filter" : '';
                                                ?>"><?php echo $i; ?></a>
                                            </li>
                                            <?php endfor; ?>
                                        </ul>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Xử lý xóa phiếu thu/chi
        $('.delete-receipt').click(function() {
            if(confirm('Bạn có chắc chắn muốn xóa phiếu này?')) {
                const receiptId = $(this).data('id');
                // Thực hiện AJAX call để xóa phiếu
                $.post('delete_receipt.php', {receipt_id: receiptId}, function(response) {
                    if(response.success) {
                        location.reload();
                    } else {
                        alert('Có lỗi xảy ra khi xóa phiếu!');
                    }
                });
            }
        });
    </script>
</body>
</html>