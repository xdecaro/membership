CREATE TABLE IF NOT EXISTS `#__decaromembership_card_numbering_rules` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `issuer_organization_uuid` CHAR(36) NOT NULL,
  `scope` VARCHAR(30) NOT NULL DEFAULT 'association',
  `numbering_mode` VARCHAR(30) NOT NULL DEFAULT 'manual',
  `source` VARCHAR(100) NULL,
  `manual_edit` TINYINT NOT NULL DEFAULT 0,
  `sequence_padding` TINYINT UNSIGNED NOT NULL DEFAULT 7,
  `notes` TEXT NULL,
  `published` TINYINT NOT NULL DEFAULT 1,
  `created` DATETIME NULL,
  `created_by` INT UNSIGNED NOT NULL DEFAULT 0,
  `modified` DATETIME NULL,
  `modified_by` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_card_numbering_issuer_scope` (`issuer_organization_uuid`,`scope`),
  KEY `idx_card_numbering_mode` (`numbering_mode`),
  KEY `idx_card_numbering_published` (`published`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
