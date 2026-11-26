<?php
declare(strict_types=1);

namespace EcoPulse\Controllers;

use EcoPulse\Core\Logger;
use EcoPulse\Core\Request;
use EcoPulse\Core\Response;
use EcoPulse\Models\ActivityRecord;
use EcoPulse\Models\CarbonCalculation;
use EcoPulse\Services\CalculationEngine;
use InvalidArgumentException;
use Throwable;

class CarbonCalculationController
{
    /**
     * POST /api/calculations/record/:id
     * Trigger explicit calculation/recalculation for an activity record.
     */
    public function calculateRecord(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $user = $request->getUser();
        $orgId = (int)$org['id'];
        $recordId = (int)($params['id'] ?? 0);

        $reason = $request->getString('reason') ?: 'Manual User Calculation';
        $overrideFactorId = $request->getInt('emission_factor_id') ?: null;

        try {
            $result = CalculationEngine::calculate($recordId, $orgId, (int)$user['id'], $reason, $overrideFactorId);

            Logger::audit(
                action: 'carbon_calculation.performed',
                entityType: 'carbon_calculations',
                entityId: $result['id'],
                orgId: $orgId,
                userId: (int)$user['id'],
                newValues: [
                    'activity_record_id' => $recordId,
                    'kg_co2e' => $result['kg_co2e'],
                    'tonnes_co2e' => $result['tonnes_co2e'],
                    'scope' => $result['scope'],
                    'version' => $result['version'],
                ]
            );

            Response::json($result, 200, 'Calculation completed successfully.');
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422, 'CALCULATION_INVALID_INPUT');
        } catch (Throwable $e) {
            Response::error('Calculation failed: ' . $e->getMessage(), 500, 'CALCULATION_ENGINE_ERROR');
        }
    }

    /**
     * POST /api/calculations/recalculate
     * Batch recalculation across reporting period or facility.
     */
    public function batchRecalculate(Request $request): void
    {
        $org = $request->getOrganization();
        $user = $request->getUser();
        $orgId = (int)$org['id'];

        $periodId = $request->getInt('reporting_period_id') ?: null;
        $facilityId = $request->getInt('facility_id') ?: null;
        $reason = $request->getString('reason') ?: 'Batch Recalculation Request';

        try {
            $results = CalculationEngine::batchRecalculate($orgId, (int)$user['id'], $periodId, $facilityId, $reason);

            Logger::audit(
                action: 'carbon_calculation.batch_recalculated',
                entityType: 'carbon_calculations',
                entityId: 0,
                orgId: $orgId,
                userId: (int)$user['id'],
                newValues: ['period_id' => $periodId, 'facility_id' => $facilityId, 'calculated' => $results['calculated']]
            );

            Response::json($results, 200, 'Batch recalculation completed.');
        } catch (Throwable $e) {
            Response::error('Batch recalculation error: ' . $e->getMessage(), 500, 'BATCH_ERROR');
        }
    }

    /**
     * GET /api/calculations/history/:record_id
     * Returns full immutable snapshot version history for an activity record.
     */
    public function history(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $orgId = (int)$org['id'];
        $recordId = (int)($params['record_id'] ?? 0);

        try {
            // Verify tenant owns the activity record
            $record = ActivityRecord::findByIdAndOrg($recordId, $orgId);
            if (!$record) {
                Response::error('Activity record not found or access denied.', 404, 'NOT_FOUND');
                return;
            }

            $history = CarbonCalculation::getHistoryForRecord($recordId, $orgId);
            Response::json([
                'activity_record' => $record,
                'history' => $history,
                'version_count' => count($history),
            ], 200, 'Calculation snapshot history retrieved.');
        } catch (Throwable $e) {
            Response::error('Failed to retrieve history: ' . $e->getMessage(), 500, 'HISTORY_FETCH_ERROR');
        }
    }

    /**
     * GET /api/calculations/summary
     * Aggregate calculation summary for dashboard/workspace.
     */
    public function summary(Request $request): void
    {
        $org = $request->getOrganization();
        $orgId = (int)$org['id'];

        $periodId = $request->getInt('reporting_period_id') ?: null;
        $domainProfileId = $request->getInt('domain_profile_id') ?: null;
        $facilityId = $request->getInt('facility_id') ?: null;

        try {
            $summary = CarbonCalculation::getWorkspaceSummary($orgId, $periodId, $domainProfileId, $facilityId);
            Response::json($summary, 200, 'Calculation summary retrieved.');
        } catch (Throwable $e) {
            Response::error('Failed to retrieve summary: ' . $e->getMessage(), 500, 'SUMMARY_FETCH_ERROR');
        }
    }
}
