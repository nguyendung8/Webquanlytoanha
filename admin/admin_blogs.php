<?php
include '../database/DBController.php';


session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location:login.php');
    exit();
}

// Thêm sự kiện mới
if (isset($_POST['add_event'])) {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $quantity = mysqli_real_escape_string($conn, $_POST['quantity']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $location = mysqli_real_escape_string($conn, $_POST['location']);
    $start_time = mysqli_real_escape_string($conn, $_POST['start_time']);
    $end_time = mysqli_real_escape_string($conn, $_POST['end_time']);
    $category_id = $_POST['category_id'];

    // Upload hình ảnh thumbnail
    $thumbnail_name = $_FILES['thumbnail']['name'];
    $thumbnail_tmp_name = $_FILES['thumbnail']['tmp_name'];
    $thumbnail_folder = 'uploaded_img/' . $thumbnail_name;

    if (move_uploaded_file($thumbnail_tmp_name, $thumbnail_folder)) {
        $insert_event_query = mysqli_query($conn, "INSERT INTO `events` (title, quantity,  thumbnail, description, location, start_time, end_time, created_by, category_id) 
        VALUES ('$title', '$quantity', '$thumbnail_name', '$description', '$location', '$start_time', '$end_time', '$admin_id', '$category_id')") or die('Query failed');

        if ($insert_event_query) {
            $message[] = 'Thêm sự kiện thành công!';
        } else {
            $message[] = 'Thêm sự kiện thất bại!';
        }
    } else {
        $message[] = 'Lỗi khi tải ảnh!';
    }
}

// Xóa sự kiện
if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    $delete_image_query = mysqli_query($conn, "SELECT thumbnail FROM `events` WHERE event_id = '$delete_id'") or die('Query failed');
    $fetch_image = mysqli_fetch_assoc($delete_image_query);
    unlink('uploaded_img/' . $fetch_image['thumbnail']);

    $delete_query = mysqli_query($conn, "DELETE FROM `events` WHERE event_id = '$delete_id'") or die('Query failed');

    if ($delete_query) {
        $message[] = 'Xóa sự kiện thành công!';
    } else {
        $message[] = 'Xóa sự kiện thất bại!';
    }
    header('location:admin_events.php');
    exit();
}

// Cập nhật sự kiện
if (isset($_POST['update_event'])) {
    $update_id = $_POST['update_id'];
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $location = mysqli_real_escape_string($conn, $_POST['location']);
    $start_time = mysqli_real_escape_string($conn, $_POST['start_time']);
    $end_time = mysqli_real_escape_string($conn, $_POST['end_time']);

    $update_query = "UPDATE `events` SET title = '$title', description = '$description', location = '$location', start_time = '$start_time', end_time = '$end_time'";

    if (!empty($_FILES['thumbnail']['name'])) {
        $thumbnail_name = $_FILES['thumbnail']['name'];
        $thumbnail_tmp_name = $_FILES['thumbnail']['tmp_name'];
        $thumbnail_folder = 'uploaded_img/' . $thumbnail_name;

        move_uploaded_file($thumbnail_tmp_name, $thumbnail_folder);
        $update_query .= ", thumbnail = '$thumbnail_name'";
    }

    $update_query .= " WHERE event_id = '$update_id'";
    $update_result = mysqli_query($conn, $update_query) or die('Query failed');

    if ($update_result) {
        $message[] = 'Cập nhật sự kiện thành công!';
    } else {
        $message[] = 'Cập nhật sự kiện thất bại!';
    }
    header('location:admin_events.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý sự kiện</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="css/admin_style.css">
    <style>
        th, td {
            text-align: center;
            font-size: 18px;
        }
        label {
            float: left !important;
        }
    </style>
</head>
<body>

<?php include 'admin_header.php'; ?>

<section class="add-products">
    <h1 class="title">Quản lý sự kiện</h1>

    <form action="" method="post" enctype="multipart/form-data">
        <h3>Thêm sự kiện mới</h3>
        <input type="text" name="title" class="box" placeholder="Tiêu đề sự kiện" required>
        <select name="category_id" class="box" required>
            <?php
            $categories = mysqli_query($conn, "SELECT * FROM `categories`") or die('Query failed');
            while ($category = mysqli_fetch_assoc($categories)) {
                echo "<option value='{$category['category_id']}'>{$category['name']}</option>";
            }
            ?>
        </select>
        <input type="text" name="quantity" class="box" placeholder="Số lượng vé" required>
        <textarea name="description" class="box" placeholder="Mô tả sự kiện" rows="5" required></textarea>
        <input type="text" name="location" class="box" placeholder="Địa điểm sự kiện" required>
        <label>Thời gian bắt đầu:</label>
        <input type="datetime-local" name="start_time" class="box" required>
        <label>Thời gian kết thúc:</label>
        <input type="datetime-local" name="end_time" class="box" required>
        <input type="file" name="thumbnail" class="box" accept="image/*" required>
        <input type="submit" value="Thêm sự kiện" name="add_event" class="new-btn btn-primary">
    </form>
</section>

<section class="show-events">
    <div class="container">
        <table class="table table-striped table-bordered">
        <thead>
                <tr>
                    <th>ID</th>
                    <th>Tiêu đề</th>
                    <th>Danh mục</th>
                    <th>Số lượng vé</th>
                    <th>Địa điểm</th>
                    <th>Bắt đầu</th>
                    <th>Kết thúc</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $select_events = mysqli_query($conn, "SELECT e.*, c.name AS category_name FROM `events` e 
                    LEFT JOIN `categories` c ON e.category_id = c.category_id 
                    ORDER BY e.created_at DESC") or die('Query failed');
                if (mysqli_num_rows($select_events) > 0) {
                    while ($event = mysqli_fetch_assoc($select_events)) {
                ?>
                        <tr>
                            <td><?php echo $event['event_id']; ?></td>
                            <td><?php echo $event['title']; ?></td>
                            <td><?php echo $event['category_name'] ?? 'Không có'; ?></td>
                            <td><?php echo $event['quantity']; ?></td>
                            <td><?php echo $event['location']; ?></td>
                            <td><?php echo $event['start_time']; ?></td>
                            <td><?php echo $event['end_time']; ?></td>
                            <td>
                                <a href="admin_events.php?edit=<?php echo $event['event_id']; ?>" class="new-btn btn-warning btn-sm">Sửa</a>
                                <a href="admin_events.php?delete=<?php echo $event['event_id']; ?>" class="new-btn btn-danger btn-sm" onclick="return confirm('Bạn có chắc chắn muốn xóa sự kiện này?');">Xóa</a>
                            </td>
                        </tr>
                <?php
                    }
                } else {
                    echo '<tr><td colspan="8" class="text-center">Chưa có sự kiện nào.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
</section>

<?php if (isset($_GET['edit'])): 
    $edit_id = $_GET['edit'];
    $edit_query = mysqli_query($conn, "SELECT * FROM `events` WHERE event_id = '$edit_id'") or die('Query failed');
    if (mysqli_num_rows($edit_query) > 0):
        $fetch_edit = mysqli_fetch_assoc($edit_query);
?>
<!-- Modal Update -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="post" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Cập nhật sự kiện</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="update_id" value="<?php echo $fetch_edit['event_id']; ?>">
                    <div class="mb-3">
                        <label for="title" class="form-label">Tiêu đề</label>
                        <input type="text" name="title" id="title" class="form-control" value="<?php echo $fetch_edit['title']; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Mô tả</label>
                        <textarea name="description" id="description" class="form-control" rows="4" required><?php echo $fetch_edit['description']; ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="location" class="form-label">Địa điểm</label>
                        <input type="text" name="location" id="location" class="form-control" value="<?php echo $fetch_edit['location']; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="start_time" class="form-label">Thời gian bắt đầu</label>
                        <input type="datetime-local" name="start_time" id="start_time" class="form-control" value="<?php echo date('Y-m-d\TH:i', strtotime($fetch_edit['start_time'])); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="end_time" class="form-label">Thời gian kết thúc</label>
                        <input type="datetime-local" name="end_time" id="end_time" class="form-control" value="<?php echo date('Y-m-d\TH:i', strtotime($fetch_edit['end_time'])); ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="new-btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" name="update_event" class="new-btn btn-primary">Cập nhật</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    var editModal = new bootstrap.Modal(document.getElementById('editModal'));
    editModal.show();
</script>
<?php endif; endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
