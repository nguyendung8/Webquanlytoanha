<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location:../index.php');
    exit();
}

// Xử lý cập nhật trạng thái 
if(isset($_POST['update_status'])) {
    $project_id = $_POST['project_id'];
    $status = $_POST['status'];
    
    $update_query = mysqli_query($conn, "UPDATE Projects SET Status = '$status' WHERE ProjectID = '$project_id'");
    
    if($update_query) {
        echo "success";
    } else {
        echo "error";
    }
    exit;
}

// Xử lý xóa đô thị nếu có
if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    
    $delete_query = mysqli_query($conn, "DELETE FROM `TownShips` WHERE TownShipId = '$delete_id'") or die('Query failed');

    if ($delete_query) {
        $message[] = 'Xóa khu đô thị thành công!';
    } else {
        $message[] = 'Xóa khu đô thị thất bại!';
    }
}

// Xử lý thêm khu đô thị mới
if(isset($_POST['add_township'])) {
    $code = mysqli_real_escape_string($conn, $_POST['township_code']);
    $name = mysqli_real_escape_string($conn, $_POST['township_name']);
    $company_id = mysqli_real_escape_string($conn, $_POST['company_id']);
    
    // Kiểm tra mã khu đô thị đã tồn tại chưa
    $check_code = mysqli_query($conn, "SELECT * FROM `TownShips` WHERE Code = '$code'");
    if(mysqli_num_rows($check_code) > 0) {
        $message[] = 'Mã khu đô thị đã tồn tại!';
    } else {
        $insert_query = mysqli_query($conn, "INSERT INTO `TownShips` (Code, Name, CompanyId) VALUES ('$code', '$name', '$company_id')");
        
        if($insert_query) {
            $message[] = 'Thêm khu đô thị thành công!';
        } else {
            $message[] = 'Thêm khu đô thị thất bại!';
        }
    }
}

// Xử lý cập nhật khu đô thị
if(isset($_POST['update_township'])) {
    $township_id = mysqli_real_escape_string($conn, $_POST['township_id']);
    $code = mysqli_real_escape_string($conn, $_POST['township_code']);
    $name = mysqli_real_escape_string($conn, $_POST['township_name']);
    $company_id = mysqli_real_escape_string($conn, $_POST['company_id']);
    
    // Kiểm tra mã khu đô thị đã tồn tại ở các khu đô thị khác chưa
    $check_code = mysqli_query($conn, "SELECT * FROM `TownShips` WHERE Code = '$code' AND TownShipId != '$township_id'");
    if(mysqli_num_rows($check_code) > 0) {
        $message[] = 'Mã khu đô thị đã tồn tại!';
    } else {
        $update_query = mysqli_query($conn, "UPDATE `TownShips` SET Code = '$code', Name = '$name', CompanyId = '$company_id' WHERE TownShipId = '$township_id'");
        
        if($update_query) {
            $message[] = 'Cập nhật khu đô thị thành công!';
        } else {
            $message[] = 'Cập nhật khu đô thị thất bại!';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý dự án</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="../css/admin_style.css">
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
        
        .tab-navigation {
            display: flex;
            margin: 20px 0;
            border-bottom: 1px solid #eaeaea;
        }
        
        .tab-item {
            padding: 12px 25px;
            background-color: #f5f5f5;
            color: #4a5568;
            border: 1px solid #eaeaea;
            border-bottom: none;
            cursor: pointer;
            font-weight: 500;
            margin-right: 5px;
            border-radius: 5px 5px 0 0;
            text-decoration: none;
        }
        
        .tab-item.active {
            background-color: #476a52;
            color: white;
            border-color: #476a52;
        }
        
        .search-container {
            display: flex;
            justify-content: space-between;
            margin: 20px 0;
        }
        
        .search-input {
            width: 300px;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 50px;
            outline: none;
        }
        
        .search-select {
            width: 200px;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 50px;
            outline: none;
            margin-left: 10px;
            background-color: white;
        }
        
        .search-btn {
            padding: 8px 20px;
            border: none;
            border-radius: 50px;
            background-color: #f8b427;
            color: white;
            cursor: pointer;
            margin-left: 10px;
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
        
        .add-btn:hover {
            background-color: #3a5943;
            color: white;
        }
        
        .project-table {
            width: 100%;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            margin-top: 20px;
            border-collapse: collapse;
        }
        
        .project-table th {
            background-color: #e2e8f0;
            color: #4a5568;
            text-align: left;
            padding: 10px;
            font-weight: 500;
            border: 1px solid #ddd;
        }
        
        .project-table td {
            padding: 8px 10px;
            border: 1px solid #f2f2f2;
            color: #4a5568;
            vertical-align: top;
        }
        
        .project-table tr:hover {
            background-color: #f9fafb;
        }
        
        .action-btn {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin: 0 2px;
            font-size: 12px;
        }
        
        .edit-btn {
            background-color: #476a52;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            background-color: #3498db;
            color: white;
            font-size: 12px;
            font-weight: 500;
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
            text-decoration: none;
            color: #4a5568;
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
            background:rgb(243, 239, 239) !important;
            width: 100%;
            padding: 20px;
        }
        
        .project-description {
            font-size: 13px;
            line-height: 1.4;
        }
        
        h2.section-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .separator-line {
            height: 2px;
            background-color: #ff6b4a;
            margin: 20px 0;
        }
        
        .total-count {
            padding: 8px 15px;
            background-color: #ff6b4a;
            color: white;
            border-radius: 5px;
            display: inline-block;
            font-weight: 500;
            margin-bottom: 10px;
        }
        
        /* Thêm style cho switch */
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
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
        
        .slider:before {
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
        
        input:checked + .slider {
            background-color: #4CAF50;
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        .status-text {
            margin-left: 5px;
            font-size: 12px;
            font-weight: 500;
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
                if (isset($message)) {
                    foreach ($message as $msg) {
                        echo '
                        <div class="alert alert-info alert-dismissible fade show" role="alert">
                            <span style="font-size: 16px;">' . $msg . '</span>
                            <i style="font-size: 20px; cursor: pointer" class="fas fa-times" onclick="this.parentElement.remove();"></i>
                        </div>';
                    }
                }
                ?>
                
                <!-- Page Header -->
                <div class="page-header">
                    <h2 style="font-weight: bold; color: #476a52; margin-bottom: 10px; text-transform: uppercase;">DANH MỤC DỰ ÁN</h2>
                    <div class="breadcrumb">
                        <a href="/webquanlytoanha/admin/dashboard.php">Trang chủ</a>
                        <span style="margin: 0 8px;">›</span>
                        <span>Danh mục dự án</span>
                    </div>
                </div>
                
                <!-- Tab Navigation -->
                <div class="tab-navigation">
                    <a href="companies.php" class="tab-item">Danh mục Công ty</a>
                    <a href="townships.php" class="tab-item">Danh mục đô thị</a>
                    <a href="projects.php" class="tab-item active">Danh mục dự án</a>
                </div>
                
                <!-- Search and Add Section -->
                <div class="search-container">
                    <div class="d-flex">
                        <input type="text" class="search-input" placeholder="Nhập nội dung tìm kiếm">
                        <select class="search-select">
                            <option value="">Chọn trạng thái</option>
                            <option value="active">Đang hoạt động</option>
                            <option value="inactive">Không hoạt động</option>
                        </select>
                        <button class="search-btn">
                            <i class="fas fa-search"></i> Tìm kiếm
                        </button>
                    </div>
                    <a href="create_project.php" class="add-btn">
                        <i class="fas fa-plus"></i> Thêm dự án
                    </a>
                </div>
                
                <!-- Project Table -->
                <table class="project-table">
                    <thead>
                        <tr>
                            <th width="5%">STT</th>
                            <th width="10%">Khu đô thị</th>
                            <th width="15%">Tên dự án</th>
                            <th width="15%">Địa chỉ</th>
                            <th width="10%">Số điện thoại</th>
                            <th width="20%">Mô tả</th>
                            <th width="10%">Trưởng ban quản lý</th>
                            <th width="7%">Trạng thái</th>
                            <th width="8%">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Pagination setup
                        $records_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
                        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                        $offset = ($page - 1) * $records_per_page;
                        
                        // Count total records for pagination
                        $count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM Projects");
                        $total_records = mysqli_fetch_assoc($count_query)['total'];
                        $total_pages = ceil($total_records / $records_per_page);
                        
                        // Fetch projects with township and manager information
                        $select_projects = mysqli_query($conn, "SELECT p.*, t.Name as TownshipName, t.Code as TownshipCode, s.Name as ManagerName 
                                                    FROM Projects p
                                                    LEFT JOIN Townships t ON p.TownshipId = t.TownshipId
                                                    LEFT JOIN Staffs s ON p.ManagerId = s.ID
                                                    ORDER BY p.ProjectID DESC LIMIT $offset, $records_per_page");
                        
                        if (mysqli_num_rows($select_projects) > 0) {
                            $counter = $offset + 1;
                            while ($project = mysqli_fetch_assoc($select_projects)) {
                                // Determine status
                                $is_active = isset($project['Status']) ? ($project['Status'] == 'active') : true;
                        ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo $project['TownshipCode'] ? $project['TownshipCode'] : 'N/A'; ?></td>
                                    <td><?php echo $project['Name']; ?></td>
                                    <td><?php echo $project['Address']; ?></td>
                                    <td><?php echo $project['Phone']; ?></td>
                                    <td><div class="project-description"><?php echo nl2br($project['Description']); ?></div></td>
                                    <td><?php echo $project['ManagerName'] ? $project['ManagerName'] : 'N/A'; ?></td>
                                    <td>
                                        <label class="switch">
                                            <input type="checkbox" class="status-toggle" data-id="<?php echo $project['ProjectID']; ?>" <?php echo $is_active ? 'checked' : ''; ?>>
                                            <span class="slider"></span>
                                        </label>
                                        <span class="status-text"><?php echo $is_active ? 'Active' : 'Inactive'; ?></span>
                                    </td>
                                    <td>
                                        <div class="d-flex">
                                            <a href="update_project.php?id=<?php echo $project['ProjectID']; ?>" class="action-btn edit-btn"><i class="fas fa-edit"></i></a>
                                        </div>
                                    </td>
                                </tr>
                        <?php
                            }
                        } else {
                            echo '<tr><td colspan="9" class="text-center">Không có dữ liệu dự án</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
                
                <div class="separator-line"></div>
                
                <!-- Pagination -->
                <div class="pagination">
                    <div class="total-count">Tổng số: <?php echo $total_records; ?> bản ghi</div>
                    <div class="page-controls">
                        <?php if ($page > 1): ?>
                            <a href="projects.php?page=1&per_page=<?php echo $records_per_page; ?>" class="page-item"><i class="fas fa-angle-double-left"></i></a>
                        <?php endif; ?>
                        
                        <?php
                        // Calculate range of page numbers to display
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++): 
                        ?>
                            <a href="projects.php?page=<?php echo $i; ?>&per_page=<?php echo $records_per_page; ?>" class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="projects.php?page=<?php echo $total_pages; ?>&per_page=<?php echo $records_per_page; ?>" class="page-item"><i class="fas fa-angle-double-right"></i></a>
                        <?php endif; ?>
                    </div>
                    <div class="items-per-page">
                        <span>Hiển thị</span>
                        <select class="dropdown-per-page" onchange="window.location.href='projects.php?page=1&per_page='+this.value">
                            <option value="10" <?php echo ($records_per_page == 10) ? 'selected' : ''; ?>>10</option>
                            <option value="20" <?php echo ($records_per_page == 20) ? 'selected' : ''; ?>>20</option>
                            <option value="50" <?php echo ($records_per_page == 50) ? 'selected' : ''; ?>>50</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Xử lý switch toggle trạng thái
            $('.status-toggle').change(function() {
                const projectId = $(this).data('id');
                const isChecked = $(this).prop('checked');
                const statusText = isChecked ? 'active' : 'inactive';
                const statusLabel = $(this).closest('td').find('.status-text');
                
                // Cập nhật văn bản trạng thái
                statusLabel.text(isChecked ? 'Active' : 'Inactive');
                
                // Gửi AJAX request để cập nhật trạng thái trong database
                $.ajax({
                    url: 'projects.php',
                    type: 'POST',
                    data: {
                        update_status: 1,
                        project_id: projectId,
                        status: statusText
                    },
                    success: function(response) {
                        if(response !== 'success') {
                            // Nếu có lỗi, đảo ngược trạng thái của switch
                            $(this).prop('checked', !isChecked);
                            statusLabel.text(!isChecked ? 'Active' : 'Inactive');
                            alert('Cập nhật trạng thái thất bại!');
                        }
                    },
                    error: function() {
                        // Nếu có lỗi, đảo ngược trạng thái của switch
                        $(this).prop('checked', !isChecked);
                        statusLabel.text(!isChecked ? 'Active' : 'Inactive');
                        alert('Đã xảy ra lỗi khi cập nhật trạng thái!');
                    }
                });
            });
        });
    </script>
</body>
</html>