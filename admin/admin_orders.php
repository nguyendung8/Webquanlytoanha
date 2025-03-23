<?php
include '../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location:../login.php');
    exit();
}

// Xử lý cập nhật trạng thái đơn đặt sân
if (isset($_POST['update_status'])) {
    $booking_id = $_POST['booking_id'];
    $new_status = $_POST['status'];
    $field_id = $_POST['field_id'];

    if ($new_status !== "") {
        // Nếu hủy đơn, cập nhật trạng thái sân thành "Đang trống"
        if ($new_status == 'Đã hủy') {
            mysqli_query($conn, "UPDATE football_fields SET status = 'Đang trống' WHERE id = '$field_id'");
        }
        // Nếu xác nhận đơn, cập nhật trạng thái sân thành "Đã đặt"
        else if ($new_status == 'Đã xác nhận') {
            mysqli_query($conn, "UPDATE football_fields SET status = 'Đã đặt' WHERE id = '$field_id'");
        }

        $update_query = mysqli_query($conn, "UPDATE bookings SET status = '$new_status' WHERE id = '$booking_id'");

        if ($update_query) {
            $message[] = 'Cập nhật trạng thái đơn đặt sân thành công!';
        } else {
            $message[] = 'Cập nhật trạng thái đơn đặt sân thất bại!';
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Đơn đặt sân</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/admin_style.css">
</head>
<body>
    <div class="d-flex">
        <?php include 'admin_navbar.php'; ?>
        <div class="manage-container">
            <?php
            if (isset($message)) {
                foreach ($message as $msg) {
                    echo '
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <span>' . $msg . '</span>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
                }
            }
            ?>
            <div style="background-color: #28a745" class="text-white text-center py-2 mb-4 shadow">
                <h1 class="mb-0">Quản lý Đơn đặt sân</h1>
            </div>

            <div class="container">
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tên sân</th>
                            <th>Khách hàng</th>
                            <th>Ngày đặt</th>
                            <th>Thời gian</th>
                            <th>Dịch vụ thêm</th>
                            <th>Tổng tiền</th>
                            <th>Trạng thái</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $select_bookings = mysqli_query($conn, "
                            SELECT b.*, f.name as field_name, f.rental_price, u.username as user_name, u.email, u.phone 
                            FROM bookings b 
                            JOIN football_fields f ON b.field_id = f.id 
                            JOIN users u ON b.user_id = u.user_id 
                            ORDER BY b.booking_date DESC, b.start_time DESC
                        ") or die('Query failed');

                        if (mysqli_num_rows($select_bookings) > 0) {
                            while ($booking = mysqli_fetch_assoc($select_bookings)) {
                        ?>
                        <tr>
                            <td><?php echo $booking['id']; ?></td>
                            <td><?php echo $booking['field_name']; ?></td>
                            <td>
                                <?php echo $booking['user_name']; ?><br>
                                <small><?php echo $booking['phone']; ?></small>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($booking['booking_date'])); ?></td>
                            <td>
                                <?php 
                                echo $booking['start_time'] . ' - ' . $booking['end_time']; 
                                echo '<br><small>(' . $booking['duration'] . ' giờ)</small>';
                                ?>
                            </td>
                            <td>
                                <?php
                                $services = [];
                                if ($booking['rent_ball']) $services[] = 'Thuê bóng';
                                if ($booking['rent_uniform']) $services[] = 'Thuê áo';
                                echo $services ? implode(', ', $services) : 'Không có';
                                ?>
                            </td>
                            <td><?php echo number_format($booking['total_price'], 0, ',', '.'); ?> đ</td>
                            <td>
                                <?php if ($booking['status'] == 'Đã hủy'): ?>
                                    <span class="badge bg-danger">Đã hủy</span>
                                <?php else: ?>
                                <form action="" method="POST">
                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                    <input type="hidden" name="field_id" value="<?php echo $booking['field_id']; ?>">
                                    <select name="status" class="form-select form-select-sm mb-2">
                                        <option value="Chờ xác nhận" <?php echo ($booking['status'] == 'Chờ xác nhận') ? 'selected' : ''; ?>>
                                            Chờ xác nhận
                                        </option>
                                        <option value="Đã xác nhận" <?php echo ($booking['status'] == 'Đã xác nhận') ? 'selected' : ''; ?>>
                                            Đã xác nhận
                                        </option>
                                        <option value="Đã hủy" <?php echo ($booking['status'] == 'Đã hủy') ? 'selected' : ''; ?>>
                                            Hủy đơn
                                        </option>
                                    </select>
                                    <button type="submit" name="update_status" class="btn btn-primary btn-sm w-100">
                                        Cập nhật
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" 
                                        data-bs-target="#bookingModal<?php echo $booking['id']; ?>">
                                    Chi tiết
                                </button>

                                <!-- Modal Chi tiết -->
                                <div class="modal fade" id="bookingModal<?php echo $booking['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">
                                                    Chi tiết đơn đặt sân #<?php echo $booking['id']; ?>
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <h6>Thông tin khách hàng</h6>
                                                        <p><strong>Tên:</strong> <?php echo $booking['user_name']; ?></p>
                                                        <p><strong>Email:</strong> <?php echo $booking['email']; ?></p>
                                                        <p><strong>SĐT:</strong> <?php echo $booking['phone']; ?></p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <h6>Thông tin đặt sân</h6>
                                                        <p><strong>Sân:</strong> <?php echo $booking['field_name']; ?></p>
                                                        <p><strong>Ngày:</strong> <?php echo date('d/m/Y', strtotime($booking['booking_date'])); ?></p>
                                                        <p><strong>Thời gian:</strong> <?php echo $booking['start_time'] . ' - ' . $booking['end_time']; ?></p>
                                                    </div>
                                                </div>

                                                <div class="price-details">
                                                    <h6>Chi tiết giá</h6>
                                                    <table class="table table-bordered">
                                                        <tr>
                                                            <td>Tiền sân (<?php echo $booking['duration']; ?> giờ)</td>
                                                            <td><?php echo number_format($booking['field_price'], 0, ',', '.'); ?> đ</td>
                                                        </tr>
                                                        <?php if ($booking['rent_ball']): ?>
                                                        <tr>
                                                            <td>Thuê bóng</td>
                                                            <td>100.000 đ</td>
                                                        </tr>
                                                        <?php endif; ?>
                                                        <?php if ($booking['rent_uniform']): ?>
                                                        <tr>
                                                            <td>Thuê áo</td>
                                                            <td>100.000 đ</td>
                                                        </tr>
                                                        <?php endif; ?>
                                                        <tr class="table-success">
                                                            <th>Tổng cộng</th>
                                                            <th><?php echo number_format($booking['total_price'], 0, ',', '.'); ?> đ</th>
                                                        </tr>
                                                    </table>
                                                </div>

                                                <?php if ($booking['note']): ?>
                                                <div class="booking-note">
                                                    <h6>Ghi chú</h6>
                                                    <p class="text-muted"><?php echo $booking['note']; ?></p>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php
                            }
                        } else {
                            echo '<tr><td colspan="9" class="text-center">Chưa có đơn đặt sân nào.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>