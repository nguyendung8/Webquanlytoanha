<?php
include 'database/DBController.php';

if (isset($_POST['submit'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = mysqli_real_escape_string($conn, md5($_POST['password']));
    $confirm_password = mysqli_real_escape_string($conn, md5($_POST['confirm_password']));

    // Kiểm tra email đã tồn tại chưa
    $select_user = mysqli_query($conn, "SELECT * FROM `users` WHERE email = '$email'") or die('Query failed');

    if (mysqli_num_rows($select_user) > 0) {
        $message[] = 'Email đã tồn tại!';
    } else {
        if ($password != $confirm_password) {
            $message[] = 'Mật khẩu không khớp!';
        } else {
            // Thêm tài khoản vào bảng `users`
            mysqli_query($conn, "INSERT INTO `users` (username, email, password) VALUES('$name', '$email', '$password')") or die('Query failed');
            $message[] = 'Đăng ký thành công!';
            header('location:login.php');
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký</title>

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
                <h4>Đăng ký</h4>
            </div>
            <div class="card-body">
                <!-- Hiển thị thông báo -->
                <?php
                if (isset($message)) {
                    foreach ($message as $msg) {
                        echo '
                        <div class="alert alert-info alert-dismissible fade show" role="alert">
                            <span>' . $msg . '</span>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>';
                    }
                }
                ?>

                <!-- Form đăng ký -->
                <form action="" method="post">
                    <div class="mb-3">
                        <label for="name" class="form-label">Họ tên</label>
                        <input type="text" id="name" name="name" class="form-control" placeholder="Nhập họ tên" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="Nhập email" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Mật khẩu</label>
                        <input type="password" id="password" name="password" class="form-control" placeholder="Nhập mật khẩu" required>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Nhập lại mật khẩu</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Nhập lại mật khẩu" required>
                    </div>
                    <button type="submit" name="submit" class="btn btn-primary w-100">Đăng ký ngay</button>
                </form>
                <p class="text-center mt-3">
                    Bạn đã có tài khoản?
                    <a href="login.php" class="text-primary text-decoration-none">Đăng nhập</a>
                </p>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>