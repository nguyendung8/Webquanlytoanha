<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Xử lý AJAX request cập nhật trạng thái
if(isset($_POST['update_status']) && isset($_POST['card_code']) && isset($_POST['status'])) {
    $card_code = mysqli_real_escape_string($conn, $_POST['card_code']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    // Kiểm tra status chỉ có thể là các giá trị hợp lệ
    $valid_statuses = ['Đã cấp phát', 'Chưa cấp phát', 'Hủy'];
    if (!in_array($status, $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Trạng thái không hợp lệ']);
        exit();
    }
    
    try {
        $update_query = mysqli_query($conn, "
            UPDATE vehiclecards 
            SET Status = '$status' 
            WHERE VehicleCardCode = '$card_code'
        ");
        
        if (mysqli_affected_rows($conn) > 0) {
            echo json_encode(['success' => true, 'message' => 'Cập nhật trạng thái thành công']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy thẻ xe hoặc trạng thái không thay đổi']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Xử lý xóa thẻ xe
if (isset($_GET['delete'])) {
    $delete_id = mysqli_real_escape_string($conn, $_GET['delete']);
    
    try {
        // Kiểm tra xem thẻ xe có đang được sử dụng không
        $check_vehicle = mysqli_query($conn, "SELECT * FROM vehicles WHERE VehicleCardCode = '$delete_id'");
        if (mysqli_num_rows($check_vehicle) > 0) {
            $_SESSION['error_msg'] = 'Không thể xóa! Thẻ xe đang được sử dụng.';
        } else {
            $delete_query = mysqli_query($conn, "DELETE FROM vehiclecards WHERE VehicleCardCode = '$delete_id'");
            
            if (mysqli_affected_rows($conn) > 0) {
                $_SESSION['success_msg'] = 'Đã xóa thẻ xe thành công!';
            } else {
                $_SESSION['error_msg'] = 'Không tìm thấy thẻ xe để xóa!';
            }
        }
    } catch (Exception $e) {
        $_SESSION['error_msg'] = 'Lỗi khi xóa thẻ xe: ' . $e->getMessage();
    }
    
    header('location: vehicle_card_list.php');
    exit();
}

// Xử lý hủy thẻ xe
if (isset($_GET['cancel'])) {
    $cancel_id = mysqli_real_escape_string($conn, $_GET['cancel']);
    
    try {
        $update_query = mysqli_query($conn, "
            UPDATE vehiclecards 
            SET Status = 'Hủy' 
            WHERE VehicleCardCode = '$cancel_id'
        ");
        
        if (mysqli_affected_rows($conn) > 0) {
            $_SESSION['success_msg'] = 'Đã hủy thẻ xe thành công!';
        } else {
            $_SESSION['error_msg'] = 'Không tìm thấy thẻ xe để hủy!';
        }
    } catch (Exception $e) {
        $_SESSION['error_msg'] = 'Lỗi khi hủy thẻ xe: ' . $e->getMessage();
    }
    
    header('location: vehicle_card_list.php');
    exit();
}

// Xử lý filter
$where_conditions = [];
$search_term = '';
$status_filter = '';

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = mysqli_real_escape_string($conn, $_GET['search']);
    $where_conditions[] = "(VehicleCardCode LIKE '%$search_term%' OR NumberPlate LIKE '%$search_term%')";
}

if (isset($_GET['status']) && !empty($_GET['status'])) {
    $status_filter = mysqli_real_escape_string($conn, $_GET['status']);
    $where_conditions[] = "Status = '$status_filter'";
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Lấy danh sách thẻ xe
$query = "SELECT * FROM vehiclecards $where_clause ORDER BY VehicleCardCode ASC";
$select_cards = mysqli_query($conn, $query) or die('Query failed: ' . mysqli_error($conn));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách thẻ xe</title>

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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .main-title {
            color: #476a52;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 0;
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
            background-color: #5a7a6a;
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
        
        .card-table {
            width: 100%;
            background-color: white;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .card-table th {
            background-color: #6b8b7b;
            color: white;
            text-align: center;
            padding: 12px;
            font-weight: 500;
            font-size: 14px;
        }
        
        .card-table td {
            padding: 12px;
            border-bottom: 1px solid #f2f2f2;
            color: #4a5568;
            text-align: center;
            font-size: 14px;
        }
        
        .card-table tr:hover {
            background-color: #f9fafb;
        }
        
        .action-icon {
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
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-issued {
            background-color: #a7c1b5;
            color: white;
        }
        
        .status-pending {
            background-color: #f0ad4e;
            color: white;
        }
        
        .status-canceled {
            background-color: #dc3545;
            color: white;
        }
        
        .vehicle-btn {
            width: fit-content !important;
            padding: 10px 25px;
            border: none;
            border-radius: 10px;
            background-color: #476a52;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            text-decoration: none;
        }
        
        .action-icon.disabled {
            cursor: not-allowed;
            opacity: 0.5;
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
                    <div>
                        <h1 class="main-title">DANH SÁCH THẺ XE</h1>
                        <div class="breadcrumb">
                            <a href="/webquanlytoanha/admin/dashboard.php">Trang chủ</a>
                            <span style="margin: 0 8px;">›</span>
                            <a href="vehicle_list.php">Phương tiện</a>
                            <span style="margin: 0 8px;">›</span>
                            <span>Thẻ xe</span>
                        </div>
                    </div>
                </div>
                
                <!-- Search and Add Section -->
                <form method="GET" action="">
                    <div class="search-container">
                        <div class="search-box">
                            <input type="text" name="search" class="search-input" placeholder="Nhập mã thẻ xe, biển số xe..." 
                                value="<?php echo htmlspecialchars($search_term); ?>">
                            <select name="status" class="dropdown-select">
                                <option value="">Trạng thái</option>
                                <option value="Đã cấp phát" <?php echo $status_filter === 'Đã cấp phát' ? 'selected' : ''; ?>>Đã cấp phát</option>
                                <option value="Chưa cấp phát" <?php echo $status_filter === 'Chưa cấp phát' ? 'selected' : ''; ?>>Chưa cấp phát</option>
                                <option value="Hủy" <?php echo $status_filter === 'Hủy' ? 'selected' : ''; ?>>Hủy</option>
                            </select>
                            <button type="submit" class="search-btn">
                                <i class="fas fa-search"></i> Tìm kiếm
                            </button>
                        </div>
                        <a href="create_vehicle_card.php" class="add-btn">
                            <i class="fas fa-plus"></i> Thêm mới
                        </a>
                    </div>
                </form>
                
                <!-- Card Table -->
                <table class="card-table">
                    <thead>
                        <tr>
                            <th>STT</th>
                            <th>MÃ THẺ</th>
                            <th>LOẠI PHƯƠNG TIỆN</th>
                            <th>BIỂN SỐ XE</th>
                            <th>GHI CHÚ</th>
                            <th>TRẠNG THÁI</th>
                            <th>THAO TÁC</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stt = 1;
                        if (mysqli_num_rows($select_cards) > 0) {
                            while($card = mysqli_fetch_assoc($select_cards)) {
                        ?>
                        <tr>
                            <td><?php echo $stt++; ?></td>
                            <td><?php echo htmlspecialchars($card['VehicleCardCode']); ?></td>
                            <td><?php echo htmlspecialchars($card['VehicleType'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($card['NumberPlate'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($card['Note'] ?? ''); ?></td>
                            <td>
                                <span class="status-badge 
                                    <?php if($card['Status'] == 'Đã cấp phát') echo 'status-issued';
                                        elseif($card['Status'] == 'Chưa cấp phát') echo 'status-pending';
                                        else echo 'status-canceled'; ?>">
                                    <?php echo $card['Status']; ?>
                                </span>
                            </td>
                            <td>
                                <?php if($card['Status'] != 'Hủy'): ?>
                                    <a href="update_vehicle_card.php?id=<?php echo $card['VehicleCardCode']; ?>" class="action-icon" title="Sửa">
                                        <i class="fas fa-edit" style="color: #ffc107;"></i>
                                    </a>
                                    <a href="vehicle_card_list.php?cancel=<?php echo $card['VehicleCardCode']; ?>" class="action-icon" title="Hủy thẻ"
                                       onclick="return confirm('Bạn có chắc chắn muốn hủy thẻ xe này?');">
                                        <i class="fas fa-times-circle" style="color: #ff6b6b;"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="action-icon disabled" title="Không thể sửa thẻ đã hủy">
                                        <i class="fas fa-edit" style="color: #ccc;"></i>
                                    </span>
                                    <span class="action-icon disabled" title="Thẻ đã bị hủy">
                                        <i class="fas fa-times-circle" style="color: #ccc;"></i>
                                    </span>
                                <?php endif; ?>
                                
                                <a href="vehicle_card_list.php?delete=<?php echo $card['VehicleCardCode']; ?>" class="action-icon" title="Xóa"
                                   onclick="return confirm('Bạn có chắc chắn muốn xóa thẻ xe này?');">
                                    <i class="fas fa-trash-alt" style="color: #dc3545;"></i>
                                </a>
                            </td>
                        </tr>
                        <?php 
                            }
                        } else {
                        ?>
                        <tr>
                            <td colspan="7" class="text-center py-3">Không tìm thấy thẻ xe nào</td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <div class="pagination">
                    <div>Tổng số: <?php echo mysqli_num_rows($select_cards); ?> bản ghi</div>
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
        // Xử lý sự kiện click vào trạng thái để thay đổi
        $('.status-badge').on('click', function() {
            const cardRow = $(this).closest('tr');
            const cardCode = cardRow.find('td:nth-child(2)').text();
            const currentStatus = $(this).text().trim();
            
            let newStatus;
            if (currentStatus === 'Chưa cấp phát') {
                newStatus = 'Đã cấp phát';
            } else if (currentStatus === 'Đã cấp phát') {
                newStatus = 'Hủy';
            } else {
                newStatus = 'Chưa cấp phát';
            }
            
            if (confirm(`Bạn có muốn chuyển trạng thái thẻ xe thành "${newStatus}"?`)) {
                // Thêm class loading để hiển thị trạng thái đang xử lý
                $(this).addClass('loading');
                
                // Gửi AJAX request để cập nhật trạng thái
                $.ajax({
                    url: 'vehicle_card_list.php',
                    type: 'POST',
                    data: {
                        update_status: 1,
                        card_code: cardCode,
                        status: newStatus
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Cập nhật UI dựa trên trạng thái mới
                            location.reload(); // Refresh trang để hiển thị đúng trạng thái mới
                        } else {
                            // Hiển thị thông báo lỗi
                            showNotification('error', response.message || 'Lỗi khi cập nhật trạng thái');
                        }
                    },
                    error: function() {
                        showNotification('error', 'Lỗi kết nối server');
                    }
                });
            }
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