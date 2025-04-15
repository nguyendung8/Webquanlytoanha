<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Lấy danh sách dự án
$projects_query = "SELECT ProjectID, Name FROM Projects WHERE Status = 'active'";
$projects_result = mysqli_query($conn, $projects_query);

// Xử lý filter
$selected_project = isset($_GET['project']) ? mysqli_real_escape_string($conn, $_GET['project']) : '';
$selected_month = isset($_GET['month']) ? mysqli_real_escape_string($conn, $_GET['month']) : date('Y-m');
$selected_year = isset($_GET['year']) ? mysqli_real_escape_string($conn, $_GET['year']) : date('Y');

// 1. Query số lượng sử dụng theo dịch vụ
$usage_query = "
    SELECT 
        s.ServiceCode,
        s.Name as ServiceName,
        COUNT(DISTINCT cs.ContractCode) as UsageCount
    FROM services s
    LEFT JOIN ContractServices cs ON s.ServiceCode = cs.ServiceId
    WHERE 1=1 
    " . ($selected_project ? "AND s.ProjectId = '$selected_project'" : "") . "
    AND (
        DATE_FORMAT(cs.ApplyDate, '%Y-%m') <= '$selected_month' 
        AND (cs.EndDate IS NULL OR DATE_FORMAT(cs.EndDate, '%Y-%m') >= '$selected_month')
    )
    GROUP BY s.ServiceCode, s.Name
    ORDER BY UsageCount DESC";

$usage_result = mysqli_query($conn, $usage_query);

// 2. Query tỷ trọng doanh thu
$revenue_ratio_query = "
    SELECT 
        s.ServiceCode,
        s.Name as ServiceName,
        SUM(pl.Price) as TotalRevenue
    FROM services s
    JOIN ContractServices cs ON s.ServiceCode = cs.ServiceId
    JOIN ServicePrice sp ON s.ServiceCode = sp.ServiceId
    JOIN pricelist pl ON sp.PriceId = pl.ID
    WHERE 1=1 
    " . ($selected_project ? "AND s.ProjectId = '$selected_project'" : "") . "
    AND DATE_FORMAT(cs.ApplyDate, '%Y-%m') = '$selected_month'
    GROUP BY s.ServiceCode, s.Name";

$revenue_ratio_result = mysqli_query($conn, $revenue_ratio_query);

// 3. Query xu hướng doanh thu theo thời gian
$trend_query = "
    SELECT 
        s.ServiceCode,
        s.Name as ServiceName,
        DATE_FORMAT(cs.ApplyDate, '%m') as Month,
        SUM(pl.Price) as MonthlyRevenue
    FROM services s
    JOIN ContractServices cs ON s.ServiceCode = cs.ServiceId
    JOIN ServicePrice sp ON s.ServiceCode = sp.ServiceId
    JOIN pricelist pl ON sp.PriceId = pl.ID
    WHERE 1=1 
    " . ($selected_project ? "AND s.ProjectId = '$selected_project'" : "") . "
    AND DATE_FORMAT(cs.ApplyDate, '%Y') = '$selected_year'
    GROUP BY s.ServiceCode, s.Name, Month
    ORDER BY Month";

$trend_result = mysqli_query($conn, $trend_query);

// 4. Query các chỉ số tổng hợp
// Doanh thu cao nhất
$highest_revenue_query = "
    SELECT 
        s.ServiceCode,
        s.Name as ServiceName,
        COALESCE(SUM(pl.Price), 0) as TotalRevenue
    FROM services s
    LEFT JOIN ContractServices cs ON s.ServiceCode = cs.ServiceId
    LEFT JOIN ServicePrice sp ON s.ServiceCode = sp.ServiceId
    LEFT JOIN pricelist pl ON sp.PriceId = pl.ID
    WHERE 1=1 
    " . ($selected_project ? "AND s.ProjectId = '$selected_project'" : "") . "
    AND (cs.ApplyDate IS NULL OR DATE_FORMAT(cs.ApplyDate, '%Y-%m') = '$selected_month')
    GROUP BY s.ServiceCode, s.Name
    HAVING TotalRevenue > 0
    ORDER BY TotalRevenue DESC
    LIMIT 1";

$highest_revenue_result = mysqli_query($conn, $highest_revenue_query);

// Doanh thu thấp nhất
$lowest_revenue_query = "
    SELECT 
        s.ServiceCode,
        s.Name as ServiceName,
        COALESCE(SUM(pl.Price), 0) as TotalRevenue
    FROM services s
    LEFT JOIN ContractServices cs ON s.ServiceCode = cs.ServiceId
    LEFT JOIN ServicePrice sp ON s.ServiceCode = sp.ServiceId
    LEFT JOIN pricelist pl ON sp.PriceId = pl.ID
    WHERE 1=1
    " . ($selected_project ? "AND s.ProjectId = '$selected_project'" : "") . "
    AND (cs.ApplyDate IS NULL OR DATE_FORMAT(cs.ApplyDate, '%Y-%m') = '$selected_month')
    GROUP BY s.ServiceCode, s.Name
    HAVING TotalRevenue >= 0
    ORDER BY TotalRevenue ASC
    LIMIT 1";

$lowest_revenue_result = mysqli_query($conn, $lowest_revenue_query);

// Sử dụng nhiều nhất
$most_used_query = "
    SELECT 
        s.ServiceCode,
        s.Name as ServiceName,
        COUNT(DISTINCT cs.ContractCode) as UsageCount
    FROM services s
    LEFT JOIN ContractServices cs ON s.ServiceCode = cs.ServiceId
    WHERE 1=1 
    " . ($selected_project ? "AND s.ProjectId = '$selected_project'" : "") . "
    AND (cs.ApplyDate IS NULL OR DATE_FORMAT(cs.ApplyDate, '%Y-%m') = '$selected_month')
    GROUP BY s.ServiceCode, s.Name
    HAVING UsageCount > 0
    ORDER BY UsageCount DESC
    LIMIT 1";

$most_used_result = mysqli_query($conn, $most_used_query);

// Sử dụng ít nhất
$least_used_query = "
    SELECT 
        s.ServiceCode,
        s.Name as ServiceName,
        COUNT(DISTINCT cs.ContractCode) as UsageCount
    FROM services s
    LEFT JOIN ContractServices cs ON s.ServiceCode = cs.ServiceId
    WHERE 1=1 
    " . ($selected_project ? "AND s.ProjectId = '$selected_project'" : "") . "
    AND (cs.ApplyDate IS NULL OR DATE_FORMAT(cs.ApplyDate, '%Y-%m') = '$selected_month')
    GROUP BY s.ServiceCode, s.Name
    HAVING UsageCount >= 0
    ORDER BY UsageCount ASC
    LIMIT 1";

$least_used_result = mysqli_query($conn, $least_used_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo cáo tổng hợp</title>
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
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            height: 400px;
        }
        .nav-pills .nav-link.active {
            background-color: #476a52;
        }
        .nav-pills .nav-link {
            color: #476a52;
        }
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stats-card h3 {
            color: #476a52;
            font-size: 1rem;
            margin-bottom: 10px;
        }
        .stats-value {
            font-size: 1.2rem;
            font-weight: bold;
            color: #476a52;
        }
        .stats-value.text-danger {
            color: #dc3545;
        }
        .timestamp {
            font-size: 13px;
            color: #6c757d;
            font-style: italic;
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            padding: 2px 5px;
            border-radius: 3px;
            margin-bottom: 20px;
            margin-left: 20px;
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
                    <h2 class="text-uppercase fw-bold" style="color: #476a52;">Báo cáo tổng hợp</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="/webquanlytoanha/admin/dashboard.php" class="text-decoration-none" style="color: #476a52;">Trang chủ</a></li>
                            <li class="breadcrumb-item">Báo cáo</li>
                            <li class="breadcrumb-item active">Báo cáo tổng hợp</li>
                        </ol>
                    </nav>
                </div>

                <div class="row mb-4">
                        <div class="col-md-3">
                        <select class="form-select" id="projectSelect" name="project">
                            <option value="">Tất cả dự án</option>
                            <?php while($project = mysqli_fetch_assoc($projects_result)) { ?>
                                <option value="<?php echo $project['ProjectID']; ?>" 
                                        <?php echo ($selected_project == $project['ProjectID']) ? 'selected' : ''; ?>>
                                    <?php echo $project['Name']; ?>
                                    </option>
                                <?php } ?>
                            </select>
                    </div>
                    <div class="col-md-3">
                        <input type="month" class="form-control" id="monthSelect" 
                               value="<?php echo $selected_month; ?>">
                    </div>
                </div>

                <div class="row">
                    <!-- Biểu đồ số lượng sử dụng -->
                    <div class="col-md-6">
                        <div class="chart-container">
                            <h3>Số lượng sử dụng theo dịch vụ</h3>
                            <canvas id="usageChart"></canvas>
                        </div>
                        <div class="timestamp">
                            <?php 
                            date_default_timezone_set('Asia/Ho_Chi_Minh');
                            echo "Số liệu tính đến " . date('H:i d/m/Y'); 
                            ?>
                        </div>
                    </div>

                    <!-- Biểu đồ tỷ trọng doanh thu -->
                    <div class="col-md-6">
                        <div class="chart-container">
                            <h3>Tỷ trọng doanh thu</h3>
                            <canvas id="revenueRatioChart"></canvas>
                        </div>
                        <div class="timestamp">
                            <?php 
                            echo "Số liệu tính đến " . date('H:i d/m/Y'); 
                            ?>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Biểu đồ xu hướng doanh thu -->
                    <div class="col-12">
                        <div class="chart-container">
                            <h3>Xu hướng doanh thu theo thời gian</h3>
                            <canvas id="trendChart"></canvas>
                        </div>
                        <div class="timestamp">
                            <?php 
                            echo "Số liệu tính đến " . date('H:i d/m/Y'); 
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Các chỉ số tổng hợp -->
                <div class="row">
                    <?php 
                    // Kiểm tra và gán giá trị mặc định nếu không có dữ liệu
                    $highest_revenue = mysqli_fetch_assoc($highest_revenue_result) ?: ['ServiceName' => 'Không có dữ liệu'];
                    $lowest_revenue = mysqli_fetch_assoc($lowest_revenue_result) ?: ['ServiceName' => 'Không có dữ liệu'];
                    $most_used = mysqli_fetch_assoc($most_used_result) ?: ['ServiceName' => 'Không có dữ liệu'];
                    $least_used = mysqli_fetch_assoc($least_used_result) ?: ['ServiceName' => 'Không có dữ liệu'];
                    ?>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h3>Doanh thu cao nhất</h3>
                            <div class="stats-value">
                                <?php echo htmlspecialchars($highest_revenue['ServiceName']); ?>
                            </div>
                            <div class="text-muted small">
                                <?php 
                                if (isset($highest_revenue['TotalRevenue'])) {
                                    echo number_format($highest_revenue['TotalRevenue'], 0, ',', '.') . ' VNĐ';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h3>Doanh thu thấp nhất</h3>
                            <div class="stats-value text-danger">
                                <?php echo htmlspecialchars($lowest_revenue['ServiceName']); ?>
                            </div>
                            <div class="text-muted small">
                                    <?php 
                                if (isset($lowest_revenue['TotalRevenue'])) {
                                    echo number_format($lowest_revenue['TotalRevenue'], 0, ',', '.') . ' VNĐ';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h3>Lượt sử dụng nhiều nhất</h3>
                            <div class="stats-value">
                                <?php echo htmlspecialchars($most_used['ServiceName']); ?>
                            </div>
                            <div class="text-muted small">
                            <?php
                                if (isset($most_used['UsageCount'])) {
                                    echo $most_used['UsageCount'] . ' lượt';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h3>Lượt sử dụng ít nhất</h3>
                            <div class="stats-value text-danger">
                                <?php echo htmlspecialchars($least_used['ServiceName']); ?>
                            </div>
                            <div class="text-muted small">
                                <?php 
                                if (isset($least_used['UsageCount'])) {
                                    echo $least_used['UsageCount'] . ' lượt';
                                }
                                ?>
                            </div>
                        </div>
                </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Dữ liệu cho biểu đồ số lượng sử dụng
        const usageData = {
            labels: [<?php 
                $labels = [];
                mysqli_data_seek($usage_result, 0);
                while($row = mysqli_fetch_assoc($usage_result)) {
                    $labels[] = "'" . $row['ServiceName'] . "'";
                }
                echo implode(',', $labels);
            ?>],
            datasets: [{
                label: 'Số lượng sử dụng',
                data: [<?php 
                    $data = [];
                    mysqli_data_seek($usage_result, 0);
                    while($row = mysqli_fetch_assoc($usage_result)) {
                        $data[] = $row['UsageCount'];
                    }
                    echo implode(',', $data);
                ?>],
                backgroundColor: [
                    'rgba(255, 99, 132, 0.5)',
                    'rgba(54, 162, 235, 0.5)',
                    'rgba(255, 206, 86, 0.5)',
                    'rgba(75, 192, 192, 0.5)',
                    'rgba(153, 102, 255, 0.5)'
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)'
                ],
                borderWidth: 1
            }]
        };

        // Dữ liệu cho biểu đồ tỷ trọng doanh thu
        const revenueRatioData = {
            labels: [<?php 
                $labels = [];
                $total_revenue = 0;
                mysqli_data_seek($revenue_ratio_result, 0);
                while($row = mysqli_fetch_assoc($revenue_ratio_result)) {
                    $total_revenue += $row['TotalRevenue'];
                }
                mysqli_data_seek($revenue_ratio_result, 0);
                while($row = mysqli_fetch_assoc($revenue_ratio_result)) {
                    $labels[] = "'" . $row['ServiceName'] . "'";
                }
                echo implode(',', $labels);
            ?>],
            datasets: [{
                data: [<?php 
                    $data = [];
                    mysqli_data_seek($revenue_ratio_result, 0);
                    while($row = mysqli_fetch_assoc($revenue_ratio_result)) {
                        $data[] = round(($row['TotalRevenue'] / $total_revenue) * 100, 1);
                    }
                    echo implode(',', $data);
                ?>],
                backgroundColor: [
                    'rgba(255, 99, 132, 0.5)',
                    'rgba(54, 162, 235, 0.5)',
                    'rgba(255, 206, 86, 0.5)',
                    'rgba(75, 192, 192, 0.5)',
                    'rgba(153, 102, 255, 0.5)'
                ]
            }]
        };

        // Dữ liệu cho biểu đồ xu hướng
        const trendData = {
            labels: ['Tháng 1', 'Tháng 2', 'Tháng 3', 'Tháng 4', 'Tháng 5', 'Tháng 6', 
                    'Tháng 7', 'Tháng 8', 'Tháng 9', 'Tháng 10', 'Tháng 11', 'Tháng 12'],
            datasets: [
                <?php
                $services = [];
                $monthly_data = [];
                
                while($row = mysqli_fetch_assoc($trend_result)) {
                    if (!isset($services[$row['ServiceCode']])) {
                        $services[$row['ServiceCode']] = [
                            'name' => $row['ServiceName'],
                            'data' => array_fill(0, 12, 0)
                        ];
                    }
                    $services[$row['ServiceCode']]['data'][$row['Month']-1] = $row['MonthlyRevenue'];
                }
                
                $colors = [
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)'
                ];
                
                $i = 0;
                foreach ($services as $service) {
                    echo "{
                        label: '" . $service['name'] . "',
                        data: [" . implode(',', $service['data']) . "],
                        borderColor: '" . $colors[$i % count($colors)] . "',
                        fill: false,
                        tension: 0.1
                    },";
                    $i++;
                }
                ?>
            ]
        };

        // Cập nhật hàm xử lý sự kiện
        document.getElementById('projectSelect').addEventListener('change', updateCharts);
        document.getElementById('monthSelect').addEventListener('change', updateCharts);

        function updateCharts() {
            const project = document.getElementById('projectSelect').value;
            const month = document.getElementById('monthSelect').value;
            window.location.href = `?project=${project}&month=${month}`;
        }

        // Cập nhật style cho các biểu đồ
        const chartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        };

        // Khởi tạo biểu đồ số lượng sử dụng
        new Chart(document.getElementById('usageChart'), {
            type: 'bar',
            data: usageData,
            options: {
                ...chartOptions,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Khởi tạo biểu đồ tỷ trọng doanh thu
        new Chart(document.getElementById('revenueRatioChart'), {
            type: 'pie',
            data: revenueRatioData,
            options: {
                ...chartOptions,
                plugins: {
                    ...chartOptions.plugins,
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.raw + '%';
                            }
                        }
                    }
                }
            }
        });

        // Khởi tạo biểu đồ xu hướng
        new Chart(document.getElementById('trendChart'), {
            type: 'line',
            data: trendData,
            options: {
                ...chartOptions,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>