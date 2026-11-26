USE `ecopulse_v3`;
SET FOREIGN_KEY_CHECKS = 0;


ALTER TABLE `carbon_calculations`
  ADD COLUMN `version` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `id`,
  ADD COLUMN `is_current` TINYINT(1) NOT NULL DEFAULT 1 AFTER `version`,
  ADD COLUMN `facility_id` BIGINT UNSIGNED NOT NULL AFTER `organization_id`,
  ADD COLUMN `reporting_period_id` BIGINT UNSIGNED NOT NULL AFTER `facility_id`,
  ADD COLUMN `activity_category_id` INT UNSIGNED NOT NULL AFTER `reporting_period_id`,
  ADD COLUMN `normalized_quantity` DECIMAL(16,4) NULL AFTER `activity_quantity_snapshot`,
  ADD COLUMN `normalized_unit` VARCHAR(50) NULL AFTER `normalized_quantity`,
  ADD COLUMN `conversion_factor` DECIMAL(16,8) NULL AFTER `normalized_unit`,
  ADD COLUMN `factor_source_snapshot` VARCHAR(150) NULL AFTER `factor_value_snapshot`,
  ADD COLUMN `factor_status_snapshot` VARCHAR(50) NULL AFTER `factor_source_snapshot`,
  ADD COLUMN `geography_snapshot` VARCHAR(100) NULL AFTER `factor_status_snapshot`,
  ADD COLUMN `gwp_applied` DECIMAL(10,4) NULL AFTER `geography_snapshot`,
  ADD COLUMN `gwp_source_snapshot` VARCHAR(100) NULL AFTER `gwp_applied`,
  MODIFY COLUMN `scope` ENUM('scope_1', 'scope_2', 'scope_3', 'avoided_emissions', 'unclassified') NOT NULL DEFAULT 'unclassified',
  ADD COLUMN `calculation_status` ENUM('validated', 'demo', 'provisional', 'unclassified', 'review_required') NOT NULL DEFAULT 'validated' AFTER `calculation_formula`,
  ADD COLUMN `recalculation_reason` VARCHAR(255) NULL AFTER `calculation_status`,
  ADD COLUMN `triggered_by` BIGINT UNSIGNED NULL AFTER `recalculation_reason`;

ALTER TABLE `carbon_calculations`
  ADD INDEX `idx_calc_act_record` (`activity_record_id`),
  ADD INDEX `idx_calc_act_current` (`activity_record_id`, `is_current`),
  ADD INDEX `idx_calc_facility` (`facility_id`),
  ADD INDEX `idx_calc_period` (`reporting_period_id`),
  ADD INDEX `idx_calc_category` (`activity_category_id`),
  ADD CONSTRAINT `fk_calc_record` FOREIGN KEY (`activity_record_id`) REFERENCES `activity_records` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_calc_fac` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_calc_per` FOREIGN KEY (`reporting_period_id`) REFERENCES `reporting_periods` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_calc_cat` FOREIGN KEY (`activity_category_id`) REFERENCES `activity_categories` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_calc_trig` FOREIGN KEY (`triggered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS = 1;
