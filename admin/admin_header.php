<?php
if(!isset($_SESSION)) {
    session_start();
}

// Include database connection
require_once __DIR__ . '/../database/DBController.php';

// Kiểm tra xem user đã đăng nhập chưa
if(!isset($_SESSION['admin_name'])) {
    header('location: ../index.php');
    exit();
}

// Xử lý đổi mật khẩu qua AJAX
if(isset($_POST['action']) && $_POST['action'] == 'change_password') {
    $response = array();
    
    $email = $_SESSION['admin_email'];
    $current_password = md5($_POST['current_password']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Kiểm tra mật khẩu hiện tại
    $check_query = "SELECT * FROM users WHERE email = '$email' AND password = '$current_password'";
    $result = mysqli_query($conn, $check_query);
    
    if(mysqli_num_rows($result) == 0) {
        $response['status'] = 'error';
        $response['message'] = 'Mật khẩu hiện tại không đúng';
    } 
    else if($new_password != $confirm_password) {
        $response['status'] = 'error';
        $response['message'] = 'Mật khẩu mới không khớp';
    } 
    else if(strlen($new_password) < 6) {
        $response['status'] = 'error';
        $response['message'] = 'Mật khẩu mới phải có ít nhất 6 ký tự';
    } 
    else {
        // Cập nhật mật khẩu mới
        $new_password_hash = md5($new_password);
        $update_query = "UPDATE users SET password = '$new_password_hash' WHERE email = '$email'";
        
        if(mysqli_query($conn, $update_query)) {
            $response['status'] = 'success';
            $response['message'] = 'Đổi mật khẩu thành công! Vui lòng đăng nhập lại.';
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Có lỗi xảy ra khi cập nhật mật khẩu';
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
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

    .adm-menu-button {
        display: block;
        width: 100%;
        padding: 8px 16px;
        text-align: left;
        border: none;
        background: none;
        color: #2F4858;
        font-size: 14px;
        cursor: pointer;
    }

    .adm-menu-button:hover {
        background-color: #f5f5f5;
    }

    .adm-dropdown-menu i {
        margin-right: 8px;
        width: 16px;
    }

    #changePasswordModal .input-group-text {
        border-right: 0;
    }

    #changePasswordModal .form-control {
        border-left: 0;
    }

    #changePasswordModal .form-control:focus {
        box-shadow: none;
        border-color: #ced4da;
    }

    #changePasswordModal .input-group:focus-within {
        box-shadow: 0 0 0 0.25rem rgba(40, 167, 69, 0.25);
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
            <a style="text-decoration: none;" href="/webquanlytoanha/admin/change-password.php" class="adm-menu-button">
                <i class="fas fa-key"></i> Đổi mật khẩu
            </a>
            <a href="/webquanlytoanha/logout.php" class="adm-logout-button">
                <i class="fas fa-sign-out-alt"></i> Đăng xuất
            </a>
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

function showChangePasswordModal() {
    // Đóng dropdown menu
    document.getElementById('admDropdownMenu').classList.remove('show');
    
    // Reset form và thông báo
    document.getElementById('changePasswordForm').reset();
    document.getElementById('passwordChangeMessage').style.display = 'none';
    
    // Hiển thị modal
    var modal = new bootstrap.Modal(document.getElementById('changePasswordModal'));
    modal.show();
}

async function changePassword() {
    const currentPassword = document.getElementById('currentPassword').value;
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;

    // Validate form
    if (!currentPassword || !newPassword || !confirmPassword) {
        showMessage('Vui lòng điền đầy đủ thông tin', 'danger');
        return;
    }

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                'action': 'change_password',
                'current_password': currentPassword,
                'new_password': newPassword,
                'confirm_password': confirmPassword
            })
        });

        const result = await response.json();
        
        if (result.status === 'success') {
            showMessage(result.message, 'success');
            setTimeout(() => {
                window.location.href = '/webquanlytoanha/logout.php';
            }, 2000);
        } else {
            showMessage(result.message, 'danger');
        }
    } catch (error) {
        showMessage('Có lỗi xảy ra, vui lòng thử lại', 'danger');
        console.error(error);
    }
}

function showMessage(message, type) {
    const messageDiv = document.getElementById('passwordChangeMessage');
    messageDiv.className = `alert alert-${type}`;
    messageDiv.textContent = message;
    messageDiv.style.display = 'block';
}
</script>

<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changePasswordModalLabel">Đổi mật khẩu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="passwordChangeMessage" class="alert" style="display: none;"></div>
                <form id="changePasswordForm">
                    <div class="mb-3">
                        <label for="currentPassword" class="form-label">Mật khẩu hiện tại</label>
                        <div class="input-group">
                            <span class="input-group-text bg-transparent">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" class="form-control" id="currentPassword" name="current_password" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="newPassword" class="form-label">Mật khẩu mới</label>
                        <div class="input-group">
                            <span class="input-group-text bg-transparent">
                                <i class="fas fa-key"></i>
                            </span>
                            <input type="password" class="form-control" id="newPassword" name="new_password" required>
                        </div>
                        <div class="form-text">Mật khẩu phải có ít nhất 6 ký tự</div>
                    </div>
                    <div class="mb-3">
                        <label for="confirmPassword" class="form-label">Xác nhận mật khẩu mới</label>
                        <div class="input-group">
                            <span class="input-group-text bg-transparent">
                                <i class="fas fa-key"></i>
                            </span>
                            <input type="password" class="form-control" id="confirmPassword" name="confirm_password" required>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-success" onclick="changePassword()">Đổi mật khẩu</button>
            </div>
        </div>
    </div>
</div>
