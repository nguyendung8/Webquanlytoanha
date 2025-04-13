<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Lấy dữ liệu theo kỳ công nợ (tháng/năm)
$query = "
    SELECT 
        DATE_FORMAT(combined.AccountingDate, '%m%Y') as DebtPeriod,
        SUM(CASE 
            WHEN combined.Type = 'receipt' THEN combined.Total 
            ELSE 0 
        END) as TotalReceipt,
        SUM(CASE 
            WHEN combined.Type = 'payment' THEN combined.Total 
            ELSE 0 
        END) as TotalPayment
    FROM (
        SELECT 
            AccountingDate,
            Total,
            'receipt' as Type
        FROM receipt
        WHERE Status != 'deleted'
        
        UNION ALL
        
        SELECT 
            AccountingDate,
            Total,
            'payment' as Type
        FROM payments
        WHERE DeletedBy IS NULL
    ) combined
    GROUP BY DATE_FORMAT(combined.AccountingDate, '%m%Y')
    ORDER BY combined.AccountingDate DESC
";

$result = mysqli_query($conn, $query);
$total_records = mysqli_num_rows($result);

// Thiết lập phân trang
$records_per_page = 5;
$total_pages = ceil($total_records / $records_per_page);
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Query với phân trang
$paged_query = $query . " LIMIT $offset, $records_per_page";
$result = mysqli_query($conn, $paged_query);

// Query cho dropdown kỳ công nợ
$period_query = "
    SELECT DISTINCT DATE_FORMAT(AccountingDate, '%m%Y') as period 
    FROM (
        SELECT AccountingDate FROM receipt WHERE Status != 'deleted'
        UNION 
        SELECT AccountingDate FROM payments WHERE DeletedBy IS NULL
    ) as dates 
    ORDER BY period DESC
";
$periods = mysqli_query($conn, $period_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo cáo thu - nợ</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .page-header {
            background-color: #f5f5f5;
            padding: 15px 20px;
            margin-bottom: 20px;
        }
        .report-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .table th {
            background-color: #f8f9fa;
            color: #476a52;
            font-weight: 600;
        }
        .btn-analytics {
            background-color: #476a52;
            border: none;
            border-radius: 5px;
            padding: 5px 10px;
            color: white;
        }
        .btn-analytics:hover {
            background-color: #3c5a46;
            color: white;
        }
        .filter-section select {
            min-width: 200px;
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
                    <h2 class="text-uppercase fw-bold" style="color: #476a52;">Báo cáo thu - nợ</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="/webquanlytoanha/admin/dashboard.php" class="text-decoration-none" style="color: #476a52;">Trang chủ</a></li>
                            <li class="breadcrumb-item"><a style="text-decoration: none; color: #476a52;" href="/webquanlytoanha/admin/payment/payment_reports.php">Báo cáo</a></li>
                            <li class="breadcrumb-item active">Báo cáo thu - nợ</li>
                        </ol>
                    </nav>
                </div>

                <div class="report-container">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="filter-section">
                            <select class="form-select" id="kyNo">
                                <option value="">Kỳ công nợ</option>
                                <?php while($period = mysqli_fetch_assoc($periods)) { ?>
                                    <option value="<?php echo $period['period']; ?>"><?php echo $period['period']; ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div>
                            <!-- <button class="btn-analytics me-2">
                                <i class="fas fa-chart-line me-2"></i>View analytics
                            </button> -->
                            <!-- <button class="btn btn-export">
                                <i class="fas fa-file-export me-2"></i>Export
                            </button> -->
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>STT</th>
                                    <th>Kỳ công nợ</th>
                                    <th>Tổng thu cuối kỳ (VNĐ)</th>
                                    <th>Tổng nợ cuối kỳ (VNĐ)</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if(mysqli_num_rows($result) > 0){
                                    $i = $offset + 1;
                                    while($row = mysqli_fetch_assoc($result)) {
                                ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td><?php echo $row['DebtPeriod']; ?></td>
                                    <td class="text-end"><?php echo number_format($row['TotalReceipt'], 0, ',', '.'); ?></td>
                                    <td class="text-end"><?php echo number_format($row['TotalPayment'], 0, ',', '.'); ?></td>
                                    <td>
                                    <a style="text-decoration: none;" href="payment_debt_report_detail.php?period=<?php echo $row['DebtPeriod']; ?>" class="btn-analytics me-2">
                                        <i class="fas fa-chart-line me-2"></i>View analytics
                                    </a >
                                    </td>
                                </tr>
                                <?php
                                    }
                                } else {
                                    echo '<tr><td colspan="5" class="text-center">Không có dữ liệu</td></tr>';
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
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    </div>
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

        document.getElementById('kyNo').addEventListener('change', function() {
            const period = this.value;
            if(period) {
                window.location.href = `?period=${period}`;
            }
        });
    </script>
</body>
</html>