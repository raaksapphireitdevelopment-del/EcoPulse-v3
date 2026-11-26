-- 03_emission_factors.sql
-- Authentic emission factors catalog for EcoPulse MVP V3
-- Includes real-world emission factors from DEFRA 2024, US EPA eGRID, IPCC AR6, and labeled demo factors

USE `ecopulse_v3`;

INSERT INTO `emission_factors` (
  `id`, `activity_category_id`, `factor_name`, `factor_value`, `activity_unit`, `co2e_unit`,
  `scope`, `gas_detail`, `geography`, `source`, `source_reference`, `version`,
  `effective_date`, `expiry_date`, `validation_status`, `is_demo`
) VALUES
-- Healthcare
(1001, 101, 'Diesel Fuel 100% Mineral (Stationary Generator)', 2.68650000, 'Liters', 'kgCO2e', 'scope_1', 'CO2, CH4, N2O', 'Global', 'UK DEFRA / DESNZ', 'UK Government GHG Conversion Factors 2024 - Fuels', '2024-v1.0', '2024-01-01', NULL, 'verified', 0),
(1002, 102, 'Medical Nitrous Oxide (N2O) Pure GWP100', 273.00000000, 'kg', 'kgCO2e', 'scope_1', 'N2O (IPCC AR6 GWP=273)', 'Global', 'IPCC Sixth Assessment Report', 'IPCC AR6 WG1 Table 7.15 (Direct GWP 100-yr)', 'AR6-2023', '2023-01-01', NULL, 'verified', 0),
(1003, 103, 'Grid Electricity - National Average (India)', 0.71600000, 'kWh', 'kgCO2e', 'scope_2', 'CO2, CH4, N2O', 'India', 'CEA India CO2 Baseline Database', 'Central Electricity Authority Ver 19.0 (2023-24)', '2024-v19.0', '2024-01-01', NULL, 'verified', 0),
(1004, 103, 'Grid Electricity - US Average (eGRID)', 0.38620000, 'kWh', 'kgCO2e', 'scope_2', 'CO2, CH4, N2O', 'United States', 'US EPA eGRID', 'eGRID2023 Summary Tables (US Average)', '2024-v1.0', '2024-01-01', NULL, 'verified', 0),
(1005, 104, 'Clinical Infectious Waste Incineration', 1.83300000, 'kg', 'kgCO2e', 'scope_3', 'CO2, N2O', 'Global', 'UK DEFRA / DESNZ', 'UK GHG Conversion Factors 2024 - Waste Disposal', '2024-v1.0', '2024-01-01', NULL, 'verified', 0),

-- Manufacturing
(2001, 201, 'Natural Gas (Gross Calorific Basis)', 2.02870000, 'm3', 'kgCO2e', 'scope_1', 'CO2, CH4, N2O', 'Global', 'UK DEFRA / DESNZ', 'UK GHG Factors 2024 - Gaseous Fuels', '2024-v1.0', '2024-01-01', NULL, 'verified', 0),
(2002, 202, 'Industrial Grid Electricity (High Voltage)', 0.69800000, 'kWh', 'kgCO2e', 'scope_2', 'CO2, CH4, N2O', 'India', 'CEA India / State Industrial Tariff', 'CEA Baseline v19 Industrial Feed', '2024-v19.0', '2024-01-01', NULL, 'verified', 0),
(2003, 203, 'Purchased Industrial District Steam (Natural Gas Boiler)', 0.05430000, 'MJ', 'kgCO2e', 'scope_2', 'CO2, CH4', 'Global', 'US EPA GHG Emission Factors Hub', 'Table 6 - Steam and Heat (80% boiler efficiency)', '2024-v2.0', '2024-01-01', NULL, 'verified', 0),
(2004, 204, 'Freight Transport - Articulated Truck >33t', 0.10650000, 'ton-km', 'kgCO2e', 'scope_3', 'Well-to-Wheel CO2e', 'Global', 'UK DEFRA / DESNZ', 'Freighting Goods 2024 - HGV All Diesel', '2024-v1.0', '2024-01-01', NULL, 'verified', 0),

-- Agriculture
(3011, 301, 'Off-Road Agricultural Diesel (Tractor/Harvester)', 2.75800000, 'Liters', 'kgCO2e', 'scope_1', 'CO2, CH4, N2O', 'Global', 'IPCC Tier 1 / EPA', 'EPA Mobile Combustion Agricultural Equipment', '2024-v1.0', '2024-01-01', NULL, 'verified', 0),
(3012, 302, 'Synthetic Nitrogen Fertilizer (Direct + Indirect N2O)', 5.88000000, 'kg', 'kgCO2e', 'scope_1', 'N2O Soil Flux', 'Global', 'IPCC Guidelines 2019 Refinement', 'Vol 4 Agriculture, Forestry and Other Land Use', '2019-IPCC', '2020-01-01', NULL, 'verified', 0),
(3013, 303, 'Dairy Cow Enteric Fermentation (Methane)', 3200.00000000, 'head-year', 'kgCO2e', 'scope_1', 'CH4 (AR6 GWP=27.9)', 'Global', 'IPCC Tier 1 Enteric Emission Factors', 'IPCC 2019 Guidelines Table 10.11 Dairy Cattle', '2019-IPCC', '2020-01-01', NULL, 'verified', 0),
(3014, 304, 'Agricultural Irrigation Power (Rural Feeder)', 0.72000000, 'kWh', 'kgCO2e', 'scope_2', 'CO2, CH4, N2O', 'India', 'CEA Baseline Database', 'Agricultural Sub-Transmission Grid', '2024-v19.0', '2024-01-01', NULL, 'verified', 0),

-- Energy & Utilities
(4001, 401, 'Natural Gas Turbine (Open/Combined Cycle Combustion)', 2.02870000, 'm3', 'kgCO2e', 'scope_1', 'CO2, CH4, N2O', 'Global', 'UK DEFRA / DESNZ', 'Stationary Combustion - Gaseous Fuels', '2024-v1.0', '2024-01-01', NULL, 'verified', 0),
(4002, 402, 'Sulfur Hexafluoride (SF6) Pure High-Voltage Switchgear', 24300.00000000, 'kg', 'kgCO2e', 'scope_1', 'SF6 (AR6 GWP=24,300)', 'Global', 'IPCC Sixth Assessment Report', 'IPCC AR6 WG1 Table 7.15 (SF6 100-yr GWP)', 'AR6-2023', '2023-01-01', NULL, 'verified', 0),
(4003, 403, 'Station Auxiliary Power (Self-Consumed Generation)', 0.69000000, 'kWh', 'kgCO2e', 'scope_2', 'CO2', 'Global', 'IEA World Energy Outlook', 'IEA Utility Auxiliary Power Baseline', '2024-IEA', '2024-01-01', NULL, 'verified', 0),
(4004, 404, 'Transmission & Distribution Grid Loss Factor', 0.05830000, 'kWh', 'kgCO2e', 'scope_3', 'Line dissipation', 'India', 'CEA National T&D Loss Report', 'Average Technical & Commercial Loss Factor', '2024-v1.0', '2024-01-01', NULL, 'verified', 0),

-- Transportation & Logistics
(5001, 501, 'Commercial Diesel Fleet Vehicle (>3.5t)', 2.68650000, 'Liters', 'kgCO2e', 'scope_1', 'CO2, CH4, N2O', 'Global', 'UK DEFRA / DESNZ', 'UK GHG Conversion Factors 2024 - Delivery Vehicles', '2024-v1.0', '2024-01-01', NULL, 'verified', 0),
(5002, 502, 'Petrol Light Commercial Van (Class II)', 2.31440000, 'Liters', 'kgCO2e', 'scope_1', 'CO2, CH4, N2O', 'Global', 'UK DEFRA / DESNZ', 'UK GHG Factors 2024 - Petrol Vans', '2024-v1.0', '2024-01-01', NULL, 'verified', 0),
(5003, 503, 'Commercial Fleet EV Charging Depots', 0.52000000, 'kWh', 'kgCO2e', 'scope_2', 'CO2, CH4, N2O', 'Global', 'IEA World Grid Mix Average', 'IEA Global Fleet Charging Factor 2024', '2024-IEA', '2024-01-01', NULL, 'verified', 0),
(5004, 504, 'Air Freight - Long Haul Bellyhold (>3700 km)', 0.58410000, 'ton-km', 'kgCO2e', 'scope_3', 'With RF factor', 'Global', 'UK DEFRA / DESNZ', 'Freight Flights 2024 - Cargo Belly Cargo', '2024-v1.0', '2024-01-01', NULL, 'verified', 0),

-- Construction & Real Estate
(6001, 601, 'Construction Excavator & Crane Gas Oil (Red Diesel)', 2.75800000, 'Liters', 'kgCO2e', 'scope_1', 'CO2, CH4, N2O', 'Global', 'UK DEFRA / DESNZ', 'UK GHG Factors 2024 - Gas Oil Off-Highway', '2024-v1.0', '2024-01-01', NULL, 'verified', 0),
(6002, 602, 'Commercial Office Building Power (Average Tenancy)', 0.71600000, 'kWh', 'kgCO2e', 'scope_2', 'CO2, CH4, N2O', 'India', 'CEA Baseline Database', 'Grid Electricity Base Factor', '2024-v19.0', '2024-01-01', NULL, 'verified', 0),
(6003, 603, 'Ordinary Portland Cement (CEM I 42.5N Cradle-to-Gate)', 860.00000000, 'metric_ton', 'kgCO2e', 'scope_3', 'Embodied Clinker CO2', 'Global', 'ICE Database / GCCA', 'Inventory of Carbon and Energy (ICE) v3.0 CEM I', 'ICE-v3.0', '2023-01-01', NULL, 'verified', 0),
(6004, 604, 'Structural Steel Rebar (Global EAF Average)', 1420.00000000, 'metric_ton', 'kgCO2e', 'scope_3', 'Embodied Steel CO2', 'Global', 'World Steel Association', 'Life Cycle Inventory Study for Steel Products 2023', 'WSA-2023', '2023-01-01', NULL, 'verified', 0),

-- Waste Management
(7001, 701, 'Municipal Mixed Organic Waste Landfill (Fugitive CH4)', 458.00000000, 'metric_ton_waste', 'kgCO2e', 'scope_1', 'CH4 Landfill Gas', 'Global', 'IPCC Waste Model / DEFRA', 'DEFRA 2024 Waste Disposal - Landfill Mixed Food', '2024-v1.0', '2024-01-01', NULL, 'verified', 0),
(7002, 702, 'Landfill Compactor Heavy Machinery Diesel', 2.75800000, 'Liters', 'kgCO2e', 'scope_1', 'CO2, CH4, N2O', 'Global', 'UK DEFRA / DESNZ', 'Off-Highway Gas Oil 2024', '2024-v1.0', '2024-01-01', NULL, 'verified', 0),
(7003, 703, 'Materials Recovery Facility (MRF) Electricity', 0.71600000, 'kWh', 'kgCO2e', 'scope_2', 'CO2, CH4, N2O', 'India', 'CEA Baseline Database', 'Industrial Grid Electricity', '2024-v19.0', '2024-01-01', NULL, 'verified', 0),
(7004, 704, 'Aerobic Composting Diversion (Avoided GHG)', 10.20000000, 'metric_ton', 'kgCO2e', 'scope_3', 'Net Process CO2e', 'Global', 'UK DEFRA / DESNZ', 'UK GHG Conversion Factors 2024 - Composting', '2024-v1.0', '2024-01-01', NULL, 'verified', 0),

-- Hospitality & Tourism
(8001, 801, 'Liquefied Petroleum Gas (LPG) Commercial Kitchens', 2.93900000, 'kg', 'kgCO2e', 'scope_1', 'CO2, CH4, N2O', 'Global', 'UK DEFRA / DESNZ', 'UK GHG Factors 2024 - LPG 100% Mineral', '2024-v1.0', '2024-01-01', NULL, 'verified', 0),
(8002, 802, 'Hotel Resort Grid Electricity Consumption', 0.71600000, 'kWh', 'kgCO2e', 'scope_2', 'CO2, CH4, N2O', 'India', 'CEA Baseline Database', 'Commercial Hotel Tariff Grid Feed', '2024-v19.0', '2024-01-01', NULL, 'verified', 0),
(8003, 803, 'Industrial Laundry Steam & Process Hot Water', 0.05430000, 'MJ', 'kgCO2e', 'scope_2', 'CO2', 'Global', 'US EPA Emission Hub', 'Table 6 - Steam and Heat Generation', '2024-v2.0', '2024-01-01', NULL, 'verified', 0),
(8004, 804, 'Hotel Food Procurement Average Mix', 3.82000000, 'kg_produce', 'kgCO2e', 'scope_3', 'Embodied Food Carbon', 'Global', 'Poore & Nemecek / Our World in Data', 'Global Food Production Carbon Intensity Average', 'OWID-2023', '2023-01-01', NULL, 'verified', 0),

-- IT & Data Centers
(9001, 901, 'Data Center Critical Standby Diesel Generator', 2.68650000, 'Liters', 'kgCO2e', 'scope_1', 'CO2, CH4, N2O', 'Global', 'UK DEFRA / DESNZ', '100% Mineral Diesel Stationary Combustion', '2024-v1.0', '2024-01-01', NULL, 'verified', 0),
(9002, 902, 'Data Center Server Racks Input Grid Electricity', 0.71600000, 'kWh', 'kgCO2e', 'scope_2', 'CO2, CH4, N2O', 'India', 'CEA Baseline Database', 'High-Reliability Dedicated Feed Substation', '2024-v19.0', '2024-01-01', NULL, 'verified', 0),
(9003, 903, 'External District Chilled Water Cooling Loop', 0.78000000, 'ton-hour', 'kgCO2e', 'scope_2', 'Chiller Power CO2e', 'Global', 'ASHRAE TC 9.9', 'ASHRAE Data Center Cooling Baseline 2023', 'ASHRAE-2023', '2023-01-01', NULL, 'verified', 0),
(9004, 904, 'Hyperscale Cloud Compute Virtual CPU Instance (Standard)', 0.00285000, 'vCPU-hour', 'kgCO2e', 'scope_3', 'Embodied + Operational', 'Global', 'Cloud Carbon Footprint Project', 'CCF Model / Etsy / Thoughtworks Coefficients', 'CCF-v0.5', '2024-01-01', NULL, 'verified', 0),

-- Demo / Provisional Factor for development testing
(9999, 101, 'DEVELOPMENT DEMO FACTOR: Experimental Bio-Diesel Blend', 1.85000000, 'Liters', 'kgCO2e', 'scope_1', 'CO2 Biogenic', 'Demo Laboratory', 'EcoPulse R&D Sandbox', 'Internal Test Scenario Specification', 'DEMO-2026', '2026-01-01', NULL, 'demo', 1)
ON DUPLICATE KEY UPDATE
  `factor_name` = VALUES(`factor_name`),
  `factor_value` = VALUES(`factor_value`),
  `activity_unit` = VALUES(`activity_unit`),
  `co2e_unit` = VALUES(`co2e_unit`),
  `scope` = VALUES(`scope`),
  `gas_detail` = VALUES(`gas_detail`),
  `geography` = VALUES(`geography`),
  `source` = VALUES(`source`),
  `source_reference` = VALUES(`source_reference`),
  `version` = VALUES(`version`),
  `effective_date` = VALUES(`effective_date`),
  `validation_status` = VALUES(`validation_status`),
  `is_demo` = VALUES(`is_demo`);
