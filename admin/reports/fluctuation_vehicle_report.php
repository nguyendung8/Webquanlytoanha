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

// Query biến động số lượng phương tiện theo tháng
$vehicle_trend_query = "
    SELECT 
        v.TypeVehicle,
        MONTH(CURRENT_DATE) as Month,
        COUNT(v.VehicleCode) as VehicleCount
    FROM vehicles v
    JOIN apartment a ON v.ApartmentID = a.ApartmentID
    JOIN buildings b ON a.BuildingId = b.ID
    WHERE v.Status = 'active'
    " . ($selected_project ? "AND b.ProjectId = '$selected_project'" : "") . "
    AND YEAR(CURRENT_DATE) = '$selected_year'
    GROUP BY v.TypeVehicle, Month
    ORDER BY Month, v.TypeVehicle";

$vehicle_trend_result = mysqli_query($conn, $vehicle_trend_query);

// Query cơ cấu tổng số phương tiện
$vehicle_ratio_query = "
    SELECT 
        v.TypeVehicle,
        COUNT(v.VehicleCode) as VehicleCount
    FROM vehicles v
    JOIN apartment a ON v.ApartmentID = a.ApartmentID
    JOIN buildings b ON a.BuildingId = b.ID
    WHERE v.Status = 'active'
    " . ($selected_project ? "AND b.ProjectId = '$selected_project'" : "") . "
    AND DATE_FORMAT(CURRENT_DATE, '%Y-%m') = '$selected_month'
    GROUP BY v.TypeVehicle";

$vehicle_ratio_result = mysqli_query($conn, $vehicle_ratio_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo cáo biến động</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            margin-bottom: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            height: 400px;
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
        .nav-tabs .nav-link.active {
            background-color: #476a52 !important;
            color: white !important;
        }
        .nav-pills .nav-link {
            color: #476a52;
        }
        .nav-tabs {
            border-bottom: none;
            margin-bottom: 20px;
        }
        .nav-tabs .nav-link {
            border: none;
            padding: 10px 20px;
            color: #476a52;
            background-color: transparent;
            font-weight: 500;
        }
        .nav-tabs .nav-link.active {
            color: white;
            background-color: #476a52;
            border-radius: 0;
        }
        .nav-tabs .nav-link:hover:not(.active) {
            border: none;
            color: #476a52;
            background-color: rgba(71, 106, 82, 0.1);
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
                    <h2 class="text-uppercase fw-bold" style="color: #476a52;">Báo cáo biến động</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="/webquanlytoanha/admin/dashboard.php" class="text-decoration-none" style="color: #476a52;">Trang chủ</a></li>
                            <li class="breadcrumb-item">Báo cáo</li>
                            <li class="breadcrumb-item active">Báo cáo biến động</li>
                        </ol>
                    </nav>
                </div>

                <!-- Tab Navigation -->
                <ul class="nav nav-tabs">
                    <li class="nav-item">
                        <a class="nav-link <?php echo !isset($_GET['tab']) || $_GET['tab'] == 'vehicles' ? 'active' : ''; ?>" 
                           href="#">
                            Báo cáo biến động phương tiện
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isset($_GET['tab']) && $_GET['tab'] == 'apartments' ? 'active' : ''; ?>" 
                           href="fluctuation_apartment_report.php">
                            Báo cáo biến động căn hộ
                        </a>
                    </li>
                </ul>

                <?php if (!isset($_GET['tab']) || $_GET['tab'] == 'vehicles'): ?>
                    <div class="row mb-4 mt-2">
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
                            <select class="form-select" id="yearSelect" name="year">
                                <?php 
                                $current_year = date('Y');
                                for($year = $current_year; $year >= $current_year - 5; $year--) { 
                                ?>
                                    <option value="<?php echo $year; ?>" 
                                            <?php echo ($selected_year == $year) ? 'selected' : ''; ?>>
                                        <?php echo $year; ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Biểu đồ biến động số lượng phương tiện -->
                        <div class="col-md-8">
                            <div class="chart-container">
                                <h3>Biến động số lượng phương tiện theo tháng</h3>
                                <canvas id="vehicleTrendChart"></canvas>
                            </div>
                            <div class="timestamp">
                                <?php 
                                date_default_timezone_set('Asia/Ho_Chi_Minh');
                                echo "Số liệu tính đến " . date('H:i d/m/Y'); 
                                ?>
                            </div>
                        </div>

                        <!-- Biểu đồ cơ cấu tổng số phương tiện -->
                        <div class="col-md-4">
                            <div class="chart-container">
                                <h3>Cơ cấu tổng số phương tiện</h3>
                                <canvas id="vehicleRatioChart"></canvas>
                            </div>
                            <div class="timestamp">
                                <?php 
                                echo "Số liệu tính đến " . date('H:i d/m/Y'); 
                                ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="alert alert-info">
                                Báo cáo biến động căn hộ đang được phát triển
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Dữ liệu cho biểu đồ biến động số lượng phương tiện
        const vehicleTrendChart = new Chart(document.getElementById('vehicleTrendChart'), {
            type: 'bar',
            data: {
                labels: ['Tháng 1', 'Tháng 2', 'Tháng 3', 'Tháng 4', 'Tháng 5', 'Tháng 6', 
                        'Tháng 7', 'Tháng 8', 'Tháng 9', 'Tháng 10', 'Tháng 11', 'Tháng 12'],
                datasets: [
                    {
                        label: 'Xe máy',
                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1,
                        data: [
                            <?php
                            $monthly_data = array_fill(0, 12, 0);
                            mysqli_data_seek($vehicle_trend_result, 0);
                            while($row = mysqli_fetch_assoc($vehicle_trend_result)) {
                                if($row['TypeVehicle'] == 'Xe máy') {
                                    $monthly_data[$row['Month']-1] = $row['VehicleCount'];
                                }
                            }
                            echo implode(',', $monthly_data);
                            ?>
                        ]
                    },
                    {
                        label: 'Ô tô',
                        backgroundColor: 'rgba(255, 99, 132, 0.5)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1,
                        data: [
                            <?php
                            $monthly_data = array_fill(0, 12, 0);
                            mysqli_data_seek($vehicle_trend_result, 0);
                            while($row = mysqli_fetch_assoc($vehicle_trend_result)) {
                                if($row['TypeVehicle'] == 'Ô tô') {
                                    $monthly_data[$row['Month']-1] = $row['VehicleCount'];
                                }
                            }
                            echo implode(',', $monthly_data);
                            ?>
                        ]
                    },
                    {
                        label: 'Xe đạp điện',
                        backgroundColor: 'rgba(200, 200, 200, 0.5)',
                        borderColor: 'rgba(200, 200, 200, 1)',
                        borderWidth: 1,
                        data: [
                            <?php
                            $monthly_data = array_fill(0, 12, 0);
                            mysqli_data_seek($vehicle_trend_result, 0);
                            while($row = mysqli_fetch_assoc($vehicle_trend_result)) {
                                if($row['TypeVehicle'] == 'Xe đạp điện') {
                                    $monthly_data[$row['Month']-1] = $row['VehicleCount'];
                                }
                            }
                            echo implode(',', $monthly_data);
                            ?>
                        ]
                    },
                    {
                        label: 'Xe đạp',
                        backgroundColor: 'rgba(75, 192, 192, 0.5)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1,
                        data: [
                            <?php
                            $monthly_data = array_fill(0, 12, 0);
                            mysqli_data_seek($vehicle_trend_result, 0);
                            while($row = mysqli_fetch_assoc($vehicle_trend_result)) {
                                if($row['TypeVehicle'] == 'Xe đạp') {
                                    $monthly_data[$row['Month']-1] = $row['VehicleCount'];
                                }
                            }
                            echo implode(',', $monthly_data);
                            ?>
                        ]
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                barPercentage: 0.8,
                categoryPercentage: 0.9
            }
        });

        // Dữ liệu cho biểu đồ cơ cấu tổng số phương tiện
        const vehicleRatioData = {
            labels: [<?php 
                $labels = [];
                $total_vehicles = 0;
                mysqli_data_seek($vehicle_ratio_result, 0);
                while($row = mysqli_fetch_assoc($vehicle_ratio_result)) {
                    $total_vehicles += $row['VehicleCount'];
                }
                mysqli_data_seek($vehicle_ratio_result, 0);
                while($row = mysqli_fetch_assoc($vehicle_ratio_result)) {
                    $labels[] = "'" . $row['TypeVehicle'] . "'";
                }
                echo implode(',', $labels);
            ?>],
            datasets: [{
                data: [<?php 
                    $data = [];
                    mysqli_data_seek($vehicle_ratio_result, 0);
                    while($row = mysqli_fetch_assoc($vehicle_ratio_result)) {
                        $data[] = round(($row['VehicleCount'] / $total_vehicles) * 100, 1);
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

        // Khởi tạo biểu đồ cơ cấu tổng số phương tiện
        new Chart(document.getElementById('vehicleRatioChart'), {
            type: 'pie',
            data: vehicleRatioData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
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

        // Xử lý sự kiện thay đổi filter
        document.getElementById('projectSelect').addEventListener('change', updateCharts);
        document.getElementById('yearSelect').addEventListener('change', updateCharts);

        function updateCharts() {
            const project = document.getElementById('projectSelect').value;
            const year = document.getElementById('yearSelect').value;
            const currentTab = '<?php echo isset($_GET['tab']) ? $_GET['tab'] : 'vehicles'; ?>';
            window.location.href = `?tab=${currentTab}&project=${project}&year=${year}`;
        }
    </script>
</body>
</html>