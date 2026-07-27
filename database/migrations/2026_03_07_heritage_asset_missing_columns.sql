-- Migration: add the heritage_asset columns that 2026_03_08_enum_to_varchar
-- expects to MODIFY but that the ahgHeritageAccountingPlugin CREATE never
-- defined (depreciation_policy, derecognition_reason, revaluation_frequency,
-- valuation_method).
--
-- Why this exists: on a fresh install heritage_asset was created without these
-- four columns, so 2026_03_08_enum_to_varchar hit "Unknown column" on its
-- MODIFY and (previously) halted the whole migration run. This runs first
-- (2026_03_07 sorts before 2026_03_08) so the columns exist before the MODIFY.
--
-- Plain ADD COLUMN (no MariaDB "IF NOT EXISTS", which MySQL 8 rejects) - the
-- runner treats "duplicate column" (1060) as safe, so this is idempotent on
-- instances that already have the columns.
-- Definitions match the target state in 2026_03_08_enum_to_varchar.

ALTER TABLE `heritage_asset` ADD COLUMN `depreciation_policy` VARCHAR(76) DEFAULT 'not_depreciated' COMMENT 'not_depreciated, straight_line, reducing_balance, units_of_production';

ALTER TABLE `heritage_asset` ADD COLUMN `derecognition_reason` VARCHAR(60) DEFAULT NULL COMMENT 'disposal, destruction, loss, transfer, write_off, other';

ALTER TABLE `heritage_asset` ADD COLUMN `revaluation_frequency` VARCHAR(64) DEFAULT 'as_needed' COMMENT 'annual, triennial, quinquennial, as_needed, not_applicable';

ALTER TABLE `heritage_asset` ADD COLUMN `valuation_method` VARCHAR(51) DEFAULT NULL COMMENT 'market, cost, income, expert, insurance, other';
