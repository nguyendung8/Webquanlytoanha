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

// Xây dựng query với các điều kiện lọc
$where_conditions = [];
$params = [];

if(isset($_GET['search']) && !empty($_GET['search'])) {
    $search = mysqli_real_escape_string($conn, $_GET['search']);
    $where_conditions[] = "(v.VehicleName LIKE '%$search%' OR v.NumberPlate LIKE '%$search%')";
}

if(isset($_GET['apartment']) && !empty($_GET['apartment'])) {
    $apartment = mysqli_real_escape_string($conn, $_GET['apartment']);
    $where_conditions[] = "v.ApartmentID = '$apartment'";
}

if(isset($_GET['type']) && !empty($_GET['type'])) {
    $type = mysqli_real_escape_string($conn, $_GET['type']);
    $where_conditions[] = "v.TypeVehicle = '$type'";
}

if(isset($_GET['status']) && !empty($_GET['status'])) {
    $status = mysqli_real_escape_string($conn, $_GET['status']);
    $where_conditions[] = "v.Status = '$status'";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Sửa lại query chính để thêm điều kiện lọc
$query = "
    SELECT 
        v.VehicleCode,
        v.VehicleName,
        v.TypeVehicle,
        v.NumberPlate,
        v.Status,
        a.Code AS ApartmentCode,
        a.Name AS ApartmentName,
        sv.ApplyFeeDate,
        sv.EndFeeDate,
        pl.Name AS PriceName,
        pl.Price,
        u.UserName AS UpdatedBy
    FROM vehicles v
    LEFT JOIN apartment a ON v.ApartmentID = a.ApartmentID
    LEFT JOIN ServiceVehicles sv ON v.VehicleCode = sv.VehicleCode
    LEFT JOIN services s ON sv.ServiceId = s.ServiceCode
    LEFT JOIN ServicePrice sp ON s.ServiceCode = sp.ServiceId
    LEFT JOIN pricelist pl ON sp.PriceId = pl.ID
    LEFT JOIN users u ON v.VehicleOwnerID = u.ResidentID
    $where_clause
    ORDER BY v.VehicleCode DESC
";

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
                        <input type="text" id="search_text" class="search-input" placeholder="Nhập tên phương tiện, biển số">
                        <select class="dropdown-select" id="apartment_filter">
                            <option value="">Tất cả căn hộ</option>
                            <?php 
                            $apartment_query = mysqli_query($conn, "
                                SELECT DISTINCT a.ApartmentID, a.Code, a.Name 
                                FROM apartment a 
                                INNER JOIN vehicles v ON a.ApartmentID = v.ApartmentID 
                                ORDER BY a.Code
                            ");
                            while($apt = mysqli_fetch_assoc($apartment_query)) {
                                echo '<option value="'.$apt['ApartmentID'].'">'
                                    .htmlspecialchars($apt['Code'].' - '.$apt['Name']).'</option>';
                            }
                            ?>
                        </select>
                        <select class="dropdown-select" id="vehicle_type_filter">
                            <option value="">Tất cả loại xe</option>
                            <option value="Ô tô">Ô tô</option>
                            <option value="Xe máy">Xe máy</option>
                            <option value="Xe đạp">Xe đạp</option>
                            <option value="Xe máy điện">Xe máy điện</option>
                            <option value="Ô tô điện">Ô tô điện</option>
                            <option value="Khác">Khác</option>
                        </select>
                        <select class="dropdown-select" id="status_filter">
                            <option value="">Tất cả trạng thái</option>
                            <option value="active">Đang hoạt động</option>
                            <option value="inactive">Ngừng hoạt động</option>
                        </select>
                        <button class="search-btn" id="search_btn">
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
                        // Thay thế phần dữ liệu mẫu bằng query từ database
                        $result = mysqli_query($conn, $query);

                        if (!$result) {
                            die("Query failed: " . mysqli_error($conn));
                        }

                        // Thay thế phần hiển thị bảng
                        $index = 1;
                        while ($vehicle = mysqli_fetch_assoc($result)) {
                        ?>
                        <tr>
                            <td><?php echo $index++; ?></td>
                            <td><?php echo htmlspecialchars($vehicle['VehicleName']); ?></td>
                            <td><?php echo htmlspecialchars($vehicle['ApartmentCode'] . ' - ' . $vehicle['ApartmentName']); ?></td>
                            <td><?php echo htmlspecialchars($vehicle['TypeVehicle']); ?></td>
                            <td><?php echo htmlspecialchars($vehicle['NumberPlate']); ?></td>
                            <td><?php echo htmlspecialchars($vehicle['PriceName'] ?? 'Chưa có'); ?></td>
                            <td><?php echo $vehicle['Price'] ? number_format($vehicle['Price']) : '0'; ?></td>
                            <td><?php echo $vehicle['ApplyFeeDate'] ? date('d/m/Y', strtotime($vehicle['ApplyFeeDate'])) : '__/__/__'; ?></td>
                            <td><?php echo $vehicle['EndFeeDate'] ? date('d/m/Y', strtotime($vehicle['EndFeeDate'])) : '__/__/__'; ?></td>
                            <td><?php echo htmlspecialchars($vehicle['UpdatedBy'] ?? 'Admin'); ?></td>
                            <td>
                                <div class="status-toggle <?php echo $vehicle['Status'] == 'active' ? 'active' : ''; ?>" 
                                     data-vehicle="<?php echo htmlspecialchars($vehicle['VehicleCode']); ?>">
                                    <div class="toggle-slider"></div>
                                </div>
                            </td>
                            <td class="action-buttons">
                                <a href="update_vehicle.php?id=<?php echo urlencode($vehicle['VehicleCode']); ?>" title="Sửa">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="delete_vehicle.php?id=<?php echo urlencode($vehicle['VehicleCode']); ?>" title="Xóa" 
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
                    <div>Tổng số: <?php echo mysqli_num_rows($result); ?> bản ghi</div>
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

        // Xử lý sự kiện tìm kiếm
        function performSearch() {
            const searchText = $('#search_text').val();
            const apartmentId = $('#apartment_filter').val();
            const vehicleType = $('#vehicle_type_filter').val();
            const status = $('#status_filter').val();
            
            // Xây dựng URL với các tham số tìm kiếm
            let searchParams = new URLSearchParams(window.location.search);
            
            // Cập nhật hoặc xóa các tham số tìm kiếm
            if(searchText) searchParams.set('search', searchText);
            else searchParams.delete('search');
            
            if(apartmentId) searchParams.set('apartment', apartmentId);
            else searchParams.delete('apartment');
            
            if(vehicleType) searchParams.set('type', vehicleType);
            else searchParams.delete('type');
            
            if(status) searchParams.set('status', status);
            else searchParams.delete('status');
            
            // Chuyển hướng đến URL mới với các tham số tìm kiếm
            window.location.href = `${window.location.pathname}?${searchParams.toString()}`;
        }

        // Xử lý sự kiện click nút tìm kiếm
        $('#search_btn').click(function(e) {
            e.preventDefault();
            performSearch();
        });

        // Xử lý sự kiện nhấn Enter trong ô tìm kiếm
        $('#search_text').keypress(function(e) {
            if(e.which == 13) {
                e.preventDefault();
                performSearch();
            }
        });

        // Tự động submit form khi thay đổi giá trị của các dropdown
        $('.dropdown-select').change(function() {
            performSearch();
        });

        // Set giá trị cho các trường tìm kiếm từ URL
        const urlParams = new URLSearchParams(window.location.search);
        if(urlParams.has('search')) $('#search_text').val(urlParams.get('search'));
        if(urlParams.has('apartment')) $('#apartment_filter').val(urlParams.get('apartment'));
        if(urlParams.has('type')) $('#vehicle_type_filter').val(urlParams.get('type'));
        if(urlParams.has('status')) $('#status_filter').val(urlParams.get('status'));
    });
    </script>
</body>

</html>