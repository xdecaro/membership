ALTER TABLE `#__decaromembership_members`
  ADD COLUMN `organization_uuid` CHAR(36) NULL AFTER `status`,
  ADD KEY `idx_member_organization_uuid` (`organization_uuid`);

ALTER TABLE `#__decaromembership_member_history`
  ADD COLUMN `old_organization_uuid` CHAR(36) NULL AFTER `new_location_id`,
  ADD COLUMN `new_organization_uuid` CHAR(36) NULL AFTER `old_organization_uuid`;

-- Membership 1.7.0 keeps location_id for standalone/legacy use.
-- organization_uuid is optional and only validated when Organizations is available.
