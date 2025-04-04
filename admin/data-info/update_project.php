<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location:../login.php');
    exit();
}

if (isset($_GET['id'])) {
    $project_id = $_GET['id'];
    $select_project = mysqli_query($conn, "SELECT * FROM Projects WHERE ProjectID = $project_id") or die('Query failed');
    
    if (mysqli_num_rows($select_project) > 0) {
        $project = mysqli_fetch_assoc($select_project);
    } else {
        header('location:projects.php');
        exit();
    }
} else {
    header('location:projects.php');
    exit();
}

// Xử lý khi form được submit
if (isset($_POST['submit'])) {
    $township_id = mysqli_real_escape_string($conn, $_POST['township_id']);
    $operation_id = mysqli_real_escape_string($conn, $_POST['operation_id']);
    $project_name = mysqli_real_escape_string($conn, $_POST['project_name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $manager_id = mysqli_real_escape_string($conn, $_POST['manager_id']);
    $deadlock = mysqli_real_escape_string($conn, $_POST['deadlock']);
    $status = isset($_POST['status']) ? 'active' : 'inactive';
    
    // Kiểm tra mã dự án đã tồn tại chưa (nếu có thay đổi)
    if ($operation_id != $project['OperationId']) {
        $check_code = mysqli_query($conn, "SELECT * FROM `Projects` WHERE OperationId = '$operation_id' AND ProjectID != '$project_id'");
    if (mysqli_num_rows($check_code) > 0) {
        $message[] = 'Mã dự án đã tồn tại!';
        }
    }
    
    // Nếu không có lỗi, tiến hành cập nhật
    if (!isset($message)) {
        $update_project = mysqli_query($conn, "UPDATE `Projects` SET 
                                            Name = '$project_name',
                                            Address = '$address',
                                            Phone = '$phone', 
                                            Email = '$email',
                                            Deadlock = '$deadlock',
                                            Description = '$description',
                                            OperationId = '$operation_id',
                                            TownShipId = '$township_id',
                                            ManagerId = '$manager_id'
                                            WHERE ProjectID = '$project_id'") 
                                        or die('Query failed: ' . mysqli_error($conn));
        
        if ($update_project) {
            $message[] = 'Cập nhật dự án thành công!';
            // Lấy lại thông tin dự án sau khi cập nhật
            $select_project = mysqli_query($conn, "SELECT * FROM Projects WHERE ProjectID = $project_id");
            $project = mysqli_fetch_assoc($select_project);
        } else {
            $message[] = 'Cập nhật dự án thất bại!';
        }
    }
}

// Xử lý thêm mới thông tin thanh toán
if (isset($_POST['add_payment'])) {
    $bank = mysqli_real_escape_string($conn, $_POST['bank']);
    $branch = mysqli_real_escape_string($conn, $_POST['branch']);
    $account_number = mysqli_real_escape_string($conn, $_POST['account_number']);
    $account_name = mysqli_real_escape_string($conn, $_POST['account_name']);
    $auto_transaction = isset($_POST['auto_transaction']) ? 1 : 0;
    $auto_reconciliation = isset($_POST['auto_reconciliation']) ? 1 : 0;

    $insert_payment = mysqli_query($conn, "INSERT INTO PaymentInformation 
        (ProjectId, Bank, Branch, AccountNumber, AccountName, AutoTransaction, AutoReconciliation) 
        VALUES ('$project_id', '$bank', '$branch', '$account_number', '$account_name', '$auto_transaction', '$auto_reconciliation')");

    if ($insert_payment) {
        $message[] = 'Thêm thông tin thanh toán thành công!';
    } else {
        $message[] = 'Thêm thông tin thanh toán thất bại!';
    }
}

// Xử lý xóa thông tin thanh toán
if (isset($_GET['delete_payment'])) {
    $payment_id = $_GET['delete_payment'];
    $delete_payment = mysqli_query($conn, "DELETE FROM PaymentInformation WHERE Id = '$payment_id'");
    
    if ($delete_payment) {
        $message[] = 'Xóa thông tin thanh toán thành công!';
    } else {
        $message[] = 'Xóa thông tin thanh toán thất bại!';
    }
    // Redirect để tránh việc refresh lại trang sẽ xóa tiếp
    header('location: update_project.php?id=' . $project_id);
    exit();
}

// Xử lý cập nhật thông tin thanh toán
if (isset($_POST['edit_payment'])) {
    $payment_id = mysqli_real_escape_string($conn, $_POST['payment_id']);
    $bank = mysqli_real_escape_string($conn, $_POST['bank']);
    $branch = mysqli_real_escape_string($conn, $_POST['branch']);
    $account_number = mysqli_real_escape_string($conn, $_POST['account_number']);
    $account_name = mysqli_real_escape_string($conn, $_POST['account_name']);
    $auto_transaction = isset($_POST['auto_transaction']) ? 1 : 0;
    $auto_reconciliation = isset($_POST['auto_reconciliation']) ? 1 : 0;

    $update_payment = mysqli_query($conn, "UPDATE PaymentInformation SET 
        Bank = '$bank',
        Branch = '$branch',
        AccountNumber = '$account_number',
        AccountName = '$account_name',
        AutoTransaction = '$auto_transaction',
        AutoReconciliation = '$auto_reconciliation'
        WHERE Id = '$payment_id'");

    if ($update_payment) {
        $message[] = 'Cập nhật thông tin thanh toán thành công!';
    } else {
        $message[] = 'Cập nhật thông tin thanh toán thất bại!';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cập nhật dự án</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="../css/admin_style.css">
    <style>
        body {
            background-color: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .page-header {
            background-color: #f8f9fc;
            padding: 15px 20px;
            color: #4a5568;
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
        
        .create-form {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-top: 20px;
        }
        
        .form-title {
            color: #476a52;
            font-size: 24px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-label {
            font-weight: 500;
            color: #4a5568;
            margin-bottom: 5px;
            display: block;
        }
        
        .form-label .required {
            color: red;
            margin-left: 3px;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            outline: none;
        }
        
        .form-control:focus {
            border-color: #476a52;
            box-shadow: 0 0 0 2px rgba(71, 106, 82, 0.1);
        }
        
        .form-select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            outline: none;
            background-color: white;
            cursor: pointer;
        }
        
        .btn-container {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 25px;
        }
        
        .btn-submit {
            padding: 10px 30px;
            background-color: #476a52;
            color: white;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.2s;
            min-width: 100px;
        }
        
        .btn-submit:hover {
            background-color: #3a5943;
        }
        
        .btn-cancel {
            padding: 10px 30px;
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            min-width: 100px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-cancel:hover {
            background-color: #c82333;
            color: white;
        }
        
        .manage-container {
            width: 100%;
            padding: 20px;
            background-color: #f8f9fc;
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-check-input {
            width: 1.5em;
            height: 1.5em;
            margin-top: 0.25em;
            margin-right: 10px;
        }
        
        .form-check-label {
            font-size: 1em;
            color: #4a5568;
        }
        
        .nav-tabs {
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 20px;
        }
        
        .nav-tabs .nav-link {
            margin-bottom: -1px;
            border: 1px solid transparent;
            border-top-left-radius: 0.25rem;
            border-top-right-radius: 0.25rem;
            padding: 10px 20px;
            color: #476a52;
        }
        
        .nav-tabs .nav-link.active {
            color: #476a52;
            background-color: #fff;
            border-color: #dee2e6 #dee2e6 #fff;
            font-weight: bold;
        }
        
        .nav-tabs .nav-link:hover {
            border-color: #e9ecef #e9ecef #dee2e6;
        }
        
        .tab-content {
            padding: 20px 0;
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        .table td {
            vertical-align: middle;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }

        .modal-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }

        .modal-footer {
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
        }

        .alert {
            padding: 10px 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }

        .alert-info {
            color: #0c5460;
            background-color: #d1ecf1;
            border-color: #bee5eb;
        }

        /* Thêm style cho form switch */
        .form-switch {
            padding-left: 2.5em;
        }

        .form-switch .form-check-input {
            width: 2em;
            margin-left: -2.5em;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3' fill='rgba%280, 0, 0, 0.25%29'/%3e%3c/svg%3e");
            background-position: left center;
            border-radius: 2em;
            transition: background-position .15s ease-in-out;
        }

        .form-switch .form-check-input:checked {
            background-position: right center;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3' fill='%23fff'/%3e%3c/svg%3e");
        }

        .modal-header {
            background-color: #476a52;
            color: white;
        }

        .modal-header .btn-close {
            color: white;
            opacity: 1;
        }

        .modal-footer {
            justify-content: center;
            gap: 10px;
        }

        .modal-footer .btn {
            min-width: 100px;
            border-radius: 5px;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <?php include '../admin_navbar.php'; ?>
        <div class="manage-container">
            <?php
            if (isset($message)) {
                foreach ($message as $msg) {
                    echo '
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <span style="font-size: 16px;">' . $msg . '</span>
                        <i style="font-size: 20px; cursor: pointer" class="fas fa-times" onclick="this.parentElement.remove();"></i>
                    </div>';
                }
            }
            ?>
            
            <div class="page-header">
                <h2 class="form-title">CẬP NHẬT DỰ ÁN</h2>
                <div class="breadcrumb">
                    <a href="/webquanlytoanha/admin/dashboard.php">Trang chủ</a>
                    <span style="margin: 0 8px;">›</span>
                    <a href="/webquanlytoanha/admin/data-info/companies.php">Thông tin công ty</a>
                    <span style="margin: 0 8px;">›</span>
                    <a href="/webquanlytoanha/admin/data-info/projects.php">Danh mục dự án</a>
                    <span style="margin: 0 8px;">›</span>
                    <span>Cập nhật dự án</span>
                </div>
            </div>
            
            <div class="create-form">
                <ul class="nav nav-tabs" id="projectTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button" role="tab" aria-controls="info" aria-selected="true">
                            Thông tin chung
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="payment-tab" data-bs-toggle="tab" data-bs-target="#payment" type="button" role="tab" aria-controls="payment" aria-selected="false">
                            Thông tin thanh toán
                        </button>
                    </li>
                </ul>

                <form action="" method="post" id="mainForm">
                    <div class="tab-content" id="projectTabsContent">
                        <!-- Tab Thông tin chung -->
                        <div class="tab-pane fade show active" id="info" role="tabpanel" aria-labelledby="info-tab">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Khu đô thị <span class="required">*</span></label>
                            <select name="township_id" class="form-select" required>
                                <option value="">Chọn khu đô thị</option>
                                <?php
                                $select_townships = mysqli_query($conn, "SELECT * FROM `Townships` ORDER BY Name");
                                while($township = mysqli_fetch_assoc($select_townships)) {
                                            $selected = ($township['TownShipId'] == $project['TownShipId']) ? 'selected' : '';
                                            echo "<option value='{$township['TownShipId']}' $selected>{$township['Name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Mã dự án <span class="required">*</span></label>
                                    <input type="text" name="project_code" class="form-control" placeholder="Mã dự án" value="<?php echo $project['ProjectID']; ?>" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Mã ban vận hành <span class="required">*</span></label>
                                    <input type="text" name="operation_id" class="form-control" placeholder="Mã ban vận hành" value="<?php echo $project['OperationId']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Tên dự án <span class="required">*</span></label>
                                    <input type="text" name="project_name" class="form-control" placeholder="Tên dự án" value="<?php echo $project['Name']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Mô tả</label>
                                    <textarea name="description" class="form-control" placeholder="Mô tả dự án"><?php echo $project['Description']; ?></textarea>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Địa chỉ <span class="required">*</span></label>
                                    <input type="text" name="address" class="form-control" placeholder="Địa chỉ dự án" value="<?php echo $project['Address']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Số điện thoại <span class="required">*</span></label>
                                    <input type="tel" name="phone" class="form-control" placeholder="Số điện thoại" value="<?php echo $project['Phone']; ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email dự án <span class="required">*</span></label>
                                    <input type="email" name="email" class="form-control" placeholder="Email" value="<?php echo $project['Email']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Trưởng ban quản lý <span class="required">*</span></label>
                            <select name="manager_id" class="form-select" required>
                                <option value="">Chọn trưởng ban quản lý</option>
                                <?php
                                $select_managers = mysqli_query($conn, "SELECT * FROM `Staffs` WHERE Position LIKE '%Trưởng BQL%' OR Position LIKE '%Trưởng ban%' ORDER BY Name");
                                while($manager = mysqli_fetch_assoc($select_managers)) {
                                            $selected = ($manager['ID'] == $project['ManagerId']) ? 'selected' : '';
                                            echo "<option value='{$manager['ID']}' $selected>{$manager['Name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ngày khóa sổ kế toán <span class="required">*</span></label>
                                    <input type="text" name="deadlock" class="form-control" value="<?php echo $project['Deadlock']; ?>" placeholder="Nhập ngày (1-31)" required>
                                </div>
                            </div>

                            <div class="btn-container mt-4">
                                <button type="submit" form="mainForm" name="submit" class="btn-submit">Cập nhật</button>
                                <a href="projects.php" class="btn-cancel">Hủy</a>
                            </div>
                        </div>

                        <!-- Tab Thông tin thanh toán -->
                        <div class="tab-pane fade" id="payment" role="tabpanel" aria-labelledby="payment-tab">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5 class="mb-0">Danh sách thông tin thanh toán</h5>
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
                                    <i class="fas fa-plus"></i> Thêm mới thanh toán
                                </button>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 50px;">STT</th>
                                            <th>Thông tin thanh toán</th>
                                            <th style="width: 100px;">Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $select_payments = mysqli_query($conn, "SELECT * FROM PaymentInformation WHERE ProjectId = '$project_id'");
                                        $stt = 1;
                                        while($payment = mysqli_fetch_assoc($select_payments)) {
                                            echo "<tr>
                                                <td class='text-center'>{$stt}</td>
                                                <td>
                                                    <strong>{$payment['Bank']}</strong><br>
                                                    Chi nhánh: {$payment['Branch']}<br>
                                                    STK: {$payment['AccountNumber']}<br>
                                                    Chủ tài khoản: {$payment['AccountName']}
                                                </td>
                                                <td class='text-center'>
                                                    <button type='button' class='btn btn-sm btn-primary me-1' 
                                                        onclick='editPayment(\"" . $payment['Id'] . "\", 
                                                                   \"" . $payment['AccountNumber'] . "\", 
                                                                   \"" . $payment['Bank'] . "\", 
                                                                   \"" . $payment['AccountName'] . "\", 
                                                                   \"" . $payment['Branch'] . "\", 
                                                                   \"" . ($payment['AutoTransaction'] ?? 0) . "\", 
                                                                   \"" . ($payment['AutoReconciliation'] ?? 0) . "\")'>
                                                        <i class='fas fa-edit'></i>
                                                    </button>
                                                    <a href='update_project.php?id={$project_id}&delete_payment={$payment['Id']}' 
                                                       class='btn btn-sm btn-danger' 
                                                       onclick='return confirm(\"Bạn có chắc chắn muốn xóa thông tin thanh toán này?\")'>
                                                        <i class='fas fa-trash'></i>
                                                    </a>
                                                </td>
                                            </tr>";
                                            $stt++;
                                        }
                                        if (mysqli_num_rows($select_payments) == 0) {
                                            echo "<tr><td colspan='3' class='text-center'>Chưa có thông tin thanh toán</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Thêm mới thanh toán -->
    <div class="modal fade" id="addPaymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Thêm mới thông tin thanh toán</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="post">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Số tài khoản <span class="text-danger">*</span></label>
                            <input type="text" name="account_number" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ngân hàng <span class="text-danger">*</span></label>
                            <select name="bank" class="form-select" required>
                                <option value="">Chọn ngân hàng</option>
                                <option value="Vietcombank">Ngân hàng TMCP Ngoại thương Việt Nam (Vietcombank)</option>
                                <option value="Vietinbank">Ngân hàng TMCP Công thương Việt Nam (Vietinbank)</option>
                                <option value="BIDV">Ngân hàng TMCP Đầu tư và Phát triển Việt Nam (BIDV)</option>
                                <option value="Agribank">Ngân hàng Nông nghiệp và Phát triển Nông thôn Việt Nam (Agribank)</option>
                                <option value="Techcombank">Ngân hàng TMCP Kỹ thương Việt Nam (Techcombank)</option>
                                <option value="ACB">Ngân hàng TMCP Á Châu (ACB)</option>
                                <option value="MBBank">Ngân hàng TMCP Quân đội (MB Bank)</option>
                                <option value="VPBank">Ngân hàng TMCP Việt Nam Thịnh Vượng (VP Bank)</option>
                                <option value="Sacombank">Ngân hàng TMCP Sài Gòn Thương Tín (Sacombank)</option>
                                <option value="TPBank">Ngân hàng TMCP Tiên Phong (TP Bank)</option>
                                <option value="HDBank">Ngân hàng TMCP Phát triển TP.HCM (HD Bank)</option>
                                <option value="OCB">Ngân hàng TMCP Phương Đông (OCB)</option>
                                <option value="SHB">Ngân hàng TMCP Sài Gòn - Hà Nội (SHB)</option>
                                <option value="VIB">Ngân hàng TMCP Quốc tế Việt Nam (VIB)</option>
                                <option value="MSB">Ngân hàng TMCP Hàng Hải (MSB)</option>
                                <option value="SCB">Ngân hàng TMCP Sài Gòn (SCB)</option>
                                <option value="Eximbank">Ngân hàng TMCP Xuất Nhập khẩu Việt Nam (Eximbank)</option>
                                <option value="ABBANK">Ngân hàng TMCP An Bình (ABBANK)</option>
                                <option value="Bac A Bank">Ngân hàng TMCP Bắc Á (Bac A Bank)</option>
                                <option value="SeABank">Ngân hàng TMCP Đông Nam Á (SeABank)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tên tài khoản <span class="text-danger">*</span></label>
                            <input type="text" name="account_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Chi nhánh</label>
                            <input type="text" name="branch" class="form-control">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="auto_transaction" id="autoTransaction">
                                    <label class="form-check-label" for="autoTransaction">Nhận giao dịch tự động</label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="auto_reconciliation" id="autoReconciliation">
                                    <label class="form-check-label" for="autoReconciliation">Tự động hạch toán</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="add_payment" class="btn btn-success">Lưu</button>
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Hủy</button>
                    </div>
                </form>
            </div>
                        </div>
                    </div>
                    
    <!-- Modal Sửa thông tin thanh toán -->
    <div class="modal fade" id="editPaymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Sửa thông tin thanh toán</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="post">
                    <div class="modal-body">
                        <input type="hidden" name="payment_id" id="edit_payment_id">
                        <div class="mb-3">
                            <label class="form-label">Số tài khoản <span class="text-danger">*</span></label>
                            <input type="text" name="account_number" id="edit_account_number" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ngân hàng <span class="text-danger">*</span></label>
                            <select name="bank" id="edit_bank" class="form-select" required>
                                <option value="">Chọn ngân hàng</option>
                                <option value="Vietcombank">Ngân hàng TMCP Ngoại thương Việt Nam (Vietcombank)</option>
                                <option value="Vietinbank">Ngân hàng TMCP Công thương Việt Nam (Vietinbank)</option>
                                <option value="BIDV">Ngân hàng TMCP Đầu tư và Phát triển Việt Nam (BIDV)</option>
                                <option value="Agribank">Ngân hàng Nông nghiệp và Phát triển Nông thôn Việt Nam (Agribank)</option>
                                <option value="Techcombank">Ngân hàng TMCP Kỹ thương Việt Nam (Techcombank)</option>
                                <option value="ACB">Ngân hàng TMCP Á Châu (ACB)</option>
                                <option value="MBBank">Ngân hàng TMCP Quân đội (MB Bank)</option>
                                <option value="VPBank">Ngân hàng TMCP Việt Nam Thịnh Vượng (VP Bank)</option>
                                <option value="Sacombank">Ngân hàng TMCP Sài Gòn Thương Tín (Sacombank)</option>
                                <option value="TPBank">Ngân hàng TMCP Tiên Phong (TP Bank)</option>
                                <option value="HDBank">Ngân hàng TMCP Phát triển TP.HCM (HD Bank)</option>
                                <option value="OCB">Ngân hàng TMCP Phương Đông (OCB)</option>
                                <option value="SHB">Ngân hàng TMCP Sài Gòn - Hà Nội (SHB)</option>
                                <option value="VIB">Ngân hàng TMCP Quốc tế Việt Nam (VIB)</option>
                                <option value="MSB">Ngân hàng TMCP Hàng Hải (MSB)</option>
                                <option value="SCB">Ngân hàng TMCP Sài Gòn (SCB)</option>
                                <option value="Eximbank">Ngân hàng TMCP Xuất Nhập khẩu Việt Nam (Eximbank)</option>
                                <option value="ABBANK">Ngân hàng TMCP An Bình (ABBANK)</option>
                                <option value="Bac A Bank">Ngân hàng TMCP Bắc Á (Bac A Bank)</option>
                                <option value="SeABank">Ngân hàng TMCP Đông Nam Á (SeABank)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tên tài khoản <span class="text-danger">*</span></label>
                            <input type="text" name="account_name" id="edit_account_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Chi nhánh</label>
                            <input type="text" name="branch" id="edit_branch" class="form-control">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="auto_transaction" id="edit_auto_transaction">
                                    <label class="form-check-label" for="edit_auto_transaction">Nhận giao dịch tự động</label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="auto_reconciliation" id="edit_auto_reconciliation">
                                    <label class="form-check-label" for="edit_auto_reconciliation">Tự động hạch toán</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="edit_payment" class="btn btn-success">Lưu</button>
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Hủy</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Lưu trạng thái tab đang active vào localStorage
        document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(function(button) {
            button.addEventListener('shown.bs.tab', function (event) {
                localStorage.setItem('activeProjectTab', event.target.id);
            });
        });

        // Khôi phục tab active khi load lại trang
        window.addEventListener('load', function() {
            var activeTab = localStorage.getItem('activeProjectTab');
            if (activeTab) {
                var tab = new bootstrap.Tab(document.querySelector('#' + activeTab));
                tab.show();
            }
        });

        // Reset form khi đóng modal
        document.getElementById('addPaymentModal').addEventListener('hidden.bs.modal', function () {
            this.querySelector('form').reset();
        });

        // Tự động đóng thông báo sau 3 giây
        setTimeout(function() {
            var alerts = document.getElementsByClassName('alert');
            for(var i = 0; i < alerts.length; i++) {
                alerts[i].style.display = 'none';
            }
        }, 3000);

        // Hàm xử lý khi click nút sửa
        function editPayment(id, accountNumber, bank, accountName, branch, autoTransaction, autoReconciliation) {
            document.getElementById('edit_payment_id').value = id;
            document.getElementById('edit_account_number').value = accountNumber;
            document.getElementById('edit_bank').value = bank;
            document.getElementById('edit_account_name').value = accountName;
            document.getElementById('edit_branch').value = branch;
            document.getElementById('edit_auto_transaction').checked = autoTransaction == "1";
            document.getElementById('edit_auto_reconciliation').checked = autoReconciliation == "1";
            
            var editModal = new bootstrap.Modal(document.getElementById('editPaymentModal'));
            editModal.show();
        }

        // Reset form khi đóng modal sửa
        document.getElementById('editPaymentModal').addEventListener('hidden.bs.modal', function () {
            this.querySelector('form').reset();
        });
    </script>
</body>
</html>