<?php
include '../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location:../login.php');
    exit();
}

// Xóa tin nhắn
if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];

    // Xóa tin nhắn
    $delete_query = mysqli_query($conn, "DELETE FROM `message` WHERE id = '$delete_id'") or die('Query failed');

    if ($delete_query) {
        $message[] = 'Xóa tin nhắn thành công!';
    } else {
        $message[] = 'Xóa tin nhắn thất bại!';
    }

    header('location:admin_messages.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Tin nhắn</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="css/admin_style.css">
</head>

<body>
    <div class="d-flex">
        <?php include 'admin_navbar.php'; ?>
        <div class="manage-container">
            <?php
            // Hiển thị thông báo
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
            <div class="bg-primary text-white text-center py-2 mb-4 shadow">
                <h1 class="mb-0">Quản Lý Tin Nhắn</h1>
            </div>
            <section class="show-messages">
                <div class="container">
                    <h1 class="text-center">Danh sách tin nhắn</h1>
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Người gửi</th>
                                <th>Email</th>
                                <th>Tin nhắn</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Lấy danh sách tin nhắn và kết hợp với tên và email người gửi
                            $select_messages = mysqli_query($conn, "
                                SELECT message.id, message.user_id, message.message, users.username, users.email 
                                FROM `message`
                                JOIN `users` ON message.user_id = users.user_id
                                ORDER BY message.id DESC
                            ") or die('Query failed');
                            
                            if (mysqli_num_rows($select_messages) > 0) {
                                while ($msg = mysqli_fetch_assoc($select_messages)) {
                            ?>
                                    <tr>
                                        <td><?php echo $msg['id']; ?></td>
                                        <td><?php echo $msg['username']; ?></td>
                                        <td><?php echo $msg['email']; ?></td>
                                        <td><?php echo $msg['message']; ?></td>
                                        <td>
                                            <a href="admin_messages.php?delete=<?php echo $msg['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Bạn có chắc chắn muốn xóa tin nhắn này?');">Xóa</a>
                                        </td>
                                    </tr>
                            <?php
                                }
                            } else {
                                echo '<tr><td colspan="5" class="text-center">Chưa có tin nhắn nào.</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
