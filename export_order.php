<?php
include 'config.php';
require('fpdf186/fpdf.php');
session_start();

// Kiểm tra nếu người dùng đã đăng nhập và có tham số event_id
if (!isset($_SESSION['user_id']) || !isset($_GET['event_id'])) {
    header('Location: events_list.php'); // Chuyển hướng nếu không đủ thông tin
    exit();
}

$user_id = $_SESSION['user_id'];
$event_id = $_GET['event_id'];

// Lấy thông tin sự kiện từ bảng events
$query = "SELECT * FROM `events` WHERE `event_id` = $event_id";
$result = mysqli_query($conn, $query) or die('Query failed');

if (mysqli_num_rows($result) == 0) {
    echo "<script>alert('Không tìm thấy sự kiện!'); window.location.href='events_list.php';</script>";
    exit();
}

$event = mysqli_fetch_assoc($result);

// Tạo file PDF
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);

// Tiêu đề sự kiện
$pdf->Cell(0, 10, 'THONG TIN SU KIEN', 0, 1, 'C');
$pdf->Ln(10);

// Nội dung chi tiết sự kiện
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, 'Ten su kien: ' . $event['title'], 0, 1);
$pdf->Cell(0, 10, 'Dia diem: ' . $event['location'], 0, 1);
$pdf->Cell(0, 10, 'Thoi gian bat dau: ' . date('H:i - d/m/Y', strtotime($event['start_time'])), 0, 1);
$pdf->Cell(0, 10, 'Thoi gian ket thuc: ' . date('H:i - d/m/Y', strtotime($event['end_time'])), 0, 1);
$pdf->Ln(5);

// Mô tả sự kiện
$pdf->MultiCell(0, 10, 'Mo ta: ' . $event['description'], 0, 1);

// Chèn hình ảnh thumbnail nếu có
if (!empty($event['thumbnail']) && file_exists('uploaded_img/' . $event['thumbnail'])) {
    $pdf->Ln(5);
    $pdf->Cell(0, 10, 'Hinh anh su kien:', 0, 1);
    $pdf->Image('uploaded_img/' . $event['thumbnail'], 10, $pdf->GetY(), 90, 60);
    $pdf->Ln(65);
}

// Xuất file PDF
$pdf->Output('D', 'Thong_Tin_Su_Kien_' . $event['event_id'] . '.pdf');
?>
