<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Lấy danh sách dịch vụ active để hiển thị trong dropdown
$select_services = mysqli_query($conn, "SELECT * FROM services WHERE Status = 'active'");

// Thêm dòng này vào đầu file, sau khi kết nối database
mysqli_set_charset($conn, "utf8mb4");

// Xử lý thêm mới bảng giá
if (isset($_POST['submit'])) {
    $code = mysqli_real_escape_string($conn, $_POST['code']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $service_id = mysqli_real_escape_string($conn, $_POST['service_id']);
    $apply_date = mysqli_real_escape_string($conn, $_POST['apply_date']);
    $type_of_fee = mysqli_real_escape_string($conn, $_POST['type_of_fee']);
    $status = 'active';

    // Kiểm tra xem code đã tồn tại chưa
    $check_code = mysqli_query($conn, "SELECT * FROM pricelist WHERE Code = '$code'");
    if (mysqli_num_rows($check_code) > 0) {
        $error = 'Mã bảng giá đã tồn tại!';
    } else {
        // Khởi tạo giá trị cho các trường tùy thuộc vào loại phí
        $calculation_method = '';
        $title = '';
        $price_from = 0;
        $price_to = 0;
        $price = 0;
        $variable_data = '';

        if ($type_of_fee == 'Cố định') {
            $calculation_method = isset($_POST['calculation_method']) ? mysqli_real_escape_string($conn, $_POST['calculation_method']) : '';
            $price = isset($_POST['fixed_price']) ? floatval($_POST['fixed_price']) : 0;
            $variable_data = '';
        } else {
            // Lưu title, price_from, price_to và price trong chuỗi JSON để lưu nhiều dòng
            $variable_titles = isset($_POST['variable_title']) ? $_POST['variable_title'] : [];
            $variable_price_froms = isset($_POST['variable_price_from']) ? $_POST['variable_price_from'] : [];
            $variable_price_tos = isset($_POST['variable_price_to']) ? $_POST['variable_price_to'] : [];
            $variable_prices = isset($_POST['variable_price']) ? $_POST['variable_price'] : [];
            
            // Lấy dòng đầu tiên làm giá trị chính
            if (!empty($variable_titles)) {
                $title = mb_convert_encoding($variable_titles[0], 'UTF-8', 'auto');
                $price_from = !empty($variable_price_froms) ? floatval($variable_price_froms[0]) : 0;
                $price_to = !empty($variable_price_tos) ? floatval($variable_price_tos[0]) : 0;
                $price = !empty($variable_prices) ? floatval($variable_prices[0]) : 0;
            }
            
            // Tạo mảng để lưu các dòng biến đổi
            $variable_rows = [];
            for ($i = 0; $i < count($variable_titles); $i++) {
                if (!empty($variable_titles[$i])) {
                    $variable_rows[] = [
                        'title' => mb_convert_encoding($variable_titles[$i], 'UTF-8', 'auto'),
                        'price_from' => isset($variable_price_froms[$i]) ? floatval($variable_price_froms[$i]) : 0,
                        'price_to' => isset($variable_price_tos[$i]) ? floatval($variable_price_tos[$i]) : 0,
                        'price' => isset($variable_prices[$i]) ? floatval($variable_prices[$i]) : 0
                    ];
                }
            }
            
            // Chuyển đổi thành chuỗi JSON với encoding UTF-8
            $variable_data = json_encode($variable_rows, JSON_UNESCAPED_UNICODE);
        }

        try {
            // Thêm mới vào bảng pricelist
            mysqli_query($conn, "
                INSERT INTO pricelist (Code, Name, TypeOfFee, Title, PriceCalculation, PriceFrom, PriceTo, Price, VariableData, ApplyDate, Status) 
                VALUES ('$code', '$name', '$type_of_fee', '$title', '$calculation_method', $price_from, $price_to, $price, '$variable_data', '$apply_date', '$status')
            ");
            
            $price_id = mysqli_insert_id($conn);
            
            // Thêm vào bảng trung gian ServicePrice
            mysqli_query($conn, "INSERT INTO ServicePrice (ServiceId, PriceId) VALUES ('$service_id', $price_id)");
            
            $_SESSION['success_msg'] = 'Đã thêm bảng giá thành công!';
            header('location: price_list.php');
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
    <title>Thêm mới bảng giá</title>

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
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 8px;
            display: block;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            outline: none;
        }
        
        .form-control:focus {
            border-color: #476a52;
            box-shadow: 0 0 0 2px rgba(71, 106, 82, 0.1);
        }
        
        .form-select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            outline: none;
            background-color: white;
            cursor: pointer;
        }
        
        .btn-container {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-submit {
            padding: 8px 20px;
            background-color: #476a52;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-submit:hover {
            background-color: #3a5943;
        }
        
        .btn-cancel {
            padding: 8px 20px;
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        
        .btn-cancel:hover {
            background-color: #c82333;
            color: white;
        }
        
        .manage-container {
            background-color: #f5f5f5;
            width: 100%;
            padding: 20px;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .price-form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
            align-items: flex-end;
        }
        
        .price-form-row .form-control {
            flex: 1;
            min-width: 120px;
        }
        
        .required:after {
            content: " (*)";
            color: red;
        }

        .add-row-btn {
            background: transparent;
            border: none;
            color: #476a52;
            cursor: pointer;
            padding: 8px;
            margin-left: 5px;
        }
        
        .remove-row-btn {
            background: transparent;
            border: none;
            color: #dc3545;
            cursor: pointer;
            padding: 8px;
        }
        
        .date-input-group {
            position: relative;
        }
        
        .date-input-group .form-control {
            padding-right: 35px;
        }
        
        .date-input-group .calendar-icon {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            pointer-events: none;
        }
        
        .price-rows-container {
            margin-top: 10px;
        }
        
        .hidden {
            display: none;
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
                    <h1 class="form-title">Thêm mới bảng giá</h1>
                    <div class="breadcrumb">
                        <a href="/webquanlytoanha/admin/dashboard.php">Trang chủ</a>
                        <span style="margin: 0 8px;">›</span>
                        <a href="/webquanlytoanha/admin/service/service_list.php">Dịch vụ tòa nhà</a>
                        <span style="margin: 0 8px;">›</span>
                        <a href="price_list.php">Danh sách bảng giá</a>
                        <span style="margin: 0 8px;">›</span>
                        <span>Thêm mới bảng giá</span>
                    </div>
                </div>

                <?php if(isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <div class="card">
                    <form action="" method="post" id="priceForm">
                    <div class="row">
                        <div class="col-md-6">
                                <div class="form-group">
                                    <label for="code" class="form-label required">Mã bảng giá</label>
                                    <input type="text" id="code" name="code" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="service_id" class="form-label required">Dịch vụ</label>
                                    <select id="service_id" name="service_id" class="form-select" required>
                                        <option value="">--Chọn dịch vụ--</option>
                                        <?php 
                                        while ($service = mysqli_fetch_assoc($select_services)) {
                                            echo '<option value="' . $service['ServiceCode'] . '">' . 
                                                htmlspecialchars($service['Name']) . 
                                                 '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="type_of_fee" class="form-label required">Loại bảng giá</label>
                                    <select id="type_of_fee" name="type_of_fee" class="form-select" required>
                                        <option value="">--Chọn loại bảng giá--</option>
                                        <option value="Cố định">Cố định</option>
                                        <option value="Lũy tiến">Lũy tiến</option>
                                        <option value="Nhân khẩu">Nhân khẩu</option>
                                        <option value="Định mức">Định mức</option>
                                    </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name" class="form-label required">Tên bảng giá</label>
                                    <input type="text" id="name" name="name" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="apply_date" class="form-label required">Ngày áp dụng</label>
                                    <div class="date-input-group">
                                        <input type="date" id="apply_date" name="apply_date" class="form-control" 
                                            min="<?php echo date('Y-m-d'); ?>" required>
                                        <span class="calendar-icon"><i class="fas fa-calendar-alt"></i></span>
                                    </div>
                                </div>
                            </div>
                                </div>
                        
                        <!-- Container cho các trường dữ liệu đặc biệt dựa vào loại bảng giá -->
                        <div id="fixed-price-container" class="mt-3 hidden">
                            <div class="row">
                                <div class="col-md-6">
                                <div class="form-group">
                                        <label for="calculation_method" class="form-label required">Mức giá</label>
                                        <select id="calculation_method" name="calculation_method" class="form-select">
                                            <option value="">--Nhập giá tiền--</option>
                                            <option value="floor">Giá sàn</option>
                                            <option value="normal">Giá thường</option>
                                    </select>
                                </div>
                                </div>
                                <div class="col-md-6">
                                <div class="form-group">
                                        <label for="fixed_price" class="form-label required">Giá tiền</label>
                                        <input type="number" id="fixed_price" name="fixed_price" class="form-control" min="0">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div id="variable-price-container" class="mt-3 hidden">
                            <div id="price-rows-container">
                                <div class="price-form-row">
                                    <input type="text" name="variable_title[]" class="form-control" placeholder="--Nhập tiêu đề--">
                                    <input type="number" name="variable_price_from[]" class="form-control" placeholder="--Nhập từ--">
                                    <input type="number" name="variable_price_to[]" class="form-control" placeholder="--Nhập đến--">
                                    <input type="number" name="variable_price[]" class="form-control" placeholder="--Nhập giá tiền--">
                                    <button type="button" class="remove-row-btn">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-center">
                                <button type="button" id="add-row-btn" class="btn-submit w-100">THÊM MỚI</button>
                        </div>
                    </div>

                        <div class="btn-container mt-4">
                        <button type="submit" name="submit" class="btn-submit">Thêm mới</button>
                            <a href="price_list.php" class="btn-cancel">Hủy</a>
                    </div>
                </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        // Xử lý khi thay đổi loại bảng giá
        $('#type_of_fee').on('change', function() {
            const selectedType = $(this).val();
            
            if (selectedType === 'Cố định') {
                $('#fixed-price-container').removeClass('hidden');
                $('#variable-price-container').addClass('hidden');
                
                // Reset validations
                $('#calculation_method').prop('required', true);
                $('#fixed_price').prop('required', true);
            } else if (selectedType) {
                $('#fixed-price-container').addClass('hidden');
                $('#variable-price-container').removeClass('hidden');
                
                // Set validations
                $('#calculation_method').prop('required', false);
            } else {
                $('#fixed-price-container').addClass('hidden');
                $('#variable-price-container').addClass('hidden');
            }
        });
        
        // Xử lý thêm dòng mới cho giá biến động
        $('#add-row-btn').on('click', function() {
            const newRow = `
                <div class="price-form-row">
                    <input type="text" name="variable_title[]" class="form-control" placeholder="--Nhập tiêu đề--">
                    <input type="number" name="variable_price_from[]" class="form-control" placeholder="--Nhập từ--">
                    <input type="number" name="variable_price_to[]" class="form-control" placeholder="--Nhập đến--">
                    <input type="number" name="variable_price[]" class="form-control" placeholder="--Nhập giá tiền--">
                    <button type="button" class="remove-row-btn">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
            
            $('#price-rows-container').append(newRow);
        });
        
        // Xử lý xóa dòng
        $(document).on('click', '.remove-row-btn', function() {
            // Kiểm tra nếu chỉ còn 1 dòng thì không cho xóa
            if ($('.price-form-row').length > 1) {
                $(this).closest('.price-form-row').remove();
            }
        });
        
        // Xử lý submit form
        $('#priceForm').on('submit', function(e) {
            const selectedType = $('#type_of_fee').val();
            
            if (!selectedType) {
                e.preventDefault();
                alert('Vui lòng chọn loại bảng giá');
                return;
            }
            
            if (selectedType === 'Cố định') {
                const calculation_method = $('#calculation_method').val();
                const price = $('#fixed_price').val();
                
                if (!calculation_method || !price) {
                    e.preventDefault();
                    alert('Vui lòng điền đầy đủ thông tin mức giá và giá tiền');
                    return;
                }
            } else {
                // Kiểm tra ít nhất 1 dòng giá biến động đã được điền đầy đủ
                let isValid = false;
                $('.price-form-row').each(function() {
                    const title = $(this).find('input[name="variable_title[]"]').val();
                    const priceFrom = $(this).find('input[name="variable_price_from[]"]').val();
                    const priceTo = $(this).find('input[name="variable_price_to[]"]').val();
                    const price = $(this).find('input[name="variable_price[]"]').val();
                    
                    if (title && priceFrom && priceTo && price) {
                        isValid = true;
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Vui lòng điền đầy đủ thông tin cho ít nhất một dòng giá');
                    return;
                }
            }
        });
    });
    </script>
</body>
</html>