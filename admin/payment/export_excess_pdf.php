<?php
include '../../database/DBController.php';
require_once('../../tcpdf/tcpdf.php');

// Tạo query để lấy dữ liệu
$query = "
    SELECT e.*, a.Code as ApartmentCode, b.Name as BuildingName,
           r.ReceiptID as ReceiptNumber, 
           COALESCE(u.UserName, res.NationalId) as CustomerName
    FROM excesspayment e
    LEFT JOIN apartment a ON e.ApartmentID = a.ApartmentID
    LEFT JOIN Buildings b ON a.BuildingId = b.ID
    LEFT JOIN receipt r ON e.ReceiptID = r.ReceiptID
    LEFT JOIN ResidentApartment ra ON a.ApartmentID = ra.ApartmentId AND ra.Relationship = 'Chủ hộ'
    LEFT JOIN resident res ON ra.ResidentId = res.ID
    LEFT JOIN users u ON res.ID = u.ResidentID
    WHERE e.Status = 'active'
    ORDER BY e.OccurrenceDate DESC
";

$result = mysqli_query($conn, $query);

// Tạo PDF
class MYPDF extends TCPDF {
    public function Header() {
        $this->SetFont('dejavusans', 'B', 15);
        $this->Cell(0, 15, 'DANH SÁCH TIỀN THỪA', 0, false, 'C', 0, '', 0, false, 'M', 'M');
    }
}

$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Admin');
$pdf->SetTitle('Danh sách tiền thừa');

$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);

$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

$pdf->AddPage();

// Tạo bảng
$html = '<table border="1" cellpadding="4">
    <tr style="background-color: #8AA989; color: white;">
        <th>STT</th>
        <th>Căn hộ</th>
        <th>Tòa nhà</th>
        <th>Khách hàng</th>
        <th>Ngày phát sinh</th>
        <th>Số phiếu thu</th>
        <th>Tiền thừa hiện tại</th>
    </tr>';

$stt = 1;
while($row = mysqli_fetch_assoc($result)) {
    $html .= '<tr>
        <td>'.$stt.'</td>
        <td>'.$row['ApartmentCode'].'</td>
        <td>'.$row['BuildingName'].'</td>
        <td>'.$row['CustomerName'].'</td>
        <td>'.date('d/m/Y', strtotime($row['OccurrenceDate'])).'</td>
        <td>'.$row['ReceiptNumber'].'</td>
        <td align="right">'.number_format($row['Total'], 0, ',', '.').'</td>
    </tr>';
    $stt++;
}

$html .= '</table>';

$pdf->writeHTML($html, true, false, true, false, '');

$pdf->Output('Danh_sach_tien_thua.pdf', 'I');