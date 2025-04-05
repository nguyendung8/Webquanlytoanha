<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name']; // Lấy tên admin từ session

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Xóa tài khoản người dùng
if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];

    // Xóa tài khoản người dùng
    $delete_query = mysqli_query($conn, "DELETE FROM `pricelist` WHERE ID = '$delete_id'") or die('Query failed');

        if ($delete_query) {
            $message[] = 'Xóa bảng giá thành công!';
        } else {
            $message[] = 'Xóa bảng giá thất bại!';
        }

    header('location:price_list.php');
    exit();
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

// Xử lý AJAX request cập nhật trạng thái
if(isset($_POST['update_status']) && isset($_POST['price_id']) && isset($_POST['status'])) {
    $price_id = mysqli_real_escape_string($conn, $_POST['price_id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    // Kiểm tra status chỉ có thể là 'active' hoặc 'inactive'
    if ($status != 'active' && $status != 'inactive') {
        echo json_encode(['success' => false, 'message' => 'Invalid status value']);
        exit();
    }
    
    try {
        $update_query = mysqli_query($conn, "
            UPDATE pricelist 
            SET Status = '$status' 
            WHERE ID = '$price_id'
        ");
        
        if (mysqli_affected_rows($conn) > 0) {
            echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Price not found or status already set to ' . $status]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Xử lý xóa bảng giá
if (isset($_GET['delete'])) {
    $delete_id = mysqli_real_escape_string($conn, $_GET['delete']);
    
    try {
        // ServicePrice sẽ tự động xóa nhờ ON DELETE CASCADE trong ràng buộc khóa ngoại
        $delete_query = mysqli_query($conn, "DELETE FROM pricelist WHERE ID = '$delete_id'");
        
        if (mysqli_affected_rows($conn) > 0) {
            $_SESSION['success_msg'] = 'Đã xóa bảng giá thành công!';
        } else {
            $_SESSION['error_msg'] = 'Không tìm thấy bảng giá để xóa!';
        }
    } catch (Exception $e) {
        $_SESSION['error_msg'] = 'Lỗi khi xóa bảng giá: ' . $e->getMessage();
    }
    
    header('location: price_list.php');
    exit();
}

// Xử lý filter
$where_conditions = [];
$search_term = '';
$status_filter = '';
$type_filter = '';

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = mysqli_real_escape_string($conn, $_GET['search']);
    $where_conditions[] = "(p.Name LIKE '%$search_term%' OR s.Name LIKE '%$search_term%' OR p.Code LIKE '%$search_term%')";
}

if (isset($_GET['status']) && !empty($_GET['status'])) {
    $status_filter = mysqli_real_escape_string($conn, $_GET['status']);
    $where_conditions[] = "p.Status = '$status_filter'";
}

if (isset($_GET['type']) && !empty($_GET['type'])) {
    $type_filter = mysqli_real_escape_string($conn, $_GET['type']);
    $where_conditions[] = "p.TypeOfFee = '$type_filter'";
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Lấy danh sách bảng giá với dịch vụ từ bảng trung gian
$query = "
    SELECT p.*, s.Name AS ServiceName, s.ServiceCode 
    FROM pricelist p
    LEFT JOIN ServicePrice sp ON p.ID = sp.PriceId
    LEFT JOIN Services s ON sp.ServiceId = s.ServiceCode
    $where_clause
    ORDER BY p.ID DESC
";
$select_prices = mysqli_query($conn, $query) or die('Query failed: ' . mysqli_error($conn));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách bảng giá</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
            margin-bottom: 20px;
        }
        
        .main-title {
            color: #476a52;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 10px;
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
            flex-wrap: wrap;
        }
        
        .search-box {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .search-input {
            width: 220px;
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            outline: none;
        }
        
        .dropdown-select {
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            outline: none;
            background-color: white;
            cursor: pointer;
        }
        
        .search-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            background-color: #6b8b7b;
            color: white;
            cursor: pointer;
        }
        
        .add-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            background-color: #6b8b7b;
            color: white;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .add-btn:hover {
            background-color: #5a7a6a;
            color: white;
        }
        
        .price-table {
            width: 100%;
            background-color: white;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .price-table th {
            background-color: #6b8b7b;
            color: white;
            text-align: center;
            padding: 12px;
            font-weight: 500;
            font-size: 14px;
        }
        
        .price-table td {
            padding: 12px;
            border-bottom: 1px solid #f2f2f2;
            color: #4a5568;
            text-align: center;
            font-size: 14px;
        }
        
        .price-table tr:hover {
            background-color: #f9fafb;
        }
        
        .status-toggle {
            width: 60px;
            height: 26px;
            background-color: #ccc;
            border-radius: 13px;
            padding: 2px;
            position: relative;
            cursor: pointer;
            margin: 0 auto;
            transition: background-color 0.3s;
        }

        .status-toggle.active {
            background-color: #a7c1b5;
        }

        .toggle-slider {
            width: 22px;
            height: 22px;
            background-color: white;
            border-radius: 50%;
            position: absolute;
            transition: transform 0.3s ease;
        }

        .status-toggle.active .toggle-slider {
            transform: translateX(34px);
        }
        
        .status-toggle.loading {
            opacity: 0.7;
            pointer-events: none;
        }
        
        .action-icon {
            color: #ffc107;
            font-size: 18px;
            margin: 0 5px;
            cursor: pointer;
            text-decoration: none;
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
            background-color: #6b8b7b;
            color: white;
            border-color: #6b8b7b;
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
            background-color: #f5f5f5;
        }
        
        .notification-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            min-width: 300px;
        }
        
        .admin-info {
            font-size: 12px;
            color: #666;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <?php include '../admin_navbar.php'; ?>
        <div style="width: 100%;">
            <?php include '../admin_header.php'; ?>
            <div class="manage-container">
                <div id="notifications"></div>
                
                <?php if(isset($_SESSION['success_msg'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php 
                    echo $_SESSION['success_msg']; 
                    unset($_SESSION['success_msg']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['error_msg'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php 
                    echo $_SESSION['error_msg']; 
                    unset($_SESSION['error_msg']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <!-- Page Header -->
                <div class="page-header">
                    <h1 class="main-title">DANH SÁCH BẢNG GIÁ</h1>
                    <div class="breadcrumb">
                        <a href="/webquanlytoanha/admin/dashboard.php">Trang chủ</a>
                        <span style="margin: 0 8px;">›</span>
                        <a href="service_list.php">Dịch vụ tòa nhà</a>
                        <span style="margin: 0 8px;">›</span>
                        <span>Danh sách bảng giá</span>
                    </div>
                </div>
                
                <!-- Search and Add Section -->
                <form method="GET" action="">
                    <div class="search-container">
                        <div class="search-box">
                            <input type="text" name="search" class="search-input" placeholder="Nhập tên bảng giá, dịch vụ" 
                                value="<?php echo htmlspecialchars($search_term); ?>">
                            <select name="status" class="dropdown-select">
                                <option value="">Trạng thái</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                            <select name="type" class="dropdown-select">
                                <option value="">Loại bảng giá</option>
                                <option value="Cố định" <?php echo $type_filter === 'Cố định' ? 'selected' : ''; ?>>Cố định</option>
                                <option value="Lũy tiến" <?php echo $type_filter === 'Lũy tiến' ? 'selected' : ''; ?>>Lũy tiến</option>
                                <option value="Định mức" <?php echo $type_filter === 'Định mức' ? 'selected' : ''; ?>>Định mức</option>
                                <option value="Nhân khẩu" <?php echo $type_filter === 'Nhân khẩu' ? 'selected' : ''; ?>>Nhân khẩu</option>
                            </select>
                            <button type="submit" class="search-btn">
                                <i class="fas fa-search"></i> Tìm kiếm
                            </button>
                        </div>
                        <a href="create_price.php" class="add-btn">
                            <i class="fas fa-plus"></i> Thêm mới
                        </a>
                    </div>
                </form>
                
                <!-- Price Table -->
                <table class="price-table">
                    <thead>
                        <tr>
                            <th>STT</th>
                            <th>TÊN BẢNG GIÁ</th>
                            <th>LOẠI BẢNG GIÁ</th>
                            <th>DỊCH VỤ</th>
                            <th>NGÀY ÁP DỤNG</th>
                            <th>NGƯỜI CẬP NHẬT</th>
                            <th>TRẠNG THÁI</th>
                            <th>THAO TÁC</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stt = 1;
                        if (mysqli_num_rows($select_prices) > 0) {
                            while($price = mysqli_fetch_assoc($select_prices)) {
                        ?>
                        <tr>
                            <td><?php echo $stt++; ?></td>
                            <td><?php echo htmlspecialchars($price['Name']); ?></td>
                            <td><?php echo htmlspecialchars($price['TypeOfFee']); ?></td>
                            <td><?php echo htmlspecialchars($price['ServiceName'] ?? 'Không có dịch vụ'); ?></td>
                            <td><?php echo $price['ApplyDate'] ? date('d/m/Y', strtotime($price['ApplyDate'])) : ''; ?></td>
                            <td>
                                <?php echo $admin_name; ?>
                                <div class="admin-info">20/05/2023 11:45:00</div>
                            </td>
                            <td>
                                <div class="status-toggle <?php echo $price['Status'] == 'active' ? 'active' : ''; ?>" 
                                     data-price="<?php echo $price['ID']; ?>">
                                    <div class="toggle-slider"></div>
                                </div>
                            </td>
                            <td>
                                <a href="update_price.php?id=<?php echo $price['ID']; ?>" class="action-icon" title="Sửa">
                                    <i class="fas fa-edit" style="color: #ffc107;"></i>
                                </a>
                                <a href="price_list.php?delete=<?php echo $price['ID']; ?>" class="action-icon" title="Xóa"
                                   onclick="return confirm('Bạn có chắc chắn muốn xóa bảng giá này?');">
                                    <i class="fas fa-trash-alt" style="color: #dc3545;"></i>
                                </a>
                            </td>
                        </tr>
                        <?php 
                            }
                        } else {
                        ?>
                        <tr>
                            <td colspan="8" class="text-center py-3">Không tìm thấy bảng giá nào</td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <div class="pagination">
                    <div>Tổng số: <?php echo mysqli_num_rows($select_prices); ?> bản ghi</div>
                    <div class="page-controls">
                        <a class="page-item" href="#"><i class="fas fa-angle-double-left"></i></a>
                        <a class="page-item active" href="#">1</a>
                        <a class="page-item" href="#"><i class="fas fa-angle-double-right"></i></a>
                    </div>
                    <div class="items-per-page">
                        <span>Hiển thị</span>
                        <select class="dropdown-per-page">
                            <option value="10">10</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        // Xử lý sự kiện click vào toggle
        $('.status-toggle').on('click', function() {
            const toggleElement = $(this);
            const priceId = toggleElement.data('price');
            const currentStatus = toggleElement.hasClass('active') ? 'active' : 'inactive';
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            
            // Thêm class loading để disable
            toggleElement.addClass('loading');
            
            // Gửi AJAX request để cập nhật trạng thái
            $.ajax({
                url: 'price_list.php',
                type: 'POST',
                data: {
                    update_status: 1,
                    price_id: priceId,
                    status: newStatus
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Cập nhật UI dựa trên trạng thái mới
                        if (newStatus === 'active') {
                            toggleElement.addClass('active');
                        } else {
                            toggleElement.removeClass('active');
                        }
                        
                        // Hiển thị thông báo thành công
                        showNotification('success', 'Cập nhật trạng thái thành công');
                    } else {
                        // Khôi phục trạng thái UI nếu có lỗi
                        if (currentStatus === 'active') {
                            toggleElement.addClass('active');
                        } else {
                            toggleElement.removeClass('active');
                        }
                        showNotification('error', response.message || 'Lỗi khi cập nhật trạng thái');
                    }
                },
                error: function() {
                    // Khôi phục trạng thái UI nếu có lỗi kết nối
                    if (currentStatus === 'active') {
                        toggleElement.addClass('active');
                    } else {
                        toggleElement.removeClass('active');
                    }
                    showNotification('error', 'Lỗi kết nối server');
                },
                complete: function() {
                    // Loại bỏ trạng thái loading
                    toggleElement.removeClass('loading');
                }
            });
        });
        
        // Hàm hiển thị thông báo
        function showNotification(type, message) {
            let alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
            let alert = `
                <div class="alert ${alertClass} alert-dismissible fade show notification-alert" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            
            // Thêm thông báo vào trang
            $('#notifications').html(alert);
            
            // Tự động ẩn sau 3 giây
            setTimeout(function() {
                $('.notification-alert').alert('close');
            }, 3000);
        }
    });
    </script>
</body>
</html>