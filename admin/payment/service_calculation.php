<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Lấy tháng và năm hiện tại
$current_month = date('m');
$current_year = date('Y');

// Xử lý khi có POST request để tính phí
if (isset($_POST['calculate_fee'])) {
    $invoice_period_month = mysqli_real_escape_string($conn, $_POST['month']);
    $invoice_period_year = mysqli_real_escape_string($conn, $_POST['year']);
    $due_date = mysqli_real_escape_string($conn, $_POST['due_date']);
    $apartment_filter = isset($_POST['apartment_filter']) ? mysqli_real_escape_string($conn, $_POST['apartment_filter']) : 'all';
    
    // Get the service details from the form
    $service_codes = $_POST['service_codes'] ?? [];
    $prices = $_POST['prices'] ?? [];
    $discounts = $_POST['discounts'] ?? [];
    $discount_reasons = $_POST['discount_reasons'] ?? [];
    
    // Kiểm tra xem đã chọn căn hộ cụ thể hay không
    $apartment_clause = ($apartment_filter != 'all') ? "AND a.ApartmentID = '$apartment_filter'" : "";
    
    // Lấy danh sách các căn hộ có hợp đồng
    $apartment_query = "
        SELECT a.ApartmentID, a.Code, a.Name, a.ContractCode, c.Status AS ContractStatus
        FROM apartment a
        LEFT JOIN Contracts c ON a.ContractCode = c.ContractCode
        WHERE c.Status != 'pending' AND c.Status != 'expired' $apartment_clause
    ";
    
    $apartments = mysqli_query($conn, $apartment_query);
    
    if (mysqli_num_rows($apartments) > 0) {
        while ($apartment = mysqli_fetch_assoc($apartments)) {
            $apartment_id = $apartment['ApartmentID'];
            $contract_code = $apartment['ContractCode'];
            
            // Lấy mã bảng kê lớn nhất hiện tại
            $max_code_query = mysqli_query($conn, "
                SELECT InvoiceCode 
                FROM debtstatements 
                WHERE InvoiceCode LIKE 'BK%' 
                ORDER BY InvoiceCode DESC 
                LIMIT 1
            ");

            if (mysqli_num_rows($max_code_query) > 0) {
                $max_code = mysqli_fetch_assoc($max_code_query)['InvoiceCode'];
                // Lấy số từ mã hiện tại và tăng lên 1
                $current_number = intval(substr($max_code, 2));
                $next_number = $current_number + 1;
            } else {
                // Nếu chưa có bảng kê nào, bắt đầu từ 1
                $next_number = 1;
            }

            // Format số thành chuỗi 2 chữ số (01, 02, ..., 99)
            $invoice_code = 'BK' . str_pad($next_number, 2, '0', STR_PAD_LEFT);
            $invoice_period = $invoice_period_month . '/' . $invoice_period_year;
            $issue_date = date('Y-m-d');
            
            $total_amount = 0;
            $service_details = [];
            
            // Calculate fees for each service
            foreach ($service_codes as $index => $service_code) {
                $unit_price = $prices[$index] ?? 0;
                $discount = $discounts[$index] ?? 0;
                $discount_reason = $discount_reasons[$index] ?? '';
                $quantity = 1; // Default quantity
                
                // Special handling for water and electricity
                $service_info_query = mysqli_query($conn, "SELECT TypeOfService FROM services WHERE ServiceCode = '$service_code'");
                $service_info = mysqli_fetch_assoc($service_info_query);
                
                if ($service_info['TypeOfService'] == 'Nước') {
                    // Get water consumption
                    $water_query = mysqli_query($conn, "
                        SELECT Consumption
                        FROM WaterMeterReading
                        WHERE ApartmentID = '$apartment_id'
                        AND MONTH(ClosingDate) = '$invoice_period_month'
                        AND YEAR(ClosingDate) = '$invoice_period_year'
                        ORDER BY ClosingDate DESC
                        LIMIT 1
                    ");
                    
                    if (mysqli_num_rows($water_query) > 0) {
                        $water_info = mysqli_fetch_assoc($water_query);
                        $quantity = $water_info['Consumption'];
                    }
                } elseif ($service_info['TypeOfService'] == 'Điện') {
                    // Get electricity consumption
                    $electric_query = mysqli_query($conn, "
                        SELECT Consumption
                        FROM ElectricityMeterReading
                        WHERE ApartmentID = '$apartment_id'
                        AND MONTH(ClosingDate) = '$invoice_period_month'
                        AND YEAR(ClosingDate) = '$invoice_period_year'
                        ORDER BY ClosingDate DESC
                        LIMIT 1
                    ");
                    
                    if (mysqli_num_rows($electric_query) > 0) {
                        $electric_info = mysqli_fetch_assoc($electric_query);
                        $quantity = $electric_info['Consumption'];
                    }
                }
                
                $amount = ($unit_price * $quantity) - $discount;
                $total_amount += $amount;
                
                $service_details[] = [
                    'service_code' => $service_code,
                    'unit_price' => $unit_price,
                    'quantity' => $quantity,
                    'discount' => $discount,
                    'amount' => $amount
                ];
            }
            
            // Trước khi thực hiện insert, tắt kiểm tra khóa ngoại
            mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=0");

            // Phần code insert vào debtstatements
            $insert_invoice = mysqli_query($conn, "
                INSERT INTO debtstatements (
                    InvoiceCode, InvoicePeriod, DueDate, OutstandingDebt, 
                    Discount, Total, PaidAmount, RemainingBalance, 
                    IssueDate, Status, StaffID, ApartmentID
                ) VALUES (
                    '$invoice_code', '$invoice_period', '$due_date', 0,
                    0, '$total_amount', 0, '$total_amount',
                    '$issue_date', 'Chờ xác nhận', '$admin_id', '$apartment_id'
                )
            ");

            if ($insert_invoice) {
                // Insert debtstatementdetail records
                foreach ($service_details as $detail) {
                    mysqli_query($conn, "
                        INSERT INTO debtstatementdetail (
                            InvoiceCode, ServiceCode, Quantity, UnitPrice,
                            Discount, PaidAmount, RemainingBalance, IssueDate
                        ) VALUES (
                            '$invoice_code', '{$detail['service_code']}', 
                            '{$detail['quantity']}', '{$detail['unit_price']}',
                            '{$detail['discount']}', 0, '{$detail['amount']}', 
                            '$issue_date'
                        )
                    ");
                }
            }

            // Sau khi hoàn thành, bật lại kiểm tra khóa ngoại
            mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=1");
        }
        
        $success_msg[] = 'Đã tính phí dịch vụ thành công';
    } else {
        $error_msg[] = 'Không tìm thấy căn hộ với hợp đồng phù hợp';
    }
}

// Lấy danh sách căn hộ cho filter
$select_apartments = mysqli_query($conn, "SELECT ApartmentID, Code, Name FROM apartment WHERE Status = 'active'");

// Đầu tiên cần lấy ContractCode từ apartment được chọn
$apartment_filter = isset($_POST['apartment_filter']) ? mysqli_real_escape_string($conn, $_POST['apartment_filter']) : 'all';
$contract_clause = "";
if ($apartment_filter != 'all') {
    $contract_clause = "AND cs.ContractCode = (SELECT ContractCode FROM apartment WHERE ApartmentID = '$apartment_filter')";
}

$services_query = "
    SELECT 
        s.ServiceCode,
        s.Name AS ServiceName,
        pl.TypeOfFee,
        pl.Name AS PriceListName,
        pl.Price,
        cs.ApplyDate,
        cs.EndDate,
        cs.ContractCode
    FROM services s
    INNER JOIN ContractServices cs ON s.ServiceCode = cs.ServiceId
    LEFT JOIN ServicePrice sp ON s.ServiceCode = sp.ServiceId
    LEFT JOIN pricelist pl ON sp.PriceId = pl.ID
    WHERE s.Status = 'active' 
    AND cs.ApplyDate <= CURDATE()
    AND (cs.EndDate IS NULL OR cs.EndDate >= CURDATE())
    $contract_clause
    ORDER BY s.ServiceCode
";

$services_result = mysqli_query($conn, $services_query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tính phí dịch vụ</title>

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
        
        .form-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .form-label {
            font-weight: 500;
        }
        
        .required::after {
            content: " *";
            color: red;
        }
        
        .btn-calculate {
            background-color: #4F714C !important;
            color: white !important;
            border-color: #4F714C !important;
        }
        
        .btn-cancel {
            background-color: #dc3545 !important;
            border-color: #dc3545 !important;
            color: white !important;
        }
        
        .service-table {
            margin-top: 20px;
        }
        
        .service-table th {
            background-color: #6b8b7b !important;
            color: white;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <?php include '../admin_navbar.php'; ?>
        <div style="width: 100%;">
            <?php include '../admin_header.php'; ?>
            <div class="container-fluid p-4">
                <div class="page-header mb-4">
                    <h2 style="font-weight: bold; color: #476a52; margin-bottom: 10px; text-transform: uppercase;">
                        TÍNH PHÍ DỊCH VỤ
                    </h2>
                    <div class="breadcrumb">
                        <a href="../dashboard.php">Trang chủ</a>
                        <span style="margin: 0 8px;">›</span>
                        <span>Quản lý thu phí</span>
                        <span style="margin: 0 8px;">›</span>
                        <span>Tính phí dịch vụ</span>
                    </div>
                </div>

                <?php
                if(isset($success_msg)){
                    foreach($success_msg as $msg){
                        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                            '.$msg.'
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>';
                    }
                }
                
                if(isset($error_msg)){
                    foreach($error_msg as $msg){
                        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                            '.$msg.'
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>';
                    }
                }
                ?>

                <div class="form-container">
                    <form method="post" action="">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label required">Kỳ tháng</label>
                                <div class="d-flex">
                                    <select class="form-select me-2" name="month" required>
                                        <?php
                                        // Tạo các tùy chọn tháng: tháng hiện tại và +/- 1 tháng
                                        $month_range = range(max(1, $current_month - 1), min(12, $current_month + 1));
                                        foreach ($month_range as $month) {
                                            $month_padded = str_pad($month, 2, '0', STR_PAD_LEFT);
                                            $selected = ($month == $current_month) ? 'selected' : '';
                                            echo "<option value=\"$month_padded\" $selected>$month_padded</option>";
                                        }
                                        ?>
                                    </select>
                                    <span class="align-self-center mx-2">/</span>
                                    <select class="form-select" name="year" required>
                                        <?php
                                        // Nếu đang ở tháng 1 hoặc 12, hiển thị thêm năm trước hoặc năm sau
                                        $year_range = [$current_year];
                                        if ($current_month == 1) {
                                            array_unshift($year_range, $current_year - 1);
                                        }
                                        if ($current_month == 12) {
                                            array_push($year_range, $current_year + 1);
                                        }
                                        
                                        foreach ($year_range as $year) {
                                            $selected = ($year == $current_year) ? 'selected' : '';
                                            echo "<option value=\"$year\" $selected>$year</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Hạn thanh toán</label>
                                <input type="date" class="form-control" name="due_date" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Căn hộ tính phí</label>
                                <select class="form-select" name="apartment_filter">
                                    <option value="all">Tất cả các căn hộ</option>
                                    <?php while($apartment = mysqli_fetch_assoc($select_apartments)): ?>
                                        <option value="<?php echo $apartment['ApartmentID']; ?>">
                                            <?php echo $apartment['Code'] . ' - ' . $apartment['Name']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-12">
                                <table class="table table-bordered service-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 50px;">STT</th>
                                            <th>DỊCH VỤ</th>
                                            <th>CÁCH TÍNH GIÁ</th>
                                            <th>BẢNG GIÁ</th>
                                            <th>NGÀY BẮT ĐẦU</th>
                                            <th>NGÀY KẾT THÚC</th>
                                            <th>GIẢM TRỪ</th>
                                            <th>LÝ DO</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        if(mysqli_num_rows($services_result) > 0):
                                            $i = 1;
                                            while($service = mysqli_fetch_assoc($services_result)):
                                        ?>
                                        <tr>
                                            <td><?php echo $i++; ?></td>
                                            <td>
                                                <?php echo $service['ServiceName']; ?>
                                                <input type="hidden" name="service_codes[]" value="<?php echo $service['ServiceCode']; ?>">
                                            </td>
                                            <td><?php echo $service['TypeOfFee']; ?></td>
                                            <td>
                                                <?php echo $service['PriceListName']; ?>
                                                <input type="hidden" name="prices[]" value="<?php echo $service['Price']; ?>">
                                            </td>
                                            <td>
                                                <input type="date" class="form-control form-control-sm" 
                                                       name="start_dates[]" 
                                                       value="<?php echo $service['ApplyDate'] ?? date('Y-m-d'); ?>" 
                                                       readonly>
                                            </td>
                                            <td>
                                                <input type="date" class="form-control form-control-sm" 
                                                       name="end_dates[]" 
                                                       value="<?php echo $service['EndDate'] ?? date('Y-m-d'); ?>" 
                                                       readonly>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control form-control-sm" 
                                                       name="discounts[]" value="0">
                                            </td>
                                            <td>
                                                <input type="text" class="form-control form-control-sm" 
                                                       name="discount_reasons[]">
                                            </td>
                                        </tr>
                                        <?php 
                                            endwhile;
                                        else:
                                        ?>
                                        <tr>
                                            <td colspan="8" class="text-center">Không có dịch vụ nào</td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="row mt-4 mb-3">
                            <div class="col-12 d-flex justify-content-center">
                                <a href="bill_list.php" class="btn btn-cancel me-3">
                                    <i class="fas fa-times"></i> Hủy
                                </a>
                                <button type="submit" name="calculate_fee" class="btn btn-calculate ms-3">
                                    <i class="fas fa-calculator"></i> Tính phí
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Script to validate due date based on selected month and year
        document.addEventListener('DOMContentLoaded', function () {
            const monthSelect = document.querySelector('select[name="month"]');
            const yearSelect = document.querySelector('select[name="year"]');
            const dueDateInput = document.querySelector('input[name="due_date"]');
            
            function updateDueDate() {
                const selectedMonth = parseInt(monthSelect.value);
                const selectedYear = parseInt(yearSelect.value);
                
                // Set min and max date for the due date field
                const firstDay = new Date(selectedYear, selectedMonth - 1, 1);
                const lastDay = new Date(selectedYear, selectedMonth, 0);
                
                const minDate = firstDay.toISOString().split('T')[0];
                const maxDate = lastDay.toISOString().split('T')[0];
                
                dueDateInput.min = minDate;
                dueDateInput.max = maxDate;
                
                // Set a default value (end of month)
                dueDateInput.value = maxDate;
            }
            
            // Initialize
            updateDueDate();
            
            // Add event listeners for changes
            monthSelect.addEventListener('change', updateDueDate);
            yearSelect.addEventListener('change', updateDueDate);
        });
    </script>
</body>
</html>