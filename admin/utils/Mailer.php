<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Sử dụng __DIR__ để xác định đường dẫn tuyệt đối
$baseDir = dirname(dirname(__DIR__)); // Lấy thư mục gốc của dự án
require_once $baseDir . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once $baseDir . '/vendor/phpmailer/phpmailer/src/SMTP.php';
require_once $baseDir . '/vendor/phpmailer/phpmailer/src/Exception.php';

class Mailer {
    private $mail;
    private $projectPhone = "0523629228";

    public function __construct() {
        $this->mail = new PHPMailer(true);
        
        // Cấu hình email server
        $this->mail->isSMTP();
        $this->mail->Host = 'smtp.gmail.com';
        $this->mail->SMTPAuth = true;
        $this->mail->Username = 'dungli1221@gmail.com'; // Thay bằng email thực tế
        $this->mail->Password = 'tlqxcbaaeyfgfsul'; // Thay bằng app password từ Google
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port = 587;
        $this->mail->CharSet = 'UTF-8';
    }

    public function sendNewAccountEmail($name, $email, $password) {
        try {
            $this->mail->setFrom('dungli1221@gmail.com', 'Ban Quản Lý Tòa Nhà');
            $this->mail->addAddress($email, $name);
            $this->mail->isHTML(true);
            
            $loginUrl = "http://localhost/webquanlytoanha/"; // Thay bằng URL thực tế
            
            $this->mail->Subject = 'Thông tin tài khoản mới - Ban Quản Lý Tòa Nhà';
            $this->mail->Body = $this->getEmailTemplate($name, $email, $password, $loginUrl);
            
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }

    private function getEmailTemplate($name, $email, $password, $loginUrl) {
        return "
        <p>Xin chào {$name},</p>

        <p>Mật khẩu của tài khoản {$email} đã được đặt lại bởi Ban Quản Lý.</p>

        <p>📌 <strong>Mật khẩu mới của bạn:</strong> {$password}<br>
        📌 <strong>Vui lòng đăng nhập tại:</strong> <a href='{$loginUrl}'>{$loginUrl}</a></p>

        <p>Chúng tôi khuyến nghị bạn <strong>đổi lại mật khẩu ngay sau khi đăng nhập</strong> để đảm bảo bảo mật.<br>
        Nếu bạn không yêu cầu đặt lại mật khẩu, vui lòng liên hệ Ban Quản Lý ngay lập tức qua {$this->projectPhone}</p>

        <p>Cảm ơn bạn!<br>
        <strong>Ban Quản Lý Tòa Nhà</strong><br>
        📞 Liên hệ hỗ trợ: {$this->projectPhone}</p>
        ";
    }

    // Thêm hàm gửi email thông báo bảng kê
    public function sendInvoiceEmail($email, $name, $subject, $content) {
        try {
            $this->mail->clearAddresses(); // Xóa các địa chỉ trước đó
            $this->mail->addAddress($email, $name);
            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body = $content;
            
            return $this->mail->send();
        } catch (Exception $e) {
            return false;
        }
    }
}
