ALTER TABLE `#__decaromembership_members`
  ADD COLUMN `person_uuid` CHAR(36) NULL AFTER `id`,
  MODIFY `first_name` VARCHAR(190) NULL,
  MODIFY `last_name` VARCHAR(190) NULL,
  ADD UNIQUE KEY `uq_member_person_uuid` (`person_uuid`);
