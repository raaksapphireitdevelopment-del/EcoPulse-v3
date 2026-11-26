-- EcoPulse MVP V3 - Phase 3 Database Migration
-- Emission Factors, Activity Collection, and Carbon Calculation Engine

USE `ecopulse_v3`;

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Step A: Expand validation_status ENUM to contain both legacy and new values
ALTER TABLE `emission_factors`
  MODIFY COLUMN `validation_status` ENUM('verified', 'provisional', 'draft', 'pending_review', 'validated', 'rejected', 'expired', 'demo') NOT NULL DEFAULT 'draft',
  ADD COLUMN IF NOT EXISTS `publishing_organization` VARCHAR(150) NULL AFTER `source`,
  ADD COLUMN IF NOT EXISTS `gwp_value` DECIMAL(10,4) NULL AFTER `gas_detail`,
  ADD COLUMN IF NOT EXISTS `gwp_source` VARCHAR(100) NULL AFTER `gwp_value`;

-- Migrate existing 'verified' factor records to 'validated'
UPDATE `emission_factors` SET `validation_status` = 'validated' WHERE `is_demo` = 0;
UPDATE `emission_factors` SET `validation_status` = 'demo' WHERE `is_demo` = 1;

-- Populate publishing_organization for existing factors
UPDATE `emission_factors` SET `publishing_organization` = `source` WHERE `publishing_organization` IS NULL;

-- Step B: Finalize validation_status ENUM with strict Phase 3 statuses
ALTER TABLE `emission_factors`
  MODIFY COLUMN `validation_status` ENUM('draft', 'pending_review', 'validated', 'rejected', 'expired', 'demo') NOT NULL DEFAULT 'draft';

-- 2. Update activity_categories with configuration fields
ALTER TABLE `activity_categories`
  ADD COLUMN IF NOT EXISTS `accepted_units` JSON NULL AFTER `standard_unit`,
  ADD COLUMN IF NOT EXISTS `form_fields` JSON NULL AFTER `accepted_units`,
  ADD COLUMN IF NOT EXISTS `accounting_rules` JSON NULL AFTER `form_fields`;

-- 3. Update activity_records with status workflow and rejection reasons
ALTER TABLE `activity_records`
  ADD COLUMN IF NOT EXISTS `status` ENUM('draft', 'submitted', 'validated', 'rejected') NOT NULL DEFAULT 'submitted' AFTER `supporting_document_url`,
  ADD COLUMN IF NOT EXISTS `rejection_reason` TEXT NULL AFTER `status`,
  ADD COLUMN IF NOT EXISTS `validated_by` BIGINT UNSIGNED NULL AFTER `rejection_reason`,
  ADD COLUMN IF NOT EXISTS `validated_at` TIMESTAMP NULL AFTER `validated_by`;

-- Add index & constraint if not exists
ALTER TABLE `activity_records`
  ADD INDEX IF NOT EXISTS `idx_act_status` (`status`);

-- 4. Update carbon_calculations for immutable versioned snapshots
-- Drop foreign key and unique index on activity_record_id
ALTER TABLE `carbon_calculations` DROP FOREIGN KEY `fk_calc_record`;
ALTER TABLE `carbon_calculations` DROP INDEX `activity_record_id`;

ALTER TABLE `carbon_calculations`
  ADD COLUMN IF NOT EXISTS `version` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `id`,
  ADD COLUMN IF NOT EXISTS `is_current` TINYINT(1) NOT NULL DEFAULT 1 AFTER `version`,
  ADD COLUMN IF NOT EXISTS `facility_id` BIGINT UNSIGNED NOT NULL AFTER `organization_id`,
  ADD COLUMN IF NOT EXISTS `reporting_period_id` BIGINT UNSIGNED NOT NULL AFTER `facility_id`,
  ADD COLUMN IF NOT EXISTS `activity_category_id` INT UNSIGNED NOT NULL AFTER `reporting_period_id`,
  ADD COLUMN IF NOT EXISTS `normalized_quantity` DECIMAL(16,4) NULL AFTER `activity_quantity_snapshot`,
  ADD COLUMN IF NOT EXISTS `normalized_unit` VARCHAR(50) NULL AFTER `normalized_quantity`,
  ADD COLUMN IF NOT EXISTS `conversion_factor` DECIMAL(16,8) NULL AFTER `normalized_unit`,
  ADD COLUMN IF NOT EXISTS `factor_source_snapshot` VARCHAR(150) NULL AFTER `factor_value_snapshot`,
  ADD COLUMN IF NOT EXISTS `factor_status_snapshot` VARCHAR(50) NULL AFTER `factor_source_snapshot`,
  ADD COLUMN IF NOT EXISTS `geography_snapshot` VARCHAR(100) NULL AFTER `factor_status_snapshot`,
  ADD COLUMN IF NOT EXISTS `gwp_applied` DECIMAL(10,4) NULL AFTER `geography_snapshot`,
  ADD COLUMN IF NOT EXISTS `gwp_source_snapshot` VARCHAR(100) NULL AFTER `gwp_applied`,
  MODIFY COLUMN `scope` ENUM('scope_1', 'scope_2', 'scope_3', 'avoided_emissions', 'unclassified') NOT NULL DEFAULT 'unclassified',
  ADD COLUMN IF NOT EXISTS `calculation_status` ENUM('validated', 'demo', 'provisional', 'unclassified', 'review_required') NOT NULL DEFAULT 'validated' AFTER `calculation_formula`,
  ADD COLUMN IF NOT EXISTS `recalculation_reason` VARCHAR(255) NULL AFTER `calculation_status`,
  ADD COLUMN IF NOT EXISTS `triggered_by` BIGINT UNSIGNED NULL AFTER `recalculation_reason`;

-- Add new indexes and re-add foreign key on activity_record_id
ALTER TABLE `carbon_calculations`
  ADD INDEX `idx_calc_act_record` (`activity_record_id`),
  ADD INDEX `idx_calc_act_current` (`activity_record_id`, `is_current`),
  ADD INDEX `idx_calc_facility` (`facility_id`),
  ADD INDEX `idx_calc_period` (`reporting_period_id`),
  ADD INDEX `idx_calc_category` (`activity_category_id`),
  ADD CONSTRAINT `fk_calc_record` FOREIGN KEY (`activity_record_id`) REFERENCES `activity_records` (`id`) ON DELETE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;
