<?php
declare(strict_types=1);

namespace EcoPulse\Controllers;

use EcoPulse\Core\Logger;
use EcoPulse\Core\Request;
use EcoPulse\Core\Response;
use EcoPulse\Models\ReductionTarget;
use Throwable;

class ReductionTargetController
{
    /**
     * GET /api/reduction-targets
     */
    public function index(Request $request): void
    {
        $org = $request->getOrganization();
        $targets = ReductionTarget::list((int)$org['id']);
        Response::json($targets, 200, 'Reduction targets retrieved.');
    }

    /**
     * POST /api/reduction-targets
     */
    public function store(Request $request): void
    {
        $org = $request->getOrganization();
        $user = $request->getUser();
        $orgId = (int)$org['id'];

        $title = $request->getString('title');
        $targetYear = $request->getInt('target_year');
        $baselineEmissions = $request->getFloat('baseline_emissions_tco2e');
        $targetEmissions = $request->getFloat('target_emissions_tco2e');

        if (empty($title) || $targetYear <= 0 || $baselineEmissions <= 0 || $targetEmissions < 0) {
            Response::error('Missing required fields: title, target_year, baseline_emissions_tco2e, target_emissions_tco2e.', 422, 'VALIDATION_FAILED');
            return;
        }

        $data = [
            'organization_id'              => $orgId,
            'title'                        => $title,
            'target_level'                 => $request->getString('target_level') ?: 'organization',
            'domain_profile_id'            => $request->getInt('domain_profile_id') ?: null,
            'facility_id'                  => $request->getInt('facility_id') ?: null,
            'scope'                        => $request->getString('scope') ?: 'all',
            'activity_category_id'         => $request->getInt('activity_category_id') ?: null,
            'baseline_reporting_period_id' => $request->getInt('baseline_reporting_period_id') ?: null,
            'baseline_year'                => $request->getInt('baseline_year') ?: (int)date('Y'),
            'target_reporting_period_id'   => $request->getInt('target_reporting_period_id') ?: null,
            'target_year'                  => $targetYear,
            'baseline_emissions_tco2e'     => $baselineEmissions,
            'target_emissions_tco2e'       => $targetEmissions,
            'target_reduction_percentage'  => $request->has('target_reduction_percentage')
                ? $request->getFloat('target_reduction_percentage')
                : round((($baselineEmissions - $targetEmissions) / $baselineEmissions) * 100.0, 2),
            'current_emissions_tco2e'      => $request->has('current_emissions_tco2e')
                ? $request->getFloat('current_emissions_tco2e')
                : $baselineEmissions,
            'status'                       => $request->getString('status') ?: 'active',
            'notes'                        => $request->getString('notes') ?: null,
            'created_by'                   => (int)$user['id'],
        ];

        try {
            $id = ReductionTarget::create($data);
            Logger::audit(
                action: 'reduction_target.created',
                entityType: 'reduction_targets',
                entityId: $id,
                orgId: $orgId,
                userId: (int)$user['id'],
                newValues: ['title' => $title, 'target_year' => $targetYear]
            );

            Response::json(['id' => $id, 'title' => $title], 201, 'Reduction target created successfully.');
        } catch (Throwable $e) {
            Response::error('Failed to create target: ' . $e->getMessage(), 500, 'CREATE_FAILED');
        }
    }

    /**
     * PUT /api/reduction-targets/:id
     */
    public function update(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $user = $request->getUser();
        $orgId = (int)$org['id'];
        $id = (int)($params['id'] ?? 0);

        $existing = ReductionTarget::findByIdAndOrg($id, $orgId);
        if (!$existing) {
            Response::error('Reduction target not found or access denied.', 404, 'NOT_FOUND');
            return;
        }

        $fields = $request->all();
        $updated = ReductionTarget::update($id, $orgId, $fields);

        if ($updated) {
            Logger::audit(
                action: 'reduction_target.updated',
                entityType: 'reduction_targets',
                entityId: $id,
                orgId: $orgId,
                userId: (int)$user['id'],
                newValues: $fields
            );
            Response::json(['id' => $id], 200, 'Reduction target updated.');
        } else {
            Response::error('No changes made or update failed.', 400, 'UPDATE_FAILED');
        }
    }

    /**
     * DELETE /api/reduction-targets/:id
     */
    public function destroy(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $user = $request->getUser();
        $orgId = (int)$org['id'];
        $id = (int)($params['id'] ?? 0);

        $existing = ReductionTarget::findByIdAndOrg($id, $orgId);
        if (!$existing) {
            Response::error('Reduction target not found or access denied.', 404, 'NOT_FOUND');
            return;
        }

        $deleted = ReductionTarget::delete($id, $orgId);
        if ($deleted) {
            Logger::audit(
                action: 'reduction_target.deleted',
                entityType: 'reduction_targets',
                entityId: $id,
                orgId: $orgId,
                userId: (int)$user['id'],
                oldValues: ['title' => $existing['title']]
            );
            Response::json(['id' => $id], 200, 'Reduction target deleted successfully.');
        } else {
            Response::error('Failed to delete reduction target.', 500, 'DELETE_FAILED');
        }
    }
}
