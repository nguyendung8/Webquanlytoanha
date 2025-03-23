<?php
include 'database/DBController.php';
session_start();

$user_id = @$_SESSION['user_id'] ?? null;
$field_id = $_GET['field_id'] ?? null;

if (!$user_id) {
    header('Location: login.php');
    exit();
}

if (!$field_id) {
    header('Location: index.php');
    exit();
}

// Lấy thông tin sân bóng
$field_query = mysqli_query($conn, "SELECT * FROM football_fields WHERE id = '$field_id'") or die('Query failed');
$field = mysqli_fetch_assoc($field_query);

// Xử lý đặt sân
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_booking'])) {
    $booking_date = $_POST['booking_date'];
    $start_time = $_POST['start_time'];
    $duration = floatval($_POST['duration']);
    $rent_ball = isset($_POST['rent_ball']) ? 1 : 0;
    $rent_uniform = isset($_POST['rent_uniform']) ? 1 : 0;
    $payment_method = $_POST['payment_method'];
    
    // Tính các loại giá
    $field_price = $field['rental_price'] * $duration;
    $total_price = $field_price;
    if ($rent_ball) $total_price += 100000;
    if ($rent_uniform) $total_price += 100000;
    
    // Tính tiền đặt cọc (50% tổng tiền)
    $deposit_amount = $total_price * 0.5;
    
    // Lưu thông tin đặt sân tạm thời vào session
    $_SESSION['temp_booking'] = [
        'field_id' => $field_id,
        'booking_date' => $booking_date,
        'start_time' => $start_time,
        'duration' => $duration,
        'rent_ball' => $rent_ball,
        'rent_uniform' => $rent_uniform,
        'field_price' => $field_price,
        'total_price' => $total_price,
        'deposit_amount' => $deposit_amount,
        'note' => $_POST['note']
    ];

    // Chuyển hướng đến trang thanh toán tương ứng
    if ($payment_method == 'momo') {
        $_POST['deposit_amount'] = $deposit_amount;
        $_POST['booking_data'] = json_encode($_SESSION['temp_booking']);
        include 'payment/momo_payment.php';
    } else if ($payment_method == 'vnpay') {
        $_POST['deposit_amount'] = $deposit_amount;
        include 'payment/vnpay_payment.php';
    }
}
?>

<?php include 'header.php'; ?>
<!-- Trong phần head -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<div class="booking-container py-5">
    <div class="container">
        <div class="row">
            <!-- Thông tin sân -->
            <div class="col-lg-4 mb-4">
                <div class="field-info-card">
                    <img src="assets/fields/<?php echo $field['image']; ?>" alt="<?php echo $field['name']; ?>" class="img-fluid mb-3">
                    <h3><?php echo $field['name']; ?></h3>
                    <div class="field-details">
                        <p><i class="fas fa-futbol"></i> Sân <?php echo $field['field_type']; ?> người</p>
                        <p><i class="fas fa-map-marker-alt"></i> <?php echo $field['address']; ?></p>
                        <p><i class="fas fa-phone"></i> <?php echo $field['phone_number']; ?></p>
                        <p class="price"><i class="fas fa-money-bill"></i> 
                            <?php echo number_format($field['rental_price'], 0, ',', '.'); ?> đ/giờ
                        </p>
                    </div>
                </div>
            </div>

            <!-- Form đặt sân -->
            <div class="col-lg-8">
                <div class="booking-form-card">
                    <h2 class="form-title">Đặt Sân</h2>
                    <form method="POST" id="bookingForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Ngày đặt sân</label>
                                <input type="date" name="booking_date" class="form-control" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Giờ bắt đầu</label>
                                <input type="time" name="start_time" class="form-control" 
                                       min="06:00" max="22:00" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Thời gian thuê (giờ)</label>
                                <select style="padding: 6px 12px;" name="duration" class="form-control" required>
                                    <option value="1">1 giờ</option>
                                    <option value="1.5">1.5 giờ</option>
                                    <option value="2">2 giờ</option>
                                    <option value="2.5">2.5 giờ</option>
                                    <option value="3">3 giờ</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Dịch vụ thêm</label>
                                <div class="additional-services">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rent_ball" id="rentBall" value="1">
                                        <label class="form-check-label" for="rentBall">
                                            Thuê bóng (+100.000đ)
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rent_uniform" id="rentUniform" value="1">
                                        <label class="form-check-label" for="rentUniform">
                                            Thuê áo pitch (+100.000đ)
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 mb-3">
                                <div class="price-summary">
                                    <div class="price-item">
                                        <span>Tiền sân:</span>
                                        <span id="fieldPrice">0 đ</span>
                                    </div>
                                    <div class="price-item" id="ballPriceRow" style="display: none;">
                                        <span>Thuê bóng:</span>
                                        <span>100.000 đ</span>
                                    </div>
                                    <div class="price-item" id="uniformPriceRow" style="display: none;">
                                        <span>Thuê áo pitch:</span>
                                        <span>100.000 đ</span>
                                    </div>
                                    <div class="price-item total">
                                        <span>Tổng tiền:</span>
                                        <span id="totalPrice">0 đ</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label>Ghi chú</label>
                            <textarea name="note" class="form-control" rows="3" 
                                    placeholder="Nhập ghi chú nếu có..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label>Phương thức thanh toán</label>
                            <div class="payment-methods">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" 
                                           id="momo" value="momo" required>
                                    <label class="form-check-label payment-label" for="momo">
                                        <img src="assets/momo.png" alt="MoMo">
                                        MOMO
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" 
                                           id="vnpay" value="vnpay" required>
                                    <label class="form-check-label payment-label" for="vnpay">
                                        <img src="assets/vnpay.png" alt="VNPay">
                                        VNPay
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" 
                                           id="bank" value="bank" required>
                                    <label class="form-check-label payment-label" for="bank">
                                        Chuyển khoản ngân hàng
                                    </label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" name="submit_booking" class="btn btn-booking mt-4">
                            Đặt sân
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Thêm modal thanh toán -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Thanh toán đặt cọc</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="payment-info">
                    <div class="amount-info mb-4">
                        <h6>Thông tin thanh toán:</h6>
                        <p>Tổng tiền: <span id="modalTotalPrice">0 đ</span></p>
                        <p>Số tiền đặt cọc (50%): <span id="modalDepositAmount">0 đ</span></p>
                    </div>

                    <div id="momoPayment" style="display: none;">
                        <h6>Quét mã QR để thanh toán MOMO</h6>
                        <div class="text-center">
                            <img src="assets/images/momo-qr.png" alt="MOMO QR" style="width: 200px;">
                        </div>
                    </div>

                    <div id="vnpayPayment" style="display: none;">
                        <h6>Quét mã QR để thanh toán VNPay</h6>
                        <div class="text-center">
                            <img src="assets/images/vnpay-qr.png" alt="VNPay QR" style="width: 200px;">
                        </div>
                    </div>

                    <div id="bankPayment" style="display: none;">
                        <h6>Thông tin chuyển khoản:</h6>
                        <div class="bank-details">
                            <p><strong>Ngân hàng:</strong> Vietcombank</p>
                            <p><strong>Số tài khoản:</strong> 1234567890</p>
                            <p><strong>Chủ tài khoản:</strong> NGUYEN VAN A</p>
                            <p><strong>Nội dung CK:</strong> <span id="transferContent"></span></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-success" onclick="simulatePayment()">
                    Đã thanh toán
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.booking-container {
    background: #f8f9fa;
    min-height: 100vh;
}

.field-info-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 0 20px rgba(0,0,0,0.1);
}

.field-info-card img {
    width: 100%;
    height: 200px;
    object-fit: cover;
    border-radius: 10px;
}

.field-info-card h3 {
    color: #1B4D3E;
    margin: 15px 0;
}

.field-details p {
    margin-bottom: 10px;
    color: #666;
}

.field-details i {
    width: 25px;
    color: #28a745;
}

.price {
    font-size: 18px;
    color: #28a745;
    font-weight: 600;
}

.booking-form-card {
    background: white;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 0 20px rgba(0,0,0,0.1);
}

.form-title {
    color: #1B4D3E;
    margin-bottom: 25px;
    padding-bottom: 10px;
    border-bottom: 2px solid #28a745;
}

.form-control {
    border-radius: 8px;
    padding: 12px;
    border: 1px solid #ddd;
}

.form-control:focus {
    border-color: #28a745;
    box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
}

.total-price {
    background: #f8f9fa;
    padding: 12px;
    border-radius: 8px;
    font-size: 18px;
    font-weight: 600;
    color: #28a745;
}

.btn-booking {
    background: #28a745;
    color: white;
    padding: 12px 30px;
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.3s;
}

.btn-booking:hover {
    background: #218838;
    transform: translateY(-2px);
}

label {
    color: #666;
    margin-bottom: 8px;
}

.additional-services {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
}

.form-check {
    margin-bottom: 10px;
}

.form-check:last-child {
    margin-bottom: 0;
}

.form-check-input {
    cursor: pointer;
}

.form-check-label {
    cursor: pointer;
    color: #666;
}

.price-summary {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
}

.price-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 1px dashed #ddd;
}

.price-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.price-item.total {
    font-weight: 600;
    color: #28a745;
    font-size: 18px;
    border-top: 2px solid #ddd;
    margin-top: 10px;
    padding-top: 10px;
}

.payment-methods {
    display: flex;
    gap: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
}

.payment-label {
    display: flex;
    align-items: center;
    gap: 10px;
}

.payment-label img {
    height: 30px;
    width: auto;
}

.bank-details {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-top: 10px;
}

.bank-details p {
    margin-bottom: 8px;
}

.amount-info {
    background: #e9ecef;
    padding: 15px;
    border-radius: 8px;
}

.amount-info p {
    margin-bottom: 5px;
    font-size: 16px;
}

.amount-info p:last-child {
    color: #28a745;
    font-weight: bold;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('bookingForm');
    const durationSelect = form.querySelector('[name="duration"]');
    const rentBallCheckbox = document.getElementById('rentBall');
    const rentUniformCheckbox = document.getElementById('rentUniform');
    const fieldPriceSpan = document.getElementById('fieldPrice');
    const totalPriceSpan = document.getElementById('totalPrice');
    const ballPriceRow = document.getElementById('ballPriceRow');
    const uniformPriceRow = document.getElementById('uniformPriceRow');
    const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
    
    const pricePerHour = <?php echo $field['rental_price']; ?>;
    const ballPrice = 100000;
    const uniformPrice = 100000;

    function updateTotalPrice() {
        const duration = parseFloat(durationSelect.value);
        const fieldPrice = pricePerHour * duration;
        let total = fieldPrice;

        // Hiển thị tiền sân
        fieldPriceSpan.textContent = fieldPrice.toLocaleString('vi-VN') + ' đ';

        // Tính thêm tiền thuê bóng
        if (rentBallCheckbox.checked) {
            total += ballPrice;
            ballPriceRow.style.display = 'flex';
        } else {
            ballPriceRow.style.display = 'none';
        }

        // Tính thêm tiền thuê áo
        if (rentUniformCheckbox.checked) {
            total += uniformPrice;
            uniformPriceRow.style.display = 'flex';
        } else {
            uniformPriceRow.style.display = 'none';
        }

        // Hiển thị tổng tiền
        totalPriceSpan.textContent = total.toLocaleString('vi-VN') + ' đ';
    }

    // Gắn sự kiện cho các input
    durationSelect.addEventListener('change', updateTotalPrice);
    rentBallCheckbox.addEventListener('change', updateTotalPrice);
    rentUniformCheckbox.addEventListener('change', updateTotalPrice);

    // Cập nhật giá ban đầu
    updateTotalPrice();

    // Xử lý submit form
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Validate time
        const timeInput = form.querySelector('[name="start_time"]');
        const time = timeInput.value;
        const [hours, minutes] = time.split(':').map(Number);
        
        if (hours < 6 || (hours === 22 && minutes > 0) || hours > 22) {
            alert('Vui lòng chọn giờ đặt sân từ 6:00 đến 22:00');
            return;
        }

        // Lấy phương thức thanh toán
        const paymentMethod = form.querySelector('input[name="payment_method"]:checked').value;
        
        // Cập nhật thông tin trong modal
        const totalPrice = parseInt(document.getElementById('totalPrice').textContent.replace(/[^\d]/g, ''));
        const depositAmount = Math.round(totalPrice * 0.5);
        
        document.getElementById('modalTotalPrice').textContent = totalPrice.toLocaleString('vi-VN') + ' đ';
        document.getElementById('modalDepositAmount').textContent = depositAmount.toLocaleString('vi-VN') + ' đ';
        
        // Hiển thị phương thức thanh toán tương ứng
        document.getElementById('momoPayment').style.display = 'none';
        document.getElementById('vnpayPayment').style.display = 'none';
        document.getElementById('bankPayment').style.display = 'none';
        
        document.getElementById(paymentMethod + 'Payment').style.display = 'block';
        
        // Tạo nội dung chuyển khoản
        const bookingDate = form.querySelector('[name="booking_date"]').value;
        const transferContent = `DC${bookingDate.replace(/-/g, '')}`;
        document.getElementById('transferContent').textContent = transferContent;
        
        // Hiển thị modal thanh toán
        paymentModal.show();
    });
});

// Hàm giả lập thanh toán thành công
function simulatePayment() {
    const form = document.getElementById('bookingForm');
    const formData = new FormData(form);
    
    // Thêm trạng thái thanh toán vào form
    formData.append('payment_status', 'Đã đặt cọc');
    
    // Submit form
    fetch('process_booking.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Đặt sân thành công!');
            window.location.href = 'my-bookings.php';
        } else {
            alert('Có lỗi xảy ra: ' + data.message);
        }
    })
    .catch(error => {
        alert('Có lỗi xảy ra khi xử lý đặt sân');
    });
}
</script>

<?php include 'footer.php'; ?>