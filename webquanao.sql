-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 15, 2025 at 05:12 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `webquanao`
--

-- --------------------------------------------------------

--
-- Table structure for table `blogs`
--

CREATE TABLE `blogs` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL,
  `image` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `cart_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(100) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`cart_id`, `user_id`, `item_id`, `quantity`) VALUES
(79, 2, 42, 1);

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `created_at`) VALUES
(9, 'Áo thun', '2025-02-15 09:32:56'),
(10, 'Áo polo', '2025-02-15 09:33:03'),
(11, 'Áo sơ mi', '2025-02-15 09:33:07'),
(12, 'Hoodie', '2025-02-15 09:33:12'),
(13, 'Áo khoác', '2025-02-15 09:33:17'),
(14, 'Quần', '2025-02-15 10:17:19');

-- --------------------------------------------------------

--
-- Table structure for table `message`
--

CREATE TABLE `message` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('sent','delivered','read') NOT NULL DEFAULT 'sent'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `message`
--

INSERT INTO `message` (`id`, `sender_id`, `receiver_id`, `message`, `created_at`, `status`) VALUES
(80, 1, 2, 'chào b', '2025-02-15 16:02:40', 'sent'),
(81, 2, 1, 'chào admin', '2025-02-15 16:03:36', 'sent'),
(82, 1, 2, 'bạn cần hỏi gì nhỉ ?', '2025-02-15 16:06:08', 'sent'),
(83, 3, 1, 'chào admin', '2025-02-15 16:10:15', 'sent'),
(84, 1, 3, 'chào bạn , bạn cần hỗ trợ gì nhỉ ?', '2025-02-15 16:10:36', 'sent');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `method` varchar(255) NOT NULL,
  `address` varchar(255) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `total_price` int(11) NOT NULL,
  `status` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_details`
--

CREATE TABLE `order_details` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` float NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `item_id` int(11) NOT NULL,
  `item_brand` varchar(255) NOT NULL,
  `item_category` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `item_desc` varchar(255) NOT NULL,
  `item_quantity` int(11) NOT NULL,
  `item_price` int(11) NOT NULL,
  `item_image` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`item_id`, `item_brand`, `item_category`, `item_name`, `item_desc`, `item_quantity`, `item_price`, `item_image`, `created_at`) VALUES
(41, 'Áo thun', 9, 'Áo Thun Seasonal Tshirt TS295', 'Thông tin sản phẩm:\r\n- Chất liệu: Cotton\r\n- Form: Oversize\r\n- Màu sắc: Đen/Trắng/Nâu Nhạt/Xanh Navy\r\n- Thiết kế: in lụa', 30, 195000, 'ts295-ada28098-5e56-4374-a938-1f94b54db3ad.webp', '2025-02-15 10:09:04'),
(42, 'Áo thun', 9, 'Summer Fresh Tshirt TS282', 'Thông tin sản phẩm:\r\n- Chất liệu: Cotton\r\n- Form: Oversize\r\n- Màu sắc: Đen/Kem\r\n- Thiết kế: In lụa', 40, 195000, 'ts282.webp', '2025-02-15 10:09:46'),
(43, 'Áo thun', 9, 'Áo Thun Dài Tay LongSleeve', 'Thông tin sản phẩm:\r\n- Chất liệu: Cotton\r\n- Form: Oversize\r\n- Màu sắc: Xám Tiêu/Đen/Trắng/Xám Đậm\r\n- Thiết kế: In lụa', 30, 210000, '1-94bbeaaf-a3e4-469e-ba81-cacae6015e9c.webp', '2025-02-15 10:10:16'),
(44, 'Áo thun', 9, 'Dino Petite Tshirt', 'Thông tin sản phẩm:\r\n- Chất liệu: Cotton\r\n- Form: Oversize\r\n- Màu sắc: Xám Tiêu/Kem\r\n- Thiết kế: In lụa\r\n\r\nMàu sắc: Xám Tiêu', 20, 195000, 'z6002325979517-fe36f5283ef718d04f77d4f523ef817d.webp', '2025-02-15 10:12:08'),
(45, 'Áo polo', 10, 'Symbol TLB Polo Shirt', 'Thông tin sản phẩm:\r\n- Chất liệu: TC cá sấu\r\n- Form: Oversize\r\n- Màu sắc: Xám/Đen\r\n- Thiết kế: In lụa\r\n\r\nMàu sắc: Xám', 30, 195000, 'ap054.webp', '2025-02-15 10:12:48'),
(46, 'Áo polo', 10, 'Sporty Stripes Royal Club', 'Thông tin sản phẩm:\r\n- Chất liệu: Vải lưới thể thao\r\n- Form: Oversize\r\n- Màu sắc: Đen/Xanh/Hồng\r\n- Thiết kế: In lụa', 30, 195000, 'z5891950936535-9d67e47afc325c0d7f8730c2f1e2eff0.webp', '2025-02-15 10:13:12'),
(47, 'Áo polo', 10, 'Áo Polo Flame', 'Thông tin sản phẩm:\r\n- Chất liệu: Cotton\r\n- Form: Oversize\r\n- Màu sắc: Đen\r\n- Thiết kế: In lụa', 40, 195000, 'img-7825-1.webp', '2025-02-15 10:13:43'),
(48, 'Áo polo', 10, 'Áo Polo Tyrannosaurus', 'Thông tin sản phẩm:\r\n\r\n- Chất liệu: Cotton\r\n\r\n- Form: Oversize\r\n\r\n- Màu sắc: Đen\r\n\r\n- Thiết kế: In lụa cao cấp.', 35, 195000, 'ap035.webp', '2025-02-15 10:14:13'),
(49, 'Áo polo', 10, 'Áo Polo Essentials Line', 'Thông tin sản phẩm: \r\n\r\n- Chất liệu: TC cá sấu\r\n\r\n- Form: Oversize\r\n\r\n- Màu sắc: Đen phối trắng\r\n\r\n- Thiết kế: In cao thành.', 20, 200000, 'ap001.webp', '2025-02-15 10:14:47'),
(50, 'Áo sơ mi', 11, 'Eco Oxford Mandarin Shirt', 'Thông tin sản phẩm:\r\n- Chất liệu: Vải Oxford \r\n- Form: Oversize\r\n- Màu sắc: Trắng/Xanh Dương/Xanh Than\r\n- Thiết kế: In lụa.', 20, 220000, 'ss073.webp', '2025-02-15 10:15:38'),
(51, 'Áo sơ mi', 11, 'Áo Sơ Mi Cộc Tay', 'Thông tin sản phẩm:\r\n- Chất liệu: Vải Oxford \r\n- Form: Oversize\r\n- Màu sắc: Đen/Hồng/Xanh Than/Xanh Dương/Trắng\r\n- Thiết kế: In lụa.', 30, 210000, 'ss068.webp', '2025-02-15 10:16:10'),
(52, 'Áo sơ mi', 11, 'Áo Sơ Mi Ngắn Tay Oxford', 'Thông tin sản phẩm:\r\n- Chất liệu: Vải  Oxford\r\n- Form: Oversize\r\n- Màu sắc: Xanh/Hồng/Xám\r\n- Thiết kế: Kẻ Sọc', 40, 210000, 'ss052-1.webp', '2025-02-15 10:16:45'),
(53, 'Hoodie', 12, 'Bunny Cake Hoodie', 'Thông tin sản phẩm:\r\n- Chất liệu: Nỉ\r\n- Form: Oversize\r\n- Màu sắc: Kem/Xám Tiêu\r\n- Thiết kế: In lụa', 20, 280000, '1-4046d3fc-8237-4c7c-8078-6db06ec88428.webp', '2025-02-15 10:17:46'),
(54, 'Hoodie', 12, 'Hoodie Zipup Boxy', 'Thông tin sản phẩm:\r\n- Chất liệu: Nỉ bông\r\n- Form: Oversize\r\n- Màu sắc: Đen/Xanh Navy/Xám Tiêu/Xám Đậm\r\n- Thiết kế: In lụa', 30, 280000, '3-a9fad637-80ce-4d67-aa7b-4cd6e5aa59c3.webp', '2025-02-15 10:18:13'),
(55, 'Hoodie', 12, ' Hoodie Dino Christmas', 'Thông tin sản phẩm:\r\n- Chất liệu: Nỉ\r\n- Form: Oversize\r\n- Màu sắc: Kem/Xám Tiêu\r\n- Thiết kế: In lụa', 10, 330000, 'hd098.webp', '2025-02-15 10:18:42'),
(56, 'Áo khoác', 13, 'Áo Khoác Len', 'Thông tin sản phẩm:\r\n- Chất liệu: Len\r\n- Form: Oversize\r\n- Màu sắc: Đen/Kem\r\n- Thiết kế: Thêu', 20, 300000, 'z6095782337333-46f118f9259767273c1358590b89c033.webp', '2025-02-15 10:19:13'),
(57, 'Áo khoác', 13, 'Diode Insulated Jacket', 'Thông tin sản phẩm:\r\n- Chất liệu: Vải gió\r\n- Form: Oversize\r\n- Màu sắc: Đen/Xám/Nâu Nhạt\r\n- Thiết kế: In lụa.', 20, 300000, 'z6020367002762-3219023f4d5a72cd31f48f5a1487e243.webp', '2025-02-15 10:19:56'),
(58, 'Áo khoác', 13, 'Classic Puffer Gilet', 'Thông tin sản phẩm:\r\n- Chất liệu: Vải gió trần bông\r\n- Form: Oversize\r\n- Màu sắc: Đen\r\n- Thiết kế: Trơn', 20, 230000, '1-90dd41fc-2cca-4f0e-bc42-136269af48f1.webp', '2025-02-15 10:20:30'),
(59, 'Áo khoác', 13, 'Cadigan Twinheart ', 'Thông tin sản phẩm:\r\n- Chất liệu: Len\r\n- Form: Oversize\r\n- Màu sắc: Đen/Kem\r\n- Thiết kế: Thêu Đắp', 20, 280000, 'ak079.webp', '2025-02-15 10:20:58'),
(60, 'Quần', 14, 'Straight Leg Sweatpants', 'Thông tin sản phẩm:\r\n- Chất liệu: Nỉ\r\n- Form: Oversize\r\n- Màu sắc: Xám Tiêu Đậm/Xám Tiêu Nhạt/Đen', 20, 250000, 'z5952235933548-56be3ccb896df86d20c7af4e7fc910c5.webp', '2025-02-15 10:21:31'),
(61, 'Quần', 14, 'Basketball Jersey', 'Thông tin sản phẩm:\r\n- Chất liệu: Vải thể thao\r\n- Form: Oversize\r\n- Màu sắc: Trắng/Đỏ/Đen\r\n- Thiết kế: In lụa', 30, 175000, 'ps091.webp', '2025-02-15 10:21:54'),
(62, 'Quần', 14, 'Boxing Shorts', 'Thông tin sản phẩm:\r\n- Chất liệu: Gió dù\r\n- Form: Oversize\r\n- Màu sắc: Đen/Đỏ/Vàng/Xanh Lá/Xanh Dương\r\n- Thiết kế: In lụa.', 40, 175000, 'ps090-1.webp', '2025-02-15 10:22:14'),
(63, 'Quần', 14, 'Pure Cotton Sports Shorts', 'Thông tin sản phẩm:\r\n- Chất liệu: Nỉ mỏng\r\n- Form: Oversize\r\n- Màu sắc:Đen/Kem/Xám/Nâu/Xanh rêu\r\n- Thiết kế: In lụa ', 20, 175000, 'img-6033-1.webp', '2025-02-15 10:22:44'),
(64, 'Quần', 14, 'Ripped Wash', 'Thông tin sản phẩm:\r\n\r\n- Chất liệu: Denim\r\n\r\n- Form: Oversize', 20, 350000, 'gp010.webp', '2025-02-15 10:23:17'),
(65, 'Quần', 14, 'Quần Jean Basic Denim', 'Thông tin sản phẩm:\r\n\r\n- Chất liệu: Denim\r\n\r\n- Form: Oversize', 20, 380000, 'gp008-1.webp', '2025-02-15 10:23:46');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `role` varchar(100) NOT NULL DEFAULT 'user',
  `status` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `email`, `role`, `status`, `created_at`) VALUES
(1, 'Admin', 'e10adc3949ba59abbe56e057f20f883e', 'admin@gmail.com', 'admin', 1, '2025-01-04 16:54:11'),
(2, 'User', 'e10adc3949ba59abbe56e057f20f883e', 'user@gmail.com', 'user', 1, '2025-01-04 16:54:11'),
(3, 'User 2', 'e10adc3949ba59abbe56e057f20f883e', 'user2@gmail.com', 'user', 1, '2025-01-05 09:05:58');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `blogs`
--
ALTER TABLE `blogs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`cart_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `message`
--
ALTER TABLE `message`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_details`
--
ALTER TABLE `order_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `item_category` (`item_category`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `blogs`
--
ALTER TABLE `blogs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `message`
--
ALTER TABLE `message`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `order_details`
--
ALTER TABLE `order_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `products` (`item_id`);

--
-- Constraints for table `message`
--
ALTER TABLE `message`
  ADD CONSTRAINT `message_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `message_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `order_details`
--
ALTER TABLE `order_details`
  ADD CONSTRAINT `order_details_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`);

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`item_category`) REFERENCES `categories` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
