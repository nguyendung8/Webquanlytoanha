<?php
if(!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/../database/DBController.php';

if(!isset($_SESSION['admin_name'])) {
    header('location: ../index.php');
    exit();
}

$message = '';
$message_type = '';

if(isset($_POST['change_password'])) {
    // 1. Check required fields
    if(empty($_POST['current_password']) || empty($_POST['new_password']) || empty($_POST['confirm_password'])) {
        $message = 'Vui lòng điền đầy đủ thông tin';
        $message_type = 'danger';
    }
    // 2. Check mật khẩu hiện tại
    else {
        $email = $_SESSION['admin_email'];
        $current_password = md5($_POST['current_password']);
        $check_query = "SELECT * FROM users WHERE email = '$email' AND password = '$current_password'";
        $result = mysqli_query($conn, $check_query);
        
        if(mysqli_num_rows($result) == 0) {
            $message = 'Mật khẩu hiện tại không đúng';
            $message_type = 'danger';
        }
        // 3. Check mật khẩu mới và xác nhận
        else if($_POST['new_password'] !== $_POST['confirm_password']) {
            $message = 'Mật khẩu xác nhận không khớp với mật khẩu mới';
            $message_type = 'danger';
        }
        else {
            $new_password_hash = md5($_POST['new_password']);
            $update_query = "UPDATE users SET password = '$new_password_hash' WHERE email = '$email'";
            mysqli_query($conn, $update_query);
            $message = 'Mật khẩu đã được cập nhật thành công';
            $message_type = 'success';
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Đổi mật khẩu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .change-password-form {
            max-width: 400px;
            margin: 0 auto;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 15px;
        }
        .btn-group {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
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

        .manage-container {
            padding: 20px;
        }
        
    </style>
</head>
<body>
<div class="d-flex">
        <?php include './admin_navbar.php'; ?>
        <div style="width: 100%;">
            <?php include './admin_header.php'; ?>
            <div class="manage-container" style="margin-top: 20px;">
                 <!-- Page Header -->
            <div class="page-header">
                <h2 style="font-weight: bold; color: #476a52; margin-bottom: 10px; text-transform: uppercase;">TÀI KHOẢN - PHÂN QUYỀN</h2>
                <div class="breadcrumb">
                    <a href="dashboard.php">Trang chủ</a>
                    <span style="margin: 0 8px;">›</span>
                    <span>Đổi mật khẩu</span>
                </div>
            </div>

                <div class="change-password-form" style="margin-top: 20px;">
                    <h3 class="mb-4">Đổi mật khẩu</h3>
                    
                    <?php if($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <?php echo $message; ?>
                    </div>
                    <?php endif; ?>
        
                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label">Mật khẩu hiện tại</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Mật khẩu mới</label>
                            <input type="password" name="new_password" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Xác nhận mật khẩu mới</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        <div class="btn-group">
                            <button type="submit" name="change_password" class="btn btn-success">Đổi mật khẩu</button>
                        </div>
                    </form>
                </div>
            </div>
    </div>
</body>
</html>