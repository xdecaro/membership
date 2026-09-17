ALTER TABLE `#__decaromembership_members`
  ADD COLUMN `current_period_started_on` DATE NULL AFTER `first_registration_date`,
  ADD COLUMN `ended_on` DATE NULL AFTER `current_period_started_on`,
  ADD COLUMN `status_reason` VARCHAR(190) NULL AFTER `ended_on`,
  ADD COLUMN `rights_status` VARCHAR(30) NOT NULL DEFAULT 'normal' AFTER `status_reason`,
  ADD COLUMN `can_vote_override` TINYINT NULL AFTER `rights_status`,
  ADD COLUMN `can_candidate_override` TINYINT NULL AFTER `can_vote_override`,
  ADD COLUMN `seniority_credit_days` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `can_candidate_override`;

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
  `effective_on` DATE NULL,
  `reason` VARCHAR(190) NULL,
  `created` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_member_history_member` (`member_id`,`created`),
  KEY `idx_member_history_event` (`event_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
