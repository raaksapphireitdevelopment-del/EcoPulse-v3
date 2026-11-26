<?php
declare(strict_types=1);

namespace EcoPulse\Controllers;

use EcoPulse\Core\Database;
use EcoPulse\Core\Logger;
use EcoPulse\Core\Request;
use EcoPulse\Core\Response;
use EcoPulse\Models\EmissionFactor;

class EmissionFactorController
{
    /**
     * GET /api/emission-factors
     */
    public function index(Request $request): void
    {
        $filters = [
            'activity_category_id' => $request->getInt('activity_category_id') ?: null,
            'scope'                => $request->getString('scope') ?: null,
            'geography'            => $request->getString('geography') ?: null,
            'validation_status'    => $request->getString('validation_status') ?: null,
            'is_demo'              => $request->has('is_demo') ? $request->getInt('is_demo') : null,
            'domain_id'            => $request->getInt('domain_id') ?: null,
            'search'               => $request->getString('search') ?: null,
        ];

        $limit = max(1, min(200, $request->getInt('limit', 100)));
        $offset = max(0, $request->getInt('offset', 0));

        $result = EmissionFactor::list($filters, $limit, $offset);
        Response::json($result, 200, 'Emission factors retrieved successfully.');
    }

    /**
     * GET /api/emission-factors/:id
     */
    public function show(Request $request, array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        $factor = EmissionFactor::findById($id);

        if (!$factor) {
            Response::error('Emission factor not found.', 404, 'NOT_FOUND');
            return;
        }

        Response::json($factor, 200, 'Emission factor details retrieved.');
    }

    /**
     * GET /api/activity-categories
     * Returns category configs for dynamic form rendering.
     */
    public function categories(Request $request): void
    {
        $db = Database::getInstance();
        $domainId = $request->getInt('domain_id') ?: null;
        $conditions = ['ac.`is_active` = 1'];
        $params = [];

        if ($domainId !== null && $domainId > 0) {
            $conditions[] = 'ac.`domain_id` = :domain_id';
            $params[':domain_id'] = $domainId;
        }

        $whereClause = implode(' AND ', $conditions);
        $sql = "SELECT ac.*, d.name as domain_name, d.code as domain_code
                FROM `activity_categories` ac
                JOIN `domains` d ON d.id = ac.domain_id
                WHERE {$whereClause}
                ORDER BY ac.domain_id ASC, ac.id ASC";

        $rows = $db->fetchAll($sql, $params);

        // Decode JSON fields if present
        $categories = array_map(function ($row) {
            if (!empty($row['accepted_units']) && is_string($row['accepted_units'])) {
                $row['accepted_units'] = json_decode($row['accepted_units'], true);
            }
            if (!empty($row['form_fields']) && is_string($row['form_fields'])) {
                $row['form_fields'] = json_decode($row['form_fields'], true);
            }
            if (!empty($row['accounting_rules']) && is_string($row['accounting_rules'])) {
                $row['accounting_rules'] = json_decode($row['accounting_rules'], true);
            }
            return $row;
        }, $rows);

        Response::json($categories, 200, 'Activity categories retrieved successfully.');
    }

    /**
     * POST /api/emission-factors
     */
    public function store(Request $request): void
    {
        $user = $request->getUser();
        $org = $request->getOrganization();

        // Enforce role permission: only super_admin, organization_owner, or organization_admin
        $role = $request->getMemberRole() ?? $user['system_role'];
        if (!in_array($role, ['super_admin', 'organization_owner', 'organization_admin'], true)) {
            Response::error('Unauthorized. Only administrators can add emission factors.', 403, 'PERMISSION_DENIED');
            return;
        }

        $categoryId = $request->getInt('activity_category_id');
        $factorName = $request->getString('factor_name');
        $factorValue = $request->getFloat('factor_value');
        $activityUnit = $request->getString('activity_unit');
        $source = $request->getString('source');
        $scope = $request->getString('scope');

        if ($categoryId <= 0 || empty($factorName) || $factorValue <= 0 || empty($activityUnit) || empty($source) || empty($scope)) {
            Response::error('Missing required fields: activity_category_id, factor_name, factor_value, activity_unit, source, scope.', 422, 'VALIDATION_FAILED');
            return;
        }

        // Only super_admin can set 'validated' directly. Others create 'pending_review' or 'draft'.
        $isSuperAdmin = ($user['system_role'] === 'super_admin');
        $status = $isSuperAdmin ? ($request->getString('validation_status') ?: 'validated') : 'pending_review';

        $data = [
            'activity_category_id'    => $categoryId,
            'factor_name'             => $factorName,
            'factor_value'            => $factorValue,
            'activity_unit'           => $activityUnit,
            'co2e_unit'               => $request->getString('co2e_unit') ?: 'kgCO2e',
            'scope'                   => $scope,
            'gas_detail'              => $request->getString('gas_detail') ?: 'CO2e',
            'gwp_value'               => $request->getFloat('gwp_value') ?: null,
            'gwp_source'              => $request->getString('gwp_source') ?: null,
            'geography'               => $request->getString('geography') ?: 'Global',
            'source'                  => $source,
            'publishing_organization' => $request->getString('publishing_organization') ?: null,
            'source_reference'        => $request->getString('source_reference') ?: null,
            'version'                 => $request->getString('version') ?: '1.0',
            'effective_date'          => $request->getString('effective_date') ?: date('Y-01-01'),
            'expiry_date'             => $request->getString('expiry_date') ?: null,
            'validation_status'       => $status,
            'is_demo'                 => $request->getInt('is_demo') ? 1 : 0,
        ];

        try {
            $id = EmissionFactor::create($data);
            Logger::audit(
                action: 'emission_factor.created',
                entityType: 'emission_factors',
                entityId: $id,
                orgId: $org['id'] ?? null,
                userId: (int)$user['id'],
                newValues: ['factor_name' => $factorName, 'validation_status' => $status]
            );

            Response::json(['id' => $id, 'status' => $status], 201, 'Emission factor created successfully.');
        } catch (\Throwable $e) {
            Response::error('Failed to create emission factor: ' . $e->getMessage(), 500, 'CREATE_FAILED');
        }
    }

    /**
     * POST /api/emission-factors/:id/validate
     * Super Admin or Org Owner/Admin approving or rejecting a factor. Normal members strictly forbidden.
     */
    public function validateFactor(Request $request, array $params): void
    {
        $user = $request->getUser();
        $org = $request->getOrganization();
        $factorId = (int)($params['id'] ?? 0);

        // Security check: normal organization members cannot validate factors!
        $role = $request->getMemberRole() ?? $user['system_role'];
        if (!in_array($role, ['super_admin', 'organization_owner', 'organization_admin'], true)) {
            Response::error('Unauthorized. Only administrators can validate or approve emission factors.', 403, 'PERMISSION_DENIED');
            return;
        }

        $status = $request->getString('validation_status');
        if (!in_array($status, ['validated', 'rejected', 'expired'], true)) {
            Response::error('Invalid validation status. Allowed: validated, rejected, expired.', 422, 'INVALID_STATUS');
            return;
        }

        $factor = EmissionFactor::findById($factorId);
        if (!$factor) {
            Response::error('Emission factor not found.', 404, 'NOT_FOUND');
            return;
        }

        EmissionFactor::updateStatus($factorId, $status);

        Logger::audit(
            action: 'emission_factor.validation_changed',
            entityType: 'emission_factors',
            entityId: $factorId,
            orgId: $org['id'] ?? null,
            userId: (int)$user['id'],
            oldValues: ['old_status' => $factor['validation_status']],
            newValues: ['new_status' => $status]
        );

        Response::json(['id' => $factorId, 'validation_status' => $status], 200, "Emission factor status updated to '{$status}'.");
    }
}
