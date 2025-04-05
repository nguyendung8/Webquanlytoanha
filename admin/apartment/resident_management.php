<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Thêm code xử lý xóa vào đầu file, sau phần session
if (isset($_GET['id'])) {
    $resident_id = mysqli_real_escape_string($conn, $_GET['id']);
    
    mysqli_begin_transaction($conn);
    try {
        // 1. Xóa các bản ghi liên quan trong bảng vehicles
        mysqli_query($conn, "UPDATE vehicles SET VehicleOwnerID = NULL WHERE VehicleOwnerID = '$resident_id'");

        // 2. Xóa các bản ghi liên quan trong bảng contracts
        mysqli_query($conn, "DELETE FROM contracts WHERE ResidentID = '$resident_id'");

        // 3. Xóa các bản ghi liên quan trong bảng payments
        mysqli_query($conn, "DELETE FROM payments WHERE ResidentID = '$resident_id'");

        // 4. Xóa các bản ghi liên quan trong bảng receipts
        mysqli_query($conn, "DELETE FROM receipts WHERE ResidentID = '$resident_id'");

        // 5. Xóa các bản ghi liên quan trong bảng debtstatements
        mysqli_query($conn, "DELETE FROM debtstatements WHERE ResidentID = '$resident_id'");

        // 6. Xóa các bản ghi liên quan trong bảng ResidentApartment
        mysqli_query($conn, "DELETE FROM ResidentApartment WHERE ResidentId = '$resident_id'");

        // 7. Xóa user account
        mysqli_query($conn, "DELETE FROM users WHERE ResidentID = '$resident_id'");

        // 8. Cuối cùng xóa resident
        mysqli_query($conn, "DELETE FROM resident WHERE ID = '$resident_id'");

        mysqli_commit($conn);
        $_SESSION['success_msg'] = 'Xóa cư dân thành công!';
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error_msg'] = 'Lỗi: ' . $e->getMessage();
    }

    // Redirect lại trang với các tham số tìm kiếm và phân trang
    $redirect_url = 'resident_management.php';
    $params = [];
    
    if (!empty($_GET['search'])) $params[] = 'search=' . urlencode($_GET['search']);
    if (!empty($_GET['building'])) $params[] = 'building=' . urlencode($_GET['building']);
    if (!empty($_GET['mobile'])) $params[] = 'mobile=' . urlencode($_GET['mobile']);
    if (!empty($_GET['page'])) $params[] = 'page=' . urlencode($_GET['page']);
    if (!empty($_GET['per_page'])) $params[] = 'per_page=' . urlencode($_GET['per_page']);
    
    if (!empty($params)) {
        $redirect_url .= '?' . implode('&', $params);
    }
    
    header("Location: $redirect_url");
    exit();
}

// Lấy danh sách tòa nhà cho filter
$select_buildings = mysqli_query($conn, "SELECT ID, Name FROM Buildings WHERE Status = 'active'");

// Xử lý các tham số tìm kiếm và phân trang
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$building_filter = isset($_GET['building']) ? mysqli_real_escape_string($conn, $_GET['building']) : '';
$mobile_filter = isset($_GET['mobile']) ? mysqli_real_escape_string($conn, $_GET['mobile']) : '';

// Thiết lập phân trang
$records_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Xây dựng câu query với điều kiện tìm kiếm
$where_conditions = [];
if (!empty($search)) {
    $where_conditions[] = "(u.UserName LIKE '%$search%' OR u.Email LIKE '%$search%' OR u.PhoneNumber LIKE '%$search%')";
}
if (!empty($building_filter)) {
    $where_conditions[] = "a.BuildingId = '$building_filter'";
}
if (!empty($mobile_filter)) {
    if($mobile_filter == '1') {
        $where_conditions[] = "u.UserId IS NOT NULL";
    } else {
        $where_conditions[] = "u.UserId IS NULL";
    }
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Query đếm tổng số bản ghi
$count_query = mysqli_query($conn, "
    SELECT COUNT(DISTINCT r.ID) as total 
    FROM resident r 
    LEFT JOIN users u ON r.ID = u.ResidentID
    LEFT JOIN ResidentApartment ra ON r.ID = ra.ResidentId
    LEFT JOIN apartment a ON ra.ApartmentId = a.ApartmentID
    $where_clause
") or die('Count query failed: ' . mysqli_error($conn));

$total_records = mysqli_fetch_assoc($count_query)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Query lấy danh sách cư dân
$select_residents = mysqli_query($conn, "
    SELECT DISTINCT r.ID, r.NationalId, r.Dob, r.Gender,
           u.UserName, u.Email, u.PhoneNumber,
           CASE 
               WHEN u.UserId IS NOT NULL THEN 'Đã kích hoạt'
               ELSE 'Chưa kích hoạt'
           END as Status
    FROM resident r 
    LEFT JOIN users u ON r.ID = u.ResidentID
    LEFT JOIN ResidentApartment ra ON r.ID = ra.ResidentId
    LEFT JOIN apartment a ON ra.ApartmentId = a.ApartmentID
    $where_clause 
    ORDER BY r.ID 
    LIMIT $offset, $records_per_page
") or die('Select query failed: ' . mysqli_error($conn));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý cư dân</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/admin_style.css">
    <style>
        
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

        .resident-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }

        .resident-table th {
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

        .status-active {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-inactive {
            background: #ffebee;
            color: #c62828;
        }

        .action-icon {
            color: #666;
            margin: 0 5px;
            font-size: 16px;
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
    </style>
</head>

<body>
    <div class="d-flex">
        <?php include '../admin_navbar.php'; ?>
        <div style="width: 100%;">
            <?php include '../admin_header.php'; ?>
            <div class="manage-container p-4">
                

                <div class="page-header">
                    <h2 style="font-weight: bold; color: #476a52; margin-bottom: 10px; text-transform: uppercase;">QUẢN LÝ CƯ DÂN</h2>
                    <div class="breadcrumb">
                        <a href="dashboard.php">Trang chủ</a>
                        <span style="margin: 0 8px;">›</span>
                        <span>Quản lý cư dân</span>
                    </div>
                </div>

                <!-- Search Section -->
                <div class="search-container">
                    <form action="" method="GET" class="row g-3">
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
                            <select name="mobile" class="form-select">
                                <option value="">Mobile active</option>
                                <option value="1" <?php echo ($mobile_filter == '1') ? 'selected' : ''; ?>>Đã kích hoạt</option>
                                <option value="0" <?php echo ($mobile_filter == '0') ? 'selected' : ''; ?>>Chưa kích hoạt</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-search"></i> Tìm kiếm
                            </button>
                        </div>
                        <div class="col-md-3 text-end">
                            <a href="create_resident.php" class="btn add-btn">
                                <i class="fas fa-plus"></i> Thêm mới
                            </a>
                        </div>
                    </form>
                </div>
                
                
                <!-- Residents Table -->
                <div class="resident-table">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>STT</th>
                                <th>HỌ VÀ TÊN</th>
                                <th>SỐ ĐIỆN THOẠI</th>
                                <th>EMAIL</th>
                                <th>ACTIVE</th>
                                <th>THAO TÁC</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if(mysqli_num_rows($select_residents) > 0){
                                $stt = $offset + 1;
                                while($resident = mysqli_fetch_assoc($select_residents)){
                            ?>
                            <tr>
                                <td><?php echo $stt++; ?></td>
                                <td><?php echo $resident['UserName']; ?></td>
                                <td><?php echo $resident['PhoneNumber']; ?></td>
                                <td><?php echo $resident['Email']; ?></td>
                                <td>
                                    <span class="status-badge <?php echo $resident['Status'] == 'Đã kích hoạt' ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $resident['Status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="#" class="action-icon" title="Đăng nhập"><i class="fas fa-sign-in-alt"></i></a>
                                    <a href="update_resident.php?id=<?php echo $resident['ID']; ?>" class="action-icon" title="Cập nhật">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="resident_management.php?id=<?php echo $resident['ID']; ?>" 
                                       class="action-icon" 
                                       title="Xóa" 
                                       onclick="return confirm('Bạn có chắc muốn xóa cư dân này?');">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php
                                }
                            } else {
                                echo '<tr><td colspan="8" class="text-center">Không có dữ liệu</td></tr>';
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
                                    <a class="page-link" href="?page=1<?php 
                                        echo !empty($search) ? "&search=$search" : '';
                                        echo !empty($building_filter) ? "&building=$building_filter" : '';
                                        echo !empty($mobile_filter) ? "&mobile=$mobile_filter" : '';
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
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php 
                                        echo !empty($search) ? "&search=$search" : '';
                                        echo !empty($building_filter) ? "&building=$building_filter" : '';
                                        echo !empty($mobile_filter) ? "&mobile=$mobile_filter" : '';
                                        echo "&per_page=$records_per_page";
                                    ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $total_pages; ?><?php 
                                        echo !empty($search) ? "&search=$search" : '';
                                        echo !empty($building_filter) ? "&building=$building_filter" : '';
                                        echo !empty($mobile_filter) ? "&mobile=$mobile_filter" : '';
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
                                <option value="?page=1<?php 
                                    echo !empty($search) ? "&search=$search" : '';
                                    echo !empty($building_filter) ? "&building=$building_filter" : '';
                                    echo !empty($mobile_filter) ? "&mobile=$mobile_filter" : '';
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
</body>
</html>