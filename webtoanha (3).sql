-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 16, 2025 at 12:15 PM
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
-- Table structure for table `apartment`
--

CREATE TABLE `apartment` (
  `ApartmentID` int(11) NOT NULL,
  `Name` varchar(255) DEFAULT NULL,
  `Area` int(11) DEFAULT NULL,
  `NumberOffBedroom` int(11) DEFAULT NULL,
  `Code` varchar(255) DEFAULT NULL,
  `ElectricId` int(11) DEFAULT NULL,
  `WaterId` int(11) NOT NULL,
  `Description` text DEFAULT NULL,
  `Status` varchar(50) DEFAULT NULL,
  `BuildingId` int(11) NOT NULL,
  `FloorId` int(11) NOT NULL,
  `ContractCode` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `apartment`
--

INSERT INTO `apartment` (`ApartmentID`, `Name`, `Area`, `NumberOffBedroom`, `Code`, `ElectricId`, `WaterId`, `Description`, `Status`, `BuildingId`, `FloorId`, `ContractCode`) VALUES
(1, 'A01 - 01', 200, 3, 'A001', NULL, 0, '<p>Phòng mới thoán đẹ</p>', 'Đang chờ nhận', 1, 1, '202504/HĐCH/0001'),
(2, 'A02 - 02', 150, 2, 'A002', NULL, 0, '<p><br></p>', 'Trống', 1, 3, NULL),
(3, 'A03 - 03', 180, 3, 'A003', NULL, 0, '<p><br></p>', 'Đang chờ nhận', 3, 4, '202504/HĐCH/0002');

-- --------------------------------------------------------

--
-- Table structure for table `buildings`
--

CREATE TABLE `buildings` (
  `ID` int(11) NOT NULL,
  `Name` varchar(255) NOT NULL,
  `Code` varchar(50) NOT NULL,
  `Status` varchar(50) DEFAULT 'active',
  `ProjectId` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `buildings`
--

INSERT INTO `buildings` (`ID`, `Name`, `Code`, `Status`, `ProjectId`) VALUES
(1, 'Tòa Nhà LM01', 'LM01', 'active', 1),
(2, 'Tòa Nhà LM02', 'LM02', 'active', 1),
(3, 'Tòa Nhà TK01', 'TK01', 'active', 2),
(4, 'Tòa Nhà TK02', 'TK02', 'active', 2);

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `CompanyId` int(11) NOT NULL,
  `Code` varchar(50) NOT NULL,
  `Name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `companies`
--

INSERT INTO `companies` (`CompanyId`, `Code`, `Name`) VALUES
(1, 'CT001', 'Top Building'),
(2, 'CT002', 'Lanmark Holding'),
(3, 'CT003', 'Agent Holding');

-- --------------------------------------------------------

--
-- Table structure for table `contractappendixs`
--

CREATE TABLE `contractappendixs` (
  `ContractAppendixId` int(11) NOT NULL,
  `Status` varchar(50) DEFAULT 'active',
  `CretionDate` date DEFAULT NULL,
  `ContractCode` varchar(50) DEFAULT NULL,
  `EndDate` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `contractappendixs`
--

INSERT INTO `contractappendixs` (`ContractAppendixId`, `Status`, `CretionDate`, `ContractCode`, `EndDate`) VALUES
(6, 'Hủy', '2025-04-15', '202504/HĐCH/0001', '2025-04-15'),
(7, 'Hủy', '2025-04-16', '202504/HĐCH/0001', '2025-04-15'),
(13, 'active', '2025-04-16', '202504/HĐCH/0001', '2025-08-16');

-- --------------------------------------------------------

--
-- Table structure for table `contracts`
--

CREATE TABLE `contracts` (
  `ContractCode` varchar(50) NOT NULL,
  `Status` varchar(50) DEFAULT 'pending',
  `CretionDate` date DEFAULT NULL,
  `File` text DEFAULT NULL,
  `CreatedAt` datetime DEFAULT current_timestamp(),
  `EndDate` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `contracts`
--

INSERT INTO `contracts` (`ContractCode`, `Status`, `CretionDate`, `File`, `CreatedAt`, `EndDate`) VALUES
('202504/HĐCH/0001', 'modified', '2025-04-06', '202504/HĐCH/0001_1743955334.pdf', '2025-04-06 16:24:24', '2025-08-16'),
('202504/HĐCH/0002', 'pending', '2025-04-08', NULL, '2025-04-07 00:35:55', '2025-10-08');

-- --------------------------------------------------------

--
-- Table structure for table `contractservices`
--

CREATE TABLE `contractservices` (
  `ContractCode` varchar(50) NOT NULL,
  `ServiceId` varchar(50) NOT NULL,
  `ApplyDate` date DEFAULT NULL,
  `EndDate` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `contractservices`
--

INSERT INTO `contractservices` (`ContractCode`, `ServiceId`, `ApplyDate`, `EndDate`) VALUES
('202504/HĐCH/0001', 'DV003', '2025-04-07', '2025-04-15'),
('202504/HĐCH/0002', 'DV002', '2025-04-07', '2025-05-07');

-- --------------------------------------------------------

--
-- Table structure for table `debtstatementdetail`
--

CREATE TABLE `debtstatementdetail` (
  `InvoiceCode` varchar(50) NOT NULL,
  `ServiceCode` varchar(50) NOT NULL,
  `Quantity` int(11) DEFAULT 0,
  `UnitPrice` int(11) DEFAULT 0,
  `Discount` int(11) DEFAULT 0,
  `PaidAmount` int(11) DEFAULT 0,
  `RemainingBalance` int(11) DEFAULT 0,
  `IssueDate` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `debtstatementdetail`
--

INSERT INTO `debtstatementdetail` (`InvoiceCode`, `ServiceCode`, `Quantity`, `UnitPrice`, `Discount`, `PaidAmount`, `RemainingBalance`, `IssueDate`) VALUES
('BK01', 'DV003', 4, 5000, 0, 0, 20000, '2025-04-16');

-- --------------------------------------------------------

--
-- Table structure for table `debtstatements`
--

CREATE TABLE `debtstatements` (
  `InvoiceCode` varchar(50) NOT NULL,
  `InvoicePeriod` varchar(50) DEFAULT NULL,
  `DueDate` date DEFAULT NULL,
  `ApprovalDate` date DEFAULT NULL,
  `OutstandingDebt` int(11) DEFAULT NULL,
  `Discount` int(11) DEFAULT NULL,
  `Total` int(11) DEFAULT NULL,
  `PaidAmount` int(11) DEFAULT NULL,
  `RemainingBalance` int(11) DEFAULT NULL,
  `IssueDate` date DEFAULT NULL,
  `Status` varchar(50) DEFAULT NULL,
  `ApartmentID` int(11) DEFAULT NULL,
  `StaffID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `debtstatements`
--

INSERT INTO `debtstatements` (`InvoiceCode`, `InvoicePeriod`, `DueDate`, `ApprovalDate`, `OutstandingDebt`, `Discount`, `Total`, `PaidAmount`, `RemainingBalance`, `IssueDate`, `Status`, `ApartmentID`, `StaffID`) VALUES
('BK01', '04/2025', '2025-04-29', NULL, 0, 0, 20000, 0, 20000, '2025-04-16', 'Chờ xác nhận', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `ID` int(11) NOT NULL,
  `Name` varchar(255) DEFAULT NULL,
  `Code` varchar(255) NOT NULL,
  `PhoneNumber` varchar(20) DEFAULT NULL,
  `Email` varchar(255) DEFAULT NULL,
  `Description` text DEFAULT NULL,
  `DepartmentManagerID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`ID`, `Name`, `Code`, `PhoneNumber`, `Email`, `Description`, `DepartmentManagerID`) VALUES
(1, 'Phòng ban 01', 'PB001', '0867570608', 'pb001@gmail.com', 'PHòng ban đầu tiên', 5),
(2, 'Phòng ban 02', 'PB002', '0924361157', 'pb002@gmail.com', 'Phòng ban thứ 2', 4),
(3, 'Phòng ban 03', 'PB003', '0924361361', 'pb003@gmail.com', 'Phòng ban thứ 3', 3);

-- --------------------------------------------------------

--
-- Table structure for table `electricitymeterreading`
--

CREATE TABLE `electricitymeterreading` (
  `ElectricityMeterID` int(11) NOT NULL,
  `InitialReading` float DEFAULT NULL,
  `FinalReading` float DEFAULT NULL,
  `Image` varchar(255) DEFAULT NULL,
  `ClosingDate` date DEFAULT NULL,
  `Consumption` float DEFAULT NULL,
  `ApartmentID` int(11) NOT NULL,
  `StaffID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `electricitymeterreading`
--

INSERT INTO `electricitymeterreading` (`ElectricityMeterID`, `InitialReading`, `FinalReading`, `Image`, `ClosingDate`, `Consumption`, `ApartmentID`, `StaffID`) VALUES
(29, 0, 135, '../uploads/electricity/1744120234_download.jpg', '2025-04-01', 135, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `excesspayment`
--

CREATE TABLE `excesspayment` (
  `ExcessPaymentID` int(11) NOT NULL,
  `OccurrenceDate` date NOT NULL,
  `Total` int(15) NOT NULL,
  `ApartmentID` int(11) NOT NULL,
  `ReceiptID` varchar(20) DEFAULT NULL,
  `Description` text DEFAULT NULL,
  `Status` varchar(50) DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `excesspayment`
--

INSERT INTO `excesspayment` (`ExcessPaymentID`, `OccurrenceDate`, `Total`, `ApartmentID`, `ReceiptID`, `Description`, `Status`) VALUES
(1, '2025-04-11', 5000, 1, 'PT2504115780', 'Thanh toán thừa từ phiếu thu PT2504115780', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `floors`
--

CREATE TABLE `floors` (
  `ID` int(11) NOT NULL,
  `Name` varchar(255) NOT NULL,
  `Code` varchar(50) NOT NULL,
  `BuildingId` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `floors`
--

INSERT INTO `floors` (`ID`, `Name`, `Code`, `BuildingId`) VALUES
(1, 'Tầng 1', 'LM01T1', 1),
(3, 'Tầng 2', 'LM01T2', 1),
(4, 'Tầng 1', 'TK01T1', 3),
(5, 'Tầng 2', 'TK01T2', 3);

-- --------------------------------------------------------

--
-- Table structure for table `otherreceipt`
--

CREATE TABLE `otherreceipt` (
  `OtherReceiptID` varchar(20) NOT NULL,
  `Quantity` int(11) DEFAULT NULL,
  `Price` int(11) DEFAULT NULL,
  `PaymentMethod` varchar(50) DEFAULT NULL,
  `Payer` varchar(100) DEFAULT NULL,
  `Total` int(15) DEFAULT NULL,
  `AccountingDate` date DEFAULT NULL,
  `Content` text DEFAULT NULL,
  `StaffID` int(11) DEFAULT NULL,
  `ApartmentID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `otherreceipt`
--

INSERT INTO `otherreceipt` (`OtherReceiptID`, `Quantity`, `Price`, `PaymentMethod`, `Payer`, `Total`, `AccountingDate`, `Content`, `StaffID`, `ApartmentID`) VALUES
('PT_20250412_001', 1, 280000, 'Tiền mặt', 'Cư dân A', 280000, '2025-04-12', 'thu phí thêm', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `paymentinformation`
--

CREATE TABLE `paymentinformation` (
  `Id` int(11) NOT NULL,
  `AccountName` varchar(255) NOT NULL,
  `AccountNumber` varchar(50) NOT NULL,
  `Bank` varchar(255) NOT NULL,
  `Branch` varchar(255) DEFAULT NULL,
  `ProjectId` int(11) NOT NULL,
  `AutoTransaction` tinyint(1) DEFAULT 0,
  `AutoReconciliation` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `paymentinformation`
--

INSERT INTO `paymentinformation` (`Id`, `AccountName`, `AccountNumber`, `Bank`, `Branch`, `ProjectId`, `AutoTransaction`, `AutoReconciliation`) VALUES
(1, 'Tran Kim Anh', '109937414042', 'Vietcombank', 'Ha Noi', 1, 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `PaymentID` int(11) NOT NULL,
  `PaymentMethod` varchar(50) DEFAULT NULL,
  `IssueDate` date DEFAULT NULL,
  `AccountingDate` date DEFAULT NULL,
  `Total` int(11) DEFAULT NULL,
  `ApartmentID` int(11) DEFAULT NULL,
  `StaffID` int(11) DEFAULT NULL,
  `DeletedBy` int(11) DEFAULT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `Content` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`PaymentID`, `PaymentMethod`, `IssueDate`, `AccountingDate`, `Total`, `ApartmentID`, `StaffID`, `DeletedBy`, `CreatedAt`, `UpdatedAt`, `Content`) VALUES
(2, 'Chuyển khoản', '2025-04-12', '2025-04-12', 5000, 1, 1, NULL, '2025-04-12 08:00:49', '2025-04-12 08:18:50', 'trả tiền thừa');

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
(8, 'Nước', 'BG03', 5000, 'Lũy tiến', '2025-04-05', 'active', '', 'Muc 1', 0, 50, '[{\"title\":\"Muc 1\",\"price_from\":0,\"price_to\":50,\"price\":5000},{\"title\":\"Muc2\",\"price_from\":50,\"price_to\":200,\"price\":10000}]');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `ProjectID` int(11) NOT NULL,
  `Name` varchar(255) NOT NULL,
  `Address` text NOT NULL,
  `Phone` varchar(20) DEFAULT NULL,
  `Email` varchar(255) DEFAULT NULL,
  `Deadlock` date DEFAULT NULL,
  `Description` text DEFAULT NULL,
  `OperationId` varchar(50) DEFAULT NULL,
  `TownShipId` int(11) NOT NULL,
  `ManagerId` int(11) NOT NULL,
  `Status` varchar(50) DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`ProjectID`, `Name`, `Address`, `Phone`, `Email`, `Deadlock`, `Description`, `OperationId`, `TownShipId`, `ManagerId`, `Status`) VALUES
(1, 'Dự án Lanmark 82', 'Hạ Đình - Thanh Xuân - Hà Nội', '05236298228', 'lm82@gmail.com', '2025-08-14', 'Dự án quy mô lớn', 'BVH001', 2, 5, 'active'),
(2, 'Dự án Super Top King', 'Số 24 - Định Công - Hoàng Mai', '0523629312', 'sptk@gmail.com', '2025-09-24', 'Dự án quy mô rất lớn', 'BVH002', 3, 4, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `receipt`
--

CREATE TABLE `receipt` (
  `ReceiptID` varchar(20) NOT NULL,
  `PaymentMethod` varchar(50) DEFAULT NULL COMMENT 'Hình thức thanh toán',
  `TransactionType` varchar(50) DEFAULT NULL COMMENT 'Loại giao dịch (Thu/Chi)',
  `ReceiptType` varchar(50) DEFAULT NULL COMMENT 'Loại phiếu',
  `Total` decimal(15,2) DEFAULT 0.00 COMMENT 'Tổng tiền',
  `AmountDue` decimal(15,2) DEFAULT 0.00 COMMENT 'Số tiền phải trả',
  `Payer` varchar(255) DEFAULT NULL COMMENT 'Người nộp/nhận tiền',
  `Address` text DEFAULT NULL COMMENT 'Địa chỉ',
  `AccountingDate` date DEFAULT NULL COMMENT 'Ngày hạch toán',
  `Content` text DEFAULT NULL COMMENT 'Nội dung',
  `StaffID` int(11) DEFAULT NULL COMMENT 'Người tạo phiếu',
  `ApartmentID` int(11) DEFAULT NULL COMMENT 'Căn hộ',
  `CreatedAt` datetime DEFAULT current_timestamp(),
  `Status` varchar(50) DEFAULT 'pending' COMMENT 'Trạng thái phiếu'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `receipt`
--

INSERT INTO `receipt` (`ReceiptID`, `PaymentMethod`, `TransactionType`, `ReceiptType`, `Total`, `AmountDue`, `Payer`, `Address`, `AccountingDate`, `Content`, `StaffID`, `ApartmentID`, `CreatedAt`, `Status`) VALUES
('PT2504115780', 'Tiền mặt', 'Thu', 'Phiếu thu', 80000.00, 80000.00, 'Cư dân A', '', '2025-04-12', 'thu phí căn hộ', 1, 1, '2025-04-11 23:00:25', 'completed');

-- --------------------------------------------------------

--
-- Table structure for table `receiptdetails`
--

CREATE TABLE `receiptdetails` (
  `ReceiptID` varchar(20) NOT NULL,
  `ServiceCode` varchar(50) NOT NULL COMMENT 'Mã dịch vụ',
  `Incurred` decimal(15,2) DEFAULT 0.00 COMMENT 'Số tiền phát sinh',
  `Discount` decimal(15,2) DEFAULT 0.00 COMMENT 'Giảm giá',
  `Payment` decimal(15,2) DEFAULT 0.00 COMMENT 'Số tiền thanh toán',
  `Note` text DEFAULT NULL COMMENT 'Ghi chú'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `receiptdetails`
--

INSERT INTO `receiptdetails` (`ReceiptID`, `ServiceCode`, `Incurred`, `Discount`, `Payment`, `Note`) VALUES
('PT2504115780', 'DV003', 70000.00, 0.00, 80000.00, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `resident`
--

CREATE TABLE `resident` (
  `ID` int(11) NOT NULL,
  `NationalId` varchar(50) DEFAULT NULL,
  `Dob` date DEFAULT NULL,
  `Gender` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `resident`
--

INSERT INTO `resident` (`ID`, `NationalId`, `Dob`, `Gender`) VALUES
(4, '1299331421312', '1999-06-07', 'Nữ'),
(5, '092356167322', '2000-05-07', 'Nam'),
(9, '129933112211222', '0000-00-00', 'Nam'),
(10, '083831257212', '2000-07-04', 'Nữ'),
(11, '0974689414', '1999-06-30', 'Nam'),
(12, '12213932132323', '2000-04-02', 'Nam');

-- --------------------------------------------------------

--
-- Table structure for table `residentapartment`
--

CREATE TABLE `residentapartment` (
  `ResidentId` int(11) NOT NULL,
  `ApartmentId` int(11) NOT NULL,
  `Relationship` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `residentapartment`
--

INSERT INTO `residentapartment` (`ResidentId`, `ApartmentId`, `Relationship`) VALUES
(4, 1, 'Vợ/Chồng'),
(9, 1, 'Anh chị em'),
(10, 1, 'Chủ hộ'),
(11, 3, 'Chủ hộ'),
(12, 1, 'Con');

-- --------------------------------------------------------

--
-- Table structure for table `serviceprice`
--

CREATE TABLE `serviceprice` (
  `ServiceId` varchar(50) NOT NULL,
  `PriceId` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `serviceprice`
--

INSERT INTO `serviceprice` (`ServiceId`, `PriceId`) VALUES
('DV001', 6),
('DV002', 7),
('DV003', 8);

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `ServiceCode` varchar(50) NOT NULL,
  `Name` varchar(255) NOT NULL,
  `Description` text DEFAULT NULL,
  `Status` varchar(50) DEFAULT 'active',
  `Cycle` int(11) DEFAULT NULL,
  `Paydate` date DEFAULT NULL,
  `FirstDate` date DEFAULT NULL,
  `ApplyForm` date DEFAULT NULL,
  `SwitchDay` int(11) DEFAULT NULL,
  `TypeOfObject` varchar(100) DEFAULT NULL,
  `TypeOfService` varchar(100) DEFAULT NULL,
  `StartPrice` varchar(100) DEFAULT NULL,
  `CancelPrice` varchar(100) DEFAULT NULL,
  `ProjectId` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`ServiceCode`, `Name`, `Description`, `Status`, `Cycle`, `Paydate`, `FirstDate`, `ApplyForm`, `SwitchDay`, `TypeOfObject`, `TypeOfService`, `StartPrice`, `CancelPrice`, `ProjectId`) VALUES
('DV001', 'Quản lý tòa nhà', '', 'active', 1, '2025-05-05', '2025-04-05', '2025-04-05', NULL, 'Ban quản trị', 'Dịch vụ quản lý', 'full', 'full', 1),
('DV002', 'Điện', '', 'active', 3, '2025-06-05', '2025-04-05', '2025-04-05', NULL, 'Thu hộ', 'Điện', 'full', 'full', 2),
('DV003', 'Nước', '', 'active', 1, '2025-05-05', '2025-04-05', '2025-04-05', NULL, 'Thu hộ', 'Nước', 'full', 'full', 1);

-- --------------------------------------------------------

--
-- Table structure for table `servicevehicles`
--

CREATE TABLE `servicevehicles` (
  `ServiceId` varchar(50) NOT NULL,
  `VehicleCode` varchar(50) NOT NULL,
  `ApplyFeeDate` date DEFAULT NULL,
  `EndFeeDate` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `servicevehicles`
--

INSERT INTO `servicevehicles` (`ServiceId`, `VehicleCode`, `ApplyFeeDate`, `EndFeeDate`) VALUES
('DV001', 'PT01', '2025-04-06', '2025-05-06'),
('DV001', 'PT02', '2025-04-06', '2025-05-06');

-- --------------------------------------------------------

--
-- Table structure for table `staffprojects`
--

CREATE TABLE `staffprojects` (
  `ProjectId` int(11) NOT NULL,
  `StaffId` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `staffprojects`
--

INSERT INTO `staffprojects` (`ProjectId`, `StaffId`) VALUES
(1, 3),
(1, 4),
(1, 5),
(1, 7),
(1, 8),
(2, 4),
(2, 11);

-- --------------------------------------------------------

--
-- Table structure for table `staffs`
--

CREATE TABLE `staffs` (
  `ID` int(11) NOT NULL,
  `Name` varchar(255) DEFAULT NULL,
  `Email` varchar(255) DEFAULT NULL,
  `PhoneNumber` varchar(20) DEFAULT NULL,
  `Position` varchar(100) DEFAULT NULL,
  `Address` text DEFAULT NULL,
  `DepartmentId` int(11) NOT NULL,
  `NationalID` varchar(50) DEFAULT NULL,
  `Status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `staffs`
--

INSERT INTO `staffs` (`ID`, `Name`, `Email`, `PhoneNumber`, `Position`, `Address`, `DepartmentId`, `NationalID`, `Status`) VALUES
(3, 'Trần Kim Anh', 'qth001@gmail.com', '0983532153', 'Quản trị hệ thống', 'Thanh xuân - Hà Nội', 1, '092830935113', 'active'),
(4, 'Phạm Thị Hồng Nhung', 'ktb001@gmail.com', '0983532158', 'Trưởng BQL', 'Số nhà 24 - Định Công', 2, '084251145334', 'active'),
(5, 'Huyền My', 'tbql001@gmail.com', '0983532153', 'Trưởng BQL', 'Số nhà 30 - Hạ Đình', 3, '092830935213', 'active'),
(7, 'Nguyễn Hoàng Nam', 'nam@gmail.com', '0923562721', 'Quản trị hệ thống', 'Vũ Trọng Phụng - Thanh Xuân - Hà Nội', 2, '092830935414', 'active'),
(8, 'Mạc Hưng', 'hung@gmail.com', '0523629827', 'Quản trị hệ thống', 'Số nhà 24 - Định Công', 0, '092830935113', 'active'),
(11, 'Hoàng Mạnh Cường ', 'hmcuong@gmail.com', '0523629827', 'Nhân viên kỹ thuật', 'Hạ Đình - Thanh Xuân - Hà Nội', 3, '09283093511332', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `townships`
--

CREATE TABLE `townships` (
  `TownShipId` int(11) NOT NULL,
  `Code` varchar(50) NOT NULL,
  `Name` varchar(255) NOT NULL,
  `CompanyId` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `townships`
--

INSERT INTO `townships` (`TownShipId`, `Code`, `Name`, `CompanyId`) VALUES
(1, 'KĐT001', 'Khu đô thị Vạn Năng', 3),
(2, 'KĐT002', 'Khu đô thị Lanmark', 2),
(3, 'KĐT003', 'Khu đô thị Top King', 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `UserId` int(11) NOT NULL,
  `UserName` varchar(255) NOT NULL,
  `Email` varchar(255) NOT NULL,
  `Password` varchar(255) NOT NULL,
  `PhoneNumber` varchar(20) DEFAULT NULL,
  `Position` varchar(255) DEFAULT NULL,
  `DepartmentId` int(11) DEFAULT NULL,
  `ResidentID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`UserId`, `UserName`, `Email`, `Password`, `PhoneNumber`, `Position`, `DepartmentId`, `ResidentID`) VALUES
(1, 'Admin', 'admin@gmail.com', 'e10adc3949ba59abbe56e057f20f883e', '0523629228', 'Quản trị hệ thống', 1, NULL),
(4, 'Trần Kim Anh', 'qth001@gmail.com', 'e10adc3949ba59abbe56e057f20f883e', '0983532153', 'Quản trị hệ thống', 1, NULL),
(6, 'Huyền My', 'tbql001@gmail.com', 'e10adc3949ba59abbe56e057f20f883e', '0983532153', 'Trưởng BQL', 1, NULL),
(11, 'Mạc Hưng', 'hung@gmail.com', 'e10adc3949ba59abbe56e057f20f883e', '0983532153', NULL, NULL, 5),
(14, 'Cư dân A', 'cudana@gmail.com', 'e10adc3949ba59abbe56e057f20f883e', '0923514572', NULL, NULL, 10),
(15, 'Cư dân B', 'cudanb@gmail.com', 'e10adc3949ba59abbe56e057f20f883e', '0512451949', NULL, NULL, 11),
(17, 'Hoàng Mạnh Cường ', 'hmcuong@gmail.com', 'e10adc3949ba59abbe56e057f20f883e', '0523629827', 'Nhân viên kỹ thuật', 3, NULL),
(18, 'Hoàng Nam', 'nam@gmail.com', 'e10adc3949ba59abbe56e057f20f883e', '0293232332', NULL, NULL, 12);

-- --------------------------------------------------------

--
-- Table structure for table `vehiclecards`
--

CREATE TABLE `vehiclecards` (
  `VehicleCardCode` varchar(50) NOT NULL,
  `VehicleType` varchar(255) NOT NULL,
  `NumberPlate` varchar(255) DEFAULT NULL,
  `Status` varchar(50) DEFAULT 'Chưa cấp phát',
  `Note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `vehiclecards`
--

INSERT INTO `vehiclecards` (`VehicleCardCode`, `VehicleType`, `NumberPlate`, `Status`, `Note`) VALUES
('TX01', 'Ô tô', '29H-01922', 'Đã cấp phát', 'thẻ xe 01 cho ô tô'),
('TX02', 'Xe máy', '29F1- 86622', 'Đã cấp phát', 'thẻ xe 02 cho  xe máy');

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `VehicleCode` varchar(50) NOT NULL,
  `TypeVehicle` varchar(100) DEFAULT NULL,
  `VehicleName` varchar(255) DEFAULT NULL,
  `NumberPlate` varchar(50) DEFAULT NULL,
  `Color` varchar(50) DEFAULT NULL,
  `Brand` varchar(100) DEFAULT NULL,
  `VehicleIdentificationNumber` varchar(100) DEFAULT NULL,
  `EngineNumber` varchar(100) DEFAULT NULL,
  `Description` text DEFAULT NULL,
  `Status` varchar(50) DEFAULT NULL,
  `VehicleCardCode` varchar(50) DEFAULT NULL,
  `ApartmentId` int(11) NOT NULL,
  `VehicleOwnerID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `vehicles`
--

INSERT INTO `vehicles` (`VehicleCode`, `TypeVehicle`, `VehicleName`, `NumberPlate`, `Color`, `Brand`, `VehicleIdentificationNumber`, `EngineNumber`, `Description`, `Status`, `VehicleCardCode`, `ApartmentId`, `VehicleOwnerID`) VALUES
('PT01', 'Ô tô', 'Oto V3', '29H-01922', 'Trắng', 'Toyota', '0321', '312321', '', 'active', 'TX01', 1, 4),
('PT02', 'Xe máy', 'SH125i', '29F1- 86622', 'Trắng', 'Honda', '0233', '312221', '', 'active', 'TX02', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `watermeterreading`
--

CREATE TABLE `watermeterreading` (
  `WaterMeterID` int(11) NOT NULL,
  `InitialReading` float DEFAULT NULL,
  `FinalReading` float DEFAULT NULL,
  `Image` varchar(255) DEFAULT NULL,
  `ClosingDate` date DEFAULT NULL,
  `Consumption` float DEFAULT NULL,
  `ApartmentID` int(11) NOT NULL,
  `StaffID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `watermeterreading`
--

INSERT INTO `watermeterreading` (`WaterMeterID`, `InitialReading`, `FinalReading`, `Image`, `ClosingDate`, `Consumption`, `ApartmentID`, `StaffID`) VALUES
(1, 0, 4, '../uploads/water/1744120285_images.jpg', '2025-04-01', 4, 1, 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `apartment`
--
ALTER TABLE `apartment`
  ADD PRIMARY KEY (`ApartmentID`);

--
-- Indexes for table `buildings`
--
ALTER TABLE `buildings`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `ProjectId` (`ProjectId`);

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`CompanyId`),
  ADD UNIQUE KEY `Code` (`Code`);

--
-- Indexes for table `contractappendixs`
--
ALTER TABLE `contractappendixs`
  ADD PRIMARY KEY (`ContractAppendixId`),
  ADD KEY `ContractCode` (`ContractCode`);

--
-- Indexes for table `contracts`
--
ALTER TABLE `contracts`
  ADD PRIMARY KEY (`ContractCode`);

--
-- Indexes for table `contractservices`
--
ALTER TABLE `contractservices`
  ADD PRIMARY KEY (`ContractCode`,`ServiceId`),
  ADD KEY `ServiceId` (`ServiceId`);

--
-- Indexes for table `debtstatementdetail`
--
ALTER TABLE `debtstatementdetail`
  ADD PRIMARY KEY (`InvoiceCode`,`ServiceCode`),
  ADD KEY `ServiceCode` (`ServiceCode`);

--
-- Indexes for table `debtstatements`
--
ALTER TABLE `debtstatements`
  ADD PRIMARY KEY (`InvoiceCode`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `fk_departments_manager` (`DepartmentManagerID`);

--
-- Indexes for table `electricitymeterreading`
--
ALTER TABLE `electricitymeterreading`
  ADD PRIMARY KEY (`ElectricityMeterID`),
  ADD KEY `ApartmentID` (`ApartmentID`),
  ADD KEY `StaffID` (`StaffID`),
  ADD KEY `ApartmentID_2` (`ApartmentID`),
  ADD KEY `StaffID_2` (`StaffID`),
  ADD KEY `ApartmentID_3` (`ApartmentID`),
  ADD KEY `StaffID_3` (`StaffID`),
  ADD KEY `ApartmentID_4` (`ApartmentID`),
  ADD KEY `StaffID_4` (`StaffID`),
  ADD KEY `ApartmentID_5` (`ApartmentID`),
  ADD KEY `StaffID_5` (`StaffID`),
  ADD KEY `ApartmentID_6` (`ApartmentID`),
  ADD KEY `StaffID_6` (`StaffID`),
  ADD KEY `ApartmentID_7` (`ApartmentID`),
  ADD KEY `StaffID_7` (`StaffID`),
  ADD KEY `ApartmentID_8` (`ApartmentID`),
  ADD KEY `StaffID_8` (`StaffID`),
  ADD KEY `ApartmentID_9` (`ApartmentID`),
  ADD KEY `StaffID_9` (`StaffID`),
  ADD KEY `ApartmentID_10` (`ApartmentID`),
  ADD KEY `StaffID_10` (`StaffID`),
  ADD KEY `ApartmentID_11` (`ApartmentID`),
  ADD KEY `StaffID_11` (`StaffID`),
  ADD KEY `ApartmentID_12` (`ApartmentID`),
  ADD KEY `StaffID_12` (`StaffID`),
  ADD KEY `ApartmentID_13` (`ApartmentID`),
  ADD KEY `StaffID_13` (`StaffID`),
  ADD KEY `ApartmentID_14` (`ApartmentID`),
  ADD KEY `StaffID_14` (`StaffID`),
  ADD KEY `ApartmentID_15` (`ApartmentID`),
  ADD KEY `StaffID_15` (`StaffID`),
  ADD KEY `ApartmentID_16` (`ApartmentID`),
  ADD KEY `StaffID_16` (`StaffID`),
  ADD KEY `ApartmentID_17` (`ApartmentID`),
  ADD KEY `StaffID_17` (`StaffID`),
  ADD KEY `ApartmentID_18` (`ApartmentID`),
  ADD KEY `StaffID_18` (`StaffID`),
  ADD KEY `ApartmentID_19` (`ApartmentID`),
  ADD KEY `StaffID_19` (`StaffID`),
  ADD KEY `ApartmentID_20` (`ApartmentID`),
  ADD KEY `StaffID_20` (`StaffID`),
  ADD KEY `ApartmentID_21` (`ApartmentID`),
  ADD KEY `StaffID_21` (`StaffID`),
  ADD KEY `ApartmentID_22` (`ApartmentID`),
  ADD KEY `StaffID_22` (`StaffID`),
  ADD KEY `ApartmentID_23` (`ApartmentID`),
  ADD KEY `StaffID_23` (`StaffID`),
  ADD KEY `ApartmentID_24` (`ApartmentID`),
  ADD KEY `StaffID_24` (`StaffID`),
  ADD KEY `ApartmentID_25` (`ApartmentID`),
  ADD KEY `StaffID_25` (`StaffID`),
  ADD KEY `ApartmentID_26` (`ApartmentID`),
  ADD KEY `StaffID_26` (`StaffID`),
  ADD KEY `ApartmentID_27` (`ApartmentID`),
  ADD KEY `StaffID_27` (`StaffID`),
  ADD KEY `ApartmentID_28` (`ApartmentID`),
  ADD KEY `StaffID_28` (`StaffID`),
  ADD KEY `ApartmentID_29` (`ApartmentID`),
  ADD KEY `StaffID_29` (`StaffID`),
  ADD KEY `ApartmentID_30` (`ApartmentID`),
  ADD KEY `StaffID_30` (`StaffID`),
  ADD KEY `ApartmentID_31` (`ApartmentID`),
  ADD KEY `StaffID_31` (`StaffID`);

--
-- Indexes for table `excesspayment`
--
ALTER TABLE `excesspayment`
  ADD PRIMARY KEY (`ExcessPaymentID`),
  ADD KEY `ApartmentID` (`ApartmentID`),
  ADD KEY `ReceiptID` (`ReceiptID`);

--
-- Indexes for table `floors`
--
ALTER TABLE `floors`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `Code` (`Code`),
  ADD KEY `BuildingId` (`BuildingId`);

--
-- Indexes for table `otherreceipt`
--
ALTER TABLE `otherreceipt`
  ADD PRIMARY KEY (`OtherReceiptID`),
  ADD KEY `StaffID` (`StaffID`),
  ADD KEY `fk_otherreceipt_apartment` (`ApartmentID`);

--
-- Indexes for table `paymentinformation`
--
ALTER TABLE `paymentinformation`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `ProjectId` (`ProjectId`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`PaymentID`),
  ADD KEY `ApartmentID` (`ApartmentID`),
  ADD KEY `StaffID` (`StaffID`);

--
-- Indexes for table `pricelist`
--
ALTER TABLE `pricelist`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `Code` (`Code`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`ProjectID`),
  ADD KEY `TownShipId` (`TownShipId`);

--
-- Indexes for table `receipt`
--
ALTER TABLE `receipt`
  ADD PRIMARY KEY (`ReceiptID`),
  ADD KEY `StaffID` (`StaffID`),
  ADD KEY `ApartmentID` (`ApartmentID`);

--
-- Indexes for table `receiptdetails`
--
ALTER TABLE `receiptdetails`
  ADD PRIMARY KEY (`ReceiptID`,`ServiceCode`);

--
-- Indexes for table `resident`
--
ALTER TABLE `resident`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `NationalId` (`NationalId`);

--
-- Indexes for table `residentapartment`
--
ALTER TABLE `residentapartment`
  ADD PRIMARY KEY (`ResidentId`,`ApartmentId`),
  ADD KEY `ApartmentId` (`ApartmentId`);

--
-- Indexes for table `serviceprice`
--
ALTER TABLE `serviceprice`
  ADD PRIMARY KEY (`ServiceId`,`PriceId`),
  ADD KEY `PriceId` (`PriceId`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`ServiceCode`),
  ADD KEY `ProjectId` (`ProjectId`);

--
-- Indexes for table `servicevehicles`
--
ALTER TABLE `servicevehicles`
  ADD PRIMARY KEY (`ServiceId`,`VehicleCode`),
  ADD KEY `VehicleCode` (`VehicleCode`);

--
-- Indexes for table `staffprojects`
--
ALTER TABLE `staffprojects`
  ADD PRIMARY KEY (`ProjectId`,`StaffId`),
  ADD KEY `StaffId` (`StaffId`);

--
-- Indexes for table `staffs`
--
ALTER TABLE `staffs`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `Email` (`Email`);

--
-- Indexes for table `townships`
--
ALTER TABLE `townships`
  ADD PRIMARY KEY (`TownShipId`),
  ADD UNIQUE KEY `Code` (`Code`),
  ADD KEY `CompanyId` (`CompanyId`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`UserId`),
  ADD KEY `fk_users_department` (`DepartmentId`),
  ADD KEY `fk_users_resident` (`ResidentID`);

--
-- Indexes for table `vehiclecards`
--
ALTER TABLE `vehiclecards`
  ADD PRIMARY KEY (`VehicleCardCode`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`VehicleCode`),
  ADD KEY `fk_vehicles_vehiclecard` (`VehicleCardCode`);

--
-- Indexes for table `watermeterreading`
--
ALTER TABLE `watermeterreading`
  ADD PRIMARY KEY (`WaterMeterID`),
  ADD KEY `ApartmentID` (`ApartmentID`),
  ADD KEY `StaffID` (`StaffID`),
  ADD KEY `ApartmentID_2` (`ApartmentID`),
  ADD KEY `StaffID_2` (`StaffID`),
  ADD KEY `ApartmentID_3` (`ApartmentID`),
  ADD KEY `StaffID_3` (`StaffID`),
  ADD KEY `ApartmentID_4` (`ApartmentID`),
  ADD KEY `StaffID_4` (`StaffID`),
  ADD KEY `ApartmentID_5` (`ApartmentID`),
  ADD KEY `StaffID_5` (`StaffID`),
  ADD KEY `ApartmentID_6` (`ApartmentID`),
  ADD KEY `StaffID_6` (`StaffID`),
  ADD KEY `ApartmentID_7` (`ApartmentID`),
  ADD KEY `StaffID_7` (`StaffID`),
  ADD KEY `ApartmentID_8` (`ApartmentID`),
  ADD KEY `StaffID_8` (`StaffID`),
  ADD KEY `ApartmentID_9` (`ApartmentID`),
  ADD KEY `StaffID_9` (`StaffID`),
  ADD KEY `ApartmentID_10` (`ApartmentID`),
  ADD KEY `StaffID_10` (`StaffID`),
  ADD KEY `ApartmentID_11` (`ApartmentID`),
  ADD KEY `StaffID_11` (`StaffID`),
  ADD KEY `ApartmentID_12` (`ApartmentID`),
  ADD KEY `StaffID_12` (`StaffID`),
  ADD KEY `ApartmentID_13` (`ApartmentID`),
  ADD KEY `StaffID_13` (`StaffID`),
  ADD KEY `ApartmentID_14` (`ApartmentID`),
  ADD KEY `StaffID_14` (`StaffID`),
  ADD KEY `ApartmentID_15` (`ApartmentID`),
  ADD KEY `StaffID_15` (`StaffID`),
  ADD KEY `ApartmentID_16` (`ApartmentID`),
  ADD KEY `StaffID_16` (`StaffID`),
  ADD KEY `ApartmentID_17` (`ApartmentID`),
  ADD KEY `StaffID_17` (`StaffID`),
  ADD KEY `ApartmentID_18` (`ApartmentID`),
  ADD KEY `StaffID_18` (`StaffID`),
  ADD KEY `ApartmentID_19` (`ApartmentID`),
  ADD KEY `StaffID_19` (`StaffID`),
  ADD KEY `ApartmentID_20` (`ApartmentID`),
  ADD KEY `StaffID_20` (`StaffID`),
  ADD KEY `ApartmentID_21` (`ApartmentID`),
  ADD KEY `StaffID_21` (`StaffID`),
  ADD KEY `ApartmentID_22` (`ApartmentID`),
  ADD KEY `StaffID_22` (`StaffID`),
  ADD KEY `ApartmentID_23` (`ApartmentID`),
  ADD KEY `StaffID_23` (`StaffID`),
  ADD KEY `ApartmentID_24` (`ApartmentID`),
  ADD KEY `StaffID_24` (`StaffID`),
  ADD KEY `ApartmentID_25` (`ApartmentID`),
  ADD KEY `StaffID_25` (`StaffID`),
  ADD KEY `ApartmentID_26` (`ApartmentID`),
  ADD KEY `StaffID_26` (`StaffID`),
  ADD KEY `ApartmentID_27` (`ApartmentID`),
  ADD KEY `StaffID_27` (`StaffID`),
  ADD KEY `ApartmentID_28` (`ApartmentID`),
  ADD KEY `StaffID_28` (`StaffID`),
  ADD KEY `ApartmentID_29` (`ApartmentID`),
  ADD KEY `StaffID_29` (`StaffID`),
  ADD KEY `ApartmentID_30` (`ApartmentID`),
  ADD KEY `StaffID_30` (`StaffID`),
  ADD KEY `ApartmentID_31` (`ApartmentID`),
  ADD KEY `StaffID_31` (`StaffID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `apartment`
--
ALTER TABLE `apartment`
  MODIFY `ApartmentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `buildings`
--
ALTER TABLE `buildings`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `CompanyId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `contractappendixs`
--
ALTER TABLE `contractappendixs`
  MODIFY `ContractAppendixId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `electricitymeterreading`
--
ALTER TABLE `electricitymeterreading`
  MODIFY `ElectricityMeterID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `excesspayment`
--
ALTER TABLE `excesspayment`
  MODIFY `ExcessPaymentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `floors`
--
ALTER TABLE `floors`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `paymentinformation`
--
ALTER TABLE `paymentinformation`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `PaymentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `pricelist`
--
ALTER TABLE `pricelist`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `ProjectID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `resident`
--
ALTER TABLE `resident`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `staffs`
--
ALTER TABLE `staffs`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `townships`
--
ALTER TABLE `townships`
  MODIFY `TownShipId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `UserId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `watermeterreading`
--
ALTER TABLE `watermeterreading`
  MODIFY `WaterMeterID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `buildings`
--
ALTER TABLE `buildings`
  ADD CONSTRAINT `buildings_ibfk_1` FOREIGN KEY (`ProjectId`) REFERENCES `projects` (`ProjectID`) ON DELETE CASCADE;

--
-- Constraints for table `contractappendixs`
--
ALTER TABLE `contractappendixs`
  ADD CONSTRAINT `contractappendixs_ibfk_1` FOREIGN KEY (`ContractCode`) REFERENCES `contracts` (`ContractCode`) ON DELETE CASCADE;

--
-- Constraints for table `contractservices`
--
ALTER TABLE `contractservices`
  ADD CONSTRAINT `contractservices_ibfk_1` FOREIGN KEY (`ContractCode`) REFERENCES `contracts` (`ContractCode`) ON DELETE CASCADE,
  ADD CONSTRAINT `contractservices_ibfk_2` FOREIGN KEY (`ServiceId`) REFERENCES `services` (`ServiceCode`) ON DELETE CASCADE;

--
-- Constraints for table `debtstatementdetail`
--
ALTER TABLE `debtstatementdetail`
  ADD CONSTRAINT `debtstatementdetail_ibfk_1` FOREIGN KEY (`InvoiceCode`) REFERENCES `debtstatements` (`InvoiceCode`) ON DELETE CASCADE,
  ADD CONSTRAINT `debtstatementdetail_ibfk_2` FOREIGN KEY (`ServiceCode`) REFERENCES `services` (`ServiceCode`) ON DELETE CASCADE;

--
-- Constraints for table `debtstatements`
--
ALTER TABLE `debtstatements`
  ADD CONSTRAINT `fk_debtstatements_resident` FOREIGN KEY (`ApartmentID`) REFERENCES `resident` (`ID`);

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `fk_departments_manager` FOREIGN KEY (`DepartmentManagerID`) REFERENCES `staffs` (`ID`);

--
-- Constraints for table `excesspayment`
--
ALTER TABLE `excesspayment`
  ADD CONSTRAINT `excesspayment_ibfk_1` FOREIGN KEY (`ApartmentID`) REFERENCES `apartment` (`ApartmentID`),
  ADD CONSTRAINT `excesspayment_ibfk_2` FOREIGN KEY (`ReceiptID`) REFERENCES `receipt` (`ReceiptID`) ON DELETE CASCADE;

--
-- Constraints for table `floors`
--
ALTER TABLE `floors`
  ADD CONSTRAINT `floors_ibfk_1` FOREIGN KEY (`BuildingId`) REFERENCES `buildings` (`ID`) ON DELETE CASCADE;

--
-- Constraints for table `otherreceipt`
--
ALTER TABLE `otherreceipt`
  ADD CONSTRAINT `fk_otherreceipt_apartment` FOREIGN KEY (`ApartmentID`) REFERENCES `apartment` (`ApartmentID`),
  ADD CONSTRAINT `otherreceipt_ibfk_1` FOREIGN KEY (`StaffID`) REFERENCES `staffs` (`ID`);

--
-- Constraints for table `paymentinformation`
--
ALTER TABLE `paymentinformation`
  ADD CONSTRAINT `paymentinformation_ibfk_1` FOREIGN KEY (`ProjectId`) REFERENCES `projects` (`ProjectID`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payment_apartment` FOREIGN KEY (`ApartmentID`) REFERENCES `apartment` (`ApartmentID`),
  ADD CONSTRAINT `fk_payment_staff` FOREIGN KEY (`StaffID`) REFERENCES `staffs` (`ID`);

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`TownShipId`) REFERENCES `townships` (`TownShipId`) ON DELETE CASCADE;

--
-- Constraints for table `receipt`
--
ALTER TABLE `receipt`
  ADD CONSTRAINT `receipt_ibfk_1` FOREIGN KEY (`StaffID`) REFERENCES `staffs` (`ID`),
  ADD CONSTRAINT `receipt_ibfk_2` FOREIGN KEY (`ApartmentID`) REFERENCES `apartment` (`ApartmentID`);

--
-- Constraints for table `receiptdetails`
--
ALTER TABLE `receiptdetails`
  ADD CONSTRAINT `receiptdetails_ibfk_1` FOREIGN KEY (`ReceiptID`) REFERENCES `receipt` (`ReceiptID`) ON DELETE CASCADE;

--
-- Constraints for table `residentapartment`
--
ALTER TABLE `residentapartment`
  ADD CONSTRAINT `residentapartment_ibfk_1` FOREIGN KEY (`ResidentId`) REFERENCES `resident` (`ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `residentapartment_ibfk_2` FOREIGN KEY (`ApartmentId`) REFERENCES `apartment` (`ApartmentID`) ON DELETE CASCADE;

--
-- Constraints for table `serviceprice`
--
ALTER TABLE `serviceprice`
  ADD CONSTRAINT `serviceprice_ibfk_1` FOREIGN KEY (`ServiceId`) REFERENCES `services` (`ServiceCode`) ON DELETE CASCADE,
  ADD CONSTRAINT `serviceprice_ibfk_2` FOREIGN KEY (`PriceId`) REFERENCES `pricelist` (`ID`) ON DELETE CASCADE;

--
-- Constraints for table `services`
--
ALTER TABLE `services`
  ADD CONSTRAINT `services_ibfk_1` FOREIGN KEY (`ProjectId`) REFERENCES `projects` (`ProjectID`) ON DELETE CASCADE;

--
-- Constraints for table `servicevehicles`
--
ALTER TABLE `servicevehicles`
  ADD CONSTRAINT `servicevehicles_ibfk_1` FOREIGN KEY (`ServiceId`) REFERENCES `services` (`ServiceCode`) ON DELETE CASCADE,
  ADD CONSTRAINT `servicevehicles_ibfk_2` FOREIGN KEY (`VehicleCode`) REFERENCES `vehicles` (`VehicleCode`) ON DELETE CASCADE;

--
-- Constraints for table `staffprojects`
--
ALTER TABLE `staffprojects`
  ADD CONSTRAINT `staffprojects_ibfk_1` FOREIGN KEY (`ProjectId`) REFERENCES `projects` (`ProjectID`) ON DELETE CASCADE,
  ADD CONSTRAINT `staffprojects_ibfk_2` FOREIGN KEY (`StaffId`) REFERENCES `staffs` (`ID`) ON DELETE CASCADE;

--
-- Constraints for table `townships`
--
ALTER TABLE `townships`
  ADD CONSTRAINT `townships_ibfk_1` FOREIGN KEY (`CompanyId`) REFERENCES `companies` (`CompanyId`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_department` FOREIGN KEY (`DepartmentId`) REFERENCES `departments` (`ID`),
  ADD CONSTRAINT `fk_users_resident` FOREIGN KEY (`ResidentID`) REFERENCES `resident` (`ID`);

--
-- Constraints for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD CONSTRAINT `fk_vehicles_vehiclecard` FOREIGN KEY (`VehicleCardCode`) REFERENCES `vehiclecards` (`VehicleCardCode`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
