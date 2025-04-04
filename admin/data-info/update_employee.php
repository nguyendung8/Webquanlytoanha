<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location:../index.php');
    exit();
}

if (isset($_GET['id'])) {
    $employee_id = mysqli_real_escape_string($conn, $_GET['id']);
    
    // Lấy thông tin nhân viên
    $select_employee = mysqli_query($conn, "
        SELECT * FROM Staffs WHERE ID = '$employee_id'
    ") or die('Query failed');
    
    if(mysqli_num_rows($select_employee) > 0) {
        $employee = mysqli_fetch_assoc($select_employee);
    } else {
        header('location: company_employees.php');
        exit();
    }

    // Lấy danh sách dự án của nhân viên
    $select_staff_projects = mysqli_query($conn, "
        SELECT ProjectId FROM StaffProjects WHERE StaffId = '$employee_id'
    ");
    
    $staff_projects = [];
    while($row = mysqli_fetch_assoc($select_staff_projects)) {
        $staff_projects[] = $row['ProjectId'];
    }
} else {
    header('location: company_employees.php');
    exit();
}

// Lấy danh sách phòng ban
$select_departments = mysqli_query($conn, "SELECT * FROM Departments ORDER BY Name");

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

// Xử lý thêm nhân viên mới
if (isset($_POST['submit'])) {
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $position = mysqli_real_escape_string($conn, $_POST['position']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    $national_id = mysqli_real_escape_string($conn, $_POST['id_card']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $projects = isset($_POST['projects']) ? $_POST['projects'] : [];

    // Kiểm tra email đã tồn tại chưa (trừ email hiện tại)
    $check_email = mysqli_query($conn, "SELECT * FROM Staffs WHERE Email = '$email' AND ID != '$employee_id'");
    
    if (mysqli_num_rows($check_email) > 0) {
        $message[] = 'Email đã tồn tại!';
    } else {
        mysqli_begin_transaction($conn);
        
        try {
            // Tắt kiểm tra khóa ngoại
            mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");

            // Cập nhật thông tin nhân viên
            $update_staff = mysqli_query($conn, "
                UPDATE Staffs SET 
                    Name = '$fullname',
                    Email = '$email',
                    PhoneNumber = '$phone',
                    Position = '$position',
                    DepartmentId = '$department',
                    Address = '$address',
                    NationalID = '$national_id'
                WHERE ID = '$employee_id'
            ") or throw new Exception('Không thể cập nhật nhân viên: ' . mysqli_error($conn));

            // Xóa các dự án cũ
            mysqli_query($conn, "DELETE FROM StaffProjects WHERE StaffId = '$employee_id'");

            // Thêm lại các dự án mới
            if (!empty($projects)) {
                foreach ($projects as $project_id) {
                    mysqli_query($conn, "
                        INSERT INTO StaffProjects (ProjectId, StaffId) 
                        VALUES ('$project_id', '$employee_id')
                    ") or throw new Exception('Không thể thêm dự án cho nhân viên: ' . mysqli_error($conn));
                }
            }

            // Bật lại kiểm tra khóa ngoại
            mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");

            mysqli_commit($conn);
            $message[] = 'Cập nhật nhân viên thành công!';
            header('location: company_employees.php');
            exit();
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");
            $message[] = 'Lỗi: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm mới nhân viên</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .manage-container {
            padding: 30px;
            width: 100%;
        }

        .page-header {
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

        .form-container {
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            color: #2d3748;
        }

        .required {
            color: #e53e3e;
            margin-left: 4px;
        }

        .form-control {
            width: 100%;
            padding: 10px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .form-control:focus {
            border-color: #3182ce;
            box-shadow: 0 0 0 3px rgba(49, 130, 206, 0.1);
        }

        .form-select {
            width: 100%;
            padding: 10px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            background-color: white;
            cursor: pointer;
        }

        .company-sections {
            margin-top: 30px;
        }

        .company-section {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            margin-bottom: 20px;
            overflow: hidden;
        }

        .company-header {
            padding: 16px 20px;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
        }

        .company-title {
            font-size: 16px;
            font-weight: 600;
            color: #2d3748;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .company-code {
            color: #718096;
            font-size: 14px;
            font-weight: normal;
        }

        .project-list {
            padding: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 16px;
        }

        .project-item {
            background: white;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .project-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .project-item label {
            margin: 0;
            cursor: pointer;
            font-size: 14px;
            color: #4a5568;
            flex: 1;
        }

        .no-projects {
            padding: 30px;
            text-align: center;
            color: #718096;
            font-style: italic;
        }

        .btn-container {
            margin-top: 30px;
            display: flex;
            justify-content: center;
            gap: 20px;
        }

        .btn {
            padding: 8px 24px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            background: #FFFFFF;
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
            <?php
            if (isset($message)) {
                foreach ($message as $msg) {
                    echo '<div class="alert alert-info alert-dismissible fade show" role="alert">
                            <span>' . $msg . '</span>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                          </div>';
                }
            }
            ?>

            <div class="page-header">
                <h2>CẬP NHẬT THÔNG TIN NHÂN VIÊN</h2>
                <div class="breadcrumb">
                    <a href="dashboard.php">Trang chủ</a>
                    <span>›</span>
                    <a href="company_employees.php">Nhân viên công ty</a>
                    <span>›</span>
                    <span>Thêm mới</span>
                </div>
            </div>

            <div class="form-container">
                <form action="" method="post">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                Mã nhân viên
                            </label>
                            <input type="text" class="form-control" value="<?php echo $employee['ID']; ?>" readonly>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Họ và tên<span class="required">*</span>
                            </label>
                            <input type="text" name="fullname" class="form-control" 
                                   value="<?php echo $employee['Name']; ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                Email<span class="required">*</span>
                            </label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?php echo $employee['Email']; ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                SĐT<span class="required">*</span>
                            </label>
                            <input type="tel" name="phone" class="form-control" 
                                   value="<?php echo $employee['PhoneNumber']; ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                Chức vụ<span class="required">*</span>
                            </label>
                            <select name="position" class="form-select" required>
                                <option value="">Chọn chức vụ</option>
                                <option value="Quản trị hệ thống" <?php echo $employee['Position'] === 'Quản trị hệ thống' ? 'selected' : ''; ?>>Quản trị hệ thống</option>
                                <option value="Kế toán ban" <?php echo $employee['Position'] === 'Kế toán ban' ? 'selected' : ''; ?>>Kế toán ban</option>
                                <option value="Trưởng BQL" <?php echo $employee['Position'] === 'Trưởng BQL' ? 'selected' : ''; ?>>Trưởng BQL</option>
                                <option value="Kế toán HO" <?php echo $employee['Position'] === 'Kế toán HO' ? 'selected' : ''; ?>>Kế toán HO</option>
                                <option value="Phó BQL" <?php echo $employee['Position'] === 'Phó BQL' ? 'selected' : ''; ?>>Phó BQL</option>
                                <option value="Nhân viên kỹ thuật" <?php echo $employee['Position'] === 'Nhân viên kỹ thuật' ? 'selected' : ''; ?>>Nhân viên kỹ thuật</option>
                                <option value="Lễ tân" <?php echo $employee['Position'] === 'Lễ tân' ? 'selected' : ''; ?>>Lễ tân</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Phòng ban</label>
                            <select name="department" class="form-select">
                                <option value="">Chọn phòng ban</option>
                                <?php while($dept = mysqli_fetch_assoc($select_departments)) { ?>
                                    <option value="<?php echo $dept['ID']; ?>" <?php echo $employee['DepartmentId'] == $dept['ID'] ? 'selected' : ''; ?>>
                                        <?php echo $dept['Name']; ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">CCCD/CMT/Hộ chiếu</label>
                            <input type="text" name="id_card" class="form-control" value="<?php echo $employee['NationalID']; ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Địa chỉ</label>
                            <input type="text" name="address" class="form-control" value="<?php echo $employee['Address']; ?>">
                        </div>
                    </div>

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
                                                <input type="checkbox" name="projects[]" 
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
                        <button type="submit" name="submit" class="btn btn-submit">
                            Cập nhật
                        </button>
                        <a href="company_employees.php" class="btn btn-cancel">
                            Hủy bỏ
                        </a>
                    </div>
                </form>
            </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>