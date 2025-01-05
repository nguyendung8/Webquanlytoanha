

TABLE `blogs` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL,
  `image` varchar(255) NOT NULL
)

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

TABLE `cart` (
  `cart_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL
)

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
)

-- --------------------------------------------------------

--
-- Table structure for table `message`
--

TABLE `message` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` varchar(255) NOT NULL
)

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `method` varchar(255) NOT NULL,
  `address` varchar(255) NOT NULL,
  `note` varchar(255) NOT NULL,
  `total_price` int(11) NOT NULL,
  `status` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
)

-- --------------------------------------------------------

--
-- Table structure for table `product`
--

TABLE `products` (
  `item_id` int(11) NOT NULL,
  `item_brand` varchar(255) NOT NULL,
  `item_category` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `item_desc` varchar(255) NOT NULL,
  `item_quantity` int(11) NOT NULL,
  `item_price` int(11) NOT NULL,
  `item_image` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
)
