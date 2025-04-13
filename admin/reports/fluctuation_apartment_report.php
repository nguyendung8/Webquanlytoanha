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
$selected_year = isset($_GET['year']) ? mysqli_real_escape_string($conn, $_GET['year']) : date('Y');

// Query để lấy số liệu căn hộ theo tháng
$year = isset($_GET['year']) ? $_GET['year'] : '2025';
$selected_project = isset($_GET['project']) ? $_GET['project'] : '';

$query = "
    SELECT 
        m.month,
        COUNT(DISTINCT CASE WHEN a.ContractCode IS NOT NULL 
            AND MONTH(c.CretionDate) = m.month 
            AND YEAR(c.CretionDate) = '$year' 
        THEN a.ApartmentID END) as occupied,
        COUNT(DISTINCT CASE WHEN a.ContractCode IS NULL THEN a.ApartmentID END) as empty
    FROM 
        (SELECT 1 as month UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 
         UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 
         UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION SELECT 12) m
    LEFT JOIN apartment a ON 1=1
    LEFT JOIN buildings b ON a.BuildingId = b.ID
    LEFT JOIN contracts c ON a.ContractCode = c.ContractCode
    WHERE 
        " . ($selected_project ? "b.ProjectId = '$selected_project'" : "1=1") . "
    GROUP BY m.month
    ORDER BY m.month";

$result = mysqli_query($conn, $query);

// Debug
echo "<!-- Query: $query -->";
if (!$result) {
    echo "Error: " . mysqli_error($conn);
}

$months = [];
$occupied_data = [];
$empty_data = [];

while ($row = mysqli_fetch_assoc($result)) {
    $months[] = 'Tháng ' . $row['month'];
    $occupied_data[] = (int)$row['occupied'];
    $empty_data[] = (int)$row['empty'];
}

// Debug data
echo "<!-- Occupied: " . implode(',', $occupied_data) . " -->";
echo "<!-- Empty: " . implode(',', $empty_data) . " -->";

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo cáo biến động căn hộ</title>
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
            margin-bottom: 40px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            height: 400px;
        }
        .nav-tabs .nav-link.active {
            background-color: #476a52 !important;
            color: white !important;
            border: none;
        }
        .nav-tabs .nav-link {
            color: #476a52;
            border: none;
        }
        .nav-tabs {
            border-bottom: none;
            margin-bottom: 20px;
        }
        .timestamp {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            padding: 2px 5px;
            border-radius: 3px;
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
                    <h2 class="text-uppercase fw-bold" style="color: #476a52;">Báo cáo biến động căn hộ</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="/webquanlytoanha/admin/dashboard.php" class="text-decoration-none" style="color: #476a52;">Trang chủ</a></li>
                            <li class="breadcrumb-item">Báo cáo</li>
                            <li class="breadcrumb-item active">Báo cáo biến động căn hộ</li>
                        </ol>
                    </nav>
                </div>

                <!-- Tab Navigation -->
                <ul class="nav nav-tabs mb-3">
                    <li class="nav-item">
                        <a class="nav-link" href="fluctuation_vehicle_report.php">
                            Báo cáo biến động phương tiện
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="fluctuation_apartment_report.php">
                            Báo cáo biến động căn hộ
                        </a>
                    </li>
                </ul>

                <!-- Filters -->
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
                        <select class="form-select" id="yearSelect" name="year">
                            <?php 
                            $current_year = date('Y');
                            for($year = $current_year; $year >= $current_year - 5; $year--) { 
                            ?>
                                <option value="<?php echo $year; ?>" 
                                        <?php echo ($selected_year == $year) ? 'selected' : ''; ?>>
                                    Năm <?php echo $year; ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="chart-container" style="position: relative;">
                            <h3>Biến động số lượng căn hộ</h3>
                            <canvas id="apartmentChart"></canvas>
                            <div class="timestamp" style="position: absolute; bottom: -30px; left: 0; font-size: 13px; color: #6c757d; font-style: italic;">
                                <?php 
                                date_default_timezone_set('Asia/Ho_Chi_Minh');
                                echo "Số liệu tính đến " . date('H:i d/m/Y'); 
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
        new Chart(document.getElementById('apartmentChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($months); ?>,
                datasets: [
                    {
                        label: 'Căn hộ có người ở',
                        data: <?php echo json_encode($occupied_data); ?>,
                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Căn hộ trống',
                        data: <?php echo json_encode($empty_data); ?>,
                        backgroundColor: 'rgba(255, 99, 132, 0.5)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1
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
                }
            }
        });

        // Xử lý sự kiện thay đổi filter
        document.getElementById('projectSelect').addEventListener('change', updateChart);
        document.getElementById('yearSelect').addEventListener('change', updateChart);

        function updateChart() {
            const project = document.getElementById('projectSelect').value;
            const year = document.getElementById('yearSelect').value;
            window.location.href = `?project=${project}&year=${year}`;
        }
    </script>
</body>
</html>