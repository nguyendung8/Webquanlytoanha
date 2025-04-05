<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Kiểm tra ID cư dân
if (!isset($_GET['id'])) {
    $_SESSION['message'] = 'Không tìm thấy cư dân!';
    header('location: resident_management.php');
    exit();
}

$resident_id = mysqli_real_escape_string($conn, $_GET['id']);

// Lấy thông tin cư dân
$resident_query = mysqli_query($conn, "
    SELECT r.*, u.UserName, u.Email, u.PhoneNumber 
    FROM resident r 
    LEFT JOIN users u ON r.ID = u.ResidentID 
    WHERE r.ID = '$resident_id'
");

if (mysqli_num_rows($resident_query) == 0) {
    $_SESSION['message'] = 'Không tìm thấy cư dân!';
    header('location: resident_management.php');
    exit();
}

$resident = mysqli_fetch_assoc($resident_query);

// Lấy danh sách căn hộ của cư dân
$apartments_query = mysqli_query($conn, "
    SELECT ra.*, 
           a.Name as ApartmentName, a.ApartmentID,
           f.Name as FloorName, f.ID as FloorId,
           b.Name as BuildingName, b.ID as BuildingId
    FROM ResidentApartment ra
    JOIN apartment a ON ra.ApartmentId = a.ApartmentID
    JOIN Floors f ON a.FloorId = f.ID
    JOIN Buildings b ON f.BuildingId = b.ID
    WHERE ra.ResidentId = '$resident_id'
") or die('Query failed: ' . mysqli_error($conn));

// Xử lý AJAX load tòa nhà
if(isset($_GET['get_buildings'])) {
    $buildings = mysqli_query($conn, "SELECT ID, Name FROM Buildings WHERE Status = 'active'");
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

// Xử lý AJAX load căn hộ theo tầng
if(isset($_GET['get_apartments'])) {
    $floor_id = $_GET['floor_id'];
    $apartments = mysqli_query($conn, "SELECT ApartmentID, Name FROM apartment WHERE FloorId = '$floor_id'");
    $apartment_list = array();
    while($apartment = mysqli_fetch_assoc($apartments)) {
        $apartment_list[] = $apartment;
    }
    echo json_encode($apartment_list);
    exit();
}

// Xử lý cập nhật thông tin
if(isset($_POST['submit'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $dob = mysqli_real_escape_string($conn, $_POST['dob']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $gender = mysqli_real_escape_string($conn, $_POST['gender']);
    $national_id = mysqli_real_escape_string($conn, $_POST['national_id']);
    
    // Lấy mảng thông tin căn hộ từ form
    $apartment_ids = isset($_POST['apartment_id']) ? $_POST['apartment_id'] : array();
    $relationships = isset($_POST['relationship']) ? $_POST['relationship'] : array();

    mysqli_begin_transaction($conn);
    try {
        // 1. Cập nhật bảng resident
        mysqli_query($conn, "
            UPDATE resident 
            SET NationalId = '$national_id', 
                Dob = '$dob', 
                Gender = '$gender'
            WHERE ID = '$resident_id'
        ") or throw new Exception('Không thể cập nhật resident: ' . mysqli_error($conn));

        // 2. Cập nhật bảng users
        mysqli_query($conn, "
            UPDATE users 
            SET UserName = '$name', 
                Email = '$email', 
                PhoneNumber = '$phone'
            WHERE ResidentID = '$resident_id'
        ") or throw new Exception('Không thể cập nhật user: ' . mysqli_error($conn));

        // 3. Xóa tất cả các liên kết căn hộ cũ
        mysqli_query($conn, "DELETE FROM ResidentApartment WHERE ResidentId = '$resident_id'")
            or throw new Exception('Không thể xóa thông tin căn hộ cũ: ' . mysqli_error($conn));

        // 4. Thêm lại thông tin căn hộ mới
        if (!empty($apartment_ids)) {
            foreach($apartment_ids as $index => $apartment_id) {
                if(!empty($apartment_id)) {
                    $relationship = mysqli_real_escape_string($conn, $relationships[$index]);
                    mysqli_query($conn, "
                        INSERT INTO ResidentApartment (ResidentId, ApartmentId, Relationship)
                        VALUES ('$resident_id', '$apartment_id', '$relationship')
                    ") or throw new Exception('Không thể thêm thông tin căn hộ: ' . mysqli_error($conn));
                }
            }
        }

        mysqli_commit($conn);
        $_SESSION['message'] = 'Cập nhật thông tin cư dân thành công!';
        header('location: resident_management.php');
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
    <title>Cập nhật thông tin cư dân</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            background: #899F87;
            color: white;
        }
        .btn-cancel {
            background: #C23636;
            color: white;
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
    </style>
</head>
<body>
    <div class="d-flex">
        <?php include '../admin_navbar.php'; ?>
        <div style="width: 100%;">
            <?php include '../admin_header.php'; ?>
            <div class="manage-container">
                <div class="page-header">
                    <h2>CẬP NHẬT THÔNG TIN CƯ DÂN</h2>
                    <div class="breadcrumb">
                        <a href="dashboard.php">Trang chủ</a>
                        <span>›</span>
                        <a href="resident_management.php">Quản lý cư dân</a>
                        <span>›</span>
                        <span>Cập nhật</span>
                    </div>
                </div>

                <div class="form-container">
                    <form action="" method="post">
                        <h5 class="mb-4">THÔNG TIN CƯ DÂN</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Tên cư dân<span class="required">*</span></label>
                                    <input type="text" name="name" class="form-control" required 
                                           value="<?php echo $resident['UserName']; ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Số điện thoại<span class="required">*</span></label>
                                    <input type="tel" name="phone" class="form-control" required 
                                           value="<?php echo $resident['PhoneNumber']; ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Giới tính</label>
                                    <div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="gender" value="Nam"
                                                   <?php echo ($resident['Gender'] == 'Nam') ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Nam</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="gender" value="Nữ"
                                                   <?php echo ($resident['Gender'] == 'Nữ') ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Nữ</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="gender" value="Khác"
                                                   <?php echo ($resident['Gender'] == 'Khác') ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Khác</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Ngày sinh</label>
                                    <input type="date" name="dob" class="form-control" 
                                           value="<?php echo $resident['Dob']; ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Căn cước công dân<span class="required">*</span></label>
                                    <input type="text" name="national_id" class="form-control" required 
                                           value="<?php echo $resident['NationalId']; ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Email<span class="required">*</span></label>
                                    <input type="email" name="email" class="form-control" required 
                                           value="<?php echo $resident['Email']; ?>">
                                </div>
                            </div>
                        </div>

                        <h5 class="mb-4 mt-4">THÔNG TIN CĂN HỘ</h5>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>STT</th>
                                    <th>Tòa nhà</th>
                                    <th>Tầng</th>
                                    <th>Căn hộ</th>
                                    <th>Quan hệ</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody id="apartment-rows">
                                <?php 
                                $stt = 1;
                                while($apartment = mysqli_fetch_assoc($apartments_query)) { 
                                ?>
                                <tr data-building-id="<?php echo $apartment['BuildingId']; ?>"
                                    data-floor-id="<?php echo $apartment['FloorId']; ?>"
                                    data-apartment-id="<?php echo $apartment['ApartmentId']; ?>">
                                    <td><?php echo $stt++; ?></td>
                                    <td>
                                        <select name="building_id[]" class="form-select building-select" required>
                                            <option value="">Chọn tòa nhà</option>
                                            <?php 
                                            $buildings = mysqli_query($conn, "SELECT ID, Name FROM Buildings WHERE Status = 'active'");
                                            while($building = mysqli_fetch_assoc($buildings)) {
                                                $selected = ($building['ID'] == $apartment['BuildingId']) ? 'selected' : '';
                                                echo "<option value='{$building['ID']}' {$selected}>{$building['Name']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="floor_id[]" class="form-select floor-select" required>
                                            <option value="">Chọn tầng</option>
                                            <?php 
                                            if($apartment['BuildingId']) {
                                                $floors = mysqli_query($conn, "SELECT ID, Name FROM Floors WHERE BuildingId = '{$apartment['BuildingId']}'");
                                                while($floor = mysqli_fetch_assoc($floors)) {
                                                    $selected = ($floor['ID'] == $apartment['FloorId']) ? 'selected' : '';
                                                    echo "<option value='{$floor['ID']}' {$selected}>{$floor['Name']}</option>";
                                                }
                                            }
                                            ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="apartment_id[]" class="form-select apartment-select" required>
                                            <option value="">Chọn căn hộ</option>
                                            <?php 
                                            if($apartment['FloorId']) {
                                                $apartments = mysqli_query($conn, "SELECT ApartmentID, Name FROM apartment WHERE FloorId = '{$apartment['FloorId']}'");
                                                while($apt = mysqli_fetch_assoc($apartments)) {
                                                    $selected = ($apt['ApartmentID'] == $apartment['ApartmentId']) ? 'selected' : '';
                                                    echo "<option value='{$apt['ApartmentID']}' {$selected}>{$apt['Name']}</option>";
                                                }
                                            }
                                            ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="relationship[]" class="form-select" required>
                                            <option value="">Chọn quan hệ</option>
                                            <?php
                                            $relationships = ['Khách thuê', 'Vợ/Chồng', 'Con', 'Bố mẹ', 'Anh chị em'];
                                            foreach($relationships as $rel) {
                                                $selected = ($rel == $apartment['Relationship']) ? 'selected' : '';
                                                echo "<option value='$rel' $selected>$rel</option>";
                                            }
                                            ?>
                                        </select>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                        <button type="button" class="btn btn-success mb-4" onclick="addApartmentRow()">
                            <i class="fas fa-plus"></i> Thêm mới
                        </button>

                        <div class="btn-container">
                            <button type="submit" name="submit" class="btn btn-submit">Cập nhật</button>
                            <a href="resident_management.php" class="btn btn-cancel">Hủy</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Xử lý sự kiện khi chọn tòa nhà
            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('building-select')) {
                    const buildingId = e.target.value;
                    const row = e.target.closest('tr');
                    const floorSelect = row.querySelector('.floor-select');
                    const apartmentSelect = row.querySelector('.apartment-select');
                    
                    // Reset các select phía sau
                    floorSelect.innerHTML = '<option value="">Chọn tầng</option>';
                    apartmentSelect.innerHTML = '<option value="">Chọn căn hộ</option>';
                    
                    if (buildingId) {
                        fetch(`update_resident.php?get_floors=1&building_id=${buildingId}`)
                            .then(response => response.json())
                            .then(data => {
                                data.forEach(floor => {
                                    floorSelect.innerHTML += `<option value="${floor.ID}">${floor.Name}</option>`;
                                });
                            });
                    }
                }
            });

            // Xử lý sự kiện khi chọn tầng
            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('floor-select')) {
                    const floorId = e.target.value;
                    const row = e.target.closest('tr');
                    const apartmentSelect = row.querySelector('.apartment-select');
                    
                    // Reset select căn hộ
                    apartmentSelect.innerHTML = '<option value="">Chọn căn hộ</option>';
                    
                    if (floorId) {
                        fetch(`update_resident.php?get_apartments=1&floor_id=${floorId}`)
                            .then(response => response.json())
                            .then(data => {
                                data.forEach(apartment => {
                                    apartmentSelect.innerHTML += `<option value="${apartment.ApartmentID}">${apartment.Name}</option>`;
                                });
                            });
                    }
                }
            });
        });

        // Hàm thêm dòng căn hộ mới
        function addApartmentRow() {
            const tbody = document.getElementById('apartment-rows');
            const rowCount = tbody.rows.length + 1;
            
            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td>${rowCount}</td>
                <td>
                    <select name="building_id[]" class="form-select building-select" required>
                        <option value="">Chọn tòa nhà</option>
                        <?php 
                        mysqli_data_seek($buildings, 0);
                        while($building = mysqli_fetch_assoc($buildings)) {
                            echo "<option value='{$building['ID']}'>{$building['Name']}</option>";
                        }
                        ?>
                    </select>
                </td>
                <td>
                    <select name="floor_id[]" class="form-select floor-select" required>
                        <option value="">Chọn tầng</option>
                    </select>
                </td>
                <td>
                    <select name="apartment_id[]" class="form-select apartment-select" required>
                        <option value="">Chọn căn hộ</option>
                    </select>
                </td>
                <td>
                    <select name="relationship[]" class="form-select" required>
                        <option value="">Chọn quan hệ</option>
                        <?php
                        foreach($relationships as $rel) {
                            echo "<option value='$rel'>$rel</option>";
                        }
                        ?>
                    </select>
                </td>
                <td>
                    <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            
            tbody.appendChild(newRow);
        }
    </script>
</body>
</html>