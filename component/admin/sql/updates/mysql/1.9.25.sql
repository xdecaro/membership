CREATE TABLE IF NOT EXISTS `#__decaromembership_card_sequences` (
  `series_key` CHAR(64) NOT NULL,
  `prefix` VARCHAR(190) NOT NULL,
  `last_number` INT UNSIGNED NOT NULL DEFAULT 0,
  `created` DATETIME NULL,
  `modified` DATETIME NULL,
  PRIMARY KEY (`series_key`),
  KEY `idx_card_sequence_prefix` (`prefix`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
