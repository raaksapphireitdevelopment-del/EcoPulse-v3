-- 04_demo_seed.sql
-- Default super admin and demo accounts for EcoPulse MVP V3
-- Standard demo password for all seeded accounts is: Admin@123

USE `ecopulse_v3`;

-- 1. Seed Super Admin
INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `system_role`, `phone`, `status`) VALUES
(1, 'Super Admin', 'admin@ecopulse.org', '$2y$10$RI16Q6fe/4yzWBYp9igZ.ucIisXmYZTLurfh2gpMoyyEKeA9rDxFi', 'super_admin', '+1-555-0100', 'active')
ON DUPLICATE KEY UPDATE `password_hash` = VALUES(`password_hash`), `status` = 'active';

-- 2. Seed Demo Organization
INSERT INTO `organizations` (`id`, `name`, `registration_number`, `industry_primary`, `country`, `state_region`, `city`, `address`, `website`, `status`) VALUES
(1, 'Apex Healthcare Global', 'CORP-HC-2024-88', 'Healthcare', 'India', 'Maharashtra', 'Mumbai', 'Plot 42, Tech Park, Powai', 'https://apexhealthcare.example.com', 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 3. Seed Organization Owner & Admin Users
INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `system_role`, `phone`, `status`) VALUES
(2, 'Dr. Sarah Jenkins', 'sarah.jenkins@apexhealth.org', '$2y$10$RI16Q6fe/4yzWBYp9igZ.ucIisXmYZTLurfh2gpMoyyEKeA9rDxFi', 'user', '+91-9876543210', 'active'),
(3, 'Mark Reynolds', 'mark.reynolds@apexhealth.org', '$2y$10$RI16Q6fe/4yzWBYp9igZ.ucIisXmYZTLurfh2gpMoyyEKeA9rDxFi', 'user', '+91-9876543211', 'active'),
(4, 'Elena Rostova', 'elena@acmeventures.com', '$2y$10$RI16Q6fe/4yzWBYp9igZ.ucIisXmYZTLurfh2gpMoyyEKeA9rDxFi', 'buyer', '+1-555-0199', 'active')
ON DUPLICATE KEY UPDATE `password_hash` = VALUES(`password_hash`), `status` = 'active';

-- 4. Seed Organization Memberships
INSERT INTO `organization_members` (`organization_id`, `user_id`, `org_role`, `status`) VALUES
(1, 2, 'organization_owner', 'active'),
(1, 3, 'organization_admin', 'active')
ON DUPLICATE KEY UPDATE `org_role` = VALUES(`org_role`);

-- 5. Seed Domain Profile for Apex Healthcare
INSERT INTO `domain_profiles` (`id`, `organization_id`, `domain_id`, `profile_name`, `base_year`, `config_settings`, `status`) VALUES
(1, 1, 1, 'Apex Clinical & Specialty Care Units', 2024, '{"licensed_beds": 450, "inpatient_annual_capacity": 60000, "ot_suites": 12}', 'active')
ON DUPLICATE KEY UPDATE `profile_name` = VALUES(`profile_name`);

-- 6. Seed Facility for Apex Healthcare
INSERT INTO `facilities` (`id`, `organization_id`, `domain_profile_id`, `name`, `facility_code`, `facility_type`, `country`, `state_region`, `city`, `address`, `gross_area_sqm`, `operational_control`, `status`) VALUES
(1, 1, 1, 'Apex Central Super-Specialty Hospital', 'FAC-HC-001', 'Tertiary Hospital', 'India', 'Maharashtra', 'Mumbai', 'Plot 42, Central Medical District, Powai', 38500.00, 1, 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 7. Seed Reporting Period
INSERT INTO `reporting_periods` (`id`, `organization_id`, `name`, `period_type`, `start_date`, `end_date`, `is_closed`) VALUES
(1, 1, 'FY 2025 (Annual)', 'annual', '2025-01-01', '2025-12-31', 0)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);
