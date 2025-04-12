<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Lấy danh sách dự án
$select_projects = mysqli_query($conn, "
    SELECT ProjectID, Name 
    FROM Projects 
    WHERE Status = 'active'
    ORDER BY Name
");

// Thêm AJAX endpoint để lấy danh sách căn hộ có tiền thừa theo dự án
if(isset($_POST['get_apartments'])) {
    $project_id = mysqli_real_escape_string($conn, $_POST['project_id']);
    
    $apartments_query = mysqli_query($conn, "
        SELECT DISTINCT 
            a.ApartmentID, 
            a.Code, 
            a.Name, 
            b.Name as BuildingName,
            e.Total as ExcessAmount,
            e.ExcessPaymentID
        FROM apartment a 
        JOIN Buildings b ON a.BuildingId = b.ID
        JOIN excesspayment e ON a.ApartmentID = e.ApartmentID
        WHERE b.ProjectId = '$project_id'
        AND e.Status = 'active'
        AND e.Total > 0
        ORDER BY b.Name, a.Code
    ");
    
    $apartments = array();
    while($row = mysqli_fetch_assoc($apartments_query)) {
        $apartments[] = $row;
    }
    
    echo json_encode($apartments);
    exit;
}

// Xử lý khi submit form
if(isset($_POST['submit'])) {
    $apartment_id = mysqli_real_escape_string($conn, $_POST['apartment_id']);
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $amount = mysqli_real_escape_string($conn, $_POST['amount']);
    $accounting_date = mysqli_real_escape_string($conn, $_POST['accounting_date']);
    $content = mysqli_real_escape_string($conn, $_POST['content']);
    $excess_payment_id = mysqli_real_escape_string($conn, $_POST['excess_payment_id']);

    // Bắt đầu transaction
    mysqli_begin_transaction($conn);

    try {
        // Tắt kiểm tra khóa ngoại
        mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");

        // Kiểm tra số tiền thừa hiện tại
        $check_excess = mysqli_query($conn, "
            SELECT Total 
            FROM excesspayment 
            WHERE ExcessPaymentID = '$excess_payment_id' 
            AND Status = 'active'
            FOR UPDATE
        ");
        
        if($row = mysqli_fetch_assoc($check_excess)) {
            if($amount > $row['Total']) {
                throw new Exception("Số tiền điều chỉnh không được vượt quá số tiền thừa!");
            }

            // Thêm phiếu chi mới
            $insert_payment = mysqli_query($conn, "
                INSERT INTO payments (
                    PaymentMethod, 
                    AccountingDate, 
                    Total, 
                    ApartmentID, 
                    StaffID, 
                    Content,
                    IssueDate
                ) VALUES (
                    '$payment_method', 
                    '$accounting_date', 
                    $amount,
                    '$apartment_id', 
                    '$admin_id', 
                    '$content',
                    CURRENT_DATE()
                )
            ");

            if(!$insert_payment) {
                throw new Exception("Lỗi khi tạo phiếu chi: " . mysqli_error($conn));
            }

            // Cập nhật số tiền thừa
            $new_total = $row['Total'] - $amount;
            $update_excess = mysqli_query($conn, "
                UPDATE excesspayment 
                SET Total = $new_total,
                    Status = CASE 
                        WHEN $new_total = 0 THEN 'inactive' 
                        ELSE 'active' 
                    END
                WHERE ExcessPaymentID = '$excess_payment_id'
            ");

            if(!$update_excess) {
                throw new Exception("Lỗi khi cập nhật số tiền thừa: " . mysqli_error($conn));
            }

            // Bật lại kiểm tra khóa ngoại
            mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");

            // Nếu mọi thứ OK, commit transaction
            mysqli_commit($conn);
            $success_msg = "Lập phiếu chi thành công! Đã cập nhật số tiền thừa.";

            // Refresh lại trang sau khi thành công
            echo "<script>
                setTimeout(function() {
                    window.location.href = 'payment_receipt.php';
                }, 1500);
            </script>";
        } else {
            throw new Exception("Không tìm thấy thông tin tiền thừa!");
        }
    } catch (Exception $e) {
        // Nếu có lỗi, rollback transaction và bật lại kiểm tra khóa ngoại
        mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");
        mysqli_rollback($conn);
        $error_msg = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lập phiếu chi</title>
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
                <div class="page-header">
                    <h2 class="text-uppercase fw-bold" style="color: #476a52;">Lập phiếu chi</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a style="color: #476a52; text-decoration: none;" href="/admin/dashboard.php">Trang chủ</a></li>
                            <li class="breadcrumb-item"><a style="color: #476a52; text-decoration: none;" href="payment_receipt.php">Quản lý thu/chi</a></li>
                            <li class="breadcrumb-item active">Lập phiếu chi</li>
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

                <form method="POST">
                    <div class="form-section">
                        <div class="section-header">Lập phiếu chi</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label required">Căn hộ nhận tiền</label>
                                <select class="form-select" name="project_id" id="project_id" required>
                                    <option value="">Chọn dự án</option>
                                    <?php while($project = mysqli_fetch_assoc($select_projects)) { ?>
                                        <option value="<?php echo $project['ProjectID']; ?>">
                                            <?php echo $project['Name']; ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">&nbsp;</label>
                                <select class="form-select" name="apartment_id" id="apartment_id" required disabled>
                                    <option value="">Chọn căn hộ</option>
                                </select>
                                <input type="hidden" name="excess_payment_id" id="excess_payment_id">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Số tiền</label>
                                <input type="text" class="form-control" id="total_excess" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Hình thức chi tiền</label>
                                <select class="form-select" name="payment_method" required>
                                    <option value="">Chọn hình thức chi</option>
                                    <option value="Tiền mặt">Tiền mặt</option>
                                    <option value="Chuyển khoản">Chuyển khoản</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label required">Nội dung chi tiền</label>
                                <textarea class="form-control" name="content" rows="3" required></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Ngày hạch toán</label>
                                <input type="date" class="form-control" name="accounting_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="section-header">Tiền thừa căn hộ</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Tổng tiền thừa:</label>
                                <input type="text" class="form-control" id="display_total_excess" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Điều chỉnh giảm:</label>
                                <input type="number" class="form-control" name="amount" id="amount" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="d-flex justify-content-end gap-2">
                            <button type="submit" name="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Lập phiếu
                            </button>
                            <a href="payment_management.php" class="btn btn-danger">
                                <i class="fas fa-times"></i> Quay lại
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#project_id').change(function() {
                const projectId = $(this).val();
                const apartmentSelect = $('#apartment_id');
                
                if (!projectId) {
                    apartmentSelect.html('<option value="">Chọn căn hộ</option>').prop('disabled', true);
                    $('#total_excess, #display_total_excess').val('');
                    $('#amount').val('').attr('max', '');
                    return;
                }

                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: {
                        get_apartments: true,
                        project_id: projectId
                    },
                    success: function(response) {
                        try {
                            const apartments = JSON.parse(response);
                            let options = '<option value="">Chọn căn hộ</option>';
                            
                            apartments.forEach(apt => {
                                options += `<option value="${apt.ApartmentID}" 
                                    data-excess="${apt.ExcessAmount}"
                                    data-excess-id="${apt.ExcessPaymentID}">
                                    ${apt.Code} - ${apt.Name} (${apt.BuildingName})
                                </option>`;
                            });
                            
                            apartmentSelect
                                .html(options)
                                .prop('disabled', false);
                        } catch (e) {
                            console.error('Error parsing apartments:', e);
                        }
                    }
                });
            });

            $('#apartment_id').change(function() {
                const selectedOption = $(this).find('option:selected');
                const excessAmount = selectedOption.data('excess');
                const excessId = selectedOption.data('excess-id');
                
                // Hiển thị số tiền thừa ở cả hai nơi
                $('#total_excess, #display_total_excess').val(excessAmount ? excessAmount.toLocaleString() + ' VNĐ' : '');
                $('#excess_payment_id').val(excessId);
                
                // Reset và set max cho field điều chỉnh giảm
                $('#amount').val('').attr('max', excessAmount);
            });

            // Validate số tiền điều chỉnh giảm
            $('#amount').on('input', function() {
                const amount = parseFloat($(this).val()) || 0;
                const maxAmount = parseFloat($(this).attr('max'));
                
                if (amount > maxAmount) {
                    alert('Số tiền điều chỉnh không được vượt quá số tiền thừa!');
                    $(this).val(maxAmount);
                }
            });

            // Validate form before submit
            $('form').submit(function(e) {
                const amount = parseFloat($('#amount').val());
                const maxAmount = parseFloat($('#amount').attr('max'));

                if (!amount || amount <= 0) {
                    alert('Vui lòng nhập số tiền điều chỉnh giảm!');
                    e.preventDefault();
                    return false;
                }

                if (amount > maxAmount) {
                    alert('Số tiền điều chỉnh không được vượt quá số tiền thừa!');
                    e.preventDefault();
                    return false;
                }
            });
        });
    </script>
</body>
</html>

