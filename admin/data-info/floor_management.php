<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location:../login.php');
    exit();
}

// Lấy danh sách tòa nhà cho dropdown
$select_buildings = mysqli_query($conn, "SELECT b.ID, b.Name, p.Name as ProjectName 
    FROM Buildings b 
    JOIN Projects p ON b.ProjectId = p.ProjectID 
    ORDER BY p.Name, b.Name");

// Xử lý xóa tầng
if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    $delete_query = mysqli_query($conn, "DELETE FROM Floors WHERE ID = '$delete_id'") or die('Query failed');

    if ($delete_query) {
        $message[] = 'Xóa tầng thành công!';
    } else {
        $message[] = 'Xóa tầng thất bại!';
    }
}

// Xử lý thêm tầng mới
if(isset($_POST['add_floor'])) {
    $name = mysqli_real_escape_string($conn, $_POST['floor_name']);
    $code = mysqli_real_escape_string($conn, $_POST['floor_code']);
    $building_id = mysqli_real_escape_string($conn, $_POST['building_id']);
    
    $insert_query = mysqli_query($conn, "INSERT INTO Floors (Name, Code, BuildingId) 
        VALUES ('$name', '$code', '$building_id')");
    
    if($insert_query) {
        $message[] = 'Thêm tầng thành công!';
    } else {
        $message[] = 'Thêm tầng thất bại!';
    }
}

// Xử lý cập nhật tầng
if(isset($_POST['update_floor'])) {
    $floor_id = mysqli_real_escape_string($conn, $_POST['floor_id']);
    $name = mysqli_real_escape_string($conn, $_POST['floor_name']);
    $code = mysqli_real_escape_string($conn, $_POST['floor_code']);
    $building_id = mysqli_real_escape_string($conn, $_POST['building_id']);
    
    $update_query = mysqli_query($conn, "UPDATE Floors 
        SET Name = '$name', Code = '$code', BuildingId = '$building_id' 
        WHERE ID = '$floor_id'");
    
    if($update_query) {
        $message[] = 'Cập nhật tầng thành công!';
    } else {
        $message[] = 'Cập nhật tầng thất bại!';
    }
}

// Xử lý phân trang
$records_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Xử lý tìm kiếm
$search_term = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Đếm tổng số bản ghi
$count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM Floors" . 
    (!empty($search_term) ? " WHERE Name LIKE '%$search_term%'" : ""));
$total_records = mysqli_fetch_assoc($count_query)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Lấy danh sách tầng
$select_floors = mysqli_query($conn, "SELECT f.*, b.Name as BuildingName, p.Name as ProjectName 
    FROM Floors f
    JOIN Buildings b ON f.BuildingId = b.ID
    JOIN Projects p ON b.ProjectId = p.ProjectID
    " . (!empty($search_term) ? "WHERE f.Name LIKE '%$search_term%'" : "") . "
    ORDER BY p.Name, b.Name, f.Name 
    LIMIT $offset, $records_per_page");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý tầng</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
        
        .company-table {
            width: 100%;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            margin-top: 20px;
            border-collapse: collapse;
        }
        
        .company-table th {
            background-color: #e2e8f0;
            color: #4a5568;
            text-align: left;
            padding: 15px;
            font-weight: 500;
        }
        
        .company-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #f2f2f2;
            color: #4a5568;
        }
        
        .company-table tr:hover {
            background-color: #f9fafb;
        }
        
        .action-btn {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #ddd;
            background: white;
            margin-right: 8px;
            color: #666;
            cursor: pointer;
        }
        
        .btn-edit:hover {
            background: #f8f9fa;
            color: #333;
        }
        
        .btn-delete:hover {
            background: #f8f9fa;
            color: #333;
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
            width: 100%;
            padding: 20px;
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
        .manage-container {
            background: #fff !important;
        }
        
        .project-select {
            width: 300px;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-active {
            background-color: #28a745;
            color: white;
        }
        
        .status-inactive {
            background-color: #dc3545;
            color: white;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <?php include '../admin_navbar.php'; ?>
        <div class="manage-container">
            <?php
            if (isset($message)) {
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
                <h2 style="font-weight: bold; color: #476a52; margin-bottom: 10px; text-transform: uppercase;">DANH SÁCH TẦNG</h2>
                <div class="breadcrumb">
                    <a href="/webquanlytoanha/admin/dashboard.php">Trang chủ</a>
                    <span style="margin: 0 8px;">›</span>
                    <a href="building_management.php">Tổng quan dự án</a>
                    <span style="margin: 0 8px;">›</span>
                    <a href="building_management.php">Quản lý tòa nhà</a>
                    <span style="margin: 0 8px;">›</span>
                    <span>Danh sách tầng</span>
                </div>
            </div>
        
            
            <!-- Search and Add Section -->
            <div class="search-container">
                <div class="d-flex">
                    <form action="" method="GET" class="d-flex">
                        <input type="text" name="search" class="search-input" placeholder="Nhập tên tầng, mã tầng" 
                               value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        <button type="submit" class="search-btn">
                            <i class="fas fa-search"></i> Tìm kiếm
                        </button>
                    </form>
                </div>
                <button type="button" class="add-btn" data-bs-toggle="modal" data-bs-target="#addFloorModal">
                    <i class="fas fa-plus"></i> Thêm mới tầng
                </button>
            </div>
            
            <!-- Floors Table -->
            <table class="company-table">
                <thead>
                    <tr>
                        <th width="5%">STT</th>
                        <th width="25%">Tòa nhà</th>
                        <th width="25%">Tên tầng</th>
                        <th width="20%">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if(mysqli_num_rows($select_floors) > 0) {
                        $counter = 1;
                        while($floor = mysqli_fetch_assoc($select_floors)) {
                            ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td><?php echo $floor['ProjectName'] . ' - ' . $floor['BuildingName']; ?></td>
                                <td><?php echo $floor['Name']; ?></td>
                                <td>
                                    <button type="button" class="action-btn btn-edit" 
                                        onclick="editFloor(
                                            '<?php echo $floor['ID']; ?>',
                                            '<?php echo $floor['Name']; ?>',
                                            '<?php echo $floor['Code']; ?>',
                                            '<?php echo $floor['BuildingId']; ?>'
                                        )">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="floor_management.php?delete=<?php echo $floor['ID']; ?>" 
                                       class="action-btn btn-delete"
                                       onclick="return confirm('Bạn có chắc chắn muốn xóa tầng này?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php
                        }
                    } else {
                        echo '<tr><td colspan="4" class="text-center">Không có tầng nào</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
            
            <div class="separator-line"></div>
            
            <!-- Pagination -->
            <div class="pagination">
                <div class="total-count">Tổng số: <?php echo $total_records; ?> bản ghi</div>
                <div class="page-controls">
                    <?php if($total_pages > 1): ?>
                        <?php if ($page > 1): ?>
                            <a href="?page=1&per_page=<?php echo $records_per_page; ?><?php echo !empty($search_term) ? '&search='.urlencode($search_term) : ''; ?>" class="page-item">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++): 
                        ?>
                            <a href="?page=<?php echo $i; ?>&per_page=<?php echo $records_per_page; ?><?php echo !empty($search_term) ? '&search='.urlencode($search_term) : ''; ?>" 
                               class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $total_pages; ?>&per_page=<?php echo $records_per_page; ?><?php echo !empty($search_term) ? '&search='.urlencode($search_term) : ''; ?>" 
                               class="page-item">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="items-per-page">
                    <span>Hiển thị</span>
                    <select class="dropdown-per-page" onchange="window.location.href='?page=1&per_page='+this.value+'<?php echo !empty($search_term) ? '&search='.urlencode($search_term) : ''; ?>'">
                        <option value="10" <?php echo ($records_per_page == 10) ? 'selected' : ''; ?>>10</option>
                        <option value="20" <?php echo ($records_per_page == 20) ? 'selected' : ''; ?>>20</option>
                        <option value="50" <?php echo ($records_per_page == 50) ? 'selected' : ''; ?>>50</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editFloor(id, name, code, buildingId) {
            document.getElementById('edit_floor_id').value = id;
            document.getElementById('edit_floor_name').value = name;
            document.getElementById('edit_floor_code').value = code;
            document.getElementById('edit_building_id').value = buildingId;
            
            var editModal = new bootstrap.Modal(document.getElementById('editFloorModal'));
            editModal.show();
        }
    </script>

    <!-- Modal Thêm Tầng -->
    <div class="modal fade" id="addFloorModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #476a52; color: white;">
                    <h5 class="modal-title">Thêm mới tầng</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="post">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Tên tầng:</label>
                            <input type="text" name="floor_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mã tầng:</label>
                            <input type="text" name="floor_code" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tòa nhà:</label>
                            <select name="building_id" class="form-select" required>
                                <option value="">Chọn tòa nhà</option>
                                <?php 
                                mysqli_data_seek($select_buildings, 0);
                                while($building = mysqli_fetch_assoc($select_buildings)) { 
                                ?>
                                    <option value="<?php echo $building['ID']; ?>">
                                        <?php echo $building['ProjectName'] . ' - ' . $building['Name']; ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-center">
                        <button type="submit" name="add_floor" class="btn btn-success" style="min-width: 100px;">Lưu</button>
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal" style="min-width: 100px;">Hủy</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Sửa Tầng -->
    <div class="modal fade" id="editFloorModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #476a52; color: white;">
                    <h5 class="modal-title">Sửa tầng</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="post">
                    <div class="modal-body">
                        <input type="hidden" name="floor_id" id="edit_floor_id">
                        <div class="mb-3">
                            <label class="form-label">Tên tầng:</label>
                            <input type="text" name="floor_name" id="edit_floor_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mã tầng:</label>
                            <input type="text" name="floor_code" id="edit_floor_code" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tòa nhà:</label>
                            <select name="building_id" id="edit_building_id" class="form-select" required>
                                <option value="">Chọn tòa nhà</option>
                                <?php 
                                mysqli_data_seek($select_buildings, 0);
                                while($building = mysqli_fetch_assoc($select_buildings)) { 
                                ?>
                                    <option value="<?php echo $building['ID']; ?>">
                                        <?php echo $building['ProjectName'] . ' - ' . $building['Name']; ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-center">
                        <button type="submit" name="update_floor" class="btn btn-success" style="min-width: 100px;">Lưu</button>
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal" style="min-width: 100px;">Hủy</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>

</html>