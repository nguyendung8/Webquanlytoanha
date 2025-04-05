<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Lấy danh sách phòng ban cho dropdown
$select_departments = mysqli_query($conn, "SELECT * FROM Departments ORDER BY Name");

// Xử lý xóa nhân viên
if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    $delete_query = mysqli_query($conn, "DELETE FROM Staffs WHERE ID = '$delete_id'") or die('Query failed');

    if ($delete_query) {
        $message[] = 'Xóa nhân viên thành công!';
    } else {
        $message[] = 'Xóa nhân viên thất bại!';
    }
}

// Xử lý phân trang
$records_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Xử lý tìm kiếm và lọc
$search_term = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$department_filter = isset($_GET['department']) ? mysqli_real_escape_string($conn, $_GET['department']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';

// Xây dựng câu query với điều kiện tìm kiếm
$where_conditions = [];
if (!empty($search_term)) {
    $where_conditions[] = "(s.Name LIKE '%$search_term%' OR s.Email LIKE '%$search_term%' OR s.PhoneNumber LIKE '%$search_term%')";
}
if (!empty($department_filter)) {
    $where_conditions[] = "s.DepartmentId = '$department_filter'";
}
if (!empty($status_filter)) {
    $where_conditions[] = "s.Status = '$status_filter'";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Đếm tổng số bản ghi
$count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM Staffs s $where_clause");
$total_records = mysqli_fetch_assoc($count_query)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Lấy danh sách nhân viên
$select_staffs = mysqli_query($conn, "
    SELECT s.*, d.Name as DepartmentName 
    FROM Staffs s 
    LEFT JOIN Departments d ON s.DepartmentId = d.ID 
    $where_clause 
    ORDER BY s.ID 
    LIMIT $offset, $records_per_page
");

// Xử lý cập nhật trạng thái qua AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    header('Content-Type: application/json');
    
    if (!isset($_POST['staff_id']) || !isset($_POST['status'])) {
        die(json_encode(['success' => false, 'message' => 'Missing parameters']));
    }

    $staff_id = mysqli_real_escape_string($conn, $_POST['staff_id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    // Kiểm tra giá trị status hợp lệ
    if (!in_array($status, ['active', 'inactive'])) {
        die(json_encode(['success' => false, 'message' => 'Invalid status value']));
    }

    // Thực hiện cập nhật
    $update_query = mysqli_query($conn, "UPDATE Staffs SET Status = '$status' WHERE ID = '$staff_id'");

    if ($update_query) {
        die(json_encode(['success' => true]));
    } else {
        die(json_encode([
            'success' => false, 
            'message' => 'Database update failed: ' . mysqli_error($conn)
        ]));
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý nhân viên</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="css/admin_style.css">
    <style>
        body {
            background-color: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
        
        .search-container {
            display: flex;
            justify-content: space-between;
            margin: 20px 0;
            gap: 10px;
        }
        
        .search-box {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .search-input {
            width: 220px;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 50px;
            outline: none;
        }
        
        .dropdown-select {
            width: 220px;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 50px;
            outline: none;
            background-color: white;
            cursor: pointer;
        }
        
        .search-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 50px;
            background-color: #f8b427;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .add-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 50px;
            background-color: #476a52;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .account-table {
            width: 100%;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            margin-top: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .account-table th {
            background-color: #6b8b7b !important;
            color: white;
            text-align: left;
            padding: 15px;
            font-weight: 500;
        }
        
        .account-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #f2f2f2;
            color: #4a5568;
        }
        
        .account-table tr:hover {
            background-color: #f9fafb;
        }
        
        .action-btn {
            padding: 6px 12px;
            border-radius: 50px;
            background-color: #f8b427;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
        }
        
        .action-icon {
            color: #718096;
            cursor: pointer;
            margin: 0 5px;
            font-size: 20px;
        }
        
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            font-size: 14px;
            color: #4a5568;
        }
        
        .page-controls {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .page-item {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #ddd;
            cursor: pointer;
        }
        
        .page-item.active {
            background-color: #476a52;
            color: white;
            border-color: #476a52;
        }
        
        .items-per-page {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .dropdown-per-page {
            padding: 5px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: white;
        }

        .manage-container {
            width: 100%;
            padding: 20px;
        }

        .filter-select {
            height: 40px;
            padding: 0 15px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background-color: white;
            color: #4a5568;
            font-size: 14px;
            cursor: pointer;
            min-width: 160px;
        }

        .search-btn {
            height: 40px;
            padding: 0 20px;
            border: none;
            border-radius: 8px;
            background-color: #4a5568;
            color: white;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .search-btn i {
            font-size: 14px;
        }

        .btn-add {
            height: 40px;
            padding: 0 20px;
            border: none;
            border-radius: 8px;
            background-color: #476a52;
            color: white;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-add:hover {
            background-color: #476a52;
            color: white;
        }

        .status-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .status-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .switch-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .switch-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .switch-slider {
            background-color: #4CAF50;
        }

        input:checked + .switch-slider:before {
            transform: translateX(26px);
        }

        .status-label {
            margin-left: 10px;
            font-size: 14px;
            color: #4a5568;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <?php include '../admin_navbar.php'; ?>
            <div style="width: 100%;">
            <?php include '../admin_header.php'; ?>
            <div class="manage-container">
                <?php
                //nhúng vào các trang bán hàng
                if (isset($message)) { // hiển thị thông báo sau khi thao tác với biến message được gán giá trị
                    foreach ($message as $msg) {
                        echo '
                        <div class=" alert alert-info alert-dismissible fade show" role="alert">
                            <span style="font-size: 16px;">' . $msg . '</span>
                            <i style="font-size: 20px; cursor: pointer" class="fas fa-times" onclick="this.parentElement.remove();"></i>
                        </div>';
                    }
                }
                ?>
                
                <!-- Page Header -->
                <div class="page-header">
                    <h2 style="font-weight: bold; color: #476a52; margin-bottom: 10px; text-transform: uppercase;">DANH SÁCH NHÂN VIÊN</h2>
                    <div class="breadcrumb">
                        <a href="dashboard.php">Trang chủ</a>
                        <span style="margin: 0 8px;">›</span>
                        <span>Nhân viên công ty</span>
                    </div>
                </div>
                
                <!-- Search and Filter Section -->
                <div class="search-container">
                    <form action="" method="GET" class="d-flex gap-3">
                        <input type="text" name="search" class="filter-select" 
                            placeholder="Tên, Email, SĐT" 
                            value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        
                        <select name="department" class="filter-select">
                            <option value="">Phòng ban</option>
                            <?php 
                            mysqli_data_seek($select_departments, 0);
                            while($dept = mysqli_fetch_assoc($select_departments)) { 
                            ?>
                                <option value="<?php echo $dept['ID']; ?>" 
                                    <?php echo ($department_filter == $dept['ID']) ? 'selected' : ''; ?>>
                                    <?php echo $dept['Name']; ?>
                                </option>
                            <?php } ?>
                        </select>

                        <select name="status" class="filter-select">
                            <option value="">Trạng thái</option>
                            <option value="active" <?php echo ($status_filter == 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($status_filter == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>

                        <button type="submit" class="search-btn">
                            <i class="fas fa-search"></i> Tìm kiếm
                        </button>
                    </form>

                    <a href="create_employee.php" class="btn-add">
                        <i class="fas fa-plus"></i> Thêm nhân viên
                    </a>
                </div>
                
                <!-- Staff Table -->
                <table class="account-table">
                    <thead>
                        <tr>
                            <th>STT</th>
                            <th>Mã nhân viên</th>
                            <th>Họ và tên</th>
                            <th>Email</th>
                            <th>SĐT</th>
                            <th>Phòng ban</th>
                            <th>Status</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if(mysqli_num_rows($select_staffs) > 0) {
                            $counter = $offset + 1;
                            while($staff = mysqli_fetch_assoc($select_staffs)) {
                                ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo $staff['ID']; ?></td>
                                    <td><?php echo $staff['Name']; ?></td>
                                    <td><?php echo $staff['Email']; ?></td>
                                    <td><?php echo $staff['PhoneNumber']; ?></td>
                                    <td><?php echo $staff['DepartmentName'] ?? 'Chưa phân công'; ?></td>
                                    <td style="display: flex;flex-direction: column; justify-content: center;">
                                        <label class="status-switch">
                                            <input type="checkbox" 
                                                <?php echo $staff['Status'] == 'active' ? 'checked' : ''; ?>
                                                onchange="updateStatus(<?php echo $staff['ID']; ?>, this.checked, this)">
                                            <span class="switch-slider"></span>
                                        </label>
                                        <span class="status-label" id="status-label-<?php echo $staff['ID']; ?>">
                                            <?php echo $staff['Status'] == 'active' ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="update_employee.php?id=<?php echo $staff['ID']; ?>" class="action-icon"><i class="far fa-edit"></i></a>
                                        <a href="?delete=<?php echo $staff['ID']; ?>" 
                                        class="action-btn"
                                        onclick="return confirm('Bạn có chắc chắn muốn xóa nhân viên này?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo '<tr><td colspan="8" class="text-center">Không có nhân viên nào</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <div class="pagination">
                    <div class="total-count">Tổng số: <?php echo $total_records; ?> bản ghi</div>
                    <div class="page-controls">
                        <?php if($total_pages > 1): ?>
                            <?php if ($page > 1): ?>
                                <a href="?page=1<?php echo !empty($search_term) ? '&search='.urlencode($search_term) : ''; ?><?php echo !empty($department_filter) ? '&department='.$department_filter : ''; ?><?php echo !empty($status_filter) ? '&status='.$status_filter : ''; ?>" class="page-item">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++): 
                            ?>
                                <a href="?page=<?php echo $i; ?><?php echo !empty($search_term) ? '&search='.urlencode($search_term) : ''; ?><?php echo !empty($department_filter) ? '&department='.$department_filter : ''; ?><?php echo !empty($status_filter) ? '&status='.$status_filter : ''; ?>" 
                                class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $total_pages; ?><?php echo !empty($search_term) ? '&search='.urlencode($search_term) : ''; ?><?php echo !empty($department_filter) ? '&department='.$department_filter : ''; ?><?php echo !empty($status_filter) ? '&status='.$status_filter : ''; ?>" 
                                class="page-item">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="items-per-page">
                        <span>Hiển thị</span>
                        <select class="dropdown-per-page" onchange="window.location.href='?page=1&per_page='+this.value<?php echo !empty($search_term) ? '&search='.urlencode($search_term) : ''; ?><?php echo !empty($department_filter) ? '&department='.$department_filter : ''; ?><?php echo !empty($status_filter) ? '&status='.$status_filter : ''; ?>">
                            <option value="10" <?php echo ($records_per_page == 10) ? 'selected' : ''; ?>>10</option>
                            <option value="20" <?php echo ($records_per_page == 20) ? 'selected' : ''; ?>>20</option>
                            <option value="50" <?php echo ($records_per_page == 50) ? 'selected' : ''; ?>>50</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editStaff(staff) {
            document.getElementById('edit_staff_id').value = staff.ID;
            document.getElementById('edit_staff_name').value = staff.Name;
            document.getElementById('edit_staff_email').value = staff.Email;
            document.getElementById('edit_staff_phone').value = staff.PhoneNumber;
            document.getElementById('edit_staff_department').value = staff.DepartmentId || '';
            document.getElementById('edit_staff_status').value = staff.Status || 'active';
            
            var editModal = new bootstrap.Modal(document.getElementById('editStaffModal'));
            editModal.show();
        }

        function updateStatus(staffId, isActive, element) {
            // Tạo form data
            const formData = new FormData();
            formData.append('staff_id', staffId);
            formData.append('status', isActive ? 'active' : 'inactive');
            formData.append('action', 'update_status');

            // Gửi AJAX request đến chính file này
            fetch('company_employees.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Cập nhật label
                    document.getElementById('status-label-' + staffId).textContent = isActive ? 'Active' : 'Inactive';
                } else {
                    alert('Có lỗi xảy ra khi cập nhật trạng thái: ' + (data.message || ''));
                    // Revert switch state
                    element.checked = !isActive;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Có lỗi xảy ra khi cập nhật trạng thái');
                // Revert switch state
                element.checked = !isActive;
            });
        }
    </script>
</body>

</html>