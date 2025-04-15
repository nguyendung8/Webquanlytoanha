<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Lấy danh sách dự án mà staff được phân quyền
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

if (!$projects_result) {
    die("Query failed: " . mysqli_error($conn));
}

// Lấy project_id từ URL hoặc mặc định là rỗng
$selected_project = isset($_GET['project_id']) ? mysqli_real_escape_string($conn, $_GET['project_id']) : '';

// Lấy danh sách tòa nhà theo dự án được chọn
$buildings_query = "
    SELECT DISTINCT b.ID, b.Name 
    FROM Buildings b
    WHERE b.Status = 'active'
    " . ($selected_project ? "AND b.ProjectId = '$selected_project'" : "") . "
    ORDER BY b.Name";
$select_buildings = mysqli_query($conn, $buildings_query);

// Các tham số tìm kiếm và phân trang
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$building_filter = isset($_GET['building']) ? mysqli_real_escape_string($conn, $_GET['building']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$records_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Xây dựng điều kiện WHERE
$where_conditions = [];
if (!empty($search)) {
    $where_conditions[] = "(a.Code LIKE '%$search%' OR a.Name LIKE '%$search%')";
}
if (!empty($building_filter)) {
    $where_conditions[] = "a.BuildingId = '$building_filter'";
}
if (!empty($status_filter)) {
    $where_conditions[] = "a.Status = '$status_filter'";
}
if (!empty($selected_project)) {
    $where_conditions[] = "b.ProjectId = '$selected_project'";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Query đếm tổng số bản ghi
$count_query = "
    SELECT COUNT(DISTINCT a.ApartmentID) as total 
    FROM apartment a 
    LEFT JOIN Buildings b ON a.BuildingId = b.ID 
    LEFT JOIN Floors f ON a.FloorId = f.ID
    $where_clause";
$count_result = mysqli_query($conn, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Query lấy danh sách căn hộ
$select_apartments = mysqli_query($conn, "
    SELECT a.*, b.Name as BuildingName, f.Name as FloorName
    FROM apartment a 
    LEFT JOIN Buildings b ON a.BuildingId = b.ID 
    LEFT JOIN Floors f ON a.FloorId = f.ID
    $where_clause 
    ORDER BY a.ApartmentID 
    LIMIT $offset, $records_per_page
") or die('Query failed');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý căn hộ</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/admin_style.css">
    <style>
        .stats-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .stats-card .icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            margin-right: 15px;
        }

        .stats-number {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
        }

        .stats-label {
            color: #666;
            margin: 0;
        }

        .stats-link {
            color: #476a52;
            text-decoration: none;
            font-size: 14px;
        }

        .search-container {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .apartment-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }

        .apartment-table th {
            background: #6b8b7b !important;
            color: white;
            font-weight: 500;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-occupied {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-pending {
            background: #fff3e0;
            color: #ef6c00;
        }

        .status-renovating {
            background: #e3f2fd;
            color: #1565c0;
        }

        .status-empty {
            background: #ffebee;
            color: #c62828;
        }

        .status-away {
            background: #f3e5f5;
            color: #6a1b9a;
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

        .add-btn {
            margin-bottom: 10px;
            width: fit-content;
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            background-color: #476a52 !important;
            color: white !important;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .project-select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .alert {
            margin-bottom: 20px;
            border-radius: 8px;
        }

        .btn-close:focus {
            box-shadow: none;
        }

        .alert-warning {
            background-color: #fff3cd;
            border-color: #ffecb5;
            color: #664d03;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <?php include '../admin_navbar.php'; ?>
        <div style="width: 100%;">
            <?php include '../admin_header.php'; ?>
            <div class="manage-container p-4">
                

                <div class="page-header">
                    <h2 style="font-weight: bold; color: #476a52; margin-bottom: 10px; text-transform: uppercase;">QUẢN LÝ CĂN HỘ</h2>
                    <div class="breadcrumb">
                        <a href="dashboard.php">Trang chủ</a>
                        <span style="margin: 0 8px;">›</span>
                        <span>Quản lý căn hộ</span>
                    </div>
                </div>

                <!-- Thêm vào sau phần breadcrumb và trước phần search -->
                <div class="mb-4 mt-4">
                    <select class="project-select form-select" style="width: 300px;" 
                            onchange="window.location.href='apartment_management.php?project_id='+this.value">
                        <option value="">Chọn dự án</option>
                        <?php while($project = mysqli_fetch_assoc($projects_result)) { ?>
                            <option value="<?php echo $project['ProjectID']; ?>" 
                                    <?php echo ($selected_project == $project['ProjectID']) ? 'selected' : ''; ?>>
                                <?php echo $project['Name']; ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <!-- Search Section -->
                <div class="search-container">
                    <form action="" method="GET" class="row g-3">
                        <input type="hidden" name="project_id" value="<?php echo $selected_project; ?>">
                        <div class="col-md-3">
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Nhập từ khóa tìm kiếm..." 
                                   value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        </div>
                        <div class="col-md-2">
                            <select name="building" class="form-select">
                                <option value="">Chọn tòa nhà</option>
                                <?php while($building = mysqli_fetch_assoc($select_buildings)) { ?>
                                    <option value="<?php echo $building['ID']; ?>" 
                                            <?php echo ($building_filter == $building['ID']) ? 'selected' : ''; ?>>
                                        <?php echo $building['Name']; ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="status" class="form-select">
                                <option value="">Trạng thái</option>
                                <option value="Đang ở" <?php echo ($status_filter == 'Đang ở') ? 'selected' : ''; ?>>Đang ở</option>
                                <option value="Đang chờ nhận" <?php echo ($status_filter == 'Đang chờ nhận') ? 'selected' : ''; ?>>Đang chờ nhận</option>
                                <option value="Đang sửa chữa" <?php echo ($status_filter == 'Đang sửa chữa') ? 'selected' : ''; ?>>Đang sửa chữa</option>
                                <option value="Trống" <?php echo ($status_filter == 'Trống') ? 'selected' : ''; ?>>Trống</option>
                                <option value="Tạm vắng" <?php echo ($status_filter == 'Tạm vắng') ? 'selected' : ''; ?>>Tạm vắng</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-search"></i> Tìm kiếm
                            </button>
                        </div>
                        <div class="col-md-3 text-end">
                            <?php if(empty($selected_project)): ?>
                                <button type="button" class="btn add-btn" onclick="showProjectAlert()">
                                    <i class="fas fa-plus"></i> Thêm mới
                                </button>
                            <?php else: ?>
                                <a href="create_apartment.php?project_id=<?php echo $selected_project; ?>" class="btn add-btn">
                                    <i class="fas fa-plus"></i> Thêm mới
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                
                <!-- Apartment Table -->
                <div class="apartment-table">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>STT</th>
                                <th>MÃ CĂN HỘ</th>
                                <th>TÊN CĂN HỘ</th>
                                <th>TÒA NHÀ</th>
                                <th>THÔNG TIN</th>
                                <th>TÌNH TRẠNG</th>
                                <th>THAO TÁC</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if(mysqli_num_rows($select_apartments) > 0){
                                $stt = $offset + 1;
                                while($apartment = mysqli_fetch_assoc($select_apartments)){
                            ?>
                            <tr>
                                <td><?php echo $stt++; ?></td>
                                <td><?php echo $apartment['Code']; ?></td>
                                <td><?php echo $apartment['Name']; ?></td>
                                <td><?php echo $apartment['BuildingName']; ?></td>
                                <td>
                                    <i class="fas fa-building"></i> Tầng: <?php echo $apartment['FloorName']; ?> 
                                    <i class="fas fa-bed ms-2"></i> <?php echo $apartment['NumberOffBedroom']; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php 
                                        switch($apartment['Status']){
                                            case 'Đang ở': echo 'status-occupied'; break;
                                            case 'Đang chờ nhận': echo 'status-pending'; break;
                                            case 'Đang sửa chữa': echo 'status-renovating'; break;
                                            case 'Trống': echo 'status-empty'; break;
                                            case 'Tạm vắng': echo 'status-away'; break;
                                        }
                                    ?>">
                                        <?php echo $apartment['Status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if(empty($selected_project)): ?>
                                        <button type="button" class="text-warning me-2 btn btn-link p-0" onclick="showProjectAlert()">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    <?php else: ?>
                                        <a href="update_apartment.php?id=<?php echo $apartment['ApartmentID']; ?>&project_id=<?php echo $selected_project; ?>" 
                                           class="text-warning me-2">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php
                                }
                            } else {
                                echo '<tr><td colspan="7" class="text-center">Không có dữ liệu</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <div>Tổng số: <?php echo $total_records; ?> bản ghi</div>
                    <nav aria-label="Page navigation">
                        <ul class="pagination mb-0">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=1&project_id=<?php echo $selected_project; ?><?php 
                                        echo !empty($search) ? "&search=$search" : '';
                                        echo !empty($building_filter) ? "&building=$building_filter" : '';
                                        echo !empty($status_filter) ? "&status=$status_filter" : '';
                                        echo "&per_page=$records_per_page";
                                    ?>">«</a>
                                </li>
                            <?php endif; ?>

                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);

                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&project_id=<?php echo $selected_project; ?><?php 
                                        echo !empty($search) ? "&search=$search" : '';
                                        echo !empty($building_filter) ? "&building=$building_filter" : '';
                                        echo !empty($status_filter) ? "&status=$status_filter" : '';
                                        echo "&per_page=$records_per_page";
                                    ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $total_pages; ?>&project_id=<?php echo $selected_project; ?><?php 
                                        echo !empty($search) ? "&search=$search" : '';
                                        echo !empty($building_filter) ? "&building=$building_filter" : '';
                                        echo !empty($status_filter) ? "&status=$status_filter" : '';
                                        echo "&per_page=$records_per_page";
                                    ?>">»</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <div class="d-flex align-items-center">
                        <span class="me-2">Hiển thị</span>
                        <select class="form-select" style="width: auto;" onchange="window.location.href=this.value">
                            <?php foreach ([10, 25, 50, 100] as $per_page): ?>
                                <option value="?page=1&project_id=<?php echo $selected_project; ?><?php 
                                    echo !empty($search) ? "&search=$search" : '';
                                    echo !empty($building_filter) ? "&building=$building_filter" : '';
                                    echo !empty($status_filter) ? "&status=$status_filter" : '';
                                    echo "&per_page=$per_page";
                                ?>" <?php echo ($records_per_page == $per_page) ? 'selected' : ''; ?>>
                                    <?php echo $per_page; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function showProjectAlert() {
        // Tạo và hiển thị alert bootstrap
        var alertHtml = `
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <strong>Thông báo!</strong> Vui lòng chọn dự án trước khi thực hiện thao tác này.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        
        // Kiểm tra xem đã có alert nào chưa
        var existingAlert = document.querySelector('.alert');
        if (existingAlert) {
            existingAlert.remove();
        }
        
        // Thêm alert mới vào đầu container
        var container = document.querySelector('.manage-container');
        container.insertAdjacentHTML('afterbegin', alertHtml);
        
        // Tự động đóng alert sau 3 giây
        setTimeout(function() {
            var alert = document.querySelector('.alert');
            if (alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 3000);
    }

    // Thêm project_id vào tất cả các URL khi chọn dự án
    document.querySelector('.project-select').addEventListener('change', function() {
        var projectId = this.value;
        if (projectId) {
            var currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('project_id', projectId);
            window.location.href = currentUrl.toString();
        }
    });
    </script>
</body>
</html>