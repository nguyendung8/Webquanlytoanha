<?php
include '../../database/DBController.php';
require_once '../utils/Mailer.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
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
    
    // Validate trước khi xử lý
    $has_error = false;
    $message = [];

    // Kiểm tra email đã tồn tại chưa
    $check_email = mysqli_query($conn, "SELECT * FROM `users` WHERE Email = '$email'");
    if (mysqli_num_rows($check_email) > 0) {
        $has_error = true;
        $message[] = 'Email đã tồn tại!';
    }

    // Nếu là Trưởng BQL, kiểm tra các dự án đã chọn có Trưởng BQL chưa
    if ($position === 'Trưởng BQL') {
        if (empty($projects)) {
            $has_error = true;
            $message[] = 'Vui lòng chọn ít nhất một dự án cho Trưởng BQL!';
        } else {
            $project_ids = implode(',', array_map(function($id) use ($conn) {
                return mysqli_real_escape_string($conn, $id);
            }, $projects));
            
            $check_manager = mysqli_query($conn, "
                SELECT p.ProjectID, p.Name, s.Name as ManagerName
                FROM Projects p
                LEFT JOIN Staffs s ON p.ManagerId = s.ID
                WHERE p.ProjectID IN ($project_ids) AND p.ManagerId != 0
            ");
            
            if (mysqli_num_rows($check_manager) > 0) {
                $has_error = true;
                $conflicting_projects = [];
                while ($row = mysqli_fetch_assoc($check_manager)) {
                    $conflicting_projects[] = $row['Name'] . ' (Trưởng BQL hiện tại: ' . $row['ManagerName'] . ')';
                }
                $message[] = 'Các dự án sau đã có Trưởng BQL: <br>' . implode('<br>', $conflicting_projects);
            }
        }
    }

    // Nếu không có lỗi thì mới tiếp tục xử lý
    if (!$has_error) {
        // Mật khẩu mặc định là 123456
        $default_password = '123456';
        $password = md5($default_password);

        // Bắt đầu transaction
        mysqli_begin_transaction($conn);
        
        try {
            mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");
            
            // Thêm mới nhân viên
            $insert_staff = mysqli_query($conn, "
                INSERT INTO Staffs (Name, Email, PhoneNumber, Position, DepartmentId, Address, NationalID) 
                VALUES ('$fullname', '$email', '$phone', '$position', '$department', '$address', '$national_id')
            ") or throw new Exception('Không thể thêm nhân viên: ' . mysqli_error($conn));
            
            $staff_id = mysqli_insert_id($conn);

            // Thêm vào bảng users
            $insert_user = mysqli_query($conn, "
                INSERT INTO users (UserName, Email, PhoneNumber, Position, DepartmentId, Password) 
                VALUES ('$fullname', '$email', '$phone', '$position', '$department', '$password')
            ") or throw new Exception('Không thể thêm user: ' . mysqli_error($conn));

            // Thêm các dự án được chọn
            if (!empty($projects)) {
                foreach ($projects as $project_id) {
                    // Thêm vào StaffProjects
                    mysqli_query($conn, "
                        INSERT INTO StaffProjects (ProjectId, StaffId) 
                        VALUES ('$project_id', '$staff_id')
                    ") or throw new Exception('Không thể thêm dự án cho nhân viên: ' . mysqli_error($conn));

                    // Nếu là Trưởng BQL, cập nhật ManagerId trong bảng Projects
                    if ($position === 'Trưởng BQL') {
                        mysqli_query($conn, "
                            UPDATE Projects 
                            SET ManagerId = '$staff_id' 
                            WHERE ProjectID = '$project_id'
                        ") or throw new Exception('Không thể cập nhật Trưởng BQL cho dự án: ' . mysqli_error($conn));
                    }
                }
            }

            // Gửi email thông tin tài khoản
            $mailer = new Mailer();
            $emailSent = $mailer->sendNewAccountEmail($fullname, $email, $default_password);

            mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");
            mysqli_commit($conn);
            
            if (!$emailSent) {
                $_SESSION['warning_msg'] = 'Thêm tài khoản thành công nhưng không gửi được email!';
            } else {
                $_SESSION['success_msg'] = 'Thêm tài khoản và gửi email thành công!';
            }
            
            header('location: acount.php');
            exit();
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");
            $message[] = 'Lỗi: ' . $e->getMessage();
        }
    }
}

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
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm mới người dùng</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="css/admin_style.css">
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
            if (isset($message) && !empty($message)) {
                foreach ($message as $msg) {
                    echo '
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <div style="font-size: 16px;">' . $msg . '</div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
                }
            }
            ?>
            
                <h2 class="form-title">THÊM MỚI NGƯỜI DÙNG</h2>
                
                <div class="create-form">
                    <form action="" method="post">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Mã nhân viên <span class="required">*</span>:</label>
                                    <input type="text" name="employee_id" class="form-control" placeholder="Nhập mã nhân viên" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Email <span class="required">*</span>:</label>
                                    <input type="email" name="email" class="form-control" placeholder="Email" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Chức vụ <span class="required">*</span>:</label>
                                    <select name="position" class="form-select" required>
                                        <option value="">Chọn chức vụ</option>
                                        <option value="Quản trị hệ thống">Quản trị hệ thống</option>
                                        <option value="Kế toán ban">Kế toán ban</option>
                                        <option value="Trưởng BQL">Trưởng BQL</option>
                                        <option value="Kế toán HO">Kế toán HO</option>
                                        <option value="Phó BQL">Phó BQL</option>
                                        <option value="Nhân viên kỹ thuật">Nhân viên kỹ thuật</option>
                                        <option value="Lễ tân">Lễ tân</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">CCCD/CMT/Hộ chiếu:</label>
                                    <input type="text" name="id_card" class="form-control" placeholder="CCCD/CMT/Hộ chiếu">
                </div>
            </div>
            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Họ và tên <span class="required">*</span>:</label>
                                    <input type="text" name="fullname" class="form-control" placeholder="Nhập tên nhân viên" required>
                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">SĐT <span class="required">*</span>:</label>
                                    <input type="tel" name="phone" class="form-control" placeholder="Số điện thoại" required>
            </div>
            
                                <div class="form-group">
                                    <label class="form-label">Bộ phận:</label>
                                    <select name="department" class="form-select">
                                        <option value="">Chọn bộ phận</option>
                                        <option value="1">Ban quản lý</option>
                                        <option value="2">Kế toán</option>
                                        <option value="3">Kỹ thuật</option>
                                        <option value="4">Lễ tân</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Địa chỉ:</label>
                                    <input type="text" name="address" class="form-control" placeholder="Nhập địa chỉ">
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
                                                        id="project-<?php echo $project['id']; ?>">
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
                            <button type="submit" name="submit" class="btn-submit">Thêm mới</button>
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