<?php
include '../../database/DBController.php';

// Thêm code để xóa ràng buộc khóa ngoại
$drop_fk_sql = "
    ALTER TABLE ElectricityMeterReading
    DROP FOREIGN KEY IF EXISTS electricitymeterreading_ibfk_1,
    DROP FOREIGN KEY IF EXISTS electricitymeterreading_ibfk_2;
    
    ALTER TABLE ElectricityMeterReading
    ADD INDEX (ApartmentID),
    ADD INDEX (StaffID);
";

try {
    $conn->multi_query($drop_fk_sql);
    while ($conn->next_result()) {;} // clean up
} catch (Exception $e) {
    // Bỏ qua lỗi nếu ràng buộc không tồn tại
}

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Xử lý AJAX request để lấy chỉ số cuối của tháng trước
if (isset($_GET['action']) && $_GET['action'] === 'get_last_reading') {
    header('Content-Type: application/json');
    
    $apartment_id = isset($_GET['apartment_id']) ? intval($_GET['apartment_id']) : 0;
    $month = isset($_GET['month']) ? $_GET['month'] : '';

    if (!$apartment_id || !$month) {
        echo json_encode([
            'success' => true,
            'final_reading' => 0,
            'message' => 'Chưa có chỉ số'
        ]);
        exit;
    }

    // Lấy chỉ số cuối cùng của tháng trước
    $query = "SELECT FinalReading 
              FROM ElectricityMeterReading 
              WHERE ApartmentID = ? 
              AND DATE_FORMAT(ClosingDate, '%Y-%m') = ?
              ORDER BY ClosingDate DESC 
              LIMIT 1";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('is', $apartment_id, $month);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'final_reading' => floatval($row['FinalReading']),
            'message' => 'Đã tìm thấy chỉ số'
        ]);
    } else {
        // Nếu không có bản ghi của tháng trước, tìm bản ghi cuối cùng
        $query = "SELECT FinalReading 
                 FROM ElectricityMeterReading 
                 WHERE ApartmentID = ? 
                 ORDER BY ClosingDate DESC 
                 LIMIT 1";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $apartment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            echo json_encode([
                'success' => true,
                'final_reading' => floatval($row['FinalReading']),
                'message' => 'Lấy chỉ số cuối cùng'
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'final_reading' => 0,
                'message' => 'Chưa có chỉ số'
            ]);
        }
    }
    exit;
}

// Xử lý thêm chỉ số điện
if(isset($_POST['save_reading'])) {
    try {
        // Validate input
        if (!isset($_POST['apartment_id']) || empty($_POST['apartment_id'])) {
            throw new Exception('Vui lòng chọn căn hộ');
        }
        if (!isset($_POST['closing_month']) || empty($_POST['closing_month'])) {
            throw new Exception('Vui lòng chọn tháng chốt số');
        }
        if (!isset($_POST['final_reading']) || empty($_POST['final_reading'])) {
            throw new Exception('Chỉ số cuối là bắt buộc');
        }

        // Xử lý upload ảnh
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $target_dir = "../uploads/electricity/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $image_name = time() . '_' . basename($_FILES["image"]["name"]);
            $target_file = $target_dir . $image_name;
            
            if (!move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                throw new Exception('Lỗi khi upload ảnh');
            }
        } else {
            throw new Exception('Ảnh chỉ số điện là bắt buộc');
        }

        // Khởi tạo các biến để bind
        $initial_reading = floatval($_POST['initial_reading']);
        $final_reading = floatval($_POST['final_reading']);
        $consumption = $final_reading - $initial_reading;
        $closing_date = date('Y-m-d', strtotime($_POST['closing_month'] . '-01'));
        $apartment_id = intval($_POST['apartment_id']);
        $staff_id = intval($_SESSION['admin_id']);
        
        $sql = "INSERT INTO ElectricityMeterReading (
            InitialReading, 
            FinalReading, 
            Image, 
            ClosingDate, 
            Consumption, 
            ApartmentID, 
            StaffID
        ) VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "ddssiii",
            $initial_reading,
            $final_reading,
            $target_file,
            $closing_date,
            $consumption,
            $apartment_id,
            $staff_id
        );

        if (!$stmt->execute()) {
            throw new Exception('Lỗi khi lưu dữ liệu: ' . $stmt->error);
        }

        // Chuyển hướng sau khi thêm thành công
        header('Location: electric_reading.php?success=1');
        exit();

    } catch (Exception $e) {
        $error_msg = $e->getMessage();
    }
}

// Xử lý xóa chỉ số
if(isset($_POST['delete_reading'])) {
    $reading_id = intval($_POST['reading_id']);
    $table_name = "ElectricityMeterReading"; // Với water_reading.php thì đổi thành "WaterMeterReading"
    
    $delete_sql = "DELETE FROM $table_name WHERE ElectricityMeterID = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("i", $reading_id);
    
    if($stmt->execute()) {
        header('Location: ' . $_SERVER['PHP_SELF'] . '?delete_success=1');
        exit();
    } else {
        $error_msg = "Lỗi khi xóa dữ liệu: " . $stmt->error;
    }
}

// Xử lý cập nhật chỉ số
if(isset($_POST['update_reading'])) {
    try {
        $reading_id = intval($_POST['reading_id']);
        $final_reading = floatval($_POST['final_reading']);
        $initial_reading = floatval($_POST['initial_reading']);
        
        if ($final_reading < $initial_reading) {
            throw new Exception('Chỉ số cuối phải lớn hơn hoặc bằng chỉ số đầu');
        }

        $consumption = $final_reading - $initial_reading;
        $table_name = "ElectricityMeterReading"; // Với water_reading.php thì đổi thành "WaterMeterReading"
        
        $update_sql = "UPDATE $table_name SET FinalReading = ?, Consumption = ? WHERE ElectricityMeterID = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ddi", $final_reading, $consumption, $reading_id);
        
        if($stmt->execute()) {
            header('Location: ' . $_SERVER['PHP_SELF'] . '?update_success=1');
            exit();
        } else {
            throw new Exception('Lỗi khi cập nhật dữ liệu: ' . $stmt->error);
        }
    } catch (Exception $e) {
        $error_msg = $e->getMessage();
    }
}

// Thêm thông báo thành công
if (isset($_GET['delete_success'])) {
    $success_msg = "Xóa chỉ số thành công!";
}
if (isset($_GET['update_success'])) {
    $success_msg = "Cập nhật chỉ số thành công!";
}

// Hiển thị thông báo thành công nếu có
if (isset($_GET['success'])) {
    $success_msg = "Thêm chỉ số điện thành công!";
}

// Lấy danh sách tòa nhà cho filter
$select_buildings = mysqli_query($conn, "SELECT ID, Name FROM Buildings WHERE Status = 'active'");

// Xử lý các tham số tìm kiếm và phân trang
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$building_filter = isset($_GET['building']) ? mysqli_real_escape_string($conn, $_GET['building']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';

// Thiết lập phân trang
$records_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Xây dựng câu query với điều kiện tìm kiếm
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

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Query đếm tổng số bản ghi
$count_query = mysqli_query($conn, "
    SELECT COUNT(*) as total 
    FROM apartment a 
    LEFT JOIN Buildings b ON a.BuildingId = b.ID 
    $where_clause
");
$total_records = mysqli_fetch_assoc($count_query)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Query lấy danh sách căn hộ
$select_apartments = mysqli_query($conn, "SELECT ApartmentID, Code, Name FROM apartment");

// Query lấy danh sách chỉ số điện
$query = "SELECT e.*, a.Code as ApartmentCode, a.Name as ApartmentName, s.Name as StaffName 
          FROM ElectricityMeterReading e
          LEFT JOIN apartment a ON e.ApartmentID = a.ApartmentID
          LEFT JOIN staffs s ON e.StaffID = s.ID
          ORDER BY e.ClosingDate DESC";

$select_readings = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chỉ số điện nước</title>

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

        .tab-container {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #6b8b7b;
        }

        .tab-item {
            padding: 12px 30px;
            text-decoration: none;
            color: #666;
            font-weight: 500;
            background: #f5f5f5;
            border: none;
            position: relative;
        }

        .tab-item.active {
            background: #6b8b7b;
            color: white;
        }

        .tab-item:first-child {
            border-top-left-radius: 4px;
        }

        .tab-item:last-child {
            border-top-right-radius: 4px;
        }

        /* Loại bỏ style mặc định của Bootstrap nếu có */
        .nav-tabs {
            border: none;
        }

        .nav-tabs .nav-link {
            border: none;
        }

        .nav-tabs .nav-link:hover {
            border: none;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <?php include '../admin_navbar.php'; ?>
        <div style="width: 100%;">
            <?php include '../admin_header.php'; ?>
            <div class="container-fluid p-4">

               <div class="page-header mb-4">
                    <h2 style="font-weight: bold; color: #476a52; margin-bottom: 10px; text-transform: uppercase;">QUẢN LÝ CHỈ SỐ ĐIỆN</h2>
                    <div class="breadcrumb">
                        <a href="dashboard.php">Trang chủ</a>
                        <span style="margin: 0 8px;">›</span>
                        <span>Chỉ số điện nước</span>
                    </div>
                </div>

                <!-- Tab Navigation -->
                <div class="tab-container">
                    <a href="#" class="tab-item active">Chỉ số điện</a>
                    <a href="./water_reading.php" class="tab-item">Chỉ số nước</a>
                </div>

                <!-- Search Form -->
                <div class="search-container mb-4">
                    <form class="row g-3">
                        <div class="col-md-3">
                            <select class="form-select" name="apartment">
                                <option value="">Chọn căn hộ</option>
                                <!-- Add apartment options -->
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="month">
                                <option value="">Chọn tháng chốt số</option>
                                <!-- Add month options -->
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="building">
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
                            <select class="form-select" name="floor">
                                <option value="">Chọn tầng</option>
                                <!-- Add floor options -->
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-search"></i> Tìm kiếm
                            </button>
                        </div>
                    </form>
                                    <!-- Add New Button -->
                    <div class="mt-3 d-flex justify-content-end">
                        <button type="button" class="btn add-btn" data-bs-toggle="modal" data-bs-target="#addReadingModal">
                            <i class="fas fa-plus"></i> Thêm chỉ số
                        </button>
                    </div>
                </div>

                <!-- Hiển thị thông báo -->
                <?php if(isset($success_msg)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_msg; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if(isset($error_msg)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_msg; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Utility Table -->
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>STT</th>
                                <th>MÃ CĂN HỘ</th>
                                <th>TÊN CĂN HỘ</th>
                                <th>DỊCH VỤ</th>
                                <th>CHỈ SỐ ĐẦU</th>
                                <th>CHỈ SỐ CUỐI</th>
                                <th>TIÊU THỤ</th>
                                <th>NGÀY CHỐT SỐ</th>
                                <th>THAO TÁC</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if(mysqli_num_rows($select_readings) > 0){
                                $i = 1;
                                while($row = mysqli_fetch_assoc($select_readings)){
                            ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo $row['ApartmentCode']; ?></td>
                                <td><?php echo $row['ApartmentName']; ?></td>
                                <td>Điện</td>
                                <td><?php echo $row['InitialReading']; ?></td>
                                <td><?php echo $row['FinalReading']; ?></td>
                                <td><?php echo $row['Consumption']; ?></td>
                                <td><?php echo date('d-m-Y', strtotime($row['ClosingDate'])); ?></td>
                                <td>
                                    <button class="btn btn-link text-primary" onclick="viewReading(<?php 
                                        echo htmlspecialchars(json_encode([
                                            'id' => $row['ElectricityMeterID'],
                                            'apartmentCode' => $row['ApartmentCode'],
                                            'apartmentName' => $row['ApartmentName'],
                                            'initialReading' => $row['InitialReading'],
                                            'finalReading' => $row['FinalReading'],
                                            'consumption' => $row['Consumption'],
                                            'closingDate' => date('d-m-Y', strtotime($row['ClosingDate'])),
                                            'image' => $row['Image']
                                        ])); 
                                    ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-link text-danger" onclick="deleteReading(<?php echo $row['ElectricityMeterID']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php
                                }
                            } else {
                                echo '<tr><td colspan="10" class="text-center">Không có dữ liệu</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- Thêm Modal -->
                <div class="modal fade" id="addReadingModal" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Thêm chỉ số điện</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <form id="addReadingForm" method="POST" enctype="multipart/form-data">
                                    <div class="mb-3">
                                        <label class="form-label">Hình ảnh chỉ số điện <span class="text-danger">*</span></label>
                                        <div class="text-center mb-3">
                                            <img id="previewImage" src="/webquanlytoanha/assets/meter.png" 
                                                 style="max-width: 200px; margin-bottom: 10px;" alt="Ảnh chỉ số điện">
                                            <div>
                                                <button type="button" class="btn btn-success" onclick="document.getElementById('imageInput').click()">
                                                    <i class="fas fa-upload"></i> Tải ảnh
                                                </button>
                                                <input type="file" id="imageInput" name="image" accept="image/*" style="display: none;" 
                                                       onchange="previewFile()" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-8">
                                            <label class="form-label">Tên căn hộ <span class="text-danger">*</span></label>
                                            <select class="form-select" name="apartment_id" id="apartment_id" required>
                                                <option value="">Chọn căn hộ</option>
                                                <?php while($apt = mysqli_fetch_assoc($select_apartments)): ?>
                                                    <option value="<?php echo $apt['ApartmentID']; ?>">
                                                        <?php echo $apt['Code'] . ' - ' . $apt['Name']; ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Tháng chốt số <span class="text-danger">*</span></label>
                                            <input type="month" class="form-control" name="closing_month" 
                                                   value="<?php echo date('Y-m'); ?>" required>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Dịch vụ</label>
                                            <input type="text" class="form-control" value="Điện" readonly>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Chỉ số đầu</label>
                                            <input type="number" class="form-control" id="initial_reading" name="initial_reading" readonly>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-12">
                                            <label class="form-label">Chỉ số cuối <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control" name="final_reading" id="final_reading" required>
                                        </div>
                                    </div>

                                    <input type="hidden" name="save_reading" value="1">
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Quay lại</button>
                                <button type="submit" form="addReadingForm" class="btn btn-success">
                                    <i class="fas fa-save"></i> Lưu
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Xem Chi Tiết -->
                <div class="modal fade" id="viewReadingModal" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Chi tiết chỉ số điện</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <form id="updateReadingForm" method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">Hình ảnh chỉ số</label>
                                        <div class="text-center mb-3">
                                            <img id="viewImage" src="" style="max-width: 200px; margin-bottom: 10px;">
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-8">
                                            <label class="form-label">Tên căn hộ</label>
                                            <input type="text" class="form-control" id="viewApartment" readonly>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Tháng chốt số</label>
                                            <input type="text" class="form-control" id="viewClosingDate" readonly>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Dịch vụ</label>
                                            <input type="text" class="form-control" value="Điện" readonly>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Chỉ số đầu</label>
                                            <input type="number" class="form-control" id="viewInitialReading" name="initial_reading" readonly>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-12">
                                            <label class="form-label">Chỉ số cuối <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control" id="viewFinalReading" name="final_reading" required>
                                        </div>
                                    </div>

                                    <input type="hidden" name="reading_id" id="viewReadingId">
                                    <input type="hidden" name="update_reading" value="1">
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                                <button type="submit" form="updateReadingForm" class="btn btn-success">
                                    <i class="fas fa-save"></i> Cập nhật
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form xóa ẩn -->
                <form id="deleteForm" method="POST" style="display: none;">
                    <input type="hidden" name="reading_id" id="deleteReadingId">
                    <input type="hidden" name="delete_reading" value="1">
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function previewFile() {
        const preview = document.getElementById('previewImage');
        const file = document.getElementById('imageInput').files[0];
        const reader = new FileReader();

        reader.onloadend = function() {
            preview.src = reader.result;
        }

        if (file) {
            reader.readAsDataURL(file);
        }
    }

    function getInitialReading() {
        const apartmentId = document.getElementById('apartment_id').value;
        const closingMonth = document.querySelector('input[name="closing_month"]').value;
        
        if (apartmentId && closingMonth) {
            // Lấy tháng trước của tháng được chọn
            const [year, month] = closingMonth.split('-');
            const date = new Date(year, month - 1, 1);
            date.setMonth(date.getMonth() - 1);
            const previousMonth = date.toISOString().slice(0, 7);

            // Reset giá trị về 0 trước khi gọi API
            document.getElementById('initial_reading').value = '0';

            // Gọi API trong cùng file với parameter action
            fetch(`electric_reading.php?action=get_last_reading&apartment_id=${apartmentId}&month=${previousMonth}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('initial_reading').value = data.final_reading;
                        // Có thể thêm thông báo nhỏ để hiển thị message
                        // console.log(data.message);
                    } else {
                        document.getElementById('initial_reading').value = '0';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('initial_reading').value = '0';
                });
        } else {
            document.getElementById('initial_reading').value = '0';
        }
    }

    // Thêm sự kiện khi mở modal để reset giá trị
    document.querySelector('[data-bs-toggle="modal"]').addEventListener('click', function() {
        document.getElementById('initial_reading').value = '0';
        document.getElementById('final_reading').value = '';
        document.getElementById('apartment_id').value = '';
        document.querySelector('input[name="closing_month"]').value = new Date().toISOString().slice(0, 7);
    });

    // Thêm sự kiện khi chọn căn hộ hoặc thay đổi tháng
    document.getElementById('apartment_id').addEventListener('change', getInitialReading);
    document.querySelector('input[name="closing_month"]').addEventListener('change', getInitialReading);

    // Validate form trước khi submit
    document.getElementById('addReadingForm').addEventListener('submit', function(e) {
        const initialReading = parseFloat(document.getElementById('initial_reading').value) || 0;
        const finalReading = parseFloat(document.getElementById('final_reading').value) || 0;
        
        if (finalReading < initialReading) {
            e.preventDefault();
            alert('Chỉ số cuối phải lớn hơn hoặc bằng chỉ số đầu');
        }
    });

    // Hàm xem chi tiết
    function viewReading(data) {
        document.getElementById('viewImage').src = data.image;
        document.getElementById('viewApartment').value = data.apartmentCode + ' - ' + data.apartmentName;
        document.getElementById('viewClosingDate').value = data.closingDate;
        document.getElementById('viewInitialReading').value = data.initialReading;
        document.getElementById('viewFinalReading').value = data.finalReading;
        document.getElementById('viewReadingId').value = data.id;
        
        new bootstrap.Modal(document.getElementById('viewReadingModal')).show();
    }

    // Hàm xóa
    function deleteReading(readingId) {
        if(confirm('Bạn có chắc chắn muốn xóa chỉ số này?')) {
            document.getElementById('deleteReadingId').value = readingId;
            document.getElementById('deleteForm').submit();
        }
    }

    // Validate form cập nhật
    document.getElementById('updateReadingForm').addEventListener('submit', function(e) {
        const initialReading = parseFloat(document.getElementById('viewInitialReading').value) || 0;
        const finalReading = parseFloat(document.getElementById('viewFinalReading').value) || 0;
        
        if (finalReading < initialReading) {
            e.preventDefault();
            alert('Chỉ số cuối phải lớn hơn hoặc bằng chỉ số đầu');
        }
    });
    </script>
</body>
</html>