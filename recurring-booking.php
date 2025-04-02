<?php
include 'database/DBController.php';
session_start();

$user_id = @$_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: login.php');
    exit();
}

$field_id = $_GET['field_id'] ?? null;
if (!$field_id) {
    header('Location: index.php');
    exit();
}

// Lấy thông tin sân
$field_query = mysqli_query($conn, "SELECT * FROM football_fields WHERE id = '$field_id'");
$field = mysqli_fetch_assoc($field_query);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $day_of_week = $_POST['day_of_week'];
    $start_time = $_POST['start_time'];
    $duration = floatval($_POST['duration']);
    $rent_ball = isset($_POST['rent_ball']) ? 1 : 0;
    $rent_uniform = isset($_POST['rent_uniform']) ? 1 : 0;
    $note = mysqli_real_escape_string($conn, $_POST['note']);

    // Kiểm tra file ảnh
    if (!isset($_FILES['payment_image']) || $_FILES['payment_image']['error'] !== 0) {
        echo "<script>
            alert('Vui lòng upload ảnh bill thanh toán!');
            history.back();
        </script>";
        exit();
    }

    // Xử lý upload ảnh
    $image = $_FILES['payment_image'];
    $image_name = time() . '_' . $image['name'];
    $target_path = 'assets/bill/' . $image_name;

    // Kiểm tra và tạo thư mục nếu chưa tồn tại
    if (!file_exists('assets/bill')) {
        mkdir('assets/bill', 0777, true);
    }

    if (!move_uploaded_file($image['tmp_name'], $target_path)) {
        echo "<script>
            alert('Có lỗi khi upload ảnh. Vui lòng thử lại!');
            history.back();
        </script>";
        exit();
    }

    // Tính giờ kết thúc
    $start_timestamp = strtotime("$start_date $start_time");
    $duration_seconds = $duration * 3600;
    $end_time = date('H:i:s', $start_timestamp + $duration_seconds);

    // Kiểm tra xem có trùng lịch không
    $current_date = $start_date;
    $valid = true;
    $error_dates = [];

    while (strtotime($current_date) <= strtotime($end_date)) {
        if (date('l', strtotime($current_date)) == $day_of_week) {
            // Kiểm tra trùng lịch
            $check_query = "SELECT b.* FROM bookings b 
                          WHERE b.field_id = '$field_id' 
                          AND b.booking_date = '$current_date'
                          AND b.status != 'Đã hủy'
                          AND ((b.start_time <= '$start_time' AND b.end_time > '$start_time')
                          OR (b.start_time < '$end_time' AND b.end_time >= '$end_time')
                          OR (b.start_time >= '$start_time' AND b.end_time <= '$end_time'))";
            
            $check_result = mysqli_query($conn, $check_query);
            if (mysqli_num_rows($check_result) > 0) {
                $valid = false;
                $error_dates[] = date('d/m/Y', strtotime($current_date));
            }
        }
        $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
    }

    if ($valid) {
        // Tính tổng tiền
        $rental_price = $field['rental_price'];
        $total_hours = 0;
        
        // Đếm số ngày đặt sân
        $current_date = $start_date;
        $number_of_days = 0;
        while (strtotime($current_date) <= strtotime($end_date)) {
            if (date('l', strtotime($current_date)) == $day_of_week) {
                $number_of_days++;
            }
            $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
        }
        
        // Tính tổng số giờ và giá tiền sân
        $total_hours = $number_of_days * $duration;
        $field_price = $total_hours * $rental_price;
        
        // Tính giá dịch vụ thêm
        $ball_price = $rent_ball ? (100000 * $number_of_days) : 0;
        $uniform_price = $rent_uniform ? (100000 * $number_of_days) : 0;
        
        // Tổng tiền trước giảm giá
        $total_before_discount = $field_price + $ball_price + $uniform_price;
        
        // Giảm giá 20% cho đặt định kỳ
        $discount = $total_before_discount * 0.2;
        
        // Tổng tiền sau giảm giá
        $total_price = $total_before_discount - $discount;

        // Tạo đặt sân định kỳ
        mysqli_query($conn, "INSERT INTO recurring_bookings 
            (user_id, field_id, start_date, end_date, day_of_week, start_time, duration, 
             rent_ball, rent_uniform, note, payment_image, total_price, status)
            VALUES 
            ('$user_id', '$field_id', '$start_date', '$end_date', '$day_of_week', 
             '$start_time', '$duration', '$rent_ball', '$rent_uniform', '$note', 
             '$image_name', '$total_price', 'Chờ xác nhận')") or die('Query failed: ' . mysqli_error($conn));

        header('Location: my-bookings.php');
        exit();
    } else {
        $message = "Không thể đặt sân định kỳ do trùng lịch vào các ngày: " . implode(', ', $error_dates);
    }
}
?>

<?php include 'header.php'; ?>

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

            <!-- Form đặt sân định kỳ -->
            <div class="col-lg-8">
                <div class="booking-form-card">
                    <h3 class="mb-4">Đặt sân định kỳ</h3>
                    <?php if(isset($message)): ?>
                        <div class="alert alert-danger">
                            <?php echo $message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" class="booking-form" enctype="multipart/form-data">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Ngày bắt đầu</label>
                                    <input type="date" name="start_date" class="form-control" 
                                           min="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Ngày kết thúc</label>
                                    <input type="date" name="end_date" class="form-control" 
                                           min="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Thứ trong tuần</label>
                                    <select name="day_of_week" class="form-control" required>
                                        <option value="Monday">Thứ 2</option>
                                        <option value="Tuesday">Thứ 3</option>
                                        <option value="Wednesday">Thứ 4</option>
                                        <option value="Thursday">Thứ 5</option>
                                        <option value="Friday">Thứ 6</option>
                                        <option value="Saturday">Thứ 7</option>
                                        <option value="Sunday">Chủ nhật</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Giờ bắt đầu</label>
                                    <input type="time" name="start_time" class="form-control" 
                                           min="06:00" max="22:00" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Thời lượng (giờ)</label>
                                    <select name="duration" class="form-control" required>
                                        <option value="1">1 giờ</option>
                                        <option value="1.5">1.5 giờ</option>
                                        <option value="2">2 giờ</option>
                                        <option value="2.5">2.5 giờ</option>
                                        <option value="3">3 giờ</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="service-options mb-3">
                            <label class="d-block mb-2">Dịch vụ thêm</label>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check service-check">
                                        <input type="checkbox" name="rent_ball" class="form-check-input" id="rent_ball">
                                        <label class="form-check-label" for="rent_ball">
                                            <i class="fas fa-futbol"></i>
                                            Thuê bóng
                                            <span class="price">100,000đ</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check service-check">
                                        <input type="checkbox" name="rent_uniform" class="form-check-input" id="rent_uniform">
                                        <label class="form-check-label" for="rent_uniform">
                                            <i class="fas fa-tshirt"></i>
                                            Thuê áo
                                            <span class="price">100,000đ</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mb-4">
                            <label>Ghi chú</label>
                            <textarea name="note" class="form-control" rows="3" 
                                    placeholder="Nhập ghi chú nếu có..."></textarea>
                        </div>

                        <div class="price-summary mb-4">
                            <h5>Thông tin thanh toán</h5>
                            <div class="price-details">
                                <div class="price-item">
                                    <span>Số ngày đặt:</span>
                                    <span id="numberOfDays">0 ngày</span>
                                </div>
                                <div class="price-item">
                                    <span>Tổng số giờ:</span>
                                    <span id="totalHours">0 giờ</span>
                                </div>
                                <div class="price-item">
                                    <span>Giá thuê sân:</span>
                                    <span id="fieldPrice">0 đ</span>
                                </div>
                                <div class="price-item" id="ballPriceRow" style="display: none;">
                                    <span>Thuê bóng (100,000đ × số ngày):</span>
                                    <span id="ballTotalPrice">0 đ</span>
                                </div>
                                <div class="price-item" id="uniformPriceRow" style="display: none;">
                                    <span>Thuê áo (100,000đ × số ngày):</span>
                                    <span id="uniformTotalPrice">0 đ</span>
                                </div>
                                <div class="price-item discount">
                                    <span>Giảm giá đặt định kỳ (20%):</span>
                                    <span id="discountPrice" class="text-success">0 đ</span>
                                </div>
                                <div class="price-item total">
                                    <span>Tổng tiền:</span>
                                    <span id="totalPrice">0 đ</span>
                                </div>
                                <div class="price-item deposit">
                                    <span>Số tiền đặt cọc (50%):</span>
                                    <span id="depositAmount" class="text-danger">0 đ</span>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mb-4">
                            <label>Upload ảnh bill thanh toán <span class="text-danger">*</span></label>
                            <input type="file" name="payment_image" class="form-control" accept="image/*" required>
                            <div class="form-text">Vui lòng upload ảnh chụp màn hình bill thanh toán đặt cọc</div>
                            <div id="imagePreview" class="mt-2 text-center" style="display: none;">
                                <img src="" alt="Preview" style="max-width: 200px; max-height: 200px;">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="fas fa-calendar-check"></i> Xác nhận đặt sân định kỳ
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.booking-container {
    background-color: #f8f9fa;
}

.field-info-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 0 15px rgba(0,0,0,0.1);
    padding: 20px;
}

.field-info-card img {
    width: 100%;
    height: 250px;
    object-fit: cover;
    border-radius: 8px;
}

.field-info-card h3 {
    margin: 15px 0;
    color: #333;
}

.field-details p {
    margin-bottom: 10px;
    color: #666;
}

.field-details i {
    width: 25px;
    color: #28a745;
}

.booking-form-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 0 15px rgba(0,0,0,0.1);
    padding: 25px;
}

.booking-form-card h3 {
    color: #333;
    border-bottom: 2px solid #28a745;
    padding-bottom: 10px;
}

.form-group label {
    font-weight: 500;
    color: #555;
    margin-bottom: 5px;
}

.service-check {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px 15px 15px 33px;
    margin-bottom: 10px;
    transition: all 0.3s ease;
}

.service-check:hover {
    background: #e9ecef;
}

.service-check label {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
    cursor: pointer;
}

.service-check i {
    color: #28a745;
}

.service-check .price {
    margin-left: auto;
    color: #dc3545;
    font-weight: 500;
}

.btn-primary {
    background-color: #28a745;
    border-color: #28a745;
    padding: 12px 25px;
    font-weight: 500;
}

.btn-primary:hover {
    background-color: #218838;
    border-color: #218838;
}

.btn i {
    margin-right: 8px;
}
/* .form-check-input {
    margin-left: -0.25rem;
} */

.price-summary {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
}

.price-summary h5 {
    color: #333;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #dee2e6;
}

.price-details .price-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 1px dashed #dee2e6;
}

.price-details .price-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.price-item.total {
    font-weight: 600;
    font-size: 18px;
    color: #28a745;
    border-top: 2px solid #dee2e6;
    margin-top: 10px;
    padding-top: 10px;
}

.price-item.deposit {
    font-weight: 600;
    color: #dc3545;
}

.price-item.discount {
    color: #28a745;
    font-weight: 500;
}

#imagePreview {
    margin-top: 10px;
    padding: 10px;
    border: 1px dashed #dee2e6;
    border-radius: 8px;
}

#imagePreview img {
    max-width: 100%;
    height: auto;
    border-radius: 4px;
}

#numberOfDays, #totalHours {
    font-weight: 600;
    color: #0056b3;
}

.price-details {
    font-size: 15px;
}

.price-item span:first-child {
    color: #666;
}

.alert-info {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-left: 4px solid #17a2b8;
    margin-bottom: 20px;
}

.alert-info p {
    margin-bottom: 8px;
}

.alert-info p:last-child {
    margin-bottom: 0;
}

#bookingDetails {
    margin-bottom: 20px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Các elements
    const form = document.querySelector('.booking-form');
    const startDateInput = form.querySelector('[name="start_date"]');
    const endDateInput = form.querySelector('[name="end_date"]');
    const dayOfWeekSelect = form.querySelector('[name="day_of_week"]');
    const durationSelect = form.querySelector('[name="duration"]');
    const rentBallCheckbox = document.getElementById('rent_ball');
    const rentUniformCheckbox = document.getElementById('rent_uniform');
    const fieldPriceSpan = document.getElementById('fieldPrice');
    const discountPriceSpan = document.getElementById('discountPrice');
    const totalPriceSpan = document.getElementById('totalPrice');
    const depositAmountSpan = document.getElementById('depositAmount');
    const ballPriceRow = document.getElementById('ballPriceRow');
    const uniformPriceRow = document.getElementById('uniformPriceRow');

    // Hàm đếm số ngày trong khoảng thời gian theo thứ đã chọn
    function countDaysInRange(startDate, endDate, dayOfWeek) {
        // Chuyển đổi dayOfWeek từ text sang số
        const dayMapping = {
            'Monday': 1,
            'Tuesday': 2,
            'Wednesday': 3,
            'Thursday': 4,
            'Friday': 5,
            'Saturday': 6,
            'Sunday': 0
        };
        
        // Chuyển đổi chuỗi ngày thành đối tượng Date
        let start = new Date(startDate);
        let end = new Date(endDate);
        
        // Đặt giờ về 00:00:00 để so sánh chính xác
        start.setHours(0, 0, 0, 0);
        end.setHours(0, 0, 0, 0);
        
        let count = 0;
        let current = new Date(start);
        
        // Debug log
        console.log('Start Date:', start);
        console.log('End Date:', end);
        console.log('Day of Week:', dayOfWeek);
        console.log('Target Day Number:', dayMapping[dayOfWeek]);
        
        while (current <= end) {
            if (current.getDay() === dayMapping[dayOfWeek]) {
                count++;
                console.log('Found matching day:', current.toDateString());
            }
            current.setDate(current.getDate() + 1);
        }
        
        console.log('Total count:', count);
        return count;
    }

    // Hàm tính giá
    function updatePrice() {
        const startDate = startDateInput.value;
        const endDate = endDateInput.value;
        const dayOfWeek = dayOfWeekSelect.value;
        const duration = parseFloat(durationSelect.value);
        const rentalPrice = <?php echo $field['rental_price']; ?>;
        const rentBall = rentBallCheckbox.checked;
        const rentUniform = rentUniformCheckbox.checked;

        if (startDate && endDate && dayOfWeek) {
            // Debug log
            console.log('Calculating for:');
            console.log('Start Date:', startDate);
            console.log('End Date:', endDate);
            console.log('Day of Week:', dayOfWeek);
            
            // Đếm số ngày đặt sân
            const numberOfDays = countDaysInRange(startDate, endDate, dayOfWeek);
            
            // Debug log
            console.log('Number of days:', numberOfDays);
            
            // Tổng số giờ và tính tiền
            const totalHours = numberOfDays * duration;
            const fieldPrice = totalHours * rentalPrice;
            const ballPrice = rentBall ? (100000 * numberOfDays) : 0;
            const uniformPrice = rentUniform ? (100000 * numberOfDays) : 0;

            // Hiển thị thông tin chi tiết
            // document.getElementById('bookingDetails').innerHTML = `
            //     <div class="alert alert-info">
            //         <p><strong>Chi tiết đặt sân:</strong></p>
            //         <p>- Thứ: ${convertDayToVietnamese(dayOfWeek)}</p>
            //         <p>- Từ ngày: ${formatDate(startDate)} đến ngày: ${formatDate(endDate)}</p>
            //         <p>- Số buổi: ${numberOfDays} buổi</p>
            //         <p>- Thời gian mỗi buổi: ${duration} giờ</p>
            //         <p>- Tổng số giờ: ${totalHours} giờ</p>
            //     </div>
            // `;

            // Hiển thị giá
            fieldPriceSpan.textContent = fieldPrice.toLocaleString('vi-VN') + ' đ';
            document.getElementById('numberOfDays').textContent = numberOfDays + ' ngày';
            document.getElementById('totalHours').textContent = totalHours + ' giờ';

            // Hiển thị/ẩn các dòng giá dịch vụ
            ballPriceRow.style.display = rentBall ? 'flex' : 'none';
            uniformPriceRow.style.display = rentUniform ? 'flex' : 'none';
            if (rentBall) {
                document.getElementById('ballTotalPrice').textContent = ballPrice.toLocaleString('vi-VN') + ' đ';
            }
            if (rentUniform) {
                document.getElementById('uniformTotalPrice').textContent = uniformPrice.toLocaleString('vi-VN') + ' đ';
            }

            // Tính tổng tiền trước giảm giá
            const totalBeforeDiscount = fieldPrice + ballPrice + uniformPrice;

            // Tính giảm giá 20%
            const discount = totalBeforeDiscount * 0.2;
            discountPriceSpan.textContent = '-' + discount.toLocaleString('vi-VN') + ' đ';

            // Tính tổng tiền sau giảm giá
            const totalAfterDiscount = totalBeforeDiscount - discount;
            totalPriceSpan.textContent = totalAfterDiscount.toLocaleString('vi-VN') + ' đ';

            // Tính tiền đặt cọc (50%)
            const deposit = totalAfterDiscount * 0.5;
            depositAmountSpan.textContent = deposit.toLocaleString('vi-VN') + ' đ';
        }
    }

    // Hàm chuyển đổi thứ sang tiếng Việt
    function convertDayToVietnamese(day) {
        const dayMap = {
            'Monday': 'Thứ 2',
            'Tuesday': 'Thứ 3',
            'Wednesday': 'Thứ 4',
            'Thursday': 'Thứ 5',
            'Friday': 'Thứ 6',
            'Saturday': 'Thứ 7',
            'Sunday': 'Chủ nhật'
        };
        return dayMap[day];
    }

    // Hàm format ngày sang dd/mm/yyyy
    function formatDate(dateString) {
        const date = new Date(dateString);
        return `${date.getDate().toString().padStart(2, '0')}/${(date.getMonth() + 1).toString().padStart(2, '0')}/${date.getFullYear()}`;
    }

    // Thêm div để hiển thị chi tiết đặt sân
    const priceDetails = document.querySelector('.price-details');
    const bookingDetailsDiv = document.createElement('div');
    bookingDetailsDiv.id = 'bookingDetails';
    priceDetails.insertBefore(bookingDetailsDiv, priceDetails.firstChild);

    // Gắn sự kiện cho các trường input
    startDateInput.addEventListener('change', updatePrice);
    endDateInput.addEventListener('change', updatePrice);
    dayOfWeekSelect.addEventListener('change', updatePrice);
    durationSelect.addEventListener('change', updatePrice);
    rentBallCheckbox.addEventListener('change', updatePrice);
    rentUniformCheckbox.addEventListener('change', updatePrice);

    // Preview ảnh khi chọn file
    const imageInput = document.querySelector('input[name="payment_image"]');
    const imagePreview = document.getElementById('imagePreview');

    imageInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                imagePreview.style.display = 'block';
                imagePreview.querySelector('img').src = e.target.result;
            }
            reader.readAsDataURL(file);
        }
    });

    // Khởi tạo giá ban đầu
    updatePrice();
});
</script>

<?php include 'footer.php'; ?> 