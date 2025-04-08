<?php
include 'database/DBController.php';
session_start();

if (isset($_SESSION['admin_id'])) {
    header('Location: admin/account/acount.php'); // Chuyển đến trang quản trị
}

if (isset($_POST['submit'])) { // Xử lý khi người dùng nhấn nút "submit"
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = mysqli_real_escape_string($conn, md5($_POST['password'])); // Mã hóa mật khẩu bằng md5

    // Truy vấn kiểm tra thông tin đăng nhập
    $query = "SELECT * FROM `users` WHERE email = '$email' AND password = '$password'";
    $result = mysqli_query($conn, $query) or die('Query failed');

    // Kiểm tra kết quả truy vấn
    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        $_SESSION['admin_name'] = $user['UserName'];
        $_SESSION['admin_email'] = $user['Email'];
        $_SESSION['admin_id'] = $user['UserId'];
        
        // Kiểm tra role dựa vào ResidentID
        if ($user['ResidentID'] !== NULL) {
            $_SESSION['admin_role'] = 'Cư dân'; // Nếu có ResidentID thì là cư dân
        } else {
            $_SESSION['admin_role'] = $user['Position']; // Nếu không thì lấy theo Position
        }
        
        header('Location: admin/account/acount.php'); // Chuyển đến trang quản trị
        exit();
    } else {
        $message = 'Tên tài khoản hoặc mật khẩu không chính xác!';
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng nhập</title>

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

                <div class="login-header">
                    <img width="100px" src="./assets/logo.png" alt="logo" class="logo-img">
                    <div class="title-login">Đăng Nhập</div>
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
                        <div class="mb-4">
                            <div class="input-group">
                                <span class="input-group-text bg-transparent border-end-0">
                                    <i class="fas fa-lock text-muted"></i>
                                </span>
                                <input type="password" name="password" class="form-control border-start-0" 
                                       placeholder="Mật khẩu" required>
                            </div>
                        </div>
                        <button type="submit" name="submit" class="btn btn-login btn-success w-100">
                            Đăng Nhập
                        </button>
                    </form>
                    <div class="register-link">
                        <a href="forget-password.php">Quên mật khẩu</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>