<?php
$user_id = $_SESSION['user_id'] ?? 0;

// Xử lý khi nhấn nút Hoàn thành
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_order'])) {
    $order_id = $_POST['order_id'];
    $status = 3; // Trạng thái "Hoàn thành"

    // Cập nhật trạng thái đơn hàng trong bảng orders
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->bind_param("ii", $status, $order_id);
    if ($stmt->execute()) {
        $message[] = 'Cập nhật trạng thái thành công!';
    } else {
        $message[] = 'Lỗi khi cập nhật trạng thái!';
    }
}

// Xử lý khi nhấn nút Hủy đơn hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $order_id = $_POST['order_id'];
    $status = 4; // Trạng thái "Đã hủy"

    // Cập nhật trạng thái đơn hàng trong bảng orders
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->bind_param("ii", $status, $order_id);
    if ($stmt->execute()) {
        $message[] = 'Hủy đơn hàng thành công!';
    } else {
        $message[] = 'Lỗi khi hủy đơn hàng!';
    }
}
// Lấy danh sách đơn hàng
$orders = [];
$result = $conn->query("
    SELECT * FROM orders WHERE user_id = $user_id ORDER BY id DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách Đơn Hàng</title>
    <style>
        .card {
            min-height: 399px !important;
        }
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .card-title {
            margin-bottom: 0 !important;
        }
    </style>
</head>
<body>
    <div class="container my-5">
        <h1 class="text-center mb-4">Danh sách Đơn Hàng Của Bạn</h1>
        <div class="row">
            <?php foreach ($orders as $order): ?>
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="order-header">
                                <h5 class="card-title">Đơn hàng #<?php echo $order['id']; ?></h5>
                                <a href="export_order.php?order_id=<?php echo $order['id']; ?>" class="btn btn-success btn-sm">Xuất PDF</a>
                            </div>
                            <p class="card-text">Tên người nhận: <?php echo $order['name']; ?></p>
                            <p class="card-text">Email: <?php echo $order['email']; ?></p>
                            <p class="card-text">Địa chỉ giao hàng: <?php echo $order['address']; ?></p>
                            <p class="card-text">Phương thức thanh toán: <?php echo $order['method']; ?></p>
                            <p class="card-text">Tổng tiền: <?php echo number_format($order['total_price'], 0, ',', '.'); ?> đ</p>
                            <p class="card-text">
                            <?php
                                    // Xác định màu sắc cho mỗi trạng thái đơn hàng
                                    $statusColor = '';
                                    switch ($order['status']) {
                                        case 0:
                                            $statusColor = '#ffc107';
                                            break;
                                        case 1:
                                            $statusColor = 'blue';
                                            break;
                                        case 2:
                                            $statusColor = 'orange';
                                            break;
                                        case 3:
                                            $statusColor = 'green';
                                            break;
                                        case 4:
                                            $statusColor = 'red';
                                            break;
                                    }
                                    ?>
                                    <strong style="color: <?php echo $statusColor; ?>">
                                    <?php
                                        switch ($order['status']) {
                                            case 0:
                                                echo "Chờ xác nhận";
                                                break;
                                            case 1:
                                                echo "Đã xác nhận";
                                                break;
                                            case 2:
                                                echo "Đang vận chuyển";
                                                break;
                                            case 3:
                                                echo "Hoàn thành";
                                                break;
                                            case 4:
                                                echo "Đã hủy";
                                                break;
                                        }
                                    ?>
                                </strong>
                            </p>

                            <?php if ($order['status'] == 0): ?>
                                <!-- Nút hoàn thành khi trạng thái là "Đang vận chuyển" -->
                                <form method="POST" style="margin-bottom: 15px;">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <button type="submit" name="cancel_order" class="btn btn-danger">Hủy đơn hàng</button>
                                </form>
                            <?php endif; ?>

                            <?php if ($order['status'] == 2): ?>
                                <!-- Nút hoàn thành khi trạng thái là "Đang vận chuyển" -->
                                <form method="POST">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <button type="submit" name="complete_order" class="btn btn-success">Hoàn thành</button>
                                </form>
                            <?php endif; ?>

                            <a href="order_detail.php?order_id=<?php echo $order['id']; ?>" class="btn btn-primary mt-2">Xem Chi tiết</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
