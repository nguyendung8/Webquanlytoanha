<?php
$user_id = $_SESSION['user_id'] ?? 0;

// Lấy sản phẩm từ giỏ hàng
$cartItems = [];
$totalPrice = 0;

$result = $conn->query("SELECT c.*, p.item_name, p.item_price, p.item_image 
                        FROM cart c 
                        JOIN products p ON c.item_id = p.item_id 
                        WHERE c.user_id = $user_id");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $cartItems[] = $row;
        $totalPrice += $row['item_price'] * $row['quantity'];
    }
}

// Xử lý khi nhấn nút Thanh toán
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $method = $_POST['method'];
    $address = $_POST['address'];
    $note = $_POST['note'];

    // Lưu đơn hàng vào bảng orders
    $stmt = $conn->prepare("INSERT INTO orders (user_id, name, email, method, address, note, total_price, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $status = 0; // Đơn hàng mới
    $stmt->bind_param("isssssii", $user_id, $name, $email, $method, $address, $note, $totalPrice, $status);
    if ($stmt->execute()) {
        $order_id = $stmt->insert_id;

        // Lưu chi tiết đơn hàng vào bảng order_details
        foreach ($cartItems as $item) {
            $stmt_detail = $conn->prepare("INSERT INTO order_details (order_id, item_id, quantity, price) 
                                           VALUES (?, ?, ?, ?)");
            $stmt_detail->bind_param("iiid", $order_id, $item['item_id'], $item['quantity'], $item['item_price']);
            $stmt_detail->execute();
        }

        // Xóa sản phẩm khỏi giỏ hàng
        $conn->query("DELETE FROM cart WHERE user_id = $user_id");

        $message[] = 'Đặt hàng thành công!';
        header('Location: cart.php');
    } else {
        $message[] = 'Đặt hàng thất bại!.';
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh toán</title>
</head>
<body>
    <section class="checkout-section">
        <div class="checkout-container">
            <h2 class="font-baloo">Thanh toán</h2>
            <div class="checkout-details">
                <h3 class="font-baloo">Sản phẩm trong giỏ hàng</h3>
                <ul class="cart-items">
                    <?php foreach ($cartItems as $item): ?>
                        <li class="cart-item">
                            <img src="./assets/products/<?php echo $item['item_image']; ?>" alt="Product Image">
                            <div class="item-info">
                                <h5><?php echo $item['item_name']; ?></h5>
                                <p>Số lượng: <?php echo $item['quantity']; ?></p>
                                <p>Giá: <?php echo number_format($item['item_price'], 0, ',', '.'); ?> đ</p>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <h4 class="font-baloo">Tổng tiền: <?php echo number_format($totalPrice, 0, ',', '.'); ?> đ</h4>
            </div>
            <h1 class="text-center">Nhập thông tin mua hàng</h1>
            <form method="POST" class="checkout-form">
                <div class="form-group">
                    <label for="name">Họ và tên</label>
                    <input type="text" name="name" id="name" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" name="email" id="email" required>
                </div>
                <div class="form-group">
                    <label for="method">Phương thức thanh toán</label>
                    <select style="height: 45px;" name="method" id="method" class="form-control" required onchange="handlePaymentMethodChange(this.value)">
                        <option value="COD">Thanh toán khi nhận hàng (COD)</option>
                        <option value="Bank" data-bs-toggle="modal" data-bs-target="#bankTransferModal">Chuyển khoản ngân hàng</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="address">Địa chỉ giao hàng</label>
                    <input type="text" name="address" id="address" required>
                </div>
                <div class="form-group">
                    <label for="note">Ghi chú (nếu có)</label>
                    <textarea name="note" id="note" rows="4"></textarea>
                </div>
                <button type="submit" name="checkout" class="btn btn-primary">Thanh toán</button>
            </form>
        </div>
        <!-- Modal -->
        <div class="modal fade" id="bankTransferModal" tabindex="-1" aria-labelledby="bankTransferModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="bankTransferModalLabel">Chuyển khoản ngân hàng</h5>
                        <!-- <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button> -->
                        </div>
                    <div class="modal-body text-center">
                        <p>Quý khách vui lòng chuyển khoản theo thông tin bên dưới <br>
                            Nội dung: SĐT - Ngày đặt hàng</p>
                        <img src="./assets/qr_ck.jpg" alt="QR Code" class="img-fluid" style="max-width: 250px;">
                        <br>
                        <strong class="">Chú ý: Ghi nội dung đầy đủ để chúng tôi kiểm tra đơn hàng</strong>
                        <p class="mt-3">Tổng tiền: <strong id="modal-total"></strong> đ</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</body>
</html>

<script>
    function handlePaymentMethodChange(value) {
    if (value === "Bank") {
        const totalPrice = <?php echo $totalPrice; ?>; // Lấy tổng tiền từ PHP
        document.getElementById("modal-total").textContent = totalPrice.toLocaleString("vi-VN");

        // Hiển thị modal bằng Bootstrap
        const modal = new bootstrap.Modal(document.getElementById("bankTransferModal"));
        modal.show();
    }
}
</script>
