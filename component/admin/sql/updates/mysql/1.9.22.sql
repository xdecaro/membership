ALTER TABLE `#__decaromembership_cards`
  ADD COLUMN `program` VARCHAR(30) NOT NULL DEFAULT 'standard' AFTER `card_number`,
  ADD COLUMN `season` VARCHAR(100) NULL AFTER `program`,
  ADD COLUMN `valid_from` DATE NULL AFTER `activated_at`,
  ADD KEY `idx_card_program_season` (`program`,`season`);
