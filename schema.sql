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
-- Table structure for table `audit_report`
--

DROP TABLE IF EXISTS `audit_report`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_report` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `f_planning_id` int(11) NOT NULL COMMENT 'planning tablosundaki denetim planina referans',
  `report_no` varchar(50) DEFAULT NULL COMMENT 'Arayüzdeki Rapor No',
  `audit_date_real` date DEFAULT NULL COMMENT 'Gerçeklesen Denetim Tarihi',
  `decision` varchar(100) DEFAULT NULL COMMENT 'Denetim Kararı (Örn: Geçti, Kaldı)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `FK_audit_report_planning` (`f_planning_id`),
  CONSTRAINT `FK_audit_report_planning` FOREIGN KEY (`f_planning_id`) REFERENCES `planning` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
) ENGINE=InnoDB AUTO_INCREMENT=255 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
) ENGINE=InnoDB AUTO_INCREMENT=239 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-02-26 16:19:08
