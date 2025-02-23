<?php
session_start();
include './database/DBController.php';

// Kiểm tra nếu người dùng chưa đăng nhập
$user_id = $_SESSION['user_id'] ?? null;
if (!isset($user_id)) {
    header('location: ./login.php');
    exit;
}

// Lấy thông tin người dùng hiện tại
$query = mysqli_query($conn, "SELECT * FROM `users` WHERE user_id = $user_id") or die('Query failed');
$user = mysqli_fetch_assoc($query);

// Xử lý cập nhật thông tin
$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $current_password = md5($_POST['current_password']); // Mã hóa MD5 để so sánh
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Kiểm tra mật khẩu hiện tại
    if ($current_password !== $user['password']) {
        $message = "<div class='alert alert-danger'>Mật khẩu hiện tại không đúng!</div>";
    } else {
        // Nếu có mật khẩu mới, kiểm tra và cập nhật
        if (!empty($new_password)) {
            if ($new_password !== $confirm_password) {
                $message = "<div class='alert alert-danger'>Mật khẩu mới và xác nhận không khớp!</div>";
            } else {
                $hashed_password = md5($new_password); // Mã hóa MD5 mật khẩu mới
                $update_query = "UPDATE `users` SET username = '$username', email = '$email', password = '$hashed_password' WHERE user_id = $user_id";

                        // Thực thi truy vấn cập nhật
                if (mysqli_query($conn, $update_query)) {
                    $message = "<div class='alert alert-success'>Cập nhật thông tin thành công!</div>";
                    // Cập nhật lại thông tin sau khi thay đổi
                    $query = mysqli_query($conn, "SELECT * FROM `users` WHERE user_id = $user_id");
                    $user = mysqli_fetch_assoc($query);
                } else {
                    $message = "<div class='alert alert-danger'>Lỗi cập nhật thông tin!</div>";
                }
            }
        } else {
            $update_query = "UPDATE `users` SET username = '$username', email = '$email' WHERE user_id = $user_id";
                    // Thực thi truy vấn cập nhật
            if (mysqli_query($conn, $update_query)) {
                $message = "<div class='alert alert-success'>Cập nhật thông tin thành công!</div>";
                // Cập nhật lại thông tin sau khi thay đổi
                $query = mysqli_query($conn, "SELECT * FROM `users` WHERE user_id = $user_id");
                $user = mysqli_fetch_assoc($query);
            } else {
                $message = "<div class='alert alert-danger'>Lỗi cập nhật thông tin!</div>";
            }
        }

    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thông tin cá nhân</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include('header.php'); ?>

<div class="container mt-5 mb-5">
    <h2 class="text-center">Thông tin cá nhân</h2>
    <p class="text-center text-muted">Cập nhật thông tin tài khoản của bạn</p>

    <div class="row justify-content-center">
        <div class="col-md-6">
            <?= $message ?>
            <form action="" method="post" class="border p-4 shadow-sm rounded">
                <div class="mb-3">
                    <label for="username" class="form-label">Tên người dùng</label>
                    <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                </div>

                <div class="mb-3">
                    <label for="current_password" class="form-label">Mật khẩu hiện tại</label>
                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                </div>

                <div class="mb-3">
                    <label for="new_password" class="form-label">Mật khẩu mới (bỏ trống nếu không đổi)</label>
                    <input type="password" class="form-control" id="new_password" name="new_password">
                </div>

                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Xác nhận mật khẩu mới</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                </div>

                <button type="submit" class="btn btn-primary w-100">Cập nhật</button>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php include('footer.php'); ?>
