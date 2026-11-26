<?php
declare(strict_types=1);

namespace EcoPulse\Services;

use EcoPulse\Core\Database;
use EcoPulse\Models\ActivityRecord;
use EcoPulse\Models\CarbonCalculation;
use EcoPulse\Models\EmissionFactor;
use InvalidArgumentException;
use RuntimeException;

class CalculationEngine
{
    /**
     * Calculate carbon emissions for an activity record and store an immutable snapshot.
     *
     * @param int $activityRecordId
     * @param int $orgId
     * @param int $userId
     * @param string|null $recalculationReason
     * @param int|null $overrideFactorId
     * @return array Calculated results and snapshot record
     *
     * @throws InvalidArgumentException|RuntimeException
     */
    public static function calculate(
        int $activityRecordId,
        int $orgId,
        int $userId,
        ?string $recalculationReason = null,
        ?int $overrideFactorId = null
    ): array {
        $db = Database::getInstance();

        // 1. Fetch Activity Record with Tenant Isolation
        $record = ActivityRecord::findByIdAndOrg($activityRecordId, $orgId);
        if (!$record) {
            throw new InvalidArgumentException("Activity record #{$activityRecordId} not found in this organization.");
        }

        // 2. Select Emission Factor
        $factor = null;
        if ($overrideFactorId !== null && $overrideFactorId > 0) {
            $factor = EmissionFactor::findById($overrideFactorId);
            if (!$factor) {
                throw new InvalidArgumentException("Specified emission factor #{$overrideFactorId} not found.");
            }
            if ($factor['validation_status'] === 'rejected' || $factor['validation_status'] === 'expired') {
                throw new InvalidArgumentException("Cannot use emission factor with status '{$factor['validation_status']}'.");
            }
            if ($factor['validation_status'] !== 'validated' && (int)($factor['is_demo'] ?? 0) !== 1) {
                throw new InvalidArgumentException("Cannot use unvalidated emission factor with status '{$factor['validation_status']}'.");
            }
            if (!empty($factor['expiry_date']) && $record['activity_date'] > $factor['expiry_date']) {
                throw new InvalidArgumentException("Specified emission factor #{$overrideFactorId} expired on {$factor['expiry_date']}.");
            }
            if (!empty($factor['effective_date']) && $record['activity_date'] < $factor['effective_date']) {
                throw new InvalidArgumentException("Specified emission factor #{$overrideFactorId} is not effective until {$factor['effective_date']}.");
            }
        } else {
            // Find applicable factor matching category, geography and date
            $facilityCountry = $record['facility_country'] ?? null;
            $activityDate = $record['activity_date'];

            // First try strict validated factors
            $factor = EmissionFactor::findApplicable(
                (int)$record['activity_category_id'],
                $facilityCountry,
                $activityDate,
                false
            );

            // If not found, check if demo factors are available
            if (!$factor) {
                $factor = EmissionFactor::findApplicable(
                    (int)$record['activity_category_id'],
                    $facilityCountry,
                    $activityDate,
                    true
                );
            }
        }

        if (!$factor) {
            throw new RuntimeException(
                "No active emission factor available for category '{$record['category_name']}' [ID: {$record['activity_category_id']}] " .
                "in geography '{$record['facility_country']}' for date {$record['activity_date']}. Calculation requires human review."
            );
        }

        // 3. Unit Conversion & Validation
        $activityUnit = trim($record['unit']);
        $factorUnit = trim($factor['activity_unit']);
        $quantity = (float)$record['quantity'];

        if (!UnitConverter::canConvert($activityUnit, $factorUnit)) {
            throw new InvalidArgumentException(
                "Unit incompatibility: Activity record unit '{$activityUnit}' cannot be converted to emission factor unit '{$factorUnit}'."
            );
        }

        $conversionFactor = UnitConverter::getConversionFactor($activityUnit, $factorUnit);
        $normalizedQuantity = round($quantity * $conversionFactor, 6);

        // 4. GWP Determination
        // If factor's co2e_unit is already kgCO2e or factor_value expresses CO2e, GWP is already folded in.
        $factorValue = (float)$factor['factor_value'];
        $co2eUnit = strtolower(trim($factor['co2e_unit'] ?? 'kgco2e'));
        $gwpApplied = null;
        $gwpSource = null;

        $isRawGas = ($co2eUnit === 'kg' || $co2eUnit === 'g') && !empty($factor['gwp_value']) && (float)$factor['gwp_value'] > 1.0;

        if ($isRawGas) {
            $gwpApplied = (float)$factor['gwp_value'];
            $gwpSource = $factor['gwp_source'] ?? 'IPCC Assessment Report';
            $calculatedKgCo2e = $normalizedQuantity * $factorValue * $gwpApplied;
        } else {
            // Factor is already in CO2e
            $calculatedKgCo2e = $normalizedQuantity * $factorValue;
        }

        $calculatedKgCo2e = round($calculatedKgCo2e, 6);
        $calculatedTonnesCo2e = round($calculatedKgCo2e / 1000.0, 6);

        // 5. Scope Classification & Avoided Emissions Segregation
        // Avoided emissions (e.g. composting diversion, solar export, carbon offset) must NEVER mix with Scope 1/2/3.
        $scope = $factor['scope'] ?? $record['category_scope'];
        $categoryCode = strtolower($record['category_code'] ?? '');

        if (
            str_contains($categoryCode, 'diversion') ||
            str_contains($categoryCode, 'avoided') ||
            str_contains($categoryCode, 'offset') ||
            str_contains($categoryCode, 'renewable_export') ||
            $scope === 'avoided_emissions'
        ) {
            $scope = 'avoided_emissions';
        }

        // 6. Calculation Status Tagging
        $calculationStatus = 'validated';
        if ((int)$factor['is_demo'] === 1 || $factor['validation_status'] === 'demo') {
            $calculationStatus = 'demo';
        } elseif ($factor['validation_status'] === 'pending_review' || $factor['validation_status'] === 'provisional') {
            $calculationStatus = 'provisional';
        }

        // 7. Formula Traceability String
        $formula = sprintf(
            "%.4f %s * %.6f (%s/%s) * %.6f kgCO2e/%s",
            $quantity,
            $activityUnit,
            $conversionFactor,
            $factorUnit,
            $activityUnit,
            $factorValue,
            $factorUnit
        );
        if ($isRawGas && $gwpApplied !== null) {
            $formula .= sprintf(" * %.2f GWP (%s)", $gwpApplied, $gwpSource);
        }
        $formula .= sprintf(" = %.4f kgCO2e (%.4f tCO2e)", $calculatedKgCo2e, $calculatedTonnesCo2e);

        // 8. Record Snapshot in Database
        $snapshotData = [
            'activity_record_id'         => $activityRecordId,
            'organization_id'            => $orgId,
            'facility_id'                => (int)$record['facility_id'],
            'reporting_period_id'        => (int)$record['reporting_period_id'],
            'activity_category_id'       => (int)$record['activity_category_id'],
            'emission_factor_id'         => (int)$factor['id'],
            'factor_version_snapshot'    => $factor['version'] ?? '1.0',
            'factor_value_snapshot'      => $factorValue,
            'factor_source_snapshot'     => $factor['source'] ?? null,
            'factor_status_snapshot'     => $factor['validation_status'] ?? 'validated',
            'geography_snapshot'         => $factor['geography'] ?? 'Global',
            'gwp_applied'                => $gwpApplied,
            'gwp_source_snapshot'        => $gwpSource,
            'activity_quantity_snapshot' => $quantity,
            'normalized_quantity'        => $normalizedQuantity,
            'normalized_unit'            => $factorUnit,
            'conversion_factor'          => $conversionFactor,
            'kg_co2e'                    => $calculatedKgCo2e,
            'tonnes_co2e'                => $calculatedTonnesCo2e,
            'scope'                      => $scope,
            'calculation_formula'        => $formula,
            'calculation_status'         => $calculationStatus,
            'recalculation_reason'       => $recalculationReason,
            'triggered_by'               => $userId,
        ];

        $snapshot = CarbonCalculation::recordSnapshot($snapshotData);

        return array_merge($snapshotData, [
            'id' => $snapshot['id'],
            'version' => $snapshot['version'],
            'factor_name' => $factor['factor_name'],
            'activity_record' => $record,
        ]);
    }

    /**
     * Recalculate all records in a reporting period or facility.
     */
    public static function batchRecalculate(
        int $orgId,
        int $userId,
        ?int $reportingPeriodId = null,
        ?int $facilityId = null,
        string $reason = 'Batch Period Recalculation'
    ): array {
        $db = Database::getInstance();
        $conditions = ['organization_id = :org_id'];
        $params = [':org_id' => $orgId];

        if ($reportingPeriodId !== null && $reportingPeriodId > 0) {
            $conditions[] = 'reporting_period_id = :period_id';
            $params[':period_id'] = $reportingPeriodId;
        }

        if ($facilityId !== null && $facilityId > 0) {
            $conditions[] = 'facility_id = :facility_id';
            $params[':facility_id'] = $facilityId;
        }

        $whereClause = implode(' AND ', $conditions);
        $records = $db->fetchAll("SELECT id FROM `activity_records` WHERE {$whereClause} ORDER BY id ASC", $params);

        $results = [
            'total'      => count($records),
            'calculated' => 0,
            'failed'     => 0,
            'errors'     => [],
        ];

        foreach ($records as $r) {
            try {
                self::calculate((int)$r['id'], $orgId, $userId, $reason);
                $results['calculated']++;
            } catch (\Throwable $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'record_id' => $r['id'],
                    'message'   => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}
