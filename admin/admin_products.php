<?php
include '../database/DBController.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location:login.php');
    exit();
}

// Thêm sản phẩm mới
if (isset($_POST['add_product'])) {
    $item_name = mysqli_real_escape_string($conn, $_POST['item_name']);
    $item_brand = mysqli_real_escape_string($conn, $_POST['item_brand']);
    $item_category = $_POST['item_category'];
    $item_desc = mysqli_real_escape_string($conn, $_POST['item_desc']);
    $item_quantity = mysqli_real_escape_string($conn, $_POST['item_quantity']);
    $item_price = mysqli_real_escape_string($conn, $_POST['item_price']);

    // Upload hình ảnh sản phẩm
    $item_image_name = $_FILES['item_image']['name'];
    $item_image_tmp_name = $_FILES['item_image']['tmp_name'];
    $item_image_folder = '../assets/products/' . $item_image_name;

    if (move_uploaded_file($item_image_tmp_name, $item_image_folder)) {
        $insert_product_query = mysqli_query($conn, "INSERT INTO `products` (item_brand, item_category, item_name, item_desc, item_quantity, item_price, item_image) 
        VALUES ('$item_brand', '$item_category', '$item_name', '$item_desc', '$item_quantity', '$item_price', '$item_image_name')") or die('Query failed');

        if ($insert_product_query) {
            $message[] = 'Thêm sản phẩm thành công!';
        } else {
            $message[] = 'Thêm sản phẩm thất bại!';
        }
    } else {
        $message[] = 'Lỗi khi tải ảnh!';
    }
}

// Xóa sản phẩm
if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    $delete_image_query = mysqli_query($conn, "SELECT item_image FROM `products` WHERE item_id = '$delete_id'") or die('Query failed');
    $fetch_image = mysqli_fetch_assoc($delete_image_query);
    unlink('../assets/products/' . $fetch_image['item_image']);

    $delete_query = mysqli_query($conn, "DELETE FROM `products` WHERE item_id = '$delete_id'") or die('Query failed');

    if ($delete_query) {
        $message[] = 'Xóa sản phẩm thành công!';
    } else {
        $message[] = 'Xóa sản phẩm thất bại!';
    }
    header('location:admin_products.php');
    exit();
}

// Cập nhật sản phẩm
if (isset($_POST['update_product'])) {
    $update_id = $_POST['update_id'];
    $item_name = mysqli_real_escape_string($conn, $_POST['item_name']);
    $item_brand = mysqli_real_escape_string($conn, $_POST['item_brand']);
    $item_category = $_POST['item_category'];
    $item_desc = mysqli_real_escape_string($conn, $_POST['item_desc']);
    $item_quantity = mysqli_real_escape_string($conn, $_POST['item_quantity']);
    $item_price = mysqli_real_escape_string($conn, $_POST['item_price']);

    $update_query = "UPDATE `products` SET item_name = '$item_name', item_brand = '$item_brand', item_category = '$item_category', 
                    item_desc = '$item_desc', item_quantity = '$item_quantity', item_price = '$item_price'";

    if (!empty($_FILES['item_image']['name'])) {
        $item_image_name = $_FILES['item_image']['name'];
        $item_image_tmp_name = $_FILES['item_image']['tmp_name'];
        $item_image_folder = '../assets/products/' . $item_image_name;

        move_uploaded_file($item_image_tmp_name, $item_image_folder);
        $update_query .= ", item_image = '$item_image_name'";
    }

    $update_query .= " WHERE item_id = '$update_id'";
    $update_result = mysqli_query($conn, $update_query) or die('Query failed');

    if ($update_result) {
        $message[] = 'Cập nhật sản phẩm thành công!';
    } else {
        $message[] = 'Cập nhật sản phẩm thất bại!';
    }
    header('location:admin_products.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý sản phẩm</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="css/admin_style.css">
</head>

<body>
    <div class="d-flex">
        <?php include 'admin_navbar.php'; ?>
        <div style="width: calc(100% - 250px);">
            <div class="bg-primary text-white text-center py-2 mb-4 shadow">
                <h1 class="mb-0">Quản lý sản phẩm</h1>
            </div>
            <section class="add-products">
                <form action="" method="post" enctype="multipart/form-data">
                    <h3>Thêm sản phẩm mới</h3>
                    <div class="mb-3">
                        <input type="text" name="item_name" class="form-control" placeholder="Tên sản phẩm" required>
                    </div>
                    <div class="mb-3">
                        <select name="item_category" class="form-control" required>
                            <?php
                            $categories = mysqli_query($conn, "SELECT * FROM `categories`") or die('Query failed');
                            while ($category = mysqli_fetch_assoc($categories)) {
                                echo "<option value='{$category['category_id']}'>{$category['name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <textarea name="item_desc" class="form-control" placeholder="Mô tả sản phẩm" rows="5" required></textarea>
                    </div>
                    <div class="mb-3">
                        <input type="number" name="item_quantity" class="form-control" placeholder="Số lượng" required>
                    </div>
                    <div class="mb-3">
                        <input type="number" name="item_price" class="form-control" placeholder="Giá sản phẩm" required>
                    </div>
                    <div class="mb-3">
                        <input type="file" name="item_image" class="form-control" accept="image/*" required>
                    </div>
                    <button type="submit" name="add_product" class="btn btn-primary">Thêm sản phẩm</button>
                </form>
            </section>

            <section class="show-products">
                <div class="container">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Hình ảnh</th>
                                <th>Tên sản phẩm</th>
                                <th>Danh mục</th>
                                <th>Số lượng</th>
                                <th>Giá</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $select_products = mysqli_query($conn, "SELECT p.*, c.name AS category_name FROM `products` p 
                            LEFT JOIN `categories` c ON p.item_category = c.category_id 
                            ORDER BY p.created_at DESC") or die('Query failed');
                            if (mysqli_num_rows($select_products) > 0) {
                                while ($product = mysqli_fetch_assoc($select_products)) {
                            ?>
                                    <tr>
                                        <td><?php echo $product['item_id']; ?></td>
                                        <td><img src="../assets/products/<?php echo $product['item_image']; ?>" alt="" width="50"></td>
                                        <td><?php echo $product['item_name']; ?></td>
                                        <td><?php echo $product['item_brand']; ?></td>
                                        <td><?php echo $product['item_quantity']; ?></td>
                                        <td><?php echo $product['item_price']; ?> VNĐ</td>
                                        <td>
                                            <!-- Modal trigger button -->
                                            <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $product['item_id']; ?>">Sửa</button>
                                            <!-- Modal -->
                                            <div class="modal fade" id="editModal<?php echo $product['item_id']; ?>" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="editModalLabel">Sửa sản phẩm</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <form action="" method="post" enctype="multipart/form-data">
                                                            <div class="modal-body">
                                                                <input type="hidden" name="update_id" value="<?php echo $product['item_id']; ?>">
                                                                <input type="text" name="item_name" class="form-control mb-3" value="<?php echo $product['item_name']; ?>" required>
                                                                <input type="text" name="item_brand" class="form-control mb-3" value="<?php echo $product['item_brand']; ?>" required>
                                                                <select name="item_category" class="form-control mb-3" required>
                                                                    <?php
                                                                    $categories = mysqli_query($conn, "SELECT * FROM `categories`") or die('Query failed');
                                                                    while ($category = mysqli_fetch_assoc($categories)) {
                                                                        echo "<option value='{$category['category_id']}'" . ($category['category_id'] == $product['item_category'] ? ' selected' : '') . ">{$category['name']}</option>";
                                                                    }
                                                                    ?>
                                                                </select>
                                                                <textarea name="item_desc" class="form-control mb-3" rows="5" required><?php echo $product['item_desc']; ?></textarea>
                                                                <input type="number" name="item_quantity" class="form-control mb-3" value="<?php echo $product['item_quantity']; ?>" required>
                                                                <input type="number" name="item_price" class="form-control mb-3" value="<?php echo $product['item_price']; ?>" required>
                                                                <input type="file" name="item_image" class="form-control mb-3" accept="image/*">
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                                                                <button type="submit" name="update_product" class="btn btn-primary">Cập nhật</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- End Modal -->
                                            <a href="admin_products.php?delete=<?php echo $product['item_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Bạn có chắc chắn muốn xóa sản phẩm này?');">Xóa</a>
                                        </td>
                                    </tr>
                            <?php
                                }
                            } else {
                                echo '<tr><td colspan="8" class="text-center">Chưa có sản phẩm nào.</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>