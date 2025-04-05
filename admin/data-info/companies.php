<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Xử lý xóa công ty nếu có
if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    
    $delete_query = mysqli_query($conn, "DELETE FROM `Companies` WHERE CompanyId = '$delete_id'") or die('Query failed');

    if ($delete_query) {
        $message[] = 'Xóa công ty thành công!';
    } else {
        $message[] = 'Xóa công ty thất bại!';
    }
}

// Xử lý thêm công ty mới
if(isset($_POST['add_company'])) {
    $code = mysqli_real_escape_string($conn, $_POST['company_code']);
    $name = mysqli_real_escape_string($conn, $_POST['company_name']);
    
    // Kiểm tra mã công ty đã tồn tại chưa
    $check_code = mysqli_query($conn, "SELECT * FROM `Companies` WHERE Code = '$code'");
    if(mysqli_num_rows($check_code) > 0) {
        $message[] = 'Mã công ty đã tồn tại!';
    } else {
        $insert_query = mysqli_query($conn, "INSERT INTO `Companies` (Code, Name) VALUES ('$code', '$name')");
        
        if($insert_query) {
            $message[] = 'Thêm công ty thành công!';
        } else {
            $message[] = 'Thêm công ty thất bại!';
        }
    }
}

// Xử lý cập nhật công ty
if(isset($_POST['update_company'])) {
    $company_id = mysqli_real_escape_string($conn, $_POST['company_id']);
    $code = mysqli_real_escape_string($conn, $_POST['company_code']);
    $name = mysqli_real_escape_string($conn, $_POST['company_name']);
    
    // Kiểm tra mã công ty đã tồn tại ở các công ty khác chưa
    $check_code = mysqli_query($conn, "SELECT * FROM `Companies` WHERE Code = '$code' AND CompanyId != '$company_id'");
    if(mysqli_num_rows($check_code) > 0) {
        $message[] = 'Mã công ty đã tồn tại!';
    } else {
        $update_query = mysqli_query($conn, "UPDATE `Companies` SET Code = '$code', Name = '$name' WHERE CompanyId = '$company_id'");
        
        if($update_query) {
            $message[] = 'Cập nhật công ty thành công!';
        } else {
            $message[] = 'Cập nhật công ty thất bại!';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý công ty</title>

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
        
        .view-btn {
            background-color: #3498db;
        }
        
        .edit-btn {
            background-color: #f39c12;
        }
        
        .delete-btn {
            background-color: #e74c3c;
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
                        <div class=" alert alert-info alert-dismissible fade show" role="alert">
                            <span style="font-size: 16px;">' . $msg . '</span>
                            <i style="font-size: 20px; cursor: pointer" class="fas fa-times" onclick="this.parentElement.remove();"></i>
                        </div>';
                    }
                }
                ?>
                
                <!-- Page Header -->
                <div class="page-header">
                    <h2 style="font-weight: bold; color: #476a52; margin-bottom: 10px; text-transform: uppercase;">THÔNG TIN CÔNG TY</h2>
                    <div class="breadcrumb">
                        <a href="/webquanlytoanha/admin/dashboard.php">Trang chủ</a>
                        <span style="margin: 0 8px;">›</span>
                        <a href="/webquanlytoanha/admin/data-info/companies.php">Thông tin công ty</a>
                        <span style="margin: 0 8px;">›</span>
                        <span>Công ty</span>
                    </div>
                </div>
                
                <!-- Tab Navigation -->
                <div class="tab-navigation">
                    <a href="#" class="tab-item active">Danh mục Công ty</a>
                    <a href="./townships.php" class="tab-item">Danh mục đô thị</a>
                    <a href="./projects.php" class="tab-item">Danh mục dự án</a>
                </div>
                
                <!-- Search and Add Section -->
                <div class="search-container">
                    <div class="d-flex">
                        <input type="text" class="search-input" placeholder="Nhập nội dung tìm kiếm">
                        <button class="search-btn">
                            <i class="fas fa-search"></i> Tìm kiếm
                        </button>
                    </div>
                    <button type="button" class="add-btn" data-bs-toggle="modal" data-bs-target="#addCompanyModal">
                        <i class="fas fa-plus"></i> Thêm công ty
                    </button>
                </div>
                
                <!-- Company Table -->
                <table class="company-table">
                    <thead>
                        <tr>
                            <th width="5%">STT</th>
                            <th width="20%">Mã công ty</th>
                            <th width="55%">Tên công ty</th>
                            <th width="20%">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Pagination setup
                        $records_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
                        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                        $offset = ($page - 1) * $records_per_page;
                        
                        // Count total records for pagination
                        $count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM `Companies`");
                        $total_records = mysqli_fetch_assoc($count_query)['total'];
                        $total_pages = ceil($total_records / $records_per_page);
                        
                        // Fetch company records with pagination
                        $select_companies = mysqli_query($conn, "SELECT * FROM `Companies` ORDER BY CompanyId DESC LIMIT $offset, $records_per_page") 
                                            or die('Query failed: ' . mysqli_error($conn));
                        
                        if (mysqli_num_rows($select_companies) > 0) {
                            $counter = $offset + 1;
                            while ($company = mysqli_fetch_assoc($select_companies)) {
                                ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo $company['Code']; ?></td>
                                    <td><?php echo $company['Name']; ?></td>
                                    <td>
                                        <div class="d-flex">
                                            <a href="javascript:void(0);" class="action-btn edit-btn" onclick="editCompany(<?php echo $company['CompanyId']; ?>, '<?php echo $company['Code']; ?>', '<?php echo $company['Name']; ?>')"><i class="fas fa-edit"></i></a>
                                            <a href="companies.php?delete=<?php echo $company['CompanyId']; ?>" class="action-btn delete-btn" onclick="return confirm('Bạn có chắc chắn muốn xóa công ty này?');"><i class="fas fa-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo '<tr><td colspan="4" class="text-center">Không có dữ liệu công ty</td></tr>';
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
                            <a href="companies.php?page=1&per_page=<?php echo $records_per_page; ?>" class="page-item"><i class="fas fa-angle-double-left"></i></a>
                        <?php endif; ?>
                        
                        <?php
                        // Calculate range of page numbers to display
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++): 
                        ?>
                            <a href="companies.php?page=<?php echo $i; ?>&per_page=<?php echo $records_per_page; ?>" class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="companies.php?page=<?php echo $total_pages; ?>&per_page=<?php echo $records_per_page; ?>" class="page-item"><i class="fas fa-angle-double-right"></i></a>
                        <?php endif; ?>
                    </div>
                    <div class="items-per-page">
                        <span>Hiển thị</span>
                        <select class="dropdown-per-page" onchange="window.location.href='companies.php?page=1&per_page='+this.value">
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
        // Hàm mở modal sửa và điền dữ liệu
        function editCompany(id, code, name) {
            document.getElementById('edit_company_id').value = id;
            document.getElementById('edit_company_code').value = code;
            document.getElementById('edit_company_name').value = name;
            
            // Mở modal
            var editModal = new bootstrap.Modal(document.getElementById('editCompanyModal'));
            editModal.show();
        }
        
        // Xử lý hiển thị thông báo lỗi từ PHP trong modal nếu có
        <?php if(isset($message) && in_array('Mã công ty đã tồn tại!', $message)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            var addModal = new bootstrap.Modal(document.getElementById('addCompanyModal'));
            addModal.show();
        });
        <?php endif; ?>
    </script>

    <!-- Modal Thêm Công Ty -->
    <div class="modal fade" id="addCompanyModal" tabindex="-1" aria-labelledby="addCompanyModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #476a52; color: white;">
                    <h5 class="modal-title" id="addCompanyModalLabel">THÊM CÔNG TY</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="background-color: white;"></button>
                </div>
                <form action="" method="post">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="company_code" class="form-label">Mã công ty <span class="text-danger">*</span>:</label>
                            <input type="text" class="form-control" id="company_code" name="company_code" required>
                        </div>
                        <div class="mb-3">
                            <label for="company_name" class="form-label">Tên công ty <span class="text-danger">*</span>:</label>
                            <input type="text" class="form-control" id="company_name" name="company_name" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="add_company" class="btn btn-success" style="background-color: #476a52; border-radius: 25px; padding: 8px 25px;">Lưu</button>
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal" style="background-color: #dc3545; border-radius: 25px; padding: 8px 25px;">Hủy</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Sửa Công Ty -->
    <div class="modal fade" id="editCompanyModal" tabindex="-1" aria-labelledby="editCompanyModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #476a52; color: white;">
                    <h5 class="modal-title" id="editCompanyModalLabel">SỬA CÔNG TY</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="background-color: white;"></button>
                </div>
                <form action="" method="post">
                    <div class="modal-body">
                        <input type="hidden" id="edit_company_id" name="company_id">
                        <div class="mb-3">
                            <label for="edit_company_code" class="form-label">Mã công ty <span class="text-danger">*</span>:</label>
                            <input type="text" class="form-control" id="edit_company_code" name="company_code" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_company_name" class="form-label">Tên công ty <span class="text-danger">*</span>:</label>
                            <input type="text" class="form-control" id="edit_company_name" name="company_name" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="update_company" class="btn btn-success" style="background-color: #476a52; border-radius: 25px; padding: 8px 25px;">Lưu</button>
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal" style="background-color: #dc3545; border-radius: 25px; padding: 8px 25px;">Hủy</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>

</html>