<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Hàm tự động tạo mã hợp đồng
function generateContractCode($conn) {
    $currentYear = date('Y');
    $currentMonth = date('m');
    $prefix = $currentYear . $currentMonth . "/HĐCH/";
    
    // Kiểm tra mã cao nhất hiện tại
    $result = mysqli_query($conn, "SELECT ContractCode FROM Contracts WHERE ContractCode LIKE '$prefix%' ORDER BY ContractCode DESC LIMIT 1");
    
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $lastCode = $row['ContractCode'];
        $lastNumber = intval(substr($lastCode, -4));
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    
    // Format số thành chuỗi 4 chữ số (với các số 0 ở đầu nếu cần)
    $formattedNumber = sprintf("%04d", $newNumber);
    return $prefix . $formattedNumber;
}

// AJAX - Lấy thông tin người đại diện dự án
if(isset($_GET['get_project_representative'])) {
    header('Content-Type: application/json');
    
    try {
        $project_id = mysqli_real_escape_string($conn, $_GET['project_id']);
        // Sửa query để phù hợp với cấu trúc CSDL hiện tại
        $query = mysqli_query($conn, "
            SELECT p.*, s.Name as ManagerName, s.Position 
            FROM Projects p
            LEFT JOIN staffs s ON p.ManagerId = s.ID
            WHERE p.ProjectID = '$project_id'
        ");
        
        if (!$query) {
            throw new Exception(mysqli_error($conn));
        }
        
        if (mysqli_num_rows($query) > 0) {
            $project = mysqli_fetch_assoc($query);
            echo json_encode($project);
        } else {
            echo json_encode(['error' => 'Không tìm thấy thông tin dự án']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

// AJAX - Lấy danh sách tòa nhà theo dự án
if(isset($_GET['get_buildings'])) {
    header('Content-Type: application/json');
    
    try {
        $project_id = mysqli_real_escape_string($conn, $_GET['project_id']);
    $buildings = mysqli_query($conn, "SELECT ID, Name FROM Buildings WHERE ProjectId = '$project_id' AND Status = 'active'");
        
        if (!$buildings) {
            throw new Exception(mysqli_error($conn));
        }
        
    $building_list = array();
    while($building = mysqli_fetch_assoc($buildings)) {
        $building_list[] = $building;
    }
    echo json_encode($building_list);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

// AJAX - Lấy danh sách căn hộ theo tòa nhà
if(isset($_GET['get_apartments'])) {
    header('Content-Type: application/json');
    
    try {
        $building_id = mysqli_real_escape_string($conn, $_GET['building_id']);
        $apartments = mysqli_query($conn, "SELECT ApartmentID, Name, Code, Area FROM apartment WHERE BuildingId = '$building_id' AND Status = 'Trống'");
        
        if (!$apartments) {
            throw new Exception(mysqli_error($conn));
        }
        
        $apartment_list = array();
        while($apartment = mysqli_fetch_assoc($apartments)) {
            $apartment_list[] = $apartment;
        }
        echo json_encode($apartment_list);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

// AJAX - Lấy danh sách cư dân
if(isset($_GET['get_residents'])) {
    header('Content-Type: application/json');
    
    try {
        $residents = mysqli_query($conn, "
            SELECT r.ID, r.NationalId,
                   u.UserName, u.Email, u.PhoneNumber
            FROM resident r
            JOIN users u ON r.ID = u.ResidentID
            WHERE r.ID NOT IN (
                SELECT DISTINCT ResidentId 
                FROM ResidentApartment
            )
            ORDER BY u.UserName ASC
        ") or throw new Exception(mysqli_error($conn));
        
        $resident_list = array();
        while($resident = mysqli_fetch_assoc($residents)) {
            $resident_list[] = $resident;
        }
        
        echo json_encode($resident_list);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

// AJAX - Lấy danh sách dịch vụ theo dự án
if(isset($_GET['get_services'])) {
    header('Content-Type: application/json');
    
    try {
        $project_id = mysqli_real_escape_string($conn, $_GET['project_id']);
        $services = mysqli_query($conn, "
            SELECT s.ServiceCode, s.Name, p.Price, p.TypeOfFee
            FROM services s
            LEFT JOIN ServicePrice sp ON s.ServiceCode = sp.ServiceId
            LEFT JOIN pricelist p ON sp.PriceId = p.ID
            WHERE s.ProjectId = '$project_id' AND s.Status = 'active'
        ");
        
        if (!$services) {
            throw new Exception(mysqli_error($conn));
        }
        
        $service_list = array();
        while($service = mysqli_fetch_assoc($services)) {
            $service_list[] = $service;
        }
        echo json_encode($service_list);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

// Lấy danh sách dự án
$select_projects = mysqli_query($conn, "SELECT ProjectID, Name FROM Projects WHERE Status = 'active' ORDER BY Name");

// Xử lý thêm mới hợp đồng
if(isset($_POST['submit'])) {
    $contract_code = mysqli_real_escape_string($conn, $_POST['contract_code']);
    $creation_date = mysqli_real_escape_string($conn, $_POST['creation_date']);
    $apply_date = mysqli_real_escape_string($conn, $_POST['apply_date']);
    $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
    $resident_id = isset($_POST['resident_id']) ? mysqli_real_escape_string($conn, $_POST['resident_id']) : '';
    $apartment_id = isset($_POST['apartment_id']) ? mysqli_real_escape_string($conn, $_POST['apartment_id']) : '';
    
    // Lấy thông tin dịch vụ (nếu có)
    $service_ids = isset($_POST['service_id']) ? $_POST['service_id'] : [];
    $service_apply_dates = isset($_POST['service_apply_date']) ? $_POST['service_apply_date'] : [];
    $service_end_dates = isset($_POST['service_end_date']) ? $_POST['service_end_date'] : [];
    
        mysqli_begin_transaction($conn);
        try {
        // Thêm hợp đồng mới
        $insert_contract = mysqli_query($conn, "
            INSERT INTO Contracts (ContractCode, Status, CretionDate, EndDate) 
            VALUES ('$contract_code', 'pending', '$apply_date', '$end_date')
        ") or throw new Exception('Không thể thêm hợp đồng: ' . mysqli_error($conn));
        
        // Thêm mối quan hệ chủ hộ vào bảng ResidentApartment
        $insert_resident_apartment = mysqli_query($conn, "
            INSERT INTO ResidentApartment (ResidentId, ApartmentId, Relationship)
            VALUES ('$resident_id', '$apartment_id', 'Chủ hộ')
        ") or throw new Exception('Không thể thêm mối quan hệ chủ hộ: ' . mysqli_error($conn));
        
        // Cập nhật ContractCode và Status trong bảng apartment
        $update_apartment = mysqli_query($conn, "
            UPDATE apartment 
            SET ContractCode = '$contract_code',
                Status = 'Đang chờ nhận'
            WHERE ApartmentID = '$apartment_id'
        ") or throw new Exception('Không thể cập nhật thông tin căn hộ: ' . mysqli_error($conn));
        
        // Thêm các dịch vụ cho hợp đồng
        for($i = 0; $i < count($service_ids); $i++) {
            if(isset($service_ids[$i]) && !empty($service_ids[$i])) {
                $service_id = mysqli_real_escape_string($conn, $service_ids[$i]);
                $service_apply_date = mysqli_real_escape_string($conn, $service_apply_dates[$i]);
                $service_end_date = mysqli_real_escape_string($conn, $service_end_dates[$i]);
                
                mysqli_query($conn, "
                    INSERT INTO ContractServices (ContractCode, ServiceId, ApplyDate, EndDate) 
                    VALUES ('$contract_code', '$service_id', '$service_apply_date', '$service_end_date')
                ") or throw new Exception('Không thể thêm dịch vụ cho hợp đồng: ' . mysqli_error($conn));
            }
        }

            mysqli_commit($conn);
        $_SESSION['success_msg'] = 'Thêm hợp đồng thành công!';
        header('location: contract_management.php');
            exit();
        } catch (Exception $e) {
            mysqli_rollback($conn);
        $_SESSION['error_msg'] = 'Lỗi: ' . $e->getMessage();
    }
}

// Tạo mã hợp đồng mới
$new_contract_code = generateContractCode($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm mới hợp đồng</title>
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
                    <h2>THÊM MỚI HỢP ĐỒNG</h2>
                    <div class="breadcrumb">
                        <a href="dashboard.php">Trang chủ</a>
                        <span>›</span>
                        <a href="contract_management.php">Quản lý hợp đồng</a>
                        <span>›</span>
                        <span>Thêm mới hợp đồng</span>
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
                    <form action="" method="post">
                        <!-- BÊN A (Ban quản lý tòa nhà) -->
                        <div class="section-header">BÊN A (Ban quản lý tòa nhà)</div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Tên dự án quản lý</label>
                                    <select name="project_id" id="project_id" class="form-select" required>
                                        <option value="">Chọn dự án</option>
                                        <?php while($project = mysqli_fetch_assoc($select_projects)) { ?>
                                            <option value="<?php echo $project['ProjectID']; ?>">
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
                                    <input type="text" id="representative" class="form-control" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Chức vụ</label>
                                    <input type="text" id="position" class="form-control" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Địa chỉ</label>
                            <input type="text" id="address" class="form-control" readonly>
                        </div>

                        <!-- BÊN B (Chủ sở hữu căn hộ) -->
                        <div class="section-header mt-4">BÊN B (Chủ sở hữu căn hộ)</div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Họ và tên <span class="required">*</span></label>
                                    <select name="resident_id" id="resident_id" class="form-select" required>
                                        <option value="">Chọn chủ sở hữu</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Căn cước công dân <span class="required">*</span></label>
                                    <input type="text" name="national_id" id="national_id" class="form-control" readonly required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Email</label>
                                    <input type="email" id="email" name="email" class="form-control" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Số điện thoại</label>
                                    <input type="tel" id="phone" name="phone" class="form-control" readonly>
                                </div>
                            </div>
                        </div>

                        <!-- THÔNG TIN HỢP ĐỒNG -->
                        <div class="section-header mt-4">THÔNG TIN HỢP ĐỒNG</div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Mã hợp đồng</label>
                                    <input type="text" name="contract_code" id="contract_code" class="form-control" value="<?php echo $new_contract_code; ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Ngày tạo hợp đồng</label>
                                    <input type="date" name="creation_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Ngày áp dụng <span class="required">*</span></label>
                                    <input type="date" name="apply_date" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Ngày kết thúc</label>
                                    <input type="date" name="end_date" class="form-control">
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
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Căn hộ <span class="required">*</span></label>
                                    <select name="apartment_id" id="apartment_id" class="form-select" required>
                                        <option value="">Chọn căn hộ</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Diện tích</label>
                            <input type="text" id="area" class="form-control" readonly>
                        </div>

                        <!-- DỊCH VỤ ÁP DỤNG -->
                        <div class="section-header mt-4">DỊCH VỤ ÁP DỤNG</div>
                        <div id="services-container">
                            <!-- Services will be loaded here -->
                            <table id="services-table" class="service-table">
                                <thead>
                                    <tr>
                                        <th width="5%"></th>
                                        <th width="35%">Dịch vụ áp dụng</th>
                                        <th width="20%">Giá</th>
                                        <th width="20%">Ngày áp dụng</th>
                                        <th width="20%">Ngày kết thúc</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="5">Vui lòng chọn dự án để xem danh sách dịch vụ</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="btn-container">
                            <button type="submit" name="submit" class="btn btn-success">Thêm mới</button>
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
                        <td>
                            <input type="checkbox" class="service-checkbox" id="service_${index}" 
                                onchange="toggleServiceFields(${index})">
                        </td>
                        <td>${service.Name}</td>
                        <td>${price} ${feeType}</td>
                        <td>
                            <input type="hidden" name="service_id[${index}]" value="">
                            <input type="date" name="service_apply_date[${index}]" class="form-control" disabled>
                        </td>
                        <td>
                            <input type="date" name="service_end_date[${index}]" class="form-control" disabled>
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
        
        // Load danh sách cư dân khi trang được load
        document.addEventListener('DOMContentLoaded', async function() {
            try {
                const response = await fetch('create_contract.php?get_residents=1');
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                const residents = await response.json();
                
                const residentSelect = document.getElementById('resident_id');
                const nationalIdInput = document.getElementById('national_id');
                
                residentSelect.innerHTML = '<option value="">Chọn chủ sở hữu</option>';
                
                if (residents && !residents.error && residents.length > 0) {
                    residents.forEach(resident => {
                        // Thêm option cho select chủ sở hữu
                        residentSelect.innerHTML += `
                            <option value="${resident.ID}" 
                                data-national-id="${resident.NationalId || ''}"
                                data-email="${resident.Email || ''}"
                                data-phone="${resident.PhoneNumber || ''}">
                                ${resident.UserName}
                            </option>
                        `;
                    });
                } else {
                    showError('Không có cư dân nào chưa được gán căn hộ. Vui lòng thêm cư dân mới.');
                }
            } catch (error) {
                showError('Lỗi khi tải danh sách cư dân: ' + error.message);
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
        
        // Xử lý chọn dịch vụ
        function toggleServiceFields(index) {
            const checkbox = document.getElementById(`service_${index}`);
            const row = checkbox.closest('tr');
            const serviceIdInput = row.querySelector('input[name^="service_id"]');
            const applyDateInput = row.querySelector('input[name^="service_apply_date"]');
            const endDateInput = row.querySelector('input[name^="service_end_date"]');
            
            if (checkbox.checked) {
                serviceIdInput.value = checkbox.dataset.serviceCode;
                applyDateInput.disabled = false;
                endDateInput.disabled = false;
            } else {
                serviceIdInput.value = '';
                applyDateInput.disabled = true;
                endDateInput.disabled = true;
            }
        }
    </script>
</body>
</html>