ALTER TABLE `#__decaromembership_members`
  ADD COLUMN `application_date` DATE NULL AFTER `first_registration_date`,
  ADD COLUMN `admission_date` DATE NULL AFTER `application_date`,
  ADD COLUMN `current_membership_start_date` DATE NULL AFTER `admission_date`,
  ADD COLUMN `seniority_credit_days` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `current_membership_start_date`,
  ADD COLUMN `status_effective_date` DATE NULL AFTER `seniority_credit_days`,
  ADD COLUMN `cessation_date` DATE NULL AFTER `status_effective_date`,
  ADD COLUMN `cessation_reason` VARCHAR(190) NULL AFTER `cessation_date`,
  ADD COLUMN `voting_active` TINYINT NOT NULL DEFAULT 0 AFTER `cessation_reason`,
  ADD COLUMN `voting_passive` TINYINT NOT NULL DEFAULT 0 AFTER `voting_active`;

ALTER TABLE `#__decaromembership_transfers`
  ADD COLUMN `effective_at` DATE NULL AFTER `requested_at`;

CREATE TABLE IF NOT EXISTS `#__decaromembership_member_history` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_id` INT UNSIGNED NOT NULL,
  `event_type` VARCHAR(50) NOT NULL,
  `old_status` VARCHAR(50) NULL,
  `new_status` VARCHAR(50) NULL,
  `old_category_id` INT UNSIGNED NULL,
  `new_category_id` INT UNSIGNED NULL,
  `old_location_id` INT UNSIGNED NULL,
  `new_location_id` INT UNSIGNED NULL,
  `effective_date` DATE NULL,
  `source_entity_type` VARCHAR(50) NULL,
  `source_entity_id` BIGINT UNSIGNED NULL,
  `metadata_json` LONGTEXT NULL,
  `user_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `created` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_member_history_member` (`member_id`,`created`),
  KEY `idx_member_history_event` (`event_type`),
  KEY `idx_member_history_source` (`source_entity_type`,`source_entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
