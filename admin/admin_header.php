<?php
if(!isset($_SESSION)) {
    session_start();
}

// Kiểm tra xem user đã đăng nhập chưa
if(!isset($_SESSION['admin_name'])) {
    header('location: ../index.php');
    exit();
}
?>


<style>
.adm-header {
    background-color: #fff;
    padding: 10px 20px;
    display: flex;
    justify-content: flex-end;
    align-items: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    height: 60px;
    z-index: 1000;
}

.adm-logo-section {
    display: flex;
    align-items: center;
    gap: 10px;
}

.adm-logo {
    width: 24px;
    height: 24px;
}

.adm-user-section {
    position: relative;
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    padding: 8px;
    border-radius: 4px;
    margin-right: 10px;
}

.adm-user-section:hover {
    background-color: #f5f5f5;
}

.adm-user-name {
    color: #2F4858;
    font-size: 14px;
    font-weight: 500;
}

.adm-user-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background-color: #A7C1B5;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 500;
}

.adm-dropdown-menu {
    position: absolute;
    top: calc(100% + 5px);
    right: 0;
    background-color: white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    border-radius: 4px;
    padding: 8px 0;
    min-width: 150px;
    display: none;
    z-index: 1001;
}

.adm-dropdown-menu.show {
    display: block;
}

.adm-logout-button {
    display: block;
    width: 100%;
    padding: 8px 16px;
    text-align: left;
    border: none;
    background: none;
    color: #2F4858;
    font-size: 14px;
    cursor: pointer;
    text-decoration: none;
}

.adm-logout-button:hover {
    background-color: #f5f5f5;
}
</style>

<header class="adm-header">
    <div class="adm-user-section" id="admUserSection">
        <div class="adm-user-avatar">
            <?php 
            $name = $_SESSION['admin_name'] ?? 'User';
            echo strtoupper(substr($name, 0, 1)); 
            ?>
        </div>
        <span class="adm-user-name"><?php echo $name; ?></span>
        
        <div class="adm-dropdown-menu" id="admDropdownMenu">
            <a href="../logout.php" class="adm-logout-button">Đăng xuất</a>
        </div>
    </div>
</header>

<script>
document.getElementById('admUserSection').addEventListener('click', function() {
    document.getElementById('admDropdownMenu').classList.toggle('show');
});

// Đóng dropdown khi click ra ngoài
window.addEventListener('click', function(event) {
    if (!event.target.closest('.adm-user-section')) {
        document.getElementById('admDropdownMenu').classList.remove('show');
    }
});
</script>
