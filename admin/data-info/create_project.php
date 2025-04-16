<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Xử lý khi form được submit
if (isset($_POST['submit'])) {
    $township_id = mysqli_real_escape_string($conn, $_POST['township_id']);
    $project_code = mysqli_real_escape_string($conn, $_POST['project_code']);
    $operation_id = mysqli_real_escape_string($conn, $_POST['operation_id']);
    $project_name = mysqli_real_escape_string($conn, $_POST['project_name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $manager_id = mysqli_real_escape_string($conn, $_POST['manager_id']);
    $deadlock = mysqli_real_escape_string($conn, $_POST['deadlock']);
    
    // Kiểm tra mã dự án đã tồn tại chưa (giả sử ProjectCode là trường không null)
    $check_code = mysqli_query($conn, "SELECT * FROM `Projects` WHERE OperationId = '$operation_id'") or die('Query failed');
    
    if (mysqli_num_rows($check_code) > 0) {
        $message[] = 'Mã dự án đã tồn tại!';
    } else {
        // Thêm dự án mới
        $insert_project = mysqli_query($conn, "INSERT INTO `Projects` (Name, Address, Phone, Email, Deadlock, Description, OperationId, TownShipId, ManagerId) 
                                               VALUES ('$project_name', '$address', '$phone', '$email', '$deadlock', '$description', '$operation_id', '$township_id', '$manager_id')") 
                                        or die('Query failed: ' . mysqli_error($conn));
        
        if ($insert_project) {
            $message[] = 'Thêm dự án thành công!';
            header('location:projects.php');
            exit();
        } else {
            $message[] = 'Thêm dự án thất bại!';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm mới dự án</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="../css/admin_style.css">
    <style>
        body {
            background-color: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .page-header {
            background-color: #f8f9fc;
            padding: 15px 20px;
            color: #4a5568;
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
            font-size: 24px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-label {
            font-weight: 500;
            color: #4a5568;
            margin-bottom: 5px;
            display: block;
        }
        
        .form-label .required {
            color: red;
            margin-left: 3px;
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
            min-width: 100px;
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
            min-width: 100px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-cancel:hover {
            background-color: #c82333;
            color: white;
        }
        
        .manage-container {
            width: 100%;
            padding: 20px;
            background-color: #f8f9fc;
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <?php include '../admin_navbar.php'; ?>
        <div style="width: 100%;">
            <?php include '../admin_header.php'; ?>
            <div class="manage-container">
                <?php
                if (isset($message)) {
                    foreach ($message as $msg) {
                        echo '
                        <div class="alert alert-info alert-dismissible fade show" role="alert">
                            <span style="font-size: 16px;">' . $msg . '</span>
                            <i style="font-size: 20px; cursor: pointer" class="fas fa-times" onclick="this.parentElement.remove();"></i>
                        </div>';
                    }
                }
                ?>
                
                <div class="page-header">
                    <h2 class="form-title">THÊM MỚI DỰ ÁN</h2>
                    <div class="breadcrumb">
                        <a href="/webquanlytoanha/admin/dashboard.php">Trang chủ</a>
                        <span style="margin: 0 8px;">›</span>
                        <a href="/webquanlytoanha/admin/data-info/companies.php">Thông tin công ty</a>
                        <span style="margin: 0 8px;">›</span>
                        <a href="/webquanlytoanha/admin/data-info/projects.php">Danh mục dự án</a>
                        <span style="margin: 0 8px;">›</span>
                        <span>Thêm mới dự án</span>
                    </div>
                </div>
                
                <div class="create-form">
                    <form action="" method="post">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Khu đô thị <span class="required">*</span></label>
                                <select name="township_id" class="form-select" required>
                                    <option value="">Chọn khu đô thị</option>
                                    <?php
                                    $select_townships = mysqli_query($conn, "SELECT * FROM `Townships` ORDER BY Name");
                                    while($township = mysqli_fetch_assoc($select_townships)) {
                                        echo "<option value='{$township['TownShipId']}'>{$township['Name']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Mã dự án <span class="required">*</span></label>
                                <input type="text" name="project_code" class="form-control" placeholder="Mã dự án" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Mã ban vận hành <span class="required">*</span></label>
                                <input type="text" name="operation_id" class="form-control" placeholder="Mã ban vận hành" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Tên dự án <span class="required">*</span></label>
                                <input type="text" name="project_name" class="form-control" placeholder="Tên dự án" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Mô tả</label>
                                <textarea name="description" class="form-control" placeholder="Mô tả dự án"></textarea>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Địa chỉ <span class="required">*</span></label>
                                <input type="text" name="address" class="form-control" placeholder="Địa chỉ dự án" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Số điện thoại <span class="required">*</span></label>
                                <input type="tel" name="phone" class="form-control" placeholder="Số điện thoại" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email dự án <span class="required">*</span></label>
                                <input type="email" name="email" class="form-control" placeholder="Email" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Trưởng ban quản lý</label>
                                <select name="manager_id" class="form-select">
                                    <option value="">Chọn trưởng ban quản lý</option>
                                    <?php
                                    $select_managers = mysqli_query($conn, "SELECT * FROM `Staffs` WHERE Position LIKE '%Trưởng BQL%' OR Position LIKE '%Trưởng ban%' ORDER BY Name");
                                    while($manager = mysqli_fetch_assoc($select_managers)) {
                                        echo "<option value='{$manager['ID']}'>{$manager['Name']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Ngày khóa sổ kế toán <span class="required">*</span></label>
                                <input type="date" name="deadlock" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="btn-container">
                            <button type="submit" name="submit" class="btn-submit">Lưu</button>
                            <a href="projects.php" class="btn-cancel">Hủy</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>