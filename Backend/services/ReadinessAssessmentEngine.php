<?php
declare(strict_types=1);

namespace EcoPulse\Services;

use EcoPulse\Models\EnvironmentalProject;
use EcoPulse\Models\ProjectDocument;

class ReadinessAssessmentEngine
{
    /**
     * Default dimension weights totaling 100%
     */
    public const DEFAULT_WEIGHTS = [
        'additionality' => 25.00,
        'baseline_rigor' => 20.00,
        'monitoring' => 20.00,
        'permanence' => 10.00,
        'verification_readiness' => 25.00,
    ];

    /**
     * Evaluate an environmental project and return structured assessment results
     */
    public function evaluateProject(array $project, array $documents, array $weights = []): array
    {
        if (empty($weights)) {
            $weights = self::DEFAULT_WEIGHTS;
        }

        // 1. Mandatory data completeness check
        $missingFields = $this->checkMandatoryFields($project);
        $isComplete = empty($missingFields);
        $completenessStatus = $isComplete ? 'complete' : 'missing_mandatory_data';

        // 2. Evaluate each dimension
        $additionalityEval = $this->evaluateAdditionality($project, $documents);
        $baselineEval = $this->evaluateBaselineRigor($project, $documents);
        $monitoringEval = $this->evaluateMonitoring($project, $documents);
        $permanenceEval = $this->evaluatePermanence($project, $documents);
        $verificationEval = $this->evaluateVerificationReadiness($project, $documents);

        $dimensions = [
            'additionality' => [
                'score' => $additionalityEval['score'],
                'weight_percentage' => $weights['additionality'],
                'weighted_score' => round(($additionalityEval['score'] * $weights['additionality']) / 100.0, 2),
                'deduction_reasons' => $additionalityEval['deductions'],
                'supporting_evidence_refs' => $additionalityEval['evidence'],
                'missing_evidence' => $additionalityEval['missing_evidence'],
            ],
            'baseline_rigor' => [
                'score' => $baselineEval['score'],
                'weight_percentage' => $weights['baseline_rigor'],
                'weighted_score' => round(($baselineEval['score'] * $weights['baseline_rigor']) / 100.0, 2),
                'deduction_reasons' => $baselineEval['deductions'],
                'supporting_evidence_refs' => $baselineEval['evidence'],
                'missing_evidence' => $baselineEval['missing_evidence'],
            ],
            'monitoring' => [
                'score' => $monitoringEval['score'],
                'weight_percentage' => $weights['monitoring'],
                'weighted_score' => round(($monitoringEval['score'] * $weights['monitoring']) / 100.0, 2),
                'deduction_reasons' => $monitoringEval['deductions'],
                'supporting_evidence_refs' => $monitoringEval['evidence'],
                'missing_evidence' => $monitoringEval['missing_evidence'],
            ],
            'permanence' => [
                'score' => $permanenceEval['score'],
                'weight_percentage' => $weights['permanence'],
                'weighted_score' => round(($permanenceEval['score'] * $weights['permanence']) / 100.0, 2),
                'deduction_reasons' => $permanenceEval['deductions'],
                'supporting_evidence_refs' => $permanenceEval['evidence'],
                'missing_evidence' => $permanenceEval['missing_evidence'],
            ],
            'verification_readiness' => [
                'score' => $verificationEval['score'],
                'weight_percentage' => $weights['verification_readiness'],
                'weighted_score' => round(($verificationEval['score'] * $weights['verification_readiness']) / 100.0, 2),
                'deduction_reasons' => $verificationEval['deductions'],
                'supporting_evidence_refs' => $verificationEval['evidence'],
                'missing_evidence' => $verificationEval['missing_evidence'],
            ],
        ];

        // 3. Compute overall readiness percentage
        $overallPercentage = 0.0;
        foreach ($dimensions as $dim) {
            $overallPercentage += $dim['weighted_score'];
        }
        $overallPercentage = min(100.0, max(0.0, round($overallPercentage, 2)));

        // 4. Determine readiness stage
        $stage = $this->determineStage($overallPercentage, $isComplete, $project, $documents);

        // 5. Generate gap analysis summary & prioritized actionable recommendations
        $gapSummary = $this->generateGapAnalysis($dimensions, $missingFields, $isComplete);
        $recommendations = $this->generateRecommendations($dimensions, $missingFields, $project);

        return [
            'project_id' => (int)$project['id'],
            'organization_id' => (int)$project['organization_id'],
            'is_complete' => $isComplete,
            'completeness_status' => $completenessStatus,
            'missing_required_fields' => $missingFields,
            'weights_used' => $weights,
            'dimension_breakdown' => $dimensions,
            'additionality_score' => $additionalityEval['score'],
            'baseline_rigor_score' => $baselineEval['score'],
            'monitoring_readiness_score' => $monitoringEval['score'],
            'permanence_risk_score' => $permanenceEval['score'],
            'verification_readiness_score' => $verificationEval['score'],
            'overall_readiness_percentage' => $overallPercentage,
            'readiness_stage' => $stage,
            'gap_analysis_summary' => $gapSummary,
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Check mandatory fields required for a complete, defensible assessment
     */
    private function checkMandatoryFields(array $project): array
    {
        $missing = [];

        if (empty($project['project_title'])) {
            $missing[] = 'project_title';
        }
        if (empty($project['methodology'])) {
            $missing[] = 'methodology';
        }
        if (empty($project['baseline_scenario_description'])) {
            $missing[] = 'baseline_scenario_description';
        }
        if (empty($project['crediting_period_start'])) {
            $missing[] = 'crediting_period_start';
        }
        if (empty($project['crediting_period_end'])) {
            $missing[] = 'crediting_period_end';
        }
        if (!isset($project['estimated_annual_mitigation_tco2e']) || (float)$project['estimated_annual_mitigation_tco2e'] <= 0) {
            $missing[] = 'estimated_annual_mitigation_tco2e';
        }

        return $missing;
    }

    /**
     * Evaluate Dimension 1: Additionality (25% weight, max raw 100)
     */
    private function evaluateAdditionality(array $project, array $documents): array
    {
        $score = 0;
        $deductions = [];
        $evidence = [];
        $missing = [];

        // 1. Regulatory Surplus (30 pts)
        if (!empty($project['baseline_scenario_description']) && strlen($project['baseline_scenario_description']) >= 30) {
            $score += 30;
            $evidence[] = 'Documented baseline indicates project activities exceed regulatory statutory minimums.';
        } else {
            $deductions[] = 'Missing detailed regulatory surplus justification (30 pts deducted).';
            $missing[] = 'Documentary proof that activity is not mandated by environmental law or code.';
        }

        // 2. Financial & Investment Barrier Analysis (30 pts)
        $classification = $project['classification'] ?? '';
        if ($classification === EnvironmentalProject::CLASSIFICATION_VERIFIED_ASSET ||
            $classification === EnvironmentalProject::CLASSIFICATION_ISSUED_CREDIT ||
            $classification === EnvironmentalProject::CLASSIFICATION_PROJECT_CANDIDATE) {
            $score += 30;
            $evidence[] = 'Project candidate demonstrates financial barrier requiring environmental asset valuation.';
        } elseif ($classification === EnvironmentalProject::CLASSIFICATION_OPERATIONAL_REDUCTION) {
            $score += 10; // Standard operational improvements are often commercially viable without carbon revenue
            $deductions[] = 'Operational reduction projects typically achieve commercial payback without carbon asset monetization (20 pts deducted).';
            $missing[] = 'Investment barrier analysis showing financial need for carbon certification.';
        } else {
            $deductions[] = 'Unverified financial additionality (30 pts deducted).';
            $missing[] = 'Financial additionality analysis.';
        }

        // 3. Common Practice & Technology Barrier (25 pts)
        $objectives = $project['project_objectives'] ?? '';
        $category = $project['project_category'] ?? '';
        if (!empty($objectives) && strlen($objectives) >= 20) {
            $score += 25;
            $evidence[] = "Clear project objectives and technology category ({$category}) defined.";
        } else {
            $score += 10;
            $deductions[] = 'Project objectives lack barrier and technological penetration detail (15 pts deducted).';
            $missing[] = 'Regional common-practice analysis and barrier documentation.';
        }

        // 4. Formal Methodology Context (15 pts)
        if (!empty($project['methodology'])) {
            $score += 15;
            $evidence[] = "Methodology framework established: {$project['methodology']}.";
        } else {
            $deductions[] = 'No recognized carbon methodology specified (15 pts deducted).';
            $missing[] = 'Selection of an approved carbon crediting methodology.';
        }

        return [
            'score' => min(100, max(0, $score)),
            'deductions' => $deductions,
            'evidence' => $evidence,
            'missing_evidence' => $missing,
        ];
    }

    /**
     * Evaluate Dimension 2: Baseline Rigor (20% weight, max raw 100)
     */
    private function evaluateBaselineRigor(array $project, array $documents): array
    {
        $score = 0;
        $deductions = [];
        $evidence = [];
        $missing = [];

        // 1. Recognized Methodology Standard (35 pts)
        $methodology = strtolower($project['methodology'] ?? '');
        $recognizedStandards = ['vcs', 'verra', 'gold standard', 'cdm', 'gcc', 'acm', 'ams', 'vm00', 'car', 'acr'];
        $isRecognized = false;
        foreach ($recognizedStandards as $rs) {
            if (str_contains($methodology, $rs)) {
                $isRecognized = true;
                break;
            }
        }

        if (!empty($methodology) && $isRecognized) {
            $score += 35;
            $evidence[] = "Recognized methodology standard verified: {$project['methodology']}.";
        } elseif (!empty($methodology)) {
            $score += 20;
            $deductions[] = "Methodology '{$project['methodology']}' is non-standard or custom (15 pts deducted).";
            $missing[] = 'Harmonization with an accredited carbon accounting standard (Verra, Gold Standard, CDM).';
        } else {
            $deductions[] = 'Missing methodology standard framework (35 pts deducted).';
            $missing[] = 'Selection and citation of an accredited methodology.';
        }

        // 2. Baseline Scenario Documentation (25 pts)
        $baselineDesc = $project['baseline_scenario_description'] ?? '';
        if (strlen($baselineDesc) >= 40) {
            $score += 25;
            $evidence[] = 'Comprehensive baseline emissions narrative documented.';
        } elseif (strlen($baselineDesc) > 0) {
            $score += 10;
            $deductions[] = 'Baseline scenario is briefly outlined but lacks technical detail (15 pts deducted).';
            $missing[] = 'Quantified baseline calculation formula and historical emissions data.';
        } else {
            $deductions[] = 'No baseline scenario documentation provided (25 pts deducted).';
            $missing[] = 'Detailed baseline scenario and emission factor documentation.';
        }

        // 3. Crediting Period Integrity (20 pts)
        if (!empty($project['crediting_period_start']) && !empty($project['crediting_period_end'])) {
            $start = strtotime($project['crediting_period_start']);
            $end = strtotime($project['crediting_period_end']);
            if ($end > $start) {
                $score += 20;
                $evidence[] = "Crediting period defined: {$project['crediting_period_start']} to {$project['crediting_period_end']}.";
            } else {
                $deductions[] = 'Crediting period end date precedes start date (20 pts deducted).';
                $missing[] = 'Valid chronological crediting period boundaries.';
            }
        } else {
            $deductions[] = 'Crediting period start or end date missing (20 pts deducted).';
            $missing[] = 'Definition of formal crediting period timeframe.';
        }

        // 4. Defensible Mitigation Estimate (20 pts)
        $mitigation = (float)($project['estimated_annual_mitigation_tco2e'] ?? 0);
        if ($mitigation > 0) {
            $score += 20;
            $evidence[] = "Defensible annual mitigation estimated: {$mitigation} tCO2e/year.";
        } else {
            $deductions[] = 'No positive annual mitigation volume specified (20 pts deducted).';
            $missing[] = 'Estimated annual greenhouse gas mitigation calculation.';
        }

        return [
            'score' => min(100, max(0, $score)),
            'deductions' => $deductions,
            'evidence' => $evidence,
            'missing_evidence' => $missing,
        ];
    }

    /**
     * Evaluate Dimension 3: Monitoring Readiness (20% weight, max raw 100)
     */
    private function evaluateMonitoring(array $project, array $documents): array
    {
        $score = 0;
        $deductions = [];
        $evidence = [];
        $missing = [];

        // Check for monitoring report in uploaded documents
        $hasAcceptedMonitoring = false;
        $hasUploadedMonitoring = false;
        foreach ($documents as $doc) {
            if (($doc['document_type'] ?? '') === ProjectDocument::TYPE_MONITORING_REPORT && empty($doc['is_archived'])) {
                $hasUploadedMonitoring = true;
                if (($doc['review_status'] ?? '') === ProjectDocument::STATUS_ACCEPTED) {
                    $hasAcceptedMonitoring = true;
                }
            }
        }

        // 1. Monitoring Plan Documentation (40 pts)
        if ($hasAcceptedMonitoring) {
            $score += 40;
            $evidence[] = 'Accepted monitoring report document verified.';
        } elseif ($hasUploadedMonitoring) {
            $score += 20;
            $deductions[] = 'Monitoring report uploaded but pending formal review/acceptance (20 pts deducted).';
            $missing[] = 'Review and acceptance of submitted monitoring report.';
        } else {
            $deductions[] = 'No monitoring report or plan document attached (40 pts deducted).';
            $missing[] = 'Upload of formal Monitoring Plan or periodic Monitoring Report.';
        }

        // 2. Metering & Telemetry Infrastructure (30 pts)
        $facilityId = $project['facility_id'] ?? null;
        if (!empty($facilityId)) {
            $score += 30;
            $evidence[] = "Linked to verified organizational operational facility (ID: {$facilityId}).";
        } else {
            $score += 15;
            $deductions[] = 'Project not linked to an operational facility profile (15 pts deducted).';
            $missing[] = 'Association with specific monitored operational facility.';
        }

        // 3. QA/QC & Data Management Procedures (30 pts)
        if (!empty($project['responsible_team']) && !empty($project['project_owner_name'])) {
            $score += 30;
            $evidence[] = "Designated QA/QC team lead: {$project['project_owner_name']} ({$project['responsible_team']}).";
        } elseif (!empty($project['project_owner_name'])) {
            $score += 15;
            $deductions[] = 'Monitoring team structure lacks department/team designation (15 pts deducted).';
            $missing[] = 'Designation of operational monitoring team.';
        } else {
            $deductions[] = 'No designated project owner or monitoring team identified (30 pts deducted).';
            $missing[] = 'Assignment of responsible project owner and monitoring procedures.';
        }

        return [
            'score' => min(100, max(0, $score)),
            'deductions' => $deductions,
            'evidence' => $evidence,
            'missing_evidence' => $missing,
        ];
    }

    /**
     * Evaluate Dimension 4: Permanence & Reversal Risk (10% weight, max raw 100)
     */
    private function evaluatePermanence(array $project, array $documents): array
    {
        $score = 0;
        $deductions = [];
        $evidence = [];
        $missing = [];

        // 1. Reversal Risk Profile (40 pts)
        $category = strtolower($project['project_category'] ?? '');
        $techCategories = ['energy_efficiency', 'renewable_energy', 'fuel_switch', 'waste_to_energy', 'industrial_process'];
        $natureCategories = ['afforestation', 'reforestation', 'soil_carbon', 'blue_carbon', 'forestry'];

        if (in_array($category, $techCategories, true)) {
            $score += 40;
            $evidence[] = "Technology-based emissions avoidance ({$category}) with inherently zero reversal risk.";
        } elseif (in_array($category, $natureCategories, true)) {
            $score += 25;
            $deductions[] = "Nature-based biological storage ({$category}) carries natural reversal risks (15 pts deducted).";
            $missing[] = 'Buffer pool contribution commitment and non-permanence risk assessment.';
        } else {
            $score += 25;
            $evidence[] = "General category: {$category}.";
        }

        // 2. Project Longevity & Commitments (30 pts)
        if (!empty($project['crediting_period_start']) && !empty($project['crediting_period_end'])) {
            $years = (strtotime($project['crediting_period_end']) - strtotime($project['crediting_period_start'])) / (365.25 * 86400);
            if ($years >= 5.0) {
                $score += 30;
                $evidence[] = sprintf('Crediting longevity guaranteed for %.1f years.', $years);
            } elseif ($years >= 2.0) {
                $score += 20;
                $deductions[] = sprintf('Crediting period of %.1f years is short (10 pts deducted).', $years);
                $missing[] = 'Extended multi-year crediting commitment.';
            } else {
                $score += 10;
                $deductions[] = 'Crediting period duration is under 2 years (20 pts deducted).';
                $missing[] = 'Long-term operational guarantee.';
            }
        } else {
            $deductions[] = 'Undetermined project duration (30 pts deducted).';
            $missing[] = 'Documentation of project operational lifespan.';
        }

        // 3. Institutional & Legal Safeguards (30 pts)
        if (!empty($project['location_country']) && $project['location_country'] !== 'Global') {
            $score += 30;
            $evidence[] = "Jurisdictional location documented: {$project['location_country']}" . (!empty($project['location_region']) ? ", {$project['location_region']}" : '') . '.';
        } else {
            $score += 15;
            $deductions[] = 'Project location specified as generic Global (15 pts deducted).';
            $missing[] = 'Specific national and regional jurisdiction location.';
        }

        return [
            'score' => min(100, max(0, $score)),
            'deductions' => $deductions,
            'evidence' => $evidence,
            'missing_evidence' => $missing,
        ];
    }

    /**
     * Evaluate Dimension 5: Verification Readiness (25% weight, max raw 100)
     */
    private function evaluateVerificationReadiness(array $project, array $documents): array
    {
        $score = 0;
        $deductions = [];
        $evidence = [];
        $missing = [];

        // Check for specific document types and statuses
        $hasAcceptedPDD = false;
        $hasUploadedPDD = false;
        $hasAcceptedVerification = false;
        $hasUploadedVerification = false;
        $hasIssuanceDoc = false;

        foreach ($documents as $doc) {
            if (!empty($doc['is_archived'])) {
                continue;
            }
            $t = $doc['document_type'] ?? '';
            $s = $doc['review_status'] ?? '';

            if ($t === ProjectDocument::TYPE_PDD) {
                $hasUploadedPDD = true;
                if ($s === ProjectDocument::STATUS_ACCEPTED) {
                    $hasAcceptedPDD = true;
                }
            }
            if ($t === ProjectDocument::TYPE_VERIFICATION_REPORT || $t === ProjectDocument::TYPE_VALIDATION_REPORT) {
                $hasUploadedVerification = true;
                if ($s === ProjectDocument::STATUS_ACCEPTED) {
                    $hasAcceptedVerification = true;
                }
            }
            if ($t === ProjectDocument::TYPE_ISSUANCE_RECORD) {
                $hasIssuanceDoc = true;
            }
        }

        // 1. Project Design Document (PDD) (35 pts)
        if ($hasAcceptedPDD) {
            $score += 35;
            $evidence[] = 'Formally accepted Project Design Document (PDD) attached.';
        } elseif ($hasUploadedPDD) {
            $score += 20;
            $deductions[] = 'PDD is uploaded but pending review/acceptance (15 pts deducted).';
            $missing[] = 'Completion of PDD review and verification acceptance.';
        } else {
            $deductions[] = 'No Project Design Document (PDD) uploaded (35 pts deducted).';
            $missing[] = 'Upload of standard Project Design Document (PDD).';
        }

        // 2. Third-Party Validation / Verification Report (35 pts)
        if ($hasAcceptedVerification && !empty($project['third_party_verifier'])) {
            $score += 35;
            $evidence[] = "Accepted third-party verification report from accredited audit body ({$project['third_party_verifier']}).";
        } elseif ($hasUploadedVerification || !empty($project['third_party_verifier'])) {
            $score += 20;
            $deductions[] = 'Third-party verifier listed or verification statement uploaded, but report is not yet accepted (15 pts deducted).';
            $missing[] = 'Independent third-party verification statement acceptance.';
        } else {
            $deductions[] = 'No third-party verification report or verifier specified (35 pts deducted).';
            $missing[] = 'Accredited third-party audit statement and verification report.';
        }

        // 3. Registry Account & Issuance Record (30 pts)
        $classification = $project['classification'] ?? '';
        $hasRegistry = !empty($project['registry_name']) && !empty($project['registry_serial_or_id']);

        if ($classification === EnvironmentalProject::CLASSIFICATION_ISSUED_CREDIT && $hasRegistry) {
            $score += 30;
            $evidence[] = "Verified registry issuance on {$project['registry_name']} (Serial: {$project['registry_serial_or_id']}).";
        } elseif ($hasRegistry) {
            $score += 20;
            $evidence[] = "Registry details provided ({$project['registry_name']}).";
        } elseif ($hasIssuanceDoc) {
            $score += 15;
            $evidence[] = 'Issuance record document attached.';
        } else {
            $deductions[] = 'No active registry serial or issuance documentation attached (30 pts deducted).';
            $missing[] = 'Registry serial number and official issuance statement.';
        }

        return [
            'score' => min(100, max(0, $score)),
            'deductions' => $deductions,
            'evidence' => $evidence,
            'missing_evidence' => $missing,
        ];
    }

    /**
     * Map score, completeness, and documentation to formal readiness stages
     */
    private function determineStage(float $overallPercentage, bool $isComplete, array $project, array $documents): string
    {
        // If incomplete, stage cannot advance beyond documentation_required
        if (!$isComplete) {
            return EnvironmentalProject::STAGE_DOCUMENTATION_REQUIRED;
        }

        // Check evidence presence
        $hasAcceptedVerification = false;
        foreach ($documents as $doc) {
            if (($doc['document_type'] ?? '') === ProjectDocument::TYPE_VERIFICATION_REPORT &&
                ($doc['review_status'] ?? '') === ProjectDocument::STATUS_ACCEPTED &&
                empty($doc['is_archived'])) {
                $hasAcceptedVerification = true;
                break;
            }
        }

        $classification = $project['classification'] ?? '';
        $hasRegistry = !empty($project['registry_name']) && !empty($project['registry_serial_or_id']);

        // Stage mapping
        if ($classification === EnvironmentalProject::CLASSIFICATION_ISSUED_CREDIT && $hasRegistry && $overallPercentage >= 85.0) {
            return EnvironmentalProject::STAGE_ISSUED;
        }

        if ($hasAcceptedVerification && $overallPercentage >= 85.0) {
            return EnvironmentalProject::STAGE_INDEPENDENTLY_VERIFIED;
        }

        if ($overallPercentage >= 65.0) {
            return EnvironmentalProject::STAGE_VERIFICATION_PREPARATION;
        }

        if ($overallPercentage >= 50.0) {
            return EnvironmentalProject::STAGE_METHODOLOGY_ASSESSMENT;
        }

        if ($overallPercentage >= 35.0) {
            return EnvironmentalProject::STAGE_UNDER_TECHNICAL_REVIEW;
        }

        return EnvironmentalProject::STAGE_DOCUMENTATION_REQUIRED;
    }

    /**
     * Generate clear gap analysis narrative
     */
    private function generateGapAnalysis(array $dimensions, array $missingFields, bool $isComplete): string
    {
        $paragraphs = [];

        if (!$isComplete) {
            $fieldList = implode(', ', array_map(fn($f) => str_replace('_', ' ', $f), $missingFields));
            $paragraphs[] = "ASSESSMENT INCOMPLETE: Essential mandatory inputs ({$fieldList}) are missing. Numerical readiness percentages reflect preliminary evaluation only and cannot establish regulatory eligibility.";
        } else {
            $paragraphs[] = "DATA COMPLETENESS VERIFIED: Core project parameters, baseline scenarios, and crediting boundaries are documented.";
        }

        $deductionsFound = [];
        foreach ($dimensions as $dimKey => $dim) {
            if (!empty($dim['deduction_reasons'])) {
                foreach ($dim['deduction_reasons'] as $reason) {
                    $deductionsFound[] = "• [" . strtoupper(str_replace('_', ' ', $dimKey)) . "] {$reason}";
                }
            }
        }

        if (!empty($deductionsFound)) {
            $paragraphs[] = "Identified Deficiencies & Scoring Deductions:\n" . implode("\n", $deductionsFound);
        } else {
            $paragraphs[] = "No scoring deductions recorded; project meets all evaluated benchmarks for its current operational tier.";
        }

        return implode("\n\n", $paragraphs);
    }

    /**
     * Generate prioritized actionable recommendations
     */
    private function generateRecommendations(array $dimensions, array $missingFields, array $project): string
    {
        $recs = [];
        $priority = 1;

        if (!empty($missingFields)) {
            foreach ($missingFields as $field) {
                $recs[] = "{$priority}. Complete Mandatory Parameter: Supply valid '" . str_replace('_', ' ', $field) . "' in project configuration.";
                $priority++;
            }
        }

        foreach ($dimensions as $dimKey => $dim) {
            if (!empty($dim['missing_evidence'])) {
                foreach ($dim['missing_evidence'] as $item) {
                    $recs[] = "{$priority}. [" . ucwords(str_replace('_', ' ', $dimKey)) . "]: {$item}";
                    $priority++;
                }
            }
        }

        if (empty($recs)) {
            return "Project is fully documented and audit-ready. Proceed with scheduled third-party monitoring cycles.";
        }

        return implode("\n", $recs);
    }
}
