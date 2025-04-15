<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];
$selected_project = isset($_GET['project_id']) ? $_GET['project_id'] : '';

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

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

// Lấy danh sách tòa nhà cho filter
$select_buildings = mysqli_query($conn, "SELECT ID, Name FROM Buildings WHERE Status = 'active'");

// Xử lý các tham số tìm kiếm và phân trang
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$building_filter = isset($_GET['building']) ? mysqli_real_escape_string($conn, $_GET['building']) : '';
$apartment_filter = isset($_GET['apartment']) ? mysqli_real_escape_string($conn, $_GET['apartment']) : '';

// Thiết lập phân trang
$records_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 7;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Xây dựng câu query với điều kiện tìm kiếm
$where_conditions = ["e.Status = 'active'"];
if (!empty($search)) {
    $where_conditions[] = "a.Code LIKE '%$search%'";
}
if (!empty($building_filter)) {
    $where_conditions[] = "b.ID = '$building_filter'";
}
if (!empty($apartment_filter)) {
    $where_conditions[] = "e.ApartmentID = '$apartment_filter'";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Query đếm tổng số bản ghi
$count_query = mysqli_query($conn, "
    SELECT COUNT(*) as total 
    FROM excesspayment e
    LEFT JOIN apartment a ON e.ApartmentID = a.ApartmentID 
    LEFT JOIN Buildings b ON a.BuildingId = b.ID
    $where_clause
");
$total_records = mysqli_fetch_assoc($count_query)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Query lấy danh sách tiền thừa
$query = "
    SELECT e.*, a.Code as ApartmentCode, b.Name as BuildingName,
           r.ReceiptID as ReceiptNumber, 
           COALESCE(u.UserName, res.NationalId) as CustomerName
    FROM excesspayment e
    LEFT JOIN apartment a ON e.ApartmentID = a.ApartmentID
    LEFT JOIN Buildings b ON a.BuildingId = b.ID
    LEFT JOIN receipt r ON e.ReceiptID = r.ReceiptID
    LEFT JOIN ResidentApartment ra ON a.ApartmentID = ra.ApartmentId AND ra.Relationship = 'Chủ hộ'
    LEFT JOIN resident res ON ra.ResidentId = res.ID
    LEFT JOIN users u ON res.ID = u.ResidentID
    WHERE " . ($selected_project ? "b.ProjectId = '$selected_project'" : "1=1") . "
    " . (!empty($where_conditions) ? " AND " . implode(" AND ", $where_conditions) : "") . "
    ORDER BY e.OccurrenceDate DESC
    LIMIT $offset, $records_per_page
";

$select_excess = mysqli_query($conn, $query);

// Lấy danh sách căn hộ cho filter
$select_apartments = mysqli_query($conn, "SELECT ApartmentID, Code FROM apartment WHERE Status = 'active'");

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý tiền thừa</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .stats-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .stats-card .icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            margin-right: 15px;
        }

        .stats-number {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
        }

        .stats-label {
            color: #666;
            margin: 0;
        }

        .stats-link {
            color: #476a52;
            text-decoration: none;
            font-size: 14px;
        }

        .search-container {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .apartment-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }

        .apartment-table th {
            background: #6b8b7b !important;
            color: white;
            font-weight: 500;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-occupied {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-pending {
            background: #fff3e0;
            color: #ef6c00;
        }

        .status-renovating {
            background: #e3f2fd;
            color: #1565c0;
        }

        .status-empty {
            background: #ffebee;
            color: #c62828;
        }

        .status-away {
            background: #f3e5f5;
            color: #6a1b9a;
        }

        .page-header {
            background-color: #f5f5f5;
            padding: 15px 20px;
            color: #4a5568;
            border-bottom: 1px solid #eaeaea;
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

        .add-btn {
            margin-bottom: 10px;
            width: fit-content;
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            background-color: #476a52 !important;
            color: white !important;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .bill-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-paid {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .status-overdue {
            background: #ffebee;
            color: #c62828;
        }
        .action-buttons button {
            padding: 4px 8px;
            margin: 0 2px;
        }
        .receipt-table th {
            background: #6b8b7b !important;
            color: white;
        }

        /* Custom styling for tabs */
        .nav-tabs-custom {
            margin-bottom: 20px;
            background: #fff;
            border: none;
        }

        .nav-tabs-custom > .nav-tabs {
            margin: 0;
            border: none;
            display: flex;
            gap: 2px;
        }

        .nav-tabs-custom > .nav-tabs > li {
            margin: 0;
        }

        .nav-tabs-custom > .nav-tabs > li > a {
            margin: 0;
            padding: 12px 25px;
            color: #333;
            background: #f5f5f5;
            border: none;
            border-radius: 0;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        /* Active tab styling */
        .nav-tabs-custom > .nav-tabs > li.active > a,
        .nav-tabs-custom > .nav-tabs > li.active > a:hover,
        .nav-tabs-custom > .nav-tabs > li.active > a:focus {
            background-color: #8AA989; /* Màu xanh lá nhạt như trong hình */
            color: #fff;
            border: none;
        }

        /* Hover effect for tabs */
        .nav-tabs-custom > .nav-tabs > li > a:hover {
            background-color: #9BB99A;
            color: #fff;
        }

        /* Tab content container */
        .tab-content {
            background: #fff;
            padding: 20px;
            border: 1px solid #ddd;
            border-top: none;
        }

        /* Container for the entire tabs section */
        .tabs-section {
            margin: 20px 0;
        }

        /* Make tabs full width on mobile */
        @media (max-width: 768px) {
            .nav-tabs-custom > .nav-tabs {
                display: flex;
                flex-direction: column;
            }
            
            .nav-tabs-custom > .nav-tabs > li {
                width: 100%;
            }
            
            .nav-tabs-custom > .nav-tabs > li > a {
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <?php include '../admin_navbar.php'; ?>
        <div class="flex-grow-1">
            <?php include '../admin_header.php'; ?>
            
            <div class="container-fluid p-4">
                <!-- Page Header -->
                <div class="page-header">
                    <h2 class="text-uppercase fw-bold" style="color: #476a52;">Quản lý tiền thừa</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="/admin/dashboard.php">Trang chủ</a></li>
                            <li class="breadcrumb-item active">Quản lý tiền thừa</li>
                        </ol>
                    </nav>
                </div>

                <!-- Search Container -->
                <div class="search-container">
                    <form class="row g-3">
                        <div class="col-md-3">
                            <select class="form-select" name="project_id" onchange="this.form.submit()">
                                <option value="">Chọn dự án</option>
                                <?php while($project = mysqli_fetch_assoc($projects_result)) { ?>
                                    <option value="<?php echo $project['ProjectID']; ?>" 
                                            <?php echo ($selected_project == $project['ProjectID']) ? 'selected' : ''; ?>>
                                        <?php echo $project['Name']; ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="building">
                                <option value="">Chọn tòa nhà</option>
                                <?php while($building = mysqli_fetch_assoc($select_buildings)) { ?>
                                    <option value="<?php echo $building['ID']; ?>" <?php echo ($building_filter == $building['ID']) ? 'selected' : ''; ?>>
                                        <?php echo $building['Name']; ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <input type="text" class="form-control" name="search" placeholder="Tìm kiếm..." value="<?php echo $search; ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-search"></i> Tìm kiếm
                            </button>
                        </div>
                    </form>
                    <div class="mt-3 d-flex justify-content-end gap-2">
                        <?php if(empty($selected_project)): ?>
                            <button type="button" class="btn btn-danger" onclick="alert('Vui lòng chọn dự án trước khi thực hiện thao tác này!');">
                                <i class="fas fa-file-pdf"></i> PDF
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn btn-danger" onclick="exportToPDF()">
                                <i class="fas fa-file-pdf"></i> PDF
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Table -->
                <div class="table-responsive mt-4">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>STT</th>
                                <th>Căn hộ</th>
                                <th>Tòa nhà</th>
                                <th>Khách hàng</th>
                                <!-- <th>Ngày phát sinh</th>
                                <th>Số phiếu thu</th> -->
                                <th>Tiền thừa hiện tại</th>
                                <!-- <th>Thao tác</th> -->
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if(mysqli_num_rows($select_excess) > 0){
                                $i = $offset + 1;
                                while($excess = mysqli_fetch_assoc($select_excess)){
                            ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo $excess['ApartmentCode']; ?></td>
                                <td><?php echo $excess['BuildingName']; ?></td>
                                <td><?php echo $excess['CustomerName'] ?? 'Chưa có chủ hộ'; ?></td>
                                <!-- <td><?php echo date('d/m/Y', strtotime($excess['OccurrenceDate'])); ?></td> -->
                                <!-- <td><?php echo $excess['ReceiptNumber']; ?></td> -->
                                <td class="text-end"><?php echo number_format($excess['Total'], 0, ',', '.'); ?></td>
                                <!-- <td>
                                    <button type="button" class="btn btn-sm btn-info" title="Chi tiết"
                                            onclick="window.location.href='excess_detail.php?id=<?php echo $excess['ExcessPaymentID']; ?>'">
                                        <i class="fas fa-info-circle"></i>
                                    </button>
                                </td> -->
                            </tr>
                            <?php
                                }
                            } else {
                                echo '<tr><td colspan="7" class="text-center">Không có dữ liệu</td></tr>';
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
                                    echo !empty($search) ? "&search=$search" : '';
                                    echo !empty($building_filter) ? "&building=$building_filter" : '';
                                    echo !empty($apartment_filter) ? "&apartment=$apartment_filter" : '';
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
        function exportToPDF() {
            window.location.href = 'export_excess_pdf.php';
        }
    </script>
</body>
</html>