<?php
include 'config.php';
session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location:login.php');
    exit();
}

// Xóa người dùng
if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    $delete_query = mysqli_query($conn, "DELETE FROM `users` WHERE user_id = '$delete_id'") or die('Query failed');

    if ($delete_query) {
        $message[] = 'Xóa người dùng thành công!';
    } else {
        $message[] = 'Xóa người dùng thất bại!';
    }
    header('location:admin_users.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý người dùng</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="css/admin_style.css">
    <style>
        th, td {
            text-align: center;
            font-size: 18px;
        }
    </style>
</head>
<body>

<?php include 'admin_header.php'; ?>

<section class="show-users">
    <div class="container mt-5">
        <h1 class="title">Quản lý người dùng</h1>
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tên đăng nhập</th>
                    <th>Email</th>
                    <th>Vai trò</th>
                    <th>Ngày tạo</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $select_users = mysqli_query($conn, "SELECT * FROM `users` WHERE role = 'user' ORDER BY created_at DESC") or die('Query failed');
                if (mysqli_num_rows($select_users) > 0) {
                    while ($user = mysqli_fetch_assoc($select_users)) {
                ?>
                        <tr>
                            <td><?php echo $user['user_id']; ?></td>
                            <td><?php echo $user['username']; ?></td>
                            <td><?php echo $user['email']; ?></td>
                            <td>
                                <?php
                                if ($user['role'] == 'admin') {
                                    echo 'Quản trị viên';
                                } else {
                                    echo 'Người dùng';
                                }
                                ?>
                            </td>
                            <td><?php echo $user['created_at']; ?></td>
                            <td>
                                <a href="admin_users.php?delete=<?php echo $user['user_id']; ?>" class="new-btn btn-danger btn-sm" onclick="return confirm('Bạn có chắc chắn muốn xóa người dùng này?');">
                                    Xóa
                                </a>
                            </td>
                        </tr>
                <?php
                    }
                } else {
                    echo '<tr><td colspan="6" class="text-center">Chưa có người dùng nào.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
