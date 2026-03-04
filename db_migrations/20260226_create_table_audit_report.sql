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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci
