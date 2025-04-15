<?php
include '../../database/DBController.php';
require_once('../../tcpdf/tcpdf.php');

// Đảm bảo không có output nào trước khi tạo PDF
ob_clean();

if(isset($_GET['invoice_code'])) {
    $invoice_code = mysqli_real_escape_string($conn, $_GET['invoice_code']);
    
    // Cập nhật query để lấy thông tin từ bảng users thay vì resident
    $invoice_query = mysqli_query($conn, "
        SELECT d.*, 
               a.Code as ApartmentCode, 
               a.Name as ApartmentName, 
               a.ApartmentID,
               b.Name as BuildingName, 
               b.ProjectId,
               p.ManagerId, 
               s.Name as ManagerName,
               u.UserName as ResidentName,
               r.NationalId
        FROM debtstatements d
        LEFT JOIN apartment a ON d.ApartmentID = a.ApartmentID
        LEFT JOIN Buildings b ON a.BuildingId = b.ID
        LEFT JOIN Projects p ON b.ProjectId = p.ProjectID
        LEFT JOIN staffs s ON p.ManagerId = s.ID
        LEFT JOIN ResidentApartment ra ON a.ApartmentID = ra.ApartmentId AND ra.Relationship = 'Chủ hộ'
        LEFT JOIN resident r ON ra.ResidentId = r.ID
        LEFT JOIN users u ON r.ID = u.ResidentID
        WHERE d.InvoiceCode = '$invoice_code'
    ");
    
    if(mysqli_num_rows($invoice_query) > 0) {
        $invoice = mysqli_fetch_assoc($invoice_query);
        
        // Kiểm tra và gán giá trị mặc định nếu không có dữ liệu
        $resident_name = !empty($invoice['ResidentName']) ? $invoice['ResidentName'] : 
                        (!empty($invoice['NationalId']) ? $invoice['NationalId'] : 'Không xác định');
        $apartment_name = !empty($invoice['ApartmentName']) ? $invoice['ApartmentName'] : 'Không xác định';
        $apartment_code = !empty($invoice['ApartmentCode']) ? $invoice['ApartmentCode'] : 'Không xác định';
        $manager_name = !empty($invoice['ManagerName']) ? $invoice['ManagerName'] : 'Hoàng Văn Nam';
        
        // Lấy chi tiết dịch vụ
        $details_query = mysqli_query($conn, "
            SELECT d.*, s.Name as ServiceName, s.TypeOfService
            FROM debtstatementdetail d
            LEFT JOIN services s ON d.ServiceCode = s.ServiceCode
            WHERE d.InvoiceCode = '$invoice_code'
        ");
        
        $details = [];
        while($detail = mysqli_fetch_assoc($details_query)) {
            $details[] = $detail;
        }
        
        // Tạo PDF
        class MYPDF extends TCPDF {
            public function Header() {
                $this->SetFont('dejavusans', 'B', 13);
                $this->Cell(0, 10, 'VĂN PHÒNG BAN QUẢN LÝ BUILDMATE', 0, 1, 'C');
                $this->SetFont('dejavusans', '', 11);
                $this->Cell(0, 6, '12 Chùa Bộc, Quang Trung, Đống Đa, Hà Nội', 0, 1, 'C');
                $this->Cell(0, 6, 'Hotline CSKH: 0978343328 - Hotline kỹ thuật: 0978343328', 0, 1, 'C');
                $this->Cell(0, 6, 'Hotline bảo vệ: 0978343328', 0, 1, 'C');
                $this->Ln(5);
            }
        }

        $pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Buildmate');
        $pdf->SetTitle('Giấy báo phí - ' . $invoice['InvoiceCode']);
        
        $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
        
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        
        $pdf->SetMargins(15, 50, 15);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
        
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
        
        $pdf->AddPage();
        
        // Tiêu đề
        $pdf->SetFont('dejavusans', 'B', 16);
        $pdf->Cell(0, 10, 'GIẤY BÁO PHÍ THÁNG ' . $invoice['InvoicePeriod'], 0, 1, 'C');
        $pdf->Ln(5);
        
        // Thông tin chung
        $pdf->SetFont('dejavusans', '', 11);
        $pdf->Cell(100, 6, 'Kính gửi/Respectfully: ' . $resident_name, 0, 0);
        $pdf->Cell(90, 6, 'Số: ' . $invoice['InvoiceCode'], 0, 1, 'R');
        
        $pdf->Cell(100, 6, 'Mã số căn hộ/Apartment code: ' . $apartment_code, 0, 0);
        $pdf->Cell(90, 6, 'Tổng tiền: ' . number_format($invoice['Total']) . ' VNĐ', 0, 1, 'R');
        $pdf->Ln(5);
        
        // Bảng tổng hợp
        $pdf->SetFont('dejavusans', 'B', 11);
        $pdf->Cell(0, 6, 'BẢNG TỔNG HỢP', 0, 1, 'L');
        
        $pdf->SetFont('dejavusans', '', 10);
        $tbl = '<table border="1" cellpadding="5">
            <tr style="background-color: #f0f0f0;">
                <th width="5%">STT</th>
                <th width="20%">Diễn giải</th>
                <th width="15%">Nợ trước</th>
                <th width="20%">Phát sinh trong tháng</th>
                <th width="15%">Thanh toán</th>
                <th width="15%">Tổng</th>
                <th width="10%">Ghi chú</th>
            </tr>
            <tr>
                <td>1</td>
                <td>Phí quản lý</td>
                <td align="right">' . number_format($invoice['OutstandingDebt']) . '</td>
                <td align="right">' . number_format($invoice['Total'] - $invoice['OutstandingDebt'] - $invoice['Discount']) . '</td>
                <td align="right">' . number_format($invoice['PaidAmount']) . '</td>
                <td align="right">' . number_format($invoice['Total']) . '</td>
                <td></td>
            </tr>
        </table>';
        
        $pdf->writeHTML($tbl, true, false, false, false, '');
        $pdf->Ln(5);
        
        // Chi tiết dịch vụ
        $pdf->SetFont('dejavusans', 'B', 11);
        $pdf->Cell(0, 6, 'THÔNG TIN CHI TIẾT/THE INFORMATION IN DETAIL', 0, 1, 'L');
        $pdf->Ln(2);
        
        foreach($details as $index => $service) {
            $pdf->SetFont('dejavusans', 'B', 10);
            $pdf->Cell(0, 6, ($index + 1) . '/' . $service['ServiceName'], 0, 1);
            
            $serviceAmount = $service['Quantity'] * $service['UnitPrice'];
            $serviceTbl = '<table border="1" cellpadding="5" style="margin-bottom: 10px;">
                <tr style="background-color: #f0f0f0;">
                    <th width="25%">Tháng (Month)</th>
                    <th width="25%">Diện tích (SQM) (1)</th>
                    <th width="25%">Đơn giá (Unit price) (2)</th>
                    <th width="25%">Thành tiền (Amount)(3)=(1)x(2)</th>
                </tr>
                <tr>
                    <td>Nợ trước/ Debt</td>
                    <td align="right">0</td>
                    <td align="right">0</td>
                    <td align="right">0</td>
                </tr>
                <tr>
                    <td>Tháng ' . $invoice['InvoicePeriod'] . '</td>
                    <td align="right">' . $service['Quantity'] . '</td>
                    <td align="right">' . number_format($service['UnitPrice']) . '</td>
                    <td align="right">' . number_format($serviceAmount) . '</td>
                </tr>
                <tr>
                    <td>Giảm giá/ Discount</td>
                    <td></td>
                    <td></td>
                    <td align="right">' . number_format($service['Discount']) . '</td>
                </tr>
                <tr>
                    <td>Thanh toán/ Paid</td>
                    <td></td>
                    <td></td>
                    <td align="right">' . number_format($service['PaidAmount']) . '</td>
                </tr>
            </table>';
            
            $pdf->writeHTML($serviceTbl, true, false, false, false, '');
        }
        
        // Phương thức thanh toán
        $pdf->SetFont('dejavusans', 'B', 11);
        $pdf->Cell(0, 6, 'PHƯƠNG THỨC THANH TOÁN/PAYMENT METHODS', 0, 1, 'L');
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->Cell(0, 6, '1/Thanh toán tiền mặt/ By cash:', 0, 1);
        $pdf->Cell(0, 6, 'Tại Văn phòng ban quản lý Buildmate', 0, 1);
        $pdf->Ln(2);
        
        $pdf->Cell(0, 6, '2/Thanh toán chuyển khoản/ ByTranfer to:', 0, 1);
        $bankTbl = '<table border="1" cellpadding="5">
            <tr style="background-color: #f0f0f0;">
                <th>Chủ tài khoản (Name)</th>
                <th>Số tài khoản (Account number)</th>
                <th>Ngân hàng (Bank)</th>
            </tr>
            <tr>
                <td>Trần Thị Kim Anh</td>
                <td>0888738572</td>
                <td>Ngân hàng Quân đội MB Bank</td>
            </tr>
        </table>';
        
        $pdf->writeHTML($bankTbl, true, false, false, false, '');
        
        // Ghi chú
        $pdf->Ln(5);
        $pdf->SetFont('dejavusans', 'B', 11);
        $pdf->Cell(0, 6, 'Ghi chú/Note:', 0, 1);
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->MultiCell(0, 6, '- Thời hạn nộp các khoản phí là 45 ngày kể từ ngày phát sinh phí chưa thanh toán.', 0, 'L');
        $pdf->MultiCell(0, 6, '- Ban Quản lý sẽ cảnh thông báo trước nợ 05 ngày trước khi tiến hành ngưng cung cấp dịch vụ đối với các căn hộ nợ các khoản phí quá 45 ngày.', 0, 'L');
        $pdf->MultiCell(0, 6, '- Nếu quý khách hàng đã thanh toán phí, xin vui lòng bỏ qua thông báo này.', 0, 'L');
        $pdf->MultiCell(0, 6, '- Trường hợp Quý cư dân vì lý do đặc biệt chưa thanh toán kịp thời thì gian quy định, xin vui lòng thông báo cho Ban Quản lý để được hỗ trợ.', 0, 'L');
        
        // Chữ ký
        $pdf->Ln(10);
        $pdf->Cell(120, 6, '', 0, 0);
        $pdf->Cell(70, 6, 'Hà Nội, ngày ' . date('d/m/Y'), 0, 1, 'L');
        $pdf->Cell(120, 6, '', 0, 0);
        $pdf->Cell(70, 6, 'TRƯỞNG BAN QUẢN LÝ', 0, 1, 'C');
        $pdf->Ln(20);
        $pdf->Cell(120, 6, '', 0, 0);
        $pdf->Cell(70, 6, $manager_name, 0, 1, 'C');
        
        // Trước khi xuất PDF, đảm bảo không có output nào
        if(ob_get_length()) ob_clean();
        
        // Xuất file PDF
        $pdf->Output('Giay_bao_phi_' . $invoice['InvoiceCode'] . '.pdf', 'I');
        exit;
    } else {
        die("Không tìm thấy thông tin bảng kê!");
    }
} else {
    die("Thiếu mã bảng kê!");
}
?>