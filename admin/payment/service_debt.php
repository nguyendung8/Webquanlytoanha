<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Xử lý các tham số tìm kiếm
$document_number = isset($_GET['document_number']) ? mysqli_real_escape_string($conn, $_GET['document_number']) : '';
$service = isset($_GET['service']) ? mysqli_real_escape_string($conn, $_GET['service']) : '';
$accounting_date_from = isset($_GET['accounting_date_from']) ? mysqli_real_escape_string($conn, $_GET['accounting_date_from']) : '';
$accounting_date_to = isset($_GET['accounting_date_to']) ? mysqli_real_escape_string($conn, $_GET['accounting_date_to']) : '';

// Thiết lập phân trang
$records_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Query để lấy dữ liệu dịch vụ và thanh toán
$query = "
    SELECT 
        r.ReceiptID as DocumentNumber,
        r.AccountingDate,
        s.ServiceCode,
        s.Name as ServiceName,
        rd.Payment as PaidAmount,
        dsd.PaidAmount as RequiredAmount,
        GREATEST(0, dsd.PaidAmount - COALESCE(rd.Payment, 0)) as RemainingBalance
    FROM receiptdetails rd
    JOIN services s ON rd.ServiceCode = s.ServiceCode
    JOIN receipt r ON rd.ReceiptID = r.ReceiptID
    LEFT JOIN debtstatementdetail dsd ON rd.ServiceCode = dsd.ServiceCode
    LEFT JOIN debtstatements ds ON dsd.InvoiceCode = ds.InvoiceCode
    WHERE 1=1
";

// Thêm điều kiện tìm kiếm
if (!empty($document_number)) {
    $query .= " AND r.ReceiptID LIKE '%$document_number%'";
}
if (!empty($service)) {
    $query .= " AND s.ServiceCode = '$service'";
}
if (!empty($accounting_date_from)) {
    $query .= " AND r.AccountingDate >= '$accounting_date_from'";
}
if (!empty($accounting_date_to)) {
    $query .= " AND r.AccountingDate <= '$accounting_date_to'";
}

$query .= " ORDER BY r.AccountingDate DESC, r.ReceiptID LIMIT $offset, $records_per_page";

$result = mysqli_query($conn, $query);

// Query đếm tổng số bản ghi
$count_query = str_replace("SELECT 
        r.ReceiptID as DocumentNumber,
        r.AccountingDate,
        s.ServiceCode,
        s.Name as ServiceName,
        rd.Payment as PaidAmount,
        dsd.PaidAmount as RequiredAmount,
        GREATEST(0, dsd.PaidAmount - COALESCE(rd.Payment, 0)) as RemainingBalance", "SELECT COUNT(*)", $query);
$count_query = preg_replace("/LIMIT \d+, \d+/", "", $count_query);
$total_records = mysqli_fetch_assoc(mysqli_query($conn, $count_query))['COUNT(*)'];
$total_pages = ceil($total_records / $records_per_page);

// Lấy danh sách dịch vụ cho dropdown
$services_query = mysqli_query($conn, "SELECT ServiceCode, Name FROM services WHERE Status = 'active'");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo cáo chi tiết công nợ theo dịch vụ</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .page-header {
            background-color: #f5f5f5;
            padding: 15px 20px;
            margin-bottom: 20px;
        }
        .search-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .table th {
            background-color: #f8f9fa;
            color: #476a52;
            font-weight: 600;
        }
        .form-control, .form-select {
            border-radius: 4px;
        }
        .btn-search {
            background-color: #476a52;
            border: none;
            border-radius: 5px;
            padding: 5px 10px;
            color: white;
        }
        .btn-search:hover {
            background-color: #3c5a46;
            color: white;
        }
        .btn-export {
            background-color: #476a52;
            color: white;
        }
        .btn-export:hover {
            background-color: #3c5a46;
            color: white;
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
                    <h2 class="text-uppercase fw-bold" style="color: #476a52;">Báo cáo chi tiết công nợ theo dịch vụ</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="/webquanlytoanha/admin/dashboard.php" class="text-decoration-none" style="color: #476a52;">Trang chủ</a></li>
                            <li class="breadcrumb-item active">Báo cáo chi tiết công nợ theo dịch vụ</li>
                        </ol>
                    </nav>
                </div>

                <div class="search-container">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <input type="text" class="form-control" name="document_number" 
                                   placeholder="Số hiệu chứng từ" value="<?php echo $document_number; ?>">
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="service">
                                <option value="">Chọn dịch vụ</option>
                                <?php while($srv = mysqli_fetch_assoc($services_query)) { ?>
                                    <option value="<?php echo $srv['ServiceCode']; ?>" 
                                            <?php echo ($service == $srv['ServiceCode']) ? 'selected' : ''; ?>>
                                        <?php echo $srv['Name']; ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="date" class="form-control" name="accounting_date_from" 
                                   value="<?php echo $accounting_date_from; ?>">
                        </div>
                        <div class="col-md-2">
                            <input type="date" class="form-control" name="accounting_date_to" 
                                   value="<?php echo $accounting_date_to; ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class=" btn-search">
                                <i class="fas fa-search me-2"></i>Tìm kiếm
                            </button>
                            <!-- <button type="button" class="btn btn-export ms-2">
                                <i class="fas fa-file-export me-2"></i>Export
                            </button> -->
                        </div>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th style="width: 50px;">
                                    <input type="checkbox" class="form-check-input" id="selectAll">
                                </th>
                                <th>STT</th>
                                <th>Ngày chứng từ</th>
                                <th>Số hiệu chứng từ</th>
                                <th>Dịch vụ</th>
                                <th>Số tiền phải thu</th>
                                <th>Số tiền đã thanh toán</th>
                                <th>Dư nợ còn lại</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if(mysqli_num_rows($result) > 0){
                                $i = $offset + 1;
                                while($row = mysqli_fetch_assoc($result)) {
                            ?>
                            <tr>
                                <td><input type="checkbox" class="form-check-input row-checkbox"></td>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($row['AccountingDate'])); ?></td>
                                <td><?php echo $row['DocumentNumber']; ?></td>
                                <td><?php echo $row['ServiceName']; ?></td>
                                <td class="text-end"><?php echo number_format($row['RequiredAmount'], 0, ',', '.'); ?></td>
                                <td class="text-end"><?php echo number_format($row['PaidAmount'], 0, ',', '.'); ?></td>
                                <td class="text-end">
                                    <?php 
                                        $remaining = floatval($row['RemainingBalance']);
                                        echo number_format($remaining <= 0 ? 0 : $remaining, 0, ',', '.'); 
                                    ?>
                                </td>
                            </tr>
                            <?php
                                }
                            } else {
                                echo '<tr><td colspan="8" class="text-center">Không có dữ liệu</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-4">
                    <div>Tổng số: <?php echo $total_records; ?> bản ghi</div>
                    <nav>
                        <ul class="pagination mb-0">
                            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php 
                                    echo !empty($document_number) ? "&document_number=$document_number" : '';
                                    echo !empty($service) ? "&service=$service" : '';
                                    echo !empty($accounting_date_from) ? "&accounting_date_from=$accounting_date_from" : '';
                                    echo !empty($accounting_date_to) ? "&accounting_date_to=$accounting_date_to" : '';
                                ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('selectAll').addEventListener('change', function() {
            document.querySelectorAll('.row-checkbox').forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    </script>
</body>
</html>