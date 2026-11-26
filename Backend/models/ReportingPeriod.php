<?php
declare(strict_types=1);

namespace EcoPulse\Models;

use EcoPulse\Core\Database;

class ReportingPeriod
{
    public static function listByOrganization(int $orgId): array
    {
        $db = Database::getInstance();
        $sql = "SELECT rp.*,
                       (SELECT COUNT(*) FROM `activity_records` ar WHERE ar.`reporting_period_id` = rp.`id`) as activity_records_count
                FROM `reporting_periods` rp
                WHERE rp.`organization_id` = :org_id
                ORDER BY rp.`start_date` DESC";
        return $db->fetchAll($sql, [':org_id' => $orgId]);
    }

    public static function findByIdAndOrg(int $id, int $orgId): ?array
    {
        $db = Database::getInstance();
        return $db->fetchOne(
            "SELECT * FROM `reporting_periods` WHERE `id` = :id AND `organization_id` = :org_id LIMIT 1",
            [':id' => $id, ':org_id' => $orgId]
        );
    }

    public static function findDuplicate(int $orgId, string $startDate, string $endDate, ?int $excludeId = null): ?array
    {
        $db = Database::getInstance();
        $sql = "SELECT `id`, `name` FROM `reporting_periods` 
                WHERE `organization_id` = :org_id 
                  AND `start_date` = :start_date 
                  AND `end_date` = :end_date";
        $params = [
            ':org_id' => $orgId,
            ':start_date' => $startDate,
            ':end_date' => $endDate,
        ];
        if ($excludeId !== null) {
            $sql .= " AND `id` != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }
        $sql .= " LIMIT 1";

        return $db->fetchOne($sql, $params);
    }

    public static function create(array $data): int
    {
        $db = Database::getInstance();
        return (int)$db->insert('reporting_periods', $data);
    }

    public static function update(int $id, int $orgId, array $data): int
    {
        $db = Database::getInstance();
        return $db->update('reporting_periods', $data, '`id` = :id AND `organization_id` = :org_id', [
            ':id' => $id,
            ':org_id' => $orgId,
        ]);
    }
}
