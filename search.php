<?php
ob_start();
session_start();

// include header.php file
include ('header.php');
include './database/DBController.php';

// Kết nối cơ sở dữ liệu
include './database/DBController.php';

$user_id = $_SESSION['user_id'] ?? 1;

// request method post
if($_SERVER['REQUEST_METHOD'] == "POST"){
    if (isset($_POST['search_submit'])){
        // call method addToCart
        $Cart->addToCart($_POST['user_id'], $_POST['item_id']);
        header('Location: ' . $_SERVER['REQUEST_URI']);
    }
}

// Khởi tạo các biến filter từ GET parameters
$date = $_GET['date'] ?? date('Y-m-d');
$time_slot = $_GET['time_slot'] ?? '';
$field_type = $_GET['field_type'] ?? '';
$status = $_GET['status'] ?? '';

// Xây dựng câu query cơ bản
$sql = "SELECT DISTINCT f.* FROM football_fields f 
        LEFT JOIN bookings b ON f.id = b.field_id 
        WHERE 1=1";
$params = [];
$types = "";

// Thêm điều kiện filter theo loại sân
if (!empty($field_type)) {
    $sql .= " AND f.field_type = ?";
    $params[] = $field_type;
    $types .= "s";
}

// Thêm điều kiện filter theo trạng thái
if (!empty($status)) {
    if ($status == 'available') {
        $sql .= " AND (f.status = 'Đang trống' OR f.status IS NULL)";
    } elseif ($status == 'booked') {
        $sql .= " AND f.status = 'Đã đặt'";
    }
}

// Thêm điều kiện filter theo thời gian
if (!empty($time_slot)) {
    $sql .= " AND NOT EXISTS (
        SELECT 1 FROM bookings b2 
        WHERE b2.field_id = f.id 
        AND b2.booking_date = ? 
        AND b2.status != 'Đã hủy'
        AND (";
    
    switch($time_slot) {
        case 'morning':
            $sql .= "b2.start_time >= '06:00' AND b2.start_time < '11:00'";
            break;
        case 'afternoon':
            $sql .= "b2.start_time >= '13:00' AND b2.start_time < '17:00'";
            break;
        case 'evening':
            $sql .= "b2.start_time >= '17:00' AND b2.start_time < '22:00'";
            break;
    }
    
    $sql .= "))";
    $params[] = $date;
    $types .= "s";
}

// Thực hiện query với prepared statement
$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kết quả tìm kiếm</title>
    <style>
        .card {
            transition: transform 0.2s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .badge {
            padding: 8px 12px;
            font-size: 12px;
        }

        .badge-success {
            background-color: #28a745;
        }

        .badge-danger {
            background-color: #dc3545;
        }

        .card-text i {
            width: 20px;
            color: #28a745;
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="row">
            <div class="col-12">
                <h4 class="mb-4">
                    Danh sách sân bóng 
                    <?php 
                    if (!empty($time_slot)) {
                        echo " - ";
                        switch($time_slot) {
                            case 'morning':
                                echo "Buổi sáng";
                                break;
                            case 'afternoon':
                                echo "Buổi chiều";
                                break;
                            case 'evening':
                                echo "Buổi tối";
                                break;
                        }
                    }
                    if (!empty($field_type)) {
                        echo " - Sân {$field_type} người";
                    }
                    ?>
                </h4>
                <div class="d-flex flex-wrap mb-4" style="gap: 20px;">
                    <?php if (count($products) > 0): ?>
                        <?php foreach ($products as $item): ?>
                        <div class="grid-item border <?php echo $item['item_brand'] ?? "Brand" ; ?>">
                            <div class="item py-2" style="width: 200px;">
                                <div class="product font-rale">
                                    <a href="<?php printf('%s?item_id=%s', 'product.php', $item['item_id']); ?>">
                                        <img src="./assets/products/<?php echo $item['item_image'] ?? "./assets/products/13.png"; ?>" alt="product1" class="img-fluid">
                                    </a>
                                    <div class="text-center">
                                        <h6><?php echo $item['item_name']; ?></h6>
                                        <div class="price py-2">
                                            <?php echo number_format($item['item_price'], 0, ',', '.'); ?> đ
                                        </div>
                                        <form method="post">
                                            <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                            <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                                            <?php
                                                if (in_array($item['item_id'], $Cart->getCartId($product->getData('cart')) ?? [])){
                                                    echo '<button type="submit" disabled class="btn btn-success font-size-12">Đã có trong giỏ</button>';
                                                }else{
                                                    echo '<button type="submit" name="search_submit" class="btn btn-warning font-size-12">Thêm vào giỏ</button>';
                                                }
                                            ?>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>Không tìm thấy sản phẩm nào phù hợp với từ khóa "<?php echo htmlspecialchars($keyword); ?>"</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>


<?php
// include footer.php file
include ('footer.php');
?>