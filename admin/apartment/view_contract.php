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
    
    // Lấy thông tin hợp đồng và các thông tin liên quan
    $contract_query = mysqli_query($conn, "
        SELECT c.*, 
            a.ApartmentID, a.BuildingId, a.Area, a.Code as ApartmentCode, a.Name as ApartmentName,
            b.ProjectId, b.Name as BuildingName,
            p.Name as ProjectName, p.Address,
            s.Name as ManagerName, s.Position,
            r.ID as ResidentId, r.NationalId,
            u.UserName, u.Email, u.PhoneNumber,
            COALESCE(ca.CretionDate, c.CretionDate) as EffectiveDate
        FROM Contracts c
        JOIN apartment a ON a.ContractCode = c.ContractCode
        JOIN Buildings b ON a.BuildingId = b.ID
        JOIN Projects p ON b.ProjectId = p.ProjectID
        LEFT JOIN staffs s ON p.ManagerId = s.ID
        JOIN ResidentApartment ra ON ra.ApartmentId = a.ApartmentID AND ra.Relationship = 'Chủ hộ'
        JOIN resident r ON ra.ResidentId = r.ID
        JOIN users u ON r.ID = u.ResidentID
        LEFT JOIN (
            SELECT ContractCode, CretionDate 
            FROM ContractAppendixs 
            WHERE Status = 'active'
            ORDER BY CretionDate DESC 
            LIMIT 1
        ) ca ON ca.ContractCode = c.ContractCode
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
            ORDER BY cs.ApplyDate DESC
        ");
        
        $contract_services = array();
        while($service = mysqli_fetch_assoc($services_query)) {
            $contract_services[] = $service;
        }

        // Lấy danh sách tất cả dịch vụ của dự án
        $all_services_query = mysqli_query($conn, "
            SELECT s.*, p.Price, p.TypeOfFee,
                   cs.ApplyDate, cs.EndDate
            FROM services s
            LEFT JOIN ServicePrice sp ON s.ServiceCode = sp.ServiceId
            LEFT JOIN pricelist p ON sp.PriceId = p.ID
            LEFT JOIN ContractServices cs ON s.ServiceCode = cs.ServiceId 
                AND cs.ContractCode = '$contract_code'
            WHERE s.ProjectId = '{$contract_data['ProjectId']}'
            AND s.Status = 'active'
            ORDER BY s.Name
        ");

        $all_services = array();
        while($service = mysqli_fetch_assoc($all_services_query)) {
            $all_services[] = $service;
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

        // Lấy danh sách phụ lục hợp đồng
        $appendix_query = mysqli_query($conn, "
            SELECT *
            FROM ContractAppendixs
            WHERE ContractCode = '$contract_code'
            ORDER BY CretionDate DESC
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
        
        mysqli_commit($conn);
        $_SESSION['success_msg'] = 'Gia hạn hợp đồng thành công!';
        header('location: contract_management.php');
        exit();
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error_msg'] = 'Lỗi: ' . $e->getMessage();
    }
}

// Tạo mảng các ServiceCode đã được chọn trong hợp đồng
$selected_services = array();
foreach($contract_services as $contract_service) {
    $selected_services[$contract_service['ServiceCode']] = $contract_service;
}

// Thêm vào đầu file sau phần session
if(isset($_GET['cancel_appendix'])) {
    $appendix_id = mysqli_real_escape_string($conn, $_GET['cancel_appendix']);
    
    mysqli_begin_transaction($conn);
    try {
        // Lấy thông tin phụ lục cần hủy
        $appendix_query = mysqli_query($conn, "
            SELECT * FROM ContractAppendixs 
            WHERE ContractAppendixId = '$appendix_id' AND Status = 'active'
        ");
        
        if($appendix = mysqli_fetch_assoc($appendix_query)) {
            // Cập nhật trạng thái phụ lục thành 'cancelled'
            mysqli_query($conn, "
                UPDATE ContractAppendixs 
                SET Status = 'cancelled' 
                WHERE ContractAppendixId = '$appendix_id'
            ") or throw new Exception(mysqli_error($conn));

            // Lấy phụ lục active mới nhất sau khi hủy
            $latest_appendix = mysqli_query($conn, "
                SELECT * FROM ContractAppendixs 
                WHERE ContractCode = '{$appendix['ContractCode']}' 
                AND Status = 'active'
                ORDER BY CretionDate DESC 
                LIMIT 1
            ");

            if($latest = mysqli_fetch_assoc($latest_appendix)) {
                // Nếu còn phụ lục active khác, cập nhật hợp đồng với thông tin phụ lục mới nhất
                mysqli_query($conn, "
                    UPDATE Contracts 
                    SET Status = 'modified',
                        EndDate = '{$latest['EndDate']}'
                    WHERE ContractCode = '{$appendix['ContractCode']}'
                ") or throw new Exception(mysqli_error($conn));
            } else {
                // Nếu không còn phụ lục active nào, quay về thông tin hợp đồng gốc
                mysqli_query($conn, "
                    UPDATE Contracts 
                    SET Status = 'active',
                        EndDate = NULL
                    WHERE ContractCode = '{$appendix['ContractCode']}'
                ") or throw new Exception(mysqli_error($conn));
            }
        }

        mysqli_commit($conn);
        $_SESSION['success_msg'] = 'Hủy phụ lục thành công!';
        header("Location: view_contract.php?contract_code=" . $appendix['ContractCode']);
        exit();
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error_msg'] = 'Lỗi: ' . $e->getMessage();
        header("Location: view_contract.php?contract_code=" . $contract_code);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hợp đồng <?php echo $contract_data['ContractCode']; ?></title>
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

        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background-color: #f8f9fa;
            font-weight: 500;
        }
        
        .form-control[readonly] {
            background-color: #f8f9fa;
            cursor: default;
        }
        
        .btn-container {
            margin-top: 20px;
            text-align: right;
        }
        
        .section-header {
            background-color: #476a52;
            color: white;
            padding: 10px 15px;
            margin-bottom: 15px;
            font-weight: 500;
            border-radius: 5px;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-active {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-cancelled {
            background: #ffebee;
            color: #c62828;
        }
        
        .action-icon {
            color: #666;
            margin: 0 5px;
            font-size: 16px;
            text-decoration: none;
        }
        
        .action-icon:hover {
            color: #dc3545;
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
            float: right;
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
                    <h2>HỢP ĐỒNG <?php echo $contract_data['ContractCode']; ?></h2>
                    <div class="breadcrumb">
                        <a href="dashboard.php">Trang chủ</a>
                        <span>›</span>
                        <a href="contract_management.php">Quản lý hợp đồng</a>
                        <span>›</span>
                        <span>Hợp đồng <?php echo $contract_data['ContractCode']; ?></span>
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
                    <!-- BÊN A (Ban quản lý tòa nhà) -->
                    <div class="section-header">BÊN A (Ban quản lý tòa nhà)</div>
                    <div class="row">
                        <div class="col-md-12">
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
                                <label class="form-label">Họ và tên</label>
                                <input type="text" class="form-control" value="<?php echo $contract_data['UserName']; ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Căn cước công dân</label>
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
                                <label class="form-label">Ngày tạo hợp đồng</label>
                                <input type="text" class="form-control" value="<?php echo date('d/m/Y', strtotime($contract_data['CretionDate'])); ?>" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Ngày áp dụng</label>
                                <input type="text" class="form-control" value="<?php echo date('d/m/Y', strtotime($contract_data['EffectiveDate'])); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Ngày kết thúc</label>
                                <input type="text" class="form-control" value="<?php echo $contract_data['EndDate'] ? date('d/m/Y', strtotime($contract_data['EndDate'])) : ''; ?>" readonly>
                            </div>
                        </div>
                    </div>

                    <!-- THÔNG TIN CĂN HỘ -->
                    <div class="section-header mt-4">THÔNG TIN CĂN HỘ</div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Tòa nhà</label>
                                <input type="text" class="form-control" value="<?php echo $contract_data['BuildingName']; ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Căn hộ</label>
                                <input type="text" class="form-control" value="<?php echo $contract_data['ApartmentCode'] . ' - ' . $contract_data['ApartmentName']; ?>" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Diện tích</label>
                        <input type="text" class="form-control" value="<?php echo $contract_data['Area']; ?> m²" readonly>
                    </div>

                    <!-- DỊCH VỤ ÁP DỤNG -->
                    <div class="section-header mt-4">DỊCH VỤ ÁP DỤNG</div>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>STT</th>
                                <th>Dịch vụ áp dụng</th>
                                <th>Giá</th>
                                <th>Ngày áp dụng</th>
                                <th>Ngày kết thúc</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if(count($contract_services) > 0):
                                $stt = 1;
                                foreach($contract_services as $service): 
                            ?>
                                <tr>
                                    <td><?php echo $stt++; ?></td>
                                    <td><?php echo $service['Name']; ?></td>
                                    <td><?php echo number_format($service['Price']) . ' ' . ($service['TypeOfFee'] == 'monthly' ? 'tháng' : 'tháng'); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($service['ApplyDate'])); ?></td>
                                    <td><?php echo $service['EndDate'] ? date('d/m/Y', strtotime($service['EndDate'])) : ''; ?></td>
                                </tr>
                            <?php 
                                endforeach;
                            else:
                            ?>
                                <tr>
                                    <td colspan="5" class="text-center">Không có dịch vụ nào được áp dụng</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- PHỤ LỤC HỢP ĐỒNG -->
                    <div class="section-header mt-4">PHỤ LỤC HỢP ĐỒNG</div>
                    <div class="text-end">
                            <a href="create_contract_appendix.php?contract_code=<?php echo $contract_code; ?>" class="btn add-btn">
                                <i class="fas fa-plus"></i> Thêm mới phụ lục
                            </a>
                        </div>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>STT</th>
                                <th>Mã phụ lục</th>
                                <th>Ngày tạo</th>
                                <th>Ngày hiệu lực</th>
                                <th>Ngày kết hạn</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $stt = 1;
                            while($appendix = mysqli_fetch_assoc($appendix_query)): 
                                $isActive = $appendix['Status'] == 'active';
                            ?>
                            <tr>
                                <td><?php echo $stt++; ?></td>
                                <td><?php echo $appendix['ContractAppendixId']; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($appendix['CretionDate'])); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($appendix['CretionDate'])); ?></td>
                                <td><?php echo $contract_data['EndDate'] ? date('d/m/Y', strtotime($contract_data['EndDate'])) : ''; ?></td>
                                <td>
                                    <span class="status-badge <?php echo $isActive ? 'status-active' : 'status-cancelled'; ?>">
                                        <?php echo $isActive ? 'Hoạt động' : 'Đã hủy'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if($isActive): ?>
                                        <a href="view_contract.php?contract_code=<?php echo $contract_code; ?>&cancel_appendix=<?php echo $appendix['ContractAppendixId']; ?>" 
                                           class="action-icon" 
                                           onclick="return confirm('Bạn có chắc muốn hủy phụ lục này không?');"
                                           title="Hủy phụ lục">
                                            <i class="fas fa-times-circle text-danger"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if(mysqli_num_rows($appendix_query) == 0): ?>
                                <tr>
                                    <td colspan="7" class="text-center">Không có phụ lục hợp đồng</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <div class="btn-container">
                        <a href="contract_management.php" class="btn btn-secondary">Quay lại</a>
                    </div>
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
        });
    </script>
</body>
</html>