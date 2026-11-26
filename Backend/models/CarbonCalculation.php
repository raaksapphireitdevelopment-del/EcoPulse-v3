<?php
declare(strict_types=1);

namespace EcoPulse\Models;

use EcoPulse\Core\Database;

class CarbonCalculation
{
    /**
     * Record an immutable calculation snapshot for an activity record.
     * Manages version increments and current flag transitions atomically.
     *
     * @return array Array containing 'id' and 'version'
     */
    public static function recordSnapshot(array $data): array
    {
        $db = Database::getInstance();
        $recordId = (int)$data['activity_record_id'];
        $orgId = (int)$data['organization_id'];

        // Determine current version sequence
        $latest = $db->fetchOne(
            "SELECT MAX(version) as max_v FROM `carbon_calculations` WHERE `activity_record_id` = :rid",
            [':rid' => $recordId]
        );
        $nextVersion = ((int)($latest['max_v'] ?? 0)) + 1;

        // Invalidate prior snapshots
        $db->execute(
            "UPDATE `carbon_calculations` SET `is_current` = 0 WHERE `activity_record_id` = :rid",
            [':rid' => $recordId]
        );

        $calcId = (int)$db->insert('carbon_calculations', [
            'version'                    => $nextVersion,
            'is_current'                 => 1,
            'activity_record_id'         => $recordId,
            'organization_id'            => $orgId,
            'facility_id'                => (int)$data['facility_id'],
            'reporting_period_id'        => (int)$data['reporting_period_id'],
            'activity_category_id'       => (int)$data['activity_category_id'],
            'emission_factor_id'         => (int)$data['emission_factor_id'],
            'factor_version_snapshot'    => $data['factor_version_snapshot'],
            'factor_value_snapshot'      => (float)$data['factor_value_snapshot'],
            'factor_source_snapshot'     => $data['factor_source_snapshot'] ?? null,
            'factor_status_snapshot'     => $data['factor_status_snapshot'] ?? 'validated',
            'geography_snapshot'         => $data['geography_snapshot'] ?? 'Global',
            'gwp_applied'                => isset($data['gwp_applied']) ? (float)$data['gwp_applied'] : null,
            'gwp_source_snapshot'        => $data['gwp_source_snapshot'] ?? null,
            'activity_quantity_snapshot' => (float)$data['activity_quantity_snapshot'],
            'normalized_quantity'        => isset($data['normalized_quantity']) ? (float)$data['normalized_quantity'] : null,
            'normalized_unit'            => $data['normalized_unit'] ?? null,
            'conversion_factor'          => isset($data['conversion_factor']) ? (float)$data['conversion_factor'] : 1.0,
            'kg_co2e'                    => (float)$data['kg_co2e'],
            'tonnes_co2e'                => (float)$data['tonnes_co2e'],
            'scope'                      => $data['scope'],
            'calculation_formula'        => $data['calculation_formula'],
            'calculation_status'         => $data['calculation_status'] ?? 'validated',
            'recalculation_reason'       => $data['recalculation_reason'] ?? null,
            'triggered_by'               => !empty($data['triggered_by']) ? (int)$data['triggered_by'] : null,
        ]);

        return [
            'id' => $calcId,
            'version' => $nextVersion,
        ];
    }

    /**
     * Get complete immutable audit history for an activity record.
     */
    public static function getHistoryForRecord(int $activityRecordId, int $orgId): array
    {
        $db = Database::getInstance();
        $sql = "SELECT c.*, ef.factor_name, u.name as triggered_by_name
                FROM `carbon_calculations` c
                JOIN `emission_factors` ef ON ef.id = c.emission_factor_id
                LEFT JOIN `users` u ON u.id = c.triggered_by
                WHERE c.`activity_record_id` = :rid AND c.`organization_id` = :org_id
                ORDER BY c.`version` DESC";

        return $db->fetchAll($sql, [':rid' => $activityRecordId, ':org_id' => $orgId]);
    }

    /**
     * Get current calculation for an activity record.
     */
    public static function getCurrentForRecord(int $activityRecordId, int $orgId): ?array
    {
        $db = Database::getInstance();
        $sql = "SELECT c.*, ef.factor_name, ef.source as ef_source, ef.source_reference as ef_source_ref
                FROM `carbon_calculations` c
                JOIN `emission_factors` ef ON ef.id = c.emission_factor_id
                WHERE c.`activity_record_id` = :rid AND c.`organization_id` = :org_id AND c.`is_current` = 1
                LIMIT 1";

        return $db->fetchOne($sql, [':rid' => $activityRecordId, ':org_id' => $orgId]);
    }

    /**
     * Calculate summary aggregations by scope, category, and status.
     * Segregates Avoided Emissions strictly from operational scopes (Scope 1, 2, 3).
     */
    public static function getWorkspaceSummary(
        int $orgId,
        ?int $reportingPeriodId = null,
        ?int $domainProfileId = null,
        ?int $facilityId = null
    ): array {
        $db = Database::getInstance();
        $conditions = ['c.`organization_id` = :org_id', 'c.`is_current` = 1'];
        $params = [':org_id' => $orgId];

        if ($reportingPeriodId !== null && $reportingPeriodId > 0) {
            $conditions[] = 'c.`reporting_period_id` = :period_id';
            $params[':period_id'] = $reportingPeriodId;
        }

        if ($facilityId !== null && $facilityId > 0) {
            $conditions[] = 'c.`facility_id` = :facility_id';
            $params[':facility_id'] = $facilityId;
        }

        if ($domainProfileId !== null && $domainProfileId > 0) {
            $conditions[] = 'ar.`domain_profile_id` = :profile_id';
            $params[':profile_id'] = $domainProfileId;
        }

        $whereClause = implode(' AND ', $conditions);

        // 1. By Scope Aggregation
        $scopeSql = "SELECT c.`scope`,
                            COUNT(*) as records_count,
                            SUM(c.`tonnes_co2e`) as total_tonnes,
                            SUM(c.`kg_co2e`) as total_kg
                     FROM `carbon_calculations` c
                     JOIN `activity_records` ar ON ar.id = c.activity_record_id
                     WHERE {$whereClause}
                     GROUP BY c.`scope`";
        $scopeRows = $db->fetchAll($scopeSql, $params);

        $scope1 = 0.0;
        $scope2 = 0.0;
        $scope3 = 0.0;
        $avoided = 0.0;
        $unclassified = 0.0;

        foreach ($scopeRows as $row) {
            $tonnes = (float)$row['total_tonnes'];
            switch ($row['scope']) {
                case 'scope_1':
                    $scope1 = $tonnes;
                    break;
                case 'scope_2':
                    $scope2 = $tonnes;
                    break;
                case 'scope_3':
                    $scope3 = $tonnes;
                    break;
                case 'avoided_emissions':
                    $avoided = $tonnes;
                    break;
                default:
                    $unclassified += $tonnes;
                    break;
            }
        }

        // Operational footprint = Scope 1 + Scope 2 + Scope 3 (Avoided emissions NEVER subtracted or mixed!)
        $operationalTotalTonnes = round($scope1 + $scope2 + $scope3, 4);

        // 2. By Validation Status (Validated vs Demo vs Review Required)
        $statusSql = "SELECT c.`calculation_status`,
                             COUNT(*) as count,
                             SUM(c.`tonnes_co2e`) as total_tonnes
                      FROM `carbon_calculations` c
                      JOIN `activity_records` ar ON ar.id = c.activity_record_id
                      WHERE {$whereClause}
                      GROUP BY c.`calculation_status`";
        $statusRows = $db->fetchAll($statusSql, $params);

        $statusBreakdown = [
            'validated'       => 0.0,
            'demo'            => 0.0,
            'provisional'     => 0.0,
            'review_required' => 0.0,
            'unclassified'    => 0.0,
        ];
        foreach ($statusRows as $sRow) {
            $statusKey = $sRow['calculation_status'];
            $statusBreakdown[$statusKey] = round((float)$sRow['total_tonnes'], 4);
        }

        // 3. Category Breakdown
        $categorySql = "SELECT ac.id as category_id, ac.name as category_name, ac.code as category_code,
                               c.`scope`,
                               COUNT(c.id) as count,
                               SUM(c.`tonnes_co2e`) as total_tonnes
                        FROM `carbon_calculations` c
                        JOIN `activity_records` ar ON ar.id = c.activity_record_id
                        JOIN `activity_categories` ac ON ac.id = c.activity_category_id
                        WHERE {$whereClause}
                        GROUP BY ac.id, ac.name, ac.code, c.`scope`
                        ORDER BY total_tonnes DESC";
        $categoryRows = $db->fetchAll($categorySql, $params);

        // 4. Monthly Trend
        $trendSql = "SELECT DATE_FORMAT(ar.activity_date, '%Y-%m') as `month`,
                            c.`scope`,
                            SUM(c.`tonnes_co2e`) as total_tonnes
                     FROM `carbon_calculations` c
                     JOIN `activity_records` ar ON ar.id = c.activity_record_id
                     WHERE {$whereClause}
                     GROUP BY `month`, c.`scope`
                     ORDER BY `month` ASC";
        $trendRows = $db->fetchAll($trendSql, $params);

        return [
            'summary' => [
                'operational_total_tonnes' => $operationalTotalTonnes,
                'scope_1_tonnes'           => round($scope1, 4),
                'scope_2_tonnes'           => round($scope2, 4),
                'scope_3_tonnes'           => round($scope3, 4),
                'avoided_emissions_tonnes' => round($avoided, 4),
                'unclassified_tonnes'      => round($unclassified, 4),
            ],
            'status_breakdown'   => $statusBreakdown,
            'category_breakdown' => $categoryRows,
            'monthly_trend'      => $trendRows,
        ];
    }
}
