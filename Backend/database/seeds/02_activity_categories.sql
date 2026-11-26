-- 02_activity_categories.sql
-- Activity Categories across all 9 domains for EcoPulse MVP V3

USE `ecopulse_v3`;

INSERT INTO `activity_categories` (`id`, `domain_id`, `code`, `name`, `scope`, `standard_unit`, `description`) VALUES
-- 1. Healthcare
(101, 1, 'hc_stationary_combustion', 'Hospital Boiler & Generator Diesel', 'scope_1', 'Liters', 'Stationary diesel fuel used for emergency generators and heating boilers'),
(102, 1, 'hc_medical_gases', 'Anesthetic & Medical Gases (N2O, Sevoflurane)', 'scope_1', 'kg', 'Inhalation anesthetics and medical nitrous oxide with high GWP'),
(103, 1, 'hc_grid_electricity', 'Hospital Purchased Grid Electricity', 'scope_2', 'kWh', 'Facility electricity consumption for HVAC, imaging devices, and wards'),
(104, 1, 'hc_biomedical_waste', 'Incinerated Biomedical Waste', 'scope_3', 'kg', 'High-temperature thermal incineration of infectious biological waste'),

-- 2. Manufacturing
(201, 2, 'mfg_natural_gas', 'Furnace & Industrial Natural Gas', 'scope_1', 'm3', 'Natural gas consumed in smelting, drying, and process heating furnaces'),
(202, 2, 'mfg_purchased_power', 'Industrial Manufacturing Electricity', 'scope_2', 'kWh', 'Heavy machinery, assembly lines, and factory floor power'),
(203, 2, 'mfg_process_steam', 'Purchased Industrial Steam/Heat', 'scope_2', 'MJ', 'District or co-generation steam piped into production units'),
(204, 2, 'mfg_raw_materials', 'Raw Steel & Polymer Input Freight', 'scope_3', 'ton-km', 'Upstream freight transport of raw materials to manufacturing plant'),

-- 3. Agriculture
(301, 3, 'ag_tractor_diesel', 'Farm Machinery & Tractor Diesel', 'scope_1', 'Liters', 'Mobile combustion in tractors, harvesters, and irrigation pumps'),
(302, 3, 'ag_fertilizer_n2o', 'Synthetic Nitrogen Fertilizer Application', 'scope_1', 'kg', 'Direct and indirect soil nitrous oxide emissions from synthetic nitrogen'),
(303, 3, 'ag_livestock_enteric', 'Dairy & Cattle Enteric Fermentation', 'scope_1', 'head-year', 'Biogenic methane emissions from ruminant animal digestion'),
(304, 3, 'ag_irrigation_electricity', 'Deep-Well Irrigation Electricity', 'scope_2', 'kWh', 'Purchased grid power for high-pressure irrigation and drying silos'),

-- 4. Energy & Utilities
(401, 4, 'eu_gas_turbine', 'Natural Gas Peaker Turbine Combustion', 'scope_1', 'm3', 'High-efficiency combined-cycle gas turbine electricity generation'),
(402, 4, 'eu_substation_sf6', 'Substation Switchgear SF6 Leakage', 'scope_1', 'kg', 'Sulfur hexafluoride gas insulating medium leakages in high-voltage breakers'),
(403, 4, 'eu_parasitic_power', 'Plant Auxiliary & Parasitic Power Consumption', 'scope_2', 'kWh', 'Station power consumption for cooling towers, pumps, and controls'),
(404, 4, 'eu_transmission_loss', 'Grid Transmission & Distribution Losses', 'scope_3', 'kWh', 'Line resistance and stepping transformer energy dissipation losses'),

-- 5. Transportation & Logistics
(501, 5, 'tr_heavy_truck_diesel', 'Heavy Duty Long-Haul Fleet Diesel', 'scope_1', 'Liters', 'Class 8 commercial articulated trucks (>33,000 lbs GVWR) fuel usage'),
(502, 5, 'tr_delivery_van_fuel', 'Last-Mile Delivery Vans (Diesel/Petrol)', 'scope_1', 'Liters', 'Urban distribution vans and courier vehicles'),
(503, 5, 'tr_ev_fleet_charging', 'Electric Delivery Fleet Charging', 'scope_2', 'kWh', 'Grid electricity used at logistics hub fleet fast-charging depots'),
(504, 5, 'tr_air_freight_belly', 'Commercial Air Cargo Freight', 'scope_3', 'ton-km', 'Dedicated and belly-hold cargo air transportation per metric ton-kilometer'),

-- 6. Construction & Real Estate
(601, 6, 'cr_heavy_equipment', 'Excavators & Cranes On-Site Diesel', 'scope_1', 'Liters', 'Earthmoving machinery, mobile tower cranes, and site generators'),
(602, 6, 'cr_commercial_hvac', 'Building Complex Purchased Electricity (HVAC & Lighting)', 'scope_2', 'kWh', 'Commercial office tower centralized chilling plant and tenant electricity'),
(603, 6, 'cr_embodied_cement', 'Structural Ordinary Portland Cement', 'scope_3', 'metric_ton', 'Cradle-to-gate embodied carbon in concrete and structural cement'),
(604, 6, 'cr_embodied_steel', 'Structural Rebar & Steel Sections', 'scope_3', 'metric_ton', 'Blast furnace and electric arc furnace structural steel reinforcement'),

-- 7. Waste Management
(701, 7, 'wm_landfill_fugitive', 'Fugitive Landfill Methane (CH4)', 'scope_1', 'metric_ton_waste', 'Anaerobic decomposition of organic waste in non-flared landfill cells'),
(702, 7, 'wm_compactor_diesel', 'Landfill Heavy Compactor Diesel', 'scope_1', 'Liters', 'Site machinery for waste leveling, compaction, and daily soil cover'),
(703, 7, 'wm_sorting_facility_power', 'MRF Automated Sorting Facility Electricity', 'scope_2', 'kWh', 'Conveyor optical sorters, balers, and shredders in materials recovery facilities'),
(704, 7, 'wm_composting_diversion', 'Organics Aerobic Composting Diversion', 'scope_3', 'metric_ton', 'Municipal green waste diversion avoiding anaerobic methane production'),

-- 8. Hospitality & Tourism
(801, 8, 'ht_kitchen_lpg', 'Commercial Hotel Kitchen LPG / Gas', 'scope_1', 'kg', 'Liquefied petroleum gas used in banquet stoves, ovens, and steamers'),
(802, 8, 'ht_resort_electricity', 'Hotel Guest Rooms & Chiller Electricity', 'scope_2', 'kWh', 'Room climate control, lighting, water heating, and pool filtration'),
(803, 8, 'ht_laundry_steam', 'Commercial Guest Laundry Steam & Hot Water', 'scope_2', 'MJ', 'Purchased steam for industrial washers, ironers, and linen presses'),
(804, 8, 'ht_food_procurement', 'Hotel Food & Beverage Supply Chain', 'scope_3', 'kg_produce', 'Embodied carbon in procured meat, dairy, and agricultural provisions'),

-- 9. IT & Data Centers
(901, 9, 'it_backup_generator', 'Data Center Standby Diesel Generators', 'scope_1', 'Liters', 'Ultra-reliable standby diesel generators for emergency backup power'),
(902, 9, 'it_datacenter_grid_power', 'Server Racks & UPS Input Grid Electricity', 'scope_2', 'kWh', 'Direct IT load including compute servers, storage arrays, and network switches'),
(903, 9, 'it_chilled_water_cooling', 'External District Chilled Water Cooling', 'scope_2', 'ton-hour', 'Purchased chilled water loop for precision CRAC/CRAH server room cooling'),
(904, 9, 'it_cloud_compute_scope3', 'Third-Party Hyperscale Cloud Instances (AWS/GCP/Azure)', 'scope_3', 'vCPU-hour', 'Shared server virtualization compute instances in public cloud datacenters')
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `scope` = VALUES(`scope`),
  `standard_unit` = VALUES(`standard_unit`),
  `description` = VALUES(`description`);
