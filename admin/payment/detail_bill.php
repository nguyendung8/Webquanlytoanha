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

// Xử lý AJAX request
if(isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if($_POST['action'] === 'get') {
        $invoice_code = mysqli_real_escape_string($conn, $_POST['invoiceCode']);
        $service_code = mysqli_real_escape_string($conn, $_POST['serviceCode']);

        $query = "
            SELECT 
                d.InvoiceCode,
                d.InvoicePeriod,
                d.DueDate,
                dd.ServiceCode,
                s.Name as ServiceName,
                dd.Quantity,
                dd.UnitPrice,
                dd.Discount,
                dd.PaidAmount,
                dd.RemainingBalance,
                dd.IssueDate as StartDate,
                d.DueDate as EndDate
            FROM debtstatements d
            JOIN debtstatementdetail dd ON d.InvoiceCode = dd.InvoiceCode
            JOIN services s ON dd.ServiceCode = s.ServiceCode
            WHERE d.InvoiceCode = '$invoice_code' 
            AND dd.ServiceCode = '$service_code'
        ";

        $result = mysqli_query($conn, $query);

        if ($result && $row = mysqli_fetch_assoc($result)) {
            echo json_encode([
                'success' => true,
                'data' => [
                    'InvoiceCode' => $row['InvoiceCode'],
                    'InvoicePeriod' => $row['InvoicePeriod'],
                    'ServiceCode' => $row['ServiceCode'],
                    'ServiceName' => $row['ServiceName'],
                    'Quantity' => (int)$row['Quantity'],
                    'UnitPrice' => (int)$row['UnitPrice'],
                    'Discount' => (int)$row['Discount'],
                    'PaidAmount' => (int)$row['PaidAmount'],
                    'RemainingBalance' => (int)$row['RemainingBalance'],
                    'StartDate' => $row['StartDate'],
                    'EndDate' => $row['EndDate']
                ]
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Không tìm thấy dữ liệu'
            ]);
        }
        exit;
    }
    
    if($_POST['action'] === 'update') {
        $service_code = mysqli_real_escape_string($conn, $_POST['serviceCode']);
        $invoice_code = mysqli_real_escape_string($conn, $_POST['invoiceCode']);
        $quantity = (int)$_POST['quantity'];
        $unit_price = (int)$_POST['unitPrice'];
        $discount = (int)$_POST['discount'];
        $discount_reason = mysqli_real_escape_string($conn, $_POST['discountReason']);

        // Kiểm tra PaidAmount
        $check_query = "SELECT PaidAmount FROM debtstatements WHERE InvoiceCode = '$invoice_code'";
        $check_result = mysqli_query($conn, $check_query);
        $check_row = mysqli_fetch_assoc($check_result);

        if ($check_row['PaidAmount'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Không thể chỉnh sửa bảng kê đã thanh toán']);
            exit;
        }

        $amount = $quantity * $unit_price - $discount;

        mysqli_begin_transaction($conn);

        try {
            $update_detail = mysqli_query($conn, "
                UPDATE debtstatementdetail 
                SET Quantity = $quantity,
                    UnitPrice = $unit_price,
                    Discount = $discount,
                    RemainingBalance = $amount
                WHERE InvoiceCode = '$invoice_code' 
                AND ServiceCode = '$service_code'
            ");

            if (!$update_detail) {
                throw new Exception("Lỗi khi cập nhật chi tiết dịch vụ");
            }

            $update_total = mysqli_query($conn, "
                UPDATE debtstatements d
                SET Total = (
                    SELECT SUM(Quantity * UnitPrice - Discount)
                    FROM debtstatementdetail
                    WHERE InvoiceCode = d.InvoiceCode
                ),
                RemainingBalance = (
                    SELECT SUM(Quantity * UnitPrice - Discount)
                    FROM debtstatementdetail
                    WHERE InvoiceCode = d.InvoiceCode
                )
                WHERE InvoiceCode = '$invoice_code'
            ");

            if (!$update_total) {
                throw new Exception("Lỗi khi cập nhật tổng tiền");
            }

            mysqli_commit($conn);
            echo json_encode(['success' => true]);

        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
        }
        
        .modal-dialog {
            background: white;
            width: 500px;
            margin: 50px auto;
            padding: 20px;
            border-radius: 8px;
            z-index: 10000;
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
                                            <?php if ($invoice['PaidAmount'] == 0): ?>
                                            <th width="10%">Thao tác</th>
                                            <?php endif; ?>
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
                                                <td class="editable" data-field="Quantity"><?php echo $detail['Quantity']; ?></td>
                                                <td class="text-end editable" data-field="UnitPrice"><?php echo number_format($detail['UnitPrice']); ?> VNĐ</td>
                                                <td class="text-end editable" data-field="Discount"><?php echo number_format($detail['Discount']); ?> VNĐ</td>
                                                <td class="text-end"><?php echo number_format($amount); ?> VNĐ</td>
                                                <td class="text-end"><?php echo number_format($detail['PaidAmount']); ?> VNĐ</td>
                                                <?php if ($invoice['PaidAmount'] == 0): ?>
                                                <td>
                                                    <a href="edit_service_detail.php?invoice_code=<?php echo $invoice_code; ?>&service_code=<?php echo $detail['ServiceCode']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="total-row">
                                            <td colspan="6" class="text-end">Tổng cộng:</td>
                                            <td class="text-end"><?php echo number_format($total_amount); ?> VNĐ</td>
                                            <td class="text-end"><?php echo number_format($total_paid); ?> VNĐ</td>
                                            <?php if ($invoice['PaidAmount'] == 0): ?>
                                            <td></td>
                                            <?php endif; ?>
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

    <!-- Modal -->
    <div class="modal fade" id="editServiceModal" tabindex="-1" aria-labelledby="editServiceModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="editServiceModalLabel">CHI TIẾT BẢNG KÊ DỊCH VỤ</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form id="editServiceForm">
                <input type="hidden" id="serviceCode" name="serviceCode">
                
                <div class="mb-3">
                    <label class="form-label">Kỳ bảng kê</label>
                    <input type="text" id="invoicePeriod" class="form-control" value="<?php echo $invoice['InvoicePeriod']; ?>" readonly>
                </div>

                <div class="mb-3">
                    <label class="form-label">Dịch vụ</label>
                    <input type="text" id="serviceName" class="form-control" readonly>
                </div>

                <div class="mb-3">
                    <label class="form-label">Đơn giá <span class="text-danger">*</span></label>
                    <input type="number" id="unitPrice" name="unitPrice" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Số lượng <span class="text-danger">*</span></label>
                    <input type="number" id="quantity" name="quantity" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Phát sinh</label>
                    <input type="text" id="total" class="form-control" readonly>
                </div>

                <div class="mb-3">
                    <label class="form-label">Giảm trừ</label>
                    <input type="number" id="discount" name="discount" class="form-control" value="0">
                </div>

                <div class="mb-3">
                    <label class="form-label">Thành tiền</label>
                    <input type="text" id="finalAmount" class="form-control" readonly>
                </div>

                <div class="mb-3">
                    <label class="form-label">Lý do giảm trừ</label>
                    <textarea id="discountReason" name="discountReason" class="form-control" rows="3"></textarea>
                </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="cancelBtn">Huỷ</button>
            <button type="button" class="btn btn-success" id="saveBtn">Cập nhật</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Modal và các phần tử
        const myModal = new bootstrap.Modal(document.getElementById('editServiceModal'));
        const serviceCodeInput = document.getElementById('serviceCode');
        const serviceNameInput = document.getElementById('serviceName');
        const quantityInput = document.getElementById('quantity');
        const unitPriceInput = document.getElementById('unitPrice');
        const discountInput = document.getElementById('discount');
        const totalInput = document.getElementById('total');
        const finalAmountInput = document.getElementById('finalAmount');
        const saveBtn = document.getElementById('saveBtn');
        
        // Hàm tính toán tổng tiền
        function calculateTotal() {
            const quantity = parseFloat(quantityInput.value) || 0;
            const unitPrice = parseFloat(unitPriceInput.value) || 0;
            const discount = parseFloat(discountInput.value) || 0;
            
            const total = quantity * unitPrice;
            const finalAmount = total - discount;
            
            totalInput.value = total.toLocaleString('vi-VN');
            finalAmountInput.value = finalAmount.toLocaleString('vi-VN');
        }
        
        // Thêm event listener cho các trường cần tính toán lại
        quantityInput.addEventListener('input', calculateTotal);
        unitPriceInput.addEventListener('input', calculateTotal);
        discountInput.addEventListener('input', calculateTotal);
        
        // Sự kiện click cho nút edit
        const editButtons = document.querySelectorAll('.edit-btn');
        console.log('Số lượng nút edit:', editButtons.length);
        
        editButtons.forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('Đã click nút edit');
                
                const serviceCode = this.getAttribute('data-service-code');
                const serviceName = this.getAttribute('data-service-name');
                const quantity = this.getAttribute('data-quantity');
                const unitPrice = this.getAttribute('data-unit-price');
                const discount = this.getAttribute('data-discount');
                
                console.log('Service Code:', serviceCode);
                
                // Điền dữ liệu vào form
                serviceCodeInput.value = serviceCode;
                serviceNameInput.value = serviceName;
                quantityInput.value = quantity;
                unitPriceInput.value = unitPrice;
                discountInput.value = discount;
                
                // Tính toán các giá trị
                calculateTotal();
                
                // Hiển thị modal
                myModal.show();
            });
        });
        
        // Lưu thay đổi
        saveBtn.addEventListener('click', function() {
            const formData = {
                action: 'update',
                serviceCode: serviceCodeInput.value,
                invoiceCode: '<?php echo $invoice_code; ?>',
                quantity: quantityInput.value,
                unitPrice: unitPriceInput.value,
                discount: discountInput.value,
                discountReason: document.getElementById('discountReason').value
            };
            
            // Tạo FormData để gửi
            const urlEncodedData = new URLSearchParams();
            for (const [key, value] of Object.entries(formData)) {
                urlEncodedData.append(key, value);
            }
            
            // Gửi request
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: urlEncodedData.toString()
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Cập nhật thành công!');
                    location.reload();
                } else {
                    alert('Có lỗi khi cập nhật: ' + data.message);
                }
            })
            .catch(error => {
                alert('Có lỗi xảy ra khi cập nhật dữ liệu');
                console.error(error);
            });
        });
    });
    </script>
</body>
</html>