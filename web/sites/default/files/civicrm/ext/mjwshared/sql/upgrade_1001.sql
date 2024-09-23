-- The old default for 'status' field was NULL, new is 'new'
UPDATE civicrm_paymentprocessor_webhook
SET status = 'new'
WHERE status IS NULL
;

ALTER TABLE civicrm_paymentprocessor_webhook
MODIFY `status` varchar(32) NOT NULL DEFAULT "new" COMMENT 'Processing status',
MODIFY `identifier` varchar(255) COMMENT 'Optional key to group webhooks, as needed by some processors.',
ADD `message` varchar(1024) NOT NULL DEFAULT "" COMMENT 'Stores data sent that is needed for processing. JSON suggested.',
ADD `data` text COMMENT 'Stores data sent that is needed for processing. JSON suggested.',
ADD INDEX `index_event_id`(event_id),
ADD INDEX `index_status_processed_date`(status, processed_date),
ADD INDEX `index_created_date`(created_date)
;
