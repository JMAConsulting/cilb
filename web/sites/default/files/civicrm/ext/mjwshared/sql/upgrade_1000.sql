-- /*******************************************************
-- *
-- * civicrm_paymentprocessor_webhook
-- *
-- * Track the processing of payment processor webhooks
-- *
-- *******************************************************/
CREATE TABLE `civicrm_paymentprocessor_webhook` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique PaymentprocessorWebhook ID',
  `payment_processor_id` int unsigned COMMENT 'Payment Processor for this webhook',
  `event_id` varchar(255) COMMENT 'Webhook event ID',
  `trigger` varchar(255) COMMENT 'Webhook trigger event type',
  `created_date` timestamp DEFAULT CURRENT_TIMESTAMP COMMENT 'When the webhook was first received by the IPN code',
  `processed_date` timestamp NULL DEFAULT NULL COMMENT 'Has this webhook been processed yet?',
  `status` varchar(255) COMMENT 'Webhook processing status',
  `identifier` text,
  PRIMARY KEY (`id`),
  CONSTRAINT FK_civicrm_paymentprocessor_webhook_payment_processor_id FOREIGN KEY (`payment_processor_id`) REFERENCES `civicrm_payment_processor`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;
