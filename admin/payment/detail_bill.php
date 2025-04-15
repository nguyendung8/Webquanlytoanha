<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Lấy invoice_code từ tham số URL
if (!isset($_GET['invoice_code'])) {
    header('location: bill_list.php');
    exit();
}

$invoice_code = mysqli_real_escape_string($conn, $_GET['invoice_code']);

// Lấy thông tin bảng kê
$invoice_query = mysqli_query($conn, "
    SELECT d.*, a.Code as ApartmentCode, a.Name as ApartmentName, a.ApartmentID, a.Area,
           b.Name as BuildingName, b.ProjectId, f.Name as FloorName,
           p.Name as ProjectName, p.ManagerId, s.Name as StaffName, m.Name as ManagerName,
           c.ContractCode, c.CreatedAt as ContractDate, c.EndDate as ContractEndDate
    FROM debtstatements d
    LEFT JOIN apartment a ON d.ApartmentID = a.ApartmentID
    LEFT JOIN Buildings b ON a.BuildingId = b.ID
    LEFT JOIN Floors f ON a.FloorId = f.ID
    LEFT JOIN Projects p ON b.ProjectId = p.ProjectID
    LEFT JOIN staffs s ON d.StaffID = s.ID
    LEFT JOIN staffs m ON p.ManagerId = m.ID
    LEFT JOIN Contracts c ON a.ContractCode = c.ContractCode
        WHERE d.InvoiceCode = '$invoice_code'
    ");
    
if (mysqli_num_rows($invoice_query) == 0) {
    header('location: bill_list.php');
    exit();
}

        $invoice = mysqli_fetch_assoc($invoice_query);
        
        // Lấy thông tin chi tiết bảng kê
        $details_query = mysqli_query($conn, "
    SELECT d.*, s.Name as ServiceName, s.TypeOfService, s.TypeOfObject
            FROM debtstatementdetail d
            LEFT JOIN services s ON d.ServiceCode = s.ServiceCode
            WHERE d.InvoiceCode = '$invoice_code'
    ORDER BY s.Name
");

// Lấy thông tin chủ hộ
        $resident_query = mysqli_query($conn, "
    SELECT r.*, u.Email, u.PhoneNumber, u.UserName
            FROM resident r
            JOIN ResidentApartment ra ON r.ID = ra.ResidentId
            LEFT JOIN users u ON r.ID = u.ResidentID
    WHERE ra.ApartmentId = '{$invoice['ApartmentID']}'
            AND ra.Relationship = 'Chủ hộ'
            LIMIT 1
        ");
        
$resident = mysqli_num_rows($resident_query) > 0 ? mysqli_fetch_assoc($resident_query) : null;

// Tính tổng số tiền đã thanh toán
$total_paid = 0;
$total_remaining = 0;
$details = [];

while ($detail = mysqli_fetch_assoc($details_query)) {
    $details[] = $detail;
    $total_paid += $detail['PaidAmount'];
    $total_remaining += $detail['RemainingBalance'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết bảng kê - <?php echo $invoice['InvoiceCode']; ?></title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/admin_style.css">
    <style>
        .page-header {
            background-color: #f5f5f5;
            padding: 15px 20px;
            color: #4a5568;
            border-bottom: 1px solid #eaeaea;
        }
        
        .breadcrumb {
            display: flex;
            align-items: center;
            margin-bottom: 0;
            padding: 0;
            background-color: transparent;
            font-size: 14px;
        }
        
        .breadcrumb a {
            color: #4a5568;
            text-decoration: none;
        }

        .detail-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .section-title {
            font-weight: 600;
            color: #476a52;
            border-bottom: 2px solid #476a52;
            padding-bottom: 8px;
            margin-bottom: 20px;
        }
        
        .info-group {
            margin-bottom: 20px;
        }
        
        .info-label {
            font-weight: 500;
            color: #555;
        }
        
        .info-value {
            font-weight: 400;
            color: #333;
        }
        
        .table-header {
            background-color: #6b8b7b !important;
            color: white;
            font-weight: 500;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }
        
        .status-paid {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-pending {
            background-color: #fff3e0;
            color: #ef6c00;
        }
        
        .status-overdue {
            background-color: #ffebee;
            color: #c62828;
        }
        
        .total-row {
            font-weight: 600;
        }
        
        .action-buttons {
            margin-top: 20px;
        }
        
        .action-buttons .btn {
            margin-right: 10px;
        }
        
        @media print {
            .no-print {
                display: none;
            }
            
            body {
                padding: 0;
                margin: 0;
            }
            
            .detail-container {
                box-shadow: none;
                padding: 0;
            }
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <div class="no-print">
        <?php include '../admin_navbar.php'; ?>
        </div>
        <div style="width: 100%;">
            <div class="no-print">
            <?php include '../admin_header.php'; ?>
            </div>
            <div class="container-fluid p-4">
                <div class="page-header mb-4 no-print">
                    <h2 style="font-weight: bold; color: #476a52; margin-bottom: 10px; text-transform: uppercase;">
                        CHI TIẾT BẢNG KÊ
                    </h2>
                    <div class="breadcrumb">
                        <a href="../dashboard.php">Trang chủ</a>
                        <span style="margin: 0 8px;">›</span>
                        <span>Quản lý thu phí</span>
                        <span style="margin: 0 8px;">›</span>
                        <a href="bill_list.php">Danh sách bảng kê</a>
                        <span style="margin: 0 8px;">›</span>
                        <span>Chi tiết bảng kê</span>
                    </div>
                </div>

                <div class="detail-container">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h3 class="mb-3"><?php echo $invoice['InvoiceCode']; ?></h3>
                            <div class="status-badge 
                                <?php
                                    switch($invoice['Status']){
                                        case 'Đã thanh toán': echo 'status-paid'; break;
                                        case 'Chờ thanh toán': echo 'status-pending'; break;
                                        case 'Quá hạn': echo 'status-overdue'; break;
                                        default: echo 'status-pending';
                                    }
                                ?>">
                                <?php echo $invoice['Status']; ?>
                            </div>
                        </div>
                        <div class="col-md-6 text-md-end no-print">
                            <a href="export_invoice_pdf.php?invoice_code=<?php echo $invoice['InvoiceCode']; ?>" class="btn btn-primary" target="_blank">
                                <i class="fas fa-file-pdf"></i> Xuất PDF
                            </a>
                            <a href="bill_list.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Quay lại
                            </a>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h4 class="section-title">Thông tin bảng kê</h4>
                            <div class="row info-group">
                                <div class="col-md-4 info-label">Kỳ bảng kê:</div>
                                <div class="col-md-8 info-value"><?php echo $invoice['InvoicePeriod']; ?></div>
                            </div>
                            <div class="row info-group">
                                <div class="col-md-4 info-label">Ngày lập:</div>
                                <div class="col-md-8 info-value"><?php echo date('d/m/Y', strtotime($invoice['IssueDate'])); ?></div>
                            </div>
                            <div class="row info-group">
                                <div class="col-md-4 info-label">Hạn thanh toán:</div>
                                <div class="col-md-8 info-value"><?php echo date('d/m/Y', strtotime($invoice['DueDate'])); ?></div>
                            </div>
                            <div class="row info-group">
                                <div class="col-md-4 info-label">Nhân viên lập:</div>
                                <div class="col-md-8 info-value"><?php echo $invoice['StaffName']; ?></div>
                            </div>
                            <div class="row info-group">
                                <div class="col-md-4 info-label">Trạng thái:</div>
                                <div class="col-md-8 info-value">
                                    <div class="status-badge 
                                        <?php
                                            switch($invoice['Status']){
                                                case 'Đã thanh toán': echo 'status-paid'; break;
                                                case 'Chờ thanh toán': echo 'status-pending'; break;
                                                case 'Quá hạn': echo 'status-overdue'; break;
                                                default: echo 'status-pending';
                                            }
                                        ?>">
                                        <?php echo $invoice['Status']; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h4 class="section-title">Tổng quan phí</h4>
                            <div class="row info-group">
                                <div class="col-md-4 info-label">Nợ cũ:</div>
                                <div class="col-md-8 info-value"><?php echo number_format($invoice['OutstandingDebt']); ?> VNĐ</div>
                            </div>
                            <div class="row info-group">
                                <div class="col-md-4 info-label">Giảm giá:</div>
                                <div class="col-md-8 info-value"><?php echo number_format($invoice['Discount']); ?> VNĐ</div>
                            </div>
                            <div class="row info-group">
                                <div class="col-md-4 info-label">Tổng tiền:</div>
                                <div class="col-md-8 info-value fw-bold"><?php echo number_format($invoice['Total']); ?> VNĐ</div>
                            </div>
                            <div class="row info-group">
                                <div class="col-md-4 info-label">Đã thanh toán:</div>
                                <div class="col-md-8 info-value"><?php echo number_format($invoice['PaidAmount']); ?> VNĐ</div>
                            </div>
                            <div class="row info-group">
                                <div class="col-md-4 info-label">Còn lại:</div>
                                <div class="col-md-8 info-value fw-bold"><?php echo number_format($invoice['RemainingBalance']); ?> VNĐ</div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h4 class="section-title">Thông tin căn hộ</h4>
                            <div class="row info-group">
                                <div class="col-md-4 info-label">Mã căn hộ:</div>
                                <div class="col-md-8 info-value"><?php echo $invoice['ApartmentCode']; ?></div>
                            </div>
                            <div class="row info-group">
                                <div class="col-md-4 info-label">Tên căn hộ:</div>
                                <div class="col-md-8 info-value"><?php echo $invoice['ApartmentName']; ?></div>
                            </div>
                            <div class="row info-group">
                                <div class="col-md-4 info-label">Tòa nhà:</div>
                                <div class="col-md-8 info-value"><?php echo $invoice['BuildingName']; ?></div>
                            </div>
                            <div class="row info-group">
                                <div class="col-md-4 info-label">Tầng:</div>
                                <div class="col-md-8 info-value"><?php echo $invoice['FloorName']; ?></div>
                            </div>
                            <div class="row info-group">
                                <div class="col-md-4 info-label">Dự án:</div>
                                <div class="col-md-8 info-value"><?php echo $invoice['ProjectName']; ?></div>
                            </div>
                            <div class="row info-group">
                                <div class="col-md-4 info-label">Diện tích:</div>
                                <div class="col-md-8 info-value"><?php echo $invoice['Area']; ?> m²</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h4 class="section-title">Thông tin chủ hộ</h4>
                            <?php if ($resident): ?>
                            <div class="row info-group">
                                <div class="col-md-4 info-label">Tên chủ hộ:</div>
                                <div class="col-md-8 info-value"><?php echo $resident['UserName']; ?></div>
                            </div>
                            <div class="row info-group">
                                <div class="col-md-4 info-label">Số điện thoại:</div>
                                <div class="col-md-8 info-value"><?php echo $resident['PhoneNumber']; ?></div>
                            </div>
                            <div class="row info-group">
                                <div class="col-md-4 info-label">Email:</div>
                                <div class="col-md-8 info-value"><?php echo $resident['Email']; ?></div>
                            </div>
                            <?php else: ?>
                            <div class="row info-group">
                                <div class="col-12 info-value">Không có thông tin chủ hộ</div>
                            </div>
                            <?php endif; ?>
                            
                            <h4 class="section-title mt-4">Thông tin hợp đồng</h4>
                            <div class="row info-group">
                                <div class="col-md-4 info-label">Mã hợp đồng:</div>
                                <div class="col-md-8 info-value"><?php echo $invoice['ContractCode'] ?? 'N/A'; ?></div>
                            </div>
                            <div class="row info-group">
                                <div class="col-md-4 info-label">Ngày ký:</div>
                                <div class="col-md-8 info-value"><?php echo $invoice['ContractDate'] ? date('d/m/Y', strtotime($invoice['ContractDate'])) : 'N/A'; ?></div>
                            </div>
                            <div class="row info-group">
                                <div class="col-md-4 info-label">Ngày hết hạn:</div>
                                <div class="col-md-8 info-value"><?php echo $invoice['ContractEndDate'] ? date('d/m/Y', strtotime($invoice['ContractEndDate'])) : 'N/A'; ?></div>
                        </div>
                    </div>
                </div>

                    <div class="row">
                        <div class="col-12">
                            <h4 class="section-title">Chi tiết dịch vụ</h4>
                <div class="table-responsive">
                                <table class="table table-striped table-bordered">
                                    <thead class="table-header">
                                        <tr>
                                            <th width="5%">STT</th>
                                            <th width="20%">Dịch vụ</th>
                                            <th width="10%">Loại dịch vụ</th>
                                            <th width="10%">Số lượng</th>
                                            <th width="15%">Đơn giá</th>
                                            <th width="10%">Giảm giá</th>
                                            <th width="15%">Thành tiền</th>
                                            <th width="15%">Đã thanh toán</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                                        $i = 1;
                                        $total_amount = 0;
                                        foreach($details as $detail): 
                                            $amount = $detail['Quantity'] * $detail['UnitPrice'] - $detail['Discount'];
                                            $total_amount += $amount;
                            ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                            <td><?php echo $detail['ServiceName']; ?></td>
                                            <td><?php echo $detail['TypeOfService']; ?></td>
                                            <td><?php echo $detail['Quantity']; ?></td>
                                            <td class="text-end"><?php echo number_format($detail['UnitPrice']); ?> VNĐ</td>
                                            <td class="text-end"><?php echo number_format($detail['Discount']); ?> VNĐ</td>
                                            <td class="text-end"><?php echo number_format($amount); ?> VNĐ</td>
                                            <td class="text-end"><?php echo number_format($detail['PaidAmount']); ?> VNĐ</td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <tr class="total-row">
                                            <td colspan="6" class="text-end">Tổng cộng:</td>
                                            <td class="text-end"><?php echo number_format($total_amount); ?> VNĐ</td>
                                            <td class="text-end"><?php echo number_format($total_paid); ?> VNĐ</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                        </div>
                    </div>

                    <!-- <div class="row mt-4">
                        <div class="col-md-8">
                            <h4 class="section-title">Lịch sử thanh toán</h4>
                            <div class="table-responsive">
                                <?php
                                // Lấy lịch sử thanh toán nếu có
                                $payment_history_query = mysqli_query($conn, "
                                    SELECT * FROM payments 
                                    WHERE InvoiceCode = '$invoice_code'
                                    ORDER BY PaymentDate DESC
                                ");
                                
                                if(mysqli_num_rows($payment_history_query) > 0):
                                ?>
                                <table class="table table-striped table-bordered">
                                    <thead class="table-header">
                                        <tr>
                                            <th>STT</th>
                                            <th>Ngày thanh toán</th>
                                            <th>Phương thức</th>
                                            <th>Số tiền</th>
                                            <th>Ghi chú</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $j = 1;
                                        while($payment = mysqli_fetch_assoc($payment_history_query)): 
                                        ?>
                                        <tr>
                                            <td><?php echo $j++; ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($payment['PaymentDate'])); ?></td>
                                            <td><?php echo $payment['PaymentMethod']; ?></td>
                                            <td class="text-end"><?php echo number_format($payment['Amount']); ?> VNĐ</td>
                                            <td><?php echo $payment['Note']; ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                                <?php else: ?>
                                <p>Chưa có lịch sử thanh toán</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <h4 class="section-title">Thông tin bổ sung</h4>
                            <div class="card">
                                <div class="card-body">
                                    <div class="row info-group">
                                        <div class="col-md-5 info-label">Trưởng ban QL:</div>
                                        <div class="col-md-7 info-value"><?php echo $invoice['ManagerName']; ?></div>
                                    </div>
                                    <div class="row info-group">
                                        <div class="col-md-5 info-label">Ghi chú:</div>
                                        <div class="col-md-7 info-value"><?php echo $invoice['Note'] ?? 'Không có ghi chú'; ?></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="action-buttons no-print">
                                <?php if($invoice['Status'] != 'Đã thanh toán'): ?>
                                <a href="create_payment.php?invoice_code=<?php echo $invoice_code; ?>" class="btn btn-success w-100 mb-2">
                                    <i class="fas fa-money-bill"></i> Tạo thanh toán
                                </a>
                                <?php endif; ?>
                                
                                <button class="btn btn-warning w-100 mb-2 send-notification" 
                                        data-invoice-code="<?php echo $invoice_code; ?>">
                                    <i class="fas fa-bell"></i> Gửi thông báo
                                </button>
                                
                                <button class="btn btn-primary w-100" onclick="window.print()">
                                    <i class="fas fa-print"></i> In bảng kê
                                </button>
                            </div>
                        </div>
                    </div> -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Xử lý khi click nút gửi thông báo
    $(document).on('click', '.send-notification', function() {
        const invoiceCode = $(this).data('invoice-code');
            
            if(confirm('Bạn có chắc chắn muốn gửi thông báo nhắc thu phí cho căn hộ này?')) {
                // Submit form để gửi thông báo
                const form = $('<form method="POST" action="bill_list.php"></form>');
                form.append('<input type="hidden" name="invoice_code" value="' + invoiceCode + '">');
                form.append('<input type="hidden" name="send_notification" value="1">');
                $('body').append(form);
                form.submit();
        }
    });
    </script>
</body>
</html>