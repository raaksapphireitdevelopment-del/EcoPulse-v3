<?php
declare(strict_types=1);

namespace EcoPulse\Models;

use EcoPulse\Core\Database;

class EmissionFactor
{
    /**
     * List emission factors with flexible filtering.
     */
    public static function list(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $db = Database::getInstance();
        $conditions = ['1=1'];
        $params = [];

        if (!empty($filters['activity_category_id'])) {
            $conditions[] = 'ef.`activity_category_id` = :cat_id';
            $params[':cat_id'] = (int)$filters['activity_category_id'];
        }

        if (!empty($filters['scope'])) {
            $conditions[] = 'ef.`scope` = :scope';
            $params[':scope'] = $filters['scope'];
        }

        if (!empty($filters['geography'])) {
            $conditions[] = 'ef.`geography` = :geography';
            $params[':geography'] = $filters['geography'];
        }

        if (!empty($filters['validation_status'])) {
            $conditions[] = 'ef.`validation_status` = :validation_status';
            $params[':validation_status'] = $filters['validation_status'];
        }

        if (isset($filters['is_demo']) && $filters['is_demo'] !== '') {
            $conditions[] = 'ef.`is_demo` = :is_demo';
            $params[':is_demo'] = (int)$filters['is_demo'];
        }

        if (!empty($filters['domain_id'])) {
            $conditions[] = 'ac.`domain_id` = :domain_id';
            $params[':domain_id'] = (int)$filters['domain_id'];
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(ef.`factor_name` LIKE :search OR ef.`source` LIKE :search OR ef.`source_reference` LIKE :search OR ef.`version` LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $whereClause = implode(' AND ', $conditions);

        $sql = "SELECT ef.*, ac.name as category_name, ac.code as category_code, ac.standard_unit as category_standard_unit, d.name as domain_name
                FROM `emission_factors` ef
                LEFT JOIN `activity_categories` ac ON ac.id = ef.activity_category_id
                LEFT JOIN `domains` d ON d.id = ac.domain_id
                WHERE {$whereClause}
                ORDER BY ef.id DESC
                LIMIT {$limit} OFFSET {$offset}";

        $countSql = "SELECT COUNT(*) as total
                     FROM `emission_factors` ef
                     LEFT JOIN `activity_categories` ac ON ac.id = ef.activity_category_id
                     WHERE {$whereClause}";

        $total = (int)($db->fetchOne($countSql, $params)['total'] ?? 0);

        return [
            'factors' => $db->fetchAll($sql, $params),
            'total'   => $total,
            'limit'   => $limit,
            'offset'  => $offset,
        ];
    }

    /**
     * Find factor by ID.
     */
    public static function findById(int $id): ?array
    {
        $db = Database::getInstance();
        $sql = "SELECT ef.*, ac.name as category_name, ac.code as category_code, ac.standard_unit as category_standard_unit, d.name as domain_name
                FROM `emission_factors` ef
                LEFT JOIN `activity_categories` ac ON ac.id = ef.activity_category_id
                LEFT JOIN `domains` d ON d.id = ac.domain_id
                WHERE ef.`id` = :id LIMIT 1";

        return $db->fetchOne($sql, [':id' => $id]);
    }

    /**
     * Create a new emission factor.
     */
    public static function create(array $data): int
    {
        $db = Database::getInstance();
        return (int)$db->insert('emission_factors', [
            'activity_category_id'    => (int)$data['activity_category_id'],
            'factor_name'             => $data['factor_name'],
            'factor_value'            => (float)$data['factor_value'],
            'activity_unit'           => $data['activity_unit'],
            'co2e_unit'               => $data['co2e_unit'] ?? 'kgCO2e',
            'scope'                   => $data['scope'],
            'gas_detail'              => $data['gas_detail'] ?? 'CO2e',
            'gwp_value'               => !empty($data['gwp_value']) ? (float)$data['gwp_value'] : null,
            'gwp_source'              => $data['gwp_source'] ?? null,
            'geography'               => $data['geography'] ?? 'Global',
            'source'                  => $data['source'],
            'publishing_organization' => $data['publishing_organization'] ?? null,
            'source_reference'        => $data['source_reference'] ?? null,
            'version'                 => $data['version'] ?? '1.0',
            'effective_date'          => $data['effective_date'] ?? date('Y-01-01'),
            'expiry_date'             => !empty($data['expiry_date']) ? $data['expiry_date'] : null,
            'validation_status'       => $data['validation_status'] ?? 'draft',
            'is_demo'                 => !empty($data['is_demo']) ? 1 : 0,
        ]);
    }

    /**
     * Update factor details.
     */
    public static function update(int $id, array $data): bool
    {
        $db = Database::getInstance();
        $fields = [];
        $params = [':id' => $id];

        $allowed = [
            'factor_name', 'factor_value', 'activity_unit', 'co2e_unit', 'scope',
            'gas_detail', 'gwp_value', 'gwp_source', 'geography', 'source',
            'publishing_organization', 'source_reference', 'version', 'effective_date',
            'expiry_date', 'validation_status', 'is_demo'
        ];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "`{$field}` = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE `emission_factors` SET " . implode(', ', $fields) . " WHERE `id` = :id";
        return $db->execute($sql, $params) > 0;
    }

    /**
     * Update validation status.
     */
    public static function updateStatus(int $id, string $status): bool
    {
        $db = Database::getInstance();
        $sql = "UPDATE `emission_factors` SET `validation_status` = :status WHERE `id` = :id";
        return $db->execute($sql, [':status' => $status, ':id' => $id]) > 0;
    }

    /**
     * Find best matching factor for category, geography, and date.
     */
    public static function findApplicable(
        int $categoryId,
        ?string $geography = null,
        ?string $activityDate = null,
        bool $allowDemo = false
    ): ?array {
        $db = Database::getInstance();
        $conditions = ['ef.`activity_category_id` = :cat_id'];
        $params = [':cat_id' => $categoryId];

        if ($allowDemo) {
            $conditions[] = "ef.`validation_status` IN ('validated', 'verified', 'demo')";
        } else {
            $conditions[] = "ef.`validation_status` IN ('validated', 'verified')";
        }

        if (!empty($activityDate)) {
            $conditions[] = "ef.`effective_date` <= :act_date1 AND (ef.`expiry_date` IS NULL OR ef.`expiry_date` >= :act_date2)";
            $params[':act_date1'] = $activityDate;
            $params[':act_date2'] = $activityDate;
        }

        $whereClause = implode(' AND ', $conditions);

        // Preference ranking:
        // 1. Exact geography match
        // 2. 'Global'
        // 3. Demo factor last
        $geoParam = $geography ?? 'Global';
        $params[':target_geo'] = $geoParam;

        $sql = "SELECT ef.*, ac.name as category_name, ac.code as category_code, ac.standard_unit as category_standard_unit
                FROM `emission_factors` ef
                LEFT JOIN `activity_categories` ac ON ac.id = ef.activity_category_id
                WHERE {$whereClause}
                ORDER BY
                    CASE
                        WHEN ef.`geography` = :target_geo THEN 1
                        WHEN ef.`geography` = 'Global' THEN 2
                        ELSE 3
                    END ASC,
                    ef.`is_demo` ASC,
                    ef.`id` DESC
                LIMIT 1";

        return $db->fetchOne($sql, $params);
    }
}
