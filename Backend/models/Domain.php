<?php
declare(strict_types=1);

namespace EcoPulse\Models;

use EcoPulse\Core\Database;

class Domain
{
    public static function listAll(): array
    {
        $db = Database::getInstance();
        return $db->fetchAll("SELECT * FROM `domains` WHERE `is_active` = 1 ORDER BY `sort_order` ASC");
    }

    public static function findByCode(string $code): ?array
    {
        $db = Database::getInstance();
        return $db->fetchOne("SELECT * FROM `domains` WHERE `code` = :code LIMIT 1", [':code' => $code]);
    }

    public static function findById(int $id): ?array
    {
        $db = Database::getInstance();
        return $db->fetchOne("SELECT * FROM `domains` WHERE `id` = :id LIMIT 1", [':id' => $id]);
    }

    public static function getActivityCategories(int $domainId): array
    {
        $db = Database::getInstance();
        return $db->fetchAll(
            "SELECT * FROM `activity_categories` WHERE `domain_id` = :did AND `is_active` = 1 ORDER BY `scope` ASC, `id` ASC",
            [':did' => $domainId]
        );
    }
}
