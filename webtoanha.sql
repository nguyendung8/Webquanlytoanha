
CREATE TABLE `apartment` (
  `ApartmentID` int(11) NOT NULL,
  `Name` varchar(255) DEFAULT NULL,
  `Area` int(11) DEFAULT NULL,
  `NumberOffBedroom` int(11) DEFAULT NULL,
  `ApplicationFee` int(11) DEFAULT NULL,
  `Population` int(11) DEFAULT NULL,
  `Description` text DEFAULT NULL,
  `Status` varchar(50) DEFAULT NULL,
  `ResidentID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


CREATE TABLE `contracts` (
  `ContractCode` varchar(50) NOT NULL,
  `CreationDate` date DEFAULT NULL,
  `Status` varchar(50) DEFAULT NULL,
  `ResidentID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;



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
  `ResidentID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;



CREATE TABLE `departments` (
  `ID` int(11) NOT NULL,
  `Name` varchar(255) DEFAULT NULL,
  `Code` varchar(50) UNIQUE NOT NULL,
  `PhoneNumber` varchar(20) DEFAULT NULL,
  `Email` varchar(255) DEFAULT NULL,
  `Description` text DEFAULT NULL,
  `DepartmentManagerID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;



CREATE TABLE `payments` (
  `PaymentID` int(11) NOT NULL,
  `PaymentMethod` varchar(50) DEFAULT NULL,
  `PaymentType` varchar(50) DEFAULT NULL,
  `IssueDate` date DEFAULT NULL,
  `AccountingDate` date DEFAULT NULL,
  `Total` int(11) DEFAULT NULL,
  `ResidentID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


CREATE TABLE `receipts` (
  `ReceiptID` int(11) NOT NULL,
  `PaymentMethod` varchar(50) DEFAULT NULL,
  `TransactionType` varchar(50) DEFAULT NULL,
  `ReceiptType` varchar(50) DEFAULT NULL,
  `Total` int(11) DEFAULT NULL,
  `AmountDue` int(11) DEFAULT NULL,
  `Payer` varchar(255) DEFAULT NULL,
  `Address` text DEFAULT NULL,
  `AccountingDate` date DEFAULT NULL,
  `Content` text DEFAULT NULL,
  `ResidentID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unico----------------------------------


CREATE TABLE `resident` (
  `ID` int(11) NOT NULL,
  `Name` varchar(FAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


CREATE TABLE Companies (
    CompanyId INT PRIMARY KEY AUTO_INCREMENT,
    Code VARCHAR(50) UNIQUE NOT NULL,
    Name VARCHAR(255) NOT NULL
);


CREATE TABLE TownShips (
    TownShipId INT PRIMARY KEY AUTO_INCREMENT,
    Code VARCHAR(50) UNIQUE NOT NULL,
    Name VARCHAR(255) NOT NULL,
    CompanyId INT NOT NULL,
    FOREIGN KEY (CompanyId) REFERENCES Companies(CompanyId) ON DELETE CASCADE
);


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

CREATE TABLE Buildings (
    ID INT PRIMARY KEY AUTO_INCREMENT,
    Name VARCHAR(255) NOT NULL,
    Code VARCHAR(50) UNIQUE NOT NULL,
    Status VARCHAR(50) DEFAULT 'active',
    ProjectId INT NOT NULL,
    FOREIGN KEY (ProjectId) REFERENCES Projects(ProjectID) ON DELETE CASCADE
);

CREATE TABLE Floors (
    ID INT PRIMARY KEY AUTO_INCREMENT,
    Name VARCHAR(255) NOT NULL,
    Code VARCHAR(50) UNIQUE NOT NULL,
    BuildingId INT NOT NULL,
    FOREIGN KEY (BuildingId) REFERENCES Buildings(ID) ON DELETE CASCADE
);


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

CREATE TABLE StaffProjects (
    ProjectId INT NOT NULL,
    StaffId INT NOT NULL,
    PRIMARY KEY (ProjectId, StaffId),
    FOREIGN KEY (ProjectId) REFERENCES Projects(ProjectID) ON DELETE CASCADE,
    FOREIGN KEY (StaffId) REFERENCES Staffs(ID) ON DELETE CASCADE
);


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


CREATE TABLE `vehiclecards` (
  `VehicleCardCode` varchar(50) NOT NULL,
  `Status` varchar(50) DEFAULT NULL,
  `Note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


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
  `VehicleCardCode` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
