<!-- Special Price -->
<?php

// request method post
if($_SERVER['REQUEST_METHOD'] == "POST"){
    if (isset($_POST['special_price_submit'])){
        // call method addToCart
        $Cart->addToCart($_POST['user_id'], $_POST['item_id']);
    }
}

$select_product =  mysqli_query($conn, "SELECT * FROM `products` order by item_id desc limit 10") or die('Query failed');
$selectProducts = mysqli_fetch_all($select_product, MYSQLI_ASSOC);

?>
<section id="special-price">
    <div class="container">
        <h4 class="font-rubik font-size-20">Special Price</h4>

        <div class="grid">
            <?php foreach ($selectProducts as $item) { ?>
            <div class="grid-item border <?php echo $item['item_brand'] ?? "Brand" ; ?>">
                <div class="item py-2" style="width: 200px;">
                    <div class="product font-rale">
                        <a href="<?php printf('%s?item_id=%s', 'product.php',  $item['item_id']); ?>"><img src="./assets/products/<?php echo $item['item_image'] ?? "./assets/products/13.png"; ?>" alt="product1" class="img-fluid"></a>
                        <div class="text-center">
                            <h6 style="height: 39px;"><?php echo $item['item_name'] ?? "Unknown"; ?></h6>
                            <!-- <div class="rating text-warning font-size-12">
                                <span><i class="fas fa-star"></i></span>
                                <span><i class="fas fa-star"></i></span>
                                <span><i class="fas fa-star"></i></span>
                                <span><i class="fas fa-star"></i></span>
                                <span><i class="far fa-star"></i></span>
                            </div> -->
                            <div class="price py-2">
                                <?php echo number_format($item['item_price'], 0, ',', '.'); ?> đ
                            </div>
                            <form method="post">
                                <input type="hidden" name="item_id" value="<?php echo $item['item_id'] ?? '1'; ?>">
                                <input type="hidden" name="user_id" value="<?php echo 1; ?>">
                                <?php
                                if (in_array($item['item_id'], $in_cart ?? [])){
                                    echo '<button type="submit" disabled class="btn btn-success font-size-12">In the Cart</button>';
                                }else{
                                    echo '<button type="submit" name="top_sale_submit" class="btn btn-warning font-size-12">Add to Cart</button>';
                                }
                                ?>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
</section>
<!-- !Special Price -->
