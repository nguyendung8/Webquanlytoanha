<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Kiểm tra ID truyền vào
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_msg'] = 'Không tìm thấy thẻ xe!';
    header('location: vehicle_card_list.php');
    exit();
}

$card_id = mysqli_real_escape_string($conn, $_GET['id']);

// Lấy thông tin thẻ xe
$get_card = mysqli_query($conn, "SELECT * FROM vehiclecards WHERE VehicleCardCode = '$card_id'");
if (mysqli_num_rows($get_card) == 0) {
    $_SESSION['error_msg'] = 'Không tìm thấy thẻ xe!';
    header('location: vehicle_card_list.php');
    exit();
}
$card = mysqli_fetch_assoc($get_card);

// Lấy danh sách biển số xe từ bảng vehicles
$select_number_plates = mysqli_query($conn, "SELECT DISTINCT NumberPlate FROM vehicles WHERE NumberPlate IS NOT NULL AND NumberPlate != '' ORDER BY NumberPlate ASC");

// Xử lý cập nhật thẻ xe
if (isset($_POST['submit'])) {
    $vehicle_type = mysqli_real_escape_string($conn, $_POST['vehicle_type']);
    $number_plate = isset($_POST['number_plate']) ? mysqli_real_escape_string($conn, $_POST['number_plate']) : null;
    $note = mysqli_real_escape_string($conn, $_POST['note']);

    try {
        // Cập nhật thông tin thẻ xe
        $update_query = mysqli_query($conn, "
            UPDATE vehiclecards 
            SET VehicleType = '$vehicle_type', 
                NumberPlate = " . ($number_plate ? "'$number_plate'" : "NULL") . ", 
                Note = '$note'
            WHERE VehicleCardCode = '$card_id'
        ");
        
        if ($update_query) {
            $_SESSION['success_msg'] = 'Cập nhật thẻ xe thành công!';
            header('location: vehicle_card_list.php');
            exit();
        } else {
            $error = 'Cập nhật thất bại. Vui lòng thử lại.';
        }
    } catch (Exception $e) {
        $error = 'Đã xảy ra lỗi: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cập nhật thẻ xe</title>

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
    </style>
</head>

<body>
    <div class="d-flex">
        <?php include '../admin_navbar.php'; ?>
        <div style="width: 100%;">
            <?php include '../admin_header.php'; ?>
            <div class="manage-container">
                <div class="page-header mb-4">
                    <h1 class="main-title">CẬP NHẬT THẺ XE</h1>
                    <div class="breadcrumb">
                        <a href="/webquanlytoanha/admin/dashboard.php">Trang chủ</a>
                        <span style="margin: 0 8px;">›</span>
                        <a href="/webquanlytoanha/admin/service/vehicle_list.php">Phương tiện</a>
                        <span style="margin: 0 8px;">›</span>
                        <a href="/webquanlytoanha/admin/service/vehicle_card_list.php">Thẻ xe</a>
                        <span style="margin: 0 8px;">›</span>
                        <span>Cập nhật thẻ xe</span>
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
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($card['VehicleCardCode']); ?>" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Loại phương tiện</label>
                                <select name="vehicle_type" class="form-select">
                                    <option value="">--Chọn loại phương tiện--</option>
                                    <option value="Ô tô" <?php echo $card['VehicleType'] == 'Ô tô' ? 'selected' : ''; ?>>Ô tô</option>
                                    <option value="Xe máy" <?php echo $card['VehicleType'] == 'Xe máy' ? 'selected' : ''; ?>>Xe máy</option>
                                    <option value="Xe đạp" <?php echo $card['VehicleType'] == 'Xe đạp' ? 'selected' : ''; ?>>Xe đạp</option>
                                    <option value="Khác" <?php echo $card['VehicleType'] == 'Khác' ? 'selected' : ''; ?>>Khác</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Chọn biển số xe</label>
                                <select name="number_plate" class="form-select">
                                    <option value="">--Nhập mô tả--</option>
                                    <?php
                                    mysqli_data_seek($select_number_plates, 0);
                                    while ($plate = mysqli_fetch_assoc($select_number_plates)) {
                                        $selected = ($card['NumberPlate'] == $plate['NumberPlate']) ? 'selected' : '';
                                        echo '<option value="' . htmlspecialchars($plate['NumberPlate']) . '" ' . $selected . '>' . 
                                            htmlspecialchars($plate['NumberPlate']) . 
                                            '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Ghi chú</label>
                                <input type="text" name="note" class="form-control" placeholder="Ghi chú" value="<?php echo htmlspecialchars($card['Note']); ?>">
                            </div>
                        </div>
                        
                        <div class="mt-4 text-end">
                            <a href="vehicle_card_list.php" class="btn-cancel me-2">Hủy</a>
                            <button type="submit" name="submit" class="btn-submit">Cập nhật</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>