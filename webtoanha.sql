// căn hộ
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
  `ContractCode` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


//bảng kê
CREATE TABLE `debtstatements` (
  `InvoiceCode` varchar(50) NOT NULL,
  `InvoicePeriod` varchar(50) DEFAULT NULL,
  `DueDate` date DEFAULT NULL,
  `OutstandingDebt` int(11) DEFAULT NULL,
  `Discount` int(11) DEFAULT NULL,
  `Total` int(11) DEFAULT NULL,
  `PaidAmount` int(11) DEFAULT NULL,
  `RemainingBalance` int(11) DEFAULT NULL,
  `IssueDate` date DEFAULT NULL,
  `Status` varchar(50) DEFAULT NULL,
  `StaffID` int(11) DEFAULT NULL,
  `ApartmentID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

//chi tiết bảng kê
CREATE TABLE `debtstatementdetail` (
    `InvoiceCode` varchar(50) NOT NULL,
    `ServiceCode` varchar(50) NOT NULL,
    `Quantity` int(11) DEFAULT 0,
    `UnitPrice` int(11) DEFAULT 0,
    `Discount` int(11) DEFAULT 0,
    `PaidAmount` int(11) DEFAULT 0,
    `RemainingBalance` int(11) DEFAULT 0,
    `IssueDate` date,
    PRIMARY KEY (`InvoiceCode`, `ServiceCode`),
    FOREIGN KEY (`InvoiceCode`) REFERENCES `debtstatements`(`InvoiceCode`) ON DELETE CASCADE,
    FOREIGN KEY (`ServiceCode`) REFERENCES `services`(`ServiceCode`) ON DELETE CASCADE
);


// phòng ban
CREATE TABLE `departments` (
  `ID` int(11) NOT NULL,
  `Name` varchar(255) DEFAULT NULL,
  `Code` varchar(50) UNIQUE NOT NULL,
  `PhoneNumber` varchar(20) DEFAULT NULL,
  `Email` varchar(255) DEFAULT NULL,
  `Description` text DEFAULT NULL,
  `DepartmentManagerID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


// phiếu chi
CREATE TABLE `payments` (
  `PaymentID` int(11) NOT NULL,
  `PaymentMethod` varchar(50) DEFAULT NULL,
  `IssueDate` date DEFAULT NULL,
  `AccountingDate` date DEFAULT NULL,
  `Total` int(11) DEFAULT NULL,
  `Content` text DEFAULT NULL,
  `ApartmentID` int(11) DEFAULT NULL,
  `StaffID` int(11) DEFAULT NULL,
  `DeletedBy` int(11) DEFAULT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
    FOREIGN KEY (ApartmentID) REFERENCES Apartment(ApartmentID) ON DELETE CASCADE,
  FOREIGN KEY (StaffID) REFERENCES Staffs(ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


// phiếu thu
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

// chi tiết phiếu thu
CREATE TABLE `receiptdetails` (
  `ReceiptID` varchar(20) NOT NULL,
  `ServiceCode` varchar(50) NOT NULL COMMENT 'Mã dịch vụ',
  `Incurred` decimal(15,2) DEFAULT 0.00 COMMENT 'Số tiền phát sinh',
  `Discount` decimal(15,2) DEFAULT 0.00 COMMENT 'Giảm giá',
  `Payment` decimal(15,2) DEFAULT 0.00 COMMENT 'Số tiền thanh toán',
  `Note` text DEFAULT NULL COMMENT 'Ghi chú'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


// phiếu thu khác
CREATE TABLE `OtherReceipt` (
  `OtherReceiptID` varchar(20) NOT NULL PRIMARY KEY,
  `ApartmentID` int(11) DEFAULT NULL,
  `Quantity` int DEFAULT NULL,
  `Price` decimal(10,2) DEFAULT NULL,
  `PaymentMethod` varchar(50) DEFAULT NULL,
  `Payer` varchar(100) DEFAULT NULL,
  `Total` decimal(10,2) DEFAULT NULL,
  `AccountingDate` date DEFAULT NULL,
  `Content` text,
  `StaffID` int,
  FOREIGN KEY (StaffID) REFERENCES staffs(ID),
  FOREIGN KEY (ApartmentID) REFERENCES apartment(ApartmentID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

// cư dân
CREATE TABLE `resident` (
  `ID` int(11) NOT NULL,
  `NationalId` varchar(50) DEFAULT NULL,
  `Dob` date DEFAULT NULL,
  `Gender` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

// bảng liên kết cư dân và căn hộ
CREATE TABLE ResidentApartment (
    ResidentId INT NOT NULL,
    ApartmentId INT NOT NULL,
    Relationship VARCHAR(100),
    PRIMARY KEY (ResidentId, ApartmentId),
    FOREIGN KEY (ResidentId) REFERENCES Resident(ID) ON DELETE CASCADE,
    FOREIGN KEY (ApartmentId) REFERENCES Apartment(ApartmentID) ON DELETE CASCADE
);

// công ty
CREATE TABLE Companies (
    CompanyId INT PRIMARY KEY AUTO_INCREMENT,
    Code VARCHAR(50) UNIQUE NOT NULL,
    Name VARCHAR(255) NOT NULL
);

// phường xã
CREATE TABLE TownShips (
    TownShipId INT PRIMARY KEY AUTO_INCREMENT,
    Code VARCHAR(50) UNIQUE NOT NULL,
    Name VARCHAR(255) NOT NULL,
    CompanyId INT NOT NULL,
    FOREIGN KEY (CompanyId) REFERENCES Companies(CompanyId) ON DELETE CASCADE
);

// dự án
CREATE TABLE Projects (
    ProjectID INT PRIMARY KEY AUTO_INCREMENT,
    Name VARCHAR(255) NOT NULL,
    Address TEXT NOT NULL,
    Phone VARCHAR(20),
    Email VARCHAR(255),
    Deadlock DATE,
    Description TEXT,
    OperationId VARCHAR(50),
    TownShipId INT NOT NULL,
    ManagerId INT NOT NULL,
    Status VARCHAR(50) DEFAULT 'active',
    FOREIGN KEY (TownShipId) REFERENCES TownShips(TownShipId) ON DELETE CASCADE,
    FOREIGN KEY (ManagerId) REFERENCES Staffs(ID) ON DELETE SET NULL
)

// tiền thừa
CREATE TABLE `excesspayment` (
  `ExcessPaymentID` int(11) NOT NULL,
  `OccurrenceDate` date NOT NULL,
  `Total` int(15) NOT NULL,
  `ApartmentID` int(11) NOT NULL,
  `ReceiptID` varchar(20) DEFAULT NULL,
  `Description` text DEFAULT NULL,
  `Status` varchar(50) DEFAULT 'active',
  FOREIGN KEY (ApartmentID) REFERENCES Apartment(ApartmentID) ON DELETE CASCADE,
  FOREIGN KEY (ReceiptID) REFERENCES Receipt(ReceiptID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

// thông tin thanh toán
CREATE TABLE PaymentInformation (
    Id INT PRIMARY KEY AUTO_INCREMENT,
    AccountName VARCHAR(255) NOT NULL,
    AccountNumber VARCHAR(50) NOT NULL,
    Bank VARCHAR(255) NOT NULL,
    Branch VARCHAR(255),
    ProjectId INT NOT NULL,
    AutoTransaction TINYINT(1) DEFAULT 0,
    AutoReconciliation TINYINT(1) DEFAULT 0,
    FOREIGN KEY (ProjectId) REFERENCES Projects(ProjectID) ON DELETE CASCADE
);

// tòa nhà
CREATE TABLE Buildings (
    ID INT PRIMARY KEY AUTO_INCREMENT,
    Name VARCHAR(255) NOT NULL,
    Code VARCHAR(50) UNIQUE NOT NULL,
    Status VARCHAR(50) DEFAULT 'active',
    ProjectId INT NOT NULL,
    FOREIGN KEY (ProjectId) REFERENCES Projects(ProjectID) ON DELETE CASCADE
);

// tầng
CREATE TABLE Floors (
    ID INT PRIMARY KEY AUTO_INCREMENT,
    Name VARCHAR(255) NOT NULL,
    Code VARCHAR(50) UNIQUE NOT NULL,
    BuildingId INT NOT NULL,
    FOREIGN KEY (BuildingId) REFERENCES Buildings(ID) ON DELETE CASCADE
);

// nhân viên
CREATE TABLE `staffs` (
  `ID` int(11) NOT NULL,
  `Name` varchar(255) DEFAULT NULL,
  `Email` varchar(255) DEFAULT NULL,
  `PhoneNumber` varchar(20) DEFAULT NULL,
  `Position` varchar(100) DEFAULT NULL,
  `Address` text DEFAULT NULL,
  `DepartmentId` int(11) DEFAULT NULL,
  `NationalID` varchar(50) DEFAULT NULL,
  `Status` varchar(50) DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

// bảng liên kết nhân viên và dự án
CREATE TABLE StaffProjects (
    ProjectId INT NOT NULL,
    StaffId INT NOT NULL,
    PRIMARY KEY (ProjectId, StaffId),
    FOREIGN KEY (ProjectId) REFERENCES Projects(ProjectID) ON DELETE CASCADE,
    FOREIGN KEY (StaffId) REFERENCES Staffs(ID) ON DELETE CASCADE
);

// tài khoản người dùng
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

// dịch vụ
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
  `ProjectId` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

// bảng liên kết dịch vụ và danh sách giá
CREATE TABLE ServicePrice (
    ServiceId VARCHAR(50) NOT NULL,
    PriceId INT NOT NULL,
    PRIMARY KEY (ServiceId, PriceId),
    FOREIGN KEY (ServiceId) REFERENCES Services(ServiceCode) ON DELETE CASCADE,
    FOREIGN KEY (PriceId) REFERENCES PriceList(ID) ON DELETE CASCADE
);

// danh sách giá
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
  `VariableData` TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

// thẻ phương tiện
CREATE TABLE `vehiclecards` (
  `VehicleCardCode` varchar(50) NOT NULL,
  `Status` varchar(50) DEFAULT 'Chưa cấp phát',
  `Note` text DEFAULT NULL,
  `VehicleType` varchar(100) DEFAULT NULL,
  `NumberPlate` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

// phương tiện
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
  `VehicleCardCode` varchar(50) DEFAULT 
  `VehicleOwnerID` int(11) DEFAULT NULL
  `ApartmentID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

// bảng liên kết dịch vụ và phương tiện
CREATE TABLE ServiceVehicles (
    ServiceId VARCHAR(50) NOT NULL,
    VehicleCode VARCHAR(50) NOT NULL,
    ApplyFeeDate DATE,
    EndFeeDate DATE,
    PRIMARY KEY (ServiceId, VehicleCode),
    FOREIGN KEY (ServiceId) REFERENCES Services(ServiceCode) ON DELETE CASCADE,
    FOREIGN KEY (VehicleCode) REFERENCES Vehicles(VehicleCode) ON DELETE CASCADE
);

// hợp đồng
CREATE TABLE Contracts (
    ContractCode VARCHAR(50) PRIMARY KEY,
    Status VARCHAR(50) DEFAULT 'active',
    CretionDate DATE,
    File TEXT NULL,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    EndDate DATE
);

// bảng liên kết hợp đồng và dịch vụ
CREATE TABLE ContractServices (
    ContractCode VARCHAR(50) NOT NULL,
    ServiceId VARCHAR(50) NOT NULL,
    ApplyDate DATE,
    EndDate DATE,
    PRIMARY KEY (ContractCode, ServiceId),
    FOREIGN KEY (ContractCode) REFERENCES Contracts(ContractCode) ON DELETE CASCADE,
    FOREIGN KEY (ServiceId) REFERENCES Services(ServiceCode) ON DELETE CASCADE
);

// phụ lục hợp đồng
CREATE TABLE ContractAppendixs (
    ContractAppendixId INT AUTO_INCREMENT PRIMARY KEY,
    Status VARCHAR(50) DEFAULT 'active',
    CretionDate DATE,
    ContractCode VARCHAR(50),
    FOREIGN KEY (ContractCode) REFERENCES Contracts(ContractCode) ON DELETE CASCADE
);

// chỉ số nước
CREATE TABLE `WaterMeterReading` (
  `WaterMeterID` int(11) NOT NULL AUTO_INCREMENT,
  `InitialReading` float DEFAULT NULL,
  `FinalReading` float DEFAULT NULL,
  `Image` varchar(255) DEFAULT NULL,
  `ClosingDate` date DEFAULT NULL,
  `Consumption` float DEFAULT NULL,
  `ApartmentID` int(11) NOT NULL,
  `StaffID` int(11) NOT NULL,
  PRIMARY KEY (`WaterMeterID`),
  FOREIGN KEY (`ApartmentID`) REFERENCES `apartment`(`ApartmentID`) ON DELETE CASCADE,
  FOREIGN KEY (`StaffID`) REFERENCES `staffs`(`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

// chỉ số điện
CREATE TABLE `ElectricityMeterReading` (
  `ElectricityMeterID` int(11) NOT NULL AUTO_INCREMENT,
  `InitialReading` float DEFAULT NULL,
  `FinalReading` float DEFAULT NULL,
  `Image` varchar(255) DEFAULT NULL,
  `ClosingDate` date DEFAULT NULL,
  `Consumption` float DEFAULT NULL,
  `ApartmentID` int(11) NOT NULL,
  `StaffID` int(11) NOT NULL,
  PRIMARY KEY (`ElectricityMeterID`),
  FOREIGN KEY (`ApartmentID`) REFERENCES `apartment`(`ApartmentID`) ON DELETE CASCADE,
  FOREIGN KEY (`StaffID`) REFERENCES `staffs`(`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

