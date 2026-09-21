-- Membership 1.9.15: modernize transfer workflow with Organizations integration.
ALTER TABLE `#__decaromembership_transfers`
    ADD COLUMN `from_organization_uuid` CHAR(36) NULL AFTER `member_id`,
    ADD COLUMN `to_organization_uuid` CHAR(36) NULL AFTER `from_organization_uuid`,
    ADD COLUMN `sticker_status` VARCHAR(50) NOT NULL DEFAULT 'unchecked' AFTER `card_position`,
    MODIFY COLUMN `to_location_id` INT UNSIGNED NULL,
    MODIFY COLUMN `arrears_amount` DECIMAL(12,2) NULL DEFAULT 0.00,
    ADD KEY `idx_transfer_from_org` (`from_organization_uuid`),
    ADD KEY `idx_transfer_to_org` (`to_organization_uuid`);
