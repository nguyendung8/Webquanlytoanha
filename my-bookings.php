<?php
include 'database/DBController.php';
session_start();

$user_id = @$_SESSION['user_id'] ?? null;

if (!$user_id) {
    header('Location: login.php');
    exit();
}

// Xử lý hủy đặt sân
if (isset($_GET['cancel']) && isset($_GET['booking_id'])) {
    $booking_id = $_GET['booking_id'];
    
    // Kiểm tra xem booking có phải của user hiện tại không
    $check_booking = mysqli_query($conn, "SELECT * FROM bookings WHERE id = '$booking_id' AND user_id = '$user_id'") or die('Query failed');
    
    if (mysqli_num_rows($check_booking) > 0) {
        $booking = mysqli_fetch_assoc($check_booking);
        $field_id = $booking['field_id'];
        
        // Chỉ cho phép hủy các đơn chờ xác nhận
        if ($booking['status'] == 'Chờ xác nhận') {
            mysqli_query($conn, "UPDATE bookings SET status = 'Đã hủy' WHERE id = '$booking_id'") or die('Query failed');
            $message[] = 'Hủy đặt sân thành công!';
        } else {
            $message[] = 'Không thể hủy đặt sân ở trạng thái này!';
        }
    }
}

// Lấy danh sách đặt sân
$bookings_query = mysqli_query($conn, "
    SELECT b.*, f.name as field_name, f.image as field_image, f.address, f.field_type 
    FROM bookings b 
    JOIN football_fields f ON b.field_id = f.id 
    WHERE b.user_id = '$user_id' 
    ORDER BY b.booking_date DESC, b.start_time DESC
") or die('Query failed');

// Thêm vào đầu file
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_booking'])) {
    $booking_id = $_POST['booking_id'];
    $booking_date = $_POST['booking_date'];
    $start_time = $_POST['start_time'];
    $duration = floatval($_POST['duration']);
    $rent_ball = isset($_POST['rent_ball']) ? 1 : 0;
    $rent_uniform = isset($_POST['rent_uniform']) ? 1 : 0;
    $note = mysqli_real_escape_string($conn, $_POST['note']);

    // Tính thời gian kết thúc
    $start_timestamp = strtotime("$booking_date $start_time");
    $duration_seconds = $duration * 3600;
    $end_timestamp = $start_timestamp + $duration_seconds;
    $end_time = date('H:i', $end_timestamp);

    // Lấy thông tin field_id và giá sân
    $booking_query = mysqli_query($conn, "SELECT b.field_id, f.rental_price 
        FROM bookings b 
        JOIN football_fields f ON b.field_id = f.id 
        WHERE b.id = '$booking_id'") or die('Query failed');
    $booking_info = mysqli_fetch_assoc($booking_query);
    
    // Tính giá mới
    $field_price = $booking_info['rental_price'] * $duration;
    $total_price = $field_price;
    if ($rent_ball) $total_price += 100000;
    if ($rent_uniform) $total_price += 100000;

    // Kiểm tra trùng lịch
    $check_booking = mysqli_query($conn, "SELECT * FROM bookings 
        WHERE field_id = '{$booking_info['field_id']}' 
        AND booking_date = '$booking_date'
        AND id != '$booking_id'
        AND ((start_time <= '$start_time' AND end_time > '$start_time')
        OR (start_time < '$end_time' AND end_time >= '$end_time')
        OR (start_time >= '$start_time' AND end_time <= '$end_time'))
        AND status != 'Đã hủy'") or die('Query failed');

    if (mysqli_num_rows($check_booking) > 0) {
        echo "<script>
            alert('Sân đã được đặt trong khoảng thời gian này!');
            window.location.href = 'my-bookings.php';
        </script>";
        exit();
    } else {
        $update_query = mysqli_query($conn, "UPDATE bookings SET 
            booking_date = '$booking_date',
            start_time = '$start_time',
            end_time = '$end_time',
            duration = '$duration',
            field_price = '$field_price',
            rent_ball = '$rent_ball',
            rent_uniform = '$rent_uniform',
            total_price = '$total_price',
            note = '$note'
            WHERE id = '$booking_id'") or die('Query failed');

        if ($update_query) {
            echo "<script>
                alert('Cập nhật đơn đặt sân thành công!');
                window.location.href = 'my-bookings.php';
            </script>";
            exit();
        } else {
            echo "<script>
                alert('Cập nhật đơn đặt sân thất bại!');
                window.location.href = 'my-bookings.php';
            </script>";
            exit();
        }
    }
}
?>

<?php include 'header.php'; ?>
<!-- Trong header.php -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- // Xử lý hủy đặt sân -->

<div class="my-bookings-container py-5">
    <div class="container">
        <h2 class="page-title">Lịch Sử Đặt Sân</h2>
        
        <?php if(mysqli_num_rows($bookings_query) > 0): ?>
            <div class="row">
                <?php while($booking = mysqli_fetch_assoc($bookings_query)): ?>
                    <div class="col-lg-6 mb-4">
                        <div class="booking-card">
                            <div class="booking-header">
                                <div class="field-info">
                                    <img src="assets/fields/<?php echo $booking['field_image']; ?>" alt="<?php echo $booking['field_name']; ?>">
                                    <div>
                                        <h4><?php echo $booking['field_name']; ?></h4>
                                        <p><i class="fas fa-futbol"></i> Sân <?php echo $booking['field_type']; ?> người</p>
                                        <p><i class="fas fa-map-marker-alt"></i> <?php echo $booking['address']; ?></p>
                                    </div>
                                </div>
                                <div class="booking-status <?php echo strtolower($booking['status']); ?>">
                                    <?php echo $booking['status']; ?>
                                </div>
                            </div>
                            
                            <div class="booking-details">
                                <div class="detail-item">
                                    <i class="fas fa-calendar"></i>
                                    <span>Ngày đặt: <?php echo date('d/m/Y', strtotime($booking['booking_date'])); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-clock"></i>
                                    <span>Thời gian: 
                                        <?php 
                                            $start_time = date('H:i', strtotime($booking['start_time']));
                                            $duration = floatval($booking['duration']); // Chuyển sang float để xử lý 1.5 giờ
                                            
                                            // Tính thời gian kết thúc
                                            $start_timestamp = strtotime($booking['booking_date'] . ' ' . $booking['start_time']);
                                            $duration_seconds = $duration * 3600; // Chuyển giờ sang giây
                                            $end_timestamp = $start_timestamp + $duration_seconds;
                                            $end_time = date('H:i', $end_timestamp);
                                            
                                            echo $start_time . ' - ' . $end_time;
                                        ?>
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-hourglass-half"></i>
                                    <span>Thời lượng: 
                                        <?php 
                                            // Hiển thị thời lượng dạng "1.5 giờ" hoặc "1 giờ"
                                            echo $duration == floor($duration) ? floor($duration) : number_format($duration, 1);
                                        ?> giờ
                                    </span>
                                </div>
                                
                                <div class="services-price">
                                    <div class="price-item">
                                        <span>Tiền sân:</span>
                                        <span><?php echo number_format($booking['field_price'], 0, ',', '.'); ?> đ</span>
                                    </div>
                                    <?php if($booking['rent_ball']): ?>
                                    <div class="price-item">
                                        <span>Thuê bóng:</span>
                                        <span>100.000 đ</span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if($booking['rent_uniform']): ?>
                                    <div class="price-item">
                                        <span>Thuê áo:</span>
                                        <span>100.000 đ</span>
                                    </div>
                                    <?php endif; ?>
                                    <div class="price-item total">
                                        <span>Tổng tiền:</span>
                                        <span><?php echo number_format($booking['total_price'], 0, ',', '.'); ?> đ</span>
                                    </div>
                                </div>

                                <?php if($booking['note']): ?>
                                <div class="booking-note">
                                    <i class="fas fa-sticky-note"></i>
                                    <span><?php echo $booking['note']; ?></span>
                                </div>
                                <?php endif; ?>

                                <div class="booking-actions">
                                    <?php if($booking['status'] == 'Chờ xác nhận'): ?>
                                        <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $booking['id']; ?>">
                                            <i class="fas fa-edit"></i> Chỉnh sửa
                                        </button>
                                        <a href="?cancel=1&booking_id=<?php echo $booking['id']; ?>" 
                                           class="btn btn-danger"
                                           onclick="return confirm('Bạn có chắc chắn muốn hủy đặt sân này?')">
                                            <i class="fas fa-times"></i> Hủy đặt sân
                                        </a>
                                    <?php elseif($booking['status'] == 'Đã hủy'): ?>
                                        <span class="btn btn-secondary disabled">
                                            <i class="fas fa-ban"></i> Đã hủy
                                        </span>
                                    <?php endif; ?>
                                    <a href="field-detail.php?id=<?php echo $booking['field_id']; ?>" 
                                       class="btn btn-info">
                                        <i class="fas fa-info-circle"></i> Xem sân
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Đặt Modal trong cùng vòng lặp với booking card -->
                        <div class="modal fade" id="editModal<?php echo $booking['id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Chỉnh sửa đơn đặt sân</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST" id="editForm<?php echo $booking['id']; ?>">
                                        <div class="modal-body">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label>Ngày đặt sân</label>
                                                    <input type="date" name="booking_date" class="form-control" 
                                                           value="<?php echo $booking['booking_date']; ?>"
                                                           min="<?php echo date('Y-m-d'); ?>" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Giờ bắt đầu</label>
                                                    <input type="time" name="start_time" class="form-control" 
                                                           value="<?php echo $booking['start_time']; ?>"
                                                           min="06:00" max="22:00" required>
                                                </div>
                                            </div>

                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label>Thời gian thuê (giờ)</label>
                                                    <select name="duration" class="form-control" required>
                                                        <?php
                                                        $durations = [1, 1.5, 2, 2.5, 3];
                                                        foreach ($durations as $d) {
                                                            $selected = ($d == $booking['duration']) ? 'selected' : '';
                                                            echo "<option value='$d' $selected>$d giờ</option>";
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Dịch vụ thêm</label>
                                                    <div class="additional-services">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="rent_ball" 
                                                                   id="rentBall<?php echo $booking['id']; ?>" value="1" 
                                                                   <?php echo $booking['rent_ball'] ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="rentBall<?php echo $booking['id']; ?>">
                                                                Thuê bóng (+100.000đ)
                                                            </label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="rent_uniform" 
                                                                   id="rentUniform<?php echo $booking['id']; ?>" value="1"
                                                                   <?php echo $booking['rent_uniform'] ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="rentUniform<?php echo $booking['id']; ?>">
                                                                Thuê áo pitch (+100.000đ)
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="price-summary mb-3">
                                                <div class="price-item">
                                                    <span>Tiền sân:</span>
                                                    <span id="fieldPrice<?php echo $booking['id']; ?>">
                                                        <?php echo number_format($booking['field_price'], 0, ',', '.'); ?> đ
                                                    </span>
                                                </div>
                                                <div class="price-item" id="ballPriceRow<?php echo $booking['id']; ?>" 
                                                     style="display: <?php echo $booking['rent_ball'] ? 'flex' : 'none'; ?>">
                                                    <span>Thuê bóng:</span>
                                                    <span>100.000 đ</span>
                                                </div>
                                                <div class="price-item" id="uniformPriceRow<?php echo $booking['id']; ?>"
                                                     style="display: <?php echo $booking['rent_uniform'] ? 'flex' : 'none'; ?>">
                                                    <span>Thuê áo pitch:</span>
                                                    <span>100.000 đ</span>
                                                </div>
                                                <div class="price-item total">
                                                    <span>Tổng tiền:</span>
                                                    <span id="totalPrice<?php echo $booking['id']; ?>">
                                                        <?php echo number_format($booking['total_price'], 0, ',', '.'); ?> đ
                                                    </span>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label>Ghi chú</label>
                                                <textarea name="note" class="form-control" rows="3"><?php echo $booking['note']; ?></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                                            <button type="submit" name="update_booking" class="btn btn-primary">Cập nhật</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="no-bookings">
                <i class="fas fa-calendar-times"></i>
                <p>Bạn chưa có lịch đặt sân nào</p>
                <a href="index.php" class="btn btn-primary">Đặt sân ngay</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Sửa lại phần script -->
<script>
function initializeEditForm(bookingId, rentalPrice) {
    const form = document.getElementById('editForm' + bookingId);
    if (!form) return; // Kiểm tra form tồn tại

    const durationSelect = form.querySelector('[name="duration"]');
    const rentBallCheckbox = document.getElementById('rentBall' + bookingId);
    const rentUniformCheckbox = document.getElementById('rentUniform' + bookingId);
    const fieldPriceSpan = document.getElementById('fieldPrice' + bookingId);
    const totalPriceSpan = document.getElementById('totalPrice' + bookingId);
    const ballPriceRow = document.getElementById('ballPriceRow' + bookingId);
    const uniformPriceRow = document.getElementById('uniformPriceRow' + bookingId);

    function updatePrice() {
        const duration = parseFloat(durationSelect.value);
        const fieldPrice = rentalPrice * duration;
        let total = fieldPrice;

        fieldPriceSpan.textContent = fieldPrice.toLocaleString('vi-VN') + ' đ';

        if (rentBallCheckbox.checked) {
            total += 100000;
            ballPriceRow.style.display = 'flex';
        } else {
            ballPriceRow.style.display = 'none';
        }

        if (rentUniformCheckbox.checked) {
            total += 100000;
            uniformPriceRow.style.display = 'flex';
        } else {
            uniformPriceRow.style.display = 'none';
        }

        totalPriceSpan.textContent = total.toLocaleString('vi-VN') + ' đ';
    }

    durationSelect.addEventListener('change', updatePrice);
    rentBallCheckbox.addEventListener('change', updatePrice);
    rentUniformCheckbox.addEventListener('change', updatePrice);

    form.addEventListener('submit', function(e) {
        const timeInput = form.querySelector('[name="start_time"]');
        const time = timeInput.value;
        const [hours, minutes] = time.split(':').map(Number);
        
        if (hours < 6 || (hours === 22 && minutes > 0) || hours > 22) {
            e.preventDefault();
            alert('Vui lòng chọn giờ đặt sân từ 6:00 đến 22:00');
        }
    });
}

// Sửa lại cách khởi tạo form
<?php 
// Reset con trỏ của bookings_query
mysqli_data_seek($bookings_query, 0);
while($booking = mysqli_fetch_assoc($bookings_query)): 
    // Lấy rental_price từ football_fields
    $field_query = mysqli_query($conn, "SELECT rental_price FROM football_fields WHERE id = '{$booking['field_id']}'");
    $field = mysqli_fetch_assoc($field_query);
?>
    initializeEditForm(<?php echo $booking['id']; ?>, <?php echo $field['rental_price']; ?>);
<?php endwhile; ?>
</script>

<style>
.my-bookings-container {
    background: #f8f9fa;
    min-height: 100vh;
}

.page-title {
    color: #1B4D3E;
    margin-bottom: 30px;
    padding-bottom: 15px;
    border-bottom: 2px solid #28a745;
    display: inline-block;
}

.booking-card {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 0 20px rgba(0,0,0,0.1);
}

.booking-header {
    padding: 20px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.field-info {
    display: flex;
    gap: 15px;
}

.field-info img {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border-radius: 10px;
}

.field-info h4 {
    color: #1B4D3E;
    margin-bottom: 10px;
}

.field-info p {
    margin-bottom: 5px;
    color: #666;
}

.field-info i {
    width: 20px;
    color: #28a745;
}

.booking-status {
    padding: 8px 15px;
    border-radius: 20px;
    font-weight: 500;
    font-size: 14px;
}

.booking-status.chờ-xác-nhận {
    background: #ffc107;
    color: #000;
}

.booking-status.đã-xác-nhận {
    background: #28a745;
    color: white;
}

.booking-status.đã-hủy {
    background: #dc3545;
    color: white;
}

.booking-details {
    padding: 20px;
}

.detail-item {
    margin-bottom: 10px;
    color: #666;
}

.detail-item i {
    width: 25px;
    color: #28a745;
}

.services-price {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 10px;
    margin: 15px 0;
}

.price-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    padding-bottom: 8px;
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
    font-size: 16px;
    border-top: 2px solid #ddd;
    margin-top: 8px;
    padding-top: 8px;
}

.booking-note {
    background: #fff3cd;
    padding: 10px 15px;
    border-radius: 8px;
    margin: 15px 0;
    color: #856404;
}

.booking-note i {
    margin-right: 10px;
}

.booking-actions {
    display: flex;
    gap: 10px;
}

.booking-actions .btn {
    flex: 1;
}

.btn-secondary.disabled {
    background: #6c757d;
    opacity: 0.8;
    cursor: not-allowed;
}

.no-bookings {
    text-align: center;
    padding: 50px 20px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 0 20px rgba(0,0,0,0.1);
}

.no-bookings i {
    font-size: 50px;
    color: #dc3545;
    margin-bottom: 20px;
}

.no-bookings p {
    color: #666;
    margin-bottom: 20px;
}
</style>

<?php include 'footer.php'; ?> 

