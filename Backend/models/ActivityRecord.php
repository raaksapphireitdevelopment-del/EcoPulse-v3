<?php
declare(strict_types=1);

namespace EcoPulse\Models;

use EcoPulse\Core\Database;
use InvalidArgumentException;

class ActivityRecord
{
    /**
     * List activity records for an organization with tenant isolation and join details.
     */
    public static function list(int $orgId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $db = Database::getInstance();
        $conditions = ['ar.`organization_id` = :org_id'];
        $params = [':org_id' => $orgId];

        if (!empty($filters['facility_id'])) {
            $conditions[] = 'ar.`facility_id` = :facility_id';
            $params[':facility_id'] = (int)$filters['facility_id'];
        }

        if (!empty($filters['reporting_period_id'])) {
            $conditions[] = 'ar.`reporting_period_id` = :period_id';
            $params[':period_id'] = (int)$filters['reporting_period_id'];
        }

        if (!empty($filters['domain_profile_id'])) {
            $conditions[] = 'ar.`domain_profile_id` = :profile_id';
            $params[':profile_id'] = (int)$filters['domain_profile_id'];
        }

        if (!empty($filters['activity_category_id'])) {
            $conditions[] = 'ar.`activity_category_id` = :category_id';
            $params[':category_id'] = (int)$filters['activity_category_id'];
        }

        if (!empty($filters['status'])) {
            $conditions[] = 'ar.`status` = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['start_date'])) {
            $conditions[] = 'ar.`activity_date` >= :start_date';
            $params[':start_date'] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $conditions[] = 'ar.`activity_date` <= :end_date';
            $params[':end_date'] = $filters['end_date'];
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(ar.`description` LIKE :search OR ac.`name` LIKE :search OR f.`name` LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $whereClause = implode(' AND ', $conditions);

        $sql = "SELECT ar.*,
                       f.name as facility_name, f.city as facility_city, f.country as facility_country,
                       rp.name as period_name, rp.is_closed as period_locked, rp.status as period_status,
                       ac.name as category_name, ac.code as category_code, ac.scope as category_scope,
                       ac.standard_unit as category_standard_unit,
                       d.name as domain_name, d.code as domain_code,
                       u.name as creator_name,
                       calc.id as calculation_id, calc.tonnes_co2e, calc.kg_co2e, calc.scope as calc_scope,
                       calc.calculation_status, calc.version as calc_version
                FROM `activity_records` ar
                JOIN `facilities` f ON f.id = ar.facility_id
                JOIN `reporting_periods` rp ON rp.id = ar.reporting_period_id
                JOIN `activity_categories` ac ON ac.id = ar.activity_category_id
                JOIN `domains` d ON d.id = ac.domain_id
                LEFT JOIN `users` u ON u.id = ar.created_by
                LEFT JOIN `carbon_calculations` calc ON calc.activity_record_id = ar.id AND calc.is_current = 1
                WHERE {$whereClause}
                ORDER BY ar.activity_date DESC, ar.id DESC
                LIMIT {$limit} OFFSET {$offset}";

        $countSql = "SELECT COUNT(*) as total
                     FROM `activity_records` ar
                     JOIN `facilities` f ON f.id = ar.facility_id
                     JOIN `reporting_periods` rp ON rp.id = ar.reporting_period_id
                     JOIN `activity_categories` ac ON ac.id = ar.activity_category_id
                     WHERE {$whereClause}";

        $total = (int)($db->fetchOne($countSql, $params)['total'] ?? 0);

        return [
            'records' => $db->fetchAll($sql, $params),
            'total'   => $total,
            'limit'   => $limit,
            'offset'  => $offset,
        ];
    }

    /**
     * Find single record by ID with strict tenant isolation.
     */
    public static function findByIdAndOrg(int $id, int $orgId): ?array
    {
        $db = Database::getInstance();
        $sql = "SELECT ar.*,
                       f.name as facility_name, f.country as facility_country, f.city as facility_city,
                       rp.name as period_name, rp.is_closed as period_locked, rp.status as period_status,
                       ac.name as category_name, ac.code as category_code, ac.scope as category_scope,
                       ac.standard_unit as category_standard_unit,
                       d.name as domain_name,
                       calc.id as calculation_id, calc.tonnes_co2e, calc.kg_co2e, calc.scope as calc_scope,
                       calc.calculation_status, calc.version as calc_version, calc.factor_value_snapshot,
                       calc.factor_version_snapshot, calc.calculation_formula
                FROM `activity_records` ar
                JOIN `facilities` f ON f.id = ar.facility_id
                JOIN `reporting_periods` rp ON rp.id = ar.reporting_period_id
                JOIN `activity_categories` ac ON ac.id = ar.activity_category_id
                JOIN `domains` d ON d.id = ac.domain_id
                LEFT JOIN `carbon_calculations` calc ON calc.activity_record_id = ar.id AND calc.is_current = 1
                WHERE ar.`id` = :id AND ar.`organization_id` = :org_id
                LIMIT 1";

        return $db->fetchOne($sql, [':id' => $id, ':org_id' => $orgId]);
    }

    /**
     * Create a new activity record with tenant isolation and validations.
     *
     * @throws InvalidArgumentException
     */
    public static function create(int $orgId, array $data, int $userId): int
    {
        $db = Database::getInstance();

        // 1. Validate quantity > 0
        $quantity = (float)($data['quantity'] ?? 0);
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Activity quantity must be a positive number greater than zero.');
        }

        // 2. Validate facility belongs to organization
        $facilityId = (int)($data['facility_id'] ?? 0);
        $facility = $db->fetchOne(
            "SELECT id, domain_profile_id, country FROM `facilities` WHERE id = :id AND organization_id = :org_id LIMIT 1",
            [':id' => $facilityId, ':org_id' => $orgId]
        );
        if (!$facility) {
            throw new InvalidArgumentException('Specified facility does not exist or does not belong to your organization.');
        }

        // 3. Validate reporting period belongs to organization and is NOT locked or closed
        $periodId = (int)($data['reporting_period_id'] ?? 0);
        $period = $db->fetchOne(
            "SELECT id, is_closed, status, start_date, end_date FROM `reporting_periods` WHERE id = :id AND organization_id = :org_id LIMIT 1",
            [':id' => $periodId, ':org_id' => $orgId]
        );
        if (!$period) {
            throw new InvalidArgumentException('Specified reporting period does not exist or does not belong to your organization.');
        }
        if ((int)$period['is_closed'] === 1 || $period['status'] === 'closed') {
            throw new InvalidArgumentException('Cannot record activity data to a locked or closed reporting period.');
        }

        // 4. Validate category exists
        $categoryId = (int)($data['activity_category_id'] ?? 0);
        $category = $db->fetchOne("SELECT id, domain_id, standard_unit FROM `activity_categories` WHERE id = :id LIMIT 1", [':id' => $categoryId]);
        if (!$category) {
            throw new InvalidArgumentException('Invalid activity category specified.');
        }

        $domainProfileId = !empty($data['domain_profile_id']) ? (int)$data['domain_profile_id'] : (!empty($facility['domain_profile_id']) ? (int)$facility['domain_profile_id'] : null);
        if ($domainProfileId === null && !empty($category['domain_id'])) {
            $profile = $db->fetchOne(
                "SELECT id FROM `domain_profiles` WHERE organization_id = :org_id AND domain_id = :dom_id LIMIT 1",
                [':org_id' => $orgId, ':dom_id' => (int)$category['domain_id']]
            );
            if ($profile) {
                $domainProfileId = (int)$profile['id'];
            }
        }
        $status = in_array($data['status'] ?? '', ['draft', 'submitted'], true) ? $data['status'] : 'submitted';

        $recordData = [
            'organization_id'         => $orgId,
            'domain_profile_id'       => $domainProfileId,
            'facility_id'             => $facilityId,
            'reporting_period_id'     => $periodId,
            'activity_category_id'    => $categoryId,
            'activity_date'           => $data['activity_date'] ?? date('Y-m-d'),
            'quantity'                => $quantity,
            'unit'                    => trim($data['unit'] ?? $category['standard_unit']),
            'description'             => $data['description'] ?? null,
            'data_source_type'        => $data['data_source_type'] ?? 'invoice',
            'supporting_document_url' => $data['supporting_document_url'] ?? null,
            'status'                  => $status,
            'created_by'              => $userId,
        ];

        return (int)$db->insert('activity_records', $recordData);
    }

    /**
     * Update activity record.
     */
    public static function update(int $id, int $orgId, array $data, int $userId): bool
    {
        $db = Database::getInstance();
        $existing = self::findByIdAndOrg($id, $orgId);
        if (!$existing) {
            throw new InvalidArgumentException('Activity record not found in organization.');
        }

        // Validate reporting period is not closed
        if ((int)$existing['period_locked'] === 1 || $existing['period_status'] === 'closed') {
            throw new InvalidArgumentException('Cannot modify records in a locked or closed reporting period.');
        }

        // Validate quantity if provided
        if (isset($data['quantity'])) {
            $qty = (float)$data['quantity'];
            if ($qty <= 0) {
                throw new InvalidArgumentException('Activity quantity must be greater than zero.');
            }
        }

        $updateData = [];
        $allowed = [
            'facility_id', 'reporting_period_id', 'activity_category_id',
            'activity_date', 'quantity', 'unit', 'description',
            'data_source_type', 'supporting_document_url', 'status'
        ];

        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $updateData[$f] = $data[$f];
            }
        }

        if (empty($updateData)) {
            return false;
        }

        $db->update(
            'activity_records',
            $updateData,
            '`id` = :where_id AND `organization_id` = :where_org_id',
            [':where_id' => $id, ':where_org_id' => $orgId]
        );
        return true;
    }

    /**
     * Submit draft record.
     */
    public static function submit(int $id, int $orgId): bool
    {
        $db = Database::getInstance();
        $db->update(
            'activity_records',
            ['status' => 'submitted'],
            '`id` = :where_id AND `organization_id` = :where_org_id AND `status` = :where_status',
            [':where_id' => $id, ':where_org_id' => $orgId, ':where_status' => 'draft']
        );
        return true;
    }

    /**
     * Validate an activity record.
     */
    public static function validate(int $id, int $orgId, int $validatorUserId): bool
    {
        $db = Database::getInstance();
        $db->update(
            'activity_records',
            [
                'status' => 'validated',
                'validated_by' => $validatorUserId,
                'validated_at' => date('Y-m-d H:i:s'),
                'rejection_reason' => null
            ],
            '`id` = :where_id AND `organization_id` = :where_org_id',
            [':where_id' => $id, ':where_org_id' => $orgId]
        );
        return true;
    }

    /**
     * Reject an activity record with mandatory reason.
     */
    public static function reject(int $id, int $orgId, string $reason, int $validatorUserId): bool
    {
        if (empty(trim($reason))) {
            throw new InvalidArgumentException('A rejection reason must be provided.');
        }

        $db = Database::getInstance();
        $db->update(
            'activity_records',
            [
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'validated_by' => $validatorUserId,
                'validated_at' => date('Y-m-d H:i:s')
            ],
            '`id` = :where_id AND `organization_id` = :where_org_id',
            [':where_id' => $id, ':where_org_id' => $orgId]
        );
        return true;
    }

    /**
     * Delete an activity record. Disallows deletion of validated records.
     */
    public static function delete(int $id, int $orgId): bool
    {
        $db = Database::getInstance();
        $existing = self::findByIdAndOrg($id, $orgId);
        if (!$existing) {
            return false;
        }

        if ($existing['status'] === 'validated') {
            throw new InvalidArgumentException('Validated activity records cannot be deleted. Archive or reject first.');
        }

        $sql = "DELETE FROM `activity_records` WHERE `id` = :id AND `organization_id` = :org_id";
        return $db->execute($sql, [':id' => $id, ':org_id' => $orgId]) > 0;
    }
}
