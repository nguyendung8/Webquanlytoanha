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

// Lấy danh sách thẻ xe chưa được gán cho phương tiện nào
$select_vehicle_cards = mysqli_query($conn, "
    SELECT vc.* 
    FROM vehiclecards vc
    LEFT JOIN vehicles v ON vc.VehicleCardCode = v.VehicleCardCode
    WHERE v.VehicleCardCode IS NULL AND vc.Status != 'Hủy'
    ORDER BY vc.VehicleCardCode ASC
");

// Xử lý thêm mới phương tiện
if (isset($_POST['submit'])) {
    $code = mysqli_real_escape_string($conn, $_POST['code']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $type_vehicle = mysqli_real_escape_string($conn, $_POST['type_vehicle']);
    $number_plate = mysqli_real_escape_string($conn, $_POST['number_plate']);
    $color = mysqli_real_escape_string($conn, $_POST['color']);
    $brand = mysqli_real_escape_string($conn, $_POST['brand']);
    $vehicle_id_number = mysqli_real_escape_string($conn, $_POST['vehicle_id_number']);
    $engine_number = mysqli_real_escape_string($conn, $_POST['engine_number']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $vehicle_card_code = mysqli_real_escape_string($conn, $_POST['vehicle_card_code']);
    $apartment_id = 1;
    $owner_id = 1;
    $service_id = mysqli_real_escape_string($conn, $_POST['service_id']);
    $apply_fee_date = mysqli_real_escape_string($conn, $_POST['apply_fee_date']);
    $end_fee_date = isset($_POST['end_fee_date']) && !empty($_POST['end_fee_date']) ? 
                     mysqli_real_escape_string($conn, $_POST['end_fee_date']) : NULL;
    $price_id = isset($_POST['price_id']) ? mysqli_real_escape_string($conn, $_POST['price_id']) : NULL;
    $status = 'active';

    // Kiểm tra xem code đã tồn tại chưa
    $check_code = mysqli_query($conn, "SELECT * FROM vehicles WHERE VehicleCode = '$code'");
    if (mysqli_num_rows($check_code) > 0) {
        $error = 'Mã phương tiện đã tồn tại!';
    } else {
        try {
            // Thêm mới vào bảng vehicles
            mysqli_query($conn, "
                INSERT INTO vehicles (VehicleCode, TypeVehicle, VehicleName, NumberPlate, Color, Brand, 
                VehicleIdentificationNumber, EngineNumber, Description, Status, VehicleCardCode, VehicleOwnerID, ApartmentID) 
                VALUES ('$code', '$type_vehicle', '$name', '$number_plate', '$color', '$brand', 
                '$vehicle_id_number', '$engine_number', '$description', '$status', '$vehicle_card_code', '$owner_id', '$apartment_id')
            ");
            
            // Cập nhật trạng thái và thông tin biển số cho thẻ xe
            mysqli_query($conn, "
                UPDATE vehiclecards 
                SET Status = 'Đã cấp phát', NumberPlate = '$number_plate' 
                WHERE VehicleCardCode = '$vehicle_card_code'
            ");
            
            // Thêm vào bảng trung gian ServiceVehicles
            $end_date_sql = $end_fee_date ? "'$end_fee_date'" : "NULL";
            mysqli_query($conn, "
                INSERT INTO ServiceVehicles (ServiceId, VehicleCode, ApplyFeeDate, EndFeeDate) 
                VALUES ('$service_id', '$code', '$apply_fee_date', $end_date_sql)
            ");
            
            $_SESSION['success_msg'] = 'Đã thêm phương tiện thành công!';
            header('location: vehicle_list.php');
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
    <title>Thêm mới phương tiện</title>

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
                    <h1 class="main-title">THÊM MỚI PHƯƠNG TIỆN</h1>
                    <div class="breadcrumb">
                        <a href="/webquanlytoanha/admin/dashboard.php">Trang chủ</a>
                        <span style="margin: 0 8px;">›</span>
                        <a href="/webquanlytoanha/admin/service/vehicle_list.php">Phương tiện</a>
                        <span style="margin: 0 8px;">›</span>
                        <span>Thêm mới phương tiện</span>
                    </div>
                </div>

                <?php if(isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <form action="" method="post" id="vehicleForm">
                    <div class="form-container">
                        <h2 class="form-title">THÔNG TIN PHƯƠNG TIỆN</h2>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Mã phương tiện<span class="required-mark">*</span></label>
                                <input type="text" name="code" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Tên phương tiện<span class="required-mark">*</span></label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Loại phương tiện<span class="required-mark">*</span></label>
                                <select name="type_vehicle" class="form-select" required>
                                    <option value="">--Chọn loại phương tiện--</option>
                                    <option value="Ô tô">Ô tô</option>
                                    <option value="Xe máy">Xe máy</option>
                                    <option value="Xe đạp">Xe đạp</option>
                                    <option value="Xe máy điện">Xe máy điện</option>
                                    <option value="Ô tô điện">Ô tô điện</option>
                                    <option value="Khác">Khác</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Biển số<span class="required-mark">*</span></label>
                                <input type="text" name="number_plate" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Màu sắc</label>
                                <input type="text" name="color" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Số khung</label>
                                <input type="text" name="vehicle_id_number" class="form-control">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Số máy</label>
                                <input type="text" name="engine_number" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Mã thẻ xe<span class="required-mark">*</span></label>
                                <select name="vehicle_card_code" class="form-select" required>
                                    <option value="">--Chọn mã thẻ xe--</option>
                                    <?php 
                                    while ($card = mysqli_fetch_assoc($select_vehicle_cards)) {
                                        echo '<option value="' . $card['VehicleCardCode'] . '">' . 
                                            htmlspecialchars($card['VehicleCardCode']) . ' - ' . 
                                            htmlspecialchars($card['VehicleType'] ?: 'Chưa xác định') . 
                                            '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Hãng xe</label>
                                <input type="text" name="brand" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Mô tả</label>
                                <textarea name="description" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                        
                        <h3 class="section-title">CHỦ SỞ HỮU</h3>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Tòa nhà<span class="required-mark">*</span></label>
                                <select id="building" class="form-select" required>
                                    <option value="">--Lựa chọn tòa nhà--</option>
                                    <?php 
                                    $buildings_query = mysqli_query($conn, "SELECT * FROM buildings WHERE Status = 'active'");
                                    while ($building = mysqli_fetch_assoc($buildings_query)) {
                                        echo '<option value="' . $building['ID'] . '">' . 
                                            htmlspecialchars($building['Name']) . 
                                            '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Căn hộ<span class="required-mark">*</span></label>
                                <select name="apartment_id" id="apartment_id" class="form-select" >
                                    <option value="">--Chọn căn hộ--</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Chủ phương tiện<span class="required-mark">*</span></label>
                                <select name="owner_id" id="owner_id" class="form-select">
                                    <option value="">--Chọn chủ phương tiện--</option>
                                </select>
                            </div>
                        </div>
                        
                        <h3 class="section-title">DỊCH VỤ ÁP DỤNG</h3>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Dịch vụ áp dụng<span class="required-mark">*</span></label>
                                <select name="service_id" id="service_id" class="form-select" required>
                                    <option value="">--Phí quản lý phương tiện--</option>
                                    <?php 
                                    while ($service = mysqli_fetch_assoc($select_services)) {
                                        echo '<option value="' . $service['ServiceCode'] . '">' . 
                                            htmlspecialchars($service['Name']) . 
                                            '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Ngày áp dụng tính phí<span class="required-mark">*</span></label>
                                <input type="date" name="apply_fee_date" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Ngày kết thúc tính phí</label>
                                <input type="date" name="end_fee_date" class="form-control">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Mức ưu tiên tính phí</label>
                                <select name="price_id" id="price_id" class="form-select">
                                    <option value="">--Mức ưu tiên tính phí--</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mt-4 text-end">
                            <a href="vehicle_list.php" class="btn-cancel me-2">Hủy</a>
                            <button type="submit" name="submit" class="btn-submit">Thêm mới</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        // Xử lý khi thay đổi tòa nhà
        $('#building').change(function() {
            const buildingId = $(this).val();
            if (buildingId) {
                // Lấy danh sách căn hộ theo tòa nhà
                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: {
                        get_apartments: 1,
                        building_id: buildingId
                    },
                    success: function(response) {
                        $('#apartment_id').html(response);
                    }
                });
            } else {
                $('#apartment_id').html('<option value="">--Chọn căn hộ--</option>');
                $('#owner_id').html('<option value="">--Chọn chủ phương tiện--</option>');
            }
        });
        
        // Xử lý khi thay đổi căn hộ
        $('#apartment_id').change(function() {
            const apartmentId = $(this).val();
            if (apartmentId) {
                // Lấy danh sách chủ căn hộ
                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: {
                        get_owners: 1,
                        apartment_id: apartmentId
                    },
                    success: function(response) {
                        $('#owner_id').html(response);
                    }
                });
            } else {
                $('#owner_id').html('<option value="">--Chọn chủ phương tiện--</option>');
            }
        });
        
        // Xử lý khi thay đổi dịch vụ
        $('#service_id').change(function() {
            const serviceId = $(this).val();
            if (serviceId) {
                // Lấy danh sách bảng giá theo dịch vụ
                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: {
                        get_price_list: 1,
                        service_id: serviceId
                    },
                    success: function(response) {
                        $('#price_id').html(response);
                    }
                });
            } else {
                $('#price_id').html('<option value="">--Mức ưu tiên tính phí--</option>');
            }
        });
        
        // Xử lý submit form
        $('#vehicleForm').on('submit', function(e) {
            // Kiểm tra đã chọn chủ phương tiện chưa
            const ownerId = $('#owner_id').val();
            if (!ownerId) {
                e.preventDefault();
                alert('Vui lòng chọn chủ phương tiện');
                return;
            }
            
            // Kiểm tra ngày kết thúc phải sau ngày bắt đầu
            const applyDate = $('input[name="apply_fee_date"]').val();
            const endDate = $('input[name="end_fee_date"]').val();
            
            if (endDate && new Date(endDate) <= new Date(applyDate)) {
                e.preventDefault();
                alert('Ngày kết thúc phải sau ngày áp dụng');
                return;
            }
        });
    });
    </script>
    
    <?php
    // Xử lý AJAX lấy danh sách căn hộ theo tòa nhà
    if (isset($_POST['get_apartments']) && isset($_POST['building_id'])) {
        $building_id = mysqli_real_escape_string($conn, $_POST['building_id']);
        $apartment_query = mysqli_query($conn, "SELECT * FROM apartment WHERE BuildingID = '$building_id'");
        
        echo '<option value="">--Chọn căn hộ--</option>';
        while ($apartment = mysqli_fetch_assoc($apartment_query)) {
            echo '<option value="'.$apartment['ID'].'">'.$apartment['ApartmentCode'].'</option>';
        }
        exit;
    }
    
    // Xử lý AJAX lấy danh sách chủ phương tiện theo căn hộ
    if (isset($_POST['get_owners']) && isset($_POST['apartment_id'])) {
        $apartment_id = mysqli_real_escape_string($conn, $_POST['apartment_id']);
        $owner_query = mysqli_query($conn, "
            SELECT u.* 
            FROM users u
            JOIN apartmentusers au ON u.ID = au.UserID
            WHERE au.ApartmentID = '$apartment_id' AND u.Status = 'active'
        ");
        
        echo '<option value="">--Chọn chủ phương tiện--</option>';
        while ($owner = mysqli_fetch_assoc($owner_query)) {
            echo '<option value="'.$owner['ID'].'">'.$owner['Name'].'</option>';
        }
        exit;
    }
    
    // Xử lý AJAX lấy danh sách bảng giá theo dịch vụ
    if (isset($_POST['get_price_list']) && isset($_POST['service_id'])) {
        $service_id = mysqli_real_escape_string($conn, $_POST['service_id']);
        $price_query = mysqli_query($conn, "
            SELECT pl.ID, pl.Name, pl.TypeOfFee, pl.Price 
            FROM pricelist pl
            JOIN ServicePrice sp ON pl.ID = sp.PriceId
            WHERE sp.ServiceId = '$service_id' AND pl.Status = 'active'
            ORDER BY pl.ApplyDate DESC
        ");
        
        echo '<option value="">--Mức ưu tiên tính phí--</option>';
        while ($price = mysqli_fetch_assoc($price_query)) {
            echo '<option value="'.$price['ID'].'">'.$price['Name'].' - '.number_format($price['Price']).' đ ('.$price['TypeOfFee'].')</option>';
        }
        exit;
    }
    ?>
</body>
</html>