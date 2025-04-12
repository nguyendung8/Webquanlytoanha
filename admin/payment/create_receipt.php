<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Lấy danh sách căn hộ có bảng kê chờ thanh toán
$select_apartments = mysqli_query($conn, "
    SELECT DISTINCT 
        a.ApartmentID, 
        a.Code, 
        a.Name, 
        b.Name as BuildingName,
        d.InvoicePeriod,
        SUM(dd.RemainingBalance) as TotalDebt
    FROM apartment a 
    JOIN Buildings b ON a.BuildingId = b.ID
    JOIN debtstatements d ON a.ApartmentID = d.ApartmentID
    JOIN debtstatementdetail dd ON d.InvoiceCode = dd.InvoiceCode
    WHERE d.Status = 'Chờ thanh toán'
    AND dd.RemainingBalance > 0
    GROUP BY a.ApartmentID, d.InvoicePeriod
    ORDER BY b.Name, a.Code
");

// Lấy danh sách dịch vụ
$select_services = mysqli_query($conn, "SELECT ServiceCode, Name FROM services WHERE Status = 'active'");

// Thêm function này sau phần khai báo database
function getResidentsByApartment($conn, $apartment_id) {
    $query = "
        SELECT 
            r.ID,
            r.NationalId,
            u.UserName,
            u.Email,
            u.PhoneNumber,
            ra.Relationship
        FROM resident r
        JOIN ResidentApartment ra ON r.ID = ra.ResidentId
        LEFT JOIN users u ON r.ID = u.ResidentID
        WHERE ra.ApartmentId = '$apartment_id'
        ORDER BY CASE WHEN ra.Relationship = 'Chủ hộ' THEN 0 ELSE 1 END
    ";
    return mysqli_query($conn, $query);
}

// Xử lý AJAX lấy danh sách dịch vụ
if(isset($_POST['get_services']) && isset($_POST['apartment_id'])) {
    $apartment_id = mysqli_real_escape_string($conn, $_POST['apartment_id']);
    
    // Lấy danh sách dịch vụ
    $services_query = "
        SELECT DISTINCT 
            s.ServiceCode,
            s.Name as ServiceName,
            dd.Quantity,
            dd.UnitPrice,
            dd.Discount,
            dd.RemainingBalance,
            d.InvoiceCode,
            d.InvoicePeriod
        FROM debtstatements d
        JOIN debtstatementdetail dd ON d.InvoiceCode = dd.InvoiceCode
        JOIN services s ON dd.ServiceCode = s.ServiceCode
        WHERE d.ApartmentID = '$apartment_id'
        AND d.Status = 'Chờ thanh toán'
        ORDER BY d.IssueDate DESC
    ";
    
    $services_result = mysqli_query($conn, $services_query);
    $services = [];
    while($row = mysqli_fetch_assoc($services_result)) {
        $services[] = $row;
    }

    // Lấy danh sách cư dân
    $residents_result = getResidentsByApartment($conn, $apartment_id);
    $residents = [];
    while($row = mysqli_fetch_assoc($residents_result)) {
        $residents[] = $row;
    }
    
    echo json_encode([
        'services' => $services,
        'residents' => $residents
    ]);
    exit;
}

// Thêm vào phần xử lý AJAX
if(isset($_POST['get_project_services']) && isset($_POST['apartment_id'])) {
    $apartment_id = mysqli_real_escape_string($conn, $_POST['apartment_id']);
    
    // Lấy thông tin căn hộ và dịch vụ của dự án với giá từ bảng PriceList
    $query = "
        SELECT 
            a.Code as ApartmentCode,
            COALESCE(u.UserName, r.NationalId) as OwnerName,
            s.ServiceCode,
            s.Name as ServiceName,
            s.Cycle,
            s.SwitchDay,
            pl.Price as ServicePrice
        FROM apartment a
        JOIN Buildings b ON a.BuildingId = b.ID
        JOIN Projects p ON b.ProjectId = p.ProjectID
        LEFT JOIN ResidentApartment ra ON a.ApartmentID = ra.ApartmentId AND ra.Relationship = 'Chủ hộ'
        LEFT JOIN resident r ON ra.ResidentId = r.ID
        LEFT JOIN users u ON r.ID = u.ResidentID
        JOIN services s ON p.ProjectID = s.ProjectId
        LEFT JOIN ServicePrice sp ON s.ServiceCode = sp.ServiceId
        LEFT JOIN pricelist pl ON sp.PriceId = pl.ID AND pl.Status = 'active'
        WHERE a.ApartmentID = '$apartment_id'
        AND s.Status = 'active'
        GROUP BY s.ServiceCode
        ORDER BY s.Name
    ";
    
    $result = mysqli_query($conn, $query);
    $services = [];
    $apartment = null;
    
    while($row = mysqli_fetch_assoc($result)) {
        if(!$apartment) {
            $apartment = [
                'ApartmentCode' => $row['ApartmentCode'],
                'OwnerName' => $row['OwnerName']
            ];
        }
        $services[] = [
            'ServiceCode' => $row['ServiceCode'],
            'Name' => $row['ServiceName'],
            'StartPrice' => $row['ServicePrice'] ?? 0, // Sử dụng giá từ bảng PriceList
            'Cycle' => $row['Cycle'],
            'SwitchDay' => $row['SwitchDay']
        ];
    }
    
    echo json_encode([
        'apartment' => $apartment,
        'services' => $services
    ]);
    exit;
}

// Xử lý khi submit form
if(isset($_POST['submit'])) {
    $apartment_id = mysqli_real_escape_string($conn, $_POST['apartment_id']);
    $service_type = mysqli_real_escape_string($conn, $_POST['service_type']);
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $receipt_type = mysqli_real_escape_string($conn, $_POST['receipt_type']);
    $amount = mysqli_real_escape_string($conn, $_POST['amount']);
    $total_amount = mysqli_real_escape_string($conn, $_POST['total_amount']);
    $payer = mysqli_real_escape_string($conn, $_POST['payer']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $accounting_date = mysqli_real_escape_string($conn, $_POST['accounting_date']);
    $content = mysqli_real_escape_string($conn, $_POST['content']);
    
    // Tạo mã chứng từ theo quy tắc: PT_YYYYMMDD_XXX
    $current_date = date('Ymd'); // Format YYYYMMDD
    // Tìm mã chứng từ cuối cùng trong ngày hiện tại để tăng số thứ tự
    $last_receipt_query = mysqli_query($conn, "
        SELECT ReceiptID 
        FROM Receipt 
        WHERE ReceiptID LIKE 'PT\_$current_date\_%' 
        ORDER BY ReceiptID DESC 
        LIMIT 1
    ");

    if (mysqli_num_rows($last_receipt_query) > 0) {
        $last_receipt = mysqli_fetch_assoc($last_receipt_query);
        $last_id = $last_receipt['ReceiptID'];
        // Lấy số thứ tự từ mã cuối cùng
        $last_sequence = intval(substr($last_id, -3));
        // Tăng số thứ tự lên 1
        $new_sequence = $last_sequence + 1;
    } else {
        // Nếu không có mã nào trong ngày hiện tại, bắt đầu từ 1
        $new_sequence = 1;
    }

    // Format số thứ tự thành chuỗi 3 chữ số (001, 002, ...)
    $sequence_str = str_pad($new_sequence, 3, '0', STR_PAD_LEFT);
    // Tạo mã chứng từ hoàn chỉnh
    $receipt_id = "PT_" . $current_date . "_" . $sequence_str;
    
    // Bắt đầu transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Tắt kiểm tra khóa ngoại
        mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");
        
        // Thêm phiếu thu mới
        $insert_receipt = mysqli_query($conn, "INSERT INTO Receipt (
            ReceiptID, PaymentMethod, TransactionType, ReceiptType, 
            Total, AmountDue, Payer, Address, AccountingDate, 
            Content, StaffID, ApartmentID, Status
        ) VALUES (
            '$receipt_id', '$payment_method', '$service_type', '$receipt_type',
            $amount, $total_amount, '$payer', '$address', '$accounting_date',
            '$content', $admin_id, '$apartment_id', 'completed'
        )");

        if (!$insert_receipt) {
            throw new Exception("Không thể tạo phiếu thu: " . mysqli_error($conn));
        }

        $excess_amount = 0; // Biến theo dõi số tiền thanh toán thừa
        
        // Thêm chi tiết phiếu thu và cập nhật bảng kê
        if(isset($_POST['services']) && is_array($_POST['services'])) {
            foreach($_POST['services'] as $service) {
                $service_code = mysqli_real_escape_string($conn, $service['code']);
                $invoice_code = isset($service['invoice_code']) ? mysqli_real_escape_string($conn, $service['invoice_code']) : null;
                $incurred = mysqli_real_escape_string($conn, $service['incurred']);
                $discount = mysqli_real_escape_string($conn, $service['discount']);
                $payment = mysqli_real_escape_string($conn, $service['payment']);
                
                // Thêm chi tiết phiếu thu
                $insert_detail = mysqli_query($conn, "INSERT INTO ReceiptDetails (
                    ReceiptID, ServiceCode, Incurred, Discount, Payment
                ) VALUES (
                    '$receipt_id', '$service_code', $incurred, $discount, $payment
                )");
                
                if (!$insert_detail) {
                    throw new Exception("Không thể tạo chi tiết phiếu thu: " . mysqli_error($conn));
                }
                
                // Nếu có mã hóa đơn, cập nhật bảng kê
                if ($invoice_code) {
                    // Lấy thông tin dư nợ hiện tại
                    $current_debt_query = mysqli_query($conn, "
                        SELECT RemainingBalance 
                        FROM debtstatementdetail 
                        WHERE InvoiceCode = '$invoice_code' 
                        AND ServiceCode = '$service_code'
                    ");
                    
                    if (mysqli_num_rows($current_debt_query) > 0) {
                        $current_debt = mysqli_fetch_assoc($current_debt_query);
                        $remaining_balance = $current_debt['RemainingBalance'];
                        
                        // Kiểm tra nếu thanh toán vượt quá dư nợ
                        if ($payment > $remaining_balance) {
                            // Số tiền trả thừa
                            $excess = $payment - $remaining_balance;
                            $excess_amount += $excess;
                            
                            // Cập nhật số tiền đã trả và dư nợ (reset về 0)
                            $update_detail = mysqli_query($conn, "
                                UPDATE debtstatementdetail 
                                SET PaidAmount = PaidAmount + $remaining_balance,
                                    RemainingBalance = 0
                                WHERE InvoiceCode = '$invoice_code' 
                                AND ServiceCode = '$service_code'
                            ");
                        } else {
                            // Cập nhật số tiền đã trả và dư nợ bình thường
                            $update_detail = mysqli_query($conn, "
                                UPDATE debtstatementdetail 
                                SET PaidAmount = PaidAmount + $payment,
                                    RemainingBalance = RemainingBalance - $payment
                                WHERE InvoiceCode = '$invoice_code' 
                                AND ServiceCode = '$service_code'
                            ");
                        }
                        
                        if (!$update_detail) {
                            throw new Exception("Không thể cập nhật chi tiết bảng kê: " . mysqli_error($conn));
                        }
                    }
                }
            }
            
            // Kiểm tra và cập nhật trạng thái bảng kê
            $invoice_check_query = mysqli_query($conn, "
                SELECT DISTINCT d.InvoiceCode
                FROM debtstatements d
                JOIN debtstatementdetail dd ON d.InvoiceCode = dd.InvoiceCode
                WHERE d.ApartmentID = '$apartment_id'
                AND d.Status = 'Chờ thanh toán'
                GROUP BY d.InvoiceCode
            ");
            
            while ($invoice = mysqli_fetch_assoc($invoice_check_query)) {
                $invoice_code = $invoice['InvoiceCode'];
                
                // Kiểm tra tổng dư nợ của bảng kê
                $debt_check = mysqli_query($conn, "
                    SELECT SUM(RemainingBalance) as TotalRemaining
                    FROM debtstatementdetail
                    WHERE InvoiceCode = '$invoice_code'
                ");
                
                $debt_result = mysqli_fetch_assoc($debt_check);
                $total_remaining = $debt_result['TotalRemaining'];
                
                // Nếu không còn dư nợ, cập nhật trạng thái bảng kê
                if ($total_remaining <= 0) {
                    $update_invoice = mysqli_query($conn, "
                        UPDATE debtstatements 
                        SET Status = 'Đã thanh toán', 
                            PaidAmount = Total,
                            RemainingBalance = 0
                        WHERE InvoiceCode = '$invoice_code'
                    ");
                    
                    if (!$update_invoice) {
                        throw new Exception("Không thể cập nhật trạng thái bảng kê: " . mysqli_error($conn));
                    }
                } else {
                    // Cập nhật số tiền đã trả và còn nợ trong bảng kê chính
                    $update_paid = mysqli_query($conn, "
                        UPDATE debtstatements 
                        SET PaidAmount = (SELECT SUM(PaidAmount) FROM debtstatementdetail WHERE InvoiceCode = '$invoice_code'),
                            RemainingBalance = (SELECT SUM(RemainingBalance) FROM debtstatementdetail WHERE InvoiceCode = '$invoice_code')
                        WHERE InvoiceCode = '$invoice_code'
                    ");
                    
                    if (!$update_paid) {
                        throw new Exception("Không thể cập nhật số tiền đã trả trong bảng kê: " . mysqli_error($conn));
                    }
                }
            }
            
            // Xử lý thanh toán thừa (nếu có)
            if ($excess_amount > 0) {
                $insert_excess = mysqli_query($conn, "
                    INSERT INTO ExcessPayment (
                        OccurrenceDate, Total, ApartmentID, ReceiptID, Description, Status
                    ) VALUES (
                        '$accounting_date', $excess_amount, '$apartment_id', '$receipt_id', 
                        'Thanh toán thừa từ phiếu thu $receipt_id', 'active'
                    )
                ");
                
                if (!$insert_excess) {
                    throw new Exception("Không thể tạo thanh toán thừa: " . mysqli_error($conn));
                }
            }
        }
        
        // Bật lại kiểm tra khóa ngoại
        mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");
        
        mysqli_commit($conn);
        $success_msg = "Tạo phiếu thu thành công!";
        if ($excess_amount > 0) {
            $success_msg .= " Có thanh toán thừa: " . number_format($excess_amount) . " VNĐ.";
        }
        
    } catch (Exception $e) {
        // Bật lại kiểm tra khóa ngoại trước khi rollback
        mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");
        mysqli_rollback($conn);
        $error_msg = "Có lỗi xảy ra: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lập phiếu thu</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .page-header {
            background-color: #f5f5f5;
            padding: 15px 20px;
            margin-bottom: 20px;
        }
        .form-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .section-header {
            background: #6b8b7b;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .required:after {
            content: " *";
            color: red;
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <?php include '../admin_navbar.php'; ?>
        <div class="flex-grow-1">
            <?php include '../admin_header.php'; ?>
            
            <div class="container-fluid p-4">
                <!-- Page Header -->
                <div class="page-header">
                    <h2 class="text-uppercase fw-bold" style="color: #476a52;">Lập phiếu thu</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a style="text-decoration: none; color: #476a52;" href="/admin/dashboard.php">Trang chủ</a></li>
                            <li class="breadcrumb-item"><a style="text-decoration: none; color: #476a52;" href="payment_receipt.php">Quản lý phiếu thu/chi</a></li>
                            <li class="breadcrumb-item active" style="color: #476a52;">Lập phiếu thu</li>
                        </ol>
                    </nav>
                </div>

                <?php
                if(isset($success_msg)) {
                    echo '<div class="alert alert-success">'.$success_msg.'</div>';
                }
                if(isset($error_msg)) {
                    echo '<div class="alert alert-danger">'.$error_msg.'</div>';
                }
                ?>

                <form method="POST" id="receiptForm">
                    <div class="form-section">
                        <div class="section-header">
                            Lập phiếu thu
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label required">Căn hộ</label>
                                <select class="form-select" name="apartment_id" required>
                                    <option value="">Chọn căn hộ</option>
                                    <?php while($apt = mysqli_fetch_assoc($select_apartments)) { ?>
                                        <option value="<?php echo $apt['ApartmentID']; ?>">
                                            <?php echo $apt['Code'] . ' - ' . $apt['Name']; ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label required">Nghiệp vụ thực hiện</label>
                                <select class="form-select" name="service_type" id="serviceType" required>
                                    <option value="">Chọn nghiệp vụ</option>
                                    <option value="Thu">Thu tiền dịch vụ</option>
                                    <option value="Chi">Hạch toán dịch vụ</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label required">Hình thức thu tiền</label>
                                <select class="form-select" name="payment_method" id="paymentMethod" required>
                                    <option value="">Chọn hình thức</option>
                                    <option value="Tiền mặt">Tiền mặt</option>
                                    <option value="Chuyển khoản">Chuyển khoản</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label required">Số tiền</label>
                                <input type="number" class="form-control" name="amount" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tổng thanh toán</label>
                                <input type="number" class="form-control" name="total_amount" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label required">Kiểu phiếu</label>
                                <select class="form-select" name="receipt_type" id="receiptType" required>
                                    <option value="">Chọn kiểu phiếu</option>
                                    <option value="Phiếu thu">Phiếu thu</option>
                                    <option value="Phiếu báo có">Phiếu báo có</option>
                                    <option value="Phiếu kế toán">Phiếu kế toán</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label required">Người nộp tiền</label>
                                <select class="form-select" name="payer" required>
                                    <option value="">Chọn người nộp tiền</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Địa chỉ</label>
                                <input type="text" class="form-control" name="address">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label required">Ngày hạch toán</label>
                                <input type="date" class="form-control" name="accounting_date" 
                                       value="<?php echo date('Y-m-d'); ?>" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Nội dung thu tiền</label>
                                <textarea class="form-control" name="content" rows="3"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="section-header">
                            Danh sách dịch vụ
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered" id="servicesTable">
                                <thead>
                                    <tr>
                                        <th>Dịch vụ</th>
                                        <th>Phát sinh</th>
                                        <th>Giảm trừ</th>
                                        <th>Thanh toán</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Dữ liệu sẽ được thêm vào đây qua JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary" id="previewBtn">
                                <i class="fas fa-plus"></i> Thêm mới công nợ
                            </button>
                            <button type="submit" name="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Thu tiền
                            </button>
                            <a href="payment_receipt.php" class="btn btn-danger">
                                <i class="fas fa-times"></i> Quay lại
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Thêm mới công nợ -->
    <div class="modal fade" id="addDebtModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: #6b8b7b; color: white;">
                    <h5 class="modal-title">Thêm mới công nợ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Căn hộ</label>
                            <input type="text" class="form-control" id="modalApartment" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Chủ hộ</label>
                            <input type="text" class="form-control" id="modalOwner" readonly>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label required">Dịch vụ</label>
                            <select class="form-select" id="modalService" required>
                                <option value="">Chọn dịch vụ</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Giá dịch vụ</label>
                            <input type="number" class="form-control" id="modalServicePrice" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ngày tính phí tiếp theo</label>
                            <input type="date" class="form-control" id="modalNextBillingDate" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ngày chốt</label>
                            <input type="number" class="form-control" id="modalDueDay" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Số lượng</label>
                            <input type="number" class="form-control" id="modalQuantity" value="1" min="1" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tổng tiền</label>
                            <input type="number" class="form-control" id="modalTotal" readonly>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" id="addNewDebtBtn">
                        <i class="fas fa-plus"></i> Thêm mới công nợ
                    </button>
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Huỷ</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Khai báo biến modal ở phạm vi global của document.ready
            const debtModal = new bootstrap.Modal(document.getElementById('addDebtModal'), {
                keyboard: false,
                backdrop: 'static'
            });
            
            let selectedApartmentId = null;
            let selectedApartmentInfo = null;

            // Xử lý khi click nút thêm mới công nợ
            $('#previewBtn').click(function() {
                selectedApartmentId = $('select[name="apartment_id"]').val();
                if (!selectedApartmentId) {
                    alert('Vui lòng chọn căn hộ trước!');
                    return;
                }

                // Reset form modal
                $('#modalService').val('');
                $('#modalServicePrice').val('');
                $('#modalDueDay').val('');
                $('#modalNextBillingDate').val('');
                $('#modalPeriod').val('');
                $('#modalStartDate').val('');
                $('#modalEndDate').val('');
                $('#modalIncurredFee').val('');
                $('#modalQuantity').val('1');
                $('#modalDiscount').val('0');
                $('#modalTotal').val('');

                // Lấy thông tin dịch vụ của dự án
                $.ajax({
                    url: window.location.href,
                    method: 'POST',
                    data: {
                        get_project_services: true,
                        apartment_id: selectedApartmentId
                    },
                    success: function(response) {
                        try {
                            const data = JSON.parse(response);
                            selectedApartmentInfo = data.apartment;
                            
                            // Cập nhật thông tin căn hộ và chủ hộ
                            $('#modalApartment').val(selectedApartmentInfo.ApartmentCode);
                            $('#modalOwner').val(selectedApartmentInfo.OwnerName);
                            
                            // Cập nhật danh sách dịch vụ
                            const serviceSelect = $('#modalService');
                            serviceSelect.empty().append('<option value="">Chọn dịch vụ</option>');
                            data.services.forEach(service => {
                                const formattedPrice = new Intl.NumberFormat('vi-VN', {
                                    style: 'currency',
                                    currency: 'VND'
                                }).format(service.StartPrice);
                                
                                serviceSelect.append(`
                                    <option value="${service.ServiceCode}" 
                                            data-price="${service.StartPrice}"
                                            data-cycle="${service.Cycle}"
                                            data-switch-day="${service.SwitchDay}">
                                        ${service.Name} - ${formattedPrice}
                                    </option>
                                `);
                            });
                            
                            // Hiển thị modal
                            debtModal.show();
                        } catch (error) {
                            console.error('Error parsing JSON:', error);
                            alert('Có lỗi xảy ra khi lấy thông tin dịch vụ!');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', error);
                        alert('Có lỗi xảy ra khi lấy thông tin dịch vụ!');
                    }
                });
            });

            // Sửa lại hàm tính tổng tiền
            function calculateTotal() {
                const price = parseFloat($('#modalServicePrice').val()) || 0;
                const quantity = parseFloat($('#modalQuantity').val()) || 1;
                const total = price * quantity;
                $('#modalTotal').val(total);
            }

            // Thêm sự kiện khi thay đổi số lượng
            $('#modalQuantity').on('input', calculateTotal);

            // Sửa lại phần xử lý khi chọn dịch vụ
            $('#modalService').change(function() {
                const selected = $(this).find(':selected');
                const price = selected.data('price') || 0;
                const cycle = selected.data('cycle') || 30;
                const switchDay = selected.data('switch-day') || 1;
                
                $('#modalServicePrice').val(price);
                $('#modalDueDay').val(switchDay);
                
                // Tính ngày tính phí tiếp theo
                const today = new Date();
                const nextBilling = new Date(today.setDate(today.getDate() + cycle));
                $('#modalNextBillingDate').val(nextBilling.toISOString().split('T')[0]);
                
                // Reset và tính lại tổng tiền
                $('#modalQuantity').val(1);
                calculateTotal();
            });

            // Sửa lại phần xử lý thêm mới công nợ
            $('#addNewDebtBtn').click(function() {
                const serviceCode = $('#modalService').val();
                if (!serviceCode) {
                    alert('Vui lòng chọn dịch vụ!');
                    return;
                }

                const serviceName = $('#modalService option:selected').text();
                const quantity = parseFloat($('#modalQuantity').val()) || 1;
                const total = parseFloat($('#modalTotal').val()) || 0;

                // Thêm vào bảng dịch vụ
                const rowCount = $('#servicesTable tbody tr').length;
                const newRow = `
                    <tr>
                        <td>
                            <input type="hidden" name="services[${rowCount}][code]" value="${serviceCode}">
                            ${serviceName}
                        </td>
                        <td>
                            <input type="number" class="form-control" name="services[${rowCount}][incurred]" 
                                   value="${total}" readonly>
                        </td>
                        <td>
                            <input type="number" class="form-control" name="services[${rowCount}][discount]" 
                                   value="0" min="0">
                        </td>
                        <td>
                            <input type="number" class="form-control payment-amount" 
                                   name="services[${rowCount}][payment]" 
                                   value="${total}" min="0">
                        </td>
                        <td>
                            <button type="button" class="btn btn-danger btn-sm remove-service">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
                
                $('#servicesTable tbody').append(newRow);
                calculateTotalPayment(); // Tính lại tổng tiền phiếu thu
                debtModal.hide();
            });

            // Xử lý thêm dòng dịch vụ mới
            $('#addService').click(function() {
                const rowCount = $('#servicesTable tbody tr').length;
                const newRow = `
                    <tr>
                        <td>
                            <select class="form-select" name="services[${rowCount}][code]">
                                <option value="">Chọn dịch vụ</option>
                                <?php 
                                mysqli_data_seek($select_services, 0);
                                while($service = mysqli_fetch_assoc($select_services)) { 
                                ?>
                                <option value="<?php echo $service['ServiceCode']; ?>">
                                    <?php echo $service['Name']; ?>
                                </option>
                                <?php } ?>
                            </select>
                        </td>
                        <td><input type="number" class="form-control" name="services[${rowCount}][incurred]"></td>
                        <td><input type="number" class="form-control" name="services[${rowCount}][discount]"></td>
                        <td><input type="number" class="form-control payment-amount" name="services[${rowCount}][payment]"></td>
                        <td>
                            <button type="button" class="btn btn-danger btn-sm remove-service">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
                $('#servicesTable tbody').append(newRow);
            });

            // Xử lý xóa dòng dịch vụ
            $(document).on('click', '.remove-service', function() {
                $(this).closest('tr').remove();
                calculateTotalPayment(); // Tính lại tổng tiền sau khi xóa
            });

            // Tính tổng thanh toán tự động khi thay đổi giá trị payment
            $(document).on('input', '.payment-amount', function() {
                calculateTotalPayment();
            });

            // Xử lý khi thay đổi căn hộ
            $('select[name="apartment_id"]').change(function() {
                const apartmentId = $(this).val();
                if(apartmentId) {
                    // Gọi AJAX để lấy danh sách dịch vụ và cư dân của căn hộ
                    $.ajax({
                        url: window.location.href,
                        method: 'POST',
                        data: { 
                            get_services: true,
                            apartment_id: apartmentId 
                        },
                        success: function(response) {
                            const data = JSON.parse(response);
                            const services = data.services;
                            const residents = data.residents;
                            
                            // Cập nhật danh sách dịch vụ
                            let tableHtml = '';
                            if(services.length > 0) {
                                services.forEach((service, index) => {
                                    tableHtml += `
                                        <tr>
                                            <td>
                                                <input type="hidden" name="services[${index}][code]" value="${service.ServiceCode}">
                                                <input type="hidden" name="services[${index}][invoice_code]" value="${service.InvoiceCode}">
                                                ${service.ServiceName} (${service.InvoicePeriod})
                                            </td>
                                            <td>
                                                <input type="number" class="form-control" name="services[${index}][incurred]" 
                                                    value="${service.RemainingBalance}" readonly>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control" name="services[${index}][discount]" 
                                                    value="0" min="0">
                                            </td>
                                            <td>
                                                <input type="number" class="form-control payment-amount" 
                                                    name="services[${index}][payment]" 
                                                    value="${service.RemainingBalance}" min="0">
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-danger btn-sm remove-service">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    `;
                                });
                            } else {
                                tableHtml = '<tr><td colspan="5" class="text-center">Không có dịch vụ cần thanh toán</td></tr>';
                            }
                            
                            $('#servicesTable tbody').html(tableHtml);
                            
                            // Cập nhật dropdown người nộp tiền
                            let payerSelect = $('select[name="payer"]');
                            payerSelect.empty().append('<option value="">Chọn người nộp tiền</option>');
                            
                            residents.forEach(resident => {
                                const displayName = resident.UserName || `Cư dân (${resident.NationalId})`;
                                const selected = resident.Relationship === 'Chủ hộ' ? 'selected' : '';
                                payerSelect.append(`
                                    <option value="${displayName}" ${selected}>
                                        ${displayName} - ${resident.Relationship}
                                        ${resident.PhoneNumber ? ` - ${resident.PhoneNumber}` : ''}
                                    </option>
                                `);
                            });
                            
                            // Tính tổng tiền
                            calculateTotalPayment();
                        }
                    });
                } else {
                    $('#servicesTable tbody').html('<tr><td colspan="5" class="text-center">Vui lòng chọn căn hộ</td></tr>');
                    $('select[name="payer"]').empty().append('<option value="">Chọn người nộp tiền</option>');
                    $('[name="amount"]').val('');
                    $('[name="total_amount"]').val('');
                }
            });

            // Xử lý khi thay đổi giảm trừ
            $(document).on('input', '[name$="[discount]"]', function() {
                const row = $(this).closest('tr');
                const incurred = parseFloat(row.find('[name$="[incurred]"]').val()) || 0;
                const discount = parseFloat($(this).val()) || 0;
                
                // Giới hạn discount không vượt quá incurred
                if (discount > incurred) {
                    $(this).val(incurred);
                    const payment = 0;
                    row.find('[name$="[payment]"]').val(payment);
                } else {
                    const payment = incurred - discount;
                    row.find('[name$="[payment]"]').val(payment);
                }
                
                calculateTotalPayment();
            });

            // Cập nhật hàm tính tổng tiền
            function calculateTotalPayment() {
                let total = 0;
                $('.payment-amount').each(function() {
                    total += parseFloat($(this).val()) || 0;
                });
                $('[name="amount"]').val(total);
                $('[name="total_amount"]').val(total);
            }

            // Xử lý khi thay đổi payment
            $(document).on('input', '.payment-amount', function() {
                calculateTotalPayment();
            });
        });
    </script>
</body>
</html>

