<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Lấy danh sách căn hộ để hiển thị trong dropdown
$select_apartments = mysqli_query($conn, "SELECT * FROM apartment");

// Lấy danh sách dịch vụ quản lý phương tiện để hiển thị trong dropdown
$select_services = mysqli_query($conn, "SELECT * FROM services WHERE Status = 'active'");

// Lấy danh sách chủ xe để hiển thị trong dropdown
$select_owners = mysqli_query($conn, "SELECT * FROM users WHERE Position = 'Quản trị hệ thống'");

// Lấy danh sách biển số xe từ bảng vehicles
$select_number_plates = mysqli_query($conn, "SELECT DISTINCT NumberPlate FROM vehicles WHERE NumberPlate IS NOT NULL AND NumberPlate != '' ORDER BY NumberPlate ASC");

// Xử lý thêm mới thẻ xe
if (isset($_POST['submit'])) {
    $card_code = mysqli_real_escape_string($conn, $_POST['card_code']);
    $vehicle_type = mysqli_real_escape_string($conn, $_POST['vehicle_type']);
    $number_plate = isset($_POST['number_plate']) ? mysqli_real_escape_string($conn, $_POST['number_plate']) : null;
    $note = mysqli_real_escape_string($conn, $_POST['note']);
    $status = 'Chưa cấp phát'; // Mặc định khi tạo mới

    // Kiểm tra xem mã thẻ đã tồn tại chưa
    $check_code = mysqli_query($conn, "SELECT * FROM vehiclecards WHERE VehicleCardCode = '$card_code'");
    if (mysqli_num_rows($check_code) > 0) {
        $error = 'Mã thẻ xe đã tồn tại!';
    } else {
        try {
            // Thêm mới vào bảng vehiclecards
            mysqli_query($conn, "
                INSERT INTO vehiclecards (VehicleCardCode, VehicleType, NumberPlate, Note, Status) 
                VALUES ('$card_code', '$vehicle_type', " . ($number_plate ? "'$number_plate'" : "NULL") . ", '$note', '$status')
            ");
            
            $_SESSION['success_msg'] = 'Đã thêm thẻ xe thành công!';
            header('location: vehicle_card_list.php');
            exit();
        } catch (Exception $e) {
            $error = 'Đã xảy ra lỗi: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm mới thẻ xe</title>

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
        
        .form-container {
            background-color: white;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            padding: 25px;
            margin-bottom: 20px;
        }
        
        .form-title {
            color: #476a52;
            font-weight: 600;
            border-bottom: 1px solid #f2f2f2;
            padding-bottom: 12px;
            margin-bottom: 20px;
            font-size: 18px;
        }
        
        .form-label {
            font-weight: 500;
            color: #4a5568;
            font-size: 14px;
        }
        
        .form-control {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 8px 12px;
            font-size: 14px;
        }
        
        .form-control:focus {
            border-color: #a7c1b5;
            box-shadow: 0 0 0 0.2rem rgba(107, 139, 123, 0.25);
        }
        
        .form-select {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 8px 12px;
            font-size: 14px;
        }
        
        .form-select:focus {
            border-color: #a7c1b5;
            box-shadow: 0 0 0 0.2rem rgba(107, 139, 123, 0.25);
        }
        
        .btn-submit {
            background-color: #6b8b7b;
            color: white;
            border: none;
            padding: 8px 20px;
            font-size: 14px;
            border-radius: 4px;
        }
        
        .btn-submit:hover {
            background-color: #5a7a6a;
            color: white;
        }
        
        .btn-cancel {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 8px 20px;
            font-size: 14px;
            text-decoration: none;
            border-radius: 4px;
        }
        
        .btn-cancel:hover {
            background-color: #c82333;
            color: white;
        }
        
        .required-mark {
            color: #dc3545;
            margin-left: 3px;
        }
        
        .manage-container {
            width: 100%;
            padding: 20px;
            background-color: #f5f5f5;
        }
        
        .section-title {
            font-weight: 600;
            font-size: 16px;
            color: #476a52;
            margin-bottom: 15px;
            padding-top: 15px;
            border-top: 1px solid #eaeaea;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <?php include '../admin_navbar.php'; ?>
        <div style="width: 100%;">
            <?php include '../admin_header.php'; ?>
            <div class="manage-container">
                <div class="page-header mb-4">
                    <h1 class="main-title">THÊM MỚI THẺ XE</h1>
                    <div class="breadcrumb">
                        <a href="/webquanlytoanha/admin/dashboard.php">Trang chủ</a>
                        <span style="margin: 0 8px;">›</span>
                        <a href="/webquanlytoanha/admin/service/vehicle_list.php">Phương tiện</a>
                        <span style="margin: 0 8px;">›</span>
                        <a href="/webquanlytoanha/admin/service/vehicle_card_list.php">Thẻ xe</a>
                        <span style="margin: 0 8px;">›</span>
                        <span>Thêm mới thẻ xe</span>
                    </div>
                </div>

                <?php if(isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <form action="" method="post" id="cardForm">
                    <div class="form-container">
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Mã thẻ xe<span class="required-mark">*</span></label>
                                <input type="text" name="card_code" class="form-control" required placeholder="Mã thẻ xe">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Loại phương tiện</label>
                                <select name="vehicle_type" class="form-select">
                                    <option value="">--Chọn loại phương tiện--</option>
                                    <option value="Ô tô">Ô tô</option>
                                    <option value="Xe máy">Xe máy</option>
                                    <option value="Xe đạp">Xe đạp</option>
                                    <option value="Xe máy điện">Xe máy điện</option>
                                    <option value="Ô tô điện">Ô tô điện</option>
                                    <option value="Khác">Khác</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Chọn biển số xe</label>
                                <select name="number_plate" class="form-select">
                                    <option value="">--Chọn biển số xe--</option>
                                    <?php
                                    while ($plate = mysqli_fetch_assoc($select_number_plates)) {
                                        echo '<option value="' . htmlspecialchars($plate['NumberPlate']) . '">' . 
                                            htmlspecialchars($plate['NumberPlate']) . 
                                            '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Ghi chú</label>
                                <input type="text" name="note" class="form-control" placeholder="Ghi chú">
                            </div>
                        </div>
                        
                        <div class="mt-4 text-end">
                            <a href="vehicle_card_list.php" class="btn-cancel me-2">Hủy</a>
                            <button type="submit" name="submit" class="btn-submit">Thêm mới</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>