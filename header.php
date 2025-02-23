<?php
$user_id = @$_SESSION['user_id'] ?? 1;  

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TeeLab</title>

    <!-- Bootstrap CDN -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css"
        integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">

    <!-- Owl-carousel CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css"
        integrity="sha256-UhQQ4fxEeABh4JrcmAJ1+16id/1dnlOEVCFOxDef9Lw=" crossorigin="anonymous" />
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.theme.default.min.css"
        integrity="sha256-kksNxjDRxd/5+jGurZUJd1sdR2v+ClrCl3svESBaJqw=" crossorigin="anonymous" />

    <!-- font awesome icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.13.0/css/all.min.css"
        integrity="sha256-h20CPZ0QyXlBuAw7A+KluUYx/3pK+c7lYEpqLTlxjYQ=" crossorigin="anonymous" />

    <!-- Custom CSS file -->
    <link rel="stylesheet" href="style.css">

    <?php
    // require functions.php file
    require('functions.php');
    ?>

    <style>
        .nav-link {
            color: white !important;
        }

        .search-product {
            width: 300px;
            margin-right: 50px;
        }

        .user-dropdown {
            cursor: pointer;
        }

        #userDropdown {
            padding-bottom: 5px;
            ;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        #userDropdown p {
            margin: 0;
            font-size: 14px;
            color: #333;
        }

        #userDropdown button {
            margin-top: 10px;
        }
        .alert {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .dropdown-item:focus, .dropdown-item:hover {
            background-color: #007bff;
            color: white;
        }
        .search-btn {
            position: absolute;
            right: 49px;
            border-radius: 0;
            height: -webkit-fill-available;
        }
    </style>

</head>

<body>

    <!-- start #header -->
    <header id="header">
        <?php

        global $message;

        if (isset($message) && is_array($message)) { // hiển thị thông báo sau khi thao tác với biến message được gán giá trị
            foreach ($message as $msg) {
                echo '
       <div class=" alert alert-info alert-dismissible fade show" role="alert">
          <span style="font-size: 16px;">' . $msg . '</span>
          <i style="font-size: 20px; cursor: pointer" class="fas fa-times" onclick="this.parentElement.remove();"></i>
       </div>';
            }
        }
        ?>
        <div class="strip d-flex justify-content-between px-4 py-1 bg-light">
            <p class="font-rale font-size-12 text-black-50 m-0">TeeLab - 0763651041 - Việt Nam</p>
            <?php if ($user_id && $user_id != 1) { ?>
                <div class="user-dropdown" style="position: relative; display: inline-block;">
                    <i class="fas fa-user-circle" style="font-size: 30px; cursor: pointer;" id="userIcon"></i>
                    <!-- Dropdown menu -->
                    <div id="userDropdown"
                        style="display: none; position: absolute; top: 30px; right: 0; background: white; border: 1px solid #ddd; border-radius: 5px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); z-index: 1000; min-width: 150px;">
                        <p class="font-rale font-size-12 text-black-50 m-0 p-3">Xin chào,
                            <?php echo $_SESSION['user_name']; ?></p>
                        <a href="./logout.php" class="btn btn-danger btn-sm w-fit">Đăng xuất</a>
                    </div>
                </div>
            <?php } else { ?>
                <div class="font-rale font-size-14">
                    <a href="#" class="px-3 text-dark">Đăng ký</a>
                    <a href="./login.php" class="px-3 border-right border-left text-dark">Đăng nhập</a>
                </div>
            <?php } ?>
        </div>

        <!-- Primary Navigation -->
        <nav style=" background: #d83131;" class="navbar navbar-expand-lg navbar-dark color-header-bg">
            <a class="navbar-brand" href="./index.php">
                <img width="120" src="./assets/logo_tl.webp" alt="logo" class="logo">
            </a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav m-auto font-size-20">
                    <li class="nav-item">
                        <a class="nav-link" href="./index.php">Trang chủ</a>
                    </li>
                   <?php 
                        $categories = $product->getData('categories');
                        ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                Danh mục
                            </a>
                            <div class="dropdown-menu" aria-labelledby="navbarDropdown">
                                <?php foreach ($categories as $category): ?>
                                    <a class="dropdown-item" href="./category.php?cate_id=<?php echo $category['id'] ?>"><?= $category['name']; ?></a>
                                <?php endforeach; ?>
                            </div>
                        </li>
                    <li class="nav-item">
                        <a class="nav-link" href="./blog.php">Blog</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="./order.php">Đơn hàng</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="./personal.php">Hồ sơ cá nhân</a>
                    </li>
                </ul>
                <form method="get" action="./search.php" class="">
                    <div class="input-group">
                        <?php $keyword = $_GET['keyword'] ?? ''; ?>
                        <input type="text" name="keyword" class="form-control search-product" placeholder="Search" value="<?php echo $keyword; ?>">
                        <div class="input-group-append">
                            <button class="btn btn-primary search-btn" type="submit">Tìm kiếm</button>
                        </div>
                    </div>
                </form>
                <form action="#" class="font-size-14 font-rale">
                    <a href="cart.php" class="py-2 rounded-pill color-primary-bg">
                        <span class="font-size-16 px-2 text-white"><i class="fas fa-shopping-cart"></i></span>
                        <span
                            class="px-3 py-2 rounded-pill text-dark bg-light"><?php echo count($product->getCartData($user_id ?? 0)); ?></span>
                    </a>
                </form>
            </div>
        </nav>
        <!-- !Primary Navigation -->

    </header>
    <!-- !start #header -->

    <!-- start #main-site -->
    <main id="main-site">

        <script>
            document.getElementById('userIcon').addEventListener('click', function() {
                const dropdown = document.getElementById('userDropdown');
                dropdown.style.display = dropdown.style.display === 'flex' ? 'none' : 'flex';
            });

            // Đóng dropdown nếu click bên ngoài
            window.addEventListener('click', function(e) {
                const dropdown = document.getElementById('userDropdown');
                const userIcon = document.getElementById('userIcon');
                if (e.target !== dropdown && e.target !== userIcon) {
                    dropdown.style.display = 'none';
                }
            });
        </script>