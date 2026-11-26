<?php
declare(strict_types=1);

namespace EcoPulse\Models;

use EcoPulse\Core\Database;

class ReductionInitiative
{
    /**
     * List reduction initiatives for an organization with joined names and filters.
     */
    public static function list(int $orgId, array $filters = [], int $page = 1, int $limit = 50): array
    {
        $db = Database::getInstance();
        $conditions = ['ri.`organization_id` = :org_id'];
        $params = [':org_id' => $orgId];

        if (!empty($filters['domain_profile_id'])) {
            $conditions[] = 'ri.`domain_profile_id` = :domain_profile_id';
            $params[':domain_profile_id'] = (int)$filters['domain_profile_id'];
        }

        if (!empty($filters['facility_id'])) {
            $conditions[] = 'ri.`facility_id` = :facility_id';
            $params[':facility_id'] = (int)$filters['facility_id'];
        }

        if (!empty($filters['status'])) {
            $conditions[] = 'ri.`status` = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['target_scope']) && $filters['target_scope'] !== 'all') {
            $conditions[] = '(ri.`target_scope` = :target_scope OR ri.`target_scope` = \'all\')';
            $params[':target_scope'] = $filters['target_scope'];
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(ri.`title` LIKE :search OR ri.`description` LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $whereClause = implode(' AND ', $conditions);
        $offset = max(0, ($page - 1) * $limit);

        $sql = "SELECT ri.*,
                       d.name as domain_name, d.code as domain_code,
                       f.name as facility_name,
                       ac.name as category_name, ac.code as category_code,
                       u.name as owner_name, u.email as owner_email,
                       creator.name as creator_name,
                       brp.name as baseline_period_name,
                       trp.name as target_period_name
                FROM `reduction_initiatives` ri
                JOIN `domain_profiles` dp ON dp.id = ri.domain_profile_id
                JOIN `domains` d ON d.id = dp.domain_id
                LEFT JOIN `facilities` f ON f.id = ri.facility_id
                LEFT JOIN `activity_categories` ac ON ac.id = ri.activity_category_id
                LEFT JOIN `users` u ON u.id = ri.owner_id
                LEFT JOIN `users` creator ON creator.id = ri.created_by
                LEFT JOIN `reporting_periods` brp ON brp.id = ri.baseline_reporting_period_id
                LEFT JOIN `reporting_periods` trp ON trp.id = ri.target_reporting_period_id
                WHERE {$whereClause}
                ORDER BY ri.id DESC
                LIMIT {$limit} OFFSET {$offset}";

        $rows = $db->fetchAll($sql, $params);

        $countSql = "SELECT COUNT(*) as total FROM `reduction_initiatives` ri WHERE {$whereClause}";
        $totalRow = $db->fetchOne($countSql, $params);
        $total = (int)($totalRow['total'] ?? 0);

        return [
            'initiatives' => $rows,
            'total'       => $total,
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => (int)ceil($total / max(1, $limit)),
        ];
    }

    /**
     * Find single initiative by ID and Organization with complete metadata.
     */
    public static function findByIdAndOrg(int $id, int $orgId): ?array
    {
        $db = Database::getInstance();
        $sql = "SELECT ri.*,
                       d.name as domain_name, d.code as domain_code,
                       f.name as facility_name, f.city as facility_city, f.country as facility_country,
                       ac.name as category_name, ac.code as category_code, ac.standard_unit as category_unit,
                       u.name as owner_name, u.email as owner_email,
                       creator.name as creator_name,
                       brp.name as baseline_period_name,
                       trp.name as target_period_name
                FROM `reduction_initiatives` ri
                JOIN `domain_profiles` dp ON dp.id = ri.domain_profile_id
                JOIN `domains` d ON d.id = dp.domain_id
                LEFT JOIN `facilities` f ON f.id = ri.facility_id
                LEFT JOIN `activity_categories` ac ON ac.id = ri.activity_category_id
                LEFT JOIN `users` u ON u.id = ri.owner_id
                LEFT JOIN `users` creator ON creator.id = ri.created_by
                LEFT JOIN `reporting_periods` brp ON brp.id = ri.baseline_reporting_period_id
                LEFT JOIN `reporting_periods` trp ON trp.id = ri.target_reporting_period_id
                WHERE ri.`id` = :id AND ri.`organization_id` = :org_id
                LIMIT 1";

        return $db->fetchOne($sql, [':id' => $id, ':org_id' => $orgId]);
    }

    /**
     * Create a new reduction initiative.
     */
    public static function create(array $data): int
    {
        $db = Database::getInstance();

        $baselineEmissions = isset($data['baseline_emissions']) ? (float)$data['baseline_emissions'] : null;
        $targetEmissions = isset($data['target_emissions']) ? (float)$data['target_emissions'] : null;
        $expectedReduction = isset($data['expected_reduction_percentage']) ? (float)$data['expected_reduction_percentage'] : null;

        // Auto-calculate expected reduction if baseline and target emissions are provided
        if ($baselineEmissions !== null && $targetEmissions !== null && $baselineEmissions > 0 && $expectedReduction === null) {
            $expectedReduction = round((($baselineEmissions - $targetEmissions) / $baselineEmissions) * 100.0, 2);
        }

        $estimatedTco2e = isset($data['estimated_annual_tco2e_reduction'])
            ? (float)$data['estimated_annual_tco2e_reduction']
            : ($baselineEmissions !== null && $targetEmissions !== null ? max(0, $baselineEmissions - $targetEmissions) : 0.0);

        return (int)$db->insert('reduction_initiatives', [
            'organization_id'                  => (int)$data['organization_id'],
            'domain_profile_id'                => (int)$data['domain_profile_id'],
            'facility_id'                      => !empty($data['facility_id']) ? (int)$data['facility_id'] : null,
            'activity_category_id'             => !empty($data['activity_category_id']) ? (int)$data['activity_category_id'] : null,
            'owner_id'                         => !empty($data['owner_id']) ? (int)$data['owner_id'] : null,
            'baseline_reporting_period_id'     => !empty($data['baseline_reporting_period_id']) ? (int)$data['baseline_reporting_period_id'] : null,
            'target_reporting_period_id'       => !empty($data['target_reporting_period_id']) ? (int)$data['target_reporting_period_id'] : null,
            'title'                            => trim($data['title']),
            'description'                      => trim($data['description'] ?? ''),
            'target_scope'                     => $data['target_scope'] ?? 'all',
            'baseline_year'                    => (int)($data['baseline_year'] ?? date('Y')),
            'target_completion_year'           => (int)($data['target_completion_year'] ?? date('Y')),
            'baseline_emissions'               => $baselineEmissions,
            'target_emissions'                 => $targetEmissions,
            'expected_reduction_percentage'    => $expectedReduction,
            'estimated_annual_tco2e_reduction' => $estimatedTco2e,
            'estimated_capex'                  => (float)($data['estimated_capex'] ?? 0.0),
            'currency'                         => $data['currency'] ?? 'USD',
            'start_date'                       => $data['start_date'] ?? null,
            'target_completion_date'           => $data['target_completion_date'] ?? null,
            'progress_percentage'              => (float)($data['progress_percentage'] ?? 0.0),
            'measured_reduction_tco2e'         => (float)($data['measured_reduction_tco2e'] ?? 0.0),
            'reduction_verification_status'    => $data['reduction_verification_status'] ?? 'unverified',
            'supporting_documents'             => !empty($data['supporting_documents']) ? json_encode($data['supporting_documents']) : null,
            'created_by'                       => (int)($data['created_by'] ?? 1),
            'status'                           => $data['status'] ?? 'proposed',
        ]);
    }

    /**
     * Update an initiative.
     */
    public static function update(int $id, int $orgId, array $data): bool
    {
        $db = Database::getInstance();
        $fields = [];
        $params = [];

        $allowed = [
            'facility_id', 'activity_category_id', 'owner_id', 'baseline_reporting_period_id',
            'target_reporting_period_id', 'title', 'description', 'target_scope', 'baseline_year',
            'target_completion_year', 'baseline_emissions', 'target_emissions',
            'expected_reduction_percentage', 'estimated_annual_tco2e_reduction', 'estimated_capex',
            'currency', 'start_date', 'target_completion_date', 'progress_percentage',
            'measured_reduction_tco2e', 'reduction_verification_status', 'supporting_documents', 'status'
        ];

        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $fields[$key] = $data[$key];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $count = $db->update(
            'reduction_initiatives',
            $fields,
            '`id` = :id AND `organization_id` = :org_id',
            [':id' => $id, ':org_id' => $orgId]
        );

        return $count > 0;
    }

    /**
     * Update status with validation.
     */
    public static function updateStatus(int $id, int $orgId, string $status): bool
    {
        $db = Database::getInstance();
        $validStatuses = ['proposed', 'approved', 'in_progress', 'on_hold', 'completed', 'cancelled'];
        if (!in_array($status, $validStatuses, true)) {
            return false;
        }

        $count = $db->update(
            'reduction_initiatives',
            ['status' => $status],
            '`id` = :id AND `organization_id` = :org_id',
            [':id' => $id, ':org_id' => $orgId]
        );

        return $count > 0;
    }
}
