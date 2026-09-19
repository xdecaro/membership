ALTER TABLE `#__decaromembership_categories`
  ADD COLUMN `code` VARCHAR(100) NULL AFTER `name`,
  ADD UNIQUE KEY `uq_category_code` (`code`);

-- Membership 1.9.0 adds category identity and lifecycle defaults.
-- Existing member/category data is preserved unchanged.
