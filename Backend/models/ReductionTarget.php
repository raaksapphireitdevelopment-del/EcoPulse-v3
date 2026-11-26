<?php
declare(strict_types=1);

namespace EcoPulse\Models;

use EcoPulse\Core\Database;

class ReductionTarget
{
    /**
     * List all reduction targets for an organization with joined names.
     */
    public static function list(int $orgId): array
    {
        $db = Database::getInstance();
        $sql = "SELECT rt.*,
                       dp.profile_name as domain_profile_name, d.name as domain_name, d.code as domain_code,
                       f.name as facility_name,
                       ac.name as category_name,
                       brp.name as baseline_period_name,
                       trp.name as target_period_name,
                       u.name as creator_name
                FROM `reduction_targets` rt
                LEFT JOIN `domain_profiles` dp ON dp.id = rt.domain_profile_id
                LEFT JOIN `domains` d ON d.id = dp.domain_id
                LEFT JOIN `facilities` f ON f.id = rt.facility_id
                LEFT JOIN `activity_categories` ac ON ac.id = rt.activity_category_id
                LEFT JOIN `reporting_periods` brp ON brp.id = rt.baseline_reporting_period_id
                LEFT JOIN `reporting_periods` trp ON trp.id = rt.target_reporting_period_id
                LEFT JOIN `users` u ON u.id = rt.created_by
                WHERE rt.`organization_id` = :org_id
                ORDER BY rt.`target_year` ASC, rt.`id` DESC";

        $targets = $db->fetchAll($sql, [':org_id' => $orgId]);

        // Compute current progress metrics for each target
        return array_map(function ($target) use ($orgId) {
            return self::appendProgressMetrics($target, $orgId);
        }, $targets);
    }

    /**
     * Find single target by ID and Organization.
     */
    public static function findByIdAndOrg(int $id, int $orgId): ?array
    {
        $db = Database::getInstance();
        $sql = "SELECT rt.*,
                       dp.profile_name as domain_profile_name, d.name as domain_name, d.code as domain_code,
                       f.name as facility_name,
                       ac.name as category_name,
                       brp.name as baseline_period_name,
                       trp.name as target_period_name,
                       u.name as creator_name
                FROM `reduction_targets` rt
                LEFT JOIN `domain_profiles` dp ON dp.id = rt.domain_profile_id
                LEFT JOIN `domains` d ON d.id = dp.domain_id
                LEFT JOIN `facilities` f ON f.id = rt.facility_id
                LEFT JOIN `activity_categories` ac ON ac.id = rt.activity_category_id
                LEFT JOIN `reporting_periods` brp ON brp.id = rt.baseline_reporting_period_id
                LEFT JOIN `reporting_periods` trp ON trp.id = rt.target_reporting_period_id
                LEFT JOIN `users` u ON u.id = rt.created_by
                WHERE rt.`id` = :id AND rt.`organization_id` = :org_id
                LIMIT 1";

        $target = $db->fetchOne($sql, [':id' => $id, ':org_id' => $orgId]);
        if (!$target) {
            return null;
        }

        return self::appendProgressMetrics($target, $orgId);
    }

    /**
     * Create a reduction target.
     */
    public static function create(array $data): int
    {
        $db = Database::getInstance();

        $baseline = (float)$data['baseline_emissions_tco2e'];
        $target = (float)$data['target_emissions_tco2e'];
        $reductionPct = isset($data['target_reduction_percentage'])
            ? (float)$data['target_reduction_percentage']
            : ($baseline > 0 ? round((($baseline - $target) / $baseline) * 100.0, 2) : 0.0);

        return (int)$db->insert('reduction_targets', [
            'organization_id'              => (int)$data['organization_id'],
            'title'                        => trim($data['title']),
            'target_level'                 => $data['target_level'] ?? 'organization',
            'domain_profile_id'            => !empty($data['domain_profile_id']) ? (int)$data['domain_profile_id'] : null,
            'facility_id'                  => !empty($data['facility_id']) ? (int)$data['facility_id'] : null,
            'scope'                        => $data['scope'] ?? 'all',
            'activity_category_id'         => !empty($data['activity_category_id']) ? (int)$data['activity_category_id'] : null,
            'baseline_reporting_period_id' => !empty($data['baseline_reporting_period_id']) ? (int)$data['baseline_reporting_period_id'] : null,
            'baseline_year'                => !empty($data['baseline_year']) ? (int)$data['baseline_year'] : null,
            'target_reporting_period_id'   => !empty($data['target_reporting_period_id']) ? (int)$data['target_reporting_period_id'] : null,
            'target_year'                  => (int)$data['target_year'],
            'baseline_emissions_tco2e'     => $baseline,
            'target_emissions_tco2e'       => $target,
            'target_reduction_percentage'  => $reductionPct,
            'current_emissions_tco2e'      => (float)($data['current_emissions_tco2e'] ?? $baseline),
            'status'                       => $data['status'] ?? 'active',
            'notes'                        => !empty($data['notes']) ? trim($data['notes']) : null,
            'created_by'                   => (int)$data['created_by'],
        ]);
    }

    /**
     * Update target parameters.
     */
    public static function update(int $id, int $orgId, array $data): bool
    {
        $db = Database::getInstance();
        $allowed = [
            'title', 'target_level', 'domain_profile_id', 'facility_id', 'scope',
            'activity_category_id', 'baseline_reporting_period_id', 'baseline_year',
            'target_reporting_period_id', 'target_year', 'baseline_emissions_tco2e',
            'target_emissions_tco2e', 'target_reduction_percentage', 'current_emissions_tco2e',
            'status', 'notes'
        ];

        $fields = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $fields[$key] = $data[$key];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $count = $db->update(
            'reduction_targets',
            $fields,
            '`id` = :id AND `organization_id` = :org_id',
            [':id' => $id, ':org_id' => $orgId]
        );

        return $count > 0;
    }

    /**
     * Delete target.
     */
    public static function delete(int $id, int $orgId): bool
    {
        $db = Database::getInstance();
        $count = $db->delete(
            'reduction_targets',
            '`id` = :id AND `organization_id` = :org_id',
            [':id' => $id, ':org_id' => $orgId]
        );

        return $count > 0;
    }

    /**
     * Helper to compute progress against current calculation snapshots.
     */
    private static function appendProgressMetrics(array $target, int $orgId): array
    {
        $baseline = (float)$target['baseline_emissions_tco2e'];
        $goal = (float)$target['target_emissions_tco2e'];
        $current = (float)$target['current_emissions_tco2e'];

        // If current emissions were not set explicitly or we derive from database:
        // Remaining reduction requirement:
        $remainingReduction = max(0.0, $current - $goal);

        // Progress percentage toward goal:
        // Total reduction required = baseline - goal
        $totalRequiredReduction = max(0.0001, $baseline - $goal);
        $achievedReduction = max(0.0, $baseline - $current);
        $progressPct = round(min(100.0, ($achievedReduction / $totalRequiredReduction) * 100.0), 2);

        $target['progress_percentage'] = $progressPct;
        $target['remaining_reduction_tco2e'] = round($remainingReduction, 4);
        $target['achieved_reduction_tco2e'] = round($achievedReduction, 4);

        return $target;
    }
}
