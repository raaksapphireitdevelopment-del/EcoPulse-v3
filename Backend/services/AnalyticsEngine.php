<?php
declare(strict_types=1);

namespace EcoPulse\Services;

use EcoPulse\Core\Database;
use EcoPulse\Models\ReductionOpportunity;

class AnalyticsEngine
{
    /**
     * Build common SQL WHERE clauses and parameters from user filters.
     */
    private static function buildFilterConditions(int $orgId, array $filters): array
    {
        $conditions = [
            'c.`organization_id` = :org_id',
            'c.`is_current` = 1',
        ];
        $params = [':org_id' => $orgId];

        if (!empty($filters['facility_id'])) {
            $conditions[] = 'c.`facility_id` = :facility_id';
            $params[':facility_id'] = (int)$filters['facility_id'];
        }

        if (!empty($filters['reporting_period_id'])) {
            $conditions[] = 'c.`reporting_period_id` = :reporting_period_id';
            $params[':reporting_period_id'] = (int)$filters['reporting_period_id'];
        }

        if (!empty($filters['domain_profile_id'])) {
            $conditions[] = 'ar.`domain_profile_id` = :domain_profile_id';
            $params[':domain_profile_id'] = (int)$filters['domain_profile_id'];
        }

        if (!empty($filters['scope']) && $filters['scope'] !== 'all') {
            $conditions[] = 'c.`scope` = :scope';
            $params[':scope'] = $filters['scope'];
        }

        if (!empty($filters['calculation_status'])) {
            $conditions[] = 'c.`calculation_status` = :calc_status';
            $params[':calc_status'] = $filters['calculation_status'];
        }

        if (!empty($filters['date_from'])) {
            $conditions[] = 'ar.`activity_date` >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $conditions[] = 'ar.`activity_date` <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }

        return [$conditions, $params];
    }

    /**
     * 1. Overview KPIs
     * Aggregates operational emissions (Scopes 1, 2, 3), unclassified, separately reported avoided emissions,
     * activity counts, and demo totals.
     */
    public static function getOverview(int $orgId, array $filters = []): array
    {
        $db = Database::getInstance();
        [$conditions, $params] = self::buildFilterConditions($orgId, $filters);
        $whereClause = implode(' AND ', $conditions);

        // Official Validated Aggregations (excluding demo factors)
        $sql = "SELECT
                    SUM(CASE WHEN c.scope = 'scope_1' AND ef.is_demo = 0 THEN c.tonnes_co2e ELSE 0 END) as scope_1_tonnes,
                    SUM(CASE WHEN c.scope = 'scope_2' AND ef.is_demo = 0 THEN c.tonnes_co2e ELSE 0 END) as scope_2_tonnes,
                    SUM(CASE WHEN c.scope = 'scope_3' AND ef.is_demo = 0 THEN c.tonnes_co2e ELSE 0 END) as scope_3_tonnes,
                    SUM(CASE WHEN c.scope NOT IN ('scope_1', 'scope_2', 'scope_3', 'avoided_emissions') AND ef.is_demo = 0 THEN c.tonnes_co2e ELSE 0 END) as unclassified_tonnes,
                    SUM(CASE WHEN c.scope = 'avoided_emissions' AND ef.is_demo = 0 THEN c.tonnes_co2e ELSE 0 END) as avoided_emissions_tonnes,
                    SUM(CASE WHEN ef.is_demo = 1 OR c.calculation_status = 'demo' THEN c.tonnes_co2e ELSE 0 END) as demo_tonnes,
                    COUNT(DISTINCT c.activity_record_id) as validated_calculations_count
                FROM `carbon_calculations` c
                JOIN `activity_records` ar ON ar.id = c.activity_record_id
                JOIN `emission_factors` ef ON ef.id = c.emission_factor_id
                WHERE {$whereClause}";

        $summary = $db->fetchOne($sql, $params) ?: [];

        $scope1 = (float)($summary['scope_1_tonnes'] ?? 0.0);
        $scope2 = (float)($summary['scope_2_tonnes'] ?? 0.0);
        $scope3 = (float)($summary['scope_3_tonnes'] ?? 0.0);
        $unclassified = (float)($summary['unclassified_tonnes'] ?? 0.0);
        $avoided = (float)($summary['avoided_emissions_tonnes'] ?? 0.0);
        $demo = (float)($summary['demo_tonnes'] ?? 0.0);
        $validatedCount = (int)($summary['validated_calculations_count'] ?? 0);

        // Strict operational total = Scope 1 + Scope 2 + Scope 3 + Unclassified (Avoided emissions strictly separated!)
        $operationalTotal = round($scope1 + $scope2 + $scope3 + $unclassified, 4);

        // Activity Record status counts
        $actConditions = ['organization_id = :org_id'];
        $actParams = [':org_id' => $orgId];
        if (!empty($filters['facility_id'])) {
            $actConditions[] = 'facility_id = :facility_id';
            $actParams[':facility_id'] = (int)$filters['facility_id'];
        }
        if (!empty($filters['reporting_period_id'])) {
            $actConditions[] = 'reporting_period_id = :reporting_period_id';
            $actParams[':reporting_period_id'] = (int)$filters['reporting_period_id'];
        }

        $actWhere = implode(' AND ', $actConditions);
        $actSql = "SELECT
                       COUNT(*) as total_records,
                       SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_count,
                       SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted_count,
                       SUM(CASE WHEN status = 'validated' THEN 1 ELSE 0 END) as validated_records_count,
                       SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
                   FROM `activity_records`
                   WHERE {$actWhere}";

        $actCounts = $db->fetchOne($actSql, $actParams) ?: [];
        $totalRecords = (int)($actCounts['total_records'] ?? 0);
        $pendingRecords = ((int)($actCounts['draft_count'] ?? 0)) + ((int)($actCounts['submitted_count'] ?? 0));
        $rejectedRecords = (int)($actCounts['rejected_count'] ?? 0);

        // Determine State
        $state = 'ready';
        if ($totalRecords === 0) {
            $state = 'no_records';
        } elseif ($validatedCount === 0 && $pendingRecords > 0) {
            $state = 'pending_calculations';
        } elseif ($pendingRecords > 0) {
            $state = 'partially_validated';
        }

        return [
            'state' => $state,
            'kpis' => [
                'total_operational_tco2e' => $operationalTotal,
                'scope_1_tco2e'           => round($scope1, 4),
                'scope_2_tco2e'           => round($scope2, 4),
                'scope_3_tco2e'           => round($scope3, 4),
                'unclassified_tco2e'      => round($unclassified, 4),
                'avoided_emissions_tco2e' => round($avoided, 4),
                'demo_calculations_tco2e' => round($demo, 4),
                'total_activity_records'  => $totalRecords,
                'validated_calculations'  => $validatedCount,
                'pending_records'         => $pendingRecords,
                'rejected_records'        => $rejectedRecords,
            ],
            'accounting_notes' => [
                'avoided_emissions_segregated' => true,
                'superseded_versions_excluded' => true,
                'demo_factors_segregated'      => true,
            ],
        ];
    }

    /**
     * 2. Scope-wise Analysis
     * Detailed Scope 1, 2, 3, unclassified breakdown with contributing activities drill-down.
     */
    public static function getEmissionsByScope(int $orgId, array $filters = []): array
    {
        $db = Database::getInstance();
        [$conditions, $params] = self::buildFilterConditions($orgId, $filters);
        $whereClause = implode(' AND ', $conditions);

        // Scope summary
        $sql = "SELECT c.scope,
                       SUM(c.tonnes_co2e) as total_tonnes,
                       COUNT(c.id) as calculation_count
                FROM `carbon_calculations` c
                JOIN `activity_records` ar ON ar.id = c.activity_record_id
                JOIN `emission_factors` ef ON ef.id = c.emission_factor_id
                WHERE {$whereClause} AND ef.is_demo = 0
                GROUP BY c.scope";

        $rows = $db->fetchAll($sql, $params);
        $scopes = [
            'scope_1' => ['tonnes' => 0.0, 'count' => 0, 'label' => 'Scope 1 (Direct Emissions)'],
            'scope_2' => ['tonnes' => 0.0, 'count' => 0, 'label' => 'Scope 2 (Electricity & Heat)'],
            'scope_3' => ['tonnes' => 0.0, 'count' => 0, 'label' => 'Scope 3 (Value Chain)'],
            'unclassified' => ['tonnes' => 0.0, 'count' => 0, 'label' => 'Unclassified Emissions'],
            'avoided_emissions' => ['tonnes' => 0.0, 'count' => 0, 'label' => 'Avoided Emissions (Segregated)'],
        ];

        $operationalTotal = 0.0;
        foreach ($rows as $row) {
            $key = $row['scope'];
            $t = (float)$row['total_tonnes'];
            if (!isset($scopes[$key])) {
                $key = 'unclassified';
            }
            $scopes[$key]['tonnes'] = round($t, 4);
            $scopes[$key]['count'] = (int)$row['calculation_count'];
            if ($key !== 'avoided_emissions') {
                $operationalTotal += $t;
            }
        }

        // Compute percentages of operational footprint
        foreach (['scope_1', 'scope_2', 'scope_3', 'unclassified'] as $s) {
            $scopes[$s]['percentage'] = $operationalTotal > 0
                ? round(($scopes[$s]['tonnes'] / $operationalTotal) * 100.0, 2)
                : 0.0;
        }

        // Detailed activities contributing to each scope
        $detailSql = "SELECT c.id, c.scope, c.tonnes_co2e, c.activity_quantity_snapshot, c.normalized_unit,
                             ar.activity_date, ar.description,
                             f.name as facility_name,
                             ac.name as category_name
                      FROM `carbon_calculations` c
                      JOIN `activity_records` ar ON ar.id = c.activity_record_id
                      JOIN `facilities` f ON f.id = c.facility_id
                      JOIN `activity_categories` ac ON ac.id = c.activity_category_id
                      JOIN `emission_factors` ef ON ef.id = c.emission_factor_id
                      WHERE {$whereClause} AND ef.is_demo = 0
                      ORDER BY c.tonnes_co2e DESC
                      LIMIT 100";

        $activities = $db->fetchAll($detailSql, $params);
        $activitiesByScope = [
            'scope_1' => [],
            'scope_2' => [],
            'scope_3' => [],
            'unclassified' => [],
            'avoided_emissions' => [],
        ];

        foreach ($activities as $act) {
            $s = $act['scope'];
            if (!isset($activitiesByScope[$s])) {
                $s = 'unclassified';
            }
            $activitiesByScope[$s][] = $act;
        }

        return [
            'operational_total_tco2e' => round($operationalTotal, 4),
            'scopes'                  => $scopes,
            'activities_by_scope'     => $activitiesByScope,
        ];
    }

    /**
     * 3. Domain-wise Analysis
     * Breakdown across the organization's enabled business domains only.
     */
    public static function getEmissionsByDomain(int $orgId, array $filters = []): array
    {
        $db = Database::getInstance();
        [$conditions, $params] = self::buildFilterConditions($orgId, $filters);
        $whereClause = implode(' AND ', $conditions);

        $sql = "SELECT dp.id as domain_profile_id, dp.profile_name,
                       d.id as domain_id, d.name as domain_name, d.code as domain_code,
                       SUM(CASE WHEN c.scope = 'scope_1' AND ef.is_demo = 0 THEN c.tonnes_co2e ELSE 0 END) as scope_1_tonnes,
                       SUM(CASE WHEN c.scope = 'scope_2' AND ef.is_demo = 0 THEN c.tonnes_co2e ELSE 0 END) as scope_2_tonnes,
                       SUM(CASE WHEN c.scope = 'scope_3' AND ef.is_demo = 0 THEN c.tonnes_co2e ELSE 0 END) as scope_3_tonnes,
                       SUM(CASE WHEN c.scope NOT IN ('scope_1', 'scope_2', 'scope_3', 'avoided_emissions') AND ef.is_demo = 0 THEN c.tonnes_co2e ELSE 0 END) as unclassified_tonnes,
                       SUM(CASE WHEN c.scope = 'avoided_emissions' AND ef.is_demo = 0 THEN c.tonnes_co2e ELSE 0 END) as avoided_emissions_tonnes,
                       COUNT(DISTINCT c.id) as calculation_count
                FROM `domain_profiles` dp
                JOIN `domains` d ON d.id = dp.domain_id
                LEFT JOIN `activity_records` ar ON ar.domain_profile_id = dp.id
                LEFT JOIN `carbon_calculations` c ON c.activity_record_id = ar.id AND c.is_current = 1
                LEFT JOIN `emission_factors` ef ON ef.id = c.emission_factor_id
                WHERE dp.organization_id = :org_id AND dp.status = 'active'
                GROUP BY dp.id, d.id
                ORDER BY (scope_1_tonnes + scope_2_tonnes + scope_3_tonnes) DESC";

        $rows = $db->fetchAll($sql, [':org_id' => $orgId]);
        $domains = [];

        foreach ($rows as $row) {
            $s1 = (float)$row['scope_1_tonnes'];
            $s2 = (float)$row['scope_2_tonnes'];
            $s3 = (float)$row['scope_3_tonnes'];
            $unc = (float)$row['unclassified_tonnes'];
            $avoided = (float)$row['avoided_emissions_tonnes'];
            $opTotal = round($s1 + $s2 + $s3 + $unc, 4);

            $domains[] = [
                'domain_profile_id'        => (int)$row['domain_profile_id'],
                'profile_name'             => $row['profile_name'],
                'domain_name'              => $row['domain_name'],
                'domain_code'              => $row['domain_code'],
                'operational_total_tco2e'  => $opTotal,
                'scope_1_tco2e'            => round($s1, 4),
                'scope_2_tco2e'            => round($s2, 4),
                'scope_3_tco2e'            => round($s3, 4),
                'unclassified_tco2e'       => round($unc, 4),
                'avoided_emissions_tco2e'  => round($avoided, 4),
                'calculation_count'        => (int)$row['calculation_count'],
            ];
        }

        return ['domains' => $domains];
    }

    /**
     * 4. Facility-wise Analysis
     * Facility comparison, Scope 1/2/3 breakdown, top emitting facilities.
     */
    public static function getEmissionsByFacility(int $orgId, array $filters = []): array
    {
        $db = Database::getInstance();
        [$conditions, $params] = self::buildFilterConditions($orgId, $filters);
        $whereClause = implode(' AND ', $conditions);

        $sql = "SELECT f.id as facility_id, f.name as facility_name, f.facility_type, f.city, f.country, f.gross_area_sqm,
                       SUM(CASE WHEN c.scope = 'scope_1' AND ef.is_demo = 0 THEN c.tonnes_co2e ELSE 0 END) as scope_1_tonnes,
                       SUM(CASE WHEN c.scope = 'scope_2' AND ef.is_demo = 0 THEN c.tonnes_co2e ELSE 0 END) as scope_2_tonnes,
                       SUM(CASE WHEN c.scope = 'scope_3' AND ef.is_demo = 0 THEN c.tonnes_co2e ELSE 0 END) as scope_3_tonnes,
                       SUM(CASE WHEN c.scope = 'avoided_emissions' AND ef.is_demo = 0 THEN c.tonnes_co2e ELSE 0 END) as avoided_emissions_tonnes,
                       COUNT(DISTINCT c.id) as calculation_count
                FROM `facilities` f
                LEFT JOIN `carbon_calculations` c ON c.facility_id = f.id AND c.is_current = 1
                LEFT JOIN `activity_records` ar ON ar.id = c.activity_record_id
                LEFT JOIN `emission_factors` ef ON ef.id = c.emission_factor_id
                WHERE f.organization_id = :org_id AND f.status = 'active'
                GROUP BY f.id
                ORDER BY (scope_1_tonnes + scope_2_tonnes + scope_3_tonnes) DESC";

        $rows = $db->fetchAll($sql, [':org_id' => $orgId]);
        $facilities = [];
        $totalOrgOperational = 0.0;

        foreach ($rows as $row) {
            $s1 = (float)$row['scope_1_tonnes'];
            $s2 = (float)$row['scope_2_tonnes'];
            $s3 = (float)$row['scope_3_tonnes'];
            $avoided = (float)$row['avoided_emissions_tonnes'];
            $opTotal = round($s1 + $s2 + $s3, 4);
            $totalOrgOperational += $opTotal;

            $area = (float)($row['gross_area_sqm'] ?? 0.0);
            $intensityPerSqm = ($area > 0 && $opTotal > 0) ? round(($opTotal * 1000.0) / $area, 2) : null; // kgCO2e/sqm

            $facilities[] = [
                'facility_id'             => (int)$row['facility_id'],
                'facility_name'           => $row['facility_name'],
                'facility_type'           => $row['facility_type'],
                'city'                    => $row['city'],
                'country'                 => $row['country'],
                'gross_area_sqm'          => $area > 0 ? $area : null,
                'operational_total_tco2e' => $opTotal,
                'scope_1_tco2e'           => round($s1, 4),
                'scope_2_tco2e'           => round($s2, 4),
                'scope_3_tco2e'           => round($s3, 4),
                'avoided_emissions_tco2e' => round($avoided, 4),
                'intensity_kgco2e_per_sqm'=> $intensityPerSqm,
                'calculation_count'       => (int)$row['calculation_count'],
            ];
        }

        // Append facility contribution percentage
        foreach ($facilities as &$fac) {
            $fac['share_percentage'] = $totalOrgOperational > 0
                ? round(($fac['operational_total_tco2e'] / $totalOrgOperational) * 100.0, 2)
                : 0.0;
        }

        return [
            'facilities' => $facilities,
            'top_emitter' => !empty($facilities) ? $facilities[0] : null,
        ];
    }

    /**
     * 5. Activity Category Pareto Analysis
     * High emission activity categories sorted by tonnes CO2e.
     */
    public static function getEmissionsByCategory(int $orgId, array $filters = []): array
    {
        $db = Database::getInstance();
        [$conditions, $params] = self::buildFilterConditions($orgId, $filters);
        $whereClause = implode(' AND ', $conditions);

        $sql = "SELECT ac.id as category_id, ac.name as category_name, ac.code as category_code, ac.scope,
                       d.name as domain_name,
                       SUM(c.tonnes_co2e) as total_tonnes,
                       SUM(c.activity_quantity_snapshot) as total_quantity,
                       c.normalized_unit,
                       COUNT(c.id) as activity_count
                FROM `carbon_calculations` c
                JOIN `activity_records` ar ON ar.id = c.activity_record_id
                JOIN `activity_categories` ac ON ac.id = c.activity_category_id
                JOIN `domains` d ON d.id = ac.domain_id
                JOIN `emission_factors` ef ON ef.id = c.emission_factor_id
                WHERE {$whereClause} AND ef.is_demo = 0
                GROUP BY ac.id, c.normalized_unit
                ORDER BY total_tonnes DESC";

        $rows = $db->fetchAll($sql, $params);
        $totalEmissions = array_sum(array_column($rows, 'total_tonnes'));

        $categories = array_map(function ($row) use ($totalEmissions) {
            $t = (float)$row['total_tonnes'];
            return [
                'category_id'     => (int)$row['category_id'],
                'category_name'   => $row['category_name'],
                'category_code'   => $row['category_code'],
                'scope'           => $row['scope'],
                'domain_name'     => $row['domain_name'],
                'total_tco2e'     => round($t, 4),
                'total_quantity'  => round((float)$row['total_quantity'], 2),
                'unit'            => $row['normalized_unit'],
                'activity_count'  => (int)$row['activity_count'],
                'share_percentage'=> $totalEmissions > 0 ? round(($t / $totalEmissions) * 100.0, 2) : 0.0,
            ];
        }, $rows);

        return ['categories' => $categories];
    }

    /**
     * 6. Emission Trends & Reporting Period Comparison
     * Monthly, quarterly, and reporting period comparisons with coverage completeness indicators.
     */
    public static function getEmissionTrends(int $orgId, array $filters = []): array
    {
        $db = Database::getInstance();
        [$conditions, $params] = self::buildFilterConditions($orgId, $filters);
        $whereClause = implode(' AND ', $conditions);

        // Monthly trends
        $monthlySql = "SELECT DATE_FORMAT(ar.activity_date, '%Y-%m') as `month`,
                              SUM(CASE WHEN c.scope = 'scope_1' THEN c.tonnes_co2e ELSE 0 END) as scope_1,
                              SUM(CASE WHEN c.scope = 'scope_2' THEN c.tonnes_co2e ELSE 0 END) as scope_2,
                              SUM(CASE WHEN c.scope = 'scope_3' THEN c.tonnes_co2e ELSE 0 END) as scope_3,
                              SUM(CASE WHEN c.scope NOT IN ('scope_1', 'scope_2', 'scope_3', 'avoided_emissions') THEN c.tonnes_co2e ELSE 0 END) as unclassified,
                              SUM(CASE WHEN c.scope = 'avoided_emissions' THEN c.tonnes_co2e ELSE 0 END) as avoided_emissions
                       FROM `carbon_calculations` c
                       JOIN `activity_records` ar ON ar.id = c.activity_record_id
                       JOIN `emission_factors` ef ON ef.id = c.emission_factor_id
                       WHERE {$whereClause} AND ef.is_demo = 0
                       GROUP BY DATE_FORMAT(ar.activity_date, '%Y-%m')
                       ORDER BY `month` ASC";

        $monthlyRows = $db->fetchAll($monthlySql, $params);
        $monthlyTrends = array_map(function ($row) {
            $s1 = (float)$row['scope_1'];
            $s2 = (float)$row['scope_2'];
            $s3 = (float)$row['scope_3'];
            $unc = (float)$row['unclassified'];
            return [
                'month'                   => $row['month'],
                'operational_total_tco2e' => round($s1 + $s2 + $s3 + $unc, 4),
                'scope_1_tco2e'           => round($s1, 4),
                'scope_2_tco2e'           => round($s2, 4),
                'scope_3_tco2e'           => round($s3, 4),
                'unclassified_tco2e'      => round($unc, 4),
                'avoided_emissions_tco2e' => round((float)$row['avoided_emissions'], 4),
            ];
        }, $monthlyRows);

        // Reporting period comparisons
        $periodSql = "SELECT rp.id as period_id, rp.name as period_name, rp.start_date, rp.end_date, rp.is_closed,
                             COUNT(DISTINCT ar.id) as activity_count,
                             COUNT(DISTINCT DATE_FORMAT(ar.activity_date, '%Y-%m')) as active_months_count,
                             SUM(CASE WHEN c.scope = 'scope_1' AND ef.is_demo = 0 THEN c.tonnes_co2e ELSE 0 END) as scope_1,
                             SUM(CASE WHEN c.scope = 'scope_2' AND ef.is_demo = 0 THEN c.tonnes_co2e ELSE 0 END) as scope_2,
                             SUM(CASE WHEN c.scope = 'scope_3' AND ef.is_demo = 0 THEN c.tonnes_co2e ELSE 0 END) as scope_3,
                             SUM(CASE WHEN c.scope = 'avoided_emissions' AND ef.is_demo = 0 THEN c.tonnes_co2e ELSE 0 END) as avoided_emissions
                      FROM `reporting_periods` rp
                      LEFT JOIN `activity_records` ar ON ar.reporting_period_id = rp.id
                      LEFT JOIN `carbon_calculations` c ON c.activity_record_id = ar.id AND c.is_current = 1
                      LEFT JOIN `emission_factors` ef ON ef.id = c.emission_factor_id
                      WHERE rp.organization_id = :org_id
                      GROUP BY rp.id
                      ORDER BY rp.start_date ASC";

        $periodRows = $db->fetchAll($periodSql, [':org_id' => $orgId]);
        $periodComparison = [];

        foreach ($periodRows as $p) {
            $s1 = (float)$p['scope_1'];
            $s2 = (float)$p['scope_2'];
            $s3 = (float)$p['scope_3'];
            $opTotal = round($s1 + $s2 + $s3, 4);
            $activeMonths = (int)$p['active_months_count'];

            // Completeness warning if active months < 3 for an annual period
            $isComplete = ($activeMonths >= 4 || (int)$p['is_closed'] === 1);
            $warning = null;
            if (!$isComplete && (int)$p['activity_count'] > 0) {
                $warning = "Period has only {$activeMonths} month(s) of activity data recorded. Comparisons may not reflect full annual inventory.";
            }

            $periodComparison[] = [
                'period_id'               => (int)$p['period_id'],
                'period_name'             => $p['period_name'],
                'start_date'              => $p['start_date'],
                'end_date'                => $p['end_date'],
                'is_closed'               => (bool)$p['is_closed'],
                'operational_total_tco2e' => $opTotal,
                'scope_1_tco2e'           => round($s1, 4),
                'scope_2_tco2e'           => round($s2, 4),
                'scope_3_tco2e'           => round($s3, 4),
                'avoided_emissions_tco2e' => round((float)$p['avoided_emissions'], 4),
                'activity_count'          => (int)$p['activity_count'],
                'active_months'           => $activeMonths,
                'is_complete'             => $isComplete,
                'warning'                 => $warning,
            ];
        }

        return [
            'monthly_trends'    => $monthlyTrends,
            'period_comparison' => $periodComparison,
        ];
    }

    /**
     * 7. Emission Intensity Indicators
     * Calculated strictly where valid, compatible numerator and denominator exist.
     * Never invents or hallucinates missing denominators.
     */
    public static function getEmissionIntensity(int $orgId, array $filters = []): array
    {
        $db = Database::getInstance();
        $overview = self::getOverview($orgId, $filters);
        $totalOpTco2e = (float)$overview['kpis']['total_operational_tco2e'];

        $metrics = [];

        // 1. Building Gross Floor Area Intensity (tCO2e / m2)
        $facilityConditions = ['organization_id = :org_id', 'status = \'active\''];
        $facParams = [':org_id' => $orgId];
        if (!empty($filters['facility_id'])) {
            $facilityConditions[] = 'id = :facility_id';
            $facParams[':facility_id'] = (int)$filters['facility_id'];
        }

        $facWhere = implode(' AND ', $facilityConditions);
        $facAreaRow = $db->fetchOne(
            "SELECT SUM(gross_area_sqm) as total_area FROM `facilities` WHERE {$facWhere}",
            $facParams
        );

        $totalArea = (float)($facAreaRow['total_area'] ?? 0.0);
        if ($totalArea > 0 && $totalOpTco2e > 0) {
            $metrics[] = [
                'indicator'        => 'Building Floor Area Intensity',
                'value'            => round($totalOpTco2e / $totalArea, 4),
                'unit'             => 'tCO2e / m²',
                'secondary_value'  => round(($totalOpTco2e * 1000.0) / $totalArea, 2),
                'secondary_unit'   => 'kgCO2e / m²',
                'denominator'      => round($totalArea, 2),
                'denominator_unit' => 'm² gross floor area',
                'status'           => 'calculated',
                'calculation_basis'=> "Total Operational Emissions ({$totalOpTco2e} tCO2e) / Total Facility Gross Area ({$totalArea} m²)",
            ];
        } else {
            $metrics[] = [
                'indicator'        => 'Building Floor Area Intensity',
                'value'            => null,
                'unit'             => 'tCO2e / m²',
                'status'           => 'insufficient_data',
                'calculation_basis'=> 'Requires valid non-zero gross floor area in facility profiles and calculated emissions.',
            ];
        }

        // 2. Freight Transport Intensity (tCO2e / tonne-km)
        $freightRow = $db->fetchOne(
            "SELECT SUM(c.tonnes_co2e) as freight_emissions, SUM(c.activity_quantity_snapshot) as total_ton_km
             FROM `carbon_calculations` c
             JOIN `activity_categories` ac ON ac.id = c.activity_category_id
             WHERE c.organization_id = :org_id AND c.is_current = 1 AND ac.code LIKE '%freight%'",
            [':org_id' => $orgId]
        );

        $freightTonKm = (float)($freightRow['total_ton_km'] ?? 0.0);
        $freightEmissions = (float)($freightRow['freight_emissions'] ?? 0.0);

        if ($freightTonKm > 0 && $freightEmissions > 0) {
            $metrics[] = [
                'indicator'        => 'Freight Logistics Intensity',
                'value'            => round(($freightEmissions * 1000.0) / $freightTonKm, 4),
                'unit'             => 'kgCO2e / ton-km',
                'denominator'      => round($freightTonKm, 2),
                'denominator_unit' => 'ton-km freight throughput',
                'status'           => 'calculated',
                'calculation_basis'=> "Freight Emissions ({$freightEmissions} tCO2e) / {$freightTonKm} ton-km",
            ];
        } else {
            $metrics[] = [
                'indicator'        => 'Freight Logistics Intensity',
                'value'            => null,
                'unit'             => 'kgCO2e / ton-km',
                'status'           => 'insufficient_data',
                'calculation_basis'=> 'Requires freight transport activity records with ton-km units.',
            ];
        }

        // 3. Hospital Inpatient Bed-Day Intensity
        $metrics[] = [
            'indicator'        => 'Healthcare Inpatient Bed-Day Intensity',
            'value'            => null,
            'unit'             => 'kgCO2e / patient bed-day',
            'status'           => 'insufficient_data',
            'calculation_basis'=> 'Requires hospital patient-day occupancy log to be linked.',
        ];

        // 4. Hospitality Guest-Night Intensity
        $metrics[] = [
            'indicator'        => 'Hospitality Guest-Night Intensity',
            'value'            => null,
            'unit'             => 'kgCO2e / guest-night',
            'status'           => 'insufficient_data',
            'calculation_basis'=> 'Requires guest-night booking records to be recorded.',
        ];

        return ['indicators' => $metrics];
    }

    /**
     * 8. Reduction Opportunity Identification
     * Generates data-driven decarbonization opportunities based on actual activity and high-emitting categories.
     * Clearly distinguishes recommendations from measured results.
     */
    public static function identifyOpportunities(int $orgId, array $filters = []): array
    {
        $db = Database::getInstance();

        // 1. Find organization's highest emitting categories
        $catSql = "SELECT ac.id as category_id, ac.name as category_name, ac.code as category_code, ac.scope,
                          d.name as domain_name, d.code as domain_code,
                          f.id as facility_id, f.name as facility_name,
                          SUM(c.tonnes_co2e) as category_emissions,
                          SUM(c.activity_quantity_snapshot) as total_quantity,
                          c.normalized_unit
                   FROM `carbon_calculations` c
                   JOIN `activity_records` ar ON ar.id = c.activity_record_id
                   JOIN `activity_categories` ac ON ac.id = c.activity_category_id
                   JOIN `domains` d ON d.id = ac.domain_id
                   JOIN `facilities` f ON f.id = c.facility_id
                   JOIN `emission_factors` ef ON ef.id = c.emission_factor_id
                   WHERE c.organization_id = :org_id AND c.is_current = 1 AND ef.is_demo = 0
                   GROUP BY ac.id, f.id, c.normalized_unit
                   HAVING category_emissions > 0.05
                   ORDER BY category_emissions DESC
                   LIMIT 10";

        $highEmitting = $db->fetchAll($catSql, [':org_id' => $orgId]);
        $opportunities = [];

        foreach ($highEmitting as $item) {
            $emissions = (float)$item['category_emissions'];
            $catCode = $item['category_code'];
            $domCode = $item['domain_code'];

            if (str_contains($catCode, 'elec') || str_contains($catCode, 'grid')) {
                $potentialSavings = round($emissions * 0.25, 4); // 25% realistic reduction
                $opportunities[] = [
                    'title'                     => 'Rooftop Solar PV & LED Energy Efficiency Optimization',
                    'domain'                    => $item['domain_name'],
                    'domain_code'               => $domCode,
                    'facility_id'               => (int)$item['facility_id'],
                    'facility_name'             => $item['facility_name'],
                    'activity_category_id'      => (int)$item['category_id'],
                    'category_name'             => $item['category_name'],
                    'supporting_data'           => [
                        'current_emissions_tco2e' => $emissions,
                        'current_consumption'    => (float)$item['total_quantity'],
                        'unit'                   => $item['normalized_unit'],
                    ],
                    'reason_for_recommendation' => "Grid electricity accounts for {$emissions} tCO2e at {$item['facility_name']}. On-site distributed solar and automated building controls yield high decarbonization ROI.",
                    'suggested_action'          => 'Deploy 50kW-150kW rooftop solar array and implement smart building energy management systems (BEMS).',
                    'potential_reduction_tco2e' => $potentialSavings,
                    'estimation_assumptions'    => 'Assumes 25% grid electricity replacement based on ASHRAE Level II solar feasibility benchmarks.',
                    'priority'                  => $emissions > 5.0 ? 'critical' : 'high',
                    'type'                      => 'recommendation',
                    'status'                    => 'identified',
                ];
            } elseif (str_contains($catCode, 'diesel') || str_contains($catCode, 'fuel')) {
                $potentialSavings = round($emissions * 0.20, 4);
                $opportunities[] = [
                    'title'                     => 'Diesel Generator Hybridization & Operational Efficiency',
                    'domain'                    => $item['domain_name'],
                    'domain_code'               => $domCode,
                    'facility_id'               => (int)$item['facility_id'],
                    'facility_name'             => $item['facility_name'],
                    'activity_category_id'      => (int)$item['category_id'],
                    'category_name'             => $item['category_name'],
                    'supporting_data'           => [
                        'current_emissions_tco2e' => $emissions,
                        'current_consumption'    => (float)$item['total_quantity'],
                        'unit'                   => $item['normalized_unit'],
                    ],
                    'reason_for_recommendation' => "Stationary fuel combustion generates {$emissions} tCO2e. Generator optimization and battery energy storage reduce runtime hours.",
                    'suggested_action'          => 'Integrate lithium-ion battery energy storage system (BESS) for peak shaving and install automated generator control modules.',
                    'potential_reduction_tco2e' => $potentialSavings,
                    'estimation_assumptions'    => 'Assumes 20% runtime reduction through peak shaving and hybrid battery cycling.',
                    'priority'                  => 'high',
                    'type'                      => 'recommendation',
                    'status'                    => 'identified',
                ];
            } elseif (str_contains($catCode, 'gas') || str_contains($catCode, 'n2o')) {
                $potentialSavings = round($emissions * 0.40, 4);
                $opportunities[] = [
                    'title'                     => 'Medical Gas Destruction & Anesthetic Gas Scavenging',
                    'domain'                    => $item['domain_name'],
                    'domain_code'               => $domCode,
                    'facility_id'               => (int)$item['facility_id'],
                    'facility_name'             => $item['facility_name'],
                    'activity_category_id'      => (int)$item['category_id'],
                    'category_name'             => $item['category_name'],
                    'supporting_data'           => [
                        'current_emissions_tco2e' => $emissions,
                        'current_consumption'    => (float)$item['total_quantity'],
                        'unit'                   => $item['normalized_unit'],
                    ],
                    'reason_for_recommendation' => "Nitrous oxide has a high global warming potential (GWP 273). Uncaptured anesthetic exhaust represents substantial Scope 1 emissions.",
                    'suggested_action'          => 'Install catalytic cracking units on operating theater scavenging systems to decompose N2O into benign N2 and O2.',
                    'potential_reduction_tco2e' => $potentialSavings,
                    'estimation_assumptions'    => 'Assumes 40% capture and catalytic decomposition efficiency based on NHS Net Zero clinical case studies.',
                    'priority'                  => 'critical',
                    'type'                      => 'recommendation',
                    'status'                    => 'identified',
                ];
            } elseif (str_contains($catCode, 'waste')) {
                $potentialSavings = round($emissions * 0.30, 4);
                $opportunities[] = [
                    'title'                     => 'Organic Waste Segregation & Aerobic Composting Diversion',
                    'domain'                    => $item['domain_name'],
                    'domain_code'               => $domCode,
                    'facility_id'               => (int)$item['facility_id'],
                    'facility_name'             => $item['facility_name'],
                    'activity_category_id'      => (int)$item['category_id'],
                    'category_name'             => $item['category_name'],
                    'supporting_data'           => [
                        'current_emissions_tco2e' => $emissions,
                        'current_consumption'    => (float)$item['total_quantity'],
                        'unit'                   => $item['normalized_unit'],
                    ],
                    'reason_for_recommendation' => "Landfill waste generates anaerobic methane. Diverting organic waste to composting prevents fugitive methane release.",
                    'suggested_action'          => 'Institute source segregation in cafeterias and ward pantries; partner with authorized composting facilities.',
                    'potential_reduction_tco2e' => $potentialSavings,
                    'estimation_assumptions'    => 'Assumes 30% organic fraction diversion from municipal landfill disposal.',
                    'priority'                  => 'medium',
                    'type'                      => 'recommendation',
                    'status'                    => 'identified',
                ];
            }
        }

        return [
            'total_identified' => count($opportunities),
            'disclaimer'       => 'Opportunities are analytical recommendations derived from activity data benchmarks and do not represent verified or achieved reductions.',
            'opportunities'    => $opportunities,
        ];
    }
}
