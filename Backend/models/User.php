<?php
declare(strict_types=1);

namespace EcoPulse\Models;

use EcoPulse\Core\Database;

class User
{
    public static function findById(int $id): ?array
    {
        $db = Database::getInstance();
        return $db->fetchOne('SELECT * FROM `users` WHERE `id` = :id LIMIT 1', [':id' => $id]);
    }

    public static function findByEmail(string $email): ?array
    {
        $db = Database::getInstance();
        return $db->fetchOne('SELECT * FROM `users` WHERE `email` = :email LIMIT 1', [':email' => strtolower(trim($email))]);
    }

    public static function create(array $data): int
    {
        $db = Database::getInstance();
        return (int)$db->insert('users', $data);
    }

    public static function getUserOrganizations(int $userId): array
    {
        $db = Database::getInstance();
        $user = self::findById($userId);
        if ($user && $user['system_role'] === 'super_admin') {
            return $db->fetchAll(
                'SELECT id, name, industry_primary, country, city, status, "super_admin" as org_role, "active" as membership_status
                 FROM `organizations`
                 WHERE `status` = "active"
                 ORDER BY id ASC'
            );
        }

        $sql = 'SELECT o.id, o.name, o.industry_primary, o.country, o.city, o.status, om.org_role, om.status as membership_status
                FROM `organizations` o
                INNER JOIN `organization_members` om ON om.organization_id = o.id
                WHERE om.user_id = :uid AND om.status = "active"
                ORDER BY o.name ASC';
        return $db->fetchAll($sql, [':uid' => $userId]);
    }
}
