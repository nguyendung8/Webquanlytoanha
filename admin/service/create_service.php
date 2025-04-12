<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Lấy thông tin user hiện tại
$user_query = mysqli_query($conn, "SELECT * FROM users WHERE UserId = '$admin_id'") or die('Query failed');
$user_info = mysqli_fetch_assoc($user_query);

// Lấy danh sách dự án của user
$projects_query = mysqli_query($conn, "
    SELECT DISTINCT p.* 
    FROM Projects p 
    WHERE p.Status = 'active'
    ORDER BY p.Name
") or die('Query failed: ' . mysqli_error($conn));

if (isset($_POST['submit'])) {
    // Debug
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    $service_code = mysqli_real_escape_string($conn, $_POST['service_code']);
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
            INSERT INTO services (
                ServiceCode, Name, Description, TypeOfObject, TypeOfService, 
                Cycle, FirstDate, Paydate, StartPrice, CancelPrice, 
                ApplyForm, Status, ProjectId
            ) VALUES (
                '$service_code', '$service_name', '$description', '$type_of_object', 
                '$type_of_service', '$cycle', '$first_date', '$paydate', 
                '$start_price_type', '$cancel_price_type', '$apply_from', 'active',
                " . ($project_id === NULL ? "NULL" : "'$project_id'") . "
            )
        ";

        
        $insert_query = mysqli_query($conn, $sql) or throw new Exception(mysqli_error($conn));

        mysqli_commit($conn);
        
        $_SESSION['success_msg'] = 'Thêm dịch vụ thành công!';
        header('location: service_list.php');
        exit();
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error = 'Lỗi: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm mới dịch vụ</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
    </style>
</head>
<body>
    <div class="d-flex">
        <?php include '../admin_navbar.php'; ?>
        <div style="width: 100%;">
            <?php include '../admin_header.php'; ?>
            <div class="manage-container">
                <div class="page-header mb-2">
                    <h2 style="font-weight: bold; color: #476a52;">THÊM MỚI DỊCH VỤ</h2>
                    <div class="breadcrumb">
                        <a href="/webquanlytoanha/admin/dashboard.php">Trang chủ</a>
                        <span style="margin: 0 8px;">›</span>
                        <a href="service_list.php">Dịch vụ tòa nhà</a>
                        <span style="margin: 0 8px;">›</span>
                        <span>Thêm mới dịch vụ</span>
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
                                    <input type="text" name="service_code" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label required">Tên dịch vụ</label>
                                    <input type="text" name="service_name" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label required">Đối tượng</label>
                                    <select name="type_of_object" class="form-select" required>
                                        <option value="">Chọn đối tượng</option>
                                        <option value="Công ty">Công ty</option>
                                        <option value="Ban quản trị">Ban quản trị</option>
                                        <option value="Chủ đầu tư">Chủ đầu tư</option>
                                        <option value="Thu hộ">Thu hộ</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label required">Loại dịch vụ</label>
                                    <select name="type_of_service" class="form-select" required>
                                        <option value="">Chọn loại dịch vụ</option>
                                        <option value="Dịch vụ quản lý">Dịch vụ quản lý</option>
                                        <option value="Điện">Điện</option>
                                        <option value="Nước">Nước</option>
                                        <option value="Phương tiện">Phương tiện</option>
                                        <option value="Dịch vụ Khác">Dịch vụ Khác</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Dự án</label>
                                    <select name="project_id" class="form-select">
                                        <option value="">Chọn dự án</option>
                                        <?php 
                                        // Reset con trỏ kết quả để đảm bảo hiển thị tất cả dự án
                                        mysqli_data_seek($projects_query, 0);
                                        while ($project = mysqli_fetch_assoc($projects_query)) {
                                            echo '<option value="' . $project['ProjectID'] . '">' . 
                                                 htmlspecialchars($project['Name']) . ' - ' . 
                                                 htmlspecialchars($project['Address']) . 
                                                 '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Mô tả</label>
                                    <textarea name="description" class="form-control" rows="4"></textarea>
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
                                        <option value="1">Tháng</option>
                                        <option value="3">Quý</option>
                                        <option value="12">Năm</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label required">Ngày đầu kỳ</label>
                                    <input type="date" name="first_date" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label required">Ngày thanh toán</label>
                                    <input type="date" name="paydate" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label required">Cách tính giá khi bắt đầu sử dụng</label>
                                    <select name="start_price_type" class="form-select" required>
                                        <option value="">Chọn cách tính</option>
                                        <option value="full">Cả tháng</option>
                                        <option value="half">Nửa tháng</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label required">Cách tính giá khi hủy</label>
                                    <select name="cancel_price_type" class="form-select" required>
                                        <option value="">Chọn cách tính</option>
                                        <option value="full">Cả tháng</option>
                                        <option value="half">Nửa tháng</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label required">Áp dụng từ</label>
                                    <input type="date" name="apply_from" class="form-control" 
                                           min="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="btn-container">
                        <button type="submit" name="submit" class="btn-submit">Thêm mới</button>
                        <a href="service_list.php" class="btn-cancel">Hủy bỏ</a>
                    </div>
                </form>
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
    });
    </script>
</body>
</html>