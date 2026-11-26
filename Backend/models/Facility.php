<?php
declare(strict_types=1);

namespace EcoPulse\Models;

use EcoPulse\Core\Database;

class Facility
{
    public static function listByOrganization(
        int $orgId,
        ?int $domainProfileId = null,
        ?string $search = null,
        ?string $status = null,
        int $limit = 50,
        int $offset = 0
    ): array {
        $db = Database::getInstance();
        $conditions = ['f.`organization_id` = :org_id'];
        $params = [':org_id' => $orgId];

        if ($domainProfileId !== null && $domainProfileId > 0) {
            $conditions[] = 'f.`domain_profile_id` = :dpid';
            $params[':dpid'] = $domainProfileId;
        }

        if (!empty($status)) {
            $conditions[] = 'f.`status` = :status';
            $params[':status'] = $status;
        }

        if (!empty($search)) {
            $conditions[] = '(f.`name` LIKE :search OR f.`facility_code` LIKE :search OR f.`city` LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $whereClause = implode(' AND ', $conditions);

        $sql = "SELECT f.*, dp.profile_name as domain_profile_name, d.code as domain_code, d.name as domain_name, d.icon as domain_icon
                FROM `facilities` f
                LEFT JOIN `domain_profiles` dp ON dp.id = f.domain_profile_id
                LEFT JOIN `domains` d ON d.id = dp.domain_id
                WHERE {$whereClause}
                ORDER BY f.id DESC
                LIMIT {$limit} OFFSET {$offset}";

        $countSql = "SELECT COUNT(*) as total FROM `facilities` f WHERE {$whereClause}";
        $total = (int)($db->fetchOne($countSql, $params)['total'] ?? 0);

        return [
            'facilities' => $db->fetchAll($sql, $params),
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    public static function findByIdAndOrg(int $id, int $orgId): ?array
    {
        $db = Database::getInstance();
        $sql = "SELECT f.*, dp.profile_name as domain_profile_name, d.code as domain_code, d.name as domain_name
                FROM `facilities` f
                LEFT JOIN `domain_profiles` dp ON dp.id = f.domain_profile_id
                LEFT JOIN `domains` d ON d.id = dp.domain_id
                WHERE f.`id` = :id AND f.`organization_id` = :org_id
                LIMIT 1";
        return $db->fetchOne($sql, [':id' => $id, ':org_id' => $orgId]);
    }

    public static function create(array $data): int
    {
        $db = Database::getInstance();
        return (int)$db->insert('facilities', $data);
    }

    public static function update(int $id, int $orgId, array $data): int
    {
        $db = Database::getInstance();
        return $db->update('facilities', $data, '`id` = :id AND `organization_id` = :org_id', [
            ':id' => $id,
            ':org_id' => $orgId,
        ]);
    }
}
