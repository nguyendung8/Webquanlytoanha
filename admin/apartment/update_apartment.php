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

// Xử lý cập nhật căn hộ
if(isset($_POST['submit'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $building_id = mysqli_real_escape_string($conn, $_POST['building_id']);
    $floor_id = mysqli_real_escape_string($conn, $_POST['floor_id']);
    $area = mysqli_real_escape_string($conn, $_POST['area']);
    $bedrooms = mysqli_real_escape_string($conn, $_POST['bedrooms']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);

    mysqli_begin_transaction($conn);
    try {
        // Cập nhật thông tin căn hộ
        $update_apartment = mysqli_query($conn, "
            UPDATE apartment SET 
            Name = '$name',
            Area = '$area',
            NumberOffBedroom = '$bedrooms',
            Description = '$description',
            BuildingId = '$building_id',
            FloorId = '$floor_id'
            WHERE ApartmentID = '$apartment_id'
        ") or throw new Exception('Không thể cập nhật căn hộ: ' . mysqli_error($conn));

        mysqli_commit($conn);
        $message[] = 'Cập nhật căn hộ thành công!';
        header('location: apartment_management.php');
        exit();
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $message[] = 'Lỗi: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cập nhật căn hộ</title>
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
                    <h2>CẬP NHẬT CĂN HỘ</h2>
                    <div class="breadcrumb">
                        <a href="dashboard.php">Trang chủ</a>
                        <span>›</span>
                        <a href="apartment_management.php">Căn hộ</a>
                        <span>›</span>
                        <span>Cập nhật</span>
                    </div>
                </div>

                <div class="form-container">
                    <form action="" method="post">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Căn hộ<span class="required">*</span></label>
                                    <input type="text" name="name" class="form-control" required 
                                           value="<?php echo $apartment['Name']; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Mã căn hộ</label>
                                    <input type="text" class="form-control" value="<?php echo $apartment['Code']; ?>" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-label">Dự án<span class="required">*</span></label>
                                    <select name="project_id" id="project_id" class="form-select" required>
                                        <option value="">Chọn dự án</option>
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
                                    <label class="form-label">Tòa nhà<span class="required">*</span></label>
                                    <select name="building_id" id="building_id" class="form-select" required>
                                        <option value="">Chọn tòa nhà</option>
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
                                    <label class="form-label">Tầng<span class="required">*</span></label>
                                    <select name="floor_id" id="floor_id" class="form-select" required>
                                        <option value="">Chọn tầng</option>
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
                                    <label class="form-label">Diện tích (m²)<span class="required">*</span></label>
                                    <input type="number" name="area" class="form-control" required 
                                           value="<?php echo $apartment['Area']; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Số phòng ngủ<span class="required">*</span></label>
                                    <input type="number" name="bedrooms" class="form-control" required 
                                           value="<?php echo $apartment['NumberOffBedroom']; ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Mô tả</label>
                            <div id="editor"><?php echo $apartment['Description']; ?></div>
                            <input type="hidden" name="description" id="description">
                        </div>

                        <div class="btn-container">
                            <button type="submit" name="submit" class="btn btn-submit">Cập nhật</button>
                            <a href="apartment_management.php" class="btn btn-cancel">Hủy</a>
                        </div>
                    </form>
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