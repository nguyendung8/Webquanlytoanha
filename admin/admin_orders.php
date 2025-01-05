<?php
include '../database/DBController.php';

session_start();

// Kiểm tra admin đăng nhập
$admin_id = $_SESSION['admin_id'];
if (!isset($admin_id)) {
    header('location:login.php');
    exit();
}

// Xử lý duyệt phiếu
if (isset($_POST['confirmed'])) {
    $borrow_id = $_POST['borrow_id'];

    // Giảm số lượng sách khi duyệt phiếu
    $query = "SELECT book_id, quantity FROM borrow_book WHERE borrow_id = '$borrow_id'";
    $result = mysqli_query($conn, $query);
    $is_available = true; // Biến để theo dõi trạng thái khả dụng của tất cả sách

    // Lặp qua từng sách trong phiếu mượn
    while ($row = mysqli_fetch_assoc($result)) {
        $book_id = $row['book_id'];
        $borrowed_quantity = $row['quantity'];
        
        // Lấy số lượng sách trong kho
        $select_book = mysqli_query($conn, "SELECT * FROM books WHERE id = '$book_id'");
        $book = mysqli_fetch_assoc($select_book);
    
        if ($book['quantity'] < $borrowed_quantity) {
            // Nếu sách không đủ số lượng, thiết lập trạng thái không khả dụng
            $is_available = false;
            $message[] = "Sách " .$book['name'] . " không đủ số lượng! Số lượng còn lại: " . $book['quantity'];
            break; // Dừng kiểm tra ngay khi phát hiện lỗi
        }
    }
    
    if ($is_available) {
        // Nếu tất cả sách đủ số lượng, thực hiện cập nhật
        mysqli_data_seek($result, 0); // Đặt lại con trỏ kết quả để lặp lại từ đầu
        while ($row = mysqli_fetch_assoc($result)) {
            $book_id = $row['book_id'];
            $borrowed_quantity = $row['quantity'];
    
            // Trừ số lượng sách trong kho
            mysqli_query($conn, "UPDATE books SET quantity = quantity - $borrowed_quantity WHERE id = '$book_id'");
        }
    
        // Cập nhật trạng thái phiếu mượn
        mysqli_query($conn, "UPDATE borrows SET borrow_status = 1 WHERE id = '$borrow_id'");
    }

}

// Xử lý trả sách
if (isset($_POST['returned'])) {
    $borrow_id = $_POST['borrow_id'];
    $pay_day = date('Y-m-d');

    // Cộng lại số lượng sách
    $query = "SELECT book_id, quantity FROM borrow_book WHERE borrow_id = '$borrow_id'";
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $book_id = $row['book_id'];
        $borrowed_quantity = $row['quantity'];
        mysqli_query($conn, "UPDATE books SET quantity = quantity + $borrowed_quantity WHERE id = '$book_id'");
    }

    mysqli_query($conn, "UPDATE borrows SET borrow_status = 2, pay_day = '$pay_day' WHERE id = '$borrow_id'");
    $message[] = "Sách đã được trả thành công!";
}

// Xử lý xóa phiếu mượn
if (isset($_POST['delete-borrow'])) {
    $borrow_id = $_POST['borrow_id'];
    mysqli_query($conn, "DELETE FROM borrow_book WHERE borrow_id = '$borrow_id'");
    mysqli_query($conn, "DELETE FROM borrows WHERE id = '$borrow_id'");
    $message[] = "Xóa phiếu mượn thành công!";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Phiếu mượn</title>

   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
   <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
   <link rel="stylesheet" href="css/admin_style.css">

   <style>
      .box {
         border: 1px solid #3670EB !important;
         background-color: #fff !important;
      }
      h1, h3 {
         color: #3670EB !important;
      }
      .confirm-btn {
         margin-top: 16px;
         padding: 7px 16px;
         border-radius: 4px;
         font-size: 18px;
         color: #fff;
         cursor: pointer;
      }
      .confirm-btn:hover {
         opacity: 0.8;
      }
      .orders .box-container .box p span {
         color: #3670EB !important;
      }
      th {
           font-size: 20px;
            text-align: center;
      }
      td {
         font-size: 18px;
         padding: 1.5rem 0.5rem !important;
         text-align: center;
      }
      .new-btn {
         padding: 10px 13px; 
         text-decoration: none; 
         font-size: 18px;
         margin-bottom: 7px;
         border-radius: 4px;
      }
      i  {
         font-size: 15px;
         margin-right: 3px;
      }
   </style>
</head>
<body>
   
<?php include 'admin_header.php'; ?>

<section class="orders">
   <div class="container mt-4">
    <h2 class="mb-4 text-primary text-center fs-1">Danh Sách Phiếu Mượn</h2>

    <table class="table table-bordered table-hover text-center">
        <thead class="table-primary">
            <tr>
               <th>ID Phiếu</th>
               <th>Tên Người Mượn</th>
               <!-- <th>Email</th> -->
               <th>Tên Sách (Số Lượng)</th>
               <th>Ngày Mượn</th>
               <th>Hạn Trả</th>
               <th>Trạng Thái</th>
               <th>Hành Động</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Query lấy dữ liệu phiếu mượn và gộp các sách thành 1 ô
            $query = "SELECT 
                        borrows.id AS borrow_id, 
                        borrows.user_id, 
                        borrows.placed_on, 
                        borrows.borrow_deadline, 
                        borrows.borrow_status, 
                        GROUP_CONCAT(CONCAT(books.name, ' (', borrow_book.quantity, ')') SEPARATOR ', ') AS book_list,
                        users.name AS user_name, 
                        users.email
                      FROM borrows
                      JOIN borrow_book ON borrows.id = borrow_book.borrow_id
                      JOIN books ON borrow_book.book_id = books.id
                      JOIN users ON borrows.user_id = users.id
                      GROUP BY borrows.id
                      ORDER BY borrows.placed_on DESC";
            $result = mysqli_query($conn, $query);

            if (mysqli_num_rows($result) > 0) {
                while ($row = mysqli_fetch_assoc($result)) {
                    ?>
                    <tr>
                        <td><?php echo $row['borrow_id']; ?></td>
                        <td><?php echo $row['user_name']; ?></td>
                        <!-- <td><?php echo $row['email']; ?></td> -->
                        <td><?php echo $row['book_list']; ?></td>
                        <td><?php echo $row['placed_on']; ?></td>
                        <td><?php echo $row['borrow_deadline']; ?></td>
                        <td>
                            <?php 
                                if ($row['borrow_deadline'] < date('Y-m-d') && $row['borrow_status'] == 1) {
                                    echo '<span class="badge bg-danger">Quá hạn</span>';
                                } elseif ($row['borrow_status'] == 1) {
                                    echo '<span class="badge bg-success">Đã duyệt</span>';
                                } elseif ($row['borrow_status'] == 2) {
                                    echo '<span class="badge bg-warning text-dark">Đã trả</span>';
                                } else {
                                    echo '<span class="badge bg-info">Chờ xử lý</span>';
                                }
                            ?>
                        </td>
                        <td>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="borrow_id" value="<?php echo $row['borrow_id']; ?>">
                                <?php if ($row['borrow_status'] == 0) : ?>
                                    <button type="submit" name="confirmed" class="new-btn btn-primary btn-sm">Duyệt</button>
                                <?php elseif ($row['borrow_status'] == 1) : ?>
                                    <button type="submit" name="returned" class="new-btn btn-success btn-sm">Trả sách</button>
                                <?php else : ?>
                                    <button type="submit" name="delete-borrow" class="new-btn btn-danger btn-sm">Xóa</button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                    <?php
                }
            } else {
                echo '<tr><td colspan="8" class="text-danger">Không có phiếu mượn nào!</td></tr>';
            }
            ?>
        </tbody>
    </table>
</div>
</section>

<script src="js/admin_script.js"></script>

</body>
</html>