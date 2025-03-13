<?php
include '../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location:../login.php');
    exit();
}

// Cập nhật trạng thái đơn hàng khi gửi form
if (isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['status'];

    // Kiểm tra nếu trạng thái mới khác với trạng thái hiện tại
    if ($new_status !== "") {
        $update_status_query = mysqli_query($conn, "UPDATE `orders` SET status = '$new_status' WHERE id = '$order_id'") or die('Query failed');

        if ($new_status == 1) {
            // Lấy danh sách sản phẩm từ order_details
            $order_details_query = mysqli_query($conn, "SELECT * FROM `order_details` WHERE order_id = '$order_id'") or die('Query failed');
            while ($order_detail = mysqli_fetch_assoc($order_details_query)) {
                $item_id = $order_detail['item_id'];
                $quantity = $order_detail['quantity'];

                // Trừ số lượng sản phẩm trong bảng products
                $product_query = mysqli_query($conn, "SELECT item_quantity FROM `products` WHERE item_id = '$item_id'") or die('Query failed');
                $product = mysqli_fetch_assoc($product_query);

                if ($product && $product['item_quantity'] >= $quantity) {
                    $new_quantity = $product['item_quantity'] - $quantity;
                    mysqli_query($conn, "UPDATE `products` SET item_quantity = '$new_quantity' WHERE item_id = '$item_id'") or die('Query failed');
                } else {
                    $message[] = "Không đủ số lượng sản phẩm: ID $item_id để trừ!";
                }
            }
        }

        if ($update_status_query) {
            $message[] = 'Cập nhật trạng thái đơn hàng thành công!';
        } else {
            $message[] = 'Cập nhật trạng thái đơn hàng thất bại!';
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Đơn hàng</title>

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
            // Hiển thị thông báo sau khi thao tác
            if (isset($message)) {
                foreach ($message as $msg) {
                    echo '
                    <div class=" alert alert-info alert-dismissible fade show" role="alert">
                        <span style="font-size: 16px;">' . $msg . '</span>
                        <i style="font-size: 20px; cursor: pointer" class="fas fa-times" onclick="this.parentElement.remove();"></i>
                    </div>';
                }
            }
            ?>
            <div class="bg-primary text-white text-center py-2 mb-4 shadow">
                <h1 class="mb-0">Quản lý Đơn hàng</h1>
            </div>
            <section class="show-orders">
                <div class="container">
                    <h1 class="text-center">Danh sách Đơn hàng</h1>
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tên khách hàng</th>
                                <th>Số điện thoại </th> 
                                <th>Email</th>
                                <th>Địa chỉ</th>
                                <th>Phương thức thanh toán</th>
                                <th>Tổng tiền</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $select_orders = mysqli_query($conn, "SELECT * FROM `orders` ORDER BY id DESC") or die('Query failed');
                            if (mysqli_num_rows($select_orders) > 0) {
                                while ($order = mysqli_fetch_assoc($select_orders)) {
                            ?>
                                    <tr>
                                        <td><?php echo $order['id']; ?></td>
                                        <td><?php echo $order['name']; ?></td>
                                        <td><?php echo $order['phone']; ?></td>
                                        <td><?php echo $order['email']; ?></td>
                                        <td><?php echo $order['address']; ?></td>
                                        <td><?php echo $order['method']; ?></td>
                                        <td><?php echo number_format($order['total_price'], 0, ',', '.'); ?> VND</td>
                                        <td>
                                            <?php if ($order['status'] == 4) {
                                                echo '<span style="color: red;"> Đã hủy</span>';
                                            } else { ?>
                                            <form action="admin_orders.php" method="POST">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <select name="status" class="form-select">
                                                    <?php
                                                    // Tạo danh sách trạng thái dựa trên giá trị hiện tại của order['status']
                                                    $status = $order['status'];
                                                    if ($status == 0) {
                                                        echo '
                                                        <option value="0" selected>Chờ xác nhận</option>
                                                        <option value="1">Xác nhận</option>
                                                        <option value="2">Vận chuyển</option>
                                                        <option value="3">Hoàn thành</option>
                                                        <option value="4">Hủy</option>';
                                                    } elseif ($status == 1) {
                                                        echo '
                                                        <option value="1" selected> Đã xác nhận</option>
                                                        <option value="2">Vận chuyển</option>
                                                        <option value="3">Hoàn thành</option>';
                                                    } elseif ($status == 2) {
                                                        echo '<option value="2" selected>Đang vận chuyển</option>';
                                                        echo '<option value="3">Hoàn thành</option>';
                                                    }
                                                    ?>
                                                </select>
                                                <input type="submit" name="update_status" value="Cập nhật" class="btn btn-success btn-sm mt-2" style="width: 100%;">
                                            </form>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#orderModal<?php echo $order['id']; ?>">
                                                Xem chi tiết
                                            </button>

                                            <!-- Modal -->
                                            <div class="modal fade" id="orderModal<?php echo $order['id']; ?>" tabindex="-1" aria-labelledby="orderModalLabel<?php echo $order['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="orderModalLabel<?php echo $order['id']; ?>">Chi tiết đơn hàng #<?php echo $order['id']; ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="row mb-3">
                                                                <div class="col-md-6">
                                                                    <p><strong>Tên khách hàng:</strong> <?php echo $order['name']; ?></p>
                                                                    <p><strong>Email:</strong> <?php echo $order['email']; ?></p>
                                                                    <p><strong>Số điện thoại:</strong> <?php echo $order['phone']; ?></p>
                                                                    <p><strong>Địa chỉ:</strong> <?php echo $order['address']; ?></p>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <p><strong>Phương thức thanh toán:</strong> <?php echo $order['method']; ?></p>
                                                                    <p><strong>Tổng tiền:</strong> <?php echo number_format($order['total_price'], 0, ',', '.'); ?> VND</p>
                                                                </div>
                                                            </div>
                                                            <h6 class="mb-3">Danh sách sản phẩm:</h6>
                                                            <table class="table table-bordered">
                                                                <thead>
                                                                    <tr>
                                                                        <th>Sản phẩm</th>
                                                                        <th>Số lượng</th>
                                                                        <th>Đơn giá</th>
                                                                        <th>Thành tiền</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php
                                                                    $order_id = $order['id'];
                                                                    $select_products = mysqli_query($conn, "SELECT od.*, p.item_name, p.item_price FROM `order_details` od 
                                                                        JOIN `products` p ON od.item_id = p.item_id 
                                                                        WHERE od.order_id = '$order_id'") or die('Query failed');
                                                                    while ($product = mysqli_fetch_assoc($select_products)) {
                                                                        $subtotal = $product['quantity'] * $product['item_price'];
                                                                    ?>
                                                                        <tr>
                                                                            <td><?php echo $product['item_name']; ?></td>
                                                                            <td><?php echo $product['quantity']; ?></td>
                                                                            <td><?php echo number_format($product['item_price'], 0, ',', '.'); ?> VND</td>
                                                                            <td><?php echo number_format($subtotal, 0, ',', '.'); ?> VND</td>
                                                                        </tr>
                                                                    <?php } ?>
                                                                </tbody>
                                                            </table>
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
                                echo '<tr><td colspan="7" class="text-center">Chưa có đơn hàng nào.</td></tr>';
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
