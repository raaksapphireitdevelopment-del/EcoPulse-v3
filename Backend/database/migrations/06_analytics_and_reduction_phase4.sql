-- ====================================================================
-- ECOPULSE MVP V3 - MIGRATION 06: PHASE 4 ANALYTICS & REDUCTION SYSTEM
-- ====================================================================

USE `ecopulse_v3`;

-- 1. Modify activity_category_id to INT UNSIGNED in reduction_initiatives to match activity_categories(id)
ALTER TABLE `reduction_initiatives`
    MODIFY COLUMN `activity_category_id` INT UNSIGNED NULL;

-- Add Foreign Key Constraints to reduction_initiatives if not already present
SET @drop_fk = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_NAME = 'fk_ri_activity_cat' AND TABLE_NAME = 'reduction_initiatives' AND TABLE_SCHEMA = 'ecopulse_v3') > 0,
    'SELECT 1',
    'ALTER TABLE `reduction_initiatives` ADD CONSTRAINT `fk_ri_activity_cat` FOREIGN KEY (`activity_category_id`) REFERENCES `activity_categories` (`id`) ON DELETE SET NULL'
));
PREPARE stmt FROM @drop_fk;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_fk2 = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_NAME = 'fk_ri_owner' AND TABLE_NAME = 'reduction_initiatives' AND TABLE_SCHEMA = 'ecopulse_v3') > 0,
    'SELECT 1',
    'ALTER TABLE `reduction_initiatives` ADD CONSTRAINT `fk_ri_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL'
));
PREPARE stmt FROM @add_fk2;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_fk3 = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_NAME = 'fk_ri_baseline_rp' AND TABLE_NAME = 'reduction_initiatives' AND TABLE_SCHEMA = 'ecopulse_v3') > 0,
    'SELECT 1',
    'ALTER TABLE `reduction_initiatives` ADD CONSTRAINT `fk_ri_baseline_rp` FOREIGN KEY (`baseline_reporting_period_id`) REFERENCES `reporting_periods` (`id`) ON DELETE SET NULL'
));
PREPARE stmt FROM @add_fk3;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_fk4 = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_NAME = 'fk_ri_target_rp' AND TABLE_NAME = 'reduction_initiatives' AND TABLE_SCHEMA = 'ecopulse_v3') > 0,
    'SELECT 1',
    'ALTER TABLE `reduction_initiatives` ADD CONSTRAINT `fk_ri_target_rp` FOREIGN KEY (`target_reporting_period_id`) REFERENCES `reporting_periods` (`id`) ON DELETE SET NULL'
));
PREPARE stmt FROM @add_fk4;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_fk5 = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_NAME = 'fk_ri_created_by' AND TABLE_NAME = 'reduction_initiatives' AND TABLE_SCHEMA = 'ecopulse_v3') > 0,
    'SELECT 1',
    'ALTER TABLE `reduction_initiatives` ADD CONSTRAINT `fk_ri_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL'
));
PREPARE stmt FROM @add_fk5;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Create reduction_initiative_updates table
CREATE TABLE IF NOT EXISTS `reduction_initiative_updates` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `initiative_id` BIGINT UNSIGNED NOT NULL,
    `organization_id` BIGINT UNSIGNED NOT NULL,
    `update_date` DATE NOT NULL,
    `description` TEXT NOT NULL,
    `progress_percentage` DECIMAL(5,2) NOT NULL,
    `status` ENUM('proposed', 'approved', 'in_progress', 'on_hold', 'completed', 'cancelled') NOT NULL,
    `measured_reduction_tco2e` DECIMAL(14,4) NULL,
    `evidence_notes` TEXT NULL,
    `supporting_document_url` VARCHAR(255) NULL,
    `updated_by` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_riu_init` (`initiative_id`),
    INDEX `idx_riu_org` (`organization_id`),
    INDEX `idx_riu_date` (`update_date`),
    CONSTRAINT `fk_riu_init` FOREIGN KEY (`initiative_id`) REFERENCES `reduction_initiatives` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_riu_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_riu_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create reduction_targets table
CREATE TABLE IF NOT EXISTS `reduction_targets` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organization_id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `target_level` ENUM('organization', 'domain', 'facility', 'scope', 'category') NOT NULL DEFAULT 'organization',
    `domain_profile_id` BIGINT UNSIGNED NULL,
    `facility_id` BIGINT UNSIGNED NULL,
    `scope` ENUM('scope_1', 'scope_2', 'scope_3', 'all') NOT NULL DEFAULT 'all',
    `activity_category_id` INT UNSIGNED NULL,
    `baseline_reporting_period_id` BIGINT UNSIGNED NULL,
    `baseline_year` SMALLINT NULL,
    `target_reporting_period_id` BIGINT UNSIGNED NULL,
    `target_year` SMALLINT NOT NULL,
    `baseline_emissions_tco2e` DECIMAL(14,4) NOT NULL,
    `target_emissions_tco2e` DECIMAL(14,4) NOT NULL,
    `target_reduction_percentage` DECIMAL(5,2) NOT NULL,
    `current_emissions_tco2e` DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `status` ENUM('active', 'achieved', 'behind_schedule', 'missed', 'cancelled') NOT NULL DEFAULT 'active',
    `notes` TEXT NULL,
    `created_by` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_rt_org` (`organization_id`),
    INDEX `idx_rt_level` (`target_level`),
    INDEX `idx_rt_status` (`status`),
    CONSTRAINT `fk_rt_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rt_dp` FOREIGN KEY (`domain_profile_id`) REFERENCES `domain_profiles` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_rt_fac` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_rt_cat` FOREIGN KEY (`activity_category_id`) REFERENCES `activity_categories` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_rt_brp` FOREIGN KEY (`baseline_reporting_period_id`) REFERENCES `reporting_periods` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_rt_trp` FOREIGN KEY (`target_reporting_period_id`) REFERENCES `reporting_periods` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_rt_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Create reduction_opportunities table
CREATE TABLE IF NOT EXISTS `reduction_opportunities` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organization_id` BIGINT UNSIGNED NOT NULL,
    `domain_profile_id` BIGINT UNSIGNED NULL,
    `activity_category_id` INT UNSIGNED NULL,
    `facility_id` BIGINT UNSIGNED NULL,
    `title` VARCHAR(255) NOT NULL,
    `domain_code` VARCHAR(50) NOT NULL,
    `supporting_data` JSON NULL,
    `reason_for_recommendation` TEXT NOT NULL,
    `suggested_action` TEXT NOT NULL,
    `potential_reduction_tco2e` DECIMAL(14,4) NULL,
    `estimation_assumptions` TEXT NULL,
    `priority` ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'medium',
    `status` ENUM('identified', 'evaluating', 'converted', 'dismissed') NOT NULL DEFAULT 'identified',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_ro_org` (`organization_id`),
    INDEX `idx_ro_domain` (`domain_code`),
    INDEX `idx_ro_priority` (`priority`),
    INDEX `idx_ro_status` (`status`),
    CONSTRAINT `fk_ro_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ro_dp` FOREIGN KEY (`domain_profile_id`) REFERENCES `domain_profiles` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_ro_cat` FOREIGN KEY (`activity_category_id`) REFERENCES `activity_categories` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_ro_fac` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
