<?php
include '../../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location: /webquanlytoanha/index.php');
    exit();
}

// Lấy danh sách dự án của nhân viên đang đăng nhập
$projects_query = mysqli_query($conn, "
    SELECT DISTINCT p.* 
    FROM Projects p 
    INNER JOIN StaffProjects sp ON p.ProjectID = sp.ProjectId 
    INNER JOIN Staffs s ON sp.StaffId = s.ID 
    INNER JOIN users u ON s.Email = u.Email 
    WHERE u.UserId = '$admin_id' AND p.Status = 'active'
    ORDER BY p.Name
") or die('Query failed: ' . mysqli_error($conn));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo cáo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .page-header {
            background-color: #f5f5f5;
            padding: 15px 20px;
            margin-bottom: 20px;
        }
        .breadcrumb {
            margin: 0;
            background: none;
            padding: 0;
        }
        .search-container {
            margin-bottom: 20px;
        }
        .report-section {
            background: #fff;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .report-title {
            background: #e9ecef;
            padding: 10px 15px;
            margin: -20px -20px 20px -20px;
            border-radius: 5px 5px 0 0;
            font-weight: 600;
            color: #476a52;
        }
        .report-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .report-item:last-child {
            border-bottom: none;
        }
        .report-item a {
            text-decoration: none;
            color: #333;
        }
        .report-item:hover {
            background: #f8f9fa;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        .star-icon {
            color: #ffc107;
        }
        .star-icon.empty {
            color: #e0e0e0;
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <?php include '../admin_navbar.php'; ?>
        <div class="flex-grow-1">
            <?php include '../admin_header.php'; ?>
            
            <div class="container-fluid p-4">
                <!-- Page Header -->
                <div class="page-header">
                    <h2 class="text-uppercase fw-bold" style="color: #476a52;">Báo cáo</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a style="text-decoration: none; color: #476a52;" href="/webquanlytoanha/admin/dashboard.php">Trang chủ</a></li>
                            <li class="breadcrumb-item"><a style="text-decoration: none; color: #476a52;" href="/webquanlytoanha/admin/payment/payment_reports.php">Quản lý thu phí</a></li>
                            <li class="breadcrumb-item active">Báo cáo</li>
                        </ol>
                    </nav>
                </div>

                <!-- Financial Reports Section -->
                <div class="report-section">
                    <div class="report-title">Báo cáo tài chính</div>
                    
                    <div class="report-item">
                        <a href="financial_reports/cash_report.php">Báo cáo số quỹ tiền mặt</a>
                        <div class="action-buttons">
                            <a href="payment_cash.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-circle-arrow-right"></i>
                            </a>
                        </div>
                    </div>

                    <div class="report-item">
                        <a href="financial_reports/bank_report.php">Báo cáo số quỹ ngân hàng</a>
                        <div class="action-buttons">
                            <a href="payment_transfer.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-circle-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Debt Reports Section -->
                <div class="report-section">
                    <div class="report-title">Báo cáo công nợ</div>
                    
                    <div class="report-item">
                        <a href="debt_reports/payment_report.php">Báo cáo thu - nợ</a>
                        <div class="action-buttons">
                            <a href="#" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-circle-arrow-right"></i>
                            </a>
                        </div>
                    </div>

                    <div class="report-item">
                        <a href="debt_reports/apartment_debt.php">Báo cáo tổng hợp công nợ theo căn hộ</a>
                        <div class="action-buttons">
                            <a href="#" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-circle-arrow-right"></i>
                            </a>
                        </div>
                    </div>

                    <div class="report-item">
                        <a href="debt_reports/service_debt.php">Báo cáo chi tiết công nợ theo dịch vụ</a>
                        <div class="action-buttons">
                            <a href="#" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-circle-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Xử lý toggle star icon
        document.querySelectorAll('.star-icon').forEach(icon => {
            icon.addEventListener('click', function() {
                this.classList.toggle('fas');
                this.classList.toggle('far');
                this.classList.toggle('empty');
            });
        });
    </script>
</body>
</html>
