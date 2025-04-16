-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 16, 2025 at 09:48 AM
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
-- Database: `webtoanha`
--

-- --------------------------------------------------------

--
-- Table structure for table `pricelist`
--

CREATE TABLE `pricelist` (
  `ID` int(11) NOT NULL,
  `Name` varchar(255) NOT NULL,
  `Code` varchar(50) NOT NULL,
  `Price` int(11) NOT NULL,
  `TypeOfFee` varchar(100) DEFAULT NULL,
  `ApplyDate` date DEFAULT NULL,
  `Status` enum('active','inactive') DEFAULT 'active',
  `PriceCalculation` varchar(100) DEFAULT NULL,
  `Title` varchar(255) DEFAULT NULL,
  `PriceFrom` int(11) DEFAULT NULL,
  `PriceTo` int(11) DEFAULT NULL,
  `VariableData` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `pricelist`
--

INSERT INTO `pricelist` (`ID`, `Name`, `Code`, `Price`, `TypeOfFee`, `ApplyDate`, `Status`, `PriceCalculation`, `Title`, `PriceFrom`, `PriceTo`, `VariableData`) VALUES
(6, 'Quản lý', 'BG01', 400000, 'Cố định', '2025-04-05', 'active', 'normal', '', 0, 0, NULL),
(7, 'Điện', 'BG02', 3500, 'Cố định', '2025-04-05', 'active', 'normal', '', 0, 0, NULL),
(8, 'Nước', 'BG03', 70000, 'Cố định', '2025-04-05', 'active', '', 'muc 1', 1, 5, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `pricelist`
--
ALTER TABLE `pricelist`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `Code` (`Code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `pricelist`
--
ALTER TABLE `pricelist`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
