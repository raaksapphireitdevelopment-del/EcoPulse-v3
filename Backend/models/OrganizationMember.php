<?php
declare(strict_types=1);

namespace EcoPulse\Models;

use EcoPulse\Core\Database;

class OrganizationMember
{
    public static function create(int $orgId, int $userId, string $role = 'organization_member', string $status = 'active'): int
    {
        $db = Database::getInstance();
        return (int)$db->insert('organization_members', [
            'organization_id' => $orgId,
            'user_id' => $userId,
            'org_role' => $role,
            'status' => $status,
        ]);
    }

    public static function findMembership(int $orgId, int $userId): ?array
    {
        $db = Database::getInstance();
        return $db->fetchOne(
            'SELECT * FROM `organization_members` WHERE `organization_id` = :oid AND `user_id` = :uid LIMIT 1',
            [':oid' => $orgId, ':uid' => $userId]
        );
    }
}
