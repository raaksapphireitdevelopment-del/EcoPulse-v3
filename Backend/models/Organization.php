<?php
declare(strict_types=1);

namespace EcoPulse\Models;

use EcoPulse\Core\Database;

class Organization
{
    public static function findById(int $id): ?array
    {
        $db = Database::getInstance();
        return $db->fetchOne('SELECT * FROM `organizations` WHERE `id` = :id LIMIT 1', [':id' => $id]);
    }

    public static function create(array $data): int
    {
        $db = Database::getInstance();
        return (int)$db->insert('organizations', $data);
    }

    public static function update(int $id, array $data): int
    {
        $db = Database::getInstance();
        return $db->update('organizations', $data, '`id` = :id', [':id' => $id]);
    }

    public static function getEnabledDomains(int $orgId): array
    {
        $db = Database::getInstance();
        $sql = 'SELECT dp.id as domain_profile_id, dp.profile_name, dp.base_year, dp.config_settings, dp.status,
                       d.id as domain_id, d.code, d.name as domain_name, d.description, d.icon
                FROM `domain_profiles` dp
                INNER JOIN `domains` d ON d.id = dp.domain_id
                WHERE dp.organization_id = :org_id AND dp.status = "active"
                ORDER BY d.sort_order ASC';
        return $db->fetchAll($sql, [':org_id' => $orgId]);
    }
}
