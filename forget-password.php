<?php
include 'database/DBController.php';
session_start();

if (isset($_POST['submit'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $current_password = mysqli_real_escape_string($conn, md5($_POST['current_password']));
    $new_password = mysqli_real_escape_string($conn, $_POST['new_password']);
    $confirm_password = mysqli_real_escape_string($conn, $_POST['confirm_password']);

    // Kiểm tra email và mật khẩu hiện tại
    $check_query = "SELECT * FROM `users` WHERE email = '$email' AND password = '$current_password'";
    $check_result = mysqli_query($conn, $check_query) or die('Query failed');

    if (mysqli_num_rows($check_result) > 0) {
        // Kiểm tra mật khẩu mới và xác nhận mật khẩu
        if ($new_password === $confirm_password) {
            $hashed_password = md5($new_password);
            $update_query = "UPDATE `users` SET password = '$hashed_password' WHERE email = '$email'";
            
            if (mysqli_query($conn, $update_query)) {
                $success_message = 'Mật khẩu đã được cập nhật thành công!';
            } else {
                $message = 'Có lỗi xảy ra khi cập nhật mật khẩu!';
            }
        } else {
            $message = 'Mật khẩu mới không khớp!';
        }
    } else {
        $message = 'Email hoặc mật khẩu hiện tại không chính xác!';
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quên mật khẩu</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <style>
        body {
            font-family: 'Roboto', sans-serif;
        }

        .container {
            margin: auto;
            display: flex;
            justify-content: center;
        }

        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: url('./assets/login-bg.png') no-repeat center center fixed;
            background-size: cover;
        }

        .form-control {
            margin-bottom: 0px !important;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
            width: 400px;
            backdrop-filter: blur(10px);
            overflow: hidden;
        }

        .login-header {
            color: #000;
            padding: 20px;
            border-radius: 15px 15px 0 0;
            text-align: center;
        }

        .login-header h4 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }

        .login-body {
            padding: 30px;
        }

        .form-control {
            border-radius: 25px;
            padding: 12px 20px;
            border: 1px solid #ddd;
            margin-bottom: 20px;
        }

        .form-control:focus {
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
            border-color: #899F87;
        }

        .btn-login {
            background: #899F87;
            border: none;
            border-radius: 25px;
            padding: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s;
        }

        .btn-login:hover {
            background: #899F87;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .register-link {
            text-align: center;
            margin-top: 20px;
        }

        .register-link a {
            color: #899F87;
            font-weight: 600;
            text-decoration: none;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        .error-message {
            background-color: #FFF5F5;
            color: #DC3545;
            padding: 12px 16px;
            border-radius: 8px 8px 0 0;
            font-size: 14px;
            text-align: center;
            margin-bottom: 0;
            border-bottom: 1px solid #FFE5E5;
        }

        .title-login {
            font-weight: 400;
            font-size: 30px;
            line-height: 140%;
            letter-spacing: 0%;
            text-align: center;
        }

        .success-message {
            background-color: #F0FFF4;
            color: #2F855A;
            padding: 12px 16px;
            border-radius: 8px 8px 0 0;
            font-size: 14px;
            text-align: center;
            margin-bottom: 0;
            border-bottom: 1px solid #C6F6D5;
        }

        .password-requirements {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            padding-left: 45px;
        }

        .back-to-login {
            display: inline-block;
            margin-right: 10px;
            color: #666;
            text-decoration: none;
        }

        .back-to-login:hover {
            color: #899F87;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="container">
            <div class="login-card">
                <?php if (isset($message) && isset($_POST['submit'])) : ?>
                    <div class="error-message">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($success_message)) : ?>
                    <div class="success-message">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <div class="login-header">
                    <img width="100px" src="/webquanlytoanha/assets/logo.png" alt="logo" class="logo-img">
                    <div class="title-login">Đổi Mật Khẩu</div>
                </div>
                <div class="login-body">
                    <form action="" method="post">
                        <div class="mb-3">
                            <div class="input-group">
                                <span class="input-group-text bg-transparent border-end-0">
                                    <i class="fas fa-envelope text-muted"></i>
                                </span>
                                <input type="email" name="email" class="form-control border-start-0" 
                                       placeholder="Email của bạn" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="input-group">
                                <span class="input-group-text bg-transparent border-end-0">
                                    <i class="fas fa-lock text-muted"></i>
                                </span>
                                <input type="password" name="current_password" class="form-control border-start-0" 
                                       placeholder="Mật khẩu hiện tại" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="input-group">
                                <span class="input-group-text bg-transparent border-end-0">
                                    <i class="fas fa-key text-muted"></i>
                                </span>
                                <input type="password" name="new_password" class="form-control border-start-0" 
                                       placeholder="Mật khẩu mới" required>
                            </div>
                            <div class="password-requirements">
                                Mật khẩu phải có ít nhất 6 ký tự
                            </div>
                        </div>
                        <div class="mb-4">
                            <div class="input-group">
                                <span class="input-group-text bg-transparent border-end-0">
                                    <i class="fas fa-key text-muted"></i>
                                </span>
                                <input type="password" name="confirm_password" class="form-control border-start-0" 
                                       placeholder="Xác nhận mật khẩu mới" required>
                            </div>
                        </div>
                        <button type="submit" name="submit" class="btn btn-login btn-success w-100">
                            Đổi Mật Khẩu
                        </button>
                    </form>
                    <div class="register-link">
                        <a href="/webquanlytoanha/index.php" class="back-to-login">
                            <i class="fas fa-arrow-left"></i> Quay lại đăng nhập
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>