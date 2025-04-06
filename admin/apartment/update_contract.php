<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Lấy thông tin hợp đồng cần sửa
if(isset($_GET['contract_code'])) {
    $contract_code = mysqli_real_escape_string($conn, $_GET['contract_code']);
    
    // Lấy thông tin hợp đồng
    $contract_query = mysqli_query($conn, "
        SELECT c.*, 
            a.ApartmentID, a.BuildingId, a.Area, a.Code as ApartmentCode, a.Name as ApartmentName,
            b.ProjectId, b.Name as BuildingName,
            p.Name as ProjectName, p.Address,
            s.Name as ManagerName, s.Position,
            r.ID as ResidentId, r.NationalId,
            u.UserName, u.Email, u.PhoneNumber,
            c.CretionDate
        FROM Contracts c
        JOIN apartment a ON a.ContractCode = c.ContractCode
        JOIN Buildings b ON a.BuildingId = b.ID
        JOIN Projects p ON b.ProjectId = p.ProjectID
        LEFT JOIN staffs s ON p.ManagerId = s.ID
        JOIN ResidentApartment ra ON ra.ApartmentId = a.ApartmentID AND ra.Relationship = 'Chủ hộ'
        JOIN resident r ON ra.ResidentId = r.ID
        JOIN users u ON r.ID = u.ResidentID
        WHERE c.ContractCode = '$contract_code'
    ");

    if(mysqli_num_rows($contract_query) > 0) {
        $contract_data = mysqli_fetch_assoc($contract_query);
        
        // Lấy danh sách dịch vụ của hợp đồng
        $services_query = mysqli_query($conn, "
            SELECT cs.*, s.Name, s.ServiceCode, p.Price, p.TypeOfFee
            FROM ContractServices cs
            JOIN services s ON cs.ServiceId = s.ServiceCode
            LEFT JOIN ServicePrice sp ON s.ServiceCode = sp.ServiceId
            LEFT JOIN pricelist p ON sp.PriceId = p.ID
            WHERE cs.ContractCode = '$contract_code'
        ");
        
        $contract_services = array();
        while($service = mysqli_fetch_assoc($services_query)) {
            $contract_services[] = $service;
        }

        // Lấy danh sách tòa nhà của dự án
        $building_query = mysqli_query($conn, "
            SELECT ID, Name 
            FROM Buildings 
            WHERE ProjectId = '{$contract_data['ProjectId']}' AND Status = 'active'
        ");

        // Lấy danh sách căn hộ của tòa nhà
        $apartment_query = mysqli_query($conn, "
            SELECT ApartmentID, Name, Code, Area 
            FROM apartment 
            WHERE BuildingId = '{$contract_data['BuildingId']}'
            AND Status != 'Trống'
        ");

        // Pre-select các giá trị
        $selected_building = $contract_data['BuildingId'];
        $selected_apartment = $contract_data['ApartmentID'];
        $selected_resident = $contract_data['ResidentId'];
    } else {
        $_SESSION['error_msg'] = 'Không tìm thấy hợp đồng!';
        header('location: contract_management.php');
        exit();
    }
} else {
    header('location: contract_management.php');
    exit();
}

// Xử lý cập nhật hợp đồng
if(isset($_POST['submit'])) {
    $cretion_date = mysqli_real_escape_string($conn, $_POST['cretion_date']);
    $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
    $resident_id = isset($_POST['resident_id']) ? mysqli_real_escape_string($conn, $_POST['resident_id']) : '';
    $apartment_id = isset($_POST['apartment_id']) ? mysqli_real_escape_string($conn, $_POST['apartment_id']) : '';
    
    mysqli_begin_transaction($conn);
    try {
        // Xử lý upload file
        $file_name = '';
        if(isset($_FILES['contract_file']) && $_FILES['contract_file']['error'] == 0) {
            try {
                // Đường dẫn upload cố định
                $upload_dir = dirname(dirname(__FILE__)) . '/uploads/contracts/';
                
                // Tạo thư mục nếu chưa tồn tại
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Tạo tên file mới: mã hợp đồng + timestamp
                $file_extension = strtolower(pathinfo($_FILES['contract_file']['name'], PATHINFO_EXTENSION));
                $new_filename = $contract_code . '_' . time() . '.' . $file_extension;
                
                // Đường dẫn đầy đủ đến file
                $file_path = $upload_dir . $new_filename;
                
                // Debug
                error_log("Upload dir: " . $upload_dir);
                error_log("File path: " . $file_path);
                
                // Upload file
                if(!move_uploaded_file($_FILES['contract_file']['tmp_name'], $file_path)) {
                    throw new Exception('Không thể upload file');
                }
                
                // Xóa file cũ nếu có
                if(!empty($contract_data['File']) && file_exists($upload_dir . $contract_data['File'])) {
                    unlink($upload_dir . $contract_data['File']);
                }
                
                // Lưu tên file vào biến để update database
                $file_name = $new_filename;
                
            } catch (Exception $e) {
                throw new Exception('Lỗi upload file: ' . $e->getMessage());
            }
        }
        
        // Cập nhật thông tin hợp đồng
        $update_query = "UPDATE Contracts SET 
            CretionDate = '$cretion_date',
            EndDate = '$end_date'";
            
        // Nếu có file mới, cập nhật file và trạng thái
        if($file_name !== '') {
            $update_query .= ", File = '$file_name', Status = 'active'";
        }
        
        $update_query .= " WHERE ContractCode = '$contract_code'";
        
        mysqli_query($conn, $update_query) or throw new Exception(mysqli_error($conn));
        
        mysqli_commit($conn);
        $_SESSION['success_msg'] = 'Cập nhật hợp đồng thành công!';
        header('location: contract_management.php');
        exit();
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error_msg'] = 'Lỗi: ' . $e->getMessage();
    }
}

// Lấy danh sách dự án
$select_projects = mysqli_query($conn, "SELECT ProjectID, Name FROM Projects WHERE Status = 'active' ORDER BY Name");

// Lấy danh sách tất cả dịch vụ của dự án
$all_services_query = mysqli_query($conn, "
    SELECT s.*, p.Price, p.TypeOfFee
    FROM services s
    LEFT JOIN ServicePrice sp ON s.ServiceCode = sp.ServiceId
    LEFT JOIN pricelist p ON sp.PriceId = p.ID
    WHERE s.ProjectId = '{$contract_data['ProjectId']}'
    AND s.Status = 'active'
    ORDER BY s.Name
");

$all_services = array();
while($service = mysqli_fetch_assoc($all_services_query)) {
    $all_services[] = $service;
}

// Tạo mảng các ServiceCode đã được chọn trong hợp đồng
$selected_services = array();
foreach($contract_services as $contract_service) {
    $selected_services[$contract_service['ServiceCode']] = $contract_service;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cập nhật hợp đồng</title>
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
        .section-header {
            background-color: #f5f5f5;
            padding: 10px 15px;
            margin-bottom: 15px;
            font-weight: 600;
            border-radius: 5px;
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
        .service-table {
            width: 100%;
            border-collapse: collapse;
        }
        .service-table th, .service-table td {
            padding: 10px;
            border: 1px solid #ddd;
        }
        .service-table th {
            background-color: #f2f2f2;
            text-align: left;
        }
        .service-checkbox {
            margin-right: 10px;
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

        .file-upload {
            position: relative;
            margin-bottom: 20px;
        }

        .file-upload .form-control {
            padding: 8px;
            line-height: 1.5;
        }

        .current-file {
            margin-top: 8px;
            font-size: 14px;
        }

        .current-file a {
            color: #476a52;
            text-decoration: none;
        }

        .current-file a:hover {
            text-decoration: underline;
        }

        .file-upload .fas {
            margin-right: 5px;
        }

        .btn-outline-primary {
            padding: 6px 12px;
            border-color: #476a52;
            color: #476a52;
        }

        .btn-outline-primary:hover {
            background-color: #476a52;
            border-color: #476a52;
            color: white;
        }

        .gap-2 {
            gap: 0.5rem !important;
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <?php include '../admin_navbar.php'; ?>
        <div style="width: 100%;">
            <?php include '../admin_header.php'; ?>
            <div class="manage-container">
                <!-- Breadcrumb -->
                <div class="page-header">
                    <h2>CẬP NHẬT HỢP ĐỒNG</h2>
                    <div class="breadcrumb">
                        <a href="dashboard.php">Trang chủ</a>
                        <span>›</span>
                        <a href="contract_management.php">Quản lý hợp đồng</a>
                        <span>›</span>
                        <span>Cập nhật hợp đồng</span>
                    </div>
                </div>

                <?php if(isset($_SESSION['error_msg'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= $_SESSION['error_msg']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error_msg']); ?>
                <?php endif; ?>

                <div class="form-container">
                    <form action="" method="post" enctype="multipart/form-data">
                        <!-- BÊN A (Ban quản lý tòa nhà) -->
                        <div class="section-header">BÊN A (Ban quản lý tòa nhà)</div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Tên dự án quản lý</label>
                                    <select name="project_id" id="project_id" class="form-select" required>
                                        <option value="">Chọn dự án</option>
                                        <?php while($project = mysqli_fetch_assoc($select_projects)) { ?>
                                            <option value="<?php echo $project['ProjectID']; ?>" <?php echo $project['ProjectID'] == $contract_data['ProjectId'] ? 'selected' : ''; ?>>
                                                <?php echo $project['Name']; ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Người đại diện</label>
                                    <input type="text" id="representative" class="form-control" readonly value="<?php echo $contract_data['ManagerName'] ?? ''; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Chức vụ</label>
                                    <input type="text" id="position" class="form-control" readonly value="<?php echo $contract_data['Position'] ?? ''; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Địa chỉ</label>
                            <input type="text" id="address" class="form-control" readonly value="<?php echo $contract_data['Address'] ?? ''; ?>">
                        </div>

                        <!-- BÊN B (Chủ sở hữu căn hộ) -->
                        <div class="section-header mt-4">BÊN B (Chủ sở hữu căn hộ)</div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Họ và tên <span class="required">*</span></label>
                                    <select name="resident_id" id="resident_id" class="form-select" required>
                                        <option value="<?php echo $contract_data['ResidentId']; ?>" 
                                                data-national-id="<?php echo $contract_data['NationalId']; ?>"
                                                data-email="<?php echo $contract_data['Email']; ?>"
                                                data-phone="<?php echo $contract_data['PhoneNumber']; ?>"
                                                selected>
                                            <?php echo $contract_data['UserName']; ?>
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Căn cước công dân <span class="required">*</span></label>
                                    <input type="text" name="national_id" id="national_id" class="form-control" readonly required value="<?php echo $contract_data['NationalId'] ?? ''; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Email</label>
                                    <input type="email" id="email" name="email" class="form-control" readonly value="<?php echo $contract_data['Email'] ?? ''; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Số điện thoại</label>
                                    <input type="tel" id="phone" name="phone" class="form-control" readonly value="<?php echo $contract_data['PhoneNumber'] ?? ''; ?>">
                                </div>
                            </div>
                        </div>

                        <!-- THÔNG TIN HỢP ĐỒNG -->
                        <div class="section-header mt-4">THÔNG TIN HỢP ĐỒNG</div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Mã hợp đồng</label>
                                    <input type="text" name="contract_code" id="contract_code" class="form-control" value="<?php echo $contract_data['ContractCode'] ?? ''; ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Ngày tạo hợp đồng</label>
                                    <input type="date" name="creation_date" class="form-control" value="<?php echo $contract_data['CretionDate'] ?? ''; ?>" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Ngày áp dụng <span class="required">*</span></label>
                                    <input type="date" name="cretion_date" class="form-control" required value="<?php echo $contract_data['CretionDate'] ?? ''; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Ngày kết thúc</label>
                                    <input type="date" name="end_date" class="form-control" value="<?php echo $contract_data['EndDate'] ?? ''; ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">File hợp đồng</label>
                                    <div class="d-flex align-items-center gap-2">
                                        <a href="/webquanlytoanha/export_contract.php?contract_code=<?php echo $contract_data['ContractCode']; ?>" 
                                           download
                                           class="btn btn-outline-primary">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <input type="file" name="contract_file" class="form-control" accept=".pdf,.doc,.docx">
                                    </div>
                                    <?php if (!empty($contract_data['File'])): ?>
                                        <div class="mt-2">
                                            <a href="/webquanlytoanha/admin/uploads/contracts/<?php echo $contract_data['File']; ?>" 
                                               target="_blank" class="text-primary">
                                                <i class="fas fa-file-alt"></i> Xem file hiện tại
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- THÔNG TIN CĂN HỘ -->
                        <div class="section-header mt-4">THÔNG TIN CĂN HỘ</div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Tòa nhà <span class="required">*</span></label>
                                    <select name="building_id" id="building_id" class="form-select" required>
                                        <option value="">Chọn tòa nhà</option>
                                        <?php while($building = mysqli_fetch_assoc($building_query)) { ?>
                                            <option value="<?php echo $building['ID']; ?>" 
                                                    <?php echo ($building['ID'] == $selected_building) ? 'selected' : ''; ?>>
                                                <?php echo $building['Name']; ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Căn hộ <span class="required">*</span></label>
                                    <select name="apartment_id" id="apartment_id" class="form-select" required>
                                        <option value="">Chọn căn hộ</option>
                                        <?php while($apartment = mysqli_fetch_assoc($apartment_query)) { ?>
                                            <option value="<?php echo $apartment['ApartmentID']; ?>" 
                                                    <?php echo ($apartment['ApartmentID'] == $selected_apartment) ? 'selected' : ''; ?>>
                                                <?php echo $apartment['Code'] . ' - ' . $apartment['Name']; ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Diện tích</label>
                            <input type="text" id="area" class="form-control" readonly value="<?php echo $contract_data['Area'] ?? ''; ?>">
                        </div>

                        <!-- DỊCH VỤ ÁP DỤNG -->
                        <div class="section-header mt-4">DỊCH VỤ ÁP DỤNG</div>
                        <div id="services-container">
                            <table id="services-table" class="service-table">
                                <thead>
                                    <tr>
                                        <th width="40%">Dịch vụ áp dụng</th>
                                        <th width="20%">Giá</th>
                                        <th width="20%">Ngày áp dụng</th>
                                        <th width="20%">Ngày kết thúc</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($contract_services as $service): ?>
                                        <tr>
                                            <td><?php echo $service['Name']; ?></td>
                                            <td><?php echo $service['Price'] . ' ' . ($service['TypeOfFee'] == 'monthly' ? 'tháng' : 'tháng'); ?></td>
                                            <td><?php echo $service['ApplyDate']; ?></td>
                                            <td><?php echo $service['EndDate']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if(empty($contract_services)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center">Không có dịch vụ nào được áp dụng</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="btn-container">
                            <button type="submit" name="submit" class="btn btn-success">Cập nhật</button>
                            <a href="contract_management.php" class="btn btn-danger">Hủy</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Hiển thị lỗi nếu có
        function showError(message) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'alert alert-danger';
            errorDiv.textContent = message;
            document.querySelector('.form-container').prepend(errorDiv);
            
            // Tự động ẩn sau 5 giây
            setTimeout(() => {
                errorDiv.remove();
            }, 5000);
        }
        
        // Xử lý lỗi khi fetch API
        async function fetchWithErrorHandling(url) {
            try {
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.error) {
                    showError(data.error);
                    return [];
                }
                
                return data;
            } catch (error) {
                showError('Lỗi khi tải dữ liệu: ' + error.message);
                return [];
            }
        }
        
        // Xử lý lấy thông tin người đại diện dự án
        document.getElementById('project_id').addEventListener('change', async function() {
            const projectId = this.value;
            if (!projectId) return;
            
            // Lấy thông tin người đại diện
            const projectData = await fetchWithErrorHandling(`create_contract.php?get_project_representative=1&project_id=${projectId}`);
            if (projectData && !projectData.error) {
                document.getElementById('representative').value = projectData.ManagerName || 'Chưa có thông tin';
                document.getElementById('position').value = projectData.Position || 'Chưa có thông tin';
                document.getElementById('address').value = projectData.Address || 'Chưa có thông tin';
            }
                
            // Lấy danh sách tòa nhà
            const buildings = await fetchWithErrorHandling(`create_contract.php?get_buildings=1&project_id=${projectId}`);
            const buildingSelect = document.getElementById('building_id');
            buildingSelect.innerHTML = '<option value="">Chọn tòa nhà</option>';
            
            if (buildings && buildings.length > 0) {
                buildings.forEach(building => {
                    buildingSelect.innerHTML += `<option value="${building.ID}">${building.Name}</option>`;
                });
            }
                
            // Lấy danh sách dịch vụ
            const services = await fetchWithErrorHandling(`create_contract.php?get_services=1&project_id=${projectId}`);
            const servicesTable = document.getElementById('services-table').getElementsByTagName('tbody')[0];
            servicesTable.innerHTML = '';
            
            if (services && services.length > 0) {
                services.forEach((service, index) => {
                    const row = document.createElement('tr');
                    const price = service.Price || 'Chưa có giá';
                    const feeType = service.TypeOfFee || '';
                    
                    row.innerHTML = `
                        <td>${service.Name}</td>
                        <td>${price} ${feeType}</td>
                        <td>
                            <input type="hidden" name="service_id[${index}]" value="${service.ServiceCode}">
                            <input type="date" name="service_apply_date[${index}]" class="form-control" value="${service.ApplyDate}" disabled>
                        </td>
                        <td>
                            <input type="date" name="service_end_date[${index}]" class="form-control" value="${service.EndDate}" disabled>
                        </td>
                    `;
                    servicesTable.appendChild(row);
                    
                    // Lưu ServiceCode vào dataset của checkbox
                    const checkbox = document.getElementById(`service_${index}`);
                    if (checkbox) {
                        checkbox.dataset.serviceCode = service.ServiceCode;
                    }
                });
            } else {
                servicesTable.innerHTML = '<tr><td colspan="5">Không có dịch vụ cho dự án này</td></tr>';
            }
        });
        
        // Xử lý lấy danh sách căn hộ theo tòa nhà
        document.getElementById('building_id').addEventListener('change', async function() {
            const buildingId = this.value;
            if (!buildingId) return;
            
            const apartments = await fetchWithErrorHandling(`create_contract.php?get_apartments=1&building_id=${buildingId}`);
            const apartmentSelect = document.getElementById('apartment_id');
            apartmentSelect.innerHTML = '<option value="">Chọn căn hộ</option>';
            
            if (apartments && apartments.length > 0) {
                apartments.forEach(apartment => {
                    apartmentSelect.innerHTML += `<option value="${apartment.ApartmentID}" data-area="${apartment.Area}">${apartment.Code} - ${apartment.Name}</option>`;
                });
            } else {
                apartmentSelect.innerHTML = '<option value="">Không có căn hộ</option>';
            }
        });
        
        // Xử lý chọn chủ sở hữu và auto-fill CCCD
        document.getElementById('resident_id').addEventListener('change', function() {
            const residentId = this.value;
            const nationalIdInput = document.getElementById('national_id');
            const emailInput = document.getElementById('email');
            const phoneInput = document.getElementById('phone');
            
            if (!residentId) {
                // Reset các trường nếu không chọn chủ sở hữu
                nationalIdInput.value = '';
                emailInput.value = '';
                phoneInput.value = '';
                return;
            }
            
            // Lấy thông tin từ option được chọn
            const selectedOption = this.options[this.selectedIndex];
            
            // Auto-fill các trường thông tin
            nationalIdInput.value = selectedOption.dataset.nationalId || '';
            emailInput.value = selectedOption.dataset.email || '';
            phoneInput.value = selectedOption.dataset.phone || '';
        });

        document.querySelector('input[name="contract_file"]').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const maxSize = 5 * 1024 * 1024; // 5MB
            const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            
            if (file) {
                if (file.size > maxSize) {
                    alert('File không được vượt quá 5MB');
                    this.value = '';
                    return;
                }
                
                if (!allowedTypes.includes(file.type)) {
                    alert('Chỉ chấp nhận file PDF, DOC hoặc DOCX');
                    this.value = '';
                    return;
                }
            }
        });
    </script>
</body>
</html>