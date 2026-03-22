/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.5.29-MariaDB, for Linux (x86_64)
--
-- Host: localhost    Database: loger
-- ------------------------------------------------------
-- Server version	10.5.29-MariaDB

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
-- Table structure for table `contests`
--

DROP TABLE IF EXISTS `contests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `rounds` int(11) NOT NULL DEFAULT 2,
  `round_duration_sec` int(11) NOT NULL DEFAULT 300,
  `start_datetime` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `break_duration_sec` int(11) NOT NULL DEFAULT 180,
  `require_peer_qso_no` tinyint(1) DEFAULT 0,
  `tapeta` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `kursanci`
--

DROP TABLE IF EXISTS `kursanci`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `kursanci` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `login` varchar(20) NOT NULL,
  `haslo` varchar(255) NOT NULL,
  `opis` varchar(100) DEFAULT NULL,
  `display_name` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `login` (`login`)
) ENGINE=InnoDB AUTO_INCREMENT=141 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `qso_log`
--

DROP TABLE IF EXISTS `qso_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `qso_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kursant` varchar(20) NOT NULL,
  `znak_qso` varchar(20) NOT NULL,
  `data_qso` date DEFAULT NULL,
  `czas_qso` time DEFAULT NULL,
  `raport` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `kursant_id` int(11) DEFAULT NULL,
  `my_qso_no` int(11) NOT NULL DEFAULT 0,
  `peer_qso_no` int(11) DEFAULT NULL,
  `round_no` int(11) DEFAULT NULL,
  `contest_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_kursant` (`kursant_id`),
  KEY `idx_kursant` (`kursant`),
  KEY `idx_znak_qso` (`znak_qso`),
  KEY `idx_round_no` (`round_no`),
  KEY `idx_contest_id` (`contest_id`),
  CONSTRAINT `fk_kursant` FOREIGN KEY (`kursant_id`) REFERENCES `kursanci` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=311 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary table structure for view `ranking_view`
--

DROP TABLE IF EXISTS `ranking_view`;
/*!50001 DROP VIEW IF EXISTS `ranking_view`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `ranking_view` AS SELECT
 1 AS `kursant`,
  1 AS `round_no`,
  1 AS `contest_id`,
  1 AS `punkty` */;
SET character_set_client = @saved_cs_client;

--
-- Final view structure for view `ranking_view`
--

/*!50001 DROP VIEW IF EXISTS `ranking_view`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `ranking_view` AS select `paired`.`kursant` AS `kursant`,`paired`.`round_no` AS `round_no`,`paired`.`contest_id` AS `contest_id`,count(0) AS `punkty` from (select `l1`.`kursant` AS `kursant`,`l1`.`round_no` AS `round_no`,`l1`.`contest_id` AS `contest_id` from ((`qso_log` `l1` join `qso_log` `l2` on(`l1`.`kursant` <> `l2`.`kursant` and `l1`.`znak_qso` = `l2`.`kursant` and `l2`.`znak_qso` = `l1`.`kursant` and `l1`.`id` < `l2`.`id` and `l1`.`round_no` = `l2`.`round_no` and `l1`.`contest_id` = `l2`.`contest_id` and abs(timestampdiff(SECOND,`l1`.`created_at`,`l2`.`created_at`)) <= 45)) join `contests` `c` on(`c`.`id` = `l1`.`contest_id`)) where `c`.`require_peer_qso_no` = 0 or `l1`.`my_qso_no` = `l2`.`peer_qso_no` and `l2`.`my_qso_no` = `l1`.`peer_qso_no` group by least(`l1`.`kursant`,`l2`.`kursant`),greatest(`l1`.`kursant`,`l2`.`kursant`),`l1`.`round_no`,`l1`.`contest_id` union all select `l2`.`kursant` AS `kursant`,`l2`.`round_no` AS `round_no`,`l2`.`contest_id` AS `contest_id` from ((`qso_log` `l1` join `qso_log` `l2` on(`l1`.`kursant` <> `l2`.`kursant` and `l1`.`znak_qso` = `l2`.`kursant` and `l2`.`znak_qso` = `l1`.`kursant` and `l1`.`id` < `l2`.`id` and `l1`.`round_no` = `l2`.`round_no` and `l1`.`contest_id` = `l2`.`contest_id` and abs(timestampdiff(SECOND,`l1`.`created_at`,`l2`.`created_at`)) <= 45)) join `contests` `c` on(`c`.`id` = `l1`.`contest_id`)) where `c`.`require_peer_qso_no` = 0 or `l1`.`my_qso_no` = `l2`.`peer_qso_no` and `l2`.`my_qso_no` = `l1`.`peer_qso_no` group by least(`l1`.`kursant`,`l2`.`kursant`),greatest(`l1`.`kursant`,`l2`.`kursant`),`l1`.`round_no`,`l1`.`contest_id`) `paired` group by `paired`.`kursant`,`paired`.`round_no`,`paired`.`contest_id` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-03-22 23:06:08
