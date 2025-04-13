<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Lấy period từ URL
$period = isset($_GET['period']) ? $_GET['period'] : date('mY');

// Query để lấy dữ liệu theo ngày trong tháng
$query = "
    SELECT 
        DAY(combined.AccountingDate) as Day,
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
        WHERE 
            Status != 'deleted' AND
            DATE_FORMAT(AccountingDate, '%m%Y') = ?
        
        UNION ALL
        
        SELECT 
            AccountingDate,
            Total,
            'payment' as Type
        FROM payments
        WHERE 
            DeletedBy IS NULL AND
            DATE_FORMAT(AccountingDate, '%m%Y') = ?
    ) combined
    GROUP BY DAY(combined.AccountingDate)
    ORDER BY Day ASC
";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'ss', $period, $period);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$days = [];
$totalReceipts = [];
$totalPayments = [];

while ($row = mysqli_fetch_assoc($result)) {
    $days[] = $row['Day'];
    $totalReceipts[] = $row['TotalReceipt'];
    $totalPayments[] = $row['TotalPayment'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết báo cáo thu - nợ</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .page-header {
            background-color: #f5f5f5;
            padding: 15px 20px;
            margin-bottom: 20px;
        }
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            height: 400px;
        }
        .btn-back {
            color: #476a52;
            text-decoration: none;
        }
        .btn-back:hover {
            color: #3c5a46;
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
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="text-uppercase fw-bold" style="color: #476a52;">
                            Báo cáo chi tiết thu - nợ trong kỳ
                        </h2>
                        <a href="payment_debt_report.php" class="btn-back">
                            <i class="fas fa-arrow-left me-2"></i>Quay lại
                        </a>
                    </div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="/webquanlytoanha/admin/dashboard.php" class="text-decoration-none" style="color: #476a52;">Trang chủ</a></li>
                            <li class="breadcrumb-item"><a href="payment_debt_report.php" class="text-decoration-none" style="color: #476a52;">Báo cáo thu - nợ</a></li>
                            <li class="breadcrumb-item active">Chi tiết kỳ <?php echo $period; ?></li>
                        </ol>
                    </nav>
                </div>

                <div class="chart-container">
                    <canvas id="debtChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const ctx = document.getElementById('debtChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($days); ?>,
                datasets: [
                    {
                        label: 'Tổng thu',
                        data: <?php echo json_encode($totalReceipts); ?>,
                        borderColor: '#36A2EB',
                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                        tension: 0.4,
                        fill: false
                    },
                    {
                        label: 'Tổng nợ',
                        data: <?php echo json_encode($totalPayments); ?>,
                        borderColor: '#4BC0C0',
                        backgroundColor: 'rgba(75, 192, 192, 0.1)',
                        tension: 0.4,
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            drawBorder: false,
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat('vi-VN').format(value);
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
