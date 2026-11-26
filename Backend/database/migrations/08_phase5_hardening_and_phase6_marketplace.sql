-- ==============================================================================
-- EcoPulse MVP V3 - Database Migration: Phase 5 Hardening & Phase 6 Marketplace
-- Migration 08_phase5_hardening_and_phase6_marketplace.sql
-- ==============================================================================

-- 1. Ensure schema_migrations table exists
CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `migration_name` VARCHAR(255) NOT NULL UNIQUE,
  `batch` INT UNSIGNED NOT NULL DEFAULT 1,
  `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Add soft deletion columns to environmental_projects
ALTER TABLE `environmental_projects`
  ADD COLUMN `is_archived` TINYINT(1) NOT NULL DEFAULT 0 AFTER `created_by`,
  ADD COLUMN `archived_at` TIMESTAMP NULL AFTER `is_archived`,
  ADD INDEX `idx_ep_archived` (`is_archived`);

-- 3. Replace ON DELETE CASCADE with ON DELETE RESTRICT on audit tables to prevent data loss
ALTER TABLE `project_classification_history` DROP FOREIGN KEY `fk_pch_project`;
ALTER TABLE `project_classification_history` ADD CONSTRAINT `fk_pch_project_restrict` FOREIGN KEY (`project_id`) REFERENCES `environmental_projects` (`id`) ON DELETE RESTRICT;

ALTER TABLE `document_reviews` DROP FOREIGN KEY `fk_dr_project`;
ALTER TABLE `document_reviews` ADD CONSTRAINT `fk_dr_project_restrict` FOREIGN KEY (`project_id`) REFERENCES `environmental_projects` (`id`) ON DELETE RESTRICT;

ALTER TABLE `project_document_versions` DROP FOREIGN KEY `fk_pdv_project`;
ALTER TABLE `project_document_versions` ADD CONSTRAINT `fk_pdv_project_restrict` FOREIGN KEY (`project_id`) REFERENCES `environmental_projects` (`id`) ON DELETE RESTRICT;

ALTER TABLE `project_readiness_assessments` DROP FOREIGN KEY `fk_pra_project`;
ALTER TABLE `project_readiness_assessments` ADD CONSTRAINT `fk_pra_project_restrict` FOREIGN KEY (`project_id`) REFERENCES `environmental_projects` (`id`) ON DELETE RESTRICT;

-- 4. Create project_stage_history for immutable stage transition audit logs
CREATE TABLE IF NOT EXISTS `project_stage_history` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `project_id` BIGINT UNSIGNED NOT NULL,
  `organization_id` BIGINT UNSIGNED NOT NULL,
  `previous_stage` VARCHAR(50) NULL,
  `new_stage` VARCHAR(50) NOT NULL,
  `reason` TEXT NOT NULL,
  `changed_by` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_psh_project` (`project_id`, `created_at`),
  INDEX `idx_psh_org` (`organization_id`),
  CONSTRAINT `fk_psh_project` FOREIGN KEY (`project_id`) REFERENCES `environmental_projects` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_psh_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_psh_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Extend marketplace_listings for Phase 6
ALTER TABLE `marketplace_listings`
  ADD COLUMN `listing_code` VARCHAR(50) NULL AFTER `project_id`,
  ADD COLUMN `domain_profile_id` BIGINT UNSIGNED NULL AFTER `listing_code`,
  ADD COLUMN `location_country` VARCHAR(100) NULL AFTER `eligible_classification`,
  ADD COLUMN `location_region` VARCHAR(150) NULL AFTER `location_country`,
  ADD COLUMN `methodology` VARCHAR(255) NULL AFTER `location_region`,
  ADD COLUMN `methodology_version` VARCHAR(50) NULL AFTER `methodology`,
  ADD COLUMN `third_party_verifier` VARCHAR(255) NULL AFTER `methodology_version`,
  ADD COLUMN `registry_name` VARCHAR(150) NULL AFTER `third_party_verifier`,
  ADD COLUMN `registry_serial_or_id` VARCHAR(255) NULL AFTER `registry_name`,
  ADD COLUMN `unit_of_measurement` VARCHAR(50) NOT NULL DEFAULT 'tCO2e' AFTER `available_volume_tco2e`,
  ADD COLUMN `project_start_date` DATE NULL AFTER `unit_of_measurement`,
  ADD COLUMN `crediting_period_start` DATE NULL AFTER `project_start_date`,
  ADD COLUMN `crediting_period_end` DATE NULL AFTER `crediting_period_start`,
  ADD COLUMN `evidence_summary` TEXT NULL AFTER `crediting_period_end`,
  ADD COLUMN `rejection_reason` TEXT NULL AFTER `admin_review_notes`,
  ADD COLUMN `suspension_reason` TEXT NULL AFTER `rejection_reason`,
  ADD COLUMN `is_archived` TINYINT(1) NOT NULL DEFAULT 0 AFTER `suspension_reason`,
  ADD COLUMN `submitted_at` TIMESTAMP NULL AFTER `is_archived`,
  ADD COLUMN `reviewed_at` TIMESTAMP NULL AFTER `submitted_at`,
  ADD COLUMN `reviewed_by` BIGINT UNSIGNED NULL AFTER `reviewed_at`,
  ADD COLUMN `created_by` BIGINT UNSIGNED NULL AFTER `reviewed_by`,
  MODIFY COLUMN `status` ENUM('draft', 'pending_review', 'approved', 'published', 'rejected', 'suspended', 'archived') NOT NULL DEFAULT 'draft',
  ADD INDEX `idx_mkt_code` (`listing_code`),
  ADD INDEX `idx_mkt_archived` (`is_archived`);

-- 6. Create marketplace_listing_reviews (Immutable moderation audit trail)
CREATE TABLE IF NOT EXISTS `marketplace_listing_reviews` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `listing_id` BIGINT UNSIGNED NOT NULL,
  `organization_id` BIGINT UNSIGNED NOT NULL,
  `previous_status` VARCHAR(50) NULL,
  `new_status` VARCHAR(50) NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `reviewer_id` BIGINT UNSIGNED NOT NULL,
  `reason` TEXT NULL,
  `comments` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_mlr_listing` (`listing_id`, `created_at`),
  INDEX `idx_mlr_org` (`organization_id`),
  CONSTRAINT `fk_mlr_listing` FOREIGN KEY (`listing_id`) REFERENCES `marketplace_listings` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_mlr_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_mlr_reviewer` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Extend buyer_inquiries for Phase 6
ALTER TABLE `buyer_inquiries`
  ADD COLUMN `organization_id` BIGINT UNSIGNED NULL AFTER `listing_id`,
  ADD COLUMN `inquiry_reference` VARCHAR(50) NULL AFTER `id`,
  ADD COLUMN `inquiry_subject` VARCHAR(255) NULL AFTER `buyer_phone`,
  ADD COLUMN `inquiry_type` ENUM('general', 'credit_purchase', 'partnership', 'verification_inquiry') NOT NULL DEFAULT 'general' AFTER `inquiry_subject`,
  ADD COLUMN `ip_address` VARCHAR(45) NULL AFTER `status`,
  MODIFY COLUMN `status` ENUM('new', 'contacted', 'in_discussion', 'closed', 'rejected') NOT NULL DEFAULT 'new',
  ADD INDEX `idx_bi_ref` (`inquiry_reference`),
  ADD INDEX `idx_bi_org` (`organization_id`);

-- 8. Extend inquiry_messages
ALTER TABLE `inquiry_messages`
  ADD COLUMN `organization_id` BIGINT UNSIGNED NULL AFTER `inquiry_id`,
  ADD COLUMN `sender_name` VARCHAR(150) NULL AFTER `sender_type`,
  ADD INDEX `idx_im_org` (`organization_id`);
