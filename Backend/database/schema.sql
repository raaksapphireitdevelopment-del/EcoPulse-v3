-- EcoPulse MVP V3 Database Schema
-- Target: MySQL 8.0+ / MariaDB 10.4+
-- Character Set: utf8mb4 / Collation: utf8mb4_unicode_ci

CREATE DATABASE IF NOT EXISTS `ecopulse_v3` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ecopulse_v3`;

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Users table
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(191) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `system_role` ENUM('super_admin', 'user', 'buyer') NOT NULL DEFAULT 'user',
  `avatar_url` VARCHAR(255) NULL,
  `phone` VARCHAR(50) NULL,
  `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
  `last_login_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_users_email` (`email`),
  INDEX `idx_users_system_role` (`system_role`),
  INDEX `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Organizations table
DROP TABLE IF EXISTS `organizations`;
CREATE TABLE `organizations` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(200) NOT NULL,
  `registration_number` VARCHAR(100) NULL UNIQUE,
  `industry_primary` VARCHAR(100) NOT NULL,
  `country` VARCHAR(100) NOT NULL,
  `state_region` VARCHAR(100) NULL,
  `city` VARCHAR(100) NOT NULL,
  `address` TEXT NULL,
  `website` VARCHAR(255) NULL,
  `logo_url` VARCHAR(255) NULL,
  `status` ENUM('pending_verification', 'active', 'suspended') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_organizations_status` (`status`),
  INDEX `idx_organizations_country` (`country`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Organization Members table
DROP TABLE IF EXISTS `organization_members`;
CREATE TABLE `organization_members` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `organization_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `org_role` ENUM('organization_owner', 'organization_admin', 'organization_member') NOT NULL DEFAULT 'organization_member',
  `status` ENUM('active', 'invited', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_org_user` (`organization_id`, `user_id`),
  CONSTRAINT `fk_org_members_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_org_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Supported Domains Reference Table (9 domains)
DROP TABLE IF EXISTS `domains`;
CREATE TABLE `domains` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT NOT NULL,
  `icon` VARCHAR(50) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Domain Profiles (Organization active domains)
DROP TABLE IF EXISTS `domain_profiles`;
CREATE TABLE `domain_profiles` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `organization_id` BIGINT UNSIGNED NOT NULL,
  `domain_id` INT UNSIGNED NOT NULL,
  `profile_name` VARCHAR(150) NOT NULL,
  `base_year` SMALLINT NOT NULL DEFAULT 2024,
  `config_settings` JSON NULL,
  `status` ENUM('active', 'archived') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_org_domain` (`organization_id`, `domain_id`),
  CONSTRAINT `fk_domain_profiles_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_domain_profiles_domain` FOREIGN KEY (`domain_id`) REFERENCES `domains` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Facilities
DROP TABLE IF EXISTS `facilities`;
CREATE TABLE `facilities` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `organization_id` BIGINT UNSIGNED NOT NULL,
  `domain_profile_id` BIGINT UNSIGNED NULL,
  `name` VARCHAR(200) NOT NULL,
  `facility_code` VARCHAR(50) NULL,
  `facility_type` VARCHAR(100) NOT NULL,
  `country` VARCHAR(100) NOT NULL,
  `state_region` VARCHAR(100) NULL,
  `city` VARCHAR(100) NOT NULL,
  `postal_code` VARCHAR(20) NULL,
  `timezone` VARCHAR(50) NOT NULL DEFAULT 'UTC',
  `address` TEXT NULL,
  `gross_area_sqm` DECIMAL(12, 2) NULL,
  `operational_control` TINYINT(1) NOT NULL DEFAULT 1,
  `metadata` JSON NULL,
  `status` ENUM('active', 'decommissioned') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_facilities_org` (`organization_id`),
  CONSTRAINT `fk_facilities_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_facilities_domain_profile` FOREIGN KEY (`domain_profile_id`) REFERENCES `domain_profiles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Reporting Periods
DROP TABLE IF EXISTS `reporting_periods`;
CREATE TABLE `reporting_periods` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `organization_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `period_type` ENUM('annual', 'quarterly', 'monthly', 'custom') NOT NULL DEFAULT 'annual',
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `is_closed` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('draft', 'active', 'closed') NOT NULL DEFAULT 'active',
  `closed_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_periods_org` (`organization_id`),
  CONSTRAINT `fk_periods_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Activity Categories
DROP TABLE IF EXISTS `activity_categories`;
CREATE TABLE `activity_categories` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `domain_id` INT UNSIGNED NOT NULL,
  `code` VARCHAR(50) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `scope` ENUM('scope_1', 'scope_2', 'scope_3') NOT NULL,
  `standard_unit` VARCHAR(50) NOT NULL,
  `description` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_domain_category_code` (`domain_id`, `code`),
  CONSTRAINT `fk_act_cat_domain` FOREIGN KEY (`domain_id`) REFERENCES `domains` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Emission Factors Catalog
DROP TABLE IF EXISTS `emission_factors`;
CREATE TABLE `emission_factors` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `activity_category_id` INT UNSIGNED NOT NULL,
  `factor_name` VARCHAR(200) NOT NULL,
  `factor_value` DECIMAL(16, 8) NOT NULL,
  `activity_unit` VARCHAR(50) NOT NULL,
  `co2e_unit` VARCHAR(20) NOT NULL DEFAULT 'kgCO2e',
  `scope` ENUM('scope_1', 'scope_2', 'scope_3') NOT NULL,
  `gas_detail` VARCHAR(50) NOT NULL DEFAULT 'CO2e',
  `geography` VARCHAR(100) NOT NULL DEFAULT 'Global',
  `source` VARCHAR(150) NOT NULL,
  `source_reference` VARCHAR(255) NULL,
  `version` VARCHAR(50) NOT NULL,
  `effective_date` DATE NOT NULL,
  `expiry_date` DATE NULL,
  `validation_status` ENUM('verified', 'provisional', 'demo') NOT NULL DEFAULT 'verified',
  `is_demo` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_factors_cat` (`activity_category_id`),
  INDEX `idx_factors_scope` (`scope`),
  CONSTRAINT `fk_factors_category` FOREIGN KEY (`activity_category_id`) REFERENCES `activity_categories` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Activity Records
DROP TABLE IF EXISTS `activity_records`;
CREATE TABLE `activity_records` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `organization_id` BIGINT UNSIGNED NOT NULL,
  `domain_profile_id` BIGINT UNSIGNED NOT NULL,
  `facility_id` BIGINT UNSIGNED NOT NULL,
  `reporting_period_id` BIGINT UNSIGNED NOT NULL,
  `activity_category_id` INT UNSIGNED NOT NULL,
  `activity_date` DATE NOT NULL,
  `quantity` DECIMAL(16, 4) NOT NULL,
  `unit` VARCHAR(50) NOT NULL,
  `description` TEXT NULL,
  `data_source_type` ENUM('meter_read', 'invoice', 'estimate', 'sensor', 'manual') NOT NULL DEFAULT 'invoice',
  `supporting_document_url` VARCHAR(255) NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_act_org` (`organization_id`),
  INDEX `idx_act_facility` (`facility_id`),
  INDEX `idx_act_period` (`reporting_period_id`),
  CONSTRAINT `fk_act_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_act_domain_profile` FOREIGN KEY (`domain_profile_id`) REFERENCES `domain_profiles` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_act_facility` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_act_period` FOREIGN KEY (`reporting_period_id`) REFERENCES `reporting_periods` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_act_category` FOREIGN KEY (`activity_category_id`) REFERENCES `activity_categories` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_act_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Carbon Calculations (Immutable history & audit)
DROP TABLE IF EXISTS `carbon_calculations`;
CREATE TABLE `carbon_calculations` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `activity_record_id` BIGINT UNSIGNED NOT NULL UNIQUE,
  `organization_id` BIGINT UNSIGNED NOT NULL,
  `emission_factor_id` BIGINT UNSIGNED NOT NULL,
  `factor_version_snapshot` VARCHAR(50) NOT NULL,
  `factor_value_snapshot` DECIMAL(16, 8) NOT NULL,
  `activity_quantity_snapshot` DECIMAL(16, 4) NOT NULL,
  `kg_co2e` DECIMAL(16, 6) NOT NULL,
  `tonnes_co2e` DECIMAL(16, 6) NOT NULL,
  `scope` ENUM('scope_1', 'scope_2', 'scope_3') NOT NULL,
  `calculation_formula` VARCHAR(255) NOT NULL,
  `calculated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_calc_org` (`organization_id`),
  INDEX `idx_calc_scope` (`scope`),
  CONSTRAINT `fk_calc_record` FOREIGN KEY (`activity_record_id`) REFERENCES `activity_records` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_calc_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_calc_factor` FOREIGN KEY (`emission_factor_id`) REFERENCES `emission_factors` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Reduction Initiatives
DROP TABLE IF EXISTS `reduction_initiatives`;
CREATE TABLE `reduction_initiatives` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `organization_id` BIGINT UNSIGNED NOT NULL,
  `domain_profile_id` BIGINT UNSIGNED NOT NULL,
  `facility_id` BIGINT UNSIGNED NULL,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT NOT NULL,
  `target_scope` ENUM('scope_1', 'scope_2', 'scope_3', 'all') NOT NULL DEFAULT 'all',
  `baseline_year` SMALLINT NOT NULL,
  `target_completion_year` SMALLINT NOT NULL,
  `estimated_annual_tco2e_reduction` DECIMAL(12, 4) NOT NULL,
  `estimated_capex` DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
  `status` ENUM('planned', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'planned',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_reduct_org` (`organization_id`),
  CONSTRAINT `fk_reduct_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reduct_domain_profile` FOREIGN KEY (`domain_profile_id`) REFERENCES `domain_profiles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reduct_facility` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Environmental Projects (4-Tier Classification)
DROP TABLE IF EXISTS `environmental_projects`;
CREATE TABLE `environmental_projects` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `organization_id` BIGINT UNSIGNED NOT NULL,
  `domain_profile_id` BIGINT UNSIGNED NOT NULL,
  `facility_id` BIGINT UNSIGNED NULL,
  `project_title` VARCHAR(255) NOT NULL,
  `project_code` VARCHAR(50) NULL UNIQUE,
  `classification` ENUM(
    'operational_reduction',
    'project_candidate',
    'verified_environmental_asset',
    'issued_carbon_credit'
  ) NOT NULL DEFAULT 'operational_reduction',
  `methodology` VARCHAR(150) NULL,
  `crediting_period_start` DATE NULL,
  `crediting_period_end` DATE NULL,
  `estimated_annual_mitigation_tco2e` DECIMAL(14, 4) NOT NULL,
  `third_party_verifier` VARCHAR(200) NULL,
  `registry_name` VARCHAR(150) NULL,
  `registry_serial_or_id` VARCHAR(150) NULL,
  `project_status` ENUM('concept', 'feasibility', 'validated', 'monitored', 'verified', 'active') NOT NULL DEFAULT 'concept',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_projects_org` (`organization_id`),
  INDEX `idx_projects_classification` (`classification`),
  CONSTRAINT `fk_projects_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projects_domain_profile` FOREIGN KEY (`domain_profile_id`) REFERENCES `domain_profiles` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_projects_facility` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Project Documents (Evidence, PDD, Verification Reports)
DROP TABLE IF EXISTS `project_documents`;
CREATE TABLE `project_documents` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `project_id` BIGINT UNSIGNED NOT NULL,
  `document_type` ENUM('pdd', 'validation_report', 'verification_report', 'issuance_record', 'monitoring_report', 'other') NOT NULL,
  `file_title` VARCHAR(200) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `file_size_kb` INT NOT NULL,
  `mime_type` VARCHAR(100) NOT NULL,
  `uploaded_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_docs_project` (`project_id`),
  CONSTRAINT `fk_docs_project` FOREIGN KEY (`project_id`) REFERENCES `environmental_projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_docs_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. Project Readiness Assessments
DROP TABLE IF EXISTS `project_readiness_assessments`;
CREATE TABLE `project_readiness_assessments` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `project_id` BIGINT UNSIGNED NOT NULL UNIQUE,
  `additionality_score` TINYINT UNSIGNED NOT NULL,
  `baseline_rigor_score` TINYINT UNSIGNED NOT NULL,
  `monitoring_readiness_score` TINYINT UNSIGNED NOT NULL,
  `permanence_risk_score` TINYINT UNSIGNED NOT NULL,
  `verification_readiness_score` TINYINT UNSIGNED NOT NULL,
  `overall_readiness_percentage` DECIMAL(5, 2) NOT NULL,
  `readiness_stage` ENUM('not_ready', 'early_stage', 'assessment_ready', 'credit_candidate') NOT NULL,
  `gap_analysis_summary` TEXT NOT NULL,
  `recommendations` TEXT NOT NULL,
  `assessed_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_readiness_project` FOREIGN KEY (`project_id`) REFERENCES `environmental_projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_readiness_assessed_by` FOREIGN KEY (`assessed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. Marketplace Listings
DROP TABLE IF EXISTS `marketplace_listings`;
CREATE TABLE `marketplace_listings` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `organization_id` BIGINT UNSIGNED NOT NULL,
  `project_id` BIGINT UNSIGNED NOT NULL,
  `listing_title` VARCHAR(255) NOT NULL,
  `headline` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `eligible_classification` ENUM('verified_environmental_asset', 'issued_carbon_credit') NOT NULL,
  `vintage_year` SMALLINT NOT NULL,
  `total_volume_tco2e` DECIMAL(14, 4) NOT NULL,
  `available_volume_tco2e` DECIMAL(14, 4) NOT NULL,
  `indicative_price_per_tco2e` DECIMAL(10, 2) NOT NULL,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
  `status` ENUM('draft', 'submitted_for_review', 'published', 'rejected', 'suspended', 'closed') NOT NULL DEFAULT 'draft',
  `admin_review_notes` TEXT NULL,
  `published_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_listings_org` (`organization_id`),
  INDEX `idx_listings_status` (`status`),
  CONSTRAINT `fk_listings_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_listings_project` FOREIGN KEY (`project_id`) REFERENCES `environmental_projects` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. Buyer Inquiries
DROP TABLE IF EXISTS `buyer_inquiries`;
CREATE TABLE `buyer_inquiries` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `listing_id` BIGINT UNSIGNED NOT NULL,
  `buyer_user_id` BIGINT UNSIGNED NOT NULL,
  `buyer_name` VARCHAR(150) NOT NULL,
  `buyer_company` VARCHAR(150) NOT NULL,
  `buyer_email` VARCHAR(191) NOT NULL,
  `buyer_phone` VARCHAR(50) NULL,
  `volume_requested_tco2e` DECIMAL(14, 4) NOT NULL,
  `target_price_per_tco2e` DECIMAL(10, 2) NULL,
  `intended_use` ENUM('esg_disclosure', 'offset_claim', 'investment', 'internal_compliance') NOT NULL DEFAULT 'esg_disclosure',
  `initial_message` TEXT NOT NULL,
  `status` ENUM('new', 'under_review', 'in_discussion', 'proposal_sent', 'closed_won', 'closed_lost', 'archived') NOT NULL DEFAULT 'new',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_inquiries_listing` (`listing_id`),
  INDEX `idx_inquiries_buyer` (`buyer_user_id`),
  CONSTRAINT `fk_inquiries_listing` FOREIGN KEY (`listing_id`) REFERENCES `marketplace_listings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inquiries_buyer` FOREIGN KEY (`buyer_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 18. Inquiry Messages (Negotiation & communication thread)
DROP TABLE IF EXISTS `inquiry_messages`;
CREATE TABLE `inquiry_messages` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `inquiry_id` BIGINT UNSIGNED NOT NULL,
  `sender_user_id` BIGINT UNSIGNED NOT NULL,
  `sender_type` ENUM('buyer', 'organization', 'super_admin') NOT NULL,
  `message` TEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_messages_inquiry` (`inquiry_id`),
  CONSTRAINT `fk_msg_inquiry` FOREIGN KEY (`inquiry_id`) REFERENCES `buyer_inquiries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_msg_sender` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 19. Generated Reports
DROP TABLE IF EXISTS `generated_reports`;
CREATE TABLE `generated_reports` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `organization_id` BIGINT UNSIGNED NOT NULL,
  `report_type` ENUM('ghg_inventory', 'domain_breakdown', 'decarbonization_progress', 'carbon_asset_portfolio') NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `parameters` JSON NOT NULL,
  `summary_data` JSON NOT NULL,
  `file_format` ENUM('json', 'csv', 'pdf') NOT NULL DEFAULT 'json',
  `file_path` VARCHAR(255) NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_reports_org` (`organization_id`),
  CONSTRAINT `fk_reports_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reports_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 20. Notifications
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `organization_id` BIGINT UNSIGNED NULL,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `action_url` VARCHAR(255) NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_notif_user` (`user_id`, `is_read`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notif_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 21. Audit Logs (Tenant & Security trail)
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` BIGINT UNSIGNED NULL,
  `organization_id` BIGINT UNSIGNED NULL,
  `action` VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(100) NOT NULL,
  `entity_id` BIGINT UNSIGNED NOT NULL,
  `old_values` JSON NULL,
  `new_values` JSON NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_audit_org` (`organization_id`),
  INDEX `idx_audit_user` (`user_id`),
  INDEX `idx_audit_action` (`action`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_audit_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
