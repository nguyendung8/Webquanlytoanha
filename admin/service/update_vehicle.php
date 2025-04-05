<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Kiểm tra và lấy thông tin phương tiện cần sửa
if (!isset($_GET['id'])) {
    header('location: vehicle_list.php');
    exit();
}

$vehicle_code = mysqli_real_escape_string($conn, $_GET['id']);

// Lấy thông tin chi tiết của phương tiện
$vehicle_query = mysqli_query($conn, "
    SELECT 
        v.*,
        sv.ServiceId,
        sv.ApplyFeeDate,
        sv.EndFeeDate,
        sp.PriceId,
        a.BuildingId,
        a.ApartmentID,
        r.ID as ResidentId,
        u.UserName,
        ra.Relationship
    FROM vehicles v
    LEFT JOIN apartment a ON v.ApartmentID = a.ApartmentID
    LEFT JOIN ResidentApartment ra ON a.ApartmentID = ra.ApartmentId
    LEFT JOIN resident r ON ra.ResidentId = r.ID
    LEFT JOIN users u ON r.ID = u.ResidentID
    LEFT JOIN ServiceVehicles sv ON v.VehicleCode = sv.VehicleCode
    LEFT JOIN ServicePrice sp ON sv.ServiceId = sp.ServiceId
    WHERE v.VehicleCode = '$vehicle_code'
");

if (!$vehicle_query || mysqli_num_rows($vehicle_query) == 0) {
    header('location: vehicle_list.php');
    exit();
}

$vehicle_data = mysqli_fetch_assoc($vehicle_query);

// Lấy danh sách thẻ xe chưa được gán cho phương tiện nào
$select_vehicle_cards = mysqli_query($conn, "
    SELECT vc.* 
    FROM vehiclecards vc
    LEFT JOIN vehicles v ON vc.VehicleCardCode = v.VehicleCardCode 
        AND v.VehicleCode != '$vehicle_code'
    WHERE vc.Status != 'Hủy'
        OR vc.VehicleCardCode = (
            SELECT VehicleCardCode 
            FROM vehicles 
            WHERE VehicleCode = '$vehicle_code'
        )
");

// Lấy danh sách căn hộ để hiển thị trong dropdown
$select_apartments = mysqli_query($conn, "SELECT * FROM apartment");

// Lấy danh sách dịch vụ quản lý phương tiện để hiển thị trong dropdown
$select_services = mysqli_query($conn, "SELECT * FROM services WHERE Status = 'active'");

// Lấy danh sách chủ xe để hiển thị trong dropdown
$select_owners = mysqli_query($conn, "SELECT * FROM users WHERE Position = 'Quản trị hệ thống'");

// Đặt đoạn code này ở đầu file, trước khi output bất kỳ HTML nào
if (isset($_POST['get_apartments']) || isset($_POST['get_owners']) || isset($_POST['get_price_list'])) {
    // Xử lý AJAX lấy danh sách căn hộ theo tòa nhà
    if (isset($_POST['get_apartments']) && isset($_POST['building_id'])) {
        $building_id = mysqli_real_escape_string($conn, $_POST['building_id']);
        $apartment_query = mysqli_query($conn, "
            SELECT ApartmentID, Name, Code 
            FROM apartment 
            WHERE BuildingId = '$building_id'
        ");
        
        $html = '<option value="">--Chọn căn hộ--</option>';
        while ($apartment = mysqli_fetch_assoc($apartment_query)) {
            $html .= '<option value="'.$apartment['ApartmentID'].'">'.$apartment['Name'].' - '.$apartment['Code'].'</option>';
        }
        echo $html;
        exit;
    }
    
    // Xử lý AJAX lấy danh sách chủ phương tiện theo căn hộ
    if (isset($_POST['get_owners']) && isset($_POST['apartment_id'])) {
        $apartment_id = mysqli_real_escape_string($conn, $_POST['apartment_id']);
        $owner_query = mysqli_query($conn, "
            SELECT DISTINCT 
                r.ID, 
                r.NationalId, 
                u.UserName, 
                ra.Relationship,
                CASE WHEN r.ID = '{$vehicle_data['VehicleOwnerID']}' THEN 1 ELSE 0 END as is_current_owner
            FROM resident r
            INNER JOIN ResidentApartment ra ON r.ID = ra.ResidentId
            INNER JOIN users u ON r.ID = u.ResidentID
            WHERE ra.ApartmentId = '$apartment_id'
            ORDER BY is_current_owner DESC, u.UserName ASC
        ");
        
        $html = '<option value="">--Chọn chủ phương tiện--</option>';
        while ($owner = mysqli_fetch_assoc($owner_query)) {
            $selected = ($owner['ID'] == $vehicle_data['VehicleOwnerID']) ? 'selected' : '';
            $display_text = sprintf(
                "%s - %s (%s)", 
                htmlspecialchars($owner['UserName']),
                htmlspecialchars($owner['NationalId']),
                htmlspecialchars($owner['Relationship'])
            );
            $html .= '<option value="'.$owner['ID'].'" '.$selected.'>'.$display_text.'</option>';
        }
        echo $html;
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
        
        $html = '<option value="">--Mức ưu tiên tính phí--</option>';
        while ($price = mysqli_fetch_assoc($price_query)) {
            $html .= '<option value="'.$price['ID'].'">'.$price['Name'].' - '.number_format($price['Price']).' đ ('.$price['TypeOfFee'].')</option>';
        }
        echo $html;
        exit;
    }
    
    exit;
}

// Xử lý cập nhật thông tin
if (isset($_POST['submit'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $type_vehicle = mysqli_real_escape_string($conn, $_POST['type_vehicle']);
    $number_plate = mysqli_real_escape_string($conn, $_POST['number_plate']);
    $color = mysqli_real_escape_string($conn, $_POST['color']);
    $brand = mysqli_real_escape_string($conn, $_POST['brand']);
    $vehicle_id_number = mysqli_real_escape_string($conn, $_POST['vehicle_id_number']);
    $engine_number = mysqli_real_escape_string($conn, $_POST['engine_number']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $vehicle_card_code = mysqli_real_escape_string($conn, $_POST['vehicle_card_code']);
    $apartment_id = mysqli_real_escape_string($conn, $_POST['apartment_id']);
    $owner_id = mysqli_real_escape_string($conn, $_POST['owner_id']);
    $service_id = mysqli_real_escape_string($conn, $_POST['service_id']);
    $apply_fee_date = mysqli_real_escape_string($conn, $_POST['apply_fee_date']);
    $end_fee_date = isset($_POST['end_fee_date']) && !empty($_POST['end_fee_date']) ? 
                     mysqli_real_escape_string($conn, $_POST['end_fee_date']) : NULL;
    $price_id = isset($_POST['price_id']) ? mysqli_real_escape_string($conn, $_POST['price_id']) : NULL;

    try {
        // Bắt đầu transaction
        mysqli_begin_transaction($conn);

        // Cập nhật thông tin phương tiện
        mysqli_query($conn, "
            UPDATE vehicles 
            SET TypeVehicle = '$type_vehicle',
                VehicleName = '$name',
                NumberPlate = '$number_plate',
                Color = '$color',
                Brand = '$brand',
                VehicleIdentificationNumber = '$vehicle_id_number',
                EngineNumber = '$engine_number',
                Description = '$description',
                VehicleCardCode = '$vehicle_card_code',
                VehicleOwnerID = '$owner_id',
                ApartmentID = '$apartment_id'
            WHERE VehicleCode = '$vehicle_code'
        ");

        // Cập nhật thông tin thẻ xe
        mysqli_query($conn, "
            UPDATE vehiclecards 
            SET NumberPlate = '$number_plate'
            WHERE VehicleCardCode = '$vehicle_card_code'
        ");

        // Cập nhật hoặc thêm mới thông tin dịch vụ
        $end_date_sql = $end_fee_date ? "'$end_fee_date'" : "NULL";
        mysqli_query($conn, "
            INSERT INTO ServiceVehicles (ServiceId, VehicleCode, ApplyFeeDate, EndFeeDate)
            VALUES ('$service_id', '$vehicle_code', '$apply_fee_date', $end_date_sql)
            ON DUPLICATE KEY UPDATE
                ApplyFeeDate = '$apply_fee_date',
                EndFeeDate = $end_date_sql
        ");

        // Commit transaction
        mysqli_commit($conn);
        
        $_SESSION['success_msg'] = 'Cập nhật phương tiện thành công!';
        header('location: vehicle_list.php');
        exit();
    } catch (Exception $e) {
        // Rollback nếu có lỗi
        mysqli_rollback($conn);
        $error = 'Đã xảy ra lỗi: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cập nhật phương tiện</title>

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
                    <h1 class="main-title">CẬP NHẬT PHƯƠNG TIỆN</h1>
                    <div class="breadcrumb">
                        <a href="/webquanlytoanha/admin/dashboard.php">Trang chủ</a>
                        <span style="margin: 0 8px;">›</span>
                        <a href="/webquanlytoanha/admin/service/vehicle_list.php">Phương tiện</a>
                        <span style="margin: 0 8px;">›</span>
                        <span>Cập nhật phương tiện</span>
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
                                <input type="text" name="code" class="form-control" value="<?php echo htmlspecialchars($vehicle_data['VehicleCode']); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Tên phương tiện<span class="required-mark">*</span></label>
                                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($vehicle_data['VehicleName']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Loại phương tiện<span class="required-mark">*</span></label>
                                <select name="type_vehicle" class="form-select" required>
                                    <option value="">--Chọn loại phương tiện--</option>
                                    <?php
                                    $vehicle_types = ['Ô tô', 'Xe máy', 'Xe đạp', 'Xe máy điện', 'Ô tô điện', 'Khác'];
                                    foreach ($vehicle_types as $type) {
                                        $selected = ($type == $vehicle_data['TypeVehicle']) ? 'selected' : '';
                                        echo '<option value="'.$type.'" '.$selected.'>'.$type.'</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Biển số<span class="required-mark">*</span></label>
                                <input type="text" name="number_plate" class="form-control" value="<?php echo htmlspecialchars($vehicle_data['NumberPlate']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Màu sắc</label>
                                <input type="text" name="color" class="form-control" value="<?php echo htmlspecialchars($vehicle_data['Color']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Số khung</label>
                                <input type="text" name="vehicle_id_number" class="form-control" value="<?php echo htmlspecialchars($vehicle_data['VehicleIdentificationNumber']); ?>">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Số máy</label>
                                <input type="text" name="engine_number" class="form-control" value="<?php echo htmlspecialchars($vehicle_data['EngineNumber']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Mã thẻ xe<span class="required-mark">*</span></label>
                                <select name="vehicle_card_code" class="form-select" required>
                                    <option value="">--Chọn mã thẻ xe--</option>
                                    <?php 
                                    while ($card = mysqli_fetch_assoc($select_vehicle_cards)) {
                                        $selected = ($card['VehicleCardCode'] == $vehicle_data['VehicleCardCode']) ? 'selected' : '';
                                        echo '<option value="'.$card['VehicleCardCode'].'" '.$selected.'>'
                                            .htmlspecialchars($card['VehicleCardCode']).' - '
                                            .htmlspecialchars($card['VehicleType'] ?: 'Chưa xác định')
                                            .'</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Hãng xe</label>
                                <input type="text" name="brand" class="form-control" value="<?php echo htmlspecialchars($vehicle_data['Brand']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Mô tả</label>
                                <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($vehicle_data['Description']); ?></textarea>
                            </div>
                        </div>
                        
                        <h3 class="section-title">CHỦ SỞ HỮU</h3>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Tòa nhà<span class="required-mark">*</span></label>
                                <select id="building" class="form-select" required>
                                    <option value="">--Lựa chọn tòa nhà--</option>
                                    <?php 
                                    $buildings_query = mysqli_query($conn, "SELECT ID, Name FROM Buildings WHERE Status = 'active'");
                                    while ($building = mysqli_fetch_assoc($buildings_query)) {
                                        $selected = ($building['ID'] == $vehicle_data['BuildingId']) ? 'selected' : '';
                                        echo '<option value="'.$building['ID'].'" '.$selected.'>'
                                            .htmlspecialchars($building['Name']).'</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Căn hộ<span class="required-mark">*</span></label>
                                <select name="apartment_id" id="apartment_id" class="form-select" required>
                                    <option value="">--Chọn căn hộ--</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Chủ phương tiện<span class="required-mark">*</span></label>
                                <select name="owner_id" id="owner_id" class="form-select" required>
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
                                    $select_services = mysqli_query($conn, "SELECT * FROM services WHERE Status = 'active'");
                                    while ($service = mysqli_fetch_assoc($select_services)) {
                                        $selected = ($service['ServiceCode'] == $vehicle_data['ServiceId']) ? 'selected' : '';
                                        echo '<option value="'.$service['ServiceCode'].'" '.$selected.'>'
                                            .htmlspecialchars($service['Name']).'</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Ngày áp dụng tính phí<span class="required-mark">*</span></label>
                                <input type="date" name="apply_fee_date" class="form-control" required 
                                       value="<?php echo $vehicle_data['ApplyFeeDate']; ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Ngày kết thúc tính phí</label>
                                <input type="date" name="end_fee_date" class="form-control"
                                       value="<?php echo $vehicle_data['EndFeeDate']; ?>">
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
                            <button type="submit" name="submit" class="btn-submit">Cập nhật</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        // Lưu trữ các giá trị ban đầu
        const initialData = {
            buildingId: '<?php echo $vehicle_data['BuildingId']; ?>',
            apartmentId: '<?php echo $vehicle_data['ApartmentID']; ?>',
            ownerId: '<?php echo $vehicle_data['VehicleOwnerID']; ?>',
            serviceId: '<?php echo $vehicle_data['ServiceId']; ?>',
            priceId: '<?php echo $vehicle_data['PriceId']; ?>'
        };

        // Function để load căn hộ
        function loadApartments(buildingId, callback) {
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    get_apartments: 1,
                    building_id: buildingId
                },
                success: function(response) {
                    $('#apartment_id').html(response);
                    if (callback) callback();
                }
            });
        }

        // Function để load chủ phương tiện
        function loadOwners(apartmentId, callback) {
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    get_owners: 1,
                    apartment_id: apartmentId
                },
                success: function(response) {
                    $('#owner_id').html(response);
                    if (callback) callback();
                }
            });
        }

        // Xử lý khi thay đổi tòa nhà
        $('#building').change(function() {
            const buildingId = $(this).val();
            if (buildingId) {
                loadApartments(buildingId, function() {
                    if (buildingId == initialData.buildingId) {
                        $('#apartment_id').val(initialData.apartmentId);
                        $('#apartment_id').trigger('change');
                    }
                });
            }
        });

        // Xử lý khi thay đổi căn hộ
        $('#apartment_id').change(function() {
            const apartmentId = $(this).val();
            if (apartmentId) {
                loadOwners(apartmentId, function() {
                    if (apartmentId == initialData.apartmentId) {
                        $('#owner_id').val(initialData.ownerId);
                    }
                });
            }
        });

        // Trigger initial load khi trang được tải
        if (initialData.buildingId) {
            $('#building').trigger('change');
        }
    });
    </script>
</body>
</html>