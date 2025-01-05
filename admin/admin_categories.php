<?php
include 'database/DBController.php';
session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location:login.php');
    exit();
}

// Thêm danh mục mới
if (isset($_POST['add_category'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);

    $insert_category_query = mysqli_query($conn, "INSERT INTO `categories` (name, description) 
    VALUES ('$name', '$description')") or die('Query failed');

    if ($insert_category_query) {
        $message[] = 'Thêm danh mục thành công!';
    } else {
        $message[] = 'Thêm danh mục thất bại!';
    }
}

// Xóa danh mục
if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    $delete_query = mysqli_query($conn, "DELETE FROM `categories` WHERE category_id = '$delete_id'") or die('Query failed');

    if ($delete_query) {
        $message[] = 'Xóa danh mục thành công!';
    } else {
        $message[] = 'Xóa danh mục thất bại!';
    }
    header('location:admin_categories.php');
    exit();
}

// Cập nhật danh mục
if (isset($_POST['update_category'])) {
    $update_id = $_POST['update_id'];
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);

    $update_query = mysqli_query($conn, "UPDATE `categories` SET name = '$name', description = '$description' 
    WHERE category_id = '$update_id'") or die('Query failed');

    if ($update_query) {
        $message[] = 'Cập nhật danh mục thành công!';
    } else {
        $message[] = 'Cập nhật danh mục thất bại!';
    }
    header('location:admin_categories.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý danh mục</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="css/admin_style.css">
</head>
<body>

<?php include 'admin_header.php'; ?>

<section class="add-products">
    <h1 class="title">Quản lý danh mục</h1>

    <form action="" method="post">
        <h3>Thêm danh mục mới</h3>
        <input type="text" name="name" class="box" placeholder="Tên danh mục" required>
        <textarea name="description" class="box" placeholder="Mô tả danh mục" rows="5"></textarea>
        <input type="submit" value="Thêm danh mục" name="add_category" class="new-btn btn-primary">
    </form>
</section>

<section class="show-categories">
    <div class="container">
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tên danh mục</th>
                    <th>Mô tả</th>
                    <th>Ngày tạo</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $select_categories = mysqli_query($conn, "SELECT * FROM `categories` ORDER BY created_at DESC") or die('Query failed');
                if (mysqli_num_rows($select_categories) > 0) {
                    while ($category = mysqli_fetch_assoc($select_categories)) {
                ?>
                        <tr>
                            <td><?php echo $category['category_id']; ?></td>
                            <td><?php echo $category['name']; ?></td>
                            <td><?php echo $category['description']; ?></td>
                            <td><?php echo $category['created_at']; ?></td>
                            <td>
                                <a href="admin_categories.php?edit=<?php echo $category['category_id']; ?>" class="new-btn btn-warning btn-sm">Sửa</a>
                                <a href="admin_categories.php?delete=<?php echo $category['category_id']; ?>" class="new-btn btn-danger btn-sm" onclick="return confirm('Bạn có chắc chắn muốn xóa danh mục này?');">Xóa</a>
                            </td>
                        </tr>
                <?php
                    }
                } else {
                    echo '<tr><td colspan="5" class="text-center">Chưa có danh mục nào.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
</section>

<?php if (isset($_GET['edit'])): 
    $edit_id = $_GET['edit'];
    $edit_query = mysqli_query($conn, "SELECT * FROM `categories` WHERE category_id = '$edit_id'") or die('Query failed');
    if (mysqli_num_rows($edit_query) > 0):
        $fetch_edit = mysqli_fetch_assoc($edit_query);
?>
<!-- Modal Update -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Cập nhật danh mục</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="update_id" value="<?php echo $fetch_edit['category_id']; ?>">
                    <div class="mb-3">
                        <label for="name" class="form-label">Tên danh mục</label>
                        <input type="text" name="name" id="name" class="form-control" value="<?php echo $fetch_edit['name']; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Mô tả</label>
                        <textarea name="description" id="description" class="form-control" rows="4"><?php echo $fetch_edit['description']; ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="new-btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" name="update_category" class="new-btn btn-primary">Cập nhật</button>
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
