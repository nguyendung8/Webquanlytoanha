<?php
include './database/DBController.php';
require_once('tcpdf/tcpdf.php');

session_start();

if (!isset($_GET['contract_code'])) {
    header('Location: contract_management.php');
    exit();
}

$contract_code = mysqli_real_escape_string($conn, $_GET['contract_code']);

// Lấy thông tin hợp đồng
$contract_query = mysqli_query($conn, "
    SELECT c.*, 
        a.ApartmentID, a.BuildingId, a.Area, a.Code as ApartmentCode, a.Name as ApartmentName,
        b.ProjectId, b.Name as BuildingName,
        p.Name as ProjectName, p.Address,
        s.Name as ManagerName, s.Position,
        r.ID as ResidentId, r.NationalId,
        u.UserName, u.Email, u.PhoneNumber,
        c.CretionDate
    FROM Contracts c
    JOIN apartment a ON a.ContractCode = c.ContractCode
    JOIN Buildings b ON a.BuildingId = b.ID
    JOIN Projects p ON b.ProjectId = p.ProjectID
    LEFT JOIN staffs s ON p.ManagerId = s.ID
    JOIN ResidentApartment ra ON ra.ApartmentId = a.ApartmentID AND ra.Relationship = 'Chủ hộ'
    JOIN resident r ON ra.ResidentId = r.ID
    JOIN users u ON r.ID = u.ResidentID
    WHERE c.ContractCode = '$contract_code'
") or die('Query failed');

if(mysqli_num_rows($contract_query) == 0) {
    echo "Không tìm thấy hợp đồng!";
    exit();
}

$contract_data = mysqli_fetch_assoc($contract_query);

// Lấy danh sách dịch vụ
$services_query = mysqli_query($conn, "
    SELECT cs.*, s.Name, s.ServiceCode, p.Price, p.TypeOfFee
    FROM ContractServices cs
    JOIN services s ON cs.ServiceId = s.ServiceCode
    LEFT JOIN ServicePrice sp ON s.ServiceCode = sp.ServiceId
    LEFT JOIN pricelist p ON sp.PriceId = p.ID
    WHERE cs.ContractCode = '$contract_code'
");

// Tạo PDF
class MYPDF extends TCPDF {
    public function Header() {
        // Tắt header mặc định của TCPDF
        // Để trống để không in header ở các trang sau
    }

    public function Footer() {
        // Tắt footer mặc định của TCPDF
    }
}

// Khởi tạo PDF
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Web Quản Lý Tòa Nhà');
$pdf->SetAuthor('Admin');
$pdf->SetTitle('Hợp đồng Quản Lý Vận Hành - ' . $contract_code);

// Tắt header và footer mặc định
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins
$pdf->SetMargins(15, 15, 15);

// Add a page
$pdf->AddPage();

// In phần header chỉ một lần ở đầu trang đầu tiên
$pdf->SetFont('dejavusans', 'B', 13);
$pdf->Cell(0, 10, 'CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM', 0, 1, 'C');
$pdf->SetFont('dejavusans', '', 13);
$pdf->Cell(0, 10, 'Độc lập - Tự do - Hạnh phúc', 0, 1, 'C');
$pdf->Cell(0, 10, '-------oOo-------', 0, 1, 'C');
$pdf->Ln(10);

$pdf->SetFont('dejavusans', 'B', 14);
$pdf->Cell(0, 10, 'HỢP ĐỒNG DỊCH VỤ QUẢN LÝ VẬN HÀNH NHÀ CHUNG CƯ', 0, 1, 'C');
$pdf->Ln(10);

// Set font cho nội dung
$pdf->SetFont('dejavusans', '', 11);

// Phần căn cứ pháp lý
$pdf->MultiCell(0, 6, 'Căn cứ Bộ Luật dân sự số 91/2015/QH13;
Căn cứ Luật Nhà ở số 65/2014/QH13;
Căn cứ Luật Xây dựng số 60/2014/QH13;
Căn cứ Nghị định số 99/2015/NĐ-CP ngày 20 tháng 10 năm 2015 của Chính phủ quy định chi tiết và hướng dẫn thi hành một số điều của Luật Nhà ở;
Căn cứ Thông tư số 02/2016/TT-BXD ngày 15 tháng 02 năm 2016 của Bộ Xây dựng ban hành kèm theo Quy chế quản lý; sử dụng nhà chung cư;
Căn cứ vào nhu cầu giữa hai bên.', 0, 'J');

$pdf->Ln(10);
$pdf->Cell(0, 10, 'Hai bên tham gia ký kết hợp đồng dưới đây bao gồm:', 0, 1);

// Thông tin Bên A
$pdf->SetFont('dejavusans', 'B', 11);
$pdf->Cell(0, 10, 'Bên A: Đơn vị quản lý vận hành nhà chung cư (sau đây gọi tắt là Bên A)', 0, 1);
$pdf->SetFont('dejavusans', '', 11);
$pdf->Cell(0, 6, 'Tên dự án quản lý: ' . $contract_data['ProjectName'], 0, 1);
$pdf->Cell(0, 6, 'Người đại diện: ' . $contract_data['ManagerName'], 0, 1);
$pdf->Cell(0, 6, 'Chức vụ: ' . $contract_data['Position'], 0, 1);
$pdf->Cell(0, 6, 'Địa chỉ: ' . $contract_data['Address'], 0, 1);

// Thông tin Bên B
$pdf->Ln(5);
$pdf->SetFont('dejavusans', 'B', 11);
$pdf->Cell(0, 10, 'Bên B: Chủ sở hữu chung cư (sau đây gọi tắt là Bên B)', 0, 1);
$pdf->SetFont('dejavusans', '', 11);
$pdf->Cell(0, 6, 'Họ và tên: ' . $contract_data['UserName'], 0, 1);
$pdf->Cell(0, 6, 'Căn cước công dân: ' . $contract_data['NationalId'], 0, 1);
$pdf->Cell(0, 6, 'Email: ' . $contract_data['Email'], 0, 1);
$pdf->Cell(0, 6, 'Điện thoại: ' . $contract_data['PhoneNumber'], 0, 1);

// Thông tin căn hộ
$pdf->Ln(5);
$pdf->SetFont('dejavusans', 'B', 11);
$pdf->Cell(0, 10, 'Điều 2. Đặc điểm của nhà chung cư', 0, 1);
$pdf->SetFont('dejavusans', '', 11);
$pdf->Cell(0, 6, '1. Tên nhà chung cư: ' . $contract_data['BuildingName'], 0, 1);
$pdf->Cell(0, 6, '2. Mã căn hộ: ' . $contract_data['ApartmentCode'], 0, 1);
$pdf->Cell(0, 6, '3. Diện tích: ' . $contract_data['Area'] . ' m2', 0, 1);

// Danh sách dịch vụ
$pdf->Ln(5);
$pdf->SetFont('dejavusans', 'B', 11);
$pdf->Cell(0, 10, 'Điều 4. Giá dịch vụ quản lý vận hành nhà chung cư', 0, 1);
$pdf->SetFont('dejavusans', '', 11);

// Tạo bảng dịch vụ
$pdf->Cell(10, 10, 'STT', 1);
$pdf->Cell(80, 10, 'Tên dịch vụ', 1);
$pdf->Cell(50, 10, 'Giá', 1);
$pdf->Cell(50, 10, 'Đơn vị tính', 1);
$pdf->Ln();

$stt = 1;
while($service = mysqli_fetch_assoc($services_query)) {
    $pdf->Cell(10, 10, $stt, 1);
    $pdf->Cell(80, 10, $service['Name'], 1);
    $pdf->Cell(50, 10, number_format($service['Price'], 0, ',', '.'), 1);
    $pdf->Cell(50, 10, $service['TypeOfFee'] == 'monthly' ? 'Tháng' : 'Năm', 1);
    $pdf->Ln();
    $stt++;
}

// Thời hạn hợp đồng
$pdf->Ln(10);
$pdf->SetFont('dejavusans', 'B', 11);
$pdf->Cell(0, 10, 'Điều 10. Thời hạn thực hiện hợp đồng', 0, 1);
$pdf->SetFont('dejavusans', '', 11);
$pdf->Cell(0, 6, 'Ngày áp dụng: ' . date('d/m/Y', strtotime($contract_data['CretionDate'])), 0, 1);
$pdf->Cell(0, 6, 'Ngày kết thúc: ' . date('d/m/Y', strtotime($contract_data['EndDate'])), 0, 1);

// Ở phần cuối, trước khi xuất PDF
$pdf->Ln(20);

// Phần chữ ký (sửa lại phần này)
$pdf->Cell(95, 10, 'BÊN A', 0, 0, 'C');
$pdf->Cell(95, 10, 'BÊN B', 0, 1, 'C');

$pdf->SetFont('dejavusans', 'I', 11);
$pdf->Cell(95, 10, '(Ký, ghi rõ họ tên, chức vụ và đóng dấu)', 0, 0, 'C');
$pdf->Cell(95, 10, '(Ký, ghi rõ họ tên)', 0, 1, 'C');

// Thêm khoảng trống cho chữ ký
$pdf->Ln(40);

// Thêm tên người ký nếu có
if (!empty($contract_data['ManagerName'])) {
    $pdf->SetFont('dejavusans', 'B', 11);
    $pdf->Cell(95, 10, $contract_data['ManagerName'], 0, 0, 'C');
    $pdf->Cell(95, 10, $contract_data['UserName'], 0, 1, 'C');
}

// Xuất file PDF
$pdf->Output('Hop_dong_' . $contract_code . '.pdf', 'D');
?>