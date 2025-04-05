<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Xử lý AJAX request cập nhật trạng thái
if(isset($_POST['update_status']) && isset($_POST['vehicle_code']) && isset($_POST['status'])) {
    $vehicle_code = mysqli_real_escape_string($conn, $_POST['vehicle_code']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    // Kiểm tra status chỉ có thể là 'active' hoặc 'inactive'
    if ($status != 'active' && $status != 'inactive') {
        echo json_encode(['success' => false, 'message' => 'Invalid status value']);
        exit();
    }
    
    try {
        $update_query = mysqli_query($conn, "
            UPDATE vehicles 
            SET Status = '$status' 
            WHERE VehicleCode = '$vehicle_code'
        ");
        
        if (mysqli_affected_rows($conn) > 0) {
            echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Vehicle not found or status already set to ' . $status]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Xóa tài khoản người dùng
if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];

        // Xóa tài khoản người dùng
        $delete_query = mysqli_query($conn, "DELETE FROM `users` WHERE UserID = '$delete_id'") or die('Query failed');

        if ($delete_query) {
            $message[] = 'Xóa tài khoản người dùng thành công!';
        } else {
            $message[] = 'Xóa tài khoản người dùng thất bại!';
        }

    header('location:acount.php');
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
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý phương tiện</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="css/admin_style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            background-color: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .page-header {
            padding: 15px 20px;
            color: #4a5568;
            border-bottom: 1px solid #eaeaea;
            background-color: #f5f5f5;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            flex-wrap: wrap;
            gap: 10px;
            margin: 20px 0;
            justify-content: space-between;
        }
        
        .search-box {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .search-input {
            width: 220px;
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            outline: none;
        }
        
        .dropdown-select {
            width: 160px;
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            outline: none;
            background-color: white;
            cursor: pointer;
        }
        
        .search-btn {
            padding: 8px 20px;
            border: none;
            border-radius: 4px;
            background-color: #6b8b7b;
            color: white;
            cursor: pointer;
        }
        
        .add-btn {
            padding: 8px 20px;
            border: none;
            border-radius: 4px;
            background-color: #5a7a6a;
            color: white;
            cursor: pointer;
            text-decoration: none;
        }
        
        .add-btn:hover {
            background-color: #5a7a6a;
            color: white;
        }
        
        .account-table {
            width: 100%;
            background-color: white;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .account-table th {
            background-color: #6b8b7b;
            color: white;
            text-align: center;
            padding: 12px 15px;
            font-weight: 500;
            font-size: 14px;
        }
        
        .account-table td {
            padding: 10px 15px;
            border-bottom: 1px solid #f2f2f2;
            color: #4a5568;
            text-align: center;
            font-size: 14px;
        }
        
        .account-table tr:hover {
            background-color: #f9fafb;
        }
        
        .status-label {
            padding: 5px 10px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }
        
        .status-active {
            background-color: rgba(72, 187, 120, 0.1);
            color: #48bb78;
        }
        
        .status-inactive {
            background-color: rgba(229, 62, 62, 0.1);
            color: #e53e3e;
        }
        
        .action-icon {
            color: #6b8b7b;
            font-size: 18px;
            margin: 0 5px;
            cursor: pointer;
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

        .status-toggle {
            width: 60px;
            height: 30px;
            background-color: #ccc;
            border-radius: 30px;
            padding: 3px;
            position: relative;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .status-toggle.active {
            background-color: #28a745;
        }

        .toggle-slider {
            width: 24px;
            height: 24px;
            background-color: white;
            border-radius: 50%;
            position: absolute;
            transition: transform 0.3s ease;
        }

        .status-toggle.active .toggle-slider {
            transform: translateX(30px);
        }

        .status-toggle.loading {
            opacity: 0.7;
            pointer-events: none;
        }
        
        .notification-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            min-width: 300px;
        }
        
        .main-content-header {
            color: #476a52;
            text-transform: uppercase;
            font-weight: bold;
            margin: 0;
        }
        
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 5px;
        }
        
        .action-buttons a {
            color: #6b8b7b;
            font-size: 18px;
            text-decoration: none;
        }

        .price-btn {
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
    </style>
</head>

<body>
    <div class="d-flex">
        <?php include '../admin_navbar.php'; ?>
        <div style="width: 100%;">
            <?php include '../admin_header.php'; ?>
            <div class="manage-container">
                <div id="notifications"></div>
                
                <!-- Page Header -->
                <div class="page-header">
                    <div>
                        <h1 class="main-content-header">PHƯƠNG TIỆN</h1>
                        <div class="breadcrumb">
                            <a href="/webquanlytoanha/admin/dashboard.php">Trang chủ</a>
                            <span style="margin: 0 8px;">›</span>
                            <span>Phương tiện</span>
                        </div>
                    </div>
                </div>

                <div class="row justify-content-end mt-3 mr-4">
                    <a href="vehicle_card_list.php" class="price-btn">
                        Thẻ xe
                    </a>
                </div>
                
                <!-- Search and Add Section -->
                <div class="search-container">
                    <div class="search-box">
                        <input type="text" class="search-input" placeholder="Nhập tên phương tiện, biển số">
                        <select class="dropdown-select">
                            <option value="">Căn hộ</option>
                            <!-- Thêm options từ database -->
                        </select>
                        <select class="dropdown-select">
                            <option value="">Loại phương tiện</option>
                            <option value="Oto">Oto</option>
                            <option value="Xe đạp">Xe đạp</option>
                            <option value="Xe máy">Xe máy</option>
                        </select>
                        <select class="dropdown-select">
                            <option value="">Trạng thái</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        <button class="search-btn">
                            <i class="fas fa-search"></i> Tìm kiếm
                        </button>
                    </div>
                    <a href="create_vehicle.php" class="add-btn">
                        <i class="fas fa-plus"></i> Thêm mới
                    </a>
                </div>
                
                <!-- Vehicles Table -->
                <table class="account-table">
                    <thead>
                        <tr>
                            <th>STT</th>
                            <th>TÊN PHƯƠNG TIỆN</th>
                            <th>CĂN HỘ</th>
                            <th>LOẠI PHƯƠNG TIỆN</th>
                            <th>BIỂN SỐ</th>
                            <th>MỨC ƯU TIÊN</th>
                            <th>PHÍ</th>
                            <th>NGÀY BẮT ĐẦU TÍNH PHÍ</th>
                            <th>NGÀY KẾT THÚC TÍNH PHÍ</th>
                            <th>NGƯỜI CẬP NHẬT</th>
                            <th>TRẠNG THÁI</th>
                            <th>THAO TÁC</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Sample data
                        $vehicles = [
                            [
                                'id' => 1,
                                'name' => 'Oto V3',
                                'apartment' => 'A01 - 01',
                                'type' => 'Oto',
                                'plate' => '29V09387',
                                'priority' => 1,
                                'fee' => '200000',
                                'start_date' => '01/12/2024',
                                'end_date' => '__/__/__',
                                'updater' => 'Admin',
                                'status' => 'active'
                            ],
                            [
                                'id' => 2,
                                'name' => 'Xe đạp',
                                'apartment' => 'A01 - 02',
                                'type' => 'Xe đạp',
                                'plate' => '29V09387',
                                'priority' => 1,
                                'fee' => '50000',
                                'start_date' => '15/01/2025',
                                'end_date' => '__/__/__',
                                'updater' => 'Admin',
                                'status' => 'inactive'
                            ],
                            [
                                'id' => 3,
                                'name' => 'Xe máy Honda',
                                'apartment' => 'A01 - 03',
                                'type' => 'Xe máy',
                                'plate' => '29V09387',
                                'priority' => 2,
                                'fee' => '150000',
                                'start_date' => '01/03/2025',
                                'end_date' => '__/__/__',
                                'updater' => 'Admin',
                                'status' => 'inactive'
                            ],
                            [
                                'id' => 4,
                                'name' => 'Xe đạp điện honda',
                                'apartment' => 'A02 - 01',
                                'type' => 'Xe đạp',
                                'plate' => '29V09387',
                                'priority' => 1,
                                'fee' => '50000',
                                'start_date' => '01/03/2025',
                                'end_date' => '__/__/__',
                                'updater' => 'Admin',
                                'status' => 'active'
                            ],
                            [
                                'id' => 5,
                                'name' => 'Xe máy yamaha',
                                'apartment' => 'B01 - 01',
                                'type' => 'Xe máy',
                                'plate' => '29V09387',
                                'priority' => 1,
                                'fee' => '200000',
                                'start_date' => '01/04/2025',
                                'end_date' => '__/__/__',
                                'updater' => 'Admin',
                                'status' => 'active'
                            ],
                            [
                                'id' => 6,
                                'name' => 'Oto G63',
                                'apartment' => 'B01 - 02',
                                'type' => 'oto',
                                'plate' => '29V09387',
                                'priority' => 2,
                                'fee' => '400000',
                                'start_date' => '01/04/2025',
                                'end_date' => '__/__/__',
                                'updater' => 'Admin',
                                'status' => 'active'
                            ],
                            [
                                'id' => 7,
                                'name' => 'Oto V3',
                                'apartment' => 'B01 - 03',
                                'type' => 'oto',
                                'plate' => '29V09387',
                                'priority' => 1,
                                'fee' => '400000',
                                'start_date' => '01/04/2025',
                                'end_date' => '__/__/__',
                                'updater' => 'Admin',
                                'status' => 'active'
                            ],
                        ];

                        foreach ($vehicles as $index => $vehicle) {
                        ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo $vehicle['name']; ?></td>
                            <td><?php echo $vehicle['apartment']; ?></td>
                            <td><?php echo $vehicle['type']; ?></td>
                            <td><?php echo $vehicle['plate']; ?></td>
                            <td><?php echo $vehicle['priority']; ?></td>
                            <td><?php echo number_format($vehicle['fee']); ?></td>
                            <td><?php echo $vehicle['start_date']; ?></td>
                            <td><?php echo $vehicle['end_date']; ?></td>
                            <td><?php echo $vehicle['updater']; ?></td>
                            <td>
                                <div class="status-toggle <?php echo $vehicle['status'] == 'active' ? 'active' : ''; ?>" 
                                     data-vehicle="<?php echo $vehicle['id']; ?>">
                                    <div class="toggle-slider"></div>
                                </div>
                            </td>
                            <td class="action-buttons">
                                <a href="update_vehicle.php?id=<?php echo $vehicle['id']; ?>" title="Sửa">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="delete_vehicle.php?id=<?php echo $vehicle['id']; ?>" title="Xóa" 
                                   onclick="return confirm('Bạn có chắc chắn muốn xóa phương tiện này?');">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <div class="pagination">
                    <div>Tổng số: 7 bản ghi</div>
                    <div class="page-controls">
                        <div class="page-item"><i class="fas fa-angle-double-left"></i></div>
                        <div class="page-item active">1</div>
                        <div class="page-item"><i class="fas fa-angle-double-right"></i></div>
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
            const vehicleId = toggleElement.data('vehicle');
            const currentStatus = toggleElement.hasClass('active') ? 'active' : 'inactive';
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            
            // Thêm class loading để disable và hiển thị trạng thái đang xử lý
            toggleElement.addClass('loading');
            
            // Gửi AJAX request để cập nhật trạng thái
            $.ajax({
                url: 'vehicle_list.php',
                type: 'POST',
                data: {
                    update_status: 1,
                    vehicle_code: vehicleId,
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
                        showNotification('error', response.message || 'Lỗi khi cập nhật trạng thái');
                    }
                },
                error: function() {
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