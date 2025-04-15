<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Kiểm tra tham số URL
if (!isset($_GET['invoice_code']) || !isset($_GET['service_code'])) {
    header('location: bill_list.php');
    exit();
}

$invoice_code = mysqli_real_escape_string($conn, $_GET['invoice_code']);
$service_code = mysqli_real_escape_string($conn, $_GET['service_code']);

// Lấy thông tin bảng kê
$invoice_query = mysqli_query($conn, "
    SELECT d.InvoiceCode, d.InvoicePeriod, d.DueDate, d.IssueDate
    FROM debtstatements d
    WHERE d.InvoiceCode = '$invoice_code'");

if (mysqli_num_rows($invoice_query) == 0) {
    header('location: bill_list.php');
    exit();
}

$invoice = mysqli_fetch_assoc($invoice_query);

// Lấy thông tin chi tiết dịch vụ
$detail_query = mysqli_query($conn, "
    SELECT d.*, s.Name as ServiceName 
    FROM debtstatementdetail d
    LEFT JOIN services s ON d.ServiceCode = s.ServiceCode
    WHERE d.InvoiceCode = '$invoice_code' AND d.ServiceCode = '$service_code'");

if (mysqli_num_rows($detail_query) == 0) {
    header('location: detail_bill.php?invoice_code=' . $invoice_code);
    exit();
}

$detail = mysqli_fetch_assoc($detail_query);

// Kiểm tra PaidAmount
$check_query = "SELECT PaidAmount FROM debtstatements WHERE InvoiceCode = '$invoice_code'";
$check_result = mysqli_query($conn, $check_query);
$check_row = mysqli_fetch_assoc($check_result);

if ($check_row['PaidAmount'] > 0) {
    header('location: detail_bill.php?invoice_code=' . $invoice_code);
    exit();
}

// Xử lý form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quantity = (int)$_POST['quantity'];
    $unit_price = (int)$_POST['unit_price'];
    $discount = (int)$_POST['discount'];
    $discount_reason = mysqli_real_escape_string($conn, $_POST['discount_reason']);
    
    // Các trường thời gian (yyyy-mm-dd)
    $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
    $end_date = mysqli_real_escape_string($conn, $_POST['end_date']); 
    $due_date = mysqli_real_escape_string($conn, $_POST['due_date']);
    
    $amount = $quantity * $unit_price - $discount;
    
    mysqli_begin_transaction($conn);
    
    try {
        // Cập nhật chi tiết bảng kê
        $update_detail = mysqli_query($conn, "
            UPDATE debtstatementdetail 
            SET Quantity = $quantity,
                UnitPrice = $unit_price,
                Discount = $discount,
                RemainingBalance = $amount,
                IssueDate = '$start_date'
            WHERE InvoiceCode = '$invoice_code' 
            AND ServiceCode = '$service_code'
        ");

        if (!$update_detail) {
            throw new Exception("Lỗi khi cập nhật chi tiết dịch vụ");
        }

        // Cập nhật bảng kê chính
        $update_invoice = mysqli_query($conn, "
            UPDATE debtstatements 
            SET DueDate = '$due_date',
                IssueDate = '$start_date',
                Total = (
                    SELECT SUM(Quantity * UnitPrice - Discount)
                    FROM debtstatementdetail
                    WHERE InvoiceCode = '$invoice_code'
                ),
                RemainingBalance = (
                    SELECT SUM(Quantity * UnitPrice - Discount)
                    FROM debtstatementdetail
                    WHERE InvoiceCode = '$invoice_code'
                )
            WHERE InvoiceCode = '$invoice_code'
        ");

        if (!$update_invoice) {
            throw new Exception("Lỗi khi cập nhật thông tin bảng kê");
        }

        mysqli_commit($conn);
        
        // Chuyển hướng về trang chi tiết bảng kê với thông báo thành công
        header('location: detail_bill.php?invoice_code=' . $invoice_code . '&updated=1');
        exit();
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error_message = $e->getMessage();
    }
}

$amount = $detail['Quantity'] * $detail['UnitPrice'] - $detail['Discount'];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết bảng kê dịch vụ</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/admin_style.css">
    <style>
        body {
            background-color: #f0f2f5;
        }
        
        .edit-container {
            max-width: 700px;
            margin: 30px auto;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .header {
            position: relative;
            background-color: #6b8b7b;
            color: white;
            padding: 15px;
            border-radius: 6px 6px 0 0;
            margin: -20px -20px 20px;
        }
        
        .header h5 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .close-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 5px;
            display: block;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
        }
        
        .form-control:disabled {
            background-color: #f8f9fa;
        }
        
        .required {
            color: red;
            margin-left: 3px;
        }
        
        .buttons {
            display: flex;
            justify-content: flex-end;
            margin-top: 25px;
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-cancel {
            background-color: #dc3545;
            color: white;
            border: none;
            margin-right: 10px;
        }
        
        .btn-update {
            background-color: #6b8b7b;
            color: white;
            border: none;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .date-input {
            display: flex;
            align-items: center;
        }
        
        .date-input .calendar-icon {
            position: absolute;
            right: 10px;
            pointer-events: none;
            color: #6c757d;
        }

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
                        CẬP NHẬT CHI TIẾT BẢNG KÊ
                    </h2>
                    <div class="breadcrumb">
                        <a href="../dashboard.php">Trang chủ</a>
                        <span style="margin: 0 8px;">›</span>
                        <span>Quản lý thu phí</span>
                        <span style="margin: 0 8px;">›</span>
                        <span>Cập nhật chi tiết bảng kê</span>
                    </div>
                </div>
                <div class="edit-container">
                    <div class="header">
                        <h5>Chi tiết bảng kê dịch vụ</h5>
                        <a href="detail_bill.php?invoice_code=<?php echo $invoice_code; ?>" class="close-btn">&times;</a>
                    </div>
                    
                    <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger mb-3">
                        <?php echo $error_message; ?>
                    </div>
                    <?php endif; ?>
                    
                    <form action="" method="post" id="editServiceForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Kỳ bảng kê</label>
                                    <input type="text" class="form-control" value="<?php echo $invoice['InvoicePeriod']; ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Thời gian bắt đầu <span class="required">*</span></label>
                                    <div class="position-relative">
                                        <input type="date" name="start_date" class="form-control" value="<?php echo $detail['IssueDate']; ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Dịch vụ</label>
                                    <input type="text" class="form-control" value="<?php echo $detail['ServiceName']; ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Thời gian chốt <span class="required">*</span></label>
                                    <div class="position-relative">
                                        <input type="date" name="end_date" class="form-control" value="<?php echo $invoice['DueDate']; ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Đơn giá <span class="required">*</span></label>
                                    <input type="number" name="unit_price" id="unitPrice" class="form-control" value="<?php echo $detail['UnitPrice']; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Hạn thanh toán <span class="required">*</span></label>
                                    <div class="position-relative">
                                        <input type="date" name="due_date" class="form-control" value="<?php echo $invoice['DueDate']; ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Số lượng <span class="required">*</span></label>
                                    <input type="number" name="quantity" id="quantity" class="form-control" value="<?php echo $detail['Quantity']; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Ghi chú</label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-12">
                                <div class="form-group">
                                    <label class="form-label">Phát sinh</label>
                                    <input type="text" id="total" class="form-control" value="<?php echo number_format($detail['Quantity'] * $detail['UnitPrice']); ?>" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-12">
                                <div class="form-group">
                                    <label class="form-label">Giảm trừ</label>
                                    <input type="number" name="discount" id="discount" class="form-control" value="<?php echo $detail['Discount']; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-12">
                                <div class="form-group">
                                    <label class="form-label">Thành tiền</label>
                                    <input type="text" id="finalAmount" class="form-control" value="<?php echo number_format($amount); ?>" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-12">
                                <div class="form-group">
                                    <label class="form-label">Thanh toán</label>
                                    <input type="text" class="form-control" value="<?php echo number_format($detail['PaidAmount']); ?>" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-12">
                                <div class="form-group">
                                    <label class="form-label">Lý do giảm trừ</label>
                                    <textarea name="discount_reason" class="form-control" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="buttons">
                            <a href="detail_bill.php?invoice_code=<?php echo $invoice_code; ?>" class="btn btn-cancel">Huỷ</a>
                            <button type="submit" class="btn btn-update">Cập nhật</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const quantityInput = document.getElementById('quantity');
            const unitPriceInput = document.getElementById('unitPrice');
            const discountInput = document.getElementById('discount');
            const totalInput = document.getElementById('total');
            const finalAmountInput = document.getElementById('finalAmount');
            
            function calculateTotal() {
                const quantity = parseFloat(quantityInput.value) || 0;
                const unitPrice = parseFloat(unitPriceInput.value) || 0;
                const discount = parseFloat(discountInput.value) || 0;
                
                const total = quantity * unitPrice;
                const finalAmount = total - discount;
                
                totalInput.value = total.toLocaleString('vi-VN');
                finalAmountInput.value = finalAmount.toLocaleString('vi-VN');
            }
            
            quantityInput.addEventListener('input', calculateTotal);
            unitPriceInput.addEventListener('input', calculateTotal);
            discountInput.addEventListener('input', calculateTotal);
        });
    </script>
</body>
</html>
