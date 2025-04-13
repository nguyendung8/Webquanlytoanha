<?php
if (!isset($_SESSION['admin_id'])) {
    header('Location: /webquanlytoanha/admin/login.php');
    exit();
}

$user_role = $_SESSION['admin_role'];

// Hiển thị menu dựa trên role
function showMenuByRole($role) {
    switch($role) {
        case 'Quản trị hệ thống':
            // Hiển thị tất cả menu
            return [
                'dashboard' => true,
                'account' => true,
                'data_info' => true,
                'apartment' => true,
                'service' => true,
                'payment' => true,
                'resident_interaction' => true,
                'service_request' => true,
                'report' => true
            ];
            
        case 'Cư dân':
            // Chỉ hiển thị các menu cho Cư dân
            return [
                'dashboard' => true,
                'apartment' => true,
                'service_request' => true
            ];
            
        case 'Trưởng BQL':
        case 'Phó BQL':
            return [
                'dashboard' => true,
                'data_info' => true,
                'apartment' => true,
                'service' => true,
                'payment' => true,
                'resident_interaction' => true,
                'service_request' => true,
                'report' => true
            ];
            
        case 'Kế toán HO':
            return [
                'dashboard' => true,
                'apartment' => true,
                'service' => true,
                'payment' => true,
                'service_request' => true
            ];
            
        case 'Kế toán ban':
            return [
                'dashboard' => true,
                'apartment' => true,
                'service' => true,
                'payment' => true,
                'service_request' => true
            ];
            
        case 'Lễ tân':
            return [
                'dashboard' => true,
                'apartment' => true,
                'service' => true,
                'service_request' => true
            ];
            
        case 'Nhân viên kỹ thuật':
            return [
                'dashboard' => true,
                'payment' => ['utility_reading' => true]
            ];
            
        default:
            return ['dashboard' => true];
    }
}

$allowed_menus = showMenuByRole($user_role);
?>
<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Admin Navbar</title>
   <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   <style>
      .sidebar {
         position: relative;
         min-width: 276px;
         min-height: 100vh;
         background-color: white;
         color: #333;
         padding-top: 20px;
         box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
      }

      .sidebar .logo {
         text-align: center;
         margin-bottom: 30px;
      }

      .logo-icon {
         width: 60px;
         height: 60px;
         margin-bottom: 10px;
      }

      .sidebar a {
         display: flex;
         align-items: center;
         color: #666;
         padding: 12px 20px;
         text-decoration: none;
         transition: all 0.3s;
         margin: 2px 15px;
         border-radius: 8px;
         font-size: 15px !important;
      }

      .sidebar a:hover {
         background-color: rgba(74, 143, 102, 0.1);
         color: #4a8f66;
      }

      .sidebar a.active {
         background-color: #4a8f66;
         color: white;
      }

      .sidebar i {
         width: 24px;
         text-align: center;
         margin-right: 10px;
         font-size: 16px;
      }

      .sidebar .active-item {
         background-color: #4a8f66;
         color: white;
      }

      .logout-btn {
         margin-top: 20px;
         background-color: #4a8f66 !important;
         color: white !important;
         border: none;
      }
      
      /* Submenu styles */
      .has-submenu {
         position: relative;
         justify-content: space-between;
      }
      
      .submenu-icon {
         margin-left: auto;
         transition: transform 0.3s;
         width: auto !important;
      }
      
      .submenu {
         display: none;
         padding-left: 15px;
      }
      
      .submenu a {
         padding: 8px 15px 8px 35px;
         font-size: 14px;
         margin: 2px 0;
      }
      
      .submenu a i {
         font-size: 14px;
      }
      
      .submenu-open .submenu-icon {
         transform: rotate(180deg);
      }
      
      .submenu-open + .submenu {
         display: block;
      }
   </style>
</head>

<body>

   <div class="sidebar">
      <div class="logo">
         <img width="130px" src="/webquanlytoanha/assets/logo.png" alt="LOGO">
      </div>

      <!-- Trang chủ -->
      <?php if (isset($allowed_menus['dashboard'])): ?>
      <a href="/webquanlytoanha/admin/dashboard.php">
         <i class="fas fa-th-large"></i> Trang chủ
      </a>
      <?php endif; ?>

      <!-- Tài khoản phân quyền -->
      <?php if (isset($allowed_menus['account'])): ?>
      <a href="/webquanlytoanha/admin/account/acount.php">
         <i class="fas fa-user-shield"></i> Tài khoản phân quyền
      </a>
      <?php endif; ?>

      <!-- Thông tin dữ liệu -->
      <?php if (isset($allowed_menus['data_info'])): ?>
      <a href="javascript:void(0);" class="has-submenu" onclick="toggleSubmenu(this)">
         <i class="fas fa-database"></i> Thông tin dữ liệu
         <i style="font-size: 14px" class="fas fa-chevron-down submenu-icon"></i>
      </a>
      <div class="submenu">
         <a href="/webquanlytoanha/admin/data-info/companies.php">
            <i class="fas fa-building"></i> Thông tin công ty
         </a>
         <a href="/webquanlytoanha/admin/data-info/building_management.php">
            <i class="fas fa-home"></i> Quản lý tòa nhà
         </a>
         <a href="/webquanlytoanha/admin/data-info/utility_info.php">
            <i class="fas fa-info-circle"></i> Thông tin loại tiện ích
         </a>
         <a href="/webquanlytoanha/admin/data-info/department_management.php">
            <i class="fas fa-door-open"></i> Danh sách phòng ban
         </a>
         <a href="/webquanlytoanha/admin/data-info/company_employees.php">
            <i class="fas fa-users"></i> Nhân viên công ty
         </a>
      </div>
      <?php endif; ?>

      <!-- Căn hộ - cư dân -->
      <?php if (isset($allowed_menus['apartment'])): ?>
      <a href="javascript:void(0);" class="has-submenu" onclick="toggleSubmenu(this)">
         <i class="fas fa-building"></i> Căn hộ - cư dân
         <i style="font-size: 14px" class="fas fa-chevron-down submenu-icon"></i>
      </a>
      <div class="submenu">
         <a href="/webquanlytoanha/admin/apartment/apartment_management.php">
            <i class="fas fa-home"></i> Quản lý căn hộ
         </a>
         <a href="/webquanlytoanha/admin/apartment/resident_management.php">
            <i class="fas fa-users"></i> Quản lý cư dân
         </a>
         <a href="/webquanlytoanha/admin/apartment/contract_management.php">
            <i class="fas fa-file-contract"></i> Quản lý hợp đồng
         </a>
      </div>
      <?php endif; ?>

      <!-- <?php if (isset($allowed_menus['resident_interaction'])): ?>
      <a href="javascript:void(0);" class="has-submenu" onclick="toggleSubmenu(this)">
         <i class="fas fa-comments"></i> Tương tác cư dân
         <i style="font-size: 14px" class="fas fa-chevron-down submenu-icon"></i>
      </a>
      <div class="submenu">
         <a href="/webquanlytoanha/admin/resident_interaction/notifications.php">
            <i class="fas fa-bell"></i> Thông báo cư dân
         </a>
         <a href="/webquanlytoanha/admin/resident_interaction/handbook.php">
            <i class="fas fa-book"></i> Cẩm nang tòa nhà
         </a>
      </div>
      <?php endif; ?> -->
      
      <!-- <?php if (isset($allowed_menus['service_request'])): ?>
      <a href="#">
         <i class="fas fa-hand-paper"></i> Yêu cầu dịch vụ
      </a>
      <?php endif; ?> -->
      
      <?php if (isset($allowed_menus['service'])): ?>
      <a href="javascript:void(0);" class="has-submenu" onclick="toggleSubmenu(this)">
         <i class="fas fa-concierge-bell"></i> Dịch vụ - phương tiện
         <i style="font-size: 14px" class="fas fa-chevron-down submenu-icon"></i>
      </a>
      <div class="submenu">
         <a href="/webquanlytoanha/admin/service/service_list.php">
            <i class="fas fa-concierge-bell"></i> Dịch vụ tòa nhà
         </a>
         <a href="/webquanlytoanha/admin/service/vehicle_list.php">
            <i class="fas fa-car"></i> Quản lý phương tiện
         </a>
      </div>
      <?php endif; ?>
      
      <?php if (isset($allowed_menus['payment'])): ?>
      <a href="javascript:void(0);" class="has-submenu" onclick="toggleSubmenu(this)">
         <i class="fas fa-dollar-sign"></i> Quản lý thu phí
         <i style="font-size: 14px" class="fas fa-chevron-down submenu-icon"></i>
      </a>
      <div class="submenu">
         <a href="/webquanlytoanha/admin/payment/electric_reading.php">
            <i class="fas fa-tachometer-alt"></i> Chỉ số điện nước
         </a>
         <a href="/webquanlytoanha/admin/payment/bill_list.php">
            <i class="fas fa-list-alt"></i> Danh sách bảng kê
         </a>
         <a href="/webquanlytoanha/admin/payment/bill_approval.php">
            <i class="fas fa-file-alt"></i> Duyệt bảng kê
         </a>
         <a href="/webquanlytoanha/admin/payment/payment_receipt.php">
            <i class="fas fa-receipt"></i> Quản lý phiếu thu/chi
         </a>
         <!-- <a href="/webquanlytoanha/admin/payment/transaction_accounting.php">
            <i class="fas fa-calculator"></i> Hạch toán giao dịch
         </a> -->
         <a href="/webquanlytoanha/admin/payment/debt_management.php">
            <i class="fas fa-coins"></i> Quản lý tiền thừa
         </a>
         <a href="/webquanlytoanha/admin/payment/payment_reports.php">
            <i class="fas fa-chart-bar"></i> Báo cáo
         </a>
      </div>
      <?php endif; ?>
      
      <?php if (isset($allowed_menus['report'])): ?>
      <a href="javascript:void(0);" class="has-submenu" onclick="toggleSubmenu(this)">
         <i class="fas fa-chart-bar"></i> Báo cáo
         <i style="font-size: 14px" class="fas fa-chevron-down submenu-icon"></i>
      </a>
      <div class="submenu">
         <a href="/webquanlytoanha/admin/reports/summary_report.php">
            <i class="fas fa-file-alt"></i> Báo cáo tổng hợp
         </a>
         <a href="/webquanlytoanha/admin/reports/fluctuation_vehicle_report.php">
            <i class="fas fa-car"></i> Báo cáo biến động
         </a>
         <!-- <a href="/webquanlytoanha/admin/reports/allocation_report.php">
            <i class="fas fa-chart-pie"></i> Báo cáo phân bổ
         </a> -->
      </div>
      <?php endif; ?>
   </div>

   <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
   <script>
      // Lấy tất cả các link trong sidebar
      const sidebarLinks = document.querySelectorAll('.sidebar a:not(.has-submenu)');

      // Lấy trang hiện tại từ localStorage hoặc mặc định là trang đầu tiên
      const activePage = localStorage.getItem('activePage') || sidebarLinks[0].getAttribute('href');

      // Gán class 'active' cho link tương ứng với trang hiện tại
      sidebarLinks.forEach(link => {
         if (link.getAttribute('href') === activePage) {
            link.classList.add('active');
            
            // Nếu link thuộc submenu, mở submenu đó
            const parentSubmenu = link.closest('.submenu');
            if (parentSubmenu) {
               const parentToggle = parentSubmenu.previousElementSibling;
               parentToggle.classList.add('submenu-open');
               parentSubmenu.style.display = 'block';
            }
         }
      });

      // Thêm sự kiện click cho từng link
      sidebarLinks.forEach(link => {
         link.addEventListener('click', function(e) {
            // Không bao gồm các link submenu toggle
            if (!this.classList.contains('has-submenu')) {
            // Xóa class 'active' khỏi tất cả các link
            sidebarLinks.forEach(item => item.classList.remove('active'));

            // Thêm class 'active' vào link được click
            this.classList.add('active');

            // Lưu trang hiện tại vào localStorage
            localStorage.setItem('activePage', this.getAttribute('href'));
            }
         });
      });
      
      // Hàm để toggle submenu
      function toggleSubmenu(element) {
         element.classList.toggle('submenu-open');
         const submenu = element.nextElementSibling;
         if (submenu.style.display === 'block') {
            submenu.style.display = 'none';
         } else {
            submenu.style.display = 'block';
         }
      }
      
      // Kiểm tra xem submenu nào cần được mở khi tải trang
      document.addEventListener('DOMContentLoaded', function() {
         const currentPath = window.location.pathname;
         const submenuLinks = document.querySelectorAll('.submenu a');
         
         submenuLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (currentPath.includes(href)) {
               const submenu = link.closest('.submenu');
               const toggle = submenu.previousElementSibling;
               toggle.classList.add('submenu-open');
               submenu.style.display = 'block';
               link.classList.add('active');
            }
         });
      });

      document.addEventListener('DOMContentLoaded', function() {
         console.log('Current role:', '<?php echo $_SESSION["admin_role"]; ?>');
         // Log ra các menu permissions để debug
         console.log('Menu permissions:', <?php echo json_encode($allowed_menus); ?>);
      });
   </script>
</body>

</html>