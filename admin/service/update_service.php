<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Kiểm tra xem có ID dịch vụ được truyền vào không
if (!isset($_GET['code']) || empty($_GET['code'])) {
    header('location: service_list.php');
    exit();
}

$service_code = mysqli_real_escape_string($conn, $_GET['code']);

// Lấy thông tin dịch vụ hiện tại
$service_query = mysqli_query($conn, "SELECT * FROM services WHERE ServiceCode = '$service_code'") or die('Query failed: ' . mysqli_error($conn));

if (mysqli_num_rows($service_query) == 0) {
    header('location: service_list.php');
    exit();
}

$service = mysqli_fetch_assoc($service_query);

// Lấy thông tin user hiện tại
$user_query = mysqli_query($conn, "SELECT * FROM users WHERE UserId = '$admin_id'") or die('Query failed');
$user_info = mysqli_fetch_assoc($user_query);

// Lấy danh sách dự án của user
$projects_query = mysqli_query($conn, "
    SELECT DISTINCT p.* 
    FROM Projects p 
    INNER JOIN StaffProjects sp ON p.ProjectID = sp.ProjectId 
    INNER JOIN Staffs s ON sp.StaffId = s.ID 
    INNER JOIN users u ON s.Email = u.Email 
    WHERE u.UserId = '$admin_id' AND p.Status = 'active'
    ORDER BY p.Name
") or die('Query failed: ' . mysqli_error($conn));

// Xử lý AJAX request cập nhật trạng thái
if(isset($_POST['update_status']) && isset($_POST['service_code']) && isset($_POST['status'])) {
    $service_code = mysqli_real_escape_string($conn, $_POST['service_code']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
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

// Xử lý form update
if (isset($_POST['submit'])) {
    $service_name = mysqli_real_escape_string($conn, $_POST['service_name']);
    $type_of_object = mysqli_real_escape_string($conn, $_POST['type_of_object']);
    $type_of_service = mysqli_real_escape_string($conn, $_POST['type_of_service']);
    $cycle = mysqli_real_escape_string($conn, $_POST['cycle']);
    $first_date = mysqli_real_escape_string($conn, $_POST['first_date']);
    $paydate = mysqli_real_escape_string($conn, $_POST['paydate']);
    $start_price_type = mysqli_real_escape_string($conn, $_POST['start_price_type']);
    $cancel_price_type = mysqli_real_escape_string($conn, $_POST['cancel_price_type']);
    $apply_from = mysqli_real_escape_string($conn, $_POST['apply_from']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $project_id = !empty($_POST['project_id']) ? mysqli_real_escape_string($conn, $_POST['project_id']) : NULL;

    try {
        mysqli_begin_transaction($conn);

        // Tạo query string với tham số NULL nếu project_id trống
        $sql = "
            UPDATE services 
            SET Name = '$service_name', 
                Description = '$description', 
                TypeOfObject = '$type_of_object', 
                TypeOfService = '$type_of_service', 
                Cycle = '$cycle', 
                FirstDate = '$first_date', 
                Paydate = '$paydate', 
                StartPrice = '$start_price_type', 
                CancelPrice = '$cancel_price_type', 
                ApplyForm = '$apply_from', 
                ProjectId = " . ($project_id === NULL ? "NULL" : "'$project_id'") . " 
            WHERE ServiceCode = '$service_code'
        ";
        
        $update_query = mysqli_query($conn, $sql) or throw new Exception(mysqli_error($conn));

        mysqli_commit($conn);
        
        $_SESSION['success_msg'] = 'Cập nhật dịch vụ thành công!';
        header('location: service_list.php');
        exit();
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error = 'Lỗi: ' . $e->getMessage();
    }
}

// Phần xử lý AJAX để lấy giá hiện tại
if (isset($_POST['ajax_get_price']) && isset($_POST['service_id'])) {
    $service_id = mysqli_real_escape_string($conn, $_POST['service_id']);
    $price_type = isset($_POST['price_type']) ? mysqli_real_escape_string($conn, $_POST['price_type']) : '';
    
    // Điều kiện tìm kiếm loại bảng giá
    $price_type_condition = !empty($price_type) ? "AND pl.TypeOfFee = '$price_type'" : "";
    
    // Lấy giá mới nhất áp dụng cho dịch vụ này
    $query = "
        SELECT pl.Price, pl.TypeOfFee, pl.PriceCalculation
        FROM pricelist pl 
        JOIN ServicePrice sp ON pl.ID = sp.PriceId
        WHERE sp.ServiceId = '$service_id' $price_type_condition AND pl.Status = 'active'
        ORDER BY pl.ApplyDate DESC
        LIMIT 1
    ";
    
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) > 0) {
        $price_data = mysqli_fetch_assoc($result);
        
        echo json_encode([
            'success' => true,
            'price' => $price_data['Price'],
            'price_formatted' => number_format($price_data['Price']) . ' VND',
            'calculation_method' => $price_data['PriceCalculation'] ?? 'Giá dịch vụ thường'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Không tìm thấy giá cho dịch vụ này'
        ]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cập nhật dịch vụ</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
        
        .create-form {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-top: 20px;
        }
        
        .form-title {
            color: #476a52;
            border-bottom: 1px solid #eaeaea;
            padding-bottom: 15px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .form-group {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .form-label {
            width: 150px;
            text-align: right;
            margin-right: 15px;
            font-weight: 500;
            color: #4a5568;
        }
        
        .form-label .required {
            color: red;
        }
        
        .form-control {
            flex: 1;
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
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            outline: none;
            background-color: white;
            cursor: pointer;
        }
        
        .company-section {
            margin-top: 20px;
            margin-bottom: 20px;
        }
        
        .company-title {
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 10px;
        }
        
        .project-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            margin-left: 20px;
        }
        
        .project-item input[type="checkbox"] {
            margin-right: 10px;
        }
        
        .btn-container {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 25px;
        }
        
        .btn-submit {
            padding: 10px 30px;
            background-color: #476a52;
            color: white;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-submit:hover {
            background-color: #3a5943;
        }
        
        .btn-cancel {
            padding: 10px 30px;
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        
        .btn-cancel:hover {
            background-color: #c82333;
        }
        
        .manage-container {
            background:rgb(243, 239, 239) !important;
            width: 100%;
            padding: 20px;
        }
        
        .form-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #476a52;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-label {
            font-weight: 500;
            margin-bottom: 5px;
        }

        .required::after {
            content: " (*)";
            color: red;
        }

        .form-control, .form-select {
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        .btn-container {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn-submit {
            border: none;
            color: white;
            padding: 8px 20px;
            border-radius: 4px;
        }

        .btn-cancel {
            background: #DC3545;
            border: none;
            color: white;
            padding: 8px 20px;
            border-radius: 4px;
            text-decoration: none;
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

        /* CSS cho tab menu giá và lịch sử bảng giá */
        .price-info-tabs {
            margin-top: 30px;
            border: 1px solid #eaeaea;
            border-radius: 4px;
            overflow: hidden;
        }

        .price-tab-header {
            display: flex;
            background-color: #f5f5f5;
            border-bottom: 1px solid #eaeaea;
        }

        .price-tab {
            padding: 15px 20px;
            cursor: pointer;
            font-weight: 500;
            color: #4a5568;
            border-bottom: 2px solid transparent;
        }

        .price-tab.active {
            background-color: #6b8b7b;
            color: white;
        }

        .price-tab-content {
            display: none;
            padding: 20px;
            background-color: white;
        }

        .price-tab-content.active {
            display: block;
        }

        .price-history-table {
            width: 100%;
            border-collapse: collapse;
        }

        .price-history-table th {
            background-color: #6b8b7b;
            color: white;
            padding: 10px;
            text-align: center;
            font-weight: 500;
        }

        .price-history-table td {
            padding: 10px;
            border-bottom: 1px solid #eaeaea;
            text-align: center;
        }

        .price-history-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .price-history-table tr:hover {
            background-color: #f5f5f5;
        }

        .price-section {
            padding: 10px;
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
                
                <div class="page-header mb-2">
                    <h2 style="font-weight: bold; color: #476a52;">CẬP NHẬT DỊCH VỤ</h2>
                    <div class="breadcrumb">
                        <a href="/webquanlytoanha/admin/dashboard.php">Trang chủ</a>
                        <span style="margin: 0 8px;">›</span>
                        <a href="service_list.php">Dịch vụ tòa nhà</a>
                        <span style="margin: 0 8px;">›</span>
                        <span>Cập nhật dịch vụ</span>
                    </div>
                </div>

                <?php if(isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <form action="" method="post">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-section">
                                <div class="section-title">THÔNG TIN DỊCH VỤ</div>
                                <div class="form-group">
                                    <label class="form-label required">Mã dịch vụ</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($service['ServiceCode']); ?>" readonly>
                                </div>
                                <div class="form-group">
                                    <label class="form-label required">Tên dịch vụ</label>
                                    <input type="text" name="service_name" class="form-control" required 
                                           value="<?php echo htmlspecialchars($service['Name']); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label required">Đối tượng</label>
                                    <select name="type_of_object" class="form-select" required>
                                        <option value="">Chọn đối tượng</option>
                                        <option value="Công ty" <?php echo $service['TypeOfObject'] == 'Công ty' ? 'selected' : ''; ?>>Công ty</option>
                                        <option value="Ban quản trị" <?php echo $service['TypeOfObject'] == 'Ban quản trị' ? 'selected' : ''; ?>>Ban quản trị</option>
                                        <option value="Chủ đầu tư" <?php echo $service['TypeOfObject'] == 'Chủ đầu tư' ? 'selected' : ''; ?>>Chủ đầu tư</option>
                                        <option value="Thu hộ" <?php echo $service['TypeOfObject'] == 'Thu hộ' ? 'selected' : ''; ?>>Thu hộ</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label required">Loại dịch vụ</label>
                                    <select name="type_of_service" class="form-select" required>
                                        <option value="">Chọn loại dịch vụ</option>
                                        <option value="Dịch vụ quản lý" <?php echo $service['TypeOfService'] == 'Dịch vụ quản lý' ? 'selected' : ''; ?>>Dịch vụ quản lý</option>
                                        <option value="Điện" <?php echo $service['TypeOfService'] == 'Điện' ? 'selected' : ''; ?>>Điện</option>
                                        <option value="Nước" <?php echo $service['TypeOfService'] == 'Nước' ? 'selected' : ''; ?>>Nước</option>
                                        <option value="Phương tiện" <?php echo $service['TypeOfService'] == 'Phương tiện' ? 'selected' : ''; ?>>Phương tiện</option>
                                        <option value="Dịch vụ Khác" <?php echo $service['TypeOfService'] == 'Dịch vụ Khác' ? 'selected' : ''; ?>>Dịch vụ Khác</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Dự án</label>
                                    <select name="project_id" class="form-select">
                                        <option value="">Chọn dự án</option>
                                        <?php 
                                        while ($project = mysqli_fetch_assoc($projects_query)) {
                                            $selected = ($project['ProjectID'] == $service['ProjectId']) ? 'selected' : '';
                                            echo '<option value="' . $project['ProjectID'] . '" ' . $selected . '>' . 
                                                 htmlspecialchars($project['Name']) . ' - ' . 
                                                 htmlspecialchars($project['Address']) . 
                                                 '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Mô tả</label>
                                    <textarea name="description" class="form-control" rows="4"><?php echo htmlspecialchars($service['Description']); ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Trạng thái</label>
                                    <div class="status-toggle <?php echo $service['Status'] == 'active' ? 'active' : ''; ?>" 
                                         data-service="<?php echo $service['ServiceCode']; ?>">
                                        <div class="toggle-slider"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-section">
                                <div class="section-title">HẠN DỊCH VỤ</div>
                                <div class="form-group">
                                    <label class="form-label required">Chu kỳ</label>
                                    <select name="cycle" class="form-select" required>
                                        <option value="">Chọn chu kỳ</option>
                                        <option value="1" <?php echo $service['Cycle'] == '1' ? 'selected' : ''; ?>>Tháng</option>
                                        <option value="3" <?php echo $service['Cycle'] == '3' ? 'selected' : ''; ?>>Quý</option>
                                        <option value="12" <?php echo $service['Cycle'] == '12' ? 'selected' : ''; ?>>Năm</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label required">Ngày đầu kỳ</label>
                                    <input type="date" name="first_date" class="form-control" required
                                           value="<?php echo $service['FirstDate']; ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label required">Ngày thanh toán</label>
                                    <input type="date" name="paydate" class="form-control" required
                                           value="<?php echo $service['Paydate']; ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label required">Cách tính giá khi bắt đầu sử dụng</label>
                                    <select name="start_price_type" class="form-select" required>
                                        <option value="">Chọn cách tính</option>
                                        <option value="full" <?php echo $service['StartPrice'] == 'full' ? 'selected' : ''; ?>>Cả tháng</option>
                                        <option value="half" <?php echo $service['StartPrice'] == 'half' ? 'selected' : ''; ?>>Nửa tháng</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label required">Cách tính giá khi hủy</label>
                                    <select name="cancel_price_type" class="form-select" required>
                                        <option value="">Chọn cách tính</option>
                                        <option value="full" <?php echo $service['CancelPrice'] == 'full' ? 'selected' : ''; ?>>Cả tháng</option>
                                        <option value="half" <?php echo $service['CancelPrice'] == 'half' ? 'selected' : ''; ?>>Nửa tháng</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label required">Áp dụng từ</label>
                                    <input type="date" name="apply_from" class="form-control" required
                                           value="<?php echo $service['ApplyForm']; ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="btn-container">
                        <button type="submit" name="submit" class="btn-submit">Cập nhật</button>
                        <a href="service_list.php" class="btn-cancel">Hủy bỏ</a>
                    </div>
                </form>

                <!-- Tab Menu cho Giá và Lịch sử bảng giá -->
                <div class="price-info-tabs">
                    <div class="price-tab-header">
                        <div class="price-tab active" data-tab="current-price">Giá hiện tại</div>
                        <div class="price-tab" data-tab="price-history">Lịch sử bảng giá</div>
                    </div>
                    
                    <!-- Tab Giá hiện tại -->
                    <div class="price-tab-content active" id="current-price-tab">
                        <div class="price-section">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Loại bảng giá</label>
                                    <select id="price_type" class="form-select">
                                        <option value="">Mặc định (cố định)</option>
                                        <?php
                                        // Lấy danh sách loại giá từ bảng giá đã liên kết với dịch vụ này
                                        $price_types_query = mysqli_query($conn, "
                                            SELECT DISTINCT pl.TypeOfFee 
                                            FROM pricelist pl 
                                            JOIN ServicePrice sp ON pl.ID = sp.PriceId 
                                            WHERE sp.ServiceId = '$service_code' AND pl.Status = 'active'
                                        ");
                                        while ($price_type = mysqli_fetch_assoc($price_types_query)) {
                                            echo '<option value="'.$price_type['TypeOfFee'].'">'.$price_type['TypeOfFee'].'</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Cách tính giá</label>
                                    <input type="text" class="form-control" value="Giá dịch vụ thường" readonly>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label class="form-label">Đơn giá</label>
                                    <input type="text" id="current_price" class="form-control" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab Lịch sử bảng giá -->
                    <div class="price-tab-content" id="price-history-tab">
                        <div class="price-history-section">
                            <table class="price-history-table">
                                <thead>
                                    <tr>
                                        <th>STT</th>
                                        <th>Mã bảng giá</th>
                                        <th>Tên bảng giá</th>
                                        <th>Loại bảng giá</th>
                                        <th>Giá tiền (VND)</th>
                                        <th>Ngày áp dụng</th>
                                        <th>Ngày kết thúc</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Lấy lịch sử giá từ bảng pricelist và ServicePrice
                                    $price_history_query = mysqli_query($conn, "
                                        SELECT pl.ID, pl.Code, pl.Name, pl.TypeOfFee, pl.Price, pl.ApplyDate, pl.Status
                                        FROM pricelist pl 
                                        JOIN ServicePrice sp ON pl.ID = sp.PriceId
                                        WHERE sp.ServiceId = '$service_code'
                                        ORDER BY pl.ApplyDate DESC
                                    ");
                                    
                                    if (mysqli_num_rows($price_history_query) > 0) {
                                        $counter = 1;
                                        $previous_date = null;
                                        
                                        while ($price = mysqli_fetch_assoc($price_history_query)) {
                                            $current_date = $price['ApplyDate'];
                                            $end_date = $previous_date ? date('d/m/Y', strtotime($previous_date)) : '';
                                            $previous_date = $current_date;
                                            
                                            echo '<tr>';
                                            echo '<td>'.$counter++.'</td>';
                                            echo '<td>'.$price['Code'].'</td>';
                                            echo '<td>'.$price['Name'].'</td>';
                                            echo '<td>'.$price['TypeOfFee'].'</td>';
                                            echo '<td>'.number_format($price['Price']).'</td>';
                                            echo '<td>'.date('d/m/Y', strtotime($price['ApplyDate'])).'</td>';
                                            echo '<td>'.$end_date.'</td>';
                                            echo '</tr>';
                                        }
                                    } else {
                                        echo '<tr><td colspan="7" class="text-center">Không có dữ liệu bảng giá nào cho dịch vụ này</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const applyFromInput = document.querySelector('input[name="apply_from"]');
        const firstDateInput = document.querySelector('input[name="first_date"]');
        const payDateInput = document.querySelector('input[name="paydate"]');

        // Set min date cho ngày đầu kỳ và ngày thanh toán
        applyFromInput.addEventListener('change', function() {
            const applyDate = this.value;
            firstDateInput.min = applyDate;
            payDateInput.min = applyDate;
            
            // Reset giá trị nếu nhỏ hơn ngày áp dụng
            if (firstDateInput.value && firstDateInput.value < applyDate) {
                firstDateInput.value = '';
            }
            if (payDateInput.value && payDateInput.value < applyDate) {
                payDateInput.value = '';
            }
        });

        // Kiểm tra ngày thanh toán không được nhỏ hơn ngày đầu kỳ
        firstDateInput.addEventListener('change', function() {
            const firstDate = this.value;
            payDateInput.min = firstDate;
            
            if (payDateInput.value && payDateInput.value < firstDate) {
                payDateInput.value = '';
            }
        });
        
        // Xử lý toggle status
        $('.status-toggle').on('click', function() {
            const toggleElement = $(this);
            const serviceCode = toggleElement.data('service');
            const currentStatus = toggleElement.hasClass('active') ? 'active' : 'inactive';
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            
            // Thêm class loading để disable và hiển thị trạng thái đang xử lý
            toggleElement.addClass('loading');
            
            // Gửi AJAX request để cập nhật trạng thái
            $.ajax({
                url: window.location.href, // Gửi AJAX đến chính file này
                type: 'POST',
                data: {
                    update_status: 1,
                    service_code: serviceCode,
                    status: newStatus
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Cập nhật UI dựa trên trạng thái mới
                        if (newStatus === 'active') {
                            toggleElement.addClass('active');
                        } else {
                            toggleElement.removeClass('active');
                        }
                        
                        // Hiển thị thông báo thành công
                        showNotification('success', 'Cập nhật trạng thái thành công');
                    } else {
                        // Khôi phục trạng thái UI nếu có lỗi
                        showNotification('error', response.message || 'Lỗi khi cập nhật trạng thái');
                    }
                },
                error: function() {
                    showNotification('error', 'Lỗi kết nối server');
                },
                complete: function() {
                    // Loại bỏ trạng thái loading
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
            
            // Thêm thông báo vào trang
            $('#notifications').html(alert);
            
            // Tự động ẩn sau 3 giây
            setTimeout(function() {
                $('.notification-alert').alert('close');
            }, 3000);
        }
    });

    $(document).ready(function() {
        // Tab switching logic
        $('.price-tab').click(function() {
            $('.price-tab').removeClass('active');
            $(this).addClass('active');
            
            const tabId = $(this).data('tab');
            $('.price-tab-content').removeClass('active');
            $('#' + tabId + '-tab').addClass('active');
        });
        
        // Lấy giá hiện tại khi chọn loại bảng giá
        $('#price_type').change(function() {
            const priceType = $(this).val();
            const serviceCode = '<?php echo $service_code; ?>';
            
            $.ajax({
                url: window.location.href, // Gửi AJAX đến chính file này
                type: 'POST',
                data: {
                    ajax_get_price: 1,
                    service_id: serviceCode,
                    price_type: priceType
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#current_price').val(response.price_formatted);
                    } else {
                        $('#current_price').val('Không có dữ liệu');
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error:", status, error);
                    console.log("Response:", xhr.responseText);
                    $('#current_price').val('Lỗi khi lấy dữ liệu');
                }
            });
        });
        
        // Tự động chọn loại bảng giá đầu tiên và lấy giá
        $('#price_type').trigger('change');
    });
    </script>
</body>
</html>