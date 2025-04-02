

TABLE `blogs` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL,
  `image` varchar(255) NOT NULL
) 


TABLE `bookings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `field_id` int(11) NOT NULL,
  `booking_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `duration` float NOT NULL,
  `field_price` int(50) NOT NULL,
  `total_price` int(50) NOT NULL,
  `note` text DEFAULT NULL,
  `payment_method` varchar(20) DEFAULT NULL,
  `payment_status` varchar(20) DEFAULT NULL,
  `deposit_amount` int(50) NOT NULL,
  `status` enum('Chờ xác nhận','Đã xác nhận','Đã hủy') DEFAULT 'Chờ xác nhận',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `rent_ball` tinyint(1) DEFAULT 0,
  `rent_uniform` tinyint(1) DEFAULT 0,
  `payment_image` varchar(255) DEFAULT NULL,
  `cancel_reason` text DEFAULT NULL,
  `refund_image` varchar(255) DEFAULT NULL,
  `cancel_date` datetime DEFAULT NULL
) 


TABLE `field_feedback` (
  `id` int(11) NOT NULL,
  `field_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` int(1) NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` tinyint(1) DEFAULT 1
) 



TABLE `football_fields` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `address` text NOT NULL,
  `description` text DEFAULT NULL,
  `field_type` enum('5','7','11') NOT NULL,
  `rental_price` int(100) NOT NULL,
  `status` varchar(255) DEFAULT 'Đang trống',
  `image` varchar(255) DEFAULT NULL,
  `phone_number` varchar(15) DEFAULT NULL
) 



TABLE `message` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('sent','delivered','read') NOT NULL DEFAULT 'sent'
) 


TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `role` varchar(100) NOT NULL DEFAULT 'user',
  `status` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_account_number` varchar(50) DEFAULT NULL,
  `bank_account_name` varchar(100) DEFAULT NULL
) 
