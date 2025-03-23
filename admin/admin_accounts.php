<?php
include '../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location:../login.php');
    exit();
}

// Xóa tài khoản người dùng
if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];

    // Kiểm tra xem tài khoản có phải là người dùng hay không (role = 0)
    $check_role_query = mysqli_query($conn, "SELECT role FROM `users` WHERE user_id = '$delete_id'") or die('Query failed');
    $check_role = mysqli_fetch_assoc($check_role_query);

    if ($check_role['role'] == 0) {
        // Xóa tài khoản người dùng
        $delete_query = mysqli_query($conn, "DELETE FROM `users` WHERE user_id = '$delete_id'") or die('Query failed');

        if ($delete_query) {
            $message[] = 'Xóa tài khoản người dùng thành công!';
        } else {
            $message[] = 'Xóa tài khoản người dùng thất bại!';
        }
    } else {
        $message[] = 'Không thể xóa tài khoản này, vì đây là tài khoản quản trị viên!';
    }

    header('location:admin_accounts.php');
    exit();
}

// Khóa tài khoản người dùng
if (isset($_GET['block'])) {
    $block_id = $_GET['block'];

    // Khóa tài khoản người dùng
    $block_query = mysqli_query($conn, "UPDATE `users` SET status = '0' WHERE user_id = '$block_id'") or die('Query failed');

    if ($block_query) {
        $message[] = 'Khóa tài khoản người dùng thành công!';
    } else {
        $message[] = 'Khóa tài khoản người dùng thất bại!';
    }

}

// Mở khóa tài khoản người dùng
if (isset($_GET['un_block'])) {
    $un_block_id = $_GET['un_block'];

    // Mở khóa tài khoản người dùng
    $un_block_query = mysqli_query($conn, "UPDATE `users` SET status = '1' WHERE user_id = '$un_block_id'") or die('Query failed');

    if ($un_block_query) {
        $message[] = 'Mở khóa tài khoản người dùng thành công!';
    } else {
        $message[] = 'Mở khóa tài khoản người dùng thất bại!';
    }

}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý tài khoản</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="css/admin_style.css">
    <style>
        th {
            background-color: #86eb86 !important;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <?php include 'admin_navbar.php'; ?>
        <div class="manage-container">
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
            <div style="background-color: #28a745" class="text-white text-center py-2 mb-4 shadow">
                <h1 class="mb-0">Quản lý Tài khoản</h1>
            </div>
            <section  class="show-users">
                <div class="container">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $select_users = mysqli_query($conn, "SELECT * FROM `users` where role ='user' ORDER BY user_id DESC") or die('Query failed');
                            if (mysqli_num_rows($select_users) > 0) {
                                while ($user = mysqli_fetch_assoc($select_users)) {
                            ?>
                                    <tr>
                                        <td><?php echo $user['user_id']; ?></td>
                                        <td><?php echo $user['username']; ?></td>
                                        <td><?php echo $user['email']; ?></td>
                                        <td>
                                            <?php echo $user['role']; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['status'] == 1) { ?>
                                                <a href="admin_accounts.php?block=<?php echo $user['user_id']; ?>" class="btn btn-warning btn-sm" onclick="return confirm('Bạn có chắc chắn muốn khóa tài khoản này?');">Khóa Tài Khoản</a>
                                            <?php } else { ?>
                                                <a href="admin_accounts.php?un_block=<?php echo $user['user_id']; ?>" class="btn btn-success btn-sm" onclick="return confirm('Bạn có chắc chắn muốn mở khóa tài khoản này?');">Mở Khóa Tài Khoản</a>
                                            <?php } ?>
                                            <a href="admin_accounts.php?delete=<?php echo $user['user_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Bạn có chắc chắn muốn xóa tài khoản này?');">Xóa</a>
                                            
                                            <!-- Thêm nút xem chi tiết -->
                                            <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#userModal<?php echo $user['user_id']; ?>">
                                                Xem chi tiết
                                            </button>

                                            <!-- Modal -->
                                            <div class="modal fade" id="userModal<?php echo $user['user_id']; ?>" tabindex="-1" aria-labelledby="userModalLabel<?php echo $user['user_id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="userModalLabel<?php echo $user['user_id']; ?>">
                                                                Thông tin chi tiết người dùng
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="user-info">
                                                                <p><strong>ID:</strong> <?php echo $user['user_id']; ?></p>
                                                                <p><strong>Tên đăng nhập:</strong> <?php echo $user['username']; ?></p>
                                                                <p><strong>Email:</strong> <?php echo $user['email']; ?></p>
                                                                <p><strong>Trạng thái:</strong> 
                                                                    <?php echo ($user['status'] == 1) ? '<span class="text-success">Đang hoạt động</span>' : '<span class="text-danger">Đã khóa</span>'; ?>
                                                                </p>
                                                                <p><strong>Ngày tạo:</strong> <?php echo $user['created_at']; ?></p>
                                                                
                                                                <!-- Thống kê đơn hàng -->
                                                                <?php
                                                                $user_id = $user['user_id'];
                                                                $orders_query = mysqli_query($conn, "SELECT 
                                                                    COUNT(*) as total_orders,
                                                                    SUM(CASE WHEN status = 3 THEN 1 ELSE 0 END) as completed_orders,
                                                                    SUM(CASE WHEN status = 4 THEN 1 ELSE 0 END) as cancelled_orders,
                                                                    SUM(CASE WHEN status = 3 THEN total_price ELSE 0 END) as total_spent
                                                                    FROM `orders` 
                                                                    WHERE user_id = '$user_id'") or die('Query failed');
                                                                $orders_stats = mysqli_fetch_assoc($orders_query);
                                                                ?>
                                                                <hr>
                                                                <h6>Thống kê đơn hàng:</h6>
                                                                <p><strong>Tổng số đơn hàng:</strong> <?php echo $orders_stats['total_orders']; ?></p>
                                                                <p><strong>Đơn hàng hoàn thành:</strong> <?php echo $orders_stats['completed_orders']; ?></p>
                                                                <p><strong>Đơn hàng đã hủy:</strong> <?php echo $orders_stats['cancelled_orders']; ?></p>
                                                                <p><strong>Tổng chi tiêu:</strong> <?php echo number_format($orders_stats['total_spent'], 0, ',', '.'); ?> VND</p>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                            <?php
                                }
                            } else {
                                echo '<tr><td colspan="5" class="text-center">Chưa có tài khoản nào.</td></tr>';
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