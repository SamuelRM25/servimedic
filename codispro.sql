-- MySQL dump 10.13  Distrib 8.0.44, for macos11.7 (x86_64)
--
-- Host: bkzonlznatzzfkelstum-mysql.services.clever-cloud.com    Database: bkzonlznatzzfkelstum
-- ------------------------------------------------------
-- Server version	8.0.22-13

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
SET @MYSQLDUMP_TEMP_LOG_BIN = @@SESSION.SQL_LOG_BIN;
SET @@SESSION.SQL_LOG_BIN= 0;

--
-- GTID state at the beginning of the backup 
--

SET @@GLOBAL.GTID_PURGED=/*!80000 '+'*/ 'a05a675a-1414-11e9-9c82-cecd01b08c7e:1-491550428,
a38a16d0-767a-11eb-abe2-cecd029e558e:1-711837987';

--
-- Current Database: `bkzonlznatzzfkelstum`
--

/*!40000 DROP DATABASE IF EXISTS `bkzonlznatzzfkelstum`*/;

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `bkzonlznatzzfkelstum` /*!40100 DEFAULT CHARACTER SET utf8 */ /*!80016 DEFAULT ENCRYPTION='N' */;

USE `bkzonlznatzzfkelstum`;

--
-- Table structure for table `AccessLog`
--

DROP TABLE IF EXISTS `AccessLog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AccessLog` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `terminalId` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `workerId` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `userId` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `doorName` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('SUCCESS','DENIED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SUCCESS',
  `timestamp` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  KEY `AccessLog_terminalId_idx` (`terminalId`),
  KEY `AccessLog_workerId_idx` (`workerId`),
  KEY `AccessLog_userId_idx` (`userId`),
  KEY `AccessLog_timestamp_idx` (`timestamp`),
  KEY `AccessLog_status_idx` (`status`),
  CONSTRAINT `AccessLog_terminalId_fkey` FOREIGN KEY (`terminalId`) REFERENCES `Terminal` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `AccessLog_userId_fkey` FOREIGN KEY (`userId`) REFERENCES `User` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `AccessLog_workerId_fkey` FOREIGN KEY (`workerId`) REFERENCES `Worker` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `AccessLog`
--

/*!40000 ALTER TABLE `AccessLog` DISABLE KEYS */;
/*!40000 ALTER TABLE `AccessLog` ENABLE KEYS */;

--
-- Table structure for table `Client`
--

DROP TABLE IF EXISTS `Client`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Client` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nit` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `createdAt` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updatedAt` datetime(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `Client_nit_key` (`nit`),
  KEY `Client_nit_idx` (`nit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Client`
--

/*!40000 ALTER TABLE `Client` DISABLE KEYS */;
/*!40000 ALTER TABLE `Client` ENABLE KEYS */;

--
-- Table structure for table `Company`
--

DROP TABLE IF EXISTS `Company`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Company` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nit` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `felAlias` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `felToken` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `isActive` tinyint(1) NOT NULL DEFAULT '1',
  `createdAt` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updatedAt` datetime(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `Company_nit_key` (`nit`),
  KEY `Company_nit_idx` (`nit`),
  KEY `Company_isActive_idx` (`isActive`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Company`
--

/*!40000 ALTER TABLE `Company` DISABLE KEYS */;
/*!40000 ALTER TABLE `Company` ENABLE KEYS */;

--
-- Table structure for table `Invoice`
--

DROP TABLE IF EXISTS `Invoice`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Invoice` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `companyId` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `clientId` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `userId` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `projectId` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `numero` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `serie` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cae` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `certDate` datetime(3) DEFAULT NULL,
  `currency` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'GTQ',
  `subTotal` decimal(16,2) NOT NULL,
  `tax` decimal(16,2) NOT NULL,
  `total` decimal(16,2) NOT NULL,
  `status` enum('DRAFT','ISSUED','CANCELLED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `xmlUrl` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pdfUrl` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `createdAt` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updatedAt` datetime(3) NOT NULL,
  `invoiceType` enum('ISSUED','RECEIVED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ISSUED',
  PRIMARY KEY (`id`),
  KEY `Invoice_projectId_idx` (`projectId`),
  KEY `Invoice_companyId_idx` (`companyId`),
  KEY `Invoice_status_idx` (`status`),
  KEY `Invoice_numero_idx` (`numero`),
  KEY `Invoice_cae_idx` (`cae`),
  KEY `Invoice_clientId_idx` (`clientId`),
  KEY `Invoice_userId_idx` (`userId`),
  CONSTRAINT `Invoice_clientId_fkey` FOREIGN KEY (`clientId`) REFERENCES `Client` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `Invoice_companyId_fkey` FOREIGN KEY (`companyId`) REFERENCES `Company` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `Invoice_projectId_fkey` FOREIGN KEY (`projectId`) REFERENCES `Project` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `Invoice_userId_fkey` FOREIGN KEY (`userId`) REFERENCES `User` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Invoice`
--

/*!40000 ALTER TABLE `Invoice` DISABLE KEYS */;
/*!40000 ALTER TABLE `Invoice` ENABLE KEYS */;

--
-- Table structure for table `InvoiceItem`
--

DROP TABLE IF EXISTS `InvoiceItem`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `InvoiceItem` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `invoiceId` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` decimal(12,3) NOT NULL,
  `unitPrice` decimal(12,4) NOT NULL,
  `total` decimal(16,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `InvoiceItem_invoiceId_idx` (`invoiceId`),
  CONSTRAINT `InvoiceItem_invoiceId_fkey` FOREIGN KEY (`invoiceId`) REFERENCES `Invoice` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `InvoiceItem`
--

/*!40000 ALTER TABLE `InvoiceItem` DISABLE KEYS */;
/*!40000 ALTER TABLE `InvoiceItem` ENABLE KEYS */;

--
-- Table structure for table `LocationLog`
--

DROP TABLE IF EXISTS `LocationLog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `LocationLog` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `userId` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vehicleId` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` double NOT NULL,
  `longitude` double NOT NULL,
  `accuracy` double DEFAULT NULL,
  `timestamp` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  KEY `LocationLog_vehicleId_idx` (`vehicleId`),
  KEY `LocationLog_timestamp_idx` (`timestamp`),
  KEY `LocationLog_userId_idx` (`userId`),
  CONSTRAINT `LocationLog_userId_fkey` FOREIGN KEY (`userId`) REFERENCES `User` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `LocationLog`
--

/*!40000 ALTER TABLE `LocationLog` DISABLE KEYS */;
/*!40000 ALTER TABLE `LocationLog` ENABLE KEYS */;

--
-- Table structure for table `PayrollEntry`
--

DROP TABLE IF EXISTS `PayrollEntry`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PayrollEntry` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `projectId` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `workerId` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `userId` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `period` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hours` decimal(8,2) DEFAULT NULL,
  `overtime` decimal(8,2) DEFAULT NULL,
  `salary` decimal(12,2) NOT NULL,
  `bonuses` decimal(12,2) NOT NULL DEFAULT '0.00',
  `deductions` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total` decimal(12,2) NOT NULL,
  `notes` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `createdAt` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updatedAt` datetime(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `PayrollEntry_projectId_idx` (`projectId`),
  KEY `PayrollEntry_period_idx` (`period`),
  KEY `PayrollEntry_workerId_idx` (`workerId`),
  KEY `PayrollEntry_userId_idx` (`userId`),
  CONSTRAINT `PayrollEntry_projectId_fkey` FOREIGN KEY (`projectId`) REFERENCES `Project` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `PayrollEntry_userId_fkey` FOREIGN KEY (`userId`) REFERENCES `User` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `PayrollEntry_workerId_fkey` FOREIGN KEY (`workerId`) REFERENCES `Worker` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `PayrollEntry`
--

/*!40000 ALTER TABLE `PayrollEntry` DISABLE KEYS */;
/*!40000 ALTER TABLE `PayrollEntry` ENABLE KEYS */;

--
-- Table structure for table `PettyCash`
--

DROP TABLE IF EXISTS `PettyCash`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PettyCash` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('INCOME','EXPENSE') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(16,2) NOT NULL,
  `category` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `userId` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `projectId` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receipt` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `createdAt` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updatedAt` datetime(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `PettyCash_projectId_idx` (`projectId`),
  KEY `PettyCash_type_idx` (`type`),
  KEY `PettyCash_date_idx` (`date`),
  KEY `PettyCash_userId_idx` (`userId`),
  CONSTRAINT `PettyCash_projectId_fkey` FOREIGN KEY (`projectId`) REFERENCES `Project` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `PettyCash_userId_fkey` FOREIGN KEY (`userId`) REFERENCES `User` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `PettyCash`
--

/*!40000 ALTER TABLE `PettyCash` DISABLE KEYS */;
/*!40000 ALTER TABLE `PettyCash` ENABLE KEYS */;

--
-- Table structure for table `Project`
--

DROP TABLE IF EXISTS `Project`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Project` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `startDate` datetime(3) NOT NULL,
  `endDate` datetime(3) DEFAULT NULL,
  `status` enum('PLANNING','ACTIVE','ON_HOLD','COMPLETED','CANCELLED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PLANNING',
  `budget` double DEFAULT NULL,
  `progress` double NOT NULL DEFAULT '0',
  `client` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `createdAt` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updatedAt` datetime(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `Project_code_key` (`code`),
  KEY `Project_code_idx` (`code`),
  KEY `Project_status_idx` (`status`),
  KEY `Project_client_idx` (`client`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Project`
--

/*!40000 ALTER TABLE `Project` DISABLE KEYS */;
INSERT INTO `Project` VALUES ('cmkymbyrv000k9m2imlv7xtq5','Oficina','OFC','Oficina','Chuscaj, Chiantla','2026-01-28 00:00:00.000','2026-01-28 00:00:00.000','ACTIVE',0,0,'CODISPRO','2026-01-28 22:50:16.509','2026-01-28 22:50:16.509');
/*!40000 ALTER TABLE `Project` ENABLE KEYS */;

--
-- Table structure for table `ProjectExpense`
--

DROP TABLE IF EXISTS `ProjectExpense`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ProjectExpense` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `projectId` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(16,2) NOT NULL,
  `description` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `receipt` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `createdAt` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updatedAt` datetime(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ProjectExpense_projectId_idx` (`projectId`),
  KEY `ProjectExpense_category_idx` (`category`),
  KEY `ProjectExpense_date_idx` (`date`),
  CONSTRAINT `ProjectExpense_projectId_fkey` FOREIGN KEY (`projectId`) REFERENCES `Project` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ProjectExpense`
--

/*!40000 ALTER TABLE `ProjectExpense` DISABLE KEYS */;
/*!40000 ALTER TABLE `ProjectExpense` ENABLE KEYS */;

--
-- Table structure for table `ProjectStaff`
--

DROP TABLE IF EXISTS `ProjectStaff`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ProjectStaff` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `projectId` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `workerId` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `startDate` datetime(3) NOT NULL,
  `endDate` datetime(3) DEFAULT NULL,
  `salary` decimal(12,2) DEFAULT NULL,
  `createdAt` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updatedAt` datetime(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ProjectStaff_projectId_idx` (`projectId`),
  KEY `ProjectStaff_workerId_idx` (`workerId`),
  CONSTRAINT `ProjectStaff_projectId_fkey` FOREIGN KEY (`projectId`) REFERENCES `Project` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ProjectStaff_workerId_fkey` FOREIGN KEY (`workerId`) REFERENCES `Worker` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ProjectStaff`
--

/*!40000 ALTER TABLE `ProjectStaff` DISABLE KEYS */;
/*!40000 ALTER TABLE `ProjectStaff` ENABLE KEYS */;

--
-- Table structure for table `Shipment`
--

DROP TABLE IF EXISTS `Shipment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Shipment` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vehicleId` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `driverId` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `projectId` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `userId` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipmentDate` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `materialName` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sentQuantity` decimal(12,3) DEFAULT NULL,
  `receivedQuantity` decimal(12,3) DEFAULT NULL,
  `receivedDate` datetime(3) DEFAULT NULL,
  `status` enum('PENDING','VERIFIED','IN_TRANSIT','RECEIVED','DISCREPANCY') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDING',
  `notes` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `createdAt` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updatedAt` datetime(3) NOT NULL,
  `arrivalPhoto` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `arrivalTime` datetime(3) DEFAULT NULL,
  `departurePhoto` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `departureTime` datetime(3) DEFAULT NULL,
  `receivedNotes` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receiverId` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `arrivalPoint` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `authorizeName` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `departurePoint` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `firma` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mode` enum('INMEDIATO','PROGRAMADO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INMEDIATO',
  `pdfUrl` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `printedAt` datetime(3) DEFAULT NULL,
  `scheduledAt` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `Shipment_driverId_idx` (`driverId`),
  KEY `Shipment_projectId_idx` (`projectId`),
  KEY `Shipment_status_idx` (`status`),
  KEY `Shipment_shipmentDate_idx` (`shipmentDate`),
  KEY `Shipment_vehicleId_idx` (`vehicleId`),
  KEY `Shipment_userId_idx` (`userId`),
  CONSTRAINT `Shipment_driverId_fkey` FOREIGN KEY (`driverId`) REFERENCES `Worker` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `Shipment_projectId_fkey` FOREIGN KEY (`projectId`) REFERENCES `Project` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `Shipment_userId_fkey` FOREIGN KEY (`userId`) REFERENCES `User` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `Shipment_vehicleId_fkey` FOREIGN KEY (`vehicleId`) REFERENCES `Vehicle` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Shipment`
--

/*!40000 ALTER TABLE `Shipment` DISABLE KEYS */;
INSERT INTO `Shipment` VALUES ('cmqb8wlsd00019k8akwck3qik','cmqb5ovks00009ka99v391bjt','cmqb6ovgq00009krp8l0xduxv','cmkymbyrv000k9m2imlv7xtq5','cmku1mi6u0001z0t65ht46jf8','2026-06-12 18:13:56.798',NULL,NULL,NULL,NULL,'PENDING','Este es un envío de prueba','2026-06-12 18:13:56.800','2026-06-12 18:14:01.447',NULL,NULL,NULL,NULL,NULL,NULL,'San Rafael La Independencia','Samuel Ramírez','Taller','Samuel Ramírez','INMEDIATO','/uploads/shipment-cmqb8wlsd00019k8akwck3qik-fc61e783-1404-4fb3-b92f-703d0a6feec5.pdf','2026-06-12 18:14:01.446',NULL);
/*!40000 ALTER TABLE `Shipment` ENABLE KEYS */;

--
-- Table structure for table `ShipmentItem`
--

DROP TABLE IF EXISTS `ShipmentItem`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ShipmentItem` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipmentId` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `materialName` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sentQuantity` decimal(12,3) NOT NULL,
  `unit` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unidad',
  `receivedQuantity` decimal(12,3) DEFAULT NULL,
  `receivedNotes` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ShipmentItem_shipmentId_idx` (`shipmentId`),
  CONSTRAINT `ShipmentItem_shipmentId_fkey` FOREIGN KEY (`shipmentId`) REFERENCES `Shipment` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ShipmentItem`
--

/*!40000 ALTER TABLE `ShipmentItem` DISABLE KEYS */;
INSERT INTO `ShipmentItem` VALUES ('cmqb8wlsd00029k8au6n257u8','cmqb8wlsd00019k8akwck3qik','Cemento',100.000,'bolsa',NULL,NULL);
/*!40000 ALTER TABLE `ShipmentItem` ENABLE KEYS */;

--
-- Table structure for table `Terminal`
--

DROP TABLE IF EXISTS `Terminal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Terminal` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `port` int NOT NULL DEFAULT '80',
  `username` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin',
  `password` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `serialNumber` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lastSeen` datetime(3) DEFAULT NULL,
  `isActive` tinyint(1) NOT NULL DEFAULT '1',
  `createdAt` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updatedAt` datetime(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `Terminal_serialNumber_key` (`serialNumber`),
  KEY `Terminal_ip_idx` (`ip`),
  KEY `Terminal_isActive_idx` (`isActive`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Terminal`
--

/*!40000 ALTER TABLE `Terminal` DISABLE KEYS */;
/*!40000 ALTER TABLE `Terminal` ENABLE KEYS */;

--
-- Table structure for table `Tool`
--

DROP TABLE IF EXISTS `Tool`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Tool` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `barcode` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('AVAILABLE','IN_USE','MAINTENANCE','RETIRED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'AVAILABLE',
  `location` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `createdAt` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updatedAt` datetime(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `Tool_barcode_key` (`barcode`),
  KEY `Tool_barcode_idx` (`barcode`),
  KEY `Tool_status_idx` (`status`),
  KEY `Tool_category_idx` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Tool`
--

/*!40000 ALTER TABLE `Tool` DISABLE KEYS */;
/*!40000 ALTER TABLE `Tool` ENABLE KEYS */;

--
-- Table structure for table `ToolLoan`
--

DROP TABLE IF EXISTS `ToolLoan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ToolLoan` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `toolId` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `userId` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `workerId` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `loanDate` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `returnDate` datetime(3) DEFAULT NULL,
  `notes` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `createdAt` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updatedAt` datetime(3) NOT NULL,
  `notified` tinyint(1) NOT NULL DEFAULT '0',
  `photo` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ToolLoan_toolId_idx` (`toolId`),
  KEY `ToolLoan_workerId_idx` (`workerId`),
  KEY `ToolLoan_loanDate_idx` (`loanDate`),
  KEY `ToolLoan_userId_idx` (`userId`),
  CONSTRAINT `ToolLoan_toolId_fkey` FOREIGN KEY (`toolId`) REFERENCES `Tool` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ToolLoan_userId_fkey` FOREIGN KEY (`userId`) REFERENCES `User` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ToolLoan_workerId_fkey` FOREIGN KEY (`workerId`) REFERENCES `Worker` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ToolLoan`
--

/*!40000 ALTER TABLE `ToolLoan` DISABLE KEYS */;
/*!40000 ALTER TABLE `ToolLoan` ENABLE KEYS */;

--
-- Table structure for table `User`
--

DROP TABLE IF EXISTS `User`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `User` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('ADMIN','MANAGER','WORKER','BODEGA','VEHICULO','RECEPTOR') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'WORKER',
  `isActive` tinyint(1) NOT NULL DEFAULT '1',
  `createdAt` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updatedAt` datetime(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `User_code_key` (`code`),
  KEY `User_code_idx` (`code`),
  KEY `User_role_idx` (`role`),
  KEY `User_isActive_idx` (`isActive`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `User`
--

/*!40000 ALTER TABLE `User` DISABLE KEYS */;
INSERT INTO `User` VALUES ('cmku1mi6u0000z0t6v9fthgy2','ADMIN1','Esmeily Saucedo','ADMIN',1,'2026-01-25 17:59:31.734','2026-06-12 14:49:41.952'),('cmku1mi6u0001z0t65ht46jf8','ADMIN2','Samuel Ramírez','ADMIN',1,'2026-01-25 17:59:31.734','2026-06-12 14:49:53.473'),('cmku1mi6u0002z0t6bvhk9wfh','ADMIN3','Edvin Saucedo','WORKER',1,'2026-01-25 17:59:31.734','2026-06-12 15:33:24.423'),('cmku1mi6u0003z0t62069n829','ADMIN4','Hazly Saucedo','ADMIN',1,'2026-01-25 17:59:31.734','2026-06-12 14:50:16.415'),('cmnz5hpww0000z0wjllre6t0r','BODEGA01','Bodeguero Principal','BODEGA',1,'2026-04-14 21:45:44.816','2026-04-14 21:45:44.816'),('cmnz5hqpv0001z0wjeiif8pc6','VEH01','Conductor 1','VEHICULO',1,'2026-04-14 21:45:45.859','2026-04-14 21:45:45.859'),('cmnz5hra80002z0wjc23zcmtv','VEH02','Conductor 2','VEHICULO',1,'2026-04-14 21:45:46.593','2026-04-14 21:45:46.593'),('cmnz5hsfg0004z0wjd622iz5k','REC02','Receptor 2','RECEPTOR',1,'2026-04-14 21:45:48.077','2026-04-14 21:45:48.077'),('cmqb1k06g00059kedm25ku6kz','REC01','Receptor 1','RECEPTOR',1,'2026-06-12 14:48:11.753','2026-06-12 14:48:11.753'),('cmqb1myca00069kedkyibe0uu','ADMIN5','Vivi Saucedo','ADMIN',1,'2026-06-12 14:50:29.339','2026-06-12 14:51:09.721');
/*!40000 ALTER TABLE `User` ENABLE KEYS */;

--
-- Table structure for table `Vehicle`
--

DROP TABLE IF EXISTS `Vehicle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Vehicle` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `plate` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('TRUCK','MACHINE','VAN','CAR') COLLATE utf8mb4_unicode_ci NOT NULL,
  `brand` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `year` int DEFAULT NULL,
  `color` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('AVAILABLE','IN_USE','MAINTENANCE','RETIRED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'AVAILABLE',
  `mileage` decimal(12,2) DEFAULT NULL,
  `fuelType` enum('GASOLINE','DIESEL','ELECTRIC','HYBRID') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `createdAt` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updatedAt` datetime(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `Vehicle_plate_key` (`plate`),
  KEY `Vehicle_plate_idx` (`plate`),
  KEY `Vehicle_type_idx` (`type`),
  KEY `Vehicle_status_idx` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Vehicle`
--

/*!40000 ALTER TABLE `Vehicle` DISABLE KEYS */;
INSERT INTO `Vehicle` VALUES ('cmqb5ovks00009ka99v391bjt','Camión Test','TEST-001','TRUCK',NULL,NULL,NULL,NULL,NULL,'AVAILABLE',NULL,NULL,'2026-06-12 16:43:57.532','2026-06-12 16:43:57.532'),('cmqb8ktia00039krpnop38ojc','Test Final','P-111AAA','TRUCK','Hino',NULL,2025,NULL,NULL,'AVAILABLE',NULL,NULL,'2026-06-12 18:04:47.075','2026-06-12 18:04:47.075');
/*!40000 ALTER TABLE `Vehicle` ENABLE KEYS */;

--
-- Table structure for table `VehicleSparePart`
--

DROP TABLE IF EXISTS `VehicleSparePart`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `VehicleSparePart` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vehicleId` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` int NOT NULL,
  `unitPrice` decimal(10,2) NOT NULL,
  `purchaseDate` datetime(3) DEFAULT NULL,
  `invoicePhoto` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `createdAt` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updatedAt` datetime(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `VehicleSparePart_vehicleId_idx` (`vehicleId`),
  CONSTRAINT `VehicleSparePart_vehicleId_fkey` FOREIGN KEY (`vehicleId`) REFERENCES `Vehicle` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `VehicleSparePart`
--

/*!40000 ALTER TABLE `VehicleSparePart` DISABLE KEYS */;
/*!40000 ALTER TABLE `VehicleSparePart` ENABLE KEYS */;

--
-- Table structure for table `VehicleTrip`
--

DROP TABLE IF EXISTS `VehicleTrip`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `VehicleTrip` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vehicleId` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `driverId` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `userId` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `startDate` datetime(3) NOT NULL,
  `endDate` datetime(3) DEFAULT NULL,
  `origin` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `destination` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `distance` decimal(10,2) DEFAULT NULL,
  `purpose` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `createdAt` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updatedAt` datetime(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `VehicleTrip_vehicleId_idx` (`vehicleId`),
  KEY `VehicleTrip_driverId_idx` (`driverId`),
  KEY `VehicleTrip_startDate_idx` (`startDate`),
  KEY `VehicleTrip_userId_idx` (`userId`),
  CONSTRAINT `VehicleTrip_driverId_fkey` FOREIGN KEY (`driverId`) REFERENCES `Worker` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `VehicleTrip_userId_fkey` FOREIGN KEY (`userId`) REFERENCES `User` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `VehicleTrip_vehicleId_fkey` FOREIGN KEY (`vehicleId`) REFERENCES `Vehicle` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `VehicleTrip`
--

/*!40000 ALTER TABLE `VehicleTrip` DISABLE KEYS */;
/*!40000 ALTER TABLE `VehicleTrip` ENABLE KEYS */;

--
-- Table structure for table `Worker`
--

DROP TABLE IF EXISTS `Worker`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Worker` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `firstName` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lastName` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dpi` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birthDate` datetime(3) DEFAULT NULL,
  `phone` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hourlyRate` double DEFAULT NULL,
  `isActive` tinyint(1) NOT NULL DEFAULT '1',
  `createdAt` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updatedAt` datetime(3) NOT NULL,
  `hikvisionId` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `Worker_hikvisionId_key` (`hikvisionId`),
  UNIQUE KEY `Worker_dpi_key` (`dpi`),
  KEY `Worker_dpi_idx` (`dpi`),
  KEY `Worker_hikvisionId_idx` (`hikvisionId`),
  KEY `Worker_isActive_idx` (`isActive`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Worker`
--

/*!40000 ALTER TABLE `Worker` DISABLE KEYS */;
INSERT INTO `Worker` VALUES ('cmqb5q2vg00009kbnbrisdxk8','Juan','Pérez',NULL,NULL,NULL,NULL,NULL,NULL,0,0,'2026-06-12 16:44:53.644','2026-06-12 17:09:00.788',NULL),('cmqb6ovgq00009krp8l0xduxv','Samuel','Prueba','123456789456123','','2000-08-25 00:00:00.000','39029076','','Supervisor',150,1,'2026-06-12 17:11:56.856','2026-06-12 17:11:56.856',NULL);
/*!40000 ALTER TABLE `Worker` ENABLE KEYS */;
SET @@SESSION.SQL_LOG_BIN = @MYSQLDUMP_TEMP_LOG_BIN;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-09 18:27:11
