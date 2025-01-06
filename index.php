<?php
ob_start();

session_start();
$user_id = @$_SESSION['user_id'];
// include header.php file
include './database/DBController.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Lấy dữ liệu từ form
    $mess = mysqli_real_escape_string($conn, $_POST['message']);
    
    // Thêm dữ liệu vào bảng `message`
    $insert_query = "INSERT INTO `message` (user_id, message) VALUES ('$user_id', '$mess')";
    if (mysqli_query($conn, $insert_query)) {
        $message[] = 'Tin nhắn đã được gửi!';
    } else {
        $message[] = 'Lỗi khi gửi tin nhắn.';
    }
}

include('header.php');
?>
<?php

/*  include banner area  */
include('Template/_banner-area.php');
/*  include banner area  */

/*  include top sale section */
include('Template/_top-sale.php');
/*  include top sale section */

/*  include special price section  */
include('Template/_special-price.php');
/*  include special price section  */

/*  include banner ads  */
include('Template/_banner-ads.php');
/*  include banner ads  */

/*  include new phones section  */
include('Template/_new-phones.php');
/*  include new phones section  */

/*  include blog area  */
include('Template/_blogs.php');
/*  include blog area  */

?>
<style>
    #chat-icon:hover {
        background: #0056b3;
    }

    #chat-form label {
        font-weight: bold;
    }
</style>
<!-- Nút chăm sóc khách hàng -->
<div id="chat-icon" style="position: fixed; bottom: 20px; right: 20px; background: #007bff; color: white; width: 60px; height: 60px; border-radius: 50%; display: flex; justify-content: center; align-items: center; cursor: pointer; z-index: 1000;">
    <i class="fas fa-comment-dots" style="font-size: 24px;"></i>
</div>

<!-- Form nhập tin nhắn -->
<div id="chat-form" style="position: fixed; bottom: 90px; right: 20px; background: white; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); width: 300px; border-radius: 8px; padding: 20px; display: none; z-index: 1000;">
    <form id="messageForm" method="post" action="">
        <h1 class="text-center">CSKH</h1>
        <div class="form-group mb-3">
            <label for="message" class="form-label">Tin nhắn:</label>
            <textarea class="form-control" id="message" name="message" rows="3" placeholder="Nhập tin nhắn của bạn..." required></textarea>
        </div>
        <button type="submit" class="btn btn-primary w-100">Gửi</button>
    </form>
</div>

<script>
    document.getElementById('chat-icon').addEventListener('click', function () {
        const chatForm = document.getElementById('chat-form');
        chatForm.style.display = chatForm.style.display === 'none' ? 'block' : 'none';
    });
</script>

<?php
// include footer.php file
include('footer.php');
?>