<?php
declare(strict_types=1);

namespace EcoPulse\Controllers;

use EcoPulse\Core\Logger;
use EcoPulse\Core\Request;
use EcoPulse\Core\Response;
use EcoPulse\Models\ReductionInitiative;
use EcoPulse\Models\ReductionInitiativeUpdate;
use Throwable;

class ReductionInitiativeController
{
    /**
     * GET /api/reduction-initiatives
     */
    public function index(Request $request): void
    {
        $org = $request->getOrganization();
        $filters = [
            'domain_profile_id' => $request->getInt('domain_profile_id') ?: null,
            'facility_id'       => $request->getInt('facility_id') ?: null,
            'status'            => $request->getString('status') ?: null,
            'target_scope'      => $request->getString('target_scope') ?: null,
            'search'            => $request->getString('search') ?: null,
        ];
        $page = max(1, $request->getInt('page') ?: 1);
        $limit = min(100, max(1, $request->getInt('limit') ?: 20));

        $data = ReductionInitiative::list((int)$org['id'], $filters, $page, $limit);
        Response::json($data, 200, 'Reduction initiatives retrieved.');
    }

    /**
     * GET /api/reduction-initiatives/:id
     */
    public function show(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $id = (int)($params['id'] ?? 0);

        $initiative = ReductionInitiative::findByIdAndOrg($id, (int)$org['id']);
        if (!$initiative) {
            Response::error('Reduction initiative not found or access denied.', 404, 'NOT_FOUND');
            return;
        }

        $history = ReductionInitiativeUpdate::getHistoryForInitiative($id, (int)$org['id']);
        $initiative['updates_history'] = $history;

        Response::json($initiative, 200, 'Reduction initiative details retrieved.');
    }

    /**
     * POST /api/reduction-initiatives
     */
    public function store(Request $request): void
    {
        $org = $request->getOrganization();
        $user = $request->getUser();
        $orgId = (int)$org['id'];

        $title = $request->getString('title');
        $domainProfileId = $request->getInt('domain_profile_id');

        if (empty($title) || $domainProfileId <= 0) {
            Response::error('Missing required fields: title and domain_profile_id.', 422, 'VALIDATION_FAILED');
            return;
        }

        $data = [
            'organization_id'                  => $orgId,
            'domain_profile_id'                => $domainProfileId,
            'facility_id'                      => $request->getInt('facility_id') ?: null,
            'activity_category_id'             => $request->getInt('activity_category_id') ?: null,
            'owner_id'                         => $request->getInt('owner_id') ?: (int)$user['id'],
            'baseline_reporting_period_id'     => $request->getInt('baseline_reporting_period_id') ?: null,
            'target_reporting_period_id'       => $request->getInt('target_reporting_period_id') ?: null,
            'title'                            => $title,
            'description'                      => $request->getString('description') ?: '',
            'target_scope'                     => $request->getString('target_scope') ?: 'all',
            'baseline_year'                    => $request->getInt('baseline_year') ?: (int)date('Y'),
            'target_completion_year'           => $request->getInt('target_completion_year') ?: ((int)date('Y') + 1),
            'baseline_emissions'               => $request->getFloat('baseline_emissions') ?: null,
            'target_emissions'                 => $request->getFloat('target_emissions') ?: null,
            'expected_reduction_percentage'    => $request->getFloat('expected_reduction_percentage') ?: null,
            'estimated_annual_tco2e_reduction' => $request->getFloat('estimated_annual_tco2e_reduction') ?: 0.0,
            'estimated_capex'                  => $request->getFloat('estimated_capex') ?: 0.0,
            'currency'                         => $request->getString('currency') ?: 'USD',
            'start_date'                       => $request->getString('start_date') ?: date('Y-m-d'),
            'target_completion_date'           => $request->getString('target_completion_date') ?: null,
            'progress_percentage'              => 0.0,
            'measured_reduction_tco2e'         => 0.0,
            'reduction_verification_status'    => 'unverified',
            'status'                           => $request->getString('status') ?: 'proposed',
            'created_by'                       => (int)$user['id'],
        ];

        try {
            $id = ReductionInitiative::create($data);
            Logger::audit(
                action: 'reduction_initiative.created',
                entityType: 'reduction_initiatives',
                entityId: $id,
                orgId: $orgId,
                userId: (int)$user['id'],
                newValues: ['title' => $title, 'domain_profile_id' => $domainProfileId]
            );

            Response::json(['id' => $id, 'title' => $title], 201, 'Reduction initiative created successfully.');
        } catch (Throwable $e) {
            Response::error('Failed to create initiative: ' . $e->getMessage(), 500, 'CREATE_FAILED');
        }
    }

    /**
     * PUT /api/reduction-initiatives/:id
     */
    public function update(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $user = $request->getUser();
        $orgId = (int)$org['id'];
        $id = (int)($params['id'] ?? 0);

        $existing = ReductionInitiative::findByIdAndOrg($id, $orgId);
        if (!$existing) {
            Response::error('Reduction initiative not found or access denied.', 404, 'NOT_FOUND');
            return;
        }

        $fields = $request->all();
        $updated = ReductionInitiative::update($id, $orgId, $fields);

        if ($updated) {
            Logger::audit(
                action: 'reduction_initiative.updated',
                entityType: 'reduction_initiatives',
                entityId: $id,
                orgId: $orgId,
                userId: (int)$user['id'],
                newValues: $fields
            );
            Response::json(['id' => $id], 200, 'Reduction initiative updated.');
        } else {
            Response::error('No changes made or update failed.', 400, 'UPDATE_FAILED');
        }
    }

    /**
     * POST /api/reduction-initiatives/:id/status
     */
    public function updateStatus(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $user = $request->getUser();
        $orgId = (int)$org['id'];
        $id = (int)($params['id'] ?? 0);

        $existing = ReductionInitiative::findByIdAndOrg($id, $orgId);
        if (!$existing) {
            Response::error('Reduction initiative not found or access denied.', 404, 'NOT_FOUND');
            return;
        }

        $status = $request->getString('status');
        $validStatuses = ['proposed', 'approved', 'in_progress', 'on_hold', 'completed', 'cancelled'];
        if (!in_array($status, $validStatuses, true)) {
            Response::error('Invalid status. Allowed: ' . implode(', ', $validStatuses), 422, 'INVALID_STATUS');
            return;
        }

        ReductionInitiative::updateStatus($id, $orgId, $status);

        Logger::audit(
            action: 'reduction_initiative.status_changed',
            entityType: 'reduction_initiatives',
            entityId: $id,
            orgId: $orgId,
            userId: (int)$user['id'],
            oldValues: ['status' => $existing['status']],
            newValues: ['status' => $status]
        );

        Response::json(['id' => $id, 'status' => $status], 200, "Initiative status updated to '{$status}'.");
    }

    /**
     * POST /api/reduction-initiatives/:id/progress
     */
    public function addProgress(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $user = $request->getUser();
        $orgId = (int)$org['id'];
        $id = (int)($params['id'] ?? 0);

        $existing = ReductionInitiative::findByIdAndOrg($id, $orgId);
        if (!$existing) {
            Response::error('Reduction initiative not found or access denied.', 404, 'NOT_FOUND');
            return;
        }

        $description = $request->getString('description');
        $progressPct = $request->getFloat('progress_percentage');
        $status = $request->getString('status') ?: $existing['status'];

        if (empty($description)) {
            Response::error('Description of progress milestone is required.', 422, 'VALIDATION_FAILED');
            return;
        }

        if ($progressPct < 0.0 || $progressPct > 100.0) {
            Response::error('Progress percentage must be between 0 and 100.', 422, 'INVALID_PROGRESS');
            return;
        }

        $updateData = [
            'initiative_id'            => $id,
            'organization_id'          => $orgId,
            'update_date'              => $request->getString('update_date') ?: date('Y-m-d'),
            'description'              => $description,
            'progress_percentage'      => $progressPct,
            'status'                   => $status,
            'measured_reduction_tco2e' => $request->has('measured_reduction_tco2e') ? $request->getFloat('measured_reduction_tco2e') : null,
            'evidence_notes'           => $request->getString('evidence_notes') ?: null,
            'supporting_document_url'  => $request->getString('supporting_document_url') ?: null,
            'updated_by'               => (int)$user['id'],
        ];

        try {
            $updateId = ReductionInitiativeUpdate::addUpdate($updateData);

            Logger::audit(
                action: 'reduction_initiative.progress_added',
                entityType: 'reduction_initiative_updates',
                entityId: $updateId,
                orgId: $orgId,
                userId: (int)$user['id'],
                newValues: ['progress_percentage' => $progressPct, 'status' => $status]
            );

            Response::json([
                'update_id'           => $updateId,
                'initiative_id'       => $id,
                'progress_percentage' => $progressPct,
                'status'              => $status,
            ], 201, 'Progress update recorded successfully.');
        } catch (Throwable $e) {
            Response::error('Failed to record progress: ' . $e->getMessage(), 500, 'UPDATE_FAILED');
        }
    }

    /**
     * GET /api/reduction-initiatives/:id/history
     */
    public function history(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $id = (int)($params['id'] ?? 0);

        $existing = ReductionInitiative::findByIdAndOrg($id, (int)$org['id']);
        if (!$existing) {
            Response::error('Reduction initiative not found or access denied.', 404, 'NOT_FOUND');
            return;
        }

        $history = ReductionInitiativeUpdate::getHistoryForInitiative($id, (int)$org['id']);
        Response::json([
            'initiative_id' => $id,
            'history'       => $history,
            'count'         => count($history),
        ], 200, 'Initiative update history retrieved.');
    }
}
