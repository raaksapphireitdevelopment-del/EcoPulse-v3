<?php
declare(strict_types=1);

namespace EcoPulse\Controllers;

use EcoPulse\Core\Logger;
use EcoPulse\Core\Request;
use EcoPulse\Core\Response;
use EcoPulse\Models\ReportingPeriod;

class ReportingPeriodController
{
    /**
     * GET /api/reporting-periods
     */
    public function index(Request $request): void
    {
        $org = $request->getOrganization();
        $periods = ReportingPeriod::listByOrganization((int)$org['id']);
        Response::json($periods, 200, 'Reporting periods retrieved.');
    }

    /**
     * GET /api/reporting-periods/:id
     */
    public function show(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $id = (int)($params['id'] ?? 0);

        $period = ReportingPeriod::findByIdAndOrg($id, (int)$org['id']);
        if (!$period) {
            Response::error('Reporting period not found or access denied.', 404, 'NOT_FOUND');
            return;
        }

        Response::json($period, 200, 'Reporting period details retrieved.');
    }

    /**
     * POST /api/reporting-periods
     */
    public function store(Request $request): void
    {
        $org = $request->getOrganization();
        $orgId = (int)$org['id'];

        $name = $request->getString('name');
        $periodType = $request->getString('period_type', 'annual');
        $startDate = $request->getString('start_date');
        $endDate = $request->getString('end_date');
        $status = $request->getString('status', 'active');

        // Validation
        if (empty($name) || empty($startDate) || empty($endDate)) {
            Response::error('Period name, start date and end date are required.', 422, 'VALIDATION_ERROR');
            return;
        }

        if (strtotime($endDate) < strtotime($startDate)) {
            Response::error('End date cannot be prior to start date.', 422, 'VALIDATION_ERROR', [
                'end_date' => 'End date must be greater than or equal to start date.'
            ]);
            return;
        }

        // Duplicate check
        $duplicate = ReportingPeriod::findDuplicate($orgId, $startDate, $endDate);
        if ($duplicate) {
            Response::error(
                "A reporting period ('{$duplicate['name']}') already exists for the date range {$startDate} to {$endDate}.",
                409,
                'DUPLICATE_PERIOD'
            );
            return;
        }

        $periodId = ReportingPeriod::create([
            'organization_id' => $orgId,
            'name' => $name,
            'period_type' => in_array($periodType, ['annual', 'quarterly', 'monthly', 'custom'], true) ? $periodType : 'annual',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => in_array($status, ['draft', 'active', 'closed'], true) ? $status : 'active',
            'is_closed' => ($status === 'closed') ? 1 : 0,
        ]);

        Logger::audit(
            action: 'reporting_period.created',
            entityType: 'reporting_periods',
            entityId: $periodId,
            orgId: $orgId,
            newValues: ['name' => $name, 'start_date' => $startDate, 'end_date' => $endDate]
        );

        $created = ReportingPeriod::findByIdAndOrg($periodId, $orgId);
        Response::json($created, 201, 'Reporting period created successfully.');
    }

    /**
     * PUT /api/reporting-periods/:id
     */
    public function update(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $orgId = (int)$org['id'];
        $id = (int)($params['id'] ?? 0);

        $existing = ReportingPeriod::findByIdAndOrg($id, $orgId);
        if (!$existing) {
            Response::error('Reporting period not found or access denied.', 404, 'NOT_FOUND');
            return;
        }

        $name = $request->getString('name', $existing['name']);
        $startDate = $request->getString('start_date', $existing['start_date']);
        $endDate = $request->getString('end_date', $existing['end_date']);
        $status = $request->getString('status', $existing['status']);

        if (strtotime($endDate) < strtotime($startDate)) {
            Response::error('End date cannot be prior to start date.', 422, 'VALIDATION_ERROR');
            return;
        }

        $duplicate = ReportingPeriod::findDuplicate($orgId, $startDate, $endDate, $id);
        if ($duplicate) {
            Response::error("Another period ('{$duplicate['name']}') already covers this date range.", 409, 'DUPLICATE_PERIOD');
            return;
        }

        $data = [
            'name' => $name,
            'period_type' => $request->getString('period_type', $existing['period_type']),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => in_array($status, ['draft', 'active', 'closed'], true) ? $status : $existing['status'],
            'is_closed' => ($status === 'closed') ? 1 : 0,
        ];

        ReportingPeriod::update($id, $orgId, $data);

        Logger::audit(
            action: 'reporting_period.updated',
            entityType: 'reporting_periods',
            entityId: $id,
            orgId: $orgId,
            oldValues: $existing,
            newValues: $data
        );

        $updated = ReportingPeriod::findByIdAndOrg($id, $orgId);
        Response::json($updated, 200, 'Reporting period updated successfully.');
    }

    /**
     * POST /api/reporting-periods/:id/close
     */
    public function close(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $orgId = (int)$org['id'];
        $id = (int)($params['id'] ?? 0);

        $existing = ReportingPeriod::findByIdAndOrg($id, $orgId);
        if (!$existing) {
            Response::error('Reporting period not found.', 404, 'NOT_FOUND');
            return;
        }

        ReportingPeriod::update($id, $orgId, [
            'is_closed' => 1,
            'status' => 'closed',
            'closed_at' => gmdate('Y-m-d H:i:s'),
        ]);

        Logger::audit(
            action: 'reporting_period.closed',
            entityType: 'reporting_periods',
            entityId: $id,
            orgId: $orgId
        );

        Response::json(null, 200, 'Reporting period closed and locked from further data entry.');
    }
}
