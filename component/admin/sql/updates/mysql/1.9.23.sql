ALTER TABLE `#__decaromembership_cards`
  MODIFY `member_id` INT UNSIGNED NULL,
  MODIFY `card_number` VARCHAR(100) NULL,
  ADD COLUMN `person_uuid` CHAR(36) NULL AFTER `member_id`,
  ADD COLUMN `issuer_organization_uuid` CHAR(36) NULL AFTER `person_uuid`,
  ADD COLUMN `scope` VARCHAR(30) NOT NULL DEFAULT 'association' AFTER `issuer_organization_uuid`;

UPDATE `#__decaromembership_cards` AS c
INNER JOIN `#__decaromembership_members` AS m ON m.id = c.member_id
SET c.person_uuid = m.person_uuid
WHERE c.person_uuid IS NULL AND m.person_uuid IS NOT NULL AND m.person_uuid <> '';

UPDATE `#__decaromembership_cards`
SET `scope` = CASE WHEN `program` = 'dcl' THEN 'competition' ELSE 'association' END
WHERE `scope` IS NULL OR `scope` = '' OR (`program` = 'dcl' AND `scope` = 'association');

ALTER TABLE `#__decaromembership_cards`
  ADD KEY `idx_card_person` (`person_uuid`),
  ADD KEY `idx_card_issuer` (`issuer_organization_uuid`),
  ADD KEY `idx_card_scope` (`scope`);
