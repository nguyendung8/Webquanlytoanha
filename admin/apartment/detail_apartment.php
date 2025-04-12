<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Xử lý AJAX load tòa nhà theo dự án
if(isset($_GET['get_buildings'])) {
    $project_id = $_GET['project_id'];
    $buildings = mysqli_query($conn, "SELECT ID, Name FROM Buildings WHERE ProjectId = '$project_id' AND Status = 'active'");
    $building_list = array();
    while($building = mysqli_fetch_assoc($buildings)) {
        $building_list[] = $building;
    }
    echo json_encode($building_list);
    exit();
}

// Xử lý AJAX load tầng theo tòa nhà
if(isset($_GET['get_floors'])) {
    $building_id = $_GET['building_id'];
    $floors = mysqli_query($conn, "SELECT ID, Name FROM Floors WHERE BuildingId = '$building_id'");
    $floor_list = array();
    while($floor = mysqli_fetch_assoc($floors)) {
        $floor_list[] = $floor;
    }
    echo json_encode($floor_list);
    exit();
}

// Kiểm tra ID căn hộ
if(!isset($_GET['id'])) {
    header('location: apartment_management.php');
    exit();
}

$apartment_id = $_GET['id'];

// Lấy thông tin căn hộ
$apartment_query = mysqli_query($conn, "
    SELECT a.*, b.ProjectId 
    FROM apartment a 
    LEFT JOIN Buildings b ON a.BuildingId = b.ID 
    WHERE a.ApartmentID = '$apartment_id'
");

if(mysqli_num_rows($apartment_query) == 0) {
    header('location: apartment_management.php');
    exit();
}

$apartment = mysqli_fetch_assoc($apartment_query);

// Lấy danh sách dự án
$select_projects = mysqli_query($conn, "SELECT ProjectID, Name FROM Projects WHERE Status = 'active' ORDER BY Name");

// Lấy danh sách tòa nhà của dự án hiện tại
$current_buildings = mysqli_query($conn, "SELECT ID, Name FROM Buildings WHERE ProjectId = '{$apartment['ProjectId']}' AND Status = 'active'");

// Lấy danh sách tầng của tòa nhà hiện tại
$current_floors = mysqli_query($conn, "SELECT ID, Name FROM Floors WHERE BuildingId = '{$apartment['BuildingId']}'");

// 1. Lấy thông tin hợp đồng
$contracts_query = mysqli_query($conn, "
    SELECT 
        c.ContractCode,
        c.CretionDate,
        c.Status,
        DATE_FORMAT(c.CretionDate, '%d/%m/%Y') as FormattedCreationDate
    FROM contracts c
    INNER JOIN ContractServices cs ON c.ContractCode = cs.ContractCode
    INNER JOIN apartment a ON a.ContractCode = c.ContractCode
    WHERE a.ApartmentID = '$apartment_id'
    ORDER BY c.CretionDate DESC
");

// 2. Lấy thông tin dịch vụ
$services_query = mysqli_query($conn, "
    SELECT 
        s.ServiceCode,
        s.Name as ServiceName,
        s.Status,
        pl.Price,
        DATE_FORMAT(s.ApplyForm, '%d/%m/%Y') as StartDate
    FROM services s
    INNER JOIN ServicePrice sp ON s.ServiceCode = sp.ServiceId
    INNER JOIN pricelist pl ON sp.PriceId = pl.ID
    WHERE s.Status = 'active'
");

// 3. Lấy thông tin cư dân
$residents_query = mysqli_query($conn, "
    SELECT 
        r.ID,
        r.NationalId,
        u.UserName,
        u.PhoneNumber,
        ra.Relationship
    FROM resident r
    INNER JOIN ResidentApartment ra ON r.ID = ra.ResidentId
    INNER JOIN users u ON r.ID = u.ResidentID
    WHERE ra.ApartmentId = '$apartment_id'
");

// 4. Lấy thông tin phương tiện
$vehicles_query = mysqli_query($conn, "
    SELECT 
        v.VehicleCode,
        v.VehicleName,
        v.NumberPlate,
        v.TypeVehicle,
        v.Status
    FROM vehicles v
    WHERE v.ApartmentID = '$apartment_id'
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết căn hộ</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <style>
        .manage-container {
            padding: 20px;
        }
        .form-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            font-weight: 500;
            margin-bottom: 8px;
        }
        .required {
            color: red;
            margin-left: 4px;
        }
        .btn-container {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        .btn-submit {
            background: #899F87 !important;
            border: 1px solid #899F87 !important;
            color: #fff !important;
        }
        .btn-cancel {
            background: #C23636 !important;
            border: 1px solid #C23636 !important;
            color: #fff !important;
        }
        #editor {
            height: 200px;
        }
        .page-header {
            background-color: #f5f5f5;
            padding: 15px 20px;
            color: #4a5568;
            border-bottom: 1px solid #eaeaea;
            margin-bottom: 30px;
        }
        .page-header h2 {
            font-size: 24px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 10px;
        }
        .breadcrumb {
            display: flex;
            gap: 8px;
            align-items: center;
            font-size: 14px;
        }
        .breadcrumb a {
            color: #3182ce;
            text-decoration: none;
        }
        .breadcrumb span {
            color: #718096;
        }
        .card {
            border: none;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #eee;
            padding: 15px 20px;
        }
        .card-header h5 {
            color: #476a52;
            font-weight: 600;
            margin: 0;
        }
        .table {
            margin-bottom: 0;
        }
        .table th {
            background-color: #f8f9fa;
            color: #476a52;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
        }
        .table td {
            vertical-align: middle;
        }
        .btn-sm {
            padding: 0.25rem 0.5rem;
        }
        .btn-primary {
            background-color: #476a52;
            border-color: #476a52;
        }
        .btn-primary:hover {
            background-color: #385442;
            border-color: #385442;
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <?php include '../admin_navbar.php'; ?>
        <div style="width: 100%;">
            <?php include '../admin_header.php'; ?>
            <div class="manage-container">
                <!-- Page Header -->
                <div class="page-header">
                    <h2>CHI TIẾT CĂN HỘ</h2>
                    <div class="breadcrumb">
                        <a style="text-decoration: none; color: #476a52;" href="dashboard.php">Trang chủ</a>
                        <span>›</span>
                        <a style="text-decoration: none; color: #476a52;" href="apartment_management.php">Căn hộ</a>
                        <span>›</span>
                        <span>Chi tiết</span>
                    </div>
                </div>

                <div class="form-container">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Căn hộ</label>
                                <input type="text" class="form-control" value="<?php echo $apartment['Name']; ?>" disabled>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Mã căn hộ</label>
                                <input type="text" class="form-control" value="<?php echo $apartment['Code']; ?>" disabled>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Dự án</label>
                                <select class="form-select" disabled>
                                    <?php while($project = mysqli_fetch_assoc($select_projects)) { ?>
                                        <option value="<?php echo $project['ProjectID']; ?>" 
                                            <?php echo ($project['ProjectID'] == $apartment['ProjectId']) ? 'selected' : ''; ?>>
                                            <?php echo $project['Name']; ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Tòa nhà</label>
                                <select class="form-select" disabled>
                                    <?php while($building = mysqli_fetch_assoc($current_buildings)) { ?>
                                        <option value="<?php echo $building['ID']; ?>"
                                            <?php echo ($building['ID'] == $apartment['BuildingId']) ? 'selected' : ''; ?>>
                                            <?php echo $building['Name']; ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Tầng</label>
                                <select class="form-select" disabled>
                                    <?php while($floor = mysqli_fetch_assoc($current_floors)) { ?>
                                        <option value="<?php echo $floor['ID']; ?>"
                                            <?php echo ($floor['ID'] == $apartment['FloorId']) ? 'selected' : ''; ?>>
                                            <?php echo $floor['Name']; ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Diện tích (m²)</label>
                                <input type="number" class="form-control" value="<?php echo $apartment['Area']; ?>" disabled>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Số phòng ngủ</label>
                                <input type="number" class="form-control" value="<?php echo $apartment['NumberOffBedroom']; ?>" disabled>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Mô tả</label>
                        <div class="form-control" style="min-height: 100px; overflow-y: auto;" disabled>
                            <?php echo $apartment['Description']; ?>
                        </div>
                    </div>

                    <div class="btn-container">
                        <a href="apartment_management.php" class="btn btn-cancel">Quay lại</a>
                    </div>
                </div>

                <div class="apartment-info mt-4">
                    <!-- Hợp đồng -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Hợp đồng</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Mã hợp đồng</th>
                                            <th>Ngày ký hợp đồng</th>
                                            <th>Trạng thái</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($contract = mysqli_fetch_assoc($contracts_query)) { ?>
                                        <tr>
                                            <td><?php echo $contract['ContractCode']; ?></td>
                                            <td><?php echo $contract['FormattedCreationDate']; ?></td>
                                            <td><?php echo $contract['Status']; ?></td>
                                        </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Dịch vụ -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Dịch vụ</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Dịch vụ</th>
                                            <th>Phí</th>
                                            <th>Ngày bắt đầu</th>
                                            <th>Trạng thái</th>
                                            
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($service = mysqli_fetch_assoc($services_query)) { ?>
                                        <tr>
                                            <td><?php echo $service['ServiceName']; ?></td>
                                            <td><?php echo number_format($service['Price']); ?> VNĐ</td>
                                            <td><?php echo $service['StartDate']; ?></td>
                                            <td><?php echo $service['Status']; ?></td>
                                        </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Cư dân -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Cư dân</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Họ và tên</th>
                                            <th>CCCD</th>
                                            <th>Số điện thoại</th>
                                            <th>Quan hệ</th>
                                            
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($resident = mysqli_fetch_assoc($residents_query)) { ?>
                                        <tr>
                                            <td><?php echo $resident['UserName']; ?></td>
                                            <td><?php echo $resident['NationalId']; ?></td>
                                            <td><?php echo $resident['PhoneNumber']; ?></td>
                                            <td><?php echo $resident['Relationship']; ?></td>
                                        </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Phương tiện -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Phương tiện</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Tên phương tiện</th>
                                            <th>Loại xe</th>
                                            <th>Biển số</th>
                                            <th>Trạng thái</th>
                                            
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($vehicle = mysqli_fetch_assoc($vehicles_query)) { ?>
                                        <tr>
                                            <td><?php echo $vehicle['VehicleName']; ?></td>
                                            <td><?php echo $vehicle['TypeVehicle']; ?></td>
                                            <td><?php echo $vehicle['NumberPlate']; ?></td>
                                            <td><?php echo $vehicle['Status']; ?></td>
                                        </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <script>
        // Khởi tạo Quill editor
        var quill = new Quill('#editor', {
            theme: 'snow'
        });

        // Cập nhật giá trị mô tả khi submit form
        document.querySelector('form').onsubmit = function() {
            document.getElementById('description').value = quill.root.innerHTML;
        };

        // Xử lý load tòa nhà theo dự án
        document.getElementById('project_id').addEventListener('change', function() {
            const projectId = this.value;
            fetch(`update_apartment.php?get_buildings=1&project_id=${projectId}`)
                .then(response => response.json())
                .then(data => {
                    const buildingSelect = document.getElementById('building_id');
                    buildingSelect.innerHTML = '<option value="">Chọn tòa nhà</option>';
                    data.forEach(building => {
                        buildingSelect.innerHTML += `<option value="${building.ID}">${building.Name}</option>`;
                    });
                });
        });

        // Xử lý load tầng theo tòa nhà
        document.getElementById('building_id').addEventListener('change', function() {
            const buildingId = this.value;
            fetch(`update_apartment.php?get_floors=1&building_id=${buildingId}`)
                .then(response => response.json())
                .then(data => {
                    const floorSelect = document.getElementById('floor_id');
                    floorSelect.innerHTML = '<option value="">Chọn tầng</option>';
                    data.forEach(floor => {
                        floorSelect.innerHTML += `<option value="${floor.ID}">${floor.Name}</option>`;
                    });
                });
        });
    </script>
</body>
</html>