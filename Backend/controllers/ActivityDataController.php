<?php
declare(strict_types=1);

namespace EcoPulse\Controllers;

use EcoPulse\Core\Logger;
use EcoPulse\Core\Request;
use EcoPulse\Core\Response;
use EcoPulse\Models\ActivityRecord;
use EcoPulse\Services\CalculationEngine;
use InvalidArgumentException;
use Throwable;

class ActivityDataController
{
    /**
     * GET /api/activity-records
     */
    public function index(Request $request): void
    {
        $org = $request->getOrganization();
        $orgId = (int)$org['id'];

        $filters = [
            'facility_id'          => $request->getInt('facility_id') ?: null,
            'reporting_period_id'  => $request->getInt('reporting_period_id') ?: null,
            'domain_profile_id'    => $request->getInt('domain_profile_id') ?: null,
            'activity_category_id' => $request->getInt('activity_category_id') ?: null,
            'status'               => $request->getString('status') ?: null,
            'start_date'           => $request->getString('start_date') ?: null,
            'end_date'             => $request->getString('end_date') ?: null,
            'search'               => $request->getString('search') ?: null,
        ];

        $limit = max(1, min(100, $request->getInt('limit', 50)));
        $offset = max(0, $request->getInt('offset', 0));

        $result = ActivityRecord::list($orgId, $filters, $limit, $offset);
        Response::json($result, 200, 'Activity records retrieved successfully.');
    }

    /**
     * GET /api/activity-records/:id
     */
    public function show(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $id = (int)($params['id'] ?? 0);

        $record = ActivityRecord::findByIdAndOrg($id, (int)$org['id']);
        if (!$record) {
            Response::error('Activity record not found or access denied.', 404, 'NOT_FOUND');
            return;
        }

        Response::json($record, 200, 'Activity record details retrieved.');
    }

    /**
     * POST /api/activity-records
     */
    public function store(Request $request): void
    {
        $org = $request->getOrganization();
        $user = $request->getUser();
        $orgId = (int)$org['id'];

        $facilityId = $request->getInt('facility_id');
        $periodId = $request->getInt('reporting_period_id');
        $categoryId = $request->getInt('activity_category_id');
        $quantity = $request->getFloat('quantity');
        $unit = $request->getString('unit');
        $date = $request->getString('activity_date') ?: date('Y-m-d');
        $status = $request->getString('status') ?: 'submitted';

        if ($facilityId <= 0 || $periodId <= 0 || $categoryId <= 0 || empty($unit)) {
            Response::error('facility_id, reporting_period_id, activity_category_id, and unit are required.', 422, 'VALIDATION_FAILED');
            return;
        }

        if ($quantity <= 0) {
            Response::error('Quantity must be greater than zero.', 422, 'INVALID_QUANTITY');
            return;
        }

        $data = [
            'facility_id'             => $facilityId,
            'reporting_period_id'     => $periodId,
            'activity_category_id'    => $categoryId,
            'domain_profile_id'       => $request->getInt('domain_profile_id') ?: null,
            'activity_date'           => $date,
            'quantity'                => $quantity,
            'unit'                    => $unit,
            'description'             => $request->getString('description') ?: null,
            'data_source_type'        => $request->getString('data_source_type') ?: 'invoice',
            'supporting_document_url' => $request->getString('supporting_document_url') ?: null,
            'status'                  => in_array($status, ['draft', 'submitted'], true) ? $status : 'submitted',
        ];

        try {
            $recordId = ActivityRecord::create($orgId, $data, (int)$user['id']);

            // Auto-calculate if submitted
            $calcResult = null;
            if ($data['status'] === 'submitted') {
                try {
                    $calcResult = CalculationEngine::calculate($recordId, $orgId, (int)$user['id'], 'Initial Submission Calculation');
                } catch (Throwable $e) {
                    Logger::warning("Auto-calculation failed for record #{$recordId}: " . $e->getMessage());
                }
            }

            Logger::audit(
                action: 'activity_record.created',
                entityType: 'activity_records',
                entityId: $recordId,
                orgId: $orgId,
                userId: (int)$user['id'],
                newValues: ['quantity' => $quantity, 'unit' => $unit, 'status' => $data['status']]
            );

            Response::json([
                'id' => $recordId,
                'status' => $data['status'],
                'calculation' => $calcResult,
            ], 201, 'Activity record created successfully.');
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422, 'VALIDATION_FAILED');
        } catch (Throwable $e) {
            Response::error('Failed to create activity record: ' . $e->getMessage(), 500, 'CREATE_FAILED');
        }
    }

    /**
     * PUT /api/activity-records/:id
     */
    public function update(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $user = $request->getUser();
        $orgId = (int)$org['id'];
        $id = (int)($params['id'] ?? 0);

        $existing = ActivityRecord::findByIdAndOrg($id, $orgId);
        if (!$existing) {
            Response::error('Activity record not found or access denied.', 404, 'NOT_FOUND');
            return;
        }

        // Prevent modification if validated, unless admin
        $memberRole = $request->getMemberRole() ?? $user['system_role'];
        if ($existing['status'] === 'validated' && !in_array($memberRole, ['super_admin', 'organization_owner', 'organization_admin'], true)) {
            Response::error('Validated records cannot be modified without administrator authorization.', 403, 'PERMISSION_DENIED');
            return;
        }

        $data = [];
        if ($request->has('facility_id')) $data['facility_id'] = $request->getInt('facility_id');
        if ($request->has('reporting_period_id')) $data['reporting_period_id'] = $request->getInt('reporting_period_id');
        if ($request->has('activity_category_id')) $data['activity_category_id'] = $request->getInt('activity_category_id');
        if ($request->has('activity_date')) $data['activity_date'] = $request->getString('activity_date');
        if ($request->has('quantity')) $data['quantity'] = $request->getFloat('quantity');
        if ($request->has('unit')) $data['unit'] = $request->getString('unit');
        if ($request->has('description')) $data['description'] = $request->getString('description');
        if ($request->has('data_source_type')) $data['data_source_type'] = $request->getString('data_source_type');
        if ($request->has('supporting_document_url')) $data['supporting_document_url'] = $request->getString('supporting_document_url');

        try {
            ActivityRecord::update($id, $orgId, $data, (int)$user['id']);

            // Auto-recalculate if quantity/unit/date/facility changed and status was submitted or validated
            $calcResult = null;
            if (isset($data['quantity']) || isset($data['unit']) || isset($data['facility_id']) || isset($data['activity_date'])) {
                try {
                    $calcResult = CalculationEngine::calculate($id, $orgId, (int)$user['id'], 'Record Updated Recalculation');
                } catch (Throwable $e) {
                    Logger::warning("Auto-recalculation on update failed for record #{$id}: " . $e->getMessage());
                }
            }

            Logger::audit(
                action: 'activity_record.updated',
                entityType: 'activity_records',
                entityId: $id,
                orgId: $orgId,
                userId: (int)$user['id'],
                oldValues: $existing,
                newValues: $data
            );

            Response::json(['id' => $id, 'calculation' => $calcResult], 200, 'Activity record updated successfully.');
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422, 'VALIDATION_FAILED');
        } catch (Throwable $e) {
            Response::error('Failed to update activity record: ' . $e->getMessage(), 500, 'UPDATE_FAILED');
        }
    }

    /**
     * POST /api/activity-records/:id/submit
     */
    public function submit(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $user = $request->getUser();
        $orgId = (int)$org['id'];
        $id = (int)($params['id'] ?? 0);

        $existing = ActivityRecord::findByIdAndOrg($id, $orgId);
        if (!$existing) {
            Response::error('Activity record not found or access denied.', 404, 'NOT_FOUND');
            return;
        }

        if ($existing['status'] !== 'draft') {
            Response::error('Only draft records can be submitted.', 422, 'INVALID_STATE');
            return;
        }

        ActivityRecord::submit($id, $orgId);

        // Run calculation upon submission
        $calcResult = null;
        try {
            $calcResult = CalculationEngine::calculate($id, $orgId, (int)$user['id'], 'Submitted for Calculation');
        } catch (Throwable $e) {
            Logger::warning("Calculation on submission failed for record #{$id}: " . $e->getMessage());
        }

        Logger::audit(
            action: 'activity_record.submitted',
            entityType: 'activity_records',
            entityId: $id,
            orgId: $orgId,
            userId: (int)$user['id'],
            oldValues: ['old_status' => 'draft'],
            newValues: ['new_status' => 'submitted']
        );

        Response::json(['id' => $id, 'status' => 'submitted', 'calculation' => $calcResult], 200, 'Activity record submitted successfully.');
    }

    /**
     * POST /api/activity-records/:id/validate
     */
    public function validate(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $user = $request->getUser();
        $orgId = (int)$org['id'];
        $id = (int)($params['id'] ?? 0);

        $role = $request->getMemberRole() ?? $user['system_role'];
        if (!in_array($role, ['super_admin', 'organization_owner', 'organization_admin'], true)) {
            Response::error('Unauthorized. Only organization administrators can validate records.', 403, 'PERMISSION_DENIED');
            return;
        }

        $existing = ActivityRecord::findByIdAndOrg($id, $orgId);
        if (!$existing) {
            Response::error('Activity record not found or access denied.', 404, 'NOT_FOUND');
            return;
        }

        ActivityRecord::validate($id, $orgId, (int)$user['id']);

        // Ensure calculation snapshot is up-to-date
        $calcResult = null;
        try {
            $calcResult = CalculationEngine::calculate($id, $orgId, (int)$user['id'], 'Administrator Record Validation');
        } catch (Throwable $e) {
            Logger::warning("Calculation upon validation failed for record #{$id}: " . $e->getMessage());
        }

        Logger::audit(
            action: 'activity_record.validated',
            entityType: 'activity_records',
            entityId: $id,
            orgId: $orgId,
            userId: (int)$user['id'],
            oldValues: ['status' => $existing['status']],
            newValues: ['status' => 'validated']
        );

        Response::json(['id' => $id, 'status' => 'validated', 'calculation' => $calcResult], 200, 'Activity record validated successfully.');
    }

    /**
     * POST /api/activity-records/:id/reject
     */
    public function reject(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $user = $request->getUser();
        $orgId = (int)$org['id'];
        $id = (int)($params['id'] ?? 0);

        $role = $request->getMemberRole() ?? $user['system_role'];
        if (!in_array($role, ['super_admin', 'organization_owner', 'organization_admin'], true)) {
            Response::error('Unauthorized. Only organization administrators can reject records.', 403, 'PERMISSION_DENIED');
            return;
        }

        $reason = $request->getString('rejection_reason');
        if (empty(trim($reason))) {
            Response::error('Rejection reason is required.', 422, 'MISSING_REASON');
            return;
        }

        $existing = ActivityRecord::findByIdAndOrg($id, $orgId);
        if (!$existing) {
            Response::error('Activity record not found or access denied.', 404, 'NOT_FOUND');
            return;
        }

        ActivityRecord::reject($id, $orgId, $reason, (int)$user['id']);

        Logger::audit(
            action: 'activity_record.rejected',
            entityType: 'activity_records',
            entityId: $id,
            orgId: $orgId,
            userId: (int)$user['id'],
            oldValues: ['status' => $existing['status']],
            newValues: ['status' => 'rejected', 'reason' => $reason]
        );

        Response::json(['id' => $id, 'status' => 'rejected', 'rejection_reason' => $reason], 200, 'Activity record marked as rejected.');
    }

    /**
     * DELETE /api/activity-records/:id
     */
    public function destroy(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $user = $request->getUser();
        $orgId = (int)$org['id'];
        $id = (int)($params['id'] ?? 0);

        try {
            $deleted = ActivityRecord::delete($id, $orgId);
            if (!$deleted) {
                Response::error('Activity record not found or could not be deleted.', 404, 'NOT_FOUND');
                return;
            }

            Logger::audit(
                action: 'activity_record.deleted',
                entityType: 'activity_records',
                entityId: $id,
                orgId: $orgId,
                userId: (int)$user['id']
            );
            Response::json(['id' => $id], 200, 'Activity record deleted successfully.');
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422, 'CANNOT_DELETE');
        }
    }

    /**
     * GET /api/activity-records/export
     */
    public function exportCsv(Request $request): void
    {
        $org = $request->getOrganization();
        $orgId = (int)$org['id'];

        $filters = [
            'facility_id'          => $request->getInt('facility_id') ?: null,
            'reporting_period_id'  => $request->getInt('reporting_period_id') ?: null,
            'domain_profile_id'    => $request->getInt('domain_profile_id') ?: null,
            'activity_category_id' => $request->getInt('activity_category_id') ?: null,
            'status'               => $request->getString('status') ?: null,
            'start_date'           => $request->getString('start_date') ?: null,
            'end_date'             => $request->getString('end_date') ?: null,
            'search'               => $request->getString('search') ?: null,
        ];

        $result = ActivityRecord::list($orgId, $filters, 10000, 0);
        $records = $result['records'] ?? [];

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="ecopulse_activity_records_' . date('Ymd_His') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fputs($output, "\xEF\xBB\xBF");

        fputcsv($output, [
            'Record ID',
            'Activity Date',
            'Facility Name',
            'Reporting Period',
            'Category Name',
            'Scope',
            'Quantity',
            'Unit',
            'Calculated tCO2e',
            'Status',
            'Factor Name',
            'Factor Value',
            'Formula Snapshot',
            'Calculation Version'
        ]);

        foreach ($records as $r) {
            fputcsv($output, [
                $r['id'] ?? '',
                $r['activity_date'] ?? '',
                $r['facility_name'] ?? '',
                $r['period_name'] ?? '',
                $r['category_name'] ?? '',
                $r['calc_scope'] ?? $r['category_scope'] ?? '',
                $r['quantity'] ?? 0,
                $r['unit'] ?? '',
                $r['tonnes_co2e'] !== null ? $r['tonnes_co2e'] : 'Pending',
                $r['status'] ?? '',
                $r['factor_name'] ?? '',
                $r['factor_value'] ?? '',
                $r['calculation_formula'] ?? '',
                $r['calc_version'] ?? ''
            ]);
        }

        fclose($output);
        exit;
    }
}
