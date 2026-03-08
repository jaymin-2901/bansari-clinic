-- MariaDB dump 10.19  Distrib 10.4.28-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: bansari_clinic
-- ------------------------------------------------------
-- Server version	10.4.28-MariaDB

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
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('super_admin','admin') DEFAULT 'admin',
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_email` (`email`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admins`
--

LOCK TABLES `admins` WRITE;
/*!40000 ALTER TABLE `admins` DISABLE KEYS */;
INSERT INTO `admins` VALUES (1,'Dr. Bansari Patel','admin@bansari.com','$2y$10$7r618hBfuirFsjD1CTC7m.roAjCIIPS6Z7qYJDSZ/8X/XCq51oq4q','super_admin',1,'2026-03-05 22:19:14','2026-03-01 10:47:48','2026-03-05 22:19:14');
/*!40000 ALTER TABLE `admins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `appointments`
--

DROP TABLE IF EXISTS `appointments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `appointments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `consultation_type` enum('offline','online') NOT NULL,
  `form_type` enum('short','full') NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time DEFAULT NULL,
  `status` enum('pending','confirmed','completed','cancelled') DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `reminder_sent` tinyint(1) DEFAULT 0,
  `reminder_sent_at` datetime DEFAULT NULL,
  `confirmation_status` enum('pending','reminder_sent','confirmed','cancelled','no_response') DEFAULT 'pending',
  `reply_source` enum('whatsapp','email','manual','auto','manual_whatsapp','manual_email') DEFAULT NULL,
  `whatsapp_message_id` varchar(255) DEFAULT NULL,
  `email_message_id` varchar(255) DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `confirmation_email_sent` tinyint(1) NOT NULL DEFAULT 0,
  `confirmation_email_sent_at` datetime DEFAULT NULL,
  `is_followup` tinyint(1) DEFAULT 0,
  `parent_appointment_id` int(11) DEFAULT NULL,
  `followup_created` tinyint(1) DEFAULT 0,
  `whatsapp_sent` tinyint(1) NOT NULL DEFAULT 0,
  `whatsapp_sent_at` datetime DEFAULT NULL,
  `followup_done` tinyint(1) NOT NULL DEFAULT 0,
  `followup_done_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `idx_date` (`appointment_date`),
  KEY `idx_status` (`status`),
  KEY `idx_type` (`consultation_type`),
  KEY `idx_reminder_sent` (`reminder_sent`),
  KEY `idx_confirmation_status` (`confirmation_status`),
  KEY `idx_is_followup` (`is_followup`),
  KEY `idx_parent_appointment` (`parent_appointment_id`),
  KEY `idx_appointments_confirmation_email` (`confirmation_email_sent`),
  CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointments`
--

LOCK TABLES `appointments` WRITE;
/*!40000 ALTER TABLE `appointments` DISABLE KEYS */;
INSERT INTO `appointments` VALUES (1,1,'offline','short','2026-03-02','12:00:00','completed',NULL,0,NULL,'pending',NULL,NULL,NULL,NULL,0,NULL,0,NULL,0,0,NULL,0,NULL,'2026-03-01 10:59:41','2026-03-01 11:22:09'),(2,3,'online','full','2026-03-02','12:00:00','completed',NULL,0,NULL,'pending',NULL,NULL,NULL,NULL,0,NULL,0,NULL,0,0,NULL,0,NULL,'2026-03-01 11:41:59','2026-03-01 11:42:52'),(3,1,'offline','short','2026-03-05','17:00:00','completed',NULL,0,NULL,'pending',NULL,NULL,NULL,NULL,0,NULL,0,NULL,0,0,NULL,0,NULL,'2026-03-01 11:45:05','2026-03-01 11:45:54'),(4,2,'offline','short','2414-04-24','14:24:00','completed',NULL,0,NULL,'pending',NULL,NULL,NULL,NULL,0,NULL,0,NULL,0,0,NULL,0,NULL,'2026-03-01 11:49:33','2026-03-01 13:19:56'),(5,2,'offline','short','2026-03-02','10:00:00','confirmed',NULL,0,NULL,'pending',NULL,NULL,NULL,NULL,0,NULL,0,NULL,0,0,NULL,1,'2026-03-02 18:56:22','2026-03-01 12:04:40','2026-03-02 18:56:22'),(6,2,'offline','short','2026-03-16','09:30:00','confirmed',NULL,1,'2026-03-04 16:07:38','reminder_sent','manual_email',NULL,'<91f9c592-aafc-1d28-d74f-2bded82ee5b8@gmail.com>',NULL,0,NULL,0,NULL,0,0,NULL,0,NULL,'2026-03-01 12:34:39','2026-03-04 16:07:38'),(7,2,'online','full','2026-03-02','09:30:00','confirmed',NULL,0,NULL,'pending',NULL,NULL,NULL,NULL,0,NULL,0,NULL,0,0,NULL,1,'2026-03-02 18:56:20','2026-03-01 13:00:28','2026-03-02 18:56:20'),(8,3,'offline','short','2026-03-03','09:30:00','confirmed',NULL,0,NULL,'pending',NULL,NULL,NULL,NULL,0,NULL,0,NULL,0,0,NULL,0,NULL,'2026-03-01 13:33:27','2026-03-01 13:49:02'),(9,5,'online','full','2026-03-02','10:30:00','confirmed',NULL,0,NULL,'pending',NULL,NULL,NULL,NULL,0,NULL,0,NULL,0,0,NULL,1,'2026-03-02 00:03:23','2026-03-01 22:02:13','2026-03-02 00:03:23'),(10,5,'offline','short','2026-03-26','09:45:00','completed',NULL,0,NULL,'pending',NULL,NULL,NULL,NULL,0,NULL,0,NULL,0,0,NULL,0,NULL,'2026-03-02 00:20:43','2026-03-02 19:25:02'),(11,5,'offline','short','2026-03-20','09:30:00','pending',NULL,0,NULL,'pending',NULL,NULL,NULL,NULL,0,NULL,0,NULL,0,0,NULL,0,NULL,'2026-03-02 19:02:44','2026-03-02 19:02:44'),(12,10,'online','full','2026-03-23','09:30:00','confirmed',NULL,1,'2026-03-05 14:00:44','confirmed','email',NULL,'<76f01c36-c57b-6ce8-28c2-51ceeb13ba41@gmail.com>','2026-03-05 14:01:44',1,'2026-03-05 14:01:50',0,NULL,0,0,NULL,0,NULL,'2026-03-04 11:10:01','2026-03-05 14:01:50'),(13,8,'online','full','2026-03-05','09:30:00','confirmed',NULL,1,'2026-03-04 17:36:51','confirmed','email',NULL,'<c39d1e1f-e0de-0eb4-8226-6a7c77407a8e@gmail.com>','2026-03-04 17:37:26',1,'2026-03-04 17:37:31',0,NULL,0,0,NULL,1,'2026-03-05 01:30:47','2026-03-04 20:14:45','2026-03-05 01:30:47'),(14,11,'offline','short','2026-03-05','10:00:00','confirmed',NULL,1,'2026-03-05 12:05:35','reminder_sent','manual_email',NULL,'<797bf563-3e7f-b365-4d6c-10c86957d972@gmail.com>',NULL,0,NULL,0,NULL,0,0,NULL,0,NULL,'2026-03-04 20:53:58','2026-03-05 12:05:35'),(15,12,'offline','short','2026-03-05','10:30:00','confirmed',NULL,1,'2026-03-04 16:15:10','confirmed','email',NULL,'<fde2bc3b-19af-a41d-2aaf-39022995e863@gmail.com>','2026-03-04 16:15:27',0,NULL,0,NULL,0,0,NULL,1,'2026-03-05 02:07:58','2026-03-04 21:44:14','2026-03-05 02:07:58'),(16,12,'offline','short','2029-06-20','09:45:00','confirmed',NULL,1,'2026-03-04 16:20:54','confirmed','email',NULL,'<f5289e6c-f51e-2bec-68e8-11cf2255560d@gmail.com>','2026-03-04 16:21:21',0,NULL,0,NULL,0,0,NULL,1,'2026-03-04 21:51:49','2026-03-04 21:48:51','2026-03-04 21:51:49'),(17,13,'offline','short','2026-03-20','10:00:00','confirmed',NULL,1,'2026-03-05 14:00:54','reminder_sent','manual_email',NULL,'<dacf3511-0b48-d908-16f1-a5424fcf0af9@gmail.com>',NULL,0,NULL,0,NULL,0,0,NULL,0,NULL,'2026-03-05 19:29:07','2026-03-05 14:00:54');
/*!40000 ALTER TABLE `appointments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clinic_schedule`
--

DROP TABLE IF EXISTS `clinic_schedule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clinic_schedule` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `day_of_week` tinyint(4) NOT NULL COMMENT '0=Sunday, 1=Monday ... 6=Saturday',
  `is_open` tinyint(1) DEFAULT 1,
  `opening_time` time DEFAULT NULL,
  `closing_time` time DEFAULT NULL,
  `break_start` time DEFAULT NULL,
  `break_end` time DEFAULT NULL,
  `new_patient_duration` int(11) DEFAULT 30 COMMENT 'minutes per slot',
  `old_patient_duration` int(11) DEFAULT 15 COMMENT 'minutes per slot',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_day` (`day_of_week`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clinic_schedule`
--

LOCK TABLES `clinic_schedule` WRITE;
/*!40000 ALTER TABLE `clinic_schedule` DISABLE KEYS */;
INSERT INTO `clinic_schedule` VALUES (1,0,0,NULL,NULL,NULL,NULL,30,15,'2026-03-05 22:18:35'),(2,1,1,'09:30:00','20:00:00','13:00:00','17:00:00',30,15,'2026-03-05 22:18:35'),(3,2,1,'09:30:00','20:00:00','13:00:00','17:00:00',30,15,'2026-03-05 22:18:35'),(4,3,1,'09:30:00','20:00:00','13:00:00','17:00:00',30,15,'2026-03-05 22:18:35'),(5,4,1,'09:30:00','20:00:00','13:00:00','17:00:00',30,15,'2026-03-05 22:18:35'),(6,5,1,'09:30:00','20:00:00','13:00:00','17:00:00',30,15,'2026-03-05 22:18:35'),(7,6,1,'09:30:00','20:00:00','13:00:00','17:00:00',30,15,'2026-03-05 22:18:35');
/*!40000 ALTER TABLE `clinic_schedule` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `complaints`
--

DROP TABLE IF EXISTS `complaints`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `complaints` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `appointment_id` int(11) NOT NULL,
  `chief_complaint` text NOT NULL,
  `complaint_duration` varchar(100) DEFAULT NULL,
  `major_diseases` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '["diabetes","bp","thyroid","asthma","tb","surgery"]' CHECK (json_valid(`major_diseases`)),
  `current_medicines` text DEFAULT NULL,
  `allergy` text DEFAULT NULL,
  `declaration_accepted` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `appointment_id` (`appointment_id`),
  CONSTRAINT `complaints_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `complaints`
--

LOCK TABLES `complaints` WRITE;
/*!40000 ALTER TABLE `complaints` DISABLE KEYS */;
INSERT INTO `complaints` VALUES (1,1,'hhr','ehr4th','[\"Diabetes\",\"High Blood Pressure\"]','hrtht','hrth',1,'2026-03-01 10:59:41'),(2,3,'rgegr','tete','[]','ey35','ery4',1,'2026-03-01 11:45:05'),(3,4,'fyrtu','ur','[\"Diabetes\"]','urit','tu',1,'2026-03-01 11:49:33'),(4,5,'qdqwkdqbo','1 month','[\"Diabetes\",\"Asthma\"]','greghe','erye',1,'2026-03-01 12:04:40'),(5,6,'jytjkty','utyt','[\"Asthma\"]','uru','uruty',1,'2026-03-01 12:34:39'),(6,8,'3yyh','hrthr','[\"Past Surgery\"]','teye','tety',1,'2026-03-01 13:33:27'),(7,10,'obsivd','odbcoei','[\"Diabetes\"]','kcwibv','vlwov',1,'2026-03-02 00:20:43'),(8,11,'wogwig','wiv f wefm faf kfe fkw','[\"Diabetes\"]','fje  kda aekajmajtmsafjjks c,da','apkkadm ;fe lfk;alkwq',1,'2026-03-02 19:02:44'),(9,14,'f0wejiowjg','fejwoinw','[\"Diabetes\"]','vpmvlnvs','vmvlmf',1,'2026-03-04 20:53:58'),(10,15,'cdafavff','gewgw','[\"Diabetes\",\"Asthma\"]','vsvsfs','dcadv',1,'2026-03-04 21:44:14'),(11,16,'','2 days','[\"Past Surgery\",\"Tuberculosis\",\"High Blood Pressure\",\"Asthma\",\"Diabetes\",\"Thyroid\"]','NA','NA',1,'2026-03-04 21:48:51'),(12,17,'yeyehetxnertewn3a4 et y et ey tq w e e te yer et rt yr ysrt','rertyers 3 yes y r ysrt yse y wa yaw ae y sr sr','[\"Diabetes\",\"High Blood Pressure\"]','terhetyae3 df strS rye gt srt hsg eryae tw rq rwz dxfgdf er','twete aeerr te te er 4t  tw zsy d x',1,'2026-03-05 19:29:07');
/*!40000 ALTER TABLE `complaints` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `confirmation_tokens`
--

DROP TABLE IF EXISTS `confirmation_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `confirmation_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `appointment_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `action` enum('confirm','cancel') NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_token` (`token`),
  KEY `idx_appointment` (`appointment_id`),
  KEY `idx_expires` (`expires_at`),
  CONSTRAINT `confirmation_tokens_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `confirmation_tokens`
--

LOCK TABLES `confirmation_tokens` WRITE;
/*!40000 ALTER TABLE `confirmation_tokens` DISABLE KEYS */;
INSERT INTO `confirmation_tokens` VALUES (1,13,'8854c2fa705c1de68984f21e4bef5efbdbdd0c41cc9085de5faf5e1f4d5295b9','confirm',0,'2026-03-06 15:02:30',NULL,'2026-03-04 15:02:30'),(2,13,'daa2abbbf5138cdb930d489e54d16ad4d509b2682d4acede724373a35b55b132','cancel',1,'2026-03-06 15:02:30','2026-03-04 17:37:32','2026-03-04 15:02:30'),(3,13,'29547d7efb3aea2a1efa2d0ecb5628c319a43d4c2d447e664b68db1833bea9f5','confirm',0,'2026-03-06 15:02:44',NULL,'2026-03-04 15:02:44'),(4,13,'db1c210f302efb390789ec9f5bad86c3777624ebfb7e5e1338a16d0b756fc504','cancel',1,'2026-03-06 15:02:44','2026-03-04 17:37:32','2026-03-04 15:02:44'),(5,13,'4b395249e50e08520c111bb3119749fd882a6a11e578229c43877a2e6910583d','confirm',0,'2026-03-06 15:03:07',NULL,'2026-03-04 15:03:07'),(6,13,'414d6394e799f76a72c24fd122cc6799b186528d108a04af212a85eb1426f8d2','cancel',1,'2026-03-06 15:03:07','2026-03-04 17:37:32','2026-03-04 15:03:07'),(7,13,'f8e4c65d2f239554ea7137253f3aed1ba7d28eea46a62da23472e760d5bc21a2','confirm',0,'2026-03-06 15:18:14',NULL,'2026-03-04 15:18:14'),(8,13,'2e345cf4c2e1d6429bfede767f96a2c083f72938d2de666302ca57e099c474bf','cancel',1,'2026-03-06 15:18:14','2026-03-04 17:37:32','2026-03-04 15:18:14'),(9,13,'cd96d060780b08695edcdd200ec6225d7bd5c95cd9e222d554d84cba1c652f35','confirm',0,'2026-03-06 15:18:57',NULL,'2026-03-04 15:18:57'),(10,13,'9de10e6b284fa5abf3b2bc6e68f23d20db627af375d412c3b2a229ad62e46d49','cancel',1,'2026-03-06 15:18:57','2026-03-04 17:37:32','2026-03-04 15:18:57'),(11,14,'6ff21cd800fdb26b885e489f13067a6748d2fe474f92f8333f0e8cad9b88c195','confirm',0,'2026-03-06 15:33:14',NULL,'2026-03-04 15:33:14'),(12,14,'0ee862fe7d5ace98cf336e61f41ba5168b84360852122fbeaaabf8ec589ed9e1','cancel',0,'2026-03-06 15:33:14',NULL,'2026-03-04 15:33:14'),(13,6,'fc1471588af8f3dad5c766bfb39f55e33aa3414cd07ea78ba35d3057349d64ea','confirm',0,'2026-03-06 16:07:33',NULL,'2026-03-04 16:07:33'),(14,6,'a25ba4bbd5d1f99c09427a163da9af6f33fd56f7c288be5d7819b1a0186e0dc0','cancel',0,'2026-03-06 16:07:33',NULL,'2026-03-04 16:07:33'),(15,14,'47180b4a7ed4a429e9f50f74ac2ad6a1548f35993d1fec74742592c5637d8b60','confirm',0,'2026-03-06 16:08:36',NULL,'2026-03-04 16:08:36'),(16,14,'709d85625a880074d91bff19c6c95ea8fe4e84e0bf462b473669e4d84aaf1bfd','cancel',0,'2026-03-06 16:08:36',NULL,'2026-03-04 16:08:36'),(17,15,'bdd4136f26d08a33240444d424f35b1212981f28364057c93a37ced976529ae8','confirm',1,'2026-03-06 16:15:05','2026-03-04 16:15:27','2026-03-04 16:15:05'),(18,15,'8ceb5899942184fdb1d2ae550cc1cb089c0c2ad222606516020b1ba10ee8190b','cancel',1,'2026-03-06 16:15:05','2026-03-04 16:15:27','2026-03-04 16:15:05'),(19,16,'52bee56e293cd87e3e04cab0a411466b42c3bc95fd31f2ea790ae6d9c1a5d613','confirm',1,'2026-03-06 16:20:49','2026-03-04 16:21:21','2026-03-04 16:20:49'),(20,16,'f9c75fdeb91b3c6babec36fd73d869e51ece853b452048fb719110ab6d07f722','cancel',1,'2026-03-06 16:20:49','2026-03-04 16:21:21','2026-03-04 16:20:49'),(21,14,'c2bf81b630e315cb1fa485e1c1e7966ffb7064243b43c900425e935f663224e7','confirm',0,'2026-03-06 17:35:10',NULL,'2026-03-04 17:35:10'),(22,14,'a485ba0edd6e6af7467fb29e71add8e57c4d62919507e8fc4aa233c9418e4b1d','cancel',0,'2026-03-06 17:35:10',NULL,'2026-03-04 17:35:10'),(23,13,'52ff56da2d48e763287fced267f226490c6e195d85b10997d5807aab128aea5e','confirm',1,'2026-03-06 17:36:48','2026-03-04 17:37:31','2026-03-04 17:36:48'),(24,13,'20b80ecdd57f3b3cf6cc4c29fb08e936c818b223a55769c95112858b0fabcb44','cancel',1,'2026-03-06 17:36:48','2026-03-04 17:37:32','2026-03-04 17:36:48'),(25,14,'8ba7c479fdaeded56a2e4a94b6f3a522e5cf16fe90c8a7687ef9a20212f12382','confirm',0,'2026-03-06 19:55:52',NULL,'2026-03-04 19:55:52'),(26,14,'86fc348868a2c278292c2517e8b1ec850355637b7d947e01e23fb15292f5150e','cancel',0,'2026-03-06 19:55:52',NULL,'2026-03-04 19:55:52'),(27,14,'0a31f5b3c1697ad2da3c34d9de82d91688bc062f58a81f5239a26ef848648bd4','confirm',0,'2026-03-07 12:05:29',NULL,'2026-03-05 12:05:29'),(28,14,'58fe32694ed760e1113f8736ea3eb2451bcda21d582d586f8f42d842162e994b','cancel',0,'2026-03-07 12:05:29',NULL,'2026-03-05 12:05:29'),(29,12,'c69d13ff752187cecbda54b7219829e0de50c8fb1d2230cee0bc60f7514901b8','confirm',1,'2026-03-07 14:00:34','2026-03-05 14:01:50','2026-03-05 14:00:34'),(30,12,'0db70ca010b568d1470e0cb1524df753bfcc1b3987acd4179abbf329ee480af6','cancel',1,'2026-03-07 14:00:34','2026-03-05 14:01:50','2026-03-05 14:00:34'),(31,17,'e1b6bfff8ca24343c6ff1a4d5c0f1c8142e96504c29c3674da51157f328645f3','confirm',0,'2026-03-07 14:00:52',NULL,'2026-03-05 14:00:52'),(32,17,'50715b5ace56441eb8233952debd6881f219a918ed3096efd20dea75f21373cd','cancel',0,'2026-03-07 14:00:52',NULL,'2026-03-05 14:00:52');
/*!40000 ALTER TABLE `confirmation_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_messages`
--

DROP TABLE IF EXISTS `contact_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_read` (`is_read`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_messages`
--

LOCK TABLES `contact_messages` WRITE;
/*!40000 ALTER TABLE `contact_messages` DISABLE KEYS */;
INSERT INTO `contact_messages` VALUES (1,'jaymin chavda','jaymin29chavda@gmail.com','+919974157344','veb','dwefweds',1,'2026-03-01 11:47:48');
/*!40000 ALTER TABLE `contact_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `family_history`
--

DROP TABLE IF EXISTS `family_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `family_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `appointment_id` int(11) NOT NULL,
  `relation` varchar(50) NOT NULL,
  `disease` varchar(150) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `appointment_id` (`appointment_id`),
  CONSTRAINT `family_history_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `family_history`
--

LOCK TABLES `family_history` WRITE;
/*!40000 ALTER TABLE `family_history` DISABLE KEYS */;
INSERT INTO `family_history` VALUES (1,2,'father','diabites','NA','2026-03-01 11:41:59'),(2,9,'ognorwvorw','enovnwi','oweo9vnw','2026-03-01 22:02:13'),(3,12,'ehth','ey','g4h','2026-03-04 11:10:01');
/*!40000 ALTER TABLE `family_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `legal_pages`
--

DROP TABLE IF EXISTS `legal_pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `legal_pages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `content` longtext NOT NULL COMMENT 'HTML content allowed',
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_slug` (`slug`),
  KEY `created_by` (`created_by`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `legal_pages_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `legal_pages_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `legal_pages`
--

LOCK TABLES `legal_pages` WRITE;
/*!40000 ALTER TABLE `legal_pages` DISABLE KEYS */;
INSERT INTO `legal_pages` VALUES (1,'Privacy Policy','privacy-policy','<h2>Privacy Policy</h2><p>Your privacy is important to us. This policy explains how we collect, use, and protect your personal information.</p>',NULL,NULL,'2026-03-05 22:18:35','2026-03-05 22:18:35'),(2,'Terms & Conditions','terms-conditions','<h2>Terms & Conditions</h2><p>By using our services, you agree to these terms and conditions.</p>',NULL,NULL,'2026-03-05 22:18:35','2026-03-05 22:18:35');
/*!40000 ALTER TABLE `legal_pages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(150) NOT NULL,
  `ip_address` varchar(45) NOT NULL DEFAULT '',
  `user_agent` varchar(255) NOT NULL DEFAULT '',
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_email_time` (`email`,`attempted_at`),
  KEY `idx_ip_time` (`ip_address`,`attempted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

LOCK TABLES `login_attempts` WRITE;
/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
INSERT INTO `login_attempts` VALUES (1,'admin@bansari.com','::1','curl/8.18.0',0,'2026-03-05 21:01:53'),(2,'admin@bansari.com','::1','curl/8.18.0',0,'2026-03-05 21:07:23'),(3,'admin@bansari.com','::1','curl/8.18.0',0,'2026-03-05 21:23:13'),(4,'admin@bansari.com','::1','curl/8.18.0',0,'2026-03-05 21:25:25'),(5,'admin@bansari.com','::1','curl/8.18.0',1,'2026-03-05 21:26:11'),(6,'admin@bansari.com','::1','curl/8.18.0',0,'2026-03-05 21:54:14'),(7,'admin@bansari.com','::1','curl/8.18.0',0,'2026-03-05 21:54:23'),(8,'admin@bansari.com','::1','curl/8.18.0',1,'2026-03-05 22:18:56'),(9,'admin@bansari.com','::1','curl/8.18.0',1,'2026-03-05 22:19:14');
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `main_complaints`
--

DROP TABLE IF EXISTS `main_complaints`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `main_complaints` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `appointment_id` int(11) NOT NULL,
  `complaint_text` text NOT NULL,
  `duration` varchar(100) DEFAULT NULL,
  `severity` enum('mild','moderate','severe') DEFAULT 'moderate',
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `appointment_id` (`appointment_id`),
  CONSTRAINT `main_complaints_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `main_complaints`
--

LOCK TABLES `main_complaints` WRITE;
/*!40000 ALTER TABLE `main_complaints` DISABLE KEYS */;
INSERT INTO `main_complaints` VALUES (1,2,'wgkeoenb','brbenb','moderate',0,'2026-03-01 11:41:59'),(2,9,'egjieib','v0svoim','mild',0,'2026-03-01 22:02:13'),(3,12,'erhrthrh','grg','moderate',0,'2026-03-04 11:10:01'),(4,13,'bdfniobnfs','lovsdniovnds','mild',0,'2026-03-04 20:14:45');
/*!40000 ALTER TABLE `main_complaints` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mental_profile`
--

DROP TABLE IF EXISTS `mental_profile`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mental_profile` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `appointment_id` int(11) NOT NULL,
  `temperament` varchar(100) DEFAULT NULL,
  `fears` text DEFAULT NULL,
  `dreams` text DEFAULT NULL,
  `stress_factors` text DEFAULT NULL,
  `emotional_state` text DEFAULT NULL,
  `hobbies` text DEFAULT NULL,
  `social_behavior` varchar(100) DEFAULT NULL,
  `additional_notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `appointment_id` (`appointment_id`),
  CONSTRAINT `mental_profile_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mental_profile`
--

LOCK TABLES `mental_profile` WRITE;
/*!40000 ALTER TABLE `mental_profile` DISABLE KEYS */;
INSERT INTO `mental_profile` VALUES (1,2,'egerbeb','gerge','gw3rge','rgwrger','gerger','grber','gege','gerget','2026-03-01 11:41:59'),(2,7,'','','','','','','','','2026-03-01 13:00:28'),(3,9,'vwfjv l','lwnvkjwn','pvjweofj','s;nvlkn','lvnsonvi','vnwsvnlwn','vklsnlvo','vlnlvn','2026-03-01 22:02:13'),(4,12,'erye','ge','t3t','terter','gerget','eeh','y4ty3t','3h','2026-03-04 11:10:01'),(5,13,'ovndsinuv','linaoinv','IDUBUOCN','FIUAE9VNA','SDIBDS',';PIC0ISDJV','SFOIBNFIPSV','JDQANOFANIKD','2026-03-04 20:14:45');
/*!40000 ALTER TABLE `mental_profile` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `past_diseases`
--

DROP TABLE IF EXISTS `past_diseases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `past_diseases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `appointment_id` int(11) NOT NULL,
  `disease_name` varchar(150) NOT NULL,
  `details` text DEFAULT NULL,
  `year_diagnosed` varchar(10) DEFAULT NULL,
  `treatment_taken` text DEFAULT NULL,
  `is_current` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `appointment_id` (`appointment_id`),
  CONSTRAINT `past_diseases_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `past_diseases`
--

LOCK TABLES `past_diseases` WRITE;
/*!40000 ALTER TABLE `past_diseases` DISABLE KEYS */;
INSERT INTO `past_diseases` VALUES (1,2,'gergrborng','','ferger','r3g',1,'2026-03-01 11:41:59'),(2,9,'eonoenv','','vwevewh98h','98hv9weh8v',1,'2026-03-01 22:02:13'),(3,12,'gthh','','eth','heh',1,'2026-03-04 11:10:01');
/*!40000 ALTER TABLE `past_diseases` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `patients`
--

DROP TABLE IF EXISTS `patients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `patients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) NOT NULL,
  `mobile` varchar(15) NOT NULL,
  `age` int(11) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `address` varchar(500) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `plain_password` varchar(255) DEFAULT NULL,
  `is_registered` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_mobile` (`mobile`),
  KEY `idx_name` (`full_name`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `patients`
--

LOCK TABLES `patients` WRITE;
/*!40000 ALTER TABLE `patients` DISABLE KEYS */;
INSERT INTO `patients` VALUES (1,'vidhi','+919974157344',19,'female','AHMEDABAD',NULL,NULL,NULL,NULL,0,'2026-03-01 10:59:41','2026-03-01 11:45:05'),(2,'vansh chavda','9974157344',14,'male','AHMEDABAD',NULL,'ajay27chavda@gmail.com','$2y$10$2PZKClO/BJ1YPDOu6VGfhO/h0D3e.icNgA709IOw1WIOkSjovOSHy',NULL,1,'2026-03-01 11:07:21','2026-03-05 19:26:55'),(3,'jaymin chavda','7046221515',19,'male','AHMEDABAD',NULL,'jaymin29chavda@gmail.com','$2y$10$2PZKClO/BJ1YPDOu6VGfhO/h0D3e.icNgA709IOw1WIOkSjovOSHy',NULL,1,'2026-03-01 11:41:59','2026-03-05 19:26:55'),(4,'Test Patient','9876543210',NULL,NULL,'',NULL,'test@example.com','$2y$10$2PZKClO/BJ1YPDOu6VGfhO/h0D3e.icNgA709IOw1WIOkSjovOSHy',NULL,1,'2026-03-01 21:56:26','2026-03-05 19:26:55'),(5,'sonalben ajaybhai Chavda','7046221514',45,'male','ahmedabad',NULL,'jaymin29chavda@gmail.com','$2y$10$2PZKClO/BJ1YPDOu6VGfhO/h0D3e.icNgA709IOw1WIOkSjovOSHy',NULL,1,'2026-03-01 21:58:22','2026-03-05 19:26:55'),(6,'New User','8888888888',NULL,NULL,'',NULL,'new@test.com','$2y$10$2PZKClO/BJ1YPDOu6VGfhO/h0D3e.icNgA709IOw1WIOkSjovOSHy',NULL,1,'2026-03-01 22:06:41','2026-03-05 19:26:55'),(7,'Fresh User','7777777777',NULL,NULL,'',NULL,'fresh@test.com','$2y$10$2PZKClO/BJ1YPDOu6VGfhO/h0D3e.icNgA709IOw1WIOkSjovOSHy',NULL,1,'2026-03-01 22:07:00','2026-03-05 19:26:55'),(8,'chavda jaymin','8948914142',23,'male','0gsojgw',NULL,'jaymin29chavda@gmail.com','$2y$10$2PZKClO/BJ1YPDOu6VGfhO/h0D3e.icNgA709IOw1WIOkSjovOSHy',NULL,1,'2026-03-02 19:27:33','2026-03-05 19:26:55'),(9,'JAYMIN CHAVDA','7045651616',12,'male','jaymin29chavda@gmail.com',NULL,'jaymin29chavda@gmail.com','$2y$10$2PZKClO/BJ1YPDOu6VGfhO/h0D3e.icNgA709IOw1WIOkSjovOSHy','jaymin@2006',1,'2026-03-04 11:02:46','2026-03-05 19:26:55'),(10,'JAYMIN CHAVDA','9984946544',23,'male','ahm',NULL,'230170146009@vgecg.ac.in','$2y$10$2PZKClO/BJ1YPDOu6VGfhO/h0D3e.icNgA709IOw1WIOkSjovOSHy','Jaymin@2901',1,'2026-03-04 11:08:32','2026-03-05 19:26:55'),(11,'sonalben ajaybhai Chavda','6464698456',47,'female','Ahmedabad',NULL,'sonal2479chavda@gmail.com','$2y$10$2PZKClO/BJ1YPDOu6VGfhO/h0D3e.icNgA709IOw1WIOkSjovOSHy','Sonal@2479',1,'2026-03-04 20:52:38','2026-03-05 19:26:55'),(12,'manish chavda','9898721213',0,'male','Ahmedabad',NULL,'chavdajaymin06@gmail.com','$2y$10$2PZKClO/BJ1YPDOu6VGfhO/h0D3e.icNgA709IOw1WIOkSjovOSHy','jaymin@06',1,'2026-03-04 21:43:04','2026-03-05 19:26:55'),(13,'JAYMIN CHAVDA','5757575757',21,'male','ahm',NULL,'chavdajaymin06@gmail.com','$2y$10$2PZKClO/BJ1YPDOu6VGfhO/h0D3e.icNgA709IOw1WIOkSjovOSHy',NULL,1,'2026-03-05 19:17:28','2026-03-05 19:29:07');
/*!40000 ALTER TABLE `patients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `physical_generals`
--

DROP TABLE IF EXISTS `physical_generals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `physical_generals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `appointment_id` int(11) NOT NULL,
  `appetite` enum('good','moderate','poor','variable') DEFAULT 'good',
  `thirst` enum('normal','increased','decreased','absent') DEFAULT 'normal',
  `stool` enum('regular','constipated','loose','alternating') DEFAULT 'regular',
  `urine` enum('normal','frequent','scanty','burning') DEFAULT 'normal',
  `sweat` enum('normal','profuse','absent','offensive') DEFAULT 'normal',
  `sleep_quality` enum('sound','disturbed','insomnia','excessive') DEFAULT 'sound',
  `sleep_position` varchar(50) DEFAULT NULL,
  `thermal` enum('hot','chilly','ambithermal') DEFAULT 'ambithermal',
  `cravings` text DEFAULT NULL,
  `aversions` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `appointment_id` (`appointment_id`),
  CONSTRAINT `physical_generals_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `physical_generals`
--

LOCK TABLES `physical_generals` WRITE;
/*!40000 ALTER TABLE `physical_generals` DISABLE KEYS */;
INSERT INTO `physical_generals` VALUES (1,2,'good','normal','constipated','frequent','profuse','disturbed','left side','hot','sweet','milk','2026-03-01 11:41:59'),(2,7,'good','normal','regular','normal','normal','sound','','ambithermal','','','2026-03-01 13:00:28'),(3,9,'moderate','increased','constipated','scanty','profuse','disturbed','dpvoiiv','hot','lweovsw','j vk,jjv','2026-03-01 22:02:13'),(4,12,'poor','decreased','constipated','frequent','profuse','insomnia','geregw','chilly','etyey','getet','2026-03-04 11:10:01'),(5,13,'moderate','normal','constipated','scanty','normal','insomnia','pdsnovidsn','chilly','lcoiasnv','ouaiuca','2026-03-04 20:14:45');
/*!40000 ALTER TABLE `physical_generals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reminder_logs`
--

DROP TABLE IF EXISTS `reminder_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reminder_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `appointment_id` int(11) NOT NULL,
  `channel` enum('whatsapp','email') NOT NULL,
  `status` enum('queued','sent','delivered','failed','replied') DEFAULT 'queued',
  `message_id` varchar(255) DEFAULT NULL,
  `recipient` varchar(255) NOT NULL,
  `template_name` varchar(100) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `patient_reply` text DEFAULT NULL,
  `reply_received_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_appointment` (`appointment_id`),
  KEY `idx_status` (`status`),
  KEY `idx_channel` (`channel`),
  KEY `idx_message_id` (`message_id`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `reminder_logs_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reminder_logs`
--

LOCK TABLES `reminder_logs` WRITE;
/*!40000 ALTER TABLE `reminder_logs` DISABLE KEYS */;
INSERT INTO `reminder_logs` VALUES (1,13,'whatsapp','failed',NULL,'8948914142',NULL,'WhatsApp API not configured',NULL,NULL,NULL,'2026-03-04 15:02:30','2026-03-04 15:02:30'),(2,13,'email','replied',NULL,'jaymin29chavda@gmail.com',NULL,'Email service not configured','CONFIRMED (via email link)','2026-03-04 17:37:32',NULL,'2026-03-04 15:02:30','2026-03-04 17:37:32'),(3,13,'whatsapp','failed',NULL,'8948914142',NULL,'WhatsApp API not configured',NULL,NULL,NULL,'2026-03-04 15:02:44','2026-03-04 15:02:44'),(4,13,'email','replied',NULL,'jaymin29chavda@gmail.com',NULL,'Email service not configured','CONFIRMED (via email link)','2026-03-04 17:37:32',NULL,'2026-03-04 15:02:44','2026-03-04 17:37:32'),(5,13,'whatsapp','failed',NULL,'8948914142',NULL,'WhatsApp API not configured',NULL,NULL,NULL,'2026-03-04 15:03:07','2026-03-04 15:03:07'),(6,13,'email','replied',NULL,'jaymin29chavda@gmail.com',NULL,'Email service not configured','CONFIRMED (via email link)','2026-03-04 17:37:32',NULL,'2026-03-04 15:03:07','2026-03-04 17:37:32'),(7,13,'whatsapp','failed',NULL,'8948914142',NULL,'WhatsApp API not configured',NULL,NULL,NULL,'2026-03-04 15:18:14','2026-03-04 15:18:14'),(8,13,'email','replied','<39bb4ba2-65ec-d58e-52e8-bf256f478815@gmail.com>','jaymin29chavda@gmail.com',NULL,NULL,'CONFIRMED (via email link)','2026-03-04 17:37:32','2026-03-04 15:18:20','2026-03-04 15:18:20','2026-03-04 17:37:32'),(9,13,'whatsapp','failed',NULL,'8948914142',NULL,'WhatsApp API not configured',NULL,NULL,NULL,'2026-03-04 15:18:57','2026-03-04 15:18:57'),(10,13,'email','replied','<dd7df9ad-1693-e61f-1f78-2e7dd3c89bb6@gmail.com>','jaymin29chavda@gmail.com',NULL,NULL,'CONFIRMED (via email link)','2026-03-04 17:37:32','2026-03-04 15:18:59','2026-03-04 15:18:59','2026-03-04 17:37:32'),(11,14,'whatsapp','failed',NULL,'6464698456',NULL,'WhatsApp API not configured',NULL,NULL,NULL,'2026-03-04 15:33:13','2026-03-04 15:33:13'),(12,14,'email','sent','<9841b4bf-d297-425b-258d-2ea2f1287bfa@gmail.com>','sonal2479chavda@gmail.com',NULL,NULL,NULL,NULL,'2026-03-04 15:33:19','2026-03-04 15:33:19','2026-03-04 15:33:19'),(13,6,'email','sent','<91f9c592-aafc-1d28-d74f-2bded82ee5b8@gmail.com>','ajay27chavda@gmail.com',NULL,NULL,NULL,NULL,'2026-03-04 16:07:38','2026-03-04 16:07:38','2026-03-04 16:07:38'),(14,14,'whatsapp','failed',NULL,'6464698456',NULL,'WhatsApp API not configured',NULL,NULL,NULL,'2026-03-04 16:08:25','2026-03-04 16:08:25'),(15,14,'email','sent','<943b7530-c489-68ff-1c66-7e6a073d85f2@gmail.com>','sonal2479chavda@gmail.com',NULL,NULL,NULL,NULL,'2026-03-04 16:08:39','2026-03-04 16:08:39','2026-03-04 16:08:39'),(16,15,'whatsapp','failed',NULL,'75464948949',NULL,'WhatsApp API not configured',NULL,NULL,NULL,'2026-03-04 16:14:58','2026-03-04 16:14:58'),(17,15,'email','replied','<fde2bc3b-19af-a41d-2aaf-39022995e863@gmail.com>','chavdajaymin06@gmail.com',NULL,NULL,'CONFIRMED (via email link)','2026-03-04 16:15:27','2026-03-04 16:15:10','2026-03-04 16:15:10','2026-03-04 16:15:27'),(18,16,'email','replied','<f5289e6c-f51e-2bec-68e8-11cf2255560d@gmail.com>','chavdajaymin06@gmail.com',NULL,NULL,'CONFIRMED (via email link)','2026-03-04 16:21:21','2026-03-04 16:20:54','2026-03-04 16:20:54','2026-03-04 16:21:21'),(19,14,'email','sent','<6f57a3b1-6268-18d9-c9a8-92cd755cce6c@gmail.com>','sonal2479chavda@gmail.com',NULL,NULL,NULL,NULL,'2026-03-04 17:35:15','2026-03-04 17:35:15','2026-03-04 17:35:15'),(20,13,'email','replied','<c39d1e1f-e0de-0eb4-8226-6a7c77407a8e@gmail.com>','jaymin29chavda@gmail.com',NULL,NULL,'CONFIRMED (via email link)','2026-03-04 17:37:32','2026-03-04 17:36:51','2026-03-04 17:36:51','2026-03-04 17:37:32'),(21,14,'whatsapp','failed',NULL,'6464698456',NULL,'WhatsApp API not configured',NULL,NULL,NULL,'2026-03-04 19:42:51','2026-03-04 19:42:51'),(22,14,'whatsapp','failed',NULL,'6464698456',NULL,'WhatsApp API not configured',NULL,NULL,NULL,'2026-03-04 19:44:55','2026-03-04 19:44:55'),(23,14,'email','sent','<1b8442d6-178a-9a69-fd8a-a882b4850640@gmail.com>','sonal2479chavda@gmail.com',NULL,NULL,NULL,NULL,'2026-03-04 19:55:58','2026-03-04 19:55:58','2026-03-04 19:55:58'),(24,14,'email','sent','<797bf563-3e7f-b365-4d6c-10c86957d972@gmail.com>','sonal2479chavda@gmail.com',NULL,NULL,NULL,NULL,'2026-03-05 12:05:34','2026-03-05 12:05:34','2026-03-05 12:05:34'),(25,12,'email','replied','<76f01c36-c57b-6ce8-28c2-51ceeb13ba41@gmail.com>','230170146009@vgecg.ac.in',NULL,NULL,'CONFIRMED (via email link)','2026-03-05 14:01:50','2026-03-05 14:00:43','2026-03-05 14:00:43','2026-03-05 14:01:50'),(26,17,'email','sent','<dacf3511-0b48-d908-16f1-a5424fcf0af9@gmail.com>','chavdajaymin06@gmail.com',NULL,NULL,NULL,NULL,'2026-03-05 14:00:54','2026-03-05 14:00:54','2026-03-05 14:00:54');
/*!40000 ALTER TABLE `reminder_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reminder_rate_limits`
--

DROP TABLE IF EXISTS `reminder_rate_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reminder_rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL DEFAULT 'send_reminder',
  `attempted_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_admin_action` (`admin_id`,`action`),
  KEY `idx_appointment` (`appointment_id`),
  KEY `idx_attempted` (`attempted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reminder_rate_limits`
--

LOCK TABLES `reminder_rate_limits` WRITE;
/*!40000 ALTER TABLE `reminder_rate_limits` DISABLE KEYS */;
INSERT INTO `reminder_rate_limits` VALUES (1,1,6,'followup_reminder','2026-03-04 16:07:38'),(2,1,14,'followup_reminder','2026-03-04 16:08:25'),(3,1,14,'followup_reminder','2026-03-04 16:08:39'),(4,1,15,'followup_reminder','2026-03-04 16:14:58'),(5,1,15,'followup_reminder','2026-03-04 16:15:10'),(6,1,16,'followup_reminder','2026-03-04 16:20:54'),(7,1,14,'followup_reminder','2026-03-04 17:35:15'),(8,1,13,'followup_reminder','2026-03-04 17:36:52'),(9,1,14,'followup_reminder','2026-03-04 19:42:51'),(10,1,14,'followup_reminder','2026-03-04 19:44:55'),(11,1,14,'followup_reminder','2026-03-04 19:56:03'),(12,1,14,'followup_reminder','2026-03-05 12:05:35'),(13,1,12,'followup_reminder','2026-03-05 14:00:44'),(14,1,17,'followup_reminder','2026-03-05 14:00:54');
/*!40000 ALTER TABLE `reminder_rate_limits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `testimonials`
--

DROP TABLE IF EXISTS `testimonials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `testimonials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_name` varchar(150) DEFAULT NULL,
  `is_anonymous` tinyint(1) DEFAULT 0,
  `treatment_description` text NOT NULL,
  `testimonial_text` text DEFAULT NULL,
  `before_image` varchar(255) DEFAULT NULL,
  `after_image` varchar(255) DEFAULT NULL,
  `rating` tinyint(4) DEFAULT 5,
  `display_status` enum('active','inactive') DEFAULT 'active',
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status` (`display_status`),
  KEY `idx_order` (`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `testimonials`
--

LOCK TABLES `testimonials` WRITE;
/*!40000 ALTER TABLE `testimonials` DISABLE KEYS */;
INSERT INTO `testimonials` VALUES (1,'sonal chavda',0,'Weight loss jurney is uccesfully complated in 2 month','foiwofhogh obnwo deiwf wi db iwebfiow bibzeiu fubdf jerijh ui bi','before_1772343744_471a855c.png','after_1772343744_9ab3b091.png',4,'active',1,'2026-03-01 11:09:57','2026-03-01 11:12:24'),(2,'patient 1',0,'kapasi treatment','wobowvnwv','before_1772384005_d8ed8b22.jpg','after_1772384005_5200c77e.jpg',5,'active',0,'2026-03-01 22:23:25','2026-03-01 22:23:25');
/*!40000 ALTER TABLE `testimonials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `website_settings`
--

DROP TABLE IF EXISTS `website_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `website_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('text','textarea','image','html','json') DEFAULT 'text',
  `setting_group` varchar(50) DEFAULT 'general',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `idx_key` (`setting_key`),
  KEY `idx_group` (`setting_group`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `website_settings`
--

LOCK TABLES `website_settings` WRITE;
/*!40000 ALTER TABLE `website_settings` DISABLE KEYS */;
INSERT INTO `website_settings` VALUES (1,'about_doctor_name','Dr. Bansari Patel','text','about','2026-03-01 10:47:49'),(2,'about_doctor_title','BHMS, MD (Homeopathy)','text','about','2026-03-01 10:47:49'),(3,'about_doctor_image','','image','about','2026-03-01 10:47:49'),(4,'about_doctor_bio','Dr. Bansari Patel is a dedicated homeopathic practitioner with years of experience in treating chronic and acute conditions through classical homeopathy. She believes in holistic healing that addresses the root cause of disease rather than just symptoms.','textarea','about','2026-03-01 10:47:49'),(5,'about_clinic_philosophy','Here, holistic healing occurs when patient comes with long term complaints which can not permanently gone or reducing. So, best option for long term, exhausted, medicine dependent patients...this homeopathic medicines, life style changes and good awareness with needed counselling, can remove disease and freedom from life-long medicines without any side effects. We ensure that patient will satisfy and happy after consultation... So, must try and be healthy for lifetime.','textarea','about','2026-03-04 11:19:04'),(6,'about_experience','5+ Years of Experience','text','about','2026-03-05 01:11:17'),(7,'about_mission','To provide gentle, effective, and lasting homeopathic treatment that improves quality of life for every patient who walks through our doors.','textarea','about','2026-03-01 10:47:49'),(8,'about_vision','To become the most trusted homeopathic healthcare provider, making natural healing accessible to everyone in our community and beyond.','textarea','about','2026-03-01 10:47:49'),(9,'about_clinic_image','','image','about','2026-03-01 10:47:49'),(10,'contact_address','212 A, Ratnadeep Flora 2nd Floor, Opposite Sv Square, Smruti Circle, New Ranip, Ahmedabad-382480, Gujarat','textarea','contact','2026-03-05 01:33:32'),(11,'contact_phone','+91 63543 88539','text','contact','2026-03-04 11:19:04'),(12,'contact_whatsapp','+91 63543 88539','text','contact','2026-03-04 11:19:04'),(13,'contact_email','info@bansarihomeopathy.com','text','contact','2026-03-01 10:47:49'),(14,'contact_map_iframe','https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3671.5!2d72.5714!3d23.0694!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x395e832b5fccb64d%3A0x9d4e7c4d4bca3e3f!2sRatnadeep%20Flora!5e0!3m2!1sen!2sin!4v1709500000000','text','contact','2026-03-04 11:19:04'),(15,'contact_hours','Mon - Sat: 9:00 AM - 1:00 PM, 5:00 PM - 8:00 PM\r\nSunday: Closed','textarea','contact','2026-03-01 12:07:28'),(16,'home_hero_title','Bansari Homeopathy','text','home','2026-03-02 18:57:06'),(17,'home_hero_subtitle','Gentle Healing, Lasting Results','text','home','2026-03-01 10:47:49'),(18,'home_hero_description','Experience the power of classical homeopathy with Dr. Bansari Patel. Personalized treatment for chronic and acute conditions.','textarea','home','2026-03-01 10:47:49'),(19,'home_hero_image','','image','home','2026-03-01 10:47:49'),(20,'clinic_name','Bansari Homeopathy Clinic','text','general','2026-03-01 10:47:49'),(21,'clinic_logo','logo_1772458074_fb6ebeb1.jpg','image','general','2026-03-02 18:57:54'),(22,'clinic_tagline','Gentle Healing, Lasting Results','text','general','2026-03-01 10:47:49'),(40,'contact_map_url','https://goo.gl/maps/NoSHG42xZDhSGq2x9?g_st=ac','text','contact','2026-03-04 11:19:04');
/*!40000 ALTER TABLE `website_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'bansari_clinic'
--

--
-- Dumping routines for database 'bansari_clinic'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-03-05 22:24:06
