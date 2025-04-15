<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Lấy danh sách dự án mà staff được phân quyền
$projects_query = "
    SELECT DISTINCT p.ProjectID, p.Name 
    FROM Projects p
    JOIN StaffProjects sp ON p.ProjectID = sp.ProjectId
    JOIN staffs s ON sp.StaffId = s.ID
    JOIN users u ON s.DepartmentId = u.DepartmentId
    WHERE u.UserId = '$admin_id' 
    AND p.Status = 'active'
    ORDER BY p.Name";
$projects_result = mysqli_query($conn, $projects_query);

// Lấy project_id từ URL
$selected_project = isset($_GET['project_id']) ? mysqli_real_escape_string($conn, $_GET['project_id']) : '';

// Xử lý xóa hợp đồng
if (isset($_GET['delete_id'])) {
    $contract_id = mysqli_real_escape_string($conn, $_GET['delete_id']);
    
    mysqli_begin_transaction($conn);
    try {
        // Xóa các dịch vụ của hợp đồng
        mysqli_query($conn, "DELETE FROM ContractServices WHERE ContractCode = '$contract_id'") 
            or throw new Exception("Không thể xóa dịch vụ của hợp đồng");
            
        // Xóa các phụ lục của hợp đồng
        mysqli_query($conn, "DELETE FROM ContractAppendixs WHERE ContractCode = '$contract_id'")
            or throw new Exception("Không thể xóa phụ lục của hợp đồng");
            
        // Xóa hợp đồng
        mysqli_query($conn, "DELETE FROM Contracts WHERE ContractCode = '$contract_id'")
            or throw new Exception("Không thể xóa hợp đồng");

        mysqli_commit($conn);
        $_SESSION['success_msg'] = 'Xóa hợp đồng thành công!';
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error_msg'] = 'Lỗi: ' . $e->getMessage();
    }
    
    header('location: contract_management.php');
    exit();
}

// Xử lý các tham số tìm kiếm và phân trang
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';

// Thiết lập phân trang
$records_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Sửa lại query đếm tổng số bản ghi với điều kiện tìm kiếm và filter theo dự án
$count_query = mysqli_query($conn, "
    SELECT COUNT(DISTINCT c.ContractCode) as total 
    FROM Contracts c
    LEFT JOIN apartment a ON a.ContractCode = c.ContractCode
    LEFT JOIN Buildings b ON a.BuildingId = b.ID
    LEFT JOIN ResidentApartment ra ON ra.ApartmentId = a.ApartmentID AND ra.Relationship = 'Chủ hộ'
    LEFT JOIN resident r ON r.ID = ra.ResidentId
    LEFT JOIN users u ON u.ResidentID = r.ID
    LEFT JOIN (
        SELECT ContractCode, CretionDate 
        FROM ContractAppendixs 
        WHERE Status = 'active'
        ORDER BY CretionDate DESC
        LIMIT 1
    ) ca ON ca.ContractCode = c.ContractCode
    WHERE " . ($selected_project ? "b.ProjectId = '$selected_project'" : "1=1") . " " .
    (!empty($search) ? "AND (c.ContractCode LIKE '%$search%' OR u.UserName LIKE '%$search%' OR a.Code LIKE '%$search%')" : "") .
    (!empty($status_filter) ? "AND c.Status = '$status_filter'" : "")
) or die('Count query failed: ' . mysqli_error($conn));

$total_records = mysqli_fetch_assoc($count_query)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Query lấy danh sách hợp đồng với thông tin chủ sở hữu và căn hộ
$status_condition = "";
if (!empty($status_filter)) {
    if ($status_filter == 'expiring') {
        $status_condition = "AND c.Status = 'active' AND c.EndDate IS NOT NULL 
                           AND c.EndDate > CURDATE() 
                           AND DATEDIFF(c.EndDate, CURDATE()) <= 30";
    } else {
        $status_condition = "AND c.Status = '$status_filter'";
    }
}

// Sửa lại query lấy danh sách hợp đồng
$select_contracts = mysqli_query($conn, "
    SELECT c.ContractCode, c.Status, 
           COALESCE(ca.CretionDate, c.CretionDate) as CretionDate, 
           c.EndDate,
           a.Code as ApartmentCode, a.Name as ApartmentName,
           u.UserName as OwnerName
    FROM Contracts c
    LEFT JOIN apartment a ON a.ContractCode = c.ContractCode
    LEFT JOIN Buildings b ON a.BuildingId = b.ID
    LEFT JOIN ResidentApartment ra ON ra.ApartmentId = a.ApartmentID AND ra.Relationship = 'Chủ hộ'
    LEFT JOIN resident r ON r.ID = ra.ResidentId
    LEFT JOIN users u ON u.ResidentID = r.ID
    LEFT JOIN (
        SELECT ContractCode, CretionDate 
        FROM ContractAppendixs 
        WHERE Status = 'active'
        ORDER BY CretionDate DESC
        LIMIT 1
    ) ca ON ca.ContractCode = c.ContractCode
    WHERE " . ($selected_project ? "b.ProjectId = '$selected_project'" : "1=1") . " " .
    (!empty($search) ? "AND (c.ContractCode LIKE '%$search%' OR u.UserName LIKE '%$search%' OR a.Code LIKE '%$search%')" : "") .
    $status_condition . "
    ORDER BY COALESCE(ca.CretionDate, c.CretionDate) DESC
    LIMIT $offset, $records_per_page
") or die('Query failed: ' . mysqli_error($conn));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý hợp đồng</title>

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

        .contract-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }

        .contract-table th {
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

        .status-active {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-expired {
            background: #ffebee;
            color: #c62828;
        }

        .status-cancelled {
            background: #fafafa;
            color: #616161;
        }

        .status-pending {
            background: #fff3e0;
            color: #ef6c00;
        }

        .status-modified {
            background: #e3f2fd;
            color: #1565c0;
        }

        .status-expiring {
            background: #fff3cd;
            color: #856404;
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

        .action-icon {
            color: #666;
            margin: 0 5px;
            font-size: 16px;
            text-decoration: none;
        }
        
        .action-icon:hover:not(.disabled) {
            color: #476a52;
        }
        
        .action-icon.disabled {
            pointer-events: none;
        }
        
        .fa-edit { color: #2196F3; }
        .fa-trash-alt { color: #F44336; }
        .fa-sync-alt { color: #4CAF50; }
        .fa-times-circle { color: #FF9800; }
        
        /* Khi disable thì icon sẽ có màu xám */
        .disabled i {
            color: #999 !important;
        }

        .contract-table tbody tr {
            cursor: pointer;
        }
        
        .contract-table tbody tr:hover {
            background-color: #f5f5f5;
        }
        
        /* Đảm bảo các icon không bị ảnh hưởng bởi click của tr */
        .action-icon {
            position: relative;
            z-index: 2;
        }

        .project-filter {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .project-filter select {
            border: 1px solid #ddd;
            padding: 8px 12px;
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <?php include '../admin_navbar.php'; ?>
        <div style="width: 100%;">
            <?php include '../admin_header.php'; ?>
            <div class="manage-container p-4">
                <div class="page-header">
                    <h2 style="font-weight: bold; color: #476a52; margin-bottom: 10px; text-transform: uppercase;">QUẢN LÝ HỢP ĐỒNG</h2>
                    <div class="breadcrumb">
                        <a href="dashboard.php">Trang chủ</a>
                        <span style="margin: 0 8px;">›</span>
                        <span>Quản lý hợp đồng</span>
                    </div>
                </div>

                <!-- Thêm ngay sau div class="page-header" và trước div class="search-container" -->
                <div class="project-filter mb-4">
                    <select class="form-select" style="width: 300px;" 
                            onchange="window.location.href='contract_management.php?project_id='+this.value">
                        <option value="">Chọn dự án</option>
                        <?php while($project = mysqli_fetch_assoc($projects_result)) { ?>
                            <option value="<?php echo $project['ProjectID']; ?>" 
                                    <?php echo ($selected_project == $project['ProjectID']) ? 'selected' : ''; ?>>
                                <?php echo $project['Name']; ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <?php if(isset($_SESSION['success_msg'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= $_SESSION['success_msg']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success_msg']); ?>
                <?php endif; ?>

                <?php if(isset($_SESSION['error_msg'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= $_SESSION['error_msg']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error_msg']); ?>
                <?php endif; ?>

                <!-- Search Section -->
                <div class="search-container">
                    <form action="" method="GET" class="row g-3">
                        <div class="col-md-4">
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Nhập từ khóa tìm kiếm..." 
                                   value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        </div>
                        <div class="col-md-3">
                            <select name="status" class="form-select">
                                <option value="">Trạng thái</option>
                                <option value="pending" <?php echo ($status_filter == 'pending') ? 'selected' : ''; ?>>Khởi tạo</option>
                                <option value="active" <?php echo ($status_filter == 'active') ? 'selected' : ''; ?>>Hoạt động</option>
                                <option value="expiring" <?php echo ($status_filter == 'expiring') ? 'selected' : ''; ?>>Sắp hết hạn</option>
                                <option value="modified" <?php echo ($status_filter == 'modified') ? 'selected' : ''; ?>>Đã điều chỉnh bởi phụ lục</option>
                                <option value="expired" <?php echo ($status_filter == 'expired') ? 'selected' : ''; ?>>Đã hết hạn</option>
                                <option value="cancelled" <?php echo ($status_filter == 'cancelled') ? 'selected' : ''; ?>>Hủy</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-search"></i> Tìm kiếm
                            </button>
                        </div>
                        <div class="col-md-3 text-end">
                            <?php if(empty($selected_project)): ?>
                                <button type="button" class="btn add-btn" onclick="showProjectAlert()">
                                    <i class="fas fa-plus"></i> Thêm mới
                                </button>
                            <?php else: ?>
                                <a href="create_contract.php?project_id=<?php echo $selected_project; ?>" class="btn add-btn">
                                    <i class="fas fa-plus"></i> Thêm mới
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <!-- Contract Table -->
                <div class="contract-table">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>STT</th>
                                <th>MÃ HỢP ĐỒNG</th>
                                <th>CHỦ SỞ HỮU</th>
                                <th>CĂN HỘ</th>
                                <th>NGÀY HIỆU LỰC</th>
                                <th>TRẠNG THÁI</th>
                                <th>THAO TÁC</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if(mysqli_num_rows($select_contracts) > 0){
                                $stt = $offset + 1;
                                while($contract = mysqli_fetch_assoc($select_contracts)){
                                    // Xác định class cho trạng thái
                                    $status_class = '';
                                    $status_text = '';
                                    
                                    $current_date = new DateTime();
                                    $end_date = new DateTime($contract['EndDate']);
                                    $interval = $current_date->diff($end_date);
                                    $days_remaining = $interval->days;
                                    $is_future = $end_date > $current_date;

                                    // Kiểm tra nếu còn 30 ngày và chưa hết hạn
                                    if ($contract['Status'] == 'active' && $is_future && $days_remaining <= 30) {
                                        $status_class = 'status-expiring';
                                        $status_text = 'Sắp hết hạn';
                                    } else {
                                        switch($contract['Status']) {
                                            case 'active':
                                                $status_class = 'status-active';
                                                $status_text = 'Hoạt động';
                                                break;
                                            case 'cancelled':
                                                $status_class = 'status-cancelled';
                                                $status_text = 'Hủy';
                                                break;
                                            case 'expired':
                                                $status_class = 'status-expired';
                                                $status_text = 'Đã hết hạn';
                                                break;
                                            case 'pending':
                                                $status_class = 'status-pending';
                                                $status_text = 'Khởi tạo';
                                                break;
                                            case 'modified':
                                                $status_class = 'status-modified';
                                                $status_text = 'Đã điều chỉnh bởi phụ lục';
                                                break;
                                            default:
                                                $status_text = $contract['Status'];
                                        }
                                    }
                            ?>
                            <tr onclick="viewContractDetail('<?php echo $contract['ContractCode']; ?>')" style="cursor: pointer;">
                                <td onclick="event.stopPropagation()"><?php echo $stt++; ?></td>
                                <td><?php echo $contract['ContractCode']; ?></td>
                                <td><?php echo $contract['OwnerName'] ?? '-'; ?></td>
                                <td><?php echo $contract['ApartmentCode'] ? $contract['ApartmentCode'] . ' - ' . $contract['ApartmentName'] : '-'; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($contract['CretionDate'])); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td onclick="event.stopPropagation()">
                                    <?php 
                                    $isPending = $contract['Status'] == 'pending';
                                    $isCancelled = $contract['Status'] == 'cancelled';
                                    $isExpired = $contract['Status'] == 'expired';
                                    
                                    if(empty($selected_project)): ?>
                                        <button type="button" class="action-icon" onclick="showProjectAlert()">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="action-icon" onclick="showProjectAlert()">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                        <button type="button" class="action-icon" onclick="showProjectAlert()">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                        <button type="button" class="action-icon" onclick="showProjectAlert()">
                                            <i class="fas fa-times-circle"></i>
                                        </button>
                                    <?php else: ?>
                                        <!-- Giữ nguyên các nút thao tác hiện tại với thêm project_id vào URL -->
                                        <a href="update_contract.php?contract_code=<?php echo $contract['ContractCode']; ?>&project_id=<?php echo $selected_project; ?>" 
                                           class="action-icon <?php echo !$isPending ? 'disabled' : ''; ?>" 
                                           title="Sửa"
                                           <?php echo !$isPending ? 'onclick="return false;"' : ''; ?>>
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <!-- Tương tự cho các nút khác -->
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php
                                }
                            } else {
                                echo '<tr><td colspan="7" class="text-center">Không có dữ liệu</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <div>Tổng số: <?php echo $total_records; ?> bản ghi</div>
                    <nav aria-label="Page navigation">
                        <ul class="pagination mb-0">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=1<?php 
                                        echo !empty($search) ? "&search=$search" : '';
                                        echo !empty($status_filter) ? "&status=$status_filter" : '';
                                        echo "&per_page=$records_per_page";
                                    ?>">«</a>
                                </li>
                            <?php endif; ?>

                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);

                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php 
                                        echo !empty($search) ? "&search=$search" : '';
                                        echo !empty($status_filter) ? "&status=$status_filter" : '';
                                        echo "&per_page=$records_per_page";
                                    ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $total_pages; ?><?php 
                                        echo !empty($search) ? "&search=$search" : '';
                                        echo !empty($status_filter) ? "&status=$status_filter" : '';
                                        echo "&per_page=$records_per_page";
                                    ?>">»</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <div class="d-flex align-items-center">
                        <span class="me-2">Hiển thị</span>
                        <select class="form-select" style="width: auto;" onchange="window.location.href=this.value">
                            <?php foreach ([10, 25, 50, 100] as $per_page): ?>
                                <option value="?page=1<?php 
                                    echo !empty($search) ? "&search=$search" : '';
                                    echo !empty($status_filter) ? "&status=$status_filter" : '';
                                    echo "&per_page=$per_page";
                                ?>" <?php echo ($records_per_page == $per_page) ? 'selected' : ''; ?>>
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
        function viewContractDetail(contractCode) {
            window.location.href = `view_contract.php?contract_code=${contractCode}`;
        }

        function showProjectAlert() {
            var alertHtml = `
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <strong>Thông báo!</strong> Vui lòng chọn dự án trước khi thực hiện thao tác này.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            
            var existingAlert = document.querySelector('.alert');
            if (existingAlert) {
                existingAlert.remove();
            }
            
            var container = document.querySelector('.manage-container');
            container.insertAdjacentHTML('afterbegin', alertHtml);
            
            setTimeout(function() {
                var alert = document.querySelector('.alert');
                if (alert) {
                    var bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            }, 3000);
        }

        // Cập nhật URL khi chọn dự án
        document.querySelector('.project-select').addEventListener('change', function() {
            var projectId = this.value;
            var currentUrl = new URL(window.location.href);
            if (projectId) {
                currentUrl.searchParams.set('project_id', projectId);
            } else {
                currentUrl.searchParams.delete('project_id');
            }
            window.location.href = currentUrl.toString();
        });
    </script>
</body>
</html>