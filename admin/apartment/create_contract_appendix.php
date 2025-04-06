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

// Xử lý gia hạn hợp đồng
if(isset($_POST['submit'])) {
    $cretion_date = mysqli_real_escape_string($conn, $_POST['cretion_date']);
    $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
    
    mysqli_begin_transaction($conn);
    try {
        // Thêm phụ lục hợp đồng mới
        mysqli_query($conn, "
            INSERT INTO ContractAppendixs (ContractCode, CretionDate, Status) 
            VALUES ('$contract_code', '$cretion_date', 'active')
        ") or throw new Exception(mysqli_error($conn));

        // Cập nhật ngày kết thúc trong hợp đồng gốc
        mysqli_query($conn, "
            UPDATE Contracts 
            SET EndDate = '$end_date',
                Status = 'modified'
            WHERE ContractCode = '$contract_code'
        ") or throw new Exception(mysqli_error($conn));
        
        // Xử lý các dịch vụ được chọn
        if(isset($_POST['service_selected'])) {
            foreach($_POST['service_selected'] as $index => $service_id) {
                $service_apply_date = mysqli_real_escape_string($conn, $_POST['service_apply_date'][$index]);
                $service_end_date = !empty($_POST['service_end_date'][$index]) ? 
                    "'" . mysqli_real_escape_string($conn, $_POST['service_end_date'][$index]) . "'" : "NULL";
                
                // Thêm dịch vụ mới cho hợp đồng
                mysqli_query($conn, "
                    INSERT INTO ContractServices (ContractCode, ServiceId, ApplyDate, EndDate) 
                    VALUES ('$contract_code', '$service_id', '$service_apply_date', $service_end_date)
                ") or throw new Exception(mysqli_error($conn));
            }
        }
        
        mysqli_commit($conn);
        $_SESSION['success_msg'] = 'Thêm phụ lục hợp đồng thành công!';
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
                    <h2>PHỤ LỤC HỢP ĐỒNG</h2>
                    <div class="breadcrumb">
                        <a href="dashboard.php">Trang chủ</a>
                        <span>›</span>
                        <a href="contract_management.php">Quản lý hợp đồng</a>
                        <span>›</span>
                        <span>Phụ lục hợp đồng</span>
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
                                    <input type="text" class="form-control" value="<?php echo $contract_data['ProjectName']; ?>" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Người đại diện</label>
                                    <input type="text" class="form-control" value="<?php echo $contract_data['ManagerName']; ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Chức vụ</label>
                                    <input type="text" class="form-control" value="<?php echo $contract_data['Position']; ?>" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Địa chỉ</label>
                            <input type="text" class="form-control" value="<?php echo $contract_data['Address']; ?>" readonly>
                        </div>

                        <!-- BÊN B (Chủ sở hữu căn hộ) -->
                        <div class="section-header mt-4">BÊN B (Chủ sở hữu căn hộ)</div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Họ và tên <span class="required">*</span></label>
                                    <input type="text" class="form-control" value="<?php echo $contract_data['UserName']; ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Căn cước công dân <span class="required">*</span></label>
                                    <input type="text" class="form-control" value="<?php echo $contract_data['NationalId']; ?>" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Email</label>
                                    <input type="text" class="form-control" value="<?php echo $contract_data['Email']; ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Số điện thoại</label>
                                    <input type="text" class="form-control" value="<?php echo $contract_data['PhoneNumber']; ?>" readonly>
                                </div>
                            </div>
                        </div>

                        <!-- THÔNG TIN HỢP ĐỒNG -->
                        <div class="section-header mt-4">THÔNG TIN HỢP ĐỒNG</div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Mã hợp đồng</label>
                                    <input type="text" class="form-control" value="<?php echo $contract_data['ContractCode']; ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Mã phụ lục hợp đồng</label>
                                    <input type="text" class="form-control" value="<?php echo $contract_data['ContractCode'].'-PL'.date('Ymd'); ?>" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Ngày áp dụng (*)</label>
                                    <input type="date" name="cretion_date" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Ngày kết thúc (*)</label>
                                    <input type="date" name="end_date" class="form-control" required>
                                </div>
                            </div>
                        </div>

                        <!-- THÔNG TIN CĂN HỘ -->
                        <div class="section-header mt-4">THÔNG TIN CĂN HỘ</div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Tòa nhà <span class="required">*</span></label>
                                    <input type="text" class="form-control" value="<?php echo $contract_data['BuildingName']; ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Căn hộ <span class="required">*</span></label>
                                    <input type="text" class="form-control" value="<?php echo $contract_data['ApartmentCode'] . ' - ' . $contract_data['ApartmentName']; ?>" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Diện tích</label>
                            <input type="text" class="form-control" value="<?php echo $contract_data['Area']; ?>" readonly>
                        </div>

                        <!-- DỊCH VỤ ÁP DỤNG -->
                        <div class="section-header mt-4">DỊCH VỤ ÁP DỤNG</div>
                        <div id="services-container">
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
                                    <?php foreach($all_services as $index => $service): 
                                        $is_selected = isset($selected_services[$service['ServiceCode']]);
                                        $service_data = $is_selected ? $selected_services[$service['ServiceCode']] : null;
                                    ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="service_selected[]" 
                                                       value="<?php echo $service['ServiceCode']; ?>"
                                                       <?php echo $is_selected ? 'checked' : ''; ?>
                                                       onchange="toggleServiceDates(this, <?php echo $index; ?>)">
                                            </td>
                                            <td><?php echo $service['Name']; ?></td>
                                            <td><?php echo number_format($service['Price']) . ' ' . ($service['TypeOfFee'] == 'monthly' ? 'tháng' : 'tháng'); ?></td>
                                            <td>
                                                <input type="date" name="service_apply_date[]" 
                                                       class="form-control" 
                                                       value="<?php echo $service_data ? $service_data['ApplyDate'] : ''; ?>"
                                                       <?php echo !$is_selected ? 'disabled' : ''; ?>>
                                                <input type="hidden" name="service_id[]" 
                                                       value="<?php echo $service['ServiceCode']; ?>">
                                            </td>
                                            <td>
                                                <input type="date" name="service_end_date[]" 
                                                       class="form-control"
                                                       value="<?php echo $service_data ? $service_data['EndDate'] : ''; ?>"
                                                       <?php echo !$is_selected ? 'disabled' : ''; ?>>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
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

        document.querySelector('form').addEventListener('submit', function(e) {
            const cretionDate = new Date(document.querySelector('input[name="cretion_date"]').value);
            const endDate = new Date(document.querySelector('input[name="end_date"]').value);
            
            if (cretionDate >= endDate) {
                e.preventDefault();
                alert('Ngày kết thúc phải lớn hơn ngày áp dụng!');
                return false;
            }

            // Kiểm tra các dịch vụ được chọn
            const selectedServices = document.querySelectorAll('input[name="service_selected[]"]:checked');
            
            for (let checkbox of selectedServices) {
                const row = checkbox.closest('tr');
                const applyDate = row.querySelector('input[name="service_apply_date[]"]');
                const endDate = row.querySelector('input[name="service_end_date[]"]');
                
                if (!applyDate.value) {
                    e.preventDefault();
                    alert('Vui lòng nhập ngày áp dụng cho các dịch vụ đã chọn');
                    return;
                }
                
                if (endDate.value && new Date(applyDate.value) >= new Date(endDate.value)) {
                    e.preventDefault();
                    alert('Ngày kết thúc dịch vụ phải lớn hơn ngày áp dụng');
                    return;
                }
            }
        });

        function toggleServiceDates(checkbox, index) {
            const row = checkbox.closest('tr');
            const applyDate = row.querySelector('input[name="service_apply_date[]"]');
            const endDate = row.querySelector('input[name="service_end_date[]"]');
            
            if (checkbox.checked) {
                applyDate.disabled = false;
                endDate.disabled = false;
            } else {
                applyDate.disabled = true;
                endDate.disabled = true;
                applyDate.value = '';
                endDate.value = '';
            }
        }
    </script>
</body>
</html>