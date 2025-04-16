<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Kiểm tra có ID bảng giá được truyền vào không
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_msg'] = 'Không tìm thấy bảng giá!';
    header('location: price_list.php');
    exit();
}

$price_id = mysqli_real_escape_string($conn, $_GET['id']);

// Lấy chi tiết bảng giá
$get_price = mysqli_query($conn, "SELECT * FROM pricelist WHERE ID = '$price_id'");
if (mysqli_num_rows($get_price) == 0) {
    $_SESSION['error_msg'] = 'Không tìm thấy bảng giá!';
    header('location: price_list.php');
    exit();
}
$price = mysqli_fetch_assoc($get_price);

// Lấy dịch vụ liên kết với bảng giá này
$get_service_price = mysqli_query($conn, "SELECT ServiceId FROM ServicePrice WHERE PriceId = '$price_id'");
$service_id = '';
if (mysqli_num_rows($get_service_price) > 0) {
    $service_price = mysqli_fetch_assoc($get_service_price);
    $service_id = $service_price['ServiceId'];
}

// Lấy danh sách dịch vụ active để hiển thị trong dropdown
$select_services = mysqli_query($conn, "SELECT * FROM services WHERE Status = 'active'");

// Xử lý cập nhật bảng giá
if (isset($_POST['submit'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $new_service_id = mysqli_real_escape_string($conn, $_POST['service_id']);
    $apply_date = mysqli_real_escape_string($conn, $_POST['apply_date']);
    $type_of_fee = mysqli_real_escape_string($conn, $_POST['type_of_fee']);
    $status = $price['Status']; // Giữ nguyên trạng thái

    // Khởi tạo giá trị cho các trường tùy thuộc vào loại phí
    $price_calculation = '';
    $title = '';
    $price_from = 0;
    $price_to = 0;
    $price_value = 0;
    $variable_data = '';

    if ($type_of_fee == 'Cố định') {
        $price_calculation = isset($_POST['price_calculation']) ? mysqli_real_escape_string($conn, $_POST['price_calculation']) : '';
        $price_value = isset($_POST['fixed_price']) ? floatval($_POST['fixed_price']) : 0;
        $variable_data = ''; // Set variable_data to empty string for fixed price type
    } else {
        // Lấy dòng đầu tiên làm giá trị chính
        $variable_titles = isset($_POST['variable_title']) ? $_POST['variable_title'] : [];
        $variable_price_froms = isset($_POST['variable_price_from']) ? $_POST['variable_price_from'] : [];
        $variable_price_tos = isset($_POST['variable_price_to']) ? $_POST['variable_price_to'] : [];
        $variable_prices = isset($_POST['variable_price']) ? $_POST['variable_price'] : [];
        
        // Đảm bảo các mảng này không rỗng
        if (!empty($variable_titles)) {
            $title = $variable_titles[0];
            $price_from = !empty($variable_price_froms) ? floatval($variable_price_froms[0]) : 0;
            $price_to = !empty($variable_price_tos) ? floatval($variable_price_tos[0]) : 0;
            $price_value = !empty($variable_prices) ? floatval($variable_prices[0]) : 0;
            
            // Tạo mảng để lưu các dòng biến đổi
            $variable_rows = [];
            for ($i = 0; $i < count($variable_titles); $i++) {
                if (!empty($variable_titles[$i])) {
                    $variable_rows[] = [
                        'title' => $variable_titles[$i],
                        'price_from' => isset($variable_price_froms[$i]) ? floatval($variable_price_froms[$i]) : 0,
                        'price_to' => isset($variable_price_tos[$i]) ? floatval($variable_price_tos[$i]) : 0,
                        'price' => isset($variable_prices[$i]) ? floatval($variable_prices[$i]) : 0
                    ];
                }
            }
            
            // Chuyển đổi thành chuỗi JSON
            $variable_data = json_encode($variable_rows);
        } else {
            // Fallback nếu không có dữ liệu
            $title = '';
            $price_from = 0;
            $price_to = 0;
            $price_value = 0;
            $variable_data = '[]'; // JSON array trống
        }
    }

    try {
        // Cập nhật thông tin bảng giá
        $update_query = mysqli_query($conn, "
            UPDATE pricelist 
            SET Name = '$name', TypeOfFee = '$type_of_fee', Title = '$title', 
                PriceCalculation = '$price_calculation', PriceFrom = $price_from, 
                PriceTo = $price_to, Price = $price_value, VariableData = '$variable_data',
                ApplyDate = '$apply_date'
            WHERE ID = '$price_id'
        ");

        // Cập nhật dịch vụ liên kết trong bảng trung gian
        if ($service_id != $new_service_id) {
            // Xóa liên kết cũ
            mysqli_query($conn, "DELETE FROM ServicePrice WHERE PriceId = '$price_id'");
            
            // Thêm liên kết mới
            mysqli_query($conn, "INSERT INTO ServicePrice (ServiceId, PriceId) VALUES ('$new_service_id', '$price_id')");
        }
        
        $_SESSION['success_msg'] = 'Cập nhật bảng giá thành công!';
        header('location: price_list.php');
        exit();
    } catch (Exception $e) {
        $error = 'Đã xảy ra lỗi: ' . $e->getMessage();
    }
}

// Parse dữ liệu biến đổi từ JSON nếu có
$variable_rows = [];
if (!empty($price['VariableData'])) {
    $variable_rows = json_decode($price['VariableData'], true);
}

// Nếu không có dữ liệu biến đổi và không phải loại Cố định, tạo dòng mặc định từ dữ liệu cơ bản
if (empty($variable_rows) && $price['TypeOfFee'] != 'Cố định') {
    $variable_rows[] = [
        'title' => $price['Title'] ?? '',
        'price_from' => $price['PriceFrom'] ?? 0,
        'price_to' => $price['PriceTo'] ?? 0,
        'price' => $price['Price'] ?? 0
    ];
}

// Giả sử $price là bản ghi từ bảng pricelist
$variable_data = json_decode($price['VariableData'], true);

// Thêm dòng này trước dòng 146, ngay sau khi lấy $variable_data
$value_to_calculate = isset($_GET['value']) ? floatval($_GET['value']) : 0; // Giá trị mặc định là 0

// Tùy thuộc vào loại phí, xử lý tính toán
if (isset($value_to_calculate) && $price['TypeOfFee'] == 'Lũy tiến') {
    // Tính phí theo lũy tiến
    $total_fee = 0;
    $remaining_value = $value_to_calculate;
    
    if (!empty($variable_data)) {
        foreach ($variable_data as $tier) {
            if ($remaining_value <= 0) break;
            
            $tier_from = $tier['price_from'];
            $tier_to = $tier['price_to'];
            $tier_price = $tier['price'];
            
            $tier_value = min($remaining_value, $tier_to - $tier_from);
            $tier_fee = $tier_value * $tier_price;
            
            $total_fee += $tier_fee;
            $remaining_value -= $tier_value;
        }
    }
} elseif (isset($value_to_calculate) && ($price['TypeOfFee'] == 'Nhân khẩu' || $price['TypeOfFee'] == 'Định mức')) {
    // Tìm mức giá phù hợp dựa trên giá trị cần tính
    $matched_tier = null;
    
    if (!empty($variable_data)) {
        foreach ($variable_data as $tier) {
            if ($value_to_calculate >= $tier['price_from'] && $value_to_calculate <= $tier['price_to']) {
                $matched_tier = $tier;
                break;
            }
        }
    }
    
    if ($matched_tier) {
        $total_fee = $value_to_calculate * $matched_tier['price'];
    } else {
        // Xử lý khi không tìm thấy mức phù hợp
        $total_fee = 0;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cập nhật bảng giá</title>

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
        }
        
        .btn-cancel {
            background-color: #e2e8f0;
            color: #4a5568;
            border: none;
            padding: 8px 20px;
            font-size: 14px;
        }
        
        .btn-remove-row {
            color: #dc3545;
            background: none;
            border: none;
            padding: 0;
            font-size: 16px;
            cursor: pointer;
        }
        
        .btn-add-row {
            background-color: #e2e8f0;
            color: #4a5568;
            border: none;
            padding: 8px 12px;
            font-size: 14px;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .price-row {
            position: relative;
            margin-bottom: 15px;
            padding-right: 30px;
        }
        
        .price-row-remove {
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
        }
        
        .variable-price-container {
            border: 1px solid #eaeaea;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .manage-container {
            width: 100%;
            padding: 20px;
            background-color: #f5f5f5;
        }
        
        .required-mark {
            color: #dc3545;
            margin-left: 3px;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <?php include '../admin_navbar.php'; ?>
        <div style="width: 100%;">
            <?php include '../admin_header.php'; ?>
            <div class="manage-container">
                <!-- Page Header -->
                <div class="page-header">
                    <h1 class="main-title">CẬP NHẬT BẢNG GIÁ</h1>
                    <div class="breadcrumb">
                        <a href="/webquanlytoanha/admin/dashboard.php">Trang chủ</a>
                        <span style="margin: 0 8px;">›</span>
                        <a href="service_list.php">Dịch vụ tòa nhà</a>
                        <span style="margin: 0 8px;">›</span>
                        <a href="price_list.php">Danh sách bảng giá</a>
                        <span style="margin: 0 8px;">›</span>
                        <span>Cập nhật bảng giá</span>
                    </div>
                </div>
                
                <!-- Form -->
                <form action="" method="POST">
                    <div class="form-container">
                        <h2 class="form-title">THÔNG TIN BẢNG GIÁ</h2>

                <?php if(isset($error)): ?>
                        <div class="alert alert-danger" role="alert">
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Mã bảng giá<span class="required-mark">*</span></label>
                                <input type="text" class="form-control" value="<?php echo $price['Code']; ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Tên bảng giá<span class="required-mark">*</span></label>
                                <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($price['Name']); ?>">
                                </div>
                                </div>
                                
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Dịch vụ<span class="required-mark">*</span></label>
                                <select name="service_id" class="form-select" required>
                                    <option value="">-- Chọn dịch vụ --</option>
                                    <?php while($service = mysqli_fetch_assoc($select_services)): ?>
                                    <option value="<?php echo $service['ServiceCode']; ?>" <?php echo $service_id == $service['ServiceCode'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($service['Name']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                    </select>
                                </div>
                            <div class="col-md-6">
                                <label class="form-label">Ngày áp dụng<span class="required-mark">*</span></label>
                                <input type="date" name="apply_date" class="form-control" required value="<?php echo $price['ApplyDate']; ?>">
                            </div>
                            </div>
                            
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Loại bảng giá<span class="required-mark">*</span></label>
                                <select name="type_of_fee" id="type_of_fee" class="form-select" required>
                                    <option value="">-- Chọn loại bảng giá --</option>
                                    <option value="Cố định" <?php echo $price['TypeOfFee'] == 'Cố định' ? 'selected' : ''; ?>>Cố định</option>
                                    <option value="Lũy tiến" <?php echo $price['TypeOfFee'] == 'Lũy tiến' ? 'selected' : ''; ?>>Lũy tiến</option>
                                    <option value="Nhân khẩu" <?php echo $price['TypeOfFee'] == 'Nhân khẩu' ? 'selected' : ''; ?>>Nhân khẩu</option>
                                    <option value="Định mức" <?php echo $price['TypeOfFee'] == 'Định mức' ? 'selected' : ''; ?>>Định mức</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Phần cho loại phí Cố định -->
                        <div id="fixed_fee_section" class="<?php echo $price['TypeOfFee'] == 'Cố định' ? '' : 'd-none'; ?>">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Hình thức tính<span class="required-mark">*</span></label>
                                    <select name="price_calculation" class="form-select">
                                        <option value="">-- Chọn hình thức tính --</option>
                                        <option value="Đơn giá (m2)" <?php echo $price['PriceCalculation'] == 'Đơn giá (m2)' ? 'selected' : ''; ?>>Đơn giá (m2)</option>
                                        <option value="Định mức (phòng)" <?php echo $price['PriceCalculation'] == 'Định mức (phòng)' ? 'selected' : ''; ?>>Định mức (phòng)</option>
                                        <option value="Định mức (HK)" <?php echo $price['PriceCalculation'] == 'Định mức (HK)' ? 'selected' : ''; ?>>Định mức (HK)</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Giá<span class="required-mark">*</span></label>
                                    <input type="number" name="fixed_price" class="form-control" value="<?php echo $price['TypeOfFee'] == 'Cố định' ? $price['Price'] : ''; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Phần cho các loại phí khác -->
                        <div id="variable_fee_section" class="<?php echo $price['TypeOfFee'] != 'Cố định' ? '' : 'd-none'; ?>">
                            <div id="variable_rows_container">
                                <?php foreach($variable_rows as $index => $row): ?>
                                <div class="price-row row">
                                    <div class="col-md-3">
                                        <label class="form-label">Tiêu đề<span class="required-mark">*</span></label>
                                        <input type="text" name="variable_title[]" class="form-control" value="<?php echo htmlspecialchars($row['title']); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Từ</label>
                                        <input type="number" name="variable_price_from[]" class="form-control" value="<?php echo $row['price_from']; ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Đến</label>
                                        <input type="number" name="variable_price_to[]" class="form-control" value="<?php echo $row['price_to']; ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Giá<span class="required-mark">*</span></label>
                                        <input type="number" name="variable_price[]" class="form-control" value="<?php echo $row['price']; ?>">
                                    </div>
                                    <?php if($index > 0): ?>
                                    <div class="price-row-remove">
                                        <button type="button" class="btn-remove-row" onclick="removeRow(this)">
                                            <i class="fas fa-times-circle"></i>
                                    </button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="text-end mt-2 mb-3">
                                <button type="button" id="add_price_row" class="btn-add-row">
                                    <i class="fas fa-plus"></i> Thêm dòng
                                </button>
                            </div>
                        </div>
                        
                        <div class="mt-4 text-end">
                            <a href="price_list.php" class="btn btn-cancel me-2">Hủy</a>
                            <button type="submit" name="submit" class="btn btn-submit">Cập nhật</button>
                        </div>
                        </div>
                    </form>
            </div>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        // Xử lý hiển thị các phần tùy theo loại phí
        $('#type_of_fee').change(function() {
            var selectedType = $(this).val();
            
            if (selectedType === 'Cố định') {
                $('#fixed_fee_section').removeClass('d-none');
                $('#variable_fee_section').addClass('d-none');
            } else {
                $('#fixed_fee_section').addClass('d-none');
                $('#variable_fee_section').removeClass('d-none');
                
                // Nếu chưa có dòng nào, thêm dòng đầu tiên
                if ($('#variable_rows_container').children().length === 0) {
                    addVariableRow();
                }
            }
        });
        
        // Xử lý thêm dòng cho bảng giá biến đổi
        $('#add_price_row').click(function() {
            addVariableRow();
        });
    });
    
    // Hàm thêm dòng mới cho bảng giá biến đổi
    function addVariableRow() {
        var newRow = `
            <div class="price-row row">
                <div class="col-md-3">
                    <label class="form-label">Tiêu đề<span class="required-mark">*</span></label>
                    <input type="text" name="variable_title[]" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Từ</label>
                    <input type="number" name="variable_price_from[]" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Đến</label>
                    <input type="number" name="variable_price_to[]" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Giá<span class="required-mark">*</span></label>
                    <input type="number" name="variable_price[]" class="form-control">
                </div>
                <div class="price-row-remove">
                    <button type="button" class="btn-remove-row" onclick="removeRow(this)">
                        <i class="fas fa-times-circle"></i>
                    </button>
                </div>
                </div>
            `;
        $('#variable_rows_container').append(newRow);
    }
    
    // Hàm xóa dòng
    function removeRow(button) {
        $(button).closest('.price-row').remove();
    }
    </script>
</body>
</html>