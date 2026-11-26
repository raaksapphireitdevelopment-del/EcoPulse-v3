-- 01_system_domains.sql
-- Seeds the 9 official business domains for EcoPulse MVP V3

USE `ecopulse_v3`;

INSERT INTO `domains` (`id`, `code`, `name`, `description`, `icon`, `sort_order`, `is_active`) VALUES
(1, 'healthcare', 'Healthcare', 'Hospitals, clinical laboratories, healthcare centers, medical gas usage and biomedical waste.', 'bi-hospital', 1, 1),
(2, 'manufacturing', 'Manufacturing', 'Industrial plants, factory operations, process heat, raw material inputs and heavy equipment.', 'bi-gear-wide-connected', 2, 1),
(3, 'agriculture', 'Agriculture', 'Crop production, livestock enteric fermentation, fertilizer management, and farm machinery.', 'bi-flower1', 3, 1),
(4, 'energy_utilities', 'Energy & Utilities', 'Power generation, fossil fuel combustion, grid distribution, and renewable generation assets.', 'bi-lightning-charge', 4, 1),
(5, 'transportation_logistics', 'Transportation & Logistics', 'Fleet vehicles, freight logistics, aviation, marine shipping, and multimodal supply chains.', 'bi-truck', 5, 1),
(6, 'construction_realestate', 'Construction & Real Estate', 'Building development, embodied carbon in materials, HVAC systems, and tenant facilities.', 'bi-buildings', 6, 1),
(7, 'waste_management', 'Waste Management', 'Landfill methane capture, municipal solid waste, composting, and wastewater treatment.', 'bi-trash3', 7, 1),
(8, 'hospitality_tourism', 'Hospitality & Tourism', 'Hotels, resorts, commercial kitchens, laundry services, and guest accommodations.', 'bi-cup-hot', 8, 1),
(9, 'it_datacenters', 'IT & Data Centers', 'Data centers, cloud infrastructure, server rack power consumption, and PUE optimization.', 'bi-server', 9, 1)
ON DUPLICATE KEY UPDATE 
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `icon` = VALUES(`icon`),
  `sort_order` = VALUES(`sort_order`),
  `is_active` = VALUES(`is_active`);
