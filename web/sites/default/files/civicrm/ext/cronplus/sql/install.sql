--
-- Table structure for table `civicrm_job_scheduled`
--

CREATE TABLE IF NOT EXISTS `civicrm_job_scheduled` (
  `job_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Job ID',
  `cron` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`job_id`),
  CONSTRAINT `civicrm_job_scheduled_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `civicrm_job` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
