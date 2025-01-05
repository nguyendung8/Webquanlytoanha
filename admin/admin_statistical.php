<?php
include '../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location:../login.php');
    exit();
}

// Tổng số người dùng
$total_users = mysqli_query($conn, "SELECT COUNT(user_id) AS total FROM `users`");
$total_users = mysqli_fetch_assoc($total_users)['total'];

// Tổng số sự kiện
$total_events = mysqli_query($conn, "SELECT COUNT(event_id) AS total FROM `events`");
$total_events = mysqli_fetch_assoc($total_events)['total'];

// Top 5 sự kiện có lượt đăng ký cao nhất
$top_events_query = "
    SELECT e.title, COUNT(er.event_id) AS registrations 
    FROM event_registrations er
    JOIN events e ON er.event_id = e.event_id
    GROUP BY er.event_id 
    ORDER BY registrations DESC 
    LIMIT 5";
$top_events_result = mysqli_query($conn, $top_events_query);

// Điểm đánh giá trung bình chung
$average_rating_query = "
    SELECT ROUND(AVG(rating), 2) AS avg_rating 
    FROM feedbacks 
    WHERE rating IS NOT NULL";
$average_rating = mysqli_query($conn, $average_rating_query);
$average_rating = mysqli_fetch_assoc($average_rating)['avg_rating'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý thống kê</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="css/admin_style.css">
    <style>
        th, td {
            text-align: center;
            font-size: 18px;
        }
        .card {
            margin-bottom: 20px;
        }
        .chart-container {
            width: 80%;
            margin: auto;
        }
        p {
            font-size: 16px;
        }
    </style>
</head>
<body>

<?php include 'admin_header.php'; ?>

<section class="container mt-5">
    <h1 class="title text-center">Thống kê quản trị viên</h1>
    <div class="row">
        <!-- Tổng số người dùng -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Tổng số người dùng</h5>
                    <p class="card-text"><?php echo $total_users; ?> người</p>
                </div>
            </div>
        </div>

        <!-- Tổng số sự kiện -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Tổng số sự kiện</h5>
                    <p class="card-text"><?php echo $total_events; ?> sự kiện</p>
                </div>
            </div>
        </div>

        <!-- Điểm đánh giá trung bình -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Điểm đánh giá trung bình</h5>
                    <p class="card-text"><?php echo $average_rating !== null ? $average_rating . " / 5" : "Chưa có đánh giá"; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Top 5 sự kiện có lượt đăng ký cao nhất -->
    <div class="mt-4">
        <h2 class="text-center">Top 5 Sự kiện có lượt đăng ký cao nhất</h2>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>STT</th>
                    <th>Tên sự kiện</th>
                    <th>Số lượng đăng ký</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $index = 1;
                while ($row = mysqli_fetch_assoc($top_events_result)): ?>
                    <tr>
                        <td><?php echo $index++; ?></td>
                        <td><?php echo $row['title']; ?></td>
                        <td><?php echo $row['registrations']; ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</section>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
