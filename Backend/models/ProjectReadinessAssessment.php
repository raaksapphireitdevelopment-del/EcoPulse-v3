<?php
declare(strict_types=1);

namespace EcoPulse\Models;

use EcoPulse\Core\Database;
use PDO;

class ProjectReadinessAssessment
{
    /**
     * Get current active readiness assessment for a project with dimension breakdowns
     */
    public static function getCurrentAssessment(int $projectId, int $orgId): ?array
    {
        $db = Database::getInstance();
        $sql = "SELECT pra.*, u.name as assessor_name, u.email as assessor_email
                FROM `project_readiness_assessments` pra
                LEFT JOIN `users` u ON pra.`assessed_by` = u.`id`
                WHERE pra.`project_id` = :project_id AND pra.`organization_id` = :org_id AND pra.`is_current` = 1
                LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->execute([':project_id' => $projectId, ':org_id' => $orgId]);
        $assessment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$assessment) {
            return null;
        }

        return self::hydrateAssessment($assessment, $db);
    }

    /**
     * Get assessment by ID with tenant security check
     */
    public static function getAssessmentById(int $assessmentId, int $orgId): ?array
    {
        $db = Database::getInstance();
        $sql = "SELECT pra.*, u.name as assessor_name, u.email as assessor_email
                FROM `project_readiness_assessments` pra
                LEFT JOIN `users` u ON pra.`assessed_by` = u.`id`
                WHERE pra.`id` = :id AND pra.`organization_id` = :org_id";

        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $assessmentId, ':org_id' => $orgId]);
        $assessment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$assessment) {
            return null;
        }

        return self::hydrateAssessment($assessment, $db);
    }

    /**
     * Get all historical assessments for a project
     */
    public static function getHistory(int $projectId, int $orgId): array
    {
        $db = Database::getInstance();
        $sql = "SELECT pra.`id`, pra.`project_id`, pra.`assessment_version`, pra.`is_current`,
                       pra.`is_complete`, pra.`completeness_status`, pra.`overall_readiness_percentage`,
                       pra.`readiness_stage`, pra.`additionality_score`, pra.`baseline_rigor_score`,
                       pra.`monitoring_readiness_score`, pra.`permanence_risk_score`,
                       pra.`verification_readiness_score`, pra.`created_at`,
                       u.name as assessor_name
                FROM `project_readiness_assessments` pra
                LEFT JOIN `users` u ON pra.`assessed_by` = u.`id`
                WHERE pra.`project_id` = :project_id AND pra.`organization_id` = :org_id
                ORDER BY pra.`assessment_version` DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute([':project_id' => $projectId, ':org_id' => $orgId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Persist new assessment version and preserve all prior historical versions
     */
    public static function saveAssessment(array $assessmentData, array $dimensionScores, int $userId): array
    {
        $db = Database::getInstance();
        $projectId = (int)$assessmentData['project_id'];
        $orgId = (int)$assessmentData['organization_id'];

        // 1. Mark existing assessments as not current
        $deactivateSql = "UPDATE `project_readiness_assessments`
                          SET `is_current` = 0
                          WHERE `project_id` = :project_id AND `organization_id` = :org_id";
        $deactivateStmt = $db->prepare($deactivateSql);
        $deactivateStmt->execute([':project_id' => $projectId, ':org_id' => $orgId]);

        // 2. Determine next assessment version
        $versionSql = "SELECT COALESCE(MAX(`assessment_version`), 0) + 1 FROM `project_readiness_assessments` WHERE `project_id` = :project_id";
        $vStmt = $db->prepare($versionSql);
        $vStmt->execute([':project_id' => $projectId]);
        $nextVersion = (int)$vStmt->fetchColumn();

        // 3. Insert new current assessment
        $insertSql = "INSERT INTO `project_readiness_assessments` (
            `project_id`, `organization_id`, `assessment_version`, `is_current`,
            `is_complete`, `completeness_status`, `missing_required_fields`, `weights_used`,
            `dimension_breakdown`, `additionality_score`, `baseline_rigor_score`,
            `monitoring_readiness_score`, `permanence_risk_score`, `verification_readiness_score`,
            `overall_readiness_percentage`, `readiness_stage`, `gap_analysis_summary`,
            `recommendations`, `assessed_by`, `created_at`
        ) VALUES (
            :project_id, :organization_id, :version, 1,
            :is_complete, :completeness_status, :missing_fields, :weights_used,
            :dimension_breakdown, :additionality_score, :baseline_rigor_score,
            :monitoring_readiness_score, :permanence_risk_score, :verification_readiness_score,
            :overall_readiness_percentage, :readiness_stage, :gap_analysis_summary,
            :recommendations, :assessed_by, NOW()
        )";

        $stmt = $db->prepare($insertSql);
        $stmt->execute([
            ':project_id' => $projectId,
            ':organization_id' => $orgId,
            ':version' => $nextVersion,
            ':is_complete' => $assessmentData['is_complete'] ? 1 : 0,
            ':completeness_status' => $assessmentData['completeness_status'],
            ':missing_fields' => !empty($assessmentData['missing_required_fields']) ? json_encode($assessmentData['missing_required_fields'], JSON_UNESCAPED_UNICODE) : null,
            ':weights_used' => json_encode($assessmentData['weights_used'] ?? [], JSON_UNESCAPED_UNICODE),
            ':dimension_breakdown' => json_encode($assessmentData['dimension_breakdown'] ?? [], JSON_UNESCAPED_UNICODE),
            ':additionality_score' => (int)($assessmentData['additionality_score'] ?? 0),
            ':baseline_rigor_score' => (int)($assessmentData['baseline_rigor_score'] ?? 0),
            ':monitoring_readiness_score' => (int)($assessmentData['monitoring_readiness_score'] ?? 0),
            ':permanence_risk_score' => (int)($assessmentData['permanence_risk_score'] ?? 0),
            ':verification_readiness_score' => (int)($assessmentData['verification_readiness_score'] ?? 0),
            ':overall_readiness_percentage' => (float)($assessmentData['overall_readiness_percentage'] ?? 0.0),
            ':readiness_stage' => $assessmentData['readiness_stage'],
            ':gap_analysis_summary' => $assessmentData['gap_analysis_summary'],
            ':recommendations' => $assessmentData['recommendations'],
            ':assessed_by' => $userId
        ]);

        $assessmentId = (int)$db->lastInsertId();

        // 4. Insert dimension line-item scores
        $dimSql = "INSERT INTO `readiness_dimension_scores` (
            `assessment_id`, `dimension_key`, `score`, `weight_percentage`,
            `weighted_score`, `deduction_reasons`, `supporting_evidence_refs`,
            `missing_evidence`, `created_at`
        ) VALUES (
            :assessment_id, :dimension_key, :score, :weight_percentage,
            :weighted_score, :deduction_reasons, :supporting_evidence_refs,
            :missing_evidence, NOW()
        )";
        $dimStmt = $db->prepare($dimSql);

        foreach ($dimensionScores as $dimKey => $dimData) {
            $dimStmt->execute([
                ':assessment_id' => $assessmentId,
                ':dimension_key' => $dimKey,
                ':score' => (int)($dimData['score'] ?? 0),
                ':weight_percentage' => (float)($dimData['weight_percentage'] ?? 0.0),
                ':weighted_score' => (float)($dimData['weighted_score'] ?? 0.0),
                ':deduction_reasons' => !empty($dimData['deduction_reasons']) ? json_encode($dimData['deduction_reasons'], JSON_UNESCAPED_UNICODE) : null,
                ':supporting_evidence_refs' => !empty($dimData['supporting_evidence_refs']) ? json_encode($dimData['supporting_evidence_refs'], JSON_UNESCAPED_UNICODE) : null,
                ':missing_evidence' => !empty($dimData['missing_evidence']) ? json_encode($dimData['missing_evidence'], JSON_UNESCAPED_UNICODE) : null,
            ]);
        }

        // 5. Update automated readiness stage in environmental_projects if not manually locked to verified/issued
        $projStmt = $db->prepare("SELECT `readiness_stage`, `classification` FROM `environmental_projects` WHERE `id` = :id AND `organization_id` = :org_id");
        $projStmt->execute([':id' => $projectId, ':org_id' => $orgId]);
        $currentProj = $projStmt->fetch(PDO::FETCH_ASSOC);

        if ($currentProj) {
            $currentStage = $currentProj['readiness_stage'];
            $newStage = $assessmentData['readiness_stage'];

            // Automated progression is allowed only up to verification_preparation.
            // independently_verified and issued require mandatory human approval.
            $automatedStages = [
                'initial_assessment',
                'documentation_required',
                'under_technical_review',
                'methodology_assessment',
                'verification_preparation'
            ];

            if (in_array($newStage, $automatedStages, true) && !in_array($currentStage, ['independently_verified', 'issued'], true)) {
                $upStageStmt = $db->prepare("UPDATE `environmental_projects` SET `readiness_stage` = :stage WHERE `id` = :id");
                $upStageStmt->execute([':stage' => $newStage, ':id' => $projectId]);
            }
        }

        return self::getAssessmentById($assessmentId, $orgId);
    }

    /**
     * Helper to hydrate JSON fields and join dimension scores
     */
    private static function hydrateAssessment(array $assessment, Database $db): array
    {
        $assessmentId = (int)$assessment['id'];

        if (!empty($assessment['missing_required_fields'])) {
            $assessment['missing_required_fields'] = json_decode($assessment['missing_required_fields'], true) ?: [];
        } else {
            $assessment['missing_required_fields'] = [];
        }

        if (!empty($assessment['weights_used'])) {
            $assessment['weights_used'] = json_decode($assessment['weights_used'], true) ?: [];
        }

        if (!empty($assessment['dimension_breakdown'])) {
            $assessment['dimension_breakdown'] = json_decode($assessment['dimension_breakdown'], true) ?: [];
        }

        // Fetch line-item dimension scores
        $sql = "SELECT * FROM `readiness_dimension_scores` WHERE `assessment_id` = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $assessmentId]);
        $scores = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $dimensions = [];
        foreach ($scores as $s) {
            $s['deduction_reasons'] = !empty($s['deduction_reasons']) ? json_decode($s['deduction_reasons'], true) : [];
            $s['supporting_evidence_refs'] = !empty($s['supporting_evidence_refs']) ? json_decode($s['supporting_evidence_refs'], true) : [];
            $s['missing_evidence'] = !empty($s['missing_evidence']) ? json_decode($s['missing_evidence'], true) : [];
            $dimensions[$s['dimension_key']] = $s;
        }

        $assessment['dimensions'] = $dimensions;
        return $assessment;
    }
}
