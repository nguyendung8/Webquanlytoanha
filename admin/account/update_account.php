<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Kiểm tra ID từ URL
if (!isset($_GET['id'])) {
    header('location:acount.php');
    exit();
}

$staff_id = mysqli_real_escape_string($conn, $_GET['id']);

// Lấy thông tin hiện tại của nhân viên
$staff_query = mysqli_query($conn, "SELECT * FROM `staffs` WHERE ID = '$staff_id'") or die('Query failed');
if (mysqli_num_rows($staff_query) == 0) {
    header('location:acount.php');
    exit();
}
$staff_data = mysqli_fetch_assoc($staff_query);

// Lấy thông tin từ bảng users (nếu có)
$user_query = mysqli_query($conn, "SELECT * FROM `users` WHERE Email = '{$staff_data['Email']}'") or die('Query failed');
$user_data = mysqli_num_rows($user_query) > 0 ? mysqli_fetch_assoc($user_query) : null;

// Lấy danh sách công ty và dự án
$select_companies = mysqli_query($conn, "
    SELECT DISTINCT c.*, p.ProjectID, p.Name as ProjectName 
    FROM Companies c 
    LEFT JOIN Projects p ON p.TownShipId IN (
        SELECT TownShipId FROM TownShips WHERE CompanyId = c.CompanyId
    )
    ORDER BY c.Name, p.Name
");

// Tổ chức dữ liệu thành mảng công ty và dự án
$companies = [];
while ($row = mysqli_fetch_assoc($select_companies)) {
    if (!isset($companies[$row['CompanyId']])) {
        $companies[$row['CompanyId']] = [
            'code' => $row['Code'],
            'name' => $row['Name'],
            'projects' => []
        ];
    }
    if ($row['ProjectID']) {
        $companies[$row['CompanyId']]['projects'][] = [
            'id' => $row['ProjectID'],
            'name' => $row['ProjectName']
        ];
    }
}

// Lấy danh sách dự án đã được phân công cho nhân viên
$staff_projects = [];
$projects_query = mysqli_query($conn, "SELECT ProjectId FROM StaffProjects WHERE StaffId = '$staff_id'");
if ($projects_query) {
    while ($project = mysqli_fetch_assoc($projects_query)) {
        $staff_projects[] = $project['ProjectId'];
    }
}

// Xử lý khi form được submit
if (isset($_POST['submit'])) {
    $employee_id = mysqli_real_escape_string($conn, $_POST['employee_id']);
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $position = mysqli_real_escape_string($conn, $_POST['position']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    $national_id = mysqli_real_escape_string($conn, $_POST['id_card']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $projects = isset($_POST['projects']) ? $_POST['projects'] : [];
    
    // Kiểm tra email đã tồn tại chưa (nếu email thay đổi)
    if ($email != $staff_data['Email']) {
        $check_email = mysqli_query($conn, "SELECT * FROM `users` WHERE Email = '$email' AND Email != '{$staff_data['Email']}'") or die('Query failed');
        
        if (mysqli_num_rows($check_email) > 0) {
            $message[] = 'Email đã tồn tại!';
        }
    }
    
    // Nếu không có lỗi, tiến hành cập nhật
    if (!isset($message)) {
        // Bắt đầu transaction để đảm bảo tính toàn vẹn dữ liệu
        mysqli_begin_transaction($conn);
        
        try {
            mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");
            
            // Cập nhật dữ liệu vào bảng staff
            $update_staff = mysqli_query($conn, "UPDATE `staffs` SET 
                                                ID = '$employee_id', 
                                                Name = '$fullname', 
                                                Email = '$email', 
                                                PhoneNumber = '$phone', 
                                                Position = '$position', 
                                                Address = '$address', 
                                                NationalID = '$national_id' 
                                                WHERE ID = '$staff_id'") 
                                                or throw new Exception('Staff update failed: ' . mysqli_error($conn));
            
            // Cập nhật dữ liệu vào bảng users nếu có
            if ($user_data) {
                $user_id = $user_data['UserId'];
                
                if (!empty($department)) {
                    $update_user = mysqli_query($conn, "UPDATE `users` SET 
                                                      UserName = '$fullname', 
                                                      Email = '$email', 
                                                      PhoneNumber = '$phone', 
                                                      Position = '$position', 
                                                      DepartmentId = '$department' 
                                                      WHERE UserId = '$user_id'") 
                                                      or throw new Exception('User update failed: ' . mysqli_error($conn));
                } else {
                    $update_user = mysqli_query($conn, "UPDATE `users` SET 
                                                      UserName = '$fullname', 
                                                      Email = '$email', 
                                                      PhoneNumber = '$phone', 
                                                      Position = '$position',
                                                      DepartmentId = NULL 
                                                      WHERE UserId = '$user_id'") 
                                                      or throw new Exception('User update failed: ' . mysqli_error($conn));
                }
            }
            
            // Xóa tất cả các dự án cũ của nhân viên
            mysqli_query($conn, "DELETE FROM StaffProjects WHERE StaffId = '$staff_id'")
                or throw new Exception('Không thể xóa dự án cũ: ' . mysqli_error($conn));

            // Thêm lại các dự án mới được chọn
            if (!empty($projects)) {
                foreach ($projects as $project_id) {
                    mysqli_query($conn, "
                        INSERT INTO StaffProjects (ProjectId, StaffId) 
                        VALUES ('$project_id', '$staff_id')
                    ") or throw new Exception('Không thể thêm dự án cho nhân viên: ' . mysqli_error($conn));
                }
            }
            
            mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");
            
            // Commit transaction nếu tất cả đều thành công
            mysqli_commit($conn);
            $message[] = 'Cập nhật thông tin thành công!';
            
        } catch (Exception $e) {
            // Rollback nếu có lỗi
            mysqli_rollback($conn);
            // Bật lại kiểm tra khóa ngoại trong trường hợp lỗi
            mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=1");
            $message[] = 'Cập nhật thông tin thất bại! Lỗi: ' . $e->getMessage();
        }
    }
}

// Lấy danh sách dự án đã chọn của nhân viên (nếu có)
$selected_projects = [];
try {
    $projects_query = mysqli_query($conn, "SELECT ProjectName FROM `staff_projects` WHERE StaffID = '$staff_id'");
    if ($projects_query) {
        while ($project = mysqli_fetch_assoc($projects_query)) {
            $selected_projects[] = $project['ProjectName'];
        }
    }
} catch (Exception $e) {
    // Bỏ qua nếu bảng không tồn tại
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cập nhật thông tin người dùng</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="../css/admin_style.css">
    <style>
        body {
            background-color: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
        
        .create-form {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-top: 20px;
        }
        
        .form-title {
            color: #476a52;
            border-bottom: 1px solid #eaeaea;
            padding-bottom: 15px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .form-group {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .form-label {
            width: 150px;
            text-align: right;
            margin-right: 15px;
            font-weight: 500;
            color: #4a5568;
        }
        
        .form-label .required {
            color: red;
        }
        
        .form-control {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            outline: none;
        }
        
        .form-control:focus {
            border-color: #476a52;
            box-shadow: 0 0 0 2px rgba(71, 106, 82, 0.1);
        }
        
        .form-select {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            outline: none;
            background-color: white;
            cursor: pointer;
        }
        
        .company-section {
            margin-top: 20px;
            margin-bottom: 20px;
        }
        
        .company-title {
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 10px;
        }
        
        .project-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            margin-left: 20px;
        }
        
        .project-item input[type="checkbox"] {
            margin-right: 10px;
        }
        
        .btn-container {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 25px;
        }
        
        .btn-submit {
            padding: 10px 30px;
            background-color: #476a52;
            color: white;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-submit:hover {
            background-color: #3a5943;
        }
        
        .btn-cancel {
            padding: 10px 30px;
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-cancel:hover {
            background-color: #c82333;
        }
        
        .manage-container {
            background:rgb(243, 239, 239) !important;
            width: 100%;
            padding: 20px;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <?php include '../admin_navbar.php'; ?>
        <div style="width: 100%;">
            <?php include '../admin_header.php'; ?>
            <div class="manage-container">
                <?php
                if (isset($message)) {
                    foreach ($message as $msg) {
                        echo '
                        <div class="alert alert-info alert-dismissible fade show" role="alert">
                            <span style="font-size: 16px;">' . $msg . '</span>
                            <i style="font-size: 20px; cursor: pointer" class="fas fa-times" onclick="this.parentElement.remove();"></i>
                        </div>';
                    }
                }
                ?>
                
                <h2 class="form-title">CẬP NHẬT THÔNG TIN NGƯỜI DÙNG</h2>
                
                <div class="create-form">
                    <form action="" method="post">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Mã nhân viên <span class="required">*</span>:</label>
                                    <input type="text" name="employee_id" class="form-control" placeholder="Nhập mã nhân viên" value="<?php echo $staff_data['ID']; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Email <span class="required">*</span>:</label>
                                    <input type="email" name="email" class="form-control" placeholder="Email" value="<?php echo $staff_data['Email']; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Chức vụ <span class="required">*</span>:</label>
                                    <select name="position" class="form-select" required>
                                        <option value="">Chọn chức vụ</option>
                                        <option value="Quản trị hệ thống" <?php if($staff_data['Position'] == 'Quản trị hệ thống') echo 'selected'; ?>>Quản trị hệ thống</option>
                                        <option value="Kế toán ban" <?php if($staff_data['Position'] == 'Kế toán ban') echo 'selected'; ?>>Kế toán ban</option>
                                        <option value="Trưởng BQL" <?php if($staff_data['Position'] == 'Trưởng BQL') echo 'selected'; ?>>Trưởng BQL</option>
                                        <option value="Kế toán HO" <?php if($staff_data['Position'] == 'Kế toán HO') echo 'selected'; ?>>Kế toán HO</option>
                                        <option value="Phó BQL" <?php if($staff_data['Position'] == 'Phó BQL') echo 'selected'; ?>>Phó BQL</option>
                                        <option value="Nhân viên kỹ thuật" <?php if($staff_data['Position'] == 'Nhân viên kỹ thuật') echo 'selected'; ?>>Nhân viên kỹ thuật</option>
                                        <option value="Lễ tân" <?php if($staff_data['Position'] == 'Lễ tân') echo 'selected'; ?>>Lễ tân</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">CCCD/CMT/Hộ chiếu:</label>
                                    <input type="text" name="id_card" class="form-control" placeholder="CCCD/CMT/Hộ chiếu" value="<?php echo $staff_data['NationalID']; ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Họ và tên <span class="required">*</span>:</label>
                                    <input type="text" name="fullname" class="form-control" placeholder="Nhập tên nhân viên" value="<?php echo $staff_data['Name']; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">SĐT <span class="required">*</span>:</label>
                                    <input type="tel" name="phone" class="form-control" placeholder="Số điện thoại" value="<?php echo $staff_data['PhoneNumber']; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Bộ phận:</label>
                                    <select name="department" class="form-select">
                                        <option value="">Chọn bộ phận</option>
                                        <option value="1" <?php if($user_data && $user_data['DepartmentId'] == 1) echo 'selected'; ?>>Ban quản lý</option>
                                        <option value="2" <?php if($user_data && $user_data['DepartmentId'] == 2) echo 'selected'; ?>>Kế toán</option>
                                        <option value="3" <?php if($user_data && $user_data['DepartmentId'] == 3) echo 'selected'; ?>>Kỹ thuật</option>
                                        <option value="4" <?php if($user_data && $user_data['DepartmentId'] == 4) echo 'selected'; ?>>Lễ tân</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Địa chỉ:</label>
                                    <input type="text" name="address" class="form-control" placeholder="Nhập địa chỉ" value="<?php echo $staff_data['Address']; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Company Sections -->
                        <div class="company-sections">
                            <?php foreach ($companies as $company_id => $company) { ?>
                                <div class="company-section">
                                    <div class="company-header">
                                        <div class="company-title">
                                            <?php echo htmlspecialchars($company['name']); ?>
                                            <span class="company-code">(<?php echo htmlspecialchars($company['code']); ?>)</span>
                                        </div>
                                    </div>
                                    <?php if (!empty($company['projects'])) { ?>
                                        <div class="project-list">
                                            <?php foreach ($company['projects'] as $project) { ?>
                                                <div class="project-item">
                                                    <input type="checkbox" 
                                                        name="projects[]" 
                                                        value="<?php echo $project['id']; ?>" 
                                                        id="project-<?php echo $project['id']; ?>"
                                                        <?php echo in_array($project['id'], $staff_projects) ? 'checked' : ''; ?>>
                                                    <label for="project-<?php echo $project['id']; ?>">
                                                        <?php echo htmlspecialchars($project['name']); ?>
                                                    </label>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    <?php } else { ?>
                                        <div class="no-projects">Chưa có dự án nào</div>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </div>
                        
                        <div class="btn-container">
                            <button type="submit" name="submit" class="btn-submit">Cập nhật</button>
                            <a href="acount.php" class="btn-cancel">Hủy bỏ</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>