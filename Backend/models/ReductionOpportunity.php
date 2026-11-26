<?php
declare(strict_types=1);

namespace EcoPulse\Models;

use EcoPulse\Core\Database;

class ReductionOpportunity
{
    /**
     * List reduction opportunities for an organization.
     */
    public static function list(int $orgId, array $filters = []): array
    {
        $db = Database::getInstance();
        $conditions = ['ro.`organization_id` = :org_id'];
        $params = [':org_id' => $orgId];

        if (!empty($filters['domain_code'])) {
            $conditions[] = 'ro.`domain_code` = :domain_code';
            $params[':domain_code'] = $filters['domain_code'];
        }

        if (!empty($filters['priority'])) {
            $conditions[] = 'ro.`priority` = :priority';
            $params[':priority'] = $filters['priority'];
        }

        if (!empty($filters['status'])) {
            $conditions[] = 'ro.`status` = :status';
            $params[':status'] = $filters['status'];
        }

        $whereClause = implode(' AND ', $conditions);
        $sql = "SELECT ro.*,
                       f.name as facility_name,
                       ac.name as category_name
                FROM `reduction_opportunities` ro
                LEFT JOIN `facilities` f ON f.id = ro.facility_id
                LEFT JOIN `activity_categories` ac ON ac.id = ro.activity_category_id
                WHERE {$whereClause}
                ORDER BY FIELD(ro.`priority`, 'critical', 'high', 'medium', 'low'), ro.`potential_reduction_tco2e` DESC";

        $rows = $db->fetchAll($sql, $params);
        return array_map(function ($row) {
            if (!empty($row['supporting_data']) && is_string($row['supporting_data'])) {
                $row['supporting_data'] = json_decode($row['supporting_data'], true);
            }
            return $row;
        }, $rows);
    }

    /**
     * Save an opportunity.
     */
    public static function save(array $data): int
    {
        $db = Database::getInstance();
        return (int)$db->insert('reduction_opportunities', [
            'organization_id'           => (int)$data['organization_id'],
            'domain_profile_id'         => !empty($data['domain_profile_id']) ? (int)$data['domain_profile_id'] : null,
            'activity_category_id'      => !empty($data['activity_category_id']) ? (int)$data['activity_category_id'] : null,
            'facility_id'               => !empty($data['facility_id']) ? (int)$data['facility_id'] : null,
            'title'                     => trim($data['title']),
            'domain_code'               => $data['domain_code'],
            'supporting_data'           => !empty($data['supporting_data']) ? json_encode($data['supporting_data']) : null,
            'reason_for_recommendation' => trim($data['reason_for_recommendation']),
            'suggested_action'          => trim($data['suggested_action']),
            'potential_reduction_tco2e' => isset($data['potential_reduction_tco2e']) ? (float)$data['potential_reduction_tco2e'] : null,
            'estimation_assumptions'    => !empty($data['estimation_assumptions']) ? trim($data['estimation_assumptions']) : null,
            'priority'                  => $data['priority'] ?? 'medium',
            'status'                    => $data['status'] ?? 'identified',
        ]);
    }

    /**
     * Update opportunity status.
     */
    public static function updateStatus(int $id, int $orgId, string $status): bool
    {
        $db = Database::getInstance();
        $count = $db->update(
            'reduction_opportunities',
            ['status' => $status],
            '`id` = :id AND `organization_id` = :org_id',
            [':id' => $id, ':org_id' => $orgId]
        );
        return $count > 0;
    }
}
