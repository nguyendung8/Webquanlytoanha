<?php
include 'database/DBController.php';
session_start();

if (isset($_POST['submit'])) { // Xử lý khi người dùng nhấn nút "submit"
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = mysqli_real_escape_string($conn, md5($_POST['password'])); // Mã hóa mật khẩu bằng md5

    // Truy vấn kiểm tra thông tin đăng nhập
    $query = "SELECT * FROM `users` WHERE email = '$email' AND password = '$password'";
    $result = mysqli_query($conn, $query) or die('Query failed');

    // Kiểm tra kết quả truy vấn
    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        if ($user['status'] == 0) {
            $message[] = 'Tài khoản của bạn đã bị khóa!';
        } else {
            if ($user['role'] == 'admin') {
                // Nếu là quản trị viên
                $_SESSION['admin_name'] = $user['username'];
                $_SESSION['admin_id'] = @$user['user_id'];
                header('Location: admin/admin_products.php'); // Chuyển đến trang quản trị
                exit();
            } elseif ($user['role'] == 'user') {
                // Nếu là user
                $_SESSION['user_name'] = $user['username'];
                $_SESSION['user_id'] = $user['user_id'];
                header('Location: index.php');
                exit();
            } else {
                $message[] = 'Tài khoản của bạn không có quyền truy cập!';
            }
        }
    } else {
        $message[] = 'Tên tài khoản hoặc mật khẩu không chính xác!';
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng nhập</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="./admin/admin_style.css">
</head>

<body class="background">
    <?php
        //nhúng vào các trang bán hàng
        if (isset($message)) { // hiển thị thông báo sau khi thao tác với biến message được gán giá trị
            foreach ($message as $msg) {
                echo '
                        <div class=" alert alert-info alert-dismissible fade show" role="alert">
                            <span style="font-size: 16px;">' . $msg . '</span>
                            <i style="font-size: 20px; cursor: pointer" class="fas fa-times" onclick="this.parentElement.remove();"></i>
                        </div>';
            }
        }
    ?>
    <div class="container d-flex justify-content-center align-items-center vh-100">
        <div class="card shadow" style="width: 400px; border-radius: 15px;">
            <div class="card-header text-center bg-primary text-white" style="border-radius: 15px 15px 0 0;">
                <h4>Đăng nhập</h4>
            </div>
            <div class="card-body">
                <form action="" method="post">
                    <div class="mb-3">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="Nhập E-mail" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Mật khẩu</label>
                        <input type="password" id="password" name="password" class="form-control" placeholder="Nhập mật khẩu" required>
                    </div>
                    <input type="submit" name="submit" class="btn btn-primary w-100" value="Đăng nhập">
                </form>
                <p class="text-center mt-3">
                    Bạn chưa có tài khoản?
                    <a href="./register.php" class="text-primary text-decoration-none">Đăng ký ngay</a>
                </p>
            </div>
        </div>
    </div>
</body>

</html>