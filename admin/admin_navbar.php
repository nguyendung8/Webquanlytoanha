<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Navbar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="./admin_style.css">
</head>
<body>

<div class="sidebar">
    <div class="logo">
        <img src="../assets/logo_flower.png" alt="Logo" width="80">
    </div>
    <a href="admin_products.php" class="active">Sản phẩm</a>
    <a href="admin_categories.php">Danh mục</a>
    <a href="admin_orders.php">Đơn hàng</a>
    <a href="admin_blogs.php">Blog</a>
    <a href="admin_accounts.php">Tài khoản</a>
    <a href="admin_statistical.php">Thống kê</a>
    <a href="../logout.php" class="btn btn-danger logout-btn">Đăng xuất</a>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Lấy tất cả các link trong sidebar
    const sidebarLinks = document.querySelectorAll('.sidebar a');

    // Thêm sự kiện click cho từng link
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function () {
            // Xóa class 'active' khỏi tất cả các link
            sidebarLinks.forEach(item => item.classList.remove('active'));

            // Thêm class 'active' vào link được click
            this.classList.add('active');
        });
    });
</script>
</body>
</html>
