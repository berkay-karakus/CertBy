/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.6.22-MariaDB, for debian-linux-gnu (aarch64)
--
-- Host: localhost    Database: certby
-- ------------------------------------------------------
-- Server version	10.6.22-MariaDB-0ubuntu0.22.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `acreditor`
--

DROP TABLE IF EXISTS `acreditor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `acreditor` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(100) NOT NULL,
  `description` varchar(250) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acreditor`
--

LOCK TABLES `acreditor` WRITE;
/*!40000 ALTER TABLE `acreditor` DISABLE KEYS */;
INSERT INTO `acreditor` VALUES (1,'ACC1',NULL),(2,'ACC2',NULL),(3,'ACC3',NULL);
/*!40000 ALTER TABLE `acreditor` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `auditor_transaction`
--

DROP TABLE IF EXISTS `auditor_transaction`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `auditor_transaction` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `f_auditorid` int(11) NOT NULL,
  `f_certification_id` int(11) NOT NULL,
  `assign_date` date NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `FK_certification_id` (`f_certification_id`),
  KEY `FK_auditor_id` (`f_auditorid`),
  CONSTRAINT `FK_auditor_user_link` FOREIGN KEY (`f_auditorid`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_certification_id` FOREIGN KEY (`f_certification_id`) REFERENCES `certification` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auditor_transaction`
--

LOCK TABLES `auditor_transaction` WRITE;
/*!40000 ALTER TABLE `auditor_transaction` DISABLE KEYS */;
/*!40000 ALTER TABLE `auditor_transaction` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cert`
--

DROP TABLE IF EXISTS `cert`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cert` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(30) NOT NULL,
  `period` int(1) NOT NULL,
  `surveillance_count` int(1) NOT NULL,
  `standard` varchar(255) NOT NULL,
  `surveillance_frequency` int(11) NOT NULL DEFAULT 12,
  PRIMARY KEY (`id`),
  UNIQUE KEY `U_certname` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cert`
--

LOCK TABLES `cert` WRITE;
/*!40000 ALTER TABLE `cert` DISABLE KEYS */;
INSERT INTO `cert` VALUES (2,'KYS',3,1,'Test Standardı 2',12),(3,'ISO 9001',3,1,'Test Standardı 3',12),(5,'ISO 45001',3,1,'Test Standardı 5',12),(6,'ISO 27001',3,1,'Test Standardı 6',12),(7,'ISO 22000',3,1,'Test Standardı 7',12),(8,'ISO 50001',3,1,'Test Standardı 8',12),(10,'ISO 20000',1,0,'Test Standardı 10',12),(11,'ISO 22301',3,1,'Test Standardı 11',12),(12,'ISO 37001',3,1,'Test Standardı 12',12),(13,'ISO 31000',3,1,'Test Standardı 13',12),(14,'ISO 10002',1,0,'Test Standardı 14',12),(16,'ISO 26000',3,1,'Test Standardı 16',12),(17,'ISO 20121',1,0,'Test Standardı 17',12),(18,'ISO 39001',3,1,'Test Standardı 18',12),(19,'ISO 28000',3,1,'Test Standardı 19',12),(20,'ISO 55001',3,1,'Test Standardı 20',12),(21,'ISO 29993',3,1,'Test Standardı 21',12),(22,'ISO 45003',3,1,'Test Standardı 22',12),(25,'FAsf',3,2,'Test Standardı 25',12),(27,'Yeni Belge1',3,2,'Yeni Belge1 Standard',12);
/*!40000 ALTER TABLE `cert` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `certification`
--

DROP TABLE IF EXISTS `certification`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `certification` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `f_company_id` int(11) NOT NULL,
  `f_cert_id` int(11) NOT NULL,
  `certno` varchar(20) NOT NULL,
  `scope` varchar(400) NOT NULL,
  `publish_date` date NOT NULL,
  `end_date` date NOT NULL,
  `level` int(1) DEFAULT NULL,
  `consult_company_id` int(11) DEFAULT NULL,
  `status` int(11) NOT NULL,
  `accreditor` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_company` (`f_company_id`),
  KEY `FK_cert` (`f_cert_id`),
  KEY `FK_consultant` (`consult_company_id`),
  KEY `FK_accreditor` (`accreditor`),
  KEY `FK_status` (`status`),
  CONSTRAINT `FK_accreditor` FOREIGN KEY (`accreditor`) REFERENCES `acreditor` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `FK_cert` FOREIGN KEY (`f_cert_id`) REFERENCES `cert` (`id`),
  CONSTRAINT `FK_company` FOREIGN KEY (`f_company_id`) REFERENCES `company` (`id`),
  CONSTRAINT `FK_consultant` FOREIGN KEY (`consult_company_id`) REFERENCES `consult_company` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `FK_status` FOREIGN KEY (`status`) REFERENCES `certification_status` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=254 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `certification`
--

LOCK TABLES `certification` WRITE;
/*!40000 ALTER TABLE `certification` DISABLE KEYS */;
INSERT INTO `certification` VALUES (226,6,6,'CERT-006','Energy Management System','2022-08-30','2025-08-29',NULL,2,2,1),(227,23,7,'CERT-007','Medical Devices Quality','2022-09-12','2025-09-11',NULL,2,3,1),(228,8,8,'CERT-008','IT Service Management','2022-10-05','2025-10-04',NULL,1,2,3),(230,10,10,'CERT-010','Anti-Bribery Management','2022-12-15','2025-12-14',NULL,1,2,1),(231,11,11,'CERT-011','Risk Management','2023-01-10','2026-01-09',NULL,1,4,3),(232,12,12,'CERT-012','Customer Satisfaction','2023-02-20','2026-02-19',NULL,1,5,3),(233,13,13,'CERT-013','Testing and Calibration Labs','2023-03-18','2026-03-17',NULL,2,5,2),(234,14,14,'CERT-014','Social Responsibility','2023-04-22','2026-04-21',NULL,1,4,2),(236,16,16,'CERT-016','Road Traffic Safety','2023-06-15','2026-06-14',NULL,2,5,2),(237,17,17,'CERT-017','Supply Chain Security','2023-07-20','2026-07-19',NULL,2,3,2),(238,18,18,'CERT-018','Asset Management','2023-08-25','2026-08-24',NULL,1,4,1),(239,19,19,'CERT-019','Learning Services','2023-09-10','2026-09-09',NULL,2,4,1),(240,20,20,'CERT-020','Psychological Health & Safety','2023-10-05','2026-10-04',NULL,2,4,1),(245,33,27,'Yeni Belgelendirme1','sfsdfgafs','2025-11-25','2028-11-24',2,1,1,1),(251,33,27,'Yeni Belgelendirme','dsafadsf','2025-12-23','2028-12-22',3,1,1,2),(252,33,27,'Yeni Belgelendirme2','fsdfsdf','2025-12-18','2028-12-17',2,1,1,1),(253,33,27,'Yeni Belgelendirme4','safasdf','2024-02-10','2027-02-09',2,1,2,1);
/*!40000 ALTER TABLE `certification` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `certification_status`
--

DROP TABLE IF EXISTS `certification_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `certification_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `status` varchar(50) NOT NULL,
  `description` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `certification_status`
--

LOCK TABLES `certification_status` WRITE;
/*!40000 ALTER TABLE `certification_status` DISABLE KEYS */;
INSERT INTO `certification_status` VALUES (1,'Aktif','Sertifika Geçerli'),(2,'Pasif','Sertifika Ara Tetkik tarihi geçmiş ve sertifika geçerlilik dönemi içinde henüz ara tetkik planlanmamış'),(3,'Askıda','Sertifika için Ara Tetkik yaptırılmamış ve sertifika programına uygun hareket edilmemiş'),(4,'İptal','Sertifika geçerlilik süresinden önce sertifika programına uygunsuzluktan dolayı İptal edilmiş'),(5,'Güncelleme','Sertifika geçerlilik süresinde bir nedenden (ünvan ve seviye değişikliği vb) dolayı güncellenmiş');
/*!40000 ALTER TABLE `certification_status` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `company`
--

DROP TABLE IF EXISTS `company`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `company` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `c_name` varchar(80) NOT NULL,
  `name` varchar(255) NOT NULL,
  `address` varchar(400) NOT NULL,
  `contact_address` varchar(400) DEFAULT NULL,
  `web` varchar(400) DEFAULT NULL,
  `c_phone` varchar(15) DEFAULT NULL,
  `c_email` varchar(100) DEFAULT NULL,
  `c_invoice_address` varchar(400) NOT NULL,
  `authorized_contact_name` varchar(100) DEFAULT NULL,
  `authorized_contact_title` varchar(50) DEFAULT NULL,
  `contact_name` varchar(100) NOT NULL,
  `contact_phone` varchar(15) DEFAULT NULL,
  `contact_email` varchar(100) NOT NULL,
  `tax_office` varchar(100) NOT NULL,
  `tax_number` varchar(20) NOT NULL,
  `consulting_id` int(11) DEFAULT NULL,
  `status` varchar(1) NOT NULL COMMENT 'A:Aktif, P:Pasif\r\n',
  PRIMARY KEY (`id`),
  UNIQUE KEY `tax_office` (`tax_office`,`tax_number`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `company`
--

LOCK TABLES `company` WRITE;
/*!40000 ALTER TABLE `company` DISABLE KEYS */;
INSERT INTO `company` VALUES (1,'secrise','secrise bilişim','üsküdar istanbul','','','','','Bulgurlu caddesi No:41','','','ali veli','','deneme@secrise.com','umraniye','23432423',1,'P'),(2,'Company1','Company1','Istanbul',NULL,NULL,NULL,NULL,'Istanbul',NULL,NULL,'',NULL,'berkay123@gmail.com','Istanbul','123456',NULL,'A'),(3,'Company2','Company2','Istanbul',NULL,NULL,NULL,NULL,'Istanbul',NULL,NULL,'',NULL,'','Istanbul','2134567',NULL,'A'),(6,'TechOne','Alpha','123 Main St, New York',NULL,NULL,NULL,NULL,'123 Main St, New York',NULL,NULL,'John Doe',NULL,'johndoe@example.com','NY Office','TX12345',NULL,'A'),(7,'BizPro','Beta','456 Park Ave, Boston','','','','','456 Park Ave, Boston','','','Jane Smith','','janesmith@example.com','Boston Center','TX67890',0,'A'),(8,'InnoMax','Gamma','789 Market St, Chicago',NULL,NULL,NULL,NULL,'789 Market St, Chicago',NULL,NULL,'Robert Brown',NULL,'robertb@example.com','Chicago North','TX11223',NULL,'A'),(9,'DataCore','Delta','101 First Ave, Dallas',NULL,NULL,NULL,NULL,'101 First Ave, Dallas',NULL,NULL,'Emily Clark',NULL,'emilyc@example.com','Dallas West','TX44556',NULL,'A'),(10,'SkyNet','Epsilon','202 Second St, Miami',NULL,NULL,NULL,NULL,'202 Second St, Miami',NULL,NULL,'Michael White',NULL,'michaelw@example.com','Miami East','TX77889',NULL,'P'),(11,'NextGen','Zeta','303 Third Blvd, Seattle',NULL,NULL,NULL,NULL,'303 Third Blvd, Seattle',NULL,NULL,'Sarah Green',NULL,'sarahg@example.com','Seattle Port','TX33445',NULL,'A'),(12,'SoftHub','Eta','404 Fourth Rd, Denver',NULL,NULL,NULL,NULL,'404 Fourth Rd, Denver',NULL,NULL,'David Lee',NULL,'davidl@example.com','Denver Central','TX55667',NULL,'P'),(13,'NetWave','Theta','505 Fifth Ln, Austin',NULL,NULL,NULL,NULL,'505 Fifth Ln, Austin',NULL,NULL,'Laura King',NULL,'laurak@example.com','Austin Hub','TX99887',NULL,'A'),(14,'Cloudix','Iota','606 Sixth Dr, Houston',NULL,NULL,NULL,NULL,'606 Sixth Dr, Houston',NULL,NULL,'James Hill',NULL,'jamesh@example.com','Houston South','TX22334',NULL,'A'),(15,'PrimeCo','Kappa','707 Seventh Ave, Phoenix',NULL,NULL,NULL,NULL,'707 Seventh Ave, Phoenix',NULL,NULL,'Anna Scott',NULL,'annas@example.com','Phoenix Center','TX66778',NULL,'P'),(16,'StarCom','Lambda','808 Eighth St, San Diego','','','','','808 Eighth St, San Diego','','','Chris Adams','','chrisad@example.com','San Diego Bay','TX88990',0,'P'),(17,'InfoTech','Mu','909 Ninth Rd, San Jose',NULL,NULL,NULL,NULL,'909 Ninth Rd, San Jose',NULL,NULL,'Sophia Young',NULL,'sophiay@example.com','San Jose Tech','TX11299',NULL,'A'),(18,'CoreSys','Nu','111 Tenth Blvd, Atlanta',NULL,NULL,NULL,NULL,'111 Tenth Blvd, Atlanta',NULL,NULL,'Daniel Wright',NULL,'danielw@example.com','Atlanta East','TX44577',NULL,'P'),(19,'GlobalX','Xi','222 Eleventh Ave, Orlando',NULL,NULL,NULL,NULL,'222 Eleventh Ave, Orlando',NULL,NULL,'Mia Turner',NULL,'miat@example.com','Orlando North','TX77822',NULL,'A'),(20,'EdgePro','Omicron','333 Twelfth St, Detroit',NULL,NULL,NULL,NULL,'333 Twelfth St, Detroit',NULL,NULL,'William Hall',NULL,'willh@example.com','Detroit Port','TX99334',NULL,'P'),(21,'Smartix','Pi','444 Thirteenth Dr, Las Vegas','','','','','444 Thirteenth Dr, Las Vegas','','','Olivia Allen','','oliviaa@example.com','Vegas Central','TX55688',0,'P'),(22,'NovaNet','Rho','555 Fourteenth Rd, Portland',NULL,NULL,NULL,NULL,'555 Fourteenth Rd, Portland',NULL,NULL,'Ethan Walker',NULL,'ethanw@example.com','Portland Hub','TX77811',NULL,'A'),(23,'ByteCom','Sigma','666 Fifteenth Ln, Tampa','','','','','666 Fifteenth Ln, Tampa','','','Ava Lewis','','aval@example.com','Tampa Office','TX88977',0,'A'),(24,'TechNova','Tau','777 Sixteenth Ave, San Antonio',NULL,NULL,NULL,NULL,'777 Sixteenth Ave, San Antonio',NULL,NULL,'Liam Harris',NULL,'liamh@example.com','San Antonio West','TX22399',NULL,'A'),(25,'ZenithX','Upsilon','888 Seventeenth Blvd, Columbus',NULL,NULL,NULL,NULL,'888 Seventeenth Blvd, Columbus',NULL,NULL,'Isabella Martin',NULL,'isabellam@example.com','Columbus East','TX44588',NULL,'A'),(26,'Firma1','Firma1','Bulgurlu caddesi No:41','','','','','Bulgurlu caddesi No:41','','','Hasan Subaşı','','hsubasi@certby.com','daire1','daire1',1,'A'),(28,'Firma2','Firma2','Bulgurlu caddesi no:41','','','','','Bulgurlu caddesi No:41','','','Hasan Subaşı','','hsubasi@certby.com','daire2','daire2',1,'P'),(33,'Test Firma','Test Firma Ltd.','Test Adres','','','','','Test Fatura Adresi','','','Test Kişi','','berkaykarakusss61@gmail.com','Test Vergi Dairesi','9876543210',0,'A'),(34,'Erdal','Erdal Bakkal','Show TV','Hatay','http://erdalbakkal.com.tr','(533) 736-6117','berkaykarakusss61@gmail.com','Hatay','Erdal','Müdür','Hasan','(533) 736-6117','berkaykarakusss61@gmail.com','San Diego Bay','TX67890',1,'A');
/*!40000 ALTER TABLE `company` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `consult_company`
--

DROP TABLE IF EXISTS `consult_company`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consult_company` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `c_name` varchar(80) NOT NULL,
  `name` varchar(255) NOT NULL,
  `address` varchar(400) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `contact_name` varchar(100) DEFAULT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `consult_company`
--

LOCK TABLES `consult_company` WRITE;
/*!40000 ALTER TABLE `consult_company` DISABLE KEYS */;
INSERT INTO `consult_company` VALUES (1,'ITSAFE','ITSAFE Bilişim',NULL,'soner@itsafe.com',NULL,NULL),(2,'Kalibre','Kalibre Mühendislik',NULL,'burak@kalibremuhendislik.com',NULL,NULL);
/*!40000 ALTER TABLE `consult_company` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_attachment`
--

DROP TABLE IF EXISTS `email_attachment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_attachment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `FK_email_template_id` int(11) NOT NULL,
  `file_name` varchar(200) NOT NULL,
  `file_path` varchar(600) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_email_template_id` (`FK_email_template_id`),
  CONSTRAINT `FK_email_template_id` FOREIGN KEY (`FK_email_template_id`) REFERENCES `email_template` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_attachment`
--

LOCK TABLES `email_attachment` WRITE;
/*!40000 ALTER TABLE `email_attachment` DISABLE KEYS */;
/*!40000 ALTER TABLE `email_attachment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_log`
--

DROP TABLE IF EXISTS `email_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `log_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `email_template_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `log_type` varchar(50) DEFAULT NULL,
  `content` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_log`
--

LOCK TABLES `email_log` WRITE;
/*!40000 ALTER TABLE `email_log` DISABLE KEYS */;
INSERT INTO `email_log` VALUES (1,'2025-11-18 14:57:51',2,2,2,'E-posta Logu','Firma ID 2 (Company1) adresine e-posta gönderildi. Konu: IOS Belgenizin yenilenmesi hk.'),(2,'2025-11-18 14:58:27',2,2,2,'E-posta Logu','Firma ID 2 (Company1) adresine e-posta gönderildi. Konu: IOS Belgenizin yenilenmesi hk.'),(3,'2025-11-18 14:59:20',2,2,2,'E-posta Logu','Firma ID 2 (Company1) adresine e-posta gönderildi. Konu: IOS Belgenizin yenilenmesi hk.'),(4,'2025-11-18 14:59:55',2,2,2,'E-posta Logu','Firma ID 2 (Company1) adresine e-posta gönderildi. Konu: IOS Belgenizin yenilenmesi hk.'),(5,'2025-11-18 15:11:32',2,2,2,'E-posta Logu','Firma ID 2 (Company1) adresine e-posta gönderildi. Konu: IOS Belgenizin yenilenmesi hk.'),(6,'2025-11-18 15:21:13',2,2,2,'E-posta Logu','Firma ID 2 (Company1) adresine e-posta gönderildi. Konu: IOS Belgenizin yenilenmesi hk.'),(7,'2025-11-18 15:22:57',2,2,2,'E-posta Logu','Firma ID 2 (Company1) adresine e-posta gönderildi. Konu: IOS Belgenizin yenilenmesi hk.'),(8,'2025-11-18 15:26:19',2,2,2,'E-posta Logu','Firma ID 2 (Company1) adresine e-posta gönderildi. Konu: IOS Belgenizin yenilenmesi hk.'),(9,'2025-11-18 15:39:45',2,2,2,'E-posta Logu','Firma ID 2 (Company1) adresine e-posta gönderildi. Konu: IOS Belgenizin yenilenmesi hk.'),(10,'2025-11-18 15:46:24',2,2,2,'E-posta Logu','Firma ID 2 (Company1) adresine e-posta gönderildi. Konu: IOS Belgenizin yenilenmesi hk.'),(11,'2025-11-18 17:05:12',2,2,2,'E-posta Logu','Firma ID 2 (Company1) adresine e-posta gönderildi. Konu: IOS Belgenizin yenilenmesi hk.'),(12,'2025-11-18 17:05:37',2,2,2,'E-posta Logu','Firma ID 2 (Company1) adresine e-posta gönderildi. Konu: IOS Belgenizin yenilenmesi hk.'),(13,'2025-11-18 17:11:01',2,2,2,'E-posta Logu','Firma ID 2 (Company1) adresine e-posta gönderildi. Konu: IOS Belgenizin yenilenmesi hk.'),(14,'2025-11-18 17:16:27',2,2,2,'E-posta Logu','Firma ID 2 (Company1) adresine e-posta gönderildi. Konu: IOS Belgenizin yenilenmesi hk.'),(15,'2025-11-18 17:19:02',2,2,2,'E-posta Logu','Firma ID 2 (Company1) adresine e-posta gönderildi. Konu: IOS Belgenizin yenilenmesi hk.'),(16,'2025-11-18 17:30:08',2,2,2,'E-posta Logu','Firma ID 2 (Company1) adresine e-posta gönderildi. Konu: IOS Belgenizin yenilenmesi hk.'),(17,'2025-11-18 17:31:45',2,2,2,'E-posta Logu','Firma ID 2 (Company1) adresine e-posta gönderildi. Konu: IOS Belgenizin yenilenmesi hk.'),(18,'2025-11-18 17:32:36',2,2,2,'E-posta Logu','Firma ID 2 (Company1) adresine e-posta gönderildi. Konu: IOS Belgenizin yenilenmesi hk.'),(19,'2025-11-18 17:35:54',2,2,33,'E-posta Logu','Firma ID 33 (Test Firma) adresine e-posta gönderildi. Konu: IOS Belgenizin yenilenmesi hk.'),(20,'2025-11-18 17:36:36',3,2,33,'E-posta Logu','Firma ID 33 (Test Firma) adresine e-posta gönderildi. Konu: Belgelendirme Başvurunuz Onaylanmıştır'),(21,'2025-11-18 17:42:12',4,2,33,'E-posta Logu','Firma ID 33 (Test Firma) adresine e-posta gönderildi. Konu: Belgelendirme Yenileme Dönemi Hatırlatması'),(22,'2025-11-18 22:09:35',28,2,33,'E-posta Logu','Firma ID 33 (Test Firma) adresine e-posta gönderildi. Konu: Witcher'),(23,'2025-11-20 13:57:23',2,11,33,'E-posta Logu','Firma ID 33 (Test Firma) adresine e-posta gönderildi. Konu: IOS Belgenizin yenilenmesi hk.'),(24,'2025-11-20 13:57:27',2,11,33,'E-posta Logu','Firma ID 33 (Test Firma) adresine e-posta gönderildi. Konu: IOS Belgenizin yenilenmesi hk.'),(25,'2025-11-20 13:57:31',2,11,33,'E-posta Logu','Firma ID 33 (Test Firma) adresine e-posta gönderildi. Konu: IOS Belgenizin yenilenmesi hk.'),(26,'2025-11-20 13:57:35',2,11,33,'E-posta Logu','Firma ID 33 (Test Firma) adresine e-posta gönderildi. Konu: IOS Belgenizin yenilenmesi hk.'),(27,'2025-11-20 13:57:49',3,11,25,'E-posta Logu','Firma ID 25 (ZenithX) adresine e-posta gönderildi. Konu: Belgelendirme Başvurunuz Onaylanmıştır'),(28,'2025-11-20 13:58:10',3,11,33,'E-posta Logu','Firma ID 33 (Test Firma) adresine e-posta gönderildi. Konu: Belgelendirme Başvurunuz Onaylanmıştır'),(29,'2025-11-20 14:45:26',4,11,33,'E-posta Logu','Firma ID 33 (Test Firma) adresine e-posta gönderildi. Konu: Belgelendirme Yenileme Dönemi Hatırlatması'),(30,'2025-11-20 14:49:02',3,11,33,'E-posta Logu','Firma ID 33 (Test Firma) adresine e-posta gönderildi. Konu: Belgelendirme Başvurunuz Onaylanmıştır'),(31,'2025-11-21 10:06:44',2,11,33,'Denetim E-postası','Denetim Planı (ID: 2) için e-posta gönderildi. Konu: IOS Belgenizin yenilenmesi hk.'),(32,'2025-11-21 10:08:55',2,11,33,'E-posta Logu','Firma ID 33 (Test Firma) adresine e-posta gönderildi. Konu: IOS Belgenizin yenilenmesi hk.'),(33,'2025-11-21 11:18:08',4,11,33,'Denetim E-postası','Denetim Planı (ID: 1) için e-posta gönderildi. Konu: Belgelendirme Yenileme Dönemi Hatırlatması'),(34,'2025-11-21 11:24:14',2,11,33,'Denetim E-postası','Denetim Planı (ID: 1) için e-posta gönderildi. Konu: IOS Belgenizin yenilenmesi hk.'),(35,'2025-11-21 12:02:20',4,11,33,'Denetim E-postası','Denetim Planı (ID: 2) için e-posta gönderildi. Konu: Belgelendirme Yenileme Dönemi Hatırlatması'),(36,'2025-11-21 12:07:59',2,11,33,'Denetim E-postası','Denetim Planı (ID: 3) için e-posta gönderildi. Konu: IOS Belgenizin yenilenmesi hk.'),(37,'2025-11-21 12:59:11',3,11,33,'E-posta Logu','Firma ID 33 (Test Firma) adresine e-posta gönderildi. Konu: Belgelendirme Başvurunuz Onaylanmıştır'),(38,'2025-11-24 07:22:06',21,11,0,'Denetim E-postası','Denetim Planı (ID: 4) için e-posta gönderildi. Konu: Yaklaşan Ara Denetim Hatırlatması'),(39,'2025-11-24 07:22:07',21,11,0,'Denetim E-postası','Denetim Planı (ID: 4) için e-posta gönderildi. Konu: Yaklaşan Ara Denetim Hatırlatması'),(40,'2025-11-24 07:22:16',21,11,0,'Denetim E-postası','Denetim Planı (ID: 4) için e-posta gönderildi. Konu: Yaklaşan Ara Denetim Hatırlatması'),(41,'2025-11-24 07:22:49',2,11,0,'Denetim E-postası','Denetim Planı (ID: 4) için e-posta gönderildi. Konu: IOS Belgenizin yenilenmesi hk.'),(42,'2025-11-24 07:23:07',2,11,33,'E-posta Logu','Firma ID 33 (Test Firma) adresine e-posta gönderildi. Konu: IOS Belgenizin yenilenmesi hk.'),(43,'2025-11-24 11:27:03',1,11,0,'Denetim E-postası','Denetim Planı (ID: 5) için e-posta gönderildi. Konu: İlk Belgelendirme Denetim Planı'),(44,'2025-11-24 11:27:22',1,11,0,'Denetim E-postası','Denetim Planı (ID: 5) için e-posta gönderildi. Konu: İlk Belgelendirme Denetim Planı'),(45,'2025-11-24 11:27:42',1,11,0,'Denetim E-postası','Denetim Planı (ID: 5) için e-posta gönderildi. Konu: İlk Belgelendirme Denetim Planı'),(46,'2025-11-24 11:54:07',1,11,33,'E-posta Logu','Firma ID 33 (Test Firma) adresine e-posta gönderildi. Konu: İlk Belgelendirme Denetim Planı'),(47,'2025-11-24 11:54:09',1,11,33,'E-posta Logu','Firma ID 33 (Test Firma) adresine e-posta gönderildi. Konu: İlk Belgelendirme Denetim Planı'),(48,'2025-11-24 15:41:50',1,11,33,'Denetim E-postası','Denetim Planı (ID: 1) için 4 kişiye e-posta gönderildi.'),(49,'2025-11-24 15:57:28',1,11,33,'Denetim E-postası','Denetim Planı (ID: 1) için 3 kişiye e-posta gönderildi.'),(50,'2025-11-24 16:42:39',1,11,33,'E-posta Logu','Firma ID 33 adresine mail atıldı.'),(51,'2025-11-24 16:44:05',1,11,33,'Denetim E-postası','Denetim Planı (ID: 1) için 3 kişiye e-posta gönderildi.'),(52,'2025-11-25 10:28:44',2,11,33,'E-posta Logu','Firma ID 33 adresine mail atıldı.'),(53,'2025-11-25 10:29:17',1,11,33,'E-posta Logu','Firma ID 33 adresine mail atıldı.'),(54,'2025-11-25 10:29:49',1,11,33,'Denetim E-postası','Denetim Planı (ID: 1) için 2 kişiye e-posta gönderildi.'),(55,'2025-11-25 10:43:12',3,11,33,'E-posta Logu','Firma ID 33 (Test Firma) adresine e-posta gönderildi.'),(56,'2025-11-25 11:32:27',1,11,33,'E-posta Logu','Firma ID 33 adresine mail atıldı.'),(57,'2025-11-25 11:33:25',1,11,33,'Denetim E-postası','Denetim Planı (ID: 1) için 2 kişiye e-posta gönderildi.'),(58,'2025-11-25 11:43:59',1,11,33,'Denetim E-postası','Denetim Planı (ID: 1) için 2 kişiye e-posta gönderildi.'),(59,'2025-11-25 11:44:42',3,11,33,'Denetim E-postası','Denetim Planı (ID: 1) için 2 kişiye e-posta gönderildi.'),(60,'2025-11-25 12:23:43',1,11,33,'Denetim E-postası','Denetim Planı (ID: 1) için 2 kişiye e-posta gönderildi.'),(61,'2025-11-25 12:32:22',1,11,33,'Denetim E-postası','Denetim Planı (ID: 1) için 2 kişiye e-posta gönderildi.'),(62,'2025-11-25 12:35:52',1,11,33,'E-posta Logu','Firma ID 33 adresine mail atıldı.'),(63,'2025-11-25 18:02:52',1,11,33,'Denetim E-postası','Denetim Planı (ID: 1) için 2 kişiye e-posta gönderildi.'),(64,'2025-11-26 11:27:06',1,11,33,'Denetim E-postası','Denetim Planı (ID: 1) için 2 kişiye e-posta gönderildi.'),(65,'2025-11-26 12:13:27',2,11,33,'Denetim E-postası','Denetim Planı (ID: 2) için 2 kişiye e-posta gönderildi.'),(66,'2025-12-01 12:32:03',1,11,33,'Denetim E-postası','Denetim Planı (ID: 1) için 2 kişiye e-posta gönderildi.'),(67,'2025-12-01 12:32:30',2,11,33,'E-posta Logu','Firma ID 33 adresine mail atıldı.'),(68,'2025-12-15 20:52:33',2,11,33,'E-posta Logu','Firma ID 33 adresine mail atıldı.'),(69,'2025-12-15 20:53:08',2,11,33,'Denetim E-postası','Denetim Planı (ID: 2) için 2 kişiye e-posta gönderildi.');
/*!40000 ALTER TABLE `email_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_settings`
--

DROP TABLE IF EXISTS `email_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `from_name` varchar(50) NOT NULL,
  `smtp_host` varchar(200) NOT NULL,
  `smtp_username` varchar(200) NOT NULL,
  `smtp_password` varchar(100) NOT NULL,
  `smtp_secure` varchar(10) DEFAULT 'TLS',
  `smtp_port` int(11) NOT NULL,
  `from_email` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_settings`
--

LOCK TABLES `email_settings` WRITE;
/*!40000 ALTER TABLE `email_settings` DISABLE KEYS */;
INSERT INTO `email_settings` VALUES (1,'Berkay Karakuş','smtp.gmail.com','berkaykarakusss61@gmail.com','ibyjrnrxijjnykhu','tls',587,'berkaykarakusss61@gmail.com');
/*!40000 ALTER TABLE `email_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_template`
--

DROP TABLE IF EXISTS `email_template`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_template` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `description` varchar(400) DEFAULT NULL,
  `subject` varchar(200) NOT NULL,
  `body` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_template`
--

LOCK TABLES `email_template` WRITE;
/*!40000 ALTER TABLE `email_template` DISABLE KEYS */;
INSERT INTO `email_template` VALUES (1,'İlk Belgelendirme','İlk Belgelendirme Denetim Planı','Sayın Yetkili,\n\nFirmanızın ilk belgelendirme denetimi planlanmıştır. Detaylar ektedir.\n\nSaygılarımızla,'),(2,'Ara Tetkik','Ara Tetkik (Gözetim) Planı','Sayın Yetkili,\n\nFirmanızın yıllık ara tetkik (gözetim) denetimi planlanmıştır. Detaylar ektedir.\n\nSaygılarımızla,'),(3,'İptal','Belge İptal Bildirimi','Sayın Yetkili,\n\nBelgeniz iptal edilmiştir.\n\nSaygılarımızla,'),(4,'Askı','Belge Askıya Alma Bildirimi','Sayın Yetkili,\n\nBelgeniz askıya alınmıştır.\n\nSaygılarımızla,'),(5,'Yeniden Belgelendirme','Yeniden Belgelendirme Planı','Sayın Yetkili,\n\nBelge süreniz dolmak üzeredir. Yeniden belgelendirme denetimi planlanmıştır.\n\nSaygılarımızla,'),(6,'Diğer','Genel Bilgilendirme','Sayın Yetkili,\n\nGenel bilgilendirme metnidir.\n\nSaygılarımızla,');
/*!40000 ALTER TABLE `email_template` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `general_log`
--

DROP TABLE IF EXISTS `general_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `general_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `cert_id` int(11) NOT NULL,
  `log_type` varchar(50) NOT NULL,
  `content` text NOT NULL,
  `log_date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=228 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `general_log`
--

LOCK TABLES `general_log` WRITE;
/*!40000 ALTER TABLE `general_log` DISABLE KEYS */;
INSERT INTO `general_log` VALUES (1,0,0,0,'Erişim Logu','Başarısız giriş denemesi (Hatalı Bilgi) - Kullanıcı: berkay','2025-11-18 22:53:35'),(2,0,0,0,'Erişim Logu','Başarısız giriş denemesi (Hatalı Bilgi) - Kullanıcı: berkay','2025-11-18 22:53:40'),(3,0,0,0,'Erişim Logu','Başarısız giriş denemesi (Hatalı Bilgi) - Kullanıcı: berkay','2025-11-18 22:57:30'),(4,0,0,0,'Erişim Logu','Başarısız giriş denemesi (Hatalı Bilgi) - Kullanıcı: berkay','2025-11-18 22:57:36'),(5,0,0,0,'Erişim Logu','Başarısız giriş denemesi (Hatalı Bilgi) - Kullanıcı: berkay','2025-11-18 22:57:41'),(6,0,0,0,'Erişim Logu','Başarısız giriş denemesi (Hatalı Bilgi) - Kullanıcı: berkay','2025-11-18 22:58:47'),(7,0,0,0,'Erişim Logu','Başarısız giriş denemesi (Hatalı Bilgi) - Kullanıcı: berkay','2025-11-18 23:09:32'),(8,0,0,0,'Erişim Logu','Başarısız giriş denemesi (Hatalı Bilgi) - Kullanıcı: berkay','2025-11-18 23:09:37'),(9,0,0,0,'Erişim Logu','Başarısız giriş denemesi (Hatalı Bilgi) - Kullanıcı: berkay','2025-11-18 23:18:35'),(10,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-19 16:43:37'),(11,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-19 19:02:57'),(12,11,0,0,'Kullanıcı Yönetim Logu','Yeni kullanıcı eklendi: buse (user)','2025-11-19 20:10:09'),(13,12,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-19 20:10:52'),(14,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-19 20:15:57'),(15,12,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-19 20:16:15'),(16,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-19 20:16:43'),(17,11,0,0,'Kullanıcı Yönetim Logu','Yeni kullanıcı eklendi: emre (auditor)','2025-11-19 20:17:18'),(18,13,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-19 20:17:33'),(19,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-19 20:17:44'),(20,0,0,0,'Erişim Logu','Başarısız giriş denemesi (Hatalı Bilgi) - Denenen: berkaykarakussss61@gmail.com','2025-11-19 20:28:24'),(21,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-19 20:28:32'),(22,0,0,0,'Erişim Logu','Başarısız giriş denemesi (Hatalı Bilgi) - Denenen: berkays','2025-11-19 20:31:03'),(23,0,0,0,'Erişim Logu','Başarısız giriş denemesi (Hatalı Bilgi) - Denenen: berkay','2025-11-19 20:31:06'),(24,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-19 20:31:11'),(25,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-19 20:33:55'),(26,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-19 20:37:09'),(27,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-20 11:58:15'),(28,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-20 12:12:19'),(29,11,16,0,'Firma Yönetim Logu','Firma güncellendi: StarCom','2025-11-20 12:49:49'),(30,11,21,0,'Firma Yönetim Logu','Firma güncellendi: Smartix','2025-11-20 12:50:04'),(31,11,7,0,'Firma Yönetim Logu','Firma güncellendi: BizPro','2025-11-20 12:54:07'),(32,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-20 13:56:12'),(33,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-20 14:40:36'),(34,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-20 16:22:30'),(35,11,23,227,'Belgelendirme Logu','Sertifika güncellendi: CERT-007','2025-11-20 18:10:40'),(36,11,2,224,'Belgelendirme Logu','Sertifika güncellendi: CERT-002','2025-11-20 18:15:15'),(37,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-20 19:33:47'),(38,11,33,241,'Belgelendirme Logu','Yeni sertifika oluşturuldu: CERT-0016','2025-11-20 19:59:21'),(39,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-21 08:56:14'),(40,11,0,0,'Denetim Planlama','Yeni denetim planlandı: Test Firma (2025-11-20)','2025-11-21 09:08:15'),(41,11,0,0,'Denetim Planlama','Yeni denetim planlandı: Test Firma (2025-11-27)','2025-11-21 09:09:00'),(42,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-21 11:09:24'),(43,11,0,0,'Denetim Planlama','Yeni denetim planlandı: Test Firma (2025-12-20)','2025-11-21 11:54:25'),(44,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-21 12:42:39'),(45,11,0,0,'Kullanıcı Yönetim Logu','Kullanıcı güncellendi (ID: 13). Yeni Durum: Pasif','2025-11-21 12:47:44'),(46,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-21 12:56:31'),(47,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-21 14:55:52'),(48,11,23,0,'Firma Yönetim Logu','Firma güncellendi: ByteCom','2025-11-21 14:57:33'),(49,11,23,242,'Belgelendirme Logu','Yeni sertifika oluşturuldu: CERT-0099','2025-11-21 15:15:29'),(50,11,0,0,'Kullanıcı Yönetim Logu','Yeni kullanıcı eklendi: hsubasi (auditor)','2025-11-21 15:31:19'),(51,11,0,0,'Kullanıcı Yönetim Logu','Kullanıcı güncellendi (ID: 13). Yeni Durum: Aktif','2025-11-21 15:35:11'),(52,11,33,241,'Belgelendirme Logu','Sertifika güncellendi: CERT-0016','2025-11-21 15:43:08'),(53,0,0,0,'Erişim Logu','Başarısız giriş denemesi (Hatalı Bilgi) - Denenen: hsubasi','2025-11-21 15:43:55'),(54,0,0,0,'Erişim Logu','Başarısız giriş denemesi (Hatalı Bilgi) - Denenen: hsubasi','2025-11-21 15:44:01'),(55,0,0,0,'Erişim Logu','Başarısız giriş denemesi (Hatalı Bilgi) - Denenen: hsubasi','2025-11-21 15:44:06'),(56,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-21 15:44:31'),(57,11,0,0,'Kullanıcı Yönetim Logu','Kullanıcı güncellendi (ID: 14). Yeni Durum: Aktif','2025-11-21 15:44:52'),(58,14,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-21 15:45:08'),(59,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-21 15:46:25'),(60,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-21 19:03:01'),(61,11,0,0,'Denetçi Yönetimi','Denetçi güncellendi: erbay','2025-11-21 19:09:51'),(62,11,0,0,'Denetçi Yönetimi','Denetçi güncellendi: erbay','2025-11-21 19:09:57'),(63,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-23 23:33:52'),(64,11,0,26,'Belge Yönetim Logu','Yeni belge türü eklendi: Yeni Belge1','2025-11-24 00:02:38'),(65,11,33,243,'Belgelendirme Logu','Yeni sertifika oluşturuldu: Yeni Belgelendirme1','2025-11-24 00:08:12'),(66,11,33,244,'Belgelendirme Logu','Yeni sertifika oluşturuldu: Yeni Belgelendirme1','2025-11-24 00:35:27'),(67,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-24 05:31:50'),(68,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-24 07:16:30'),(69,11,9,229,'Belgelendirme Logu','Sertifika güncellendi: CERT-009','2025-11-24 07:16:51'),(70,11,33,0,'Denetim Planlama','Yeni denetim planlandı: Test Firma (2025-11-25)','2025-11-24 07:20:25'),(71,11,33,0,'Denetim Planlama','Denetim güncellendi: 4','2025-11-24 07:20:46'),(72,11,0,244,'Belgelendirme Logu','Sertifika silindi. ID: 244','2025-11-24 08:16:12'),(73,11,0,26,'Belge Yönetim Logu','Belge türü silindi. ID: 26','2025-11-24 08:16:22'),(74,11,0,27,'Belge Yönetim Logu','Yeni belge türü eklendi: Yeni Belge1','2025-11-24 08:17:07'),(75,11,0,0,'Kullanıcı Yönetim Logu','Yeni kullanıcı eklendi: berkay061 (auditor)','2025-11-24 08:19:07'),(76,11,33,245,'Belgelendirme Logu','Yeni sertifika oluşturuldu: Yeni Belgelendirme1','2025-11-24 08:19:54'),(77,11,0,0,'Denetim Planlama','Denetim planı silindi. ID: 4','2025-11-24 08:20:08'),(78,11,33,0,'Denetim Planlama','Yeni denetim planlandı: Test Firma (2025-11-27)','2025-11-24 08:51:50'),(79,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-24 10:11:16'),(80,11,33,0,'Denetim Planlama','Denetim güncellendi: 5','2025-11-24 10:12:15'),(81,11,33,0,'Denetim Planlama','Denetim güncellendi: 5','2025-11-24 10:12:27'),(82,11,33,0,'Denetim Planlama','Denetim güncellendi: 5','2025-11-24 10:31:22'),(83,11,33,0,'Denetim Planlama','Denetim güncellendi: 5','2025-11-24 10:31:53'),(84,11,33,0,'Denetim Planlama','Denetim güncellendi: 5','2025-11-24 10:32:12'),(85,11,33,0,'Denetim Planlama','Denetim güncellendi: 5','2025-11-24 10:37:42'),(86,11,33,0,'Denetim Planlama','Denetim güncellendi: 5','2025-11-24 10:38:07'),(87,11,33,0,'Denetim Planlama','Denetim güncellendi: 5','2025-11-24 10:40:18'),(88,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-24 11:09:43'),(89,11,33,0,'Denetim Planlama','Denetim güncellendi: 5','2025-11-24 11:10:22'),(90,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-24 11:53:47'),(91,11,33,0,'Denetim Planlama','Denetim güncellendi: 5','2025-11-24 12:34:24'),(92,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-24 13:16:55'),(93,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-24 15:30:30'),(94,11,33,0,'Denetim Planlama','Yeni denetim planlandı: Test Firma (2025-11-27)','2025-11-24 15:37:53'),(95,11,33,0,'Denetim Planlama','Denetim güncellendi: 1','2025-11-24 15:57:13'),(96,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-25 10:15:33'),(97,11,33,0,'Denetim Planlama','Denetim güncellendi: 1','2025-11-25 10:29:37'),(98,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-25 11:32:07'),(99,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-25 12:17:24'),(100,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-25 17:43:29'),(101,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-26 08:47:55'),(102,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-26 11:23:39'),(103,11,33,0,'Denetim Planlama','Yeni denetim planlandı: Test Firma (2026-11-26)','2025-11-26 12:12:13'),(104,11,33,0,'Denetim Planlama','Denetim güncellendi: 1','2025-11-26 12:12:41'),(105,0,0,0,'Erişim Logu','Başarısız giriş denemesi (Hatalı Bilgi) - Denenen: dmpkas','2025-11-26 13:13:27'),(106,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-26 13:13:32'),(107,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-30 23:10:09'),(108,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-11-30 23:42:02'),(109,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-01 01:14:01'),(110,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-01 10:38:01'),(111,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-01 12:11:36'),(112,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-01 12:33:57'),(113,11,0,0,'Sistem Ayarları','SMTP e-posta ayarları güncellendi.','2025-12-01 12:36:57'),(114,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-01 12:37:41'),(115,15,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-01 12:38:39'),(116,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-01 12:49:26'),(117,0,0,0,'Erişim Logu','Başarısız giriş denemesi (Hatalı Bilgi) - Denenen: buseun','2025-12-01 12:49:46'),(118,0,0,0,'Erişim Logu','Başarısız giriş denemesi (Hatalı Bilgi) - Denenen: buseun','2025-12-01 12:49:51'),(119,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-01 12:49:55'),(120,11,0,0,'Kullanıcı Yönetim Logu','Kullanıcı güncellendi (ID: 12). Yeni Durum: Aktif','2025-12-01 12:50:06'),(121,0,0,0,'Erişim Logu','Başarısız giriş denemesi (Hatalı Bilgi) - Denenen: buseun','2025-12-01 12:50:23'),(122,0,0,0,'Erişim Logu','Başarısız giriş denemesi (Hatalı Bilgi) - Denenen: buseun','2025-12-01 12:50:28'),(123,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-01 12:50:32'),(124,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-01 13:26:16'),(125,11,0,0,'Kullanıcı Yönetim Logu','Kullanıcı güncellendi (ID: 11).','2025-12-01 13:27:00'),(126,0,0,0,'Erişim Logu','Başarısız giriş denemesi (Hatalı Bilgi) - Denenen: berkay','2025-12-01 13:33:35'),(127,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-01 13:33:40'),(128,0,0,0,'Erişim Logu','Başarısız giriş denemesi - Denenen: berkay','2025-12-01 13:37:15'),(129,11,0,0,'Kullanıcı Yönetim Logu','Kullanıcı şifresini \'Şifremi Unuttum\' ile sıfırladı.','2025-12-01 13:37:47'),(130,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-01 13:40:30'),(131,0,0,0,'Erişim Logu','Başarısız giriş denemesi - Denenen: berkay','2025-12-01 13:41:56'),(132,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-01 13:42:52'),(133,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-01 13:43:46'),(134,0,0,0,'Erişim Logu','Başarısız giriş denemesi - Denenen: powefas','2025-12-01 13:45:02'),(135,0,0,0,'Erişim Logu','Başarısız giriş denemesi - Denenen: berkaykarakuss512','2025-12-01 13:45:12'),(136,0,0,0,'Erişim Logu','Başarısız giriş denemesi - Denenen: berkay','2025-12-01 13:45:16'),(137,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-01 13:45:21'),(138,0,0,0,'Erişim Logu','Başarısız giriş denemesi - Denenen: berkay','2025-12-01 13:45:32'),(139,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-01 13:45:42'),(140,0,0,0,'Erişim Logu','Başarısız giriş denemesi - Denenen: berkaykarakuss61@gmail.com','2025-12-01 13:46:02'),(141,0,0,0,'Erişim Logu','Başarısız giriş denemesi - Denenen: berkaykarakussdsafas','2025-12-01 13:46:12'),(142,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-01 13:46:17'),(143,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-01 13:46:30'),(144,0,0,0,'Erişim Logu','Başarısız giriş denemesi - Denenen: berkaykarakusss61@gmail.coms','2025-12-01 13:46:43'),(145,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-01 13:46:56'),(146,0,0,0,'Erişim Logu','Başarısız giriş denemesi - Denenen: berkaykarakusss61qgmail.com','2025-12-01 13:47:53'),(147,0,0,0,'Erişim Logu','Başarısız giriş denemesi - Denenen: berkay','2025-12-01 13:47:59'),(148,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-01 13:48:03'),(149,11,33,0,'Denetim Planlama','Yeni denetim planlandı: Test Firma (2025-12-03)','2025-12-01 13:55:09'),(150,0,0,0,'Erişim Logu','Başarısız giriş denemesi - Denenen: berkay','2025-12-01 13:55:56'),(151,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-01 13:56:01'),(152,0,0,0,'Erişim Logu','Başarısız giriş denemesi - Denenen: buseun','2025-12-01 14:10:00'),(153,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-01 14:10:18'),(154,15,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-01 14:14:31'),(155,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-01 14:15:02'),(156,11,0,0,'Firma Yönetim Logu','Yeni firma eklendi: Erdal','2025-12-01 14:19:56'),(157,11,34,0,'Denetim Planlama','Yeni denetim planlandı: Erdal (2025-12-03)','2025-12-01 14:23:44'),(158,15,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-01 14:24:16'),(159,0,0,0,'Erişim Logu','Başarısız giriş denemesi - Denenen: berkay','2025-12-01 14:26:57'),(160,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-01 14:27:02'),(161,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-01 21:41:15'),(162,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-02 20:01:50'),(163,0,0,0,'Erişim Logu','Başarısız giriş denemesi - Denenen: berkay','2025-12-02 20:02:49'),(164,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-02 20:02:53'),(165,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-03 16:39:27'),(166,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-03 18:15:12'),(167,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-03 20:16:05'),(168,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-04 14:33:13'),(169,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-04 20:27:54'),(170,11,7,246,'Belgelendirme Logu','Yeni sertifika oluşturuldu: ISO-1231','2025-12-04 20:33:19'),(171,11,7,247,'Belgelendirme Logu','Yeni sertifika oluşturuldu: ISO-12412','2025-12-04 20:44:54'),(172,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-07 20:39:34'),(173,11,7,248,'Belgelendirme Logu','Yeni sertifika oluşturuldu: ISO-124142','2025-12-07 20:40:23'),(174,11,7,249,'Belgelendirme Logu','Yeni sertifika oluşturuldu: ISO-43125','2025-12-07 20:40:50'),(175,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-07 22:23:08'),(176,11,14,250,'Belgelendirme Logu','Yeni sertifika oluşturuldu: ISO-6161','2025-12-07 22:33:14'),(177,11,0,0,'Kullanıcı Yönetim Logu','Kullanıcı güncellendi (ID: 15).','2025-12-07 23:54:42'),(178,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-08 03:26:10'),(179,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-08 16:55:11'),(180,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-08 18:30:17'),(181,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-08 23:05:14'),(182,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-09 00:39:14'),(183,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-09 12:05:13'),(184,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-09 14:56:03'),(185,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-15 01:26:52'),(186,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-15 14:34:27'),(187,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-15 20:47:21'),(188,11,0,0,'Kullanıcı Yönetim Logu','Kullanıcı güncellendi (ID: 11).','2025-12-15 20:48:24'),(189,11,0,0,'Kullanıcı Yönetim Logu','Kullanıcı güncellendi (ID: 11).','2025-12-15 20:48:50'),(190,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-16 00:14:09'),(191,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-16 20:39:19'),(192,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-18 17:27:06'),(193,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-18 18:42:34'),(194,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-19 22:48:51'),(195,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-21 22:43:35'),(196,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-22 18:02:43'),(197,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-22 19:26:40'),(198,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-22 21:08:13'),(199,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-22 22:52:01'),(200,11,0,241,'Belgelendirme Logu','Sertifika silindi. ID: 241','2025-12-23 01:06:08'),(201,11,0,249,'Belgelendirme Logu','Sertifika silindi. ID: 249','2025-12-23 01:06:43'),(202,11,0,248,'Belgelendirme Logu','Sertifika silindi. ID: 248','2025-12-23 01:06:47'),(203,11,0,247,'Belgelendirme Logu','Sertifika silindi. ID: 247','2025-12-23 01:06:51'),(204,11,0,246,'Belgelendirme Logu','Sertifika silindi. ID: 246','2025-12-23 01:06:56'),(205,11,0,250,'Belgelendirme Logu','Sertifika silindi. ID: 250','2025-12-23 01:07:02'),(206,11,0,242,'Belgelendirme Logu','Sertifika silindi. ID: 242','2025-12-23 01:07:05'),(207,11,0,224,'Belgelendirme Logu','Sertifika silindi. ID: 224','2025-12-23 01:07:14'),(208,11,0,225,'Belgelendirme Logu','Sertifika silindi. ID: 225','2025-12-23 01:07:18'),(209,11,0,229,'Belgelendirme Logu','Sertifika silindi. ID: 229','2025-12-23 01:07:30'),(210,11,0,0,'Denetim Planlama','Denetim planı silindi. ID: 4','2025-12-23 01:07:55'),(211,11,0,0,'Denetim Planlama','Denetim planı silindi. ID: 1','2025-12-23 01:08:00'),(212,11,0,0,'Denetim Planlama','Denetim planı silindi. ID: 3','2025-12-23 01:08:03'),(213,11,0,0,'Denetim Planlama','Denetim planı silindi. ID: 2','2025-12-23 01:08:06'),(214,11,0,9,'Belge Yönetim Logu','Belge türü silindi. ID: 9','2025-12-23 01:15:42'),(215,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2025-12-25 11:43:52'),(216,11,33,251,'Belgelendirme Logu','Yeni sertifika oluşturuldu: Yeni Belgelendirme','2025-12-25 11:46:24'),(217,11,33,252,'Belgelendirme Logu','Yeni sertifika oluşturuldu: Yeni Belgelendirme2','2025-12-25 11:52:18'),(218,11,33,0,'Denetim Planlama','Yeni denetim planlandı: Test Firma (2025-12-27)','2025-12-25 11:59:45'),(219,11,0,223,'Belgelendirme Logu','Sertifika silindi. ID: 223','2025-12-25 12:22:06'),(220,11,0,1,'Belge Yönetim Logu','Belge türü silindi. ID: 1','2025-12-25 12:22:15'),(221,11,33,253,'Belgelendirme Logu','Yeni sertifika oluşturuldu: Yeni Belgelendirme4','2025-12-25 12:23:48'),(222,11,0,253,'Sistem Otomasyonu','Sertifika otomatik olarak PASİF\'e çekildi (Ara tetkik zamanı geçti ve plan yok).','2025-12-25 12:23:50'),(223,11,33,0,'Denetim Planlama','Yeni denetim planlandı: Test Firma (2025-12-25)','2025-12-25 12:41:24'),(224,11,33,0,'Denetim Planlama','Yeni denetim planlandı: Test Firma (2025-12-27)','2025-12-25 12:44:02'),(225,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2026-01-08 15:53:50'),(226,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2026-02-10 00:48:41'),(227,11,0,0,'Erişim Logu','Başarılı giriş yapıldı.','2026-02-10 00:51:17');
/*!40000 ALTER TABLE `general_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `participant`
--

DROP TABLE IF EXISTS `participant`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `participant` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `f_planning_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_planning_part` (`f_planning_id`),
  CONSTRAINT `FK_planning_part` FOREIGN KEY (`f_planning_id`) REFERENCES `planning` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `participant`
--

LOCK TABLES `participant` WRITE;
/*!40000 ALTER TABLE `participant` DISABLE KEYS */;
/*!40000 ALTER TABLE `participant` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `planning`
--

DROP TABLE IF EXISTS `planning`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `planning` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `f_company_id` int(11) DEFAULT NULL,
  `f_cert_id` int(11) DEFAULT NULL,
  `f_consult_company_id` int(11) DEFAULT NULL,
  `audit_publish_date` date NOT NULL,
  `audit_end_date` date NOT NULL,
  `audit_status` varchar(50) NOT NULL,
  `audit_certtification_no` varchar(20) DEFAULT NULL,
  `audit_link` varchar(200) NOT NULL,
  `is_auto_generated` tinyint(1) DEFAULT 0,
  `audit_type` varchar(20) DEFAULT 'ilk',
  PRIMARY KEY (`id`),
  KEY `FK_planning_company` (`f_company_id`),
  KEY `FK_planning_cert` (`f_cert_id`),
  KEY `FK_planning_consult` (`f_consult_company_id`),
  CONSTRAINT `FK_planning_cert` FOREIGN KEY (`f_cert_id`) REFERENCES `cert` (`id`) ON DELETE SET NULL,
  CONSTRAINT `FK_planning_company` FOREIGN KEY (`f_company_id`) REFERENCES `company` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_planning_consult` FOREIGN KEY (`f_consult_company_id`) REFERENCES `consult_company` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `planning`
--

LOCK TABLES `planning` WRITE;
/*!40000 ALTER TABLE `planning` DISABLE KEYS */;
INSERT INTO `planning` VALUES (5,33,27,1,'2025-12-27','2025-12-29','Planlandı','','sfasdfsadf',0,'ilk'),(6,33,27,2,'2025-12-25','2025-12-26','Planlandı','Yeni Belgelendirme1','dcfgbhn',0,'ara'),(7,33,27,1,'2025-12-27','2025-11-29','Planlandı','','sfasf',0,'ilk');
/*!40000 ALTER TABLE `planning` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `planning_auditor`
--

DROP TABLE IF EXISTS `planning_auditor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `planning_auditor` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `f_planning_id` int(11) NOT NULL,
  `f_auditor_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_pa_planning` (`f_planning_id`),
  KEY `FK_pa_user` (`f_auditor_id`),
  CONSTRAINT `FK_pa_planning` FOREIGN KEY (`f_planning_id`) REFERENCES `planning` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_pa_user` FOREIGN KEY (`f_auditor_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `planning_auditor`
--

LOCK TABLES `planning_auditor` WRITE;
/*!40000 ALTER TABLE `planning_auditor` DISABLE KEYS */;
INSERT INTO `planning_auditor` VALUES (11,5,14),(12,6,15),(13,7,14);
/*!40000 ALTER TABLE `planning_auditor` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role`
--

DROP TABLE IF EXISTS `role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `role` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `description` varchar(200) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role`
--

LOCK TABLES `role` WRITE;
/*!40000 ALTER TABLE `role` DISABLE KEYS */;
INSERT INTO `role` VALUES (1,'Operator','Operator role added'),(2,'User','User role added'),(3,'Auditor','Auditor role added\r\n');
/*!40000 ALTER TABLE `role` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(250) NOT NULL,
  `status` varchar(1) NOT NULL COMMENT 'A:Aktif, P:Pasif',
  `role_code` varchar(50) NOT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `U_username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user`
--

LOCK TABLES `user` WRITE;
/*!40000 ALTER TABLE `user` DISABLE KEYS */;
INSERT INTO `user` VALUES (11,'berkay','berkay','berkaykarakusss61@gmail.com','$2y$10$Lqw6Wih8K.ttuhCWVeLK7OtRTwtW/BMc0owTzMelGImSbmxIRWa8m','A','operator','762b5f2193560bc42aab5d175b09311efbe3a8eafb7214fae45cb082bb9f371e','2025-12-01 18:25:28'),(12,'buse','buse ün','buseun@gmail.com','$2y$10$jSFBgzbBHBhAdWOybx50SunZRHtS67W8.wmi8xp21v0wo6GRGUXFK','A','user',NULL,NULL),(13,'emre','erbay','emreerbay@gmail.com','$2y$10$QCGL0v2Gk8LFNHxEzhDL2OIlNb0Co33ZRQ6QSuvrxvEMaSnRprQe.','A','auditor',NULL,NULL),(14,'hsubasi','Hasan Subaşı','hsubasi@certby.com','$2y$10$W2dwQ8otl1QQwenrhUBFt.eui4UG7vUUYbjSkokYK.jmCkzVfk6r6','A','auditor',NULL,NULL),(15,'berkay061','berkaykarakuş','berkaykarakus18@marun.edu.tr','$2y$10$wo3xI9rQKTckkq/aEdufj.a81Swa/8EpR9m4HpVWMBcBIILFF246q','A','auditor',NULL,NULL);
/*!40000 ALTER TABLE `user` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-02-17  1:27:44
