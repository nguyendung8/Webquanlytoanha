<?php
include 'database/DBController.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    $field_id = $_POST['field_id'];
    $booking_date = $_POST['booking_date'];
    $start_time = $_POST['start_time'];
    $duration = floatval($_POST['duration']);
    $rent_ball = isset($_POST['rent_ball']) ? 1 : 0;
    $rent_uniform = isset($_POST['rent_uniform']) ? 1 : 0;
    $payment_method = $_POST['payment_method'];
    $note = mysqli_real_escape_string($conn, $_POST['note']);

    // Tính thời gian kết thúc
    $start_timestamp = strtotime("$booking_date $start_time");
    $duration_seconds = $duration * 3600;
    $end_timestamp = $start_timestamp + $duration_seconds;
    $end_time = date('H:i', $end_timestamp);

    // Tính giá
    $field_query = mysqli_query($conn, "SELECT rental_price FROM football_fields WHERE id = '$field_id'");
    $field = mysqli_fetch_assoc($field_query);
    $field_price = $field['rental_price'] * $duration;
    $total_price = $field_price;
    if ($rent_ball) $total_price += 100000;
    if ($rent_uniform) $total_price += 100000;
    $deposit_amount = $total_price * 0.5;

    // Kiểm tra trùng lịch
    $check_booking = mysqli_query($conn, "SELECT * FROM bookings 
        WHERE field_id = '$field_id' 
        AND booking_date = '$booking_date'
        AND ((start_time <= '$start_time' AND end_time > '$start_time')
        OR (start_time < '$end_time' AND end_time >= '$end_time')
        OR (start_time >= '$start_time' AND end_time <= '$end_time'))
        AND status != 'Đã hủy'");

    if (mysqli_num_rows($check_booking) > 0) {
        echo json_encode(['success' => false, 'message' => 'Sân đã được đặt trong khoảng thời gian này']);
        exit;
    }

    // Lưu đơn đặt sân
    $insert_booking = mysqli_query($conn, "INSERT INTO bookings 
        (user_id, field_id, booking_date, start_time, end_time, duration,
         field_price, rent_ball, rent_uniform, total_price, note, status,
         payment_method, deposit_amount, payment_status) 
        VALUES (
            '$user_id', '$field_id', '$booking_date', '$start_time', '$end_time',
            '$duration', '$field_price', '$rent_ball', '$rent_uniform', '$total_price',
            '$note', 'Chờ xác nhận', '$payment_method', '$deposit_amount', 'Đã đặt cọc'
        )");

    if ($insert_booking) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Lỗi khi lưu đơn đặt sân");
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 