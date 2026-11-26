-- ==============================================================================
-- EcoPulse MVP V3 - Database Migration: Phase 5
-- Environmental Projects, Evidence Management & Readiness Assessment
-- Migration 07_phase5_projects_evidence_readiness.sql
-- ==============================================================================

-- 1. Extend environmental_projects
ALTER TABLE `environmental_projects`
  ADD COLUMN `description` TEXT NULL AFTER `project_title`,
  ADD COLUMN `project_objectives` TEXT NULL AFTER `description`,
  ADD COLUMN `project_category` VARCHAR(100) NOT NULL DEFAULT 'energy_efficiency' AFTER `project_objectives`,
  ADD COLUMN `methodology_version` VARCHAR(50) NULL AFTER `methodology`,
  ADD COLUMN `baseline_scenario_description` TEXT NULL AFTER `methodology_version`,
  ADD COLUMN `expected_environmental_outcomes` TEXT NULL AFTER `baseline_scenario_description`,
  ADD COLUMN `location_country` VARCHAR(100) NOT NULL DEFAULT 'Global' AFTER `expected_environmental_outcomes`,
  ADD COLUMN `location_region` VARCHAR(150) NULL AFTER `location_country`,
  ADD COLUMN `project_owner_name` VARCHAR(150) NULL AFTER `location_region`,
  ADD COLUMN `responsible_team` VARCHAR(200) NULL AFTER `project_owner_name`,
  ADD COLUMN `project_start_date` DATE NULL AFTER `responsible_team`,
  ADD COLUMN `expected_completion_date` DATE NULL AFTER `project_start_date`,
  ADD COLUMN `readiness_stage` ENUM(
    'initial_assessment',
    'documentation_required',
    'under_technical_review',
    'methodology_assessment',
    'verification_preparation',
    'independently_verified',
    'issued'
  ) NOT NULL DEFAULT 'initial_assessment' AFTER `project_status`,
  ADD COLUMN `created_by` BIGINT UNSIGNED NULL AFTER `readiness_stage`,
  ADD CONSTRAINT `fk_projects_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- 2. Create project_classification_history (Immutable audit log)
CREATE TABLE IF NOT EXISTS `project_classification_history` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `project_id` BIGINT UNSIGNED NOT NULL,
  `organization_id` BIGINT UNSIGNED NOT NULL,
  `previous_classification` ENUM(
    'operational_reduction',
    'project_candidate',
    'verified_environmental_asset',
    'issued_carbon_credit'
  ) NULL,
  `new_classification` ENUM(
    'operational_reduction',
    'project_candidate',
    'verified_environmental_asset',
    'issued_carbon_credit'
  ) NOT NULL,
  `reason` TEXT NOT NULL,
  `supporting_evidence_notes` TEXT NULL,
  `changed_by` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_pch_project` (`project_id`, `created_at`),
  INDEX `idx_pch_org` (`organization_id`),
  CONSTRAINT `fk_pch_project` FOREIGN KEY (`project_id`) REFERENCES `environmental_projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pch_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pch_changed_by` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Extend project_documents
ALTER TABLE `project_documents`
  ADD COLUMN `organization_id` BIGINT UNSIGNED NOT NULL AFTER `project_id`,
  ADD COLUMN `storage_identifier` VARCHAR(255) NOT NULL AFTER `document_type`,
  ADD COLUMN `original_filename` VARCHAR(255) NOT NULL AFTER `file_title`,
  ADD COLUMN `sha256_hash` CHAR(64) NOT NULL AFTER `mime_type`,
  ADD COLUMN `current_version` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `sha256_hash`,
  ADD COLUMN `review_status` ENUM(
    'pending',
    'under_review',
    'accepted',
    'rejected',
    'requires_revision'
  ) NOT NULL DEFAULT 'pending' AFTER `current_version`,
  ADD COLUMN `review_comments` TEXT NULL AFTER `review_status`,
  ADD COLUMN `reviewed_by` BIGINT UNSIGNED NULL AFTER `review_comments`,
  ADD COLUMN `reviewed_at` TIMESTAMP NULL AFTER `reviewed_by`,
  ADD COLUMN `description` VARCHAR(255) NULL AFTER `reviewed_at`,
  ADD COLUMN `is_archived` TINYINT(1) NOT NULL DEFAULT 0 AFTER `description`,
  ADD UNIQUE INDEX `idx_docs_storage_id` (`storage_identifier`),
  ADD INDEX `idx_docs_org` (`organization_id`),
  ADD INDEX `idx_docs_review_status` (`review_status`),
  ADD CONSTRAINT `fk_docs_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_docs_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- 4. Create project_document_versions (Historical version retention)
CREATE TABLE IF NOT EXISTS `project_document_versions` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `document_id` BIGINT UNSIGNED NOT NULL,
  `project_id` BIGINT UNSIGNED NOT NULL,
  `organization_id` BIGINT UNSIGNED NOT NULL,
  `version_number` INT UNSIGNED NOT NULL,
  `storage_identifier` VARCHAR(255) NOT NULL,
  `original_filename` VARCHAR(255) NOT NULL,
  `file_size_kb` INT NOT NULL,
  `mime_type` VARCHAR(100) NOT NULL,
  `sha256_hash` CHAR(64) NOT NULL,
  `change_summary` VARCHAR(255) NULL,
  `uploaded_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_pdv_doc` (`document_id`, `version_number`),
  INDEX `idx_pdv_project` (`project_id`),
  INDEX `idx_pdv_org` (`organization_id`),
  CONSTRAINT `fk_pdv_doc` FOREIGN KEY (`document_id`) REFERENCES `project_documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pdv_project` FOREIGN KEY (`project_id`) REFERENCES `environmental_projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pdv_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pdv_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Create document_reviews (Immutable review decisions log)
CREATE TABLE IF NOT EXISTS `document_reviews` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `document_id` BIGINT UNSIGNED NOT NULL,
  `document_version` INT UNSIGNED NOT NULL,
  `project_id` BIGINT UNSIGNED NOT NULL,
  `organization_id` BIGINT UNSIGNED NOT NULL,
  `reviewer_id` BIGINT UNSIGNED NOT NULL,
  `review_status` ENUM(
    'pending',
    'under_review',
    'accepted',
    'rejected',
    'requires_revision'
  ) NOT NULL,
  `review_comments` TEXT NOT NULL,
  `rejection_or_revision_reason` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_dr_doc` (`document_id`, `created_at`),
  INDEX `idx_dr_project` (`project_id`),
  INDEX `idx_dr_org` (`organization_id`),
  CONSTRAINT `fk_dr_doc` FOREIGN KEY (`document_id`) REFERENCES `project_documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dr_project` FOREIGN KEY (`project_id`) REFERENCES `environmental_projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dr_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dr_reviewer` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Upgrade project_readiness_assessments (Preserve history and completeness state)
ALTER TABLE `project_readiness_assessments`
  DROP FOREIGN KEY `fk_readiness_project`,
  DROP INDEX `project_id`,
  ADD COLUMN `organization_id` BIGINT UNSIGNED NOT NULL AFTER `project_id`,
  ADD COLUMN `assessment_version` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `organization_id`,
  ADD COLUMN `is_current` TINYINT(1) NOT NULL DEFAULT 1 AFTER `assessment_version`,
  ADD COLUMN `is_complete` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_current`,
  ADD COLUMN `completeness_status` ENUM(
    'complete',
    'missing_mandatory_data',
    'unsupported_methodology'
  ) NOT NULL DEFAULT 'missing_mandatory_data' AFTER `is_complete`,
  ADD COLUMN `missing_required_fields` JSON NULL AFTER `completeness_status`,
  ADD COLUMN `weights_used` JSON NULL AFTER `missing_required_fields`,
  ADD COLUMN `dimension_breakdown` JSON NULL AFTER `weights_used`,
  MODIFY COLUMN `readiness_stage` ENUM(
    'initial_assessment',
    'documentation_required',
    'under_technical_review',
    'methodology_assessment',
    'verification_preparation',
    'independently_verified',
    'issued'
  ) NOT NULL DEFAULT 'initial_assessment',
  ADD INDEX `idx_pra_project` (`project_id`, `is_current`),
  ADD INDEX `idx_pra_org` (`organization_id`),
  ADD CONSTRAINT `fk_pra_project` FOREIGN KEY (`project_id`) REFERENCES `environmental_projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pra_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE;

-- 7. Create readiness_dimension_scores (Line-item dimension scores & deduction reasons)
CREATE TABLE IF NOT EXISTS `readiness_dimension_scores` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `assessment_id` BIGINT UNSIGNED NOT NULL,
  `dimension_key` ENUM(
    'additionality',
    'baseline_rigor',
    'monitoring',
    'permanence',
    'verification_readiness'
  ) NOT NULL,
  `score` TINYINT UNSIGNED NOT NULL,
  `weight_percentage` DECIMAL(5, 2) NOT NULL,
  `weighted_score` DECIMAL(5, 2) NOT NULL,
  `deduction_reasons` JSON NULL,
  `supporting_evidence_refs` JSON NULL,
  `missing_evidence` JSON NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE INDEX `idx_rds_assess_dim` (`assessment_id`, `dimension_key`),
  CONSTRAINT `fk_rds_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `project_readiness_assessments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
