--
-- Alter table structure `civicrm_job_scheduled`
--

ALTER TABLE `civicrm_job_scheduled`
ADD COLUMN `cron` varchar(255) DEFAULT NULL;
