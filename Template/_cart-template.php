<!-- Shopping cart section  -->
<?php
$user_id = $_SESSION['user_id'] ?? 1;
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['delete-cart-submit'])) {
        $deletedrecord = $Cart->deleteCart($_POST['item_id']);
    }

    // Update cart
    if (isset($_POST['update-quantity'])) {
        $updatedQuantity = $Cart->updateQuantity($_POST['item_id'], $_POST['quantity']);
    }

    // Delete all cart
    if (isset($_POST['delete-all-cart'])) {
        $Cart->deleteAllCart($user_id);
    }

    // save for later
    if (isset($_POST['wishlist-submit'])) {
        $Cart->saveForLater($_POST['item_id']);
    }

    // go-buy
    if (isset($_POST['go-buy'])) {
        if (isset($_SESSION['user_id'])) {
            header('Location: checkout.php');
        } else {
            $message[] = 'Vui lòng đăng nhập để mua hàng.';
            header('Location: login.php');
        }
    }
}
?>

<section id="cart" class="py-3 mb-5">
    <div class="container-fluid w-75">
        <h1 class="font-baloo text-center">Giỏ hàng</h1>

        <!-- shopping cart items -->
        <div class="row">
            <div class="col-sm-9">
                <?php
        // Giả sử bạn đã lấy dữ liệu giỏ hàng từ cơ sở dữ liệu hoặc session
        $cartItems = $product->getCartData($user_id); // Lấy dữ liệu giỏ hàng

if ($cartItems):
    foreach ($cartItems as $cart) :
        // Lấy chi tiết sản phẩm từ database
        $item = $product->getProduct($cart['item_id']); // Lấy thông tin sản phẩm

        // Vì $item là mảng chứa một phần tử (mảng sản phẩm)
        foreach ($item as $singleItem):
            $item_id = $singleItem['item_id']; // Lấy ID của sản phẩm trong giỏ
            $item_name = $singleItem['item_name']; // Tên sản phẩm
            $item_price = $singleItem['item_price']; // Giá sản phẩm
            $item_image = $singleItem['item_image']; // Hình ảnh sản phẩm
            $item_quantity = $cart['quantity']; // Số lượng trong giỏ hàng
        ?>

                <!-- cart item -->
                <div class="row border-top py-3 mt-3">
                    <div class="col-sm-2">
                        <img src="./assets/products/<?php echo $item_image; ?>" style="height: 120px;" alt="cart1"
                            class="img-fluid">
                    </div>
                    <div class="col-sm-8">
                        <h5 class="font-baloo font-size-20"><?php echo $item_name ?? "Unknown"; ?></h5>

                        <!-- product qty -->
                        <div class="qty d-flex pt-2">
                            <!-- <div class="d-flex font-rale w-25">
                                <button class="qty-up border bg-light" data-id="<?php echo $item_id; ?>"><i
                                        class="fas fa-angle-up"></i></button>
                                <input type="number" name="quantity" data-id="<?php echo $item_id; ?>"
                                    class="qty_input border px-2 w-100 bg-light" value="<?php echo $item_quantity; ?>"
                                    min="1">
                                <button data-id="<?php echo $item_id; ?>" class="qty-down border bg-light"><i
                                        class="fas fa-angle-down"></i></button>
                            </div> -->

                            <!-- Update Quantity Button -->
                            <form method="POST" class="update-form">
                                <input type="hidden" value="<?php echo $item_id; ?>" name="item_id">
                                <input type="number" name="quantity" value="<?php echo $item_quantity; ?>" min="1" max="<?php echo $singleItem['item_quantity']; ?>"
                                    class="form-control" required>
                                <button type="submit" name="update-quantity" class="btn btn-primary mt-2">Cập
                                    nhật</button>
                            </form>

                            <form method="post">
                                <input type="hidden" value="<?php echo $item_id; ?>" name="item_id">
                                <button type="submit" name="delete-cart-submit"
                                    class="btn btn-danger text-white ml-4 font-baloo px-3">Xóa</button>
                            </form>
                        </div>
                        <!-- !product qty -->

                    </div>

                    <div class="col-sm-2 text-right">
                        <div class="font-size-20 text-danger font-baloo">
                            <span class="product_price" data-id="<?php echo $item_id; ?>"
                                data-price="<?php echo $item_price; ?>">
                                <?php echo number_format($item_price * $item_quantity, 0, ',', '.') . ' đ'; ?>
                            </span>
                        </div>
                    </div>
                </div>
                <!-- !cart item -->

                <?php
                        endforeach; // Kết thúc foreach $item
                    endforeach; // Kết thúc foreach $cartItems
                else:
                    echo "<p>Giỏ hàng của bạn hiện tại không có sản phẩm nào.</p>";
                endif;
                ?>

                <form method="post">
                    <button type="submit" name="delete-all-cart"
                        class="btn btn-danger font-baloo mt-4 px-3">Xóa tất cả</button>
                </form>

            </div>

            <!-- subtotal section -->
            <div class="col-sm-3">
                <div class="sub-total border text-center mt-2">
                    <h6 class="font-size-12 font-rale text-success py-3"><i class="fas fa-check"></i> Đơn hàng của bạn
                        được Giao hàng MIỄN PHÍ.</h6>
                    <div class="border-top py-4">
                        <h5 class="font-baloo font-size-20">Tổng giỏ hàng
                            (<?php echo isset($cartItems) ? count($cartItems) : 0; ?> item) <br> <span
                                class="text-danger" id="deal-price">
                                <?php echo isset($cartItems) ? $Cart->getSum($cartItems) : 0; ?> đ
                            </span>
                        </h5>
                        <form method="post">
                            <button type="submit" name="go-buy" class="btn btn-warning mt-3">Mua hàng</button>
                        </form>
                    </div>

                </div>
            </div>
            <!-- !subtotal section -->
        </div>
        <!-- !shopping cart items -->
    </div>
</section>
<!-- !Shopping cart section  -->