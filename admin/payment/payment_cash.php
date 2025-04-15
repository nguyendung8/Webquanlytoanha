<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];
$selected_project = isset($_GET['project_id']) ? mysqli_real_escape_string($conn, $_GET['project_id']) : '';

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Xử lý Ajax request cho tòa nhà và căn hộ
if(isset($_POST['action'])) {
    if($_POST['action'] == 'get_buildings') {
        $project_id = mysqli_real_escape_string($conn, $_POST['project_id']);
        $query = "SELECT ID, Name FROM Buildings WHERE ProjectId = '$project_id' AND Status = 'active'";
        $result = mysqli_query($conn, $query);
        
        $html = '<option value="">Chọn tòa nhà</option>';
        while($row = mysqli_fetch_assoc($result)) {
            $html .= '<option value="'.$row['ID'].'">'.$row['Name'].'</option>';
        }
        echo $html;
        exit;
    }
    
    if($_POST['action'] == 'get_apartments') {
        $building_id = mysqli_real_escape_string($conn, $_POST['building_id']);
        $query = "SELECT ApartmentID, Code FROM apartment WHERE BuildingId = '$building_id' AND Status = 'active'";
        $result = mysqli_query($conn, $query);
        
        $html = '<option value="">Chọn căn hộ</option>';
        while($row = mysqli_fetch_assoc($result)) {
            $html .= '<option value="'.$row['ApartmentID'].'">'.$row['Code'].'</option>';
        }
        echo $html;
        exit;
    }
}

// Lấy danh sách tòa nhà theo dự án
$buildings_query = mysqli_query($conn, "SELECT ID, Name FROM Buildings WHERE Status = 'active'" . 
    ($selected_project ? " AND ProjectId = '$selected_project'" : ""));

// Xử lý các tham số tìm kiếm
$document_number = isset($_GET['document_number']) ? mysqli_real_escape_string($conn, $_GET['document_number']) : '';
$apartment = isset($_GET['apartment']) ? mysqli_real_escape_string($conn, $_GET['apartment']) : '';
$building = isset($_GET['building']) ? mysqli_real_escape_string($conn, $_GET['building']) : '';
$start_date = isset($_GET['start_date']) ? mysqli_real_escape_string($conn, $_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? mysqli_real_escape_string($conn, $_GET['end_date']) : '';

// Thiết lập phân trang
$records_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Xây dựng câu query với điều kiện tìm kiếm
$where_conditions = ["(r.PaymentMethod = 'Tiền mặt' OR p.PaymentMethod = 'Tiền mặt')"];

if (!empty($document_number)) {
    $where_conditions[] = "(r.ReceiptID LIKE '%$document_number%' OR p.PaymentID LIKE '%$document_number%')";
}
if (!empty($apartment)) {
    $where_conditions[] = "(r.ApartmentID = '$apartment' OR p.ApartmentID = '$apartment')";
}
if (!empty($building)) {
    $where_conditions[] = "a.BuildingId = '$building'";
}
if (!empty($start_date)) {
    $where_conditions[] = "(r.AccountingDate >= '$start_date' OR p.AccountingDate >= '$start_date')";
}
if (!empty($end_date)) {
    $where_conditions[] = "(r.AccountingDate <= '$end_date' OR p.AccountingDate <= '$end_date')";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Query để lấy dữ liệu kết hợp từ cả phiếu thu và phiếu chi
$query = "
    SELECT 
        a.Code as ApartmentCode,
        COALESCE(r.AccountingDate, p.AccountingDate) as TransactionDate,
        COALESCE(r.ReceiptID, CONCAT('PC_', p.PaymentID)) as DocumentNumber,
        CASE 
            WHEN r.ReceiptID IS NOT NULL THEN r.Total
            ELSE 0
        END as ReceiptAmount,
        CASE 
            WHEN p.PaymentID IS NOT NULL THEN p.Total
            ELSE 0
        END as PaymentAmount,
        u.UserName as StaffName,
        CASE 
            WHEN r.ReceiptID IS NOT NULL THEN 'Người thu'
            ELSE 'Người chi'
        END as TransactionType
    FROM (
        SELECT 
            ApartmentID, 
            AccountingDate, 
            ReceiptID COLLATE utf8mb4_unicode_ci as ReceiptID, 
            Total, 
            StaffID, 
            'Thu' COLLATE utf8mb4_unicode_ci as Type 
        FROM receipt 
        WHERE PaymentMethod = 'Tiền mặt'
        UNION ALL
        SELECT 
            ApartmentID, 
            AccountingDate, 
            CAST(PaymentID AS CHAR) COLLATE utf8mb4_unicode_ci as ReceiptID, 
            Total, 
            StaffID, 
            'Chi' COLLATE utf8mb4_unicode_ci as Type 
        FROM payments 
        WHERE PaymentMethod = 'Tiền mặt'
    ) transactions
    LEFT JOIN apartment a ON transactions.ApartmentID = a.ApartmentID
    LEFT JOIN Buildings b ON a.BuildingId = b.ID
    LEFT JOIN receipt r ON transactions.ReceiptID = r.ReceiptID
    LEFT JOIN payments p ON transactions.ReceiptID = CAST(p.PaymentID AS CHAR) COLLATE utf8mb4_unicode_ci
    LEFT JOIN users u ON transactions.StaffID = u.UserId
    $where_clause
    ORDER BY TransactionDate DESC, DocumentNumber DESC
    LIMIT $offset, $records_per_page
";

$transactions = mysqli_query($conn, $query);

// Query đếm tổng số bản ghi
$count_query = "
    SELECT COUNT(*) as total
    FROM (
        SELECT 
            ApartmentID, 
            AccountingDate, 
            ReceiptID COLLATE utf8mb4_unicode_ci as ReceiptID, 
            Total, 
            Payer, 
            'Thu' as Type 
        FROM receipt r 
        WHERE PaymentMethod = 'Tiền mặt'
        UNION ALL
        SELECT 
            ApartmentID, 
            AccountingDate, 
            CAST(PaymentID AS CHAR) COLLATE utf8mb4_unicode_ci as ReceiptID, 
            Total, 
            NULL as Payer, 
            'Chi' as Type 
        FROM payments p 
        WHERE PaymentMethod = 'Tiền mặt'
    ) transactions
    LEFT JOIN apartment a ON transactions.ApartmentID = a.ApartmentID
    LEFT JOIN Buildings b ON a.BuildingId = b.ID
    LEFT JOIN receipt r ON transactions.ReceiptID = r.ReceiptID
    LEFT JOIN payments p ON transactions.ReceiptID = CAST(p.PaymentID AS CHAR) COLLATE utf8mb4_unicode_ci
    LEFT JOIN staffs s ON p.StaffID = s.ID
    $where_clause
";
$total_records = mysqli_fetch_assoc(mysqli_query($conn, $count_query))['total'];
$total_pages = ceil($total_records / $records_per_page);

// Lấy danh sách căn hộ theo dự án và tòa nhà
$apartments_query = mysqli_query($conn, "
    SELECT a.ApartmentID, a.Code 
    FROM apartment a 
    JOIN Buildings b ON a.BuildingId = b.ID 
    WHERE a.Status = 'active'" . 
    ($selected_project ? " AND b.ProjectId = '$selected_project'" : "") .
    ($building ? " AND b.ID = '$building'" : ""));

// Lấy danh sách dự án của nhân viên
$projects_query = "SELECT DISTINCT p.ProjectID, p.Name 
                  FROM Projects p
                  JOIN StaffProjects sp ON p.ProjectID = sp.ProjectId
                  JOIN staffs s ON sp.StaffId = s.ID
                  JOIN users u ON s.DepartmentId = u.DepartmentId
                  WHERE u.UserId = '$admin_id' 
                  AND p.Status = 'active'
                  ORDER BY p.Name";
$projects_result = mysqli_query($conn, $projects_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo cáo thu phí bằng tiền mặt</title>
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
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .table th {
            background-color: #f8f9fa;
            color: #476a52;
        }
        .form-control, .form-select {
            border-radius: 0;
        }
        .btn-success {
            background-color: #476a52;
            border-color: #476a52;
        }
        .btn-success:hover {
            background-color: #3c5a46;
            border-color: #3c5a46;
        }
    </style>
</head>
<body></body>
    <div class="d-flex">
        <?php include '../admin_navbar.php'; ?>
        <div class="flex-grow-1">
            <?php include '../admin_header.php'; ?>
            
            <div class="container-fluid p-4">
                <!-- Page Header -->
                <div class="page-header">
                    <h2 class="text-uppercase fw-bold" style="color: #476a52;">Báo cáo thu phí bằng tiền mặt</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a style="text-decoration: none; color: #476a52;" href="/webquanlytoanha/admin/dashboard.php">Trang chủ</a></li>
                            <li class="breadcrumb-item"><a style="text-decoration: none; color: #476a52;" href="/webquanlytoanha/admin/payment/payment_reports.php">Báo cáo</a></li>
                            <li class="breadcrumb-item active">Báo cáo thu phí bằng tiền mặt</li>
                        </ol>
                    </nav>
                </div>

                <!-- Search Form -->
                <div class="search-container">
                    <form method="GET" class="row g-3" id="searchForm">
                        <div class="col-md-2">
                            <select class="form-select" name="project_id" id="projectSelect">
                                <option value="">Chọn dự án</option>
                                <?php while($project = mysqli_fetch_assoc($projects_result)) { ?>
                                    <option value="<?php echo $project['ProjectID']; ?>" 
                                            <?php echo ($selected_project == $project['ProjectID']) ? 'selected' : ''; ?>>
                                        <?php echo $project['Name']; ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="building" id="buildingSelect">
                                <option value="">Chọn tòa nhà</option>
                                <?php while($building_row = mysqli_fetch_assoc($buildings_query)) { ?>
                                    <option value="<?php echo $building_row['ID']; ?>"
                                            <?php echo ($building == $building_row['ID']) ? 'selected' : ''; ?>>
                                        <?php echo $building_row['Name']; ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="apartment" id="apartmentSelect">
                                <option value="">Chọn căn hộ</option>
                                <?php while($apt = mysqli_fetch_assoc($apartments_query)) { ?>
                                    <option value="<?php echo $apt['ApartmentID']; ?>" 
                                            <?php echo ($apartment == $apt['ApartmentID']) ? 'selected' : ''; ?>>
                                        <?php echo $apt['Code']; ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="text" class="form-control" name="document_number" 
                                   placeholder="Số hiệu chứng từ" value="<?php echo $document_number; ?>">
                        </div>
                        <div class="col-md-1">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Transactions Table -->
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>STT</th>
                                <th>Căn hộ</th>
                                <th>Ngày, tháng chứng từ</th>
                                <th>Số hiệu chứng từ</th>
                                <th>Thu</th>
                                <th>Chi</th>
                                <th>Người thực hiện</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if(mysqli_num_rows($transactions) > 0){
                                $i = $offset + 1;
                                while($row = mysqli_fetch_assoc($transactions)) {
                            ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo $row['ApartmentCode']; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($row['TransactionDate'])); ?></td>
                                <td><?php echo $row['DocumentNumber']; ?></td>
                                <td class="text-end"><?php echo number_format($row['ReceiptAmount'], 0, ',', '.'); ?></td>
                                <td class="text-end"><?php echo number_format($row['PaymentAmount'], 0, ',', '.'); ?></td>
                                <td>
                                    <?php echo $row['StaffName']; ?> 
                                    <span class="text-muted">(<?php echo $row['TransactionType']; ?>)</span>
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

                <!-- Pagination -->
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <div>Tổng số: <?php echo $total_records; ?> bản ghi</div>
                    <nav>
                        <ul class="pagination mb-0">
                            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php 
                                    echo !empty($document_number) ? "&document_number=$document_number" : '';
                                    echo !empty($apartment) ? "&apartment=$apartment" : '';
                                    echo !empty($building) ? "&building=$building" : '';
                                    echo !empty($start_date) ? "&start_date=$start_date" : '';
                                    echo !empty($end_date) ? "&end_date=$end_date" : '';
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Xử lý checkbox select all
        document.getElementById('select-all').addEventListener('change', function() {
            document.querySelectorAll('.row-checkbox').forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Hàm xuất báo cáo
        function exportReport() {
            // Thêm logic xuất báo cáo ở đây
            alert('Chức năng xuất báo cáo đang được phát triển');
        }

        $(document).ready(function() {
            // Khi chọn dự án
            $('#projectSelect').change(function() {
                var projectId = $(this).val();
                
                // Reset các select box khác
                $('#buildingSelect').html('<option value="">Chọn tòa nhà</option>');
                $('#apartmentSelect').html('<option value="">Chọn căn hộ</option>');
                
                if(projectId) {
                    // Load danh sách tòa nhà theo dự án
                    $.ajax({
                        url: window.location.pathname,
                        type: 'POST',
                        data: {
                            action: 'get_buildings',
                            project_id: projectId
                        },
                        success: function(data) {
                            $('#buildingSelect').html(data);
                        }
                    });
                }
                this.form.submit();
            });

            // Khi chọn tòa nhà
            $('#buildingSelect').change(function() {
                var buildingId = $(this).val();
                
                // Reset căn hộ
                $('#apartmentSelect').html('<option value="">Chọn căn hộ</option>');
                
                if(buildingId) {
                    // Load danh sách căn hộ theo tòa nhà
                    $.ajax({
                        url: window.location.pathname,
                        type: 'POST',
                        data: {
                            action: 'get_apartments',
                            building_id: buildingId
                        },
                        success: function(data) {
                            $('#apartmentSelect').html(data);
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>