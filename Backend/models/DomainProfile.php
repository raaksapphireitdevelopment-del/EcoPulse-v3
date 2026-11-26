<?php
declare(strict_types=1);

namespace EcoPulse\Models;

use EcoPulse\Core\Database;

class DomainProfile
{
    public static function listByOrganization(int $orgId): array
    {
        $db = Database::getInstance();
        $sql = "SELECT dp.*, d.code as domain_code, d.name as domain_name, d.description as domain_description, d.icon as domain_icon,
                       (SELECT COUNT(*) FROM `facilities` f WHERE f.`domain_profile_id` = dp.`id`) as facilities_count
                FROM `domain_profiles` dp
                INNER JOIN `domains` d ON d.id = dp.domain_id
                WHERE dp.`organization_id` = :org_id
                ORDER BY d.`sort_order` ASC";
        return $db->fetchAll($sql, [':org_id' => $orgId]);
    }

    public static function findByOrgAndDomainCode(int $orgId, string $domainCode): ?array
    {
        $db = Database::getInstance();
        $sql = "SELECT dp.*, d.id as domain_id, d.code as domain_code, d.name as domain_name, d.description as domain_description, d.icon as domain_icon
                FROM `domain_profiles` dp
                INNER JOIN `domains` d ON d.id = dp.domain_id
                WHERE dp.`organization_id` = :org_id AND d.`code` = :code LIMIT 1";
        return $db->fetchOne($sql, [':org_id' => $orgId, ':code' => $domainCode]);
    }

    public static function enable(int $orgId, int $domainId, string $profileName, ?int $baseYear = null, ?array $settings = null): int
    {
        $db = Database::getInstance();
        $baseYear = $baseYear ?? (int)date('Y');
        $jsonSettings = $settings ? json_encode($settings) : json_encode(['enabled_at' => gmdate('Y-m-d H:i:s')]);

        // If exists, activate; if not, insert
        $existing = $db->fetchOne(
            "SELECT `id` FROM `domain_profiles` WHERE `organization_id` = :oid AND `domain_id` = :did LIMIT 1",
            [':oid' => $orgId, ':did' => $domainId]
        );

        if ($existing) {
            $db->update('domain_profiles', [
                'profile_name' => $profileName,
                'base_year' => $baseYear,
                'status' => 'active',
            ], '`id` = :id', [':id' => $existing['id']]);
            return (int)$existing['id'];
        }

        return (int)$db->insert('domain_profiles', [
            'organization_id' => $orgId,
            'domain_id' => $domainId,
            'profile_name' => $profileName,
            'base_year' => $baseYear,
            'config_settings' => $jsonSettings,
            'status' => 'active',
        ]);
    }

    public static function disable(int $id, int $orgId): int
    {
        $db = Database::getInstance();
        return $db->update('domain_profiles', [
            'status' => 'archived',
        ], '`id` = :id AND `organization_id` = :oid', [
            ':id' => $id,
            ':oid' => $orgId,
        ]);
    }
}
