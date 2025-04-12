<?php
include '../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Lấy doanh thu từ bảng debtstatements theo từng tháng trong năm hiện tại
$current_year = date('Y');
$revenue_query = "
    SELECT 
        DATE_FORMAT(IssueDate, '%Y%m') as Month,
        SUM(PaidAmount) as Revenue
    FROM debtstatements 
    WHERE Status = 'Đã thanh toán'
    AND YEAR(IssueDate) = $current_year
    GROUP BY DATE_FORMAT(IssueDate, '%Y%m')
    ORDER BY Month ASC
";
$revenue_result = mysqli_query($conn, $revenue_query);
$revenue_data = [];
while ($row = mysqli_fetch_assoc($revenue_result)) {
    $revenue_data[$row['Month']] = $row['Revenue'];
}

// Lấy tỷ lệ dòng tiền (tiền mặt/chuyển khoản) từ bảng receipt
$payment_method_query = "
    SELECT 
        PaymentMethod,
        COUNT(*) as Count,
        SUM(Total) as Total
    FROM receipt
    WHERE PaymentMethod IN ('Tiền mặt', 'Chuyển khoản')
    GROUP BY PaymentMethod
";
$payment_method_result = mysqli_query($conn, $payment_method_query);
$payment_data = [
    'Tiền mặt' => 0,
    'Chuyển khoản' => 0
];
while ($row = mysqli_fetch_assoc($payment_method_result)) {
    $payment_data[$row['PaymentMethod']] = $row['Total'];
}

// Đếm số căn hộ
$apartment_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM apartment"))['count'];

// Đếm số cư dân
$resident_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM resident"))['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dashboard-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            text-align: center;
            transition: transform 0.2s;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            font-size: 2.5rem;
            color: #476a52;
            margin-bottom: 15px;
        }
        .stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #476a52;
            margin-bottom: 5px;
        }
        .stat-label {
            color: #666;
            font-size: 16px;
            font-weight: 500;
            text-transform: uppercase;
        }
        .chart-title {
            color: #476a52;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <?php include 'admin_navbar.php'; ?>
        <div class="flex-grow-1">
            <?php include 'admin_header.php'; ?>
            
            <div class="container-fluid p-4">
                <div class="row">
                    <!-- Biểu đồ doanh thu -->
                    <div class="col-md-8">
                        <div class="dashboard-card">
                            <div class="d-flex align-items-center mb-4">
                                <i class="fas fa-chart-line me-2" style="color: #476a52; font-size: 24px;"></i>
                                <h5 class="chart-title mb-0">Doanh thu</h5>
                            </div>
                            <div class="chart-container">
                                <canvas id="revenueChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Biểu đồ dòng tiền -->
                    <div class="col-md-4">
                        <div class="dashboard-card">
                            <div class="d-flex align-items-center mb-4">
                                <i class="fas fa-money-bill-wave me-2" style="color: #476a52; font-size: 24px;"></i>
                                <h5 class="chart-title mb-0">Dòng tiền</h5>
                            </div>
                            <div class="chart-container">
                                <canvas id="paymentChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Thống kê -->
                    <div class="col-md-6">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-building"></i>
                            </div>
                            <div class="stat-number"><?php echo $apartment_count; ?></div>
                            <div class="stat-label">CĂN HỘ</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-number"><?php echo $resident_count; ?></div>
                            <div class="stat-label">CƯ DÂN CĂN HỘ</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Biểu đồ doanh thu
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_keys($revenue_data)); ?>,
                datasets: [{
                    label: 'Doanh thu',
                    data: <?php echo json_encode(array_values($revenue_data)); ?>,
                    backgroundColor: '#476a52',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            drawBorder: false
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Biểu đồ dòng tiền
        const paymentCtx = document.getElementById('paymentChart').getContext('2d');
        new Chart(paymentCtx, {
            type: 'doughnut',
            data: {
                labels: ['Tiền mặt', 'Chuyển khoản'],
                datasets: [{
                    data: [
                        <?php echo $payment_data['Tiền mặt']; ?>,
                        <?php echo $payment_data['Chuyển khoản']; ?>
                    ],
                    backgroundColor: ['#36A2EB', '#FF6384'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    }
                },
                cutout: '75%'
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>