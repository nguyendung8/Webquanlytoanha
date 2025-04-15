<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Xử lý AJAX request cập nhật trạng thái
if(isset($_POST['update_status']) && isset($_POST['service_code']) && isset($_POST['status'])) {
    $service_code = mysqli_real_escape_string($conn, $_POST['service_code']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    // Kiểm tra status chỉ có thể là 'active' hoặc 'inactive'
    if ($status != 'active' && $status != 'inactive') {
        echo json_encode(['success' => false, 'message' => 'Invalid status value']);
        exit();
    }
    
    try {
        $update_query = mysqli_query($conn, "
            UPDATE services 
            SET Status = '$status' 
            WHERE ServiceCode = '$service_code'
        ");
        
        if (mysqli_affected_rows($conn) > 0) {
            echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Service not found or status already set to ' . $status]);
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
    <title>Dịch vụ tòa nhà</title>

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

        .service-table th {
            background-color: #6b8b7b !important;
            color: white;
            font-weight: normal;
            text-align: center;
            vertical-align: middle;
            font-size: 14px !important;
            padding: 10px !important;
        }

        .service-table td {
            text-align: center;
            vertical-align: middle;
            font-size: 14px !important;
            padding: 10px !important;
        }

        .action-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #ddd;
            color: #666;
            margin: 0 3px;
        }

        .action-icon:hover {
            background-color: #f5f5f5;
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
            <div class="manage-container">
                <div id="notifications"></div>
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
                <h2 style="font-weight: bold; color: #476a52; margin-bottom: 10px;">DỊCH VỤ TÒA NHÀ</h2>
                <div class="breadcrumb">
                    <a href="/webquanlytoanha/admin/dashboard.php">Trang chủ</a>
                    <span style="margin: 0 8px;">›</span>
                    <span>Dịch vụ tòa nhà</span>
                </div>
            </div>

            <!-- Thêm sau page-header -->
            <div class="project-filter mb-4">
                <?php
                // Query lấy danh sách dự án
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
                ?>
                
                <select class="form-select" style="width: 300px;" 
                        onchange="window.location.href='service_list.php?project_id='+this.value">
                    <option value="">Chọn dự án</option>
                    <?php while($project = mysqli_fetch_assoc($projects_result)) { ?>
                        <option value="<?php echo $project['ProjectID']; ?>" 
                                <?php echo ($selected_project == $project['ProjectID']) ? 'selected' : ''; ?>>
                            <?php echo $project['Name']; ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <div class="row justify-content-end mt-3 mr-4">
                <a href="price_list.php" class="price-btn">
                     Bảng giá
                </a>
            </div>
            
            <!-- Search and Add Section -->
            <div class="search-container">
                <div class="search-box">
                    <input type="text" class="search-input" placeholder="Nhập từ khóa tìm kiếm...">
                    <select class="dropdown-select">
                        <option value="">Trạng thái</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <button class="search-btn">
                        <i class="fas fa-search"></i> Tìm kiếm
                    </button>
                </div>
                <?php if(empty($selected_project)): ?>
                    <button type="button" class="add-btn" onclick="showProjectAlert()">
                        <i class="fas fa-plus"></i> Thêm mới
                    </button>
                <?php else: ?>
                    <a href="create_service.php?project_id=<?php echo $selected_project; ?>" class="add-btn">
                        <i class="fas fa-plus"></i> Thêm mới
                    </a>
                <?php endif; ?>
            </div>
            
            <!-- Services Table -->
            <table class="account-table service-table">
                <thead>
                    <tr>
                        <th>STT</th>
                        <th>TÊN DỊCH VỤ</th>
                        <th>MÃ DỊCH VỤ</th>
                        <th>LOẠI DỊCH VỤ</th>
                        <th>CHU KỲ</th>
                        <th>NGÀY CHỐT</th>
                        <th>NGÀY THANH TOÁN</th>
                        <th>NGÀY ÁP DỤNG</th>
                        <th>TRẠNG THÁI</th>
                        <th>THAO TÁC</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $select_services = mysqli_query($conn, "
                        SELECT s.*, u.UserName as UpdatedBy 
                        FROM services s 
                        LEFT JOIN users u ON s.ProjectId = u.UserId 
                        WHERE " . ($selected_project ? "s.ProjectId = '$selected_project'" : "1=1") . "
                        ORDER BY s.ServiceCode
                    ") or die('Query failed');

                    $stt = 1;
                    while($service = mysqli_fetch_assoc($select_services)) {
                    ?>
                    <tr>
                        <td><?php echo $stt++; ?></td>
                        <td><?php echo $service['Name']; ?></td>
                        <td><?php echo $service['ServiceCode']; ?></td>
                        <td><?php echo $service['TypeOfService']; ?></td>
                        <td><?php echo $service['Cycle'] . ' tháng'; ?></td>
                        <td>Ngày <?php echo $service['FirstDate']; ?></td>
                        <td>Ngày <?php echo $service['SwitchDay']; ?></td>
                        <td><?php echo date('d/m/Y', strtotime($service['ApplyForm'])); ?></td>
                        <td>
                            <div class="status-toggle <?php echo $service['Status'] == 'active' ? 'active' : ''; ?>" 
                                 data-service="<?php echo $service['ServiceCode']; ?>">
                                <div class="toggle-slider"></div>
                            </div>
                        </td>
                        <td>
                            <?php if(empty($selected_project)): ?>
                                <button type="button" class="action-icon" onclick="showProjectAlert()">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="action-icon" onclick="showProjectAlert()">
                                    <i class="fas fa-trash"></i>
                                </button>
                            <?php else: ?>
                                <a href="update_service.php?code=<?php echo $service['ServiceCode']; ?>&project_id=<?php echo $selected_project; ?>" 
                                   class="action-icon">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="service_delete.php?id=<?php echo $service['ServiceCode']; ?>&project_id=<?php echo $selected_project; ?>" 
                                   class="action-icon" 
                                   onclick="return confirm('Bạn có chắc chắn muốn xóa dịch vụ này?');">
                                    <i class="fas fa-trash"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <div class="pagination">
                <div>Tổng số: <?php echo mysqli_num_rows($select_services); ?> bản ghi</div>
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
            if (!<?php echo $selected_project ? 'true' : 'false'; ?>) {
                showProjectAlert();
                return;
            }
            
            const toggleElement = $(this);
            const serviceCode = toggleElement.data('service');
            const currentStatus = toggleElement.hasClass('active') ? 'active' : 'inactive';
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            
            toggleElement.addClass('loading');
            
            $.ajax({
                url: 'service_list.php',
                type: 'POST',
                data: {
                    update_status: 1,
                    service_code: serviceCode,
                    status: newStatus,
                    project_id: '<?php echo $selected_project; ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        if (newStatus === 'active') {
                            toggleElement.addClass('active');
                        } else {
                            toggleElement.removeClass('active');
                        }
                        showNotification('success', 'Cập nhật trạng thái thành công');
                    } else {
                        showNotification('error', response.message || 'Lỗi khi cập nhật trạng thái');
                    }
                },
                error: function() {
                    showNotification('error', 'Lỗi kết nối server');
                },
                complete: function() {
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
            
            $('#notifications').html(alert);
            
            setTimeout(function() {
                $('.notification-alert').alert('close');
            }, 3000);
        }
    });

    // Hàm hiển thị thông báo khi chưa chọn dự án
    function showProjectAlert() {
        var alertHtml = `
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <strong>Thông báo!</strong> Vui lòng chọn dự án trước khi thực hiện thao tác này.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        
        // Xóa thông báo cũ nếu có
        $('.alert').remove();
        
        // Thêm thông báo mới vào đầu container
        $('.manage-container').prepend(alertHtml);
        
        // Tự động ẩn sau 3 giây
        setTimeout(function() {
            $('.alert').fadeOut('slow', function() {
                $(this).remove();
            });
        }, 3000);
    }
    </script>
</body>

</html>