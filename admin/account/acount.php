<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Thêm xử lý xóa account
if (isset($_GET['delete'])) {
    $staff_id = mysqli_real_escape_string($conn, $_GET['delete']);
    
    mysqli_begin_transaction($conn);
    try {
        // Tắt kiểm tra khóa ngoại tạm thời
        mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");
        
        // 1. Lấy UserId từ bảng users dựa trên thông tin staff
        $user_query = mysqli_query($conn, "
            SELECT u.UserId 
            FROM users u 
            INNER JOIN staffs s ON (s.Name = u.UserName OR s.PhoneNumber = u.PhoneNumber)
            WHERE s.ID = '$staff_id'
        ") or throw new Exception('Không thể tìm thấy user tương ứng: ' . mysqli_error($conn));
        
        if ($user = mysqli_fetch_assoc($user_query)) {
            $user_id = $user['UserId'];
            
            // 2. Xóa từ bảng StaffProjects
            mysqli_query($conn, "DELETE FROM StaffProjects WHERE StaffId = '$staff_id'") 
                or throw new Exception('Không thể xóa dự án của nhân viên: ' . mysqli_error($conn));
            
            // 3. Xóa từ bảng users
            mysqli_query($conn, "DELETE FROM users WHERE UserId = '$user_id'") 
                or throw new Exception('Không thể xóa tài khoản user: ' . mysqli_error($conn));
            
            // 4. Xóa từ bảng Staffs
            mysqli_query($conn, "DELETE FROM Staffs WHERE ID = '$staff_id'") 
                or throw new Exception('Không thể xóa nhân viên: ' . mysqli_error($conn));
        } else {
            throw new Exception('Không tìm thấy tài khoản user tương ứng với nhân viên này');
        }

        // Bật lại kiểm tra khóa ngoại
        mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");
        
        mysqli_commit($conn);
        $_SESSION['success_msg'] = 'Xóa tài khoản thành công!';
        header('location: acount.php');
        exit();
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");
        $_SESSION['error_msg'] = 'Lỗi: ' . $e->getMessage();
        header('location: acount.php');
        exit();
    }
}

// Khóa tài khoản người dùng
if (isset($_GET['block'])) {
    $block_id = $_GET['block'];

    // Khóa tài khoản người dùng
    $block_query = mysqli_query($conn, "UPDATE `users` SET status = '0' WHERE user_id = '$block_id'") or die('Query failed');

    if ($block_query) {
        $message[] = 'Khóa tài khoản người dùng thành công!';
    } else {
        $message[] = 'Khóa tài khoản người dùng thất bại!';
    }
}

// Mở khóa tài khoản người dùng
if (isset($_GET['un_block'])) {
    $un_block_id = $_GET['un_block'];

    // Mở khóa tài khoản người dùng
    $un_block_query = mysqli_query($conn, "UPDATE `users` SET status = '1' WHERE user_id = '$un_block_id'") or die('Query failed');

    if ($un_block_query) {
        $message[] = 'Mở khóa tài khoản người dùng thành công!';
    } else {
        $message[] = 'Mở khóa tài khoản người dùng thất bại!';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý tài khoản</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
                <h2 style="font-weight: bold; color: #476a52; margin-bottom: 10px; text-transform: uppercase;">TÀI KHOẢN - PHÂN QUYỀN</h2>
                <div class="breadcrumb">
                    <a href="dashboard.php">Trang chủ</a>
                    <span style="margin: 0 8px;">›</span>
                    <span>Tài khoản - Phân quyền</span>
                </div>
            </div>
            
            <!-- Search and Add Section -->
            <div class="search-container">
                <div class="search-box">
                    <input type="text" class="search-input" placeholder="Tên, email, SĐT">
                    <select class="dropdown-select">
                        <option>Chọn chức vụ</option>
                        <option>Quản trị hệ thống</option>
                        <option>Kế toán ban</option>
                        <option>Trưởng BQL</option>
                    </select>
                    <button class="search-btn">
                        <i class="fas fa-search"></i> Tìm kiếm
                    </button>
                </div>
                <a href="create_account.php" class="add-btn">
                    <i class="fas fa-plus"></i> Thêm mới
                </a>
            </div>
            
            <!-- Users Table -->
            <table class="account-table">
                        <thead>
                            <tr>
                        <th>MÃ NHÂN VIÊN</th>
                        <th>HỌ TÊN</th>
                        <th>SĐT</th>
                        <th>EMAIL</th>
                        <th>CHỨC VỤ</th>
                        <th>THAO TÁC</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                    // Pagination setup
                    $records_per_page = 10;
                    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                    $offset = ($page - 1) * $records_per_page;
                    
                    // Count total records for pagination - join theo name hoặc phone
                    $count_query = mysqli_query($conn, "
                        SELECT COUNT(DISTINCT s.ID) as total 
                        FROM staffs s
                        INNER JOIN users u 
                        ON s.Name = u.UserName OR s.PhoneNumber = u.PhoneNumber
                    ");
                    $total_records = mysqli_fetch_assoc($count_query)['total'];
                    $total_pages = ceil($total_records / $records_per_page);
                    
                    // Fetch staff records with pagination - join theo name hoặc phone
                    $select_staff = mysqli_query($conn, "
                        SELECT DISTINCT s.* 
                        FROM staffs s
                        INNER JOIN users u 
                        ON s.Name = u.UserName OR s.PhoneNumber = u.PhoneNumber
                        ORDER BY s.ID DESC 
                        LIMIT $offset, $records_per_page
                    ") or die('Query failed: ' . mysqli_error($conn));
                    
                    if (mysqli_num_rows($select_staff) > 0) {
                        while ($staff = mysqli_fetch_assoc($select_staff)) {
                            ?>
                                    <tr>
                                <td><?php echo $staff['ID']; ?></td>
                                <td><?php echo $staff['Name']; ?></td>
                                <td><?php echo $staff['PhoneNumber']; ?></td>
                                <td><?php echo $staff['Email']; ?></td>
                                <td><?php echo $staff['Position']; ?></td>
                                <td>
                                    <div class="d-flex">
                                        <a href="update_account.php?id=<?php echo $staff['ID']; ?>" class="action-icon"><i class="far fa-edit"></i></a>
                                        <a href="acount.php?delete=<?php echo $staff['ID']; ?>" class="action-icon" onclick="return confirm('Bạn có chắc chắn muốn xóa người dùng này?');"><i class="far fa-trash-alt"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                            <?php
                                }
                            } else {
                        echo '<tr><td colspan="7" class="text-center">Không có dữ liệu nhân viên</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
            
            <!-- Pagination -->
            <div class="pagination">
                <div>Tổng số: <?php echo $total_records; ?> bản ghi</div>
                <div class="page-controls">
                    <?php if ($page > 1): ?>
                        <a href="acount.php?page=1" class="page-item"><i class="fas fa-angle-double-left"></i></a>
                    <?php endif; ?>
                    
                    <?php
                    // Calculate range of page numbers to display
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                        <a href="acount.php?page=<?php echo $i; ?>" class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="acount.php?page=<?php echo $total_pages; ?>" class="page-item"><i class="fas fa-angle-double-right"></i></a>
                    <?php endif; ?>
                </div>
                <div class="items-per-page">
                    <span>Hiển thị</span>
                    <select class="dropdown-per-page" onchange="window.location.href='acount.php?page=1&per_page='+this.value">
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
</body>

</html>