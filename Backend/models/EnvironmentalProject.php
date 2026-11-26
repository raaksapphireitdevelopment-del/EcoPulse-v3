<?php
declare(strict_types=1);

namespace EcoPulse\Models;

use EcoPulse\Core\Database;
use PDO;

class EnvironmentalProject
{
    /**
     * Allowed classification tiers
     */
    public const CLASSIFICATION_OPERATIONAL_REDUCTION = 'operational_reduction';
    public const CLASSIFICATION_PROJECT_CANDIDATE = 'project_candidate';
    public const CLASSIFICATION_VERIFIED_ASSET = 'verified_environmental_asset';
    public const CLASSIFICATION_ISSUED_CREDIT = 'issued_carbon_credit';

    public const CLASSIFICATIONS = [
        self::CLASSIFICATION_OPERATIONAL_REDUCTION,
        self::CLASSIFICATION_PROJECT_CANDIDATE,
        self::CLASSIFICATION_VERIFIED_ASSET,
        self::CLASSIFICATION_ISSUED_CREDIT,
    ];

    /**
     * Allowed project operational statuses
     */
    public const STATUS_CONCEPT = 'concept';
    public const STATUS_FEASIBILITY = 'feasibility';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_MONITORED = 'monitored';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_ACTIVE = 'active';

    public const STATUSES = [
        self::STATUS_CONCEPT,
        self::STATUS_FEASIBILITY,
        self::STATUS_VALIDATED,
        self::STATUS_MONITORED,
        self::STATUS_VERIFIED,
        self::STATUS_ACTIVE,
    ];

    /**
     * Allowed readiness stages
     */
    public const STAGE_INITIAL_ASSESSMENT = 'initial_assessment';
    public const STAGE_DOCUMENTATION_REQUIRED = 'documentation_required';
    public const STAGE_UNDER_TECHNICAL_REVIEW = 'under_technical_review';
    public const STAGE_METHODOLOGY_ASSESSMENT = 'methodology_assessment';
    public const STAGE_VERIFICATION_PREPARATION = 'verification_preparation';
    public const STAGE_INDEPENDENTLY_VERIFIED = 'independently_verified';
    public const STAGE_ISSUED = 'issued';

    public const READINESS_STAGES = [
        self::STAGE_INITIAL_ASSESSMENT,
        self::STAGE_DOCUMENTATION_REQUIRED,
        self::STAGE_UNDER_TECHNICAL_REVIEW,
        self::STAGE_METHODOLOGY_ASSESSMENT,
        self::STAGE_VERIFICATION_PREPARATION,
        self::STAGE_INDEPENDENTLY_VERIFIED,
        self::STAGE_ISSUED,
    ];

    /**
     * List projects for an organization with joined domain, facility, and latest readiness info.
     */
    public static function list(int $orgId, array $filters = [], int $page = 1, int $limit = 50): array
    {
        $db = Database::getInstance();
        $conditions = ['ep.`organization_id` = :org_id'];
        $params = [':org_id' => $orgId];

        if (!empty($filters['domain_profile_id'])) {
            $conditions[] = 'ep.`domain_profile_id` = :domain_profile_id';
            $params[':domain_profile_id'] = (int)$filters['domain_profile_id'];
        }

        if (!empty($filters['facility_id'])) {
            $conditions[] = 'ep.`facility_id` = :facility_id';
            $params[':facility_id'] = (int)$filters['facility_id'];
        }

        if (!empty($filters['classification'])) {
            $conditions[] = 'ep.`classification` = :classification';
            $params[':classification'] = $filters['classification'];
        }

        if (!empty($filters['project_status'])) {
            $conditions[] = 'ep.`project_status` = :project_status';
            $params[':project_status'] = $filters['project_status'];
        }

        if (!empty($filters['readiness_stage'])) {
            $conditions[] = 'ep.`readiness_stage` = :readiness_stage';
            $params[':readiness_stage'] = $filters['readiness_stage'];
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(ep.`project_title` LIKE :search OR ep.`project_code` LIKE :search OR ep.`methodology` LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        if (empty($filters['include_archived'])) {
            $conditions[] = 'ep.`is_archived` = 0';
        }

        $whereClause = implode(' AND ', $conditions);
        $offset = max(0, ($page - 1) * $limit);

        $sql = "SELECT ep.*,
                       d.name as domain_name, d.code as domain_code, d.icon as domain_icon,
                       f.name as facility_name,
                       u.name as creator_name,
                       pra.overall_readiness_percentage,
                       pra.readiness_stage as assessment_readiness_stage,
                       pra.is_complete as assessment_is_complete,
                       pra.completeness_status as assessment_completeness_status,
                       pra.assessment_version,
                       (SELECT COUNT(*) FROM `project_documents` WHERE `project_id` = ep.`id` AND `is_archived` = 0) as document_count,
                       (SELECT COUNT(*) FROM `project_documents` WHERE `project_id` = ep.`id` AND `review_status` = 'accepted' AND `is_archived` = 0) as accepted_document_count
                FROM `environmental_projects` ep
                JOIN `domain_profiles` dp ON ep.`domain_profile_id` = dp.`id`
                JOIN `domains` d ON dp.`domain_id` = d.`id`
                LEFT JOIN `facilities` f ON ep.`facility_id` = f.`id`
                LEFT JOIN `users` u ON ep.`created_by` = u.`id`
                LEFT JOIN `project_readiness_assessments` pra ON ep.`id` = pra.`project_id` AND pra.`is_current` = 1
                WHERE {$whereClause}
                ORDER BY ep.`created_at` DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Count total matching items
        $countSql = "SELECT COUNT(*) FROM `environmental_projects` ep WHERE {$whereClause}";
        $countStmt = $db->prepare($countSql);
        foreach ($params as $k => $v) {
            $countStmt->bindValue($k, $v);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => $total > 0 ? (int)ceil($total / $limit) : 1
        ];
    }

    /**
     * Find single project by ID and organization ID
     */
    public static function findById(int $id, int $orgId, bool $includeArchived = false): ?array
    {
        $db = Database::getInstance();
        $archivedClause = $includeArchived ? '' : ' AND ep.`is_archived` = 0';
        $sql = "SELECT ep.*,
                       d.name as domain_name, d.code as domain_code, d.icon as domain_icon,
                       f.name as facility_name,
                       u.name as creator_name,
                       pra.id as latest_assessment_id,
                       pra.overall_readiness_percentage,
                       pra.additionality_score,
                       pra.baseline_rigor_score,
                       pra.monitoring_readiness_score,
                       pra.permanence_risk_score,
                       pra.verification_readiness_score,
                       pra.readiness_stage as assessment_readiness_stage,
                       pra.is_complete as assessment_is_complete,
                       pra.completeness_status as assessment_completeness_status,
                       pra.missing_required_fields as assessment_missing_fields,
                       pra.gap_analysis_summary,
                       pra.recommendations,
                       pra.assessment_version,
                       pra.created_at as assessed_at,
                       (SELECT COUNT(*) FROM `project_documents` WHERE `project_id` = ep.`id` AND `is_archived` = 0) as document_count,
                       (SELECT COUNT(*) FROM `project_documents` WHERE `project_id` = ep.`id` AND `review_status` = 'accepted' AND `is_archived` = 0) as accepted_document_count
                FROM `environmental_projects` ep
                JOIN `domain_profiles` dp ON ep.`domain_profile_id` = dp.`id`
                JOIN `domains` d ON dp.`domain_id` = d.`id`
                LEFT JOIN `facilities` f ON ep.`facility_id` = f.`id`
                LEFT JOIN `users` u ON ep.`created_by` = u.`id`
                LEFT JOIN `project_readiness_assessments` pra ON ep.`id` = pra.`project_id` AND pra.`is_current` = 1
                WHERE ep.`id` = :id AND ep.`organization_id` = :org_id {$archivedClause}";

        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $id, ':org_id' => $orgId]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$project) {
            return null;
        }

        if (!empty($project['assessment_missing_fields'])) {
            $project['assessment_missing_fields'] = json_decode($project['assessment_missing_fields'], true) ?: [];
        }

        return $project;
    }

    /**
     * Create a new environmental project with classification governance.
     */
    public static function create(array $data, int $userId): array
    {
        $db = Database::getInstance();

        $classification = $data['classification'] ?? self::CLASSIFICATION_OPERATIONAL_REDUCTION;
        if (!in_array($classification, self::CLASSIFICATIONS, true)) {
            $classification = self::CLASSIFICATION_OPERATIONAL_REDUCTION;
        }

        // Governance: Direct creation as verified or issued is prohibited without prior accepted evidence
        if ($classification === self::CLASSIFICATION_VERIFIED_ASSET || $classification === self::CLASSIFICATION_ISSUED_CREDIT) {
            throw new \InvalidArgumentException("Projects cannot be directly created as '{$classification}'. Projects must be registered as 'operational_reduction' or 'project_candidate' and transitioned only after independent evidence documents are uploaded, reviewed, and accepted.");
        }

        // Governance: operational reduction must not have registry serial or claim credit status
        $registryName = !empty($data['registry_name']) ? trim($data['registry_name']) : null;
        $registrySerial = !empty($data['registry_serial_or_id']) ? trim($data['registry_serial_or_id']) : null;
        $thirdPartyVerifier = !empty($data['third_party_verifier']) ? trim($data['third_party_verifier']) : null;

        if ($classification === self::CLASSIFICATION_OPERATIONAL_REDUCTION) {
            $registryName = null;
            $registrySerial = null;
        }

        // Generate project code if not provided
        $projectCode = !empty($data['project_code']) ? trim($data['project_code']) : null;
        if (empty($projectCode)) {
            $projectCode = 'PRJ-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        }

        $sql = "INSERT INTO `environmental_projects` (
            `organization_id`, `domain_profile_id`, `facility_id`, `project_title`,
            `description`, `project_objectives`, `project_category`, `project_code`,
            `classification`, `methodology`, `methodology_version`, `baseline_scenario_description`,
            `expected_environmental_outcomes`, `location_country`, `location_region`,
            `project_owner_name`, `responsible_team`, `project_start_date`, `expected_completion_date`,
            `crediting_period_start`, `crediting_period_end`, `estimated_annual_mitigation_tco2e`,
            `third_party_verifier`, `registry_name`, `registry_serial_or_id`,
            `project_status`, `readiness_stage`, `created_by`
        ) VALUES (
            :organization_id, :domain_profile_id, :facility_id, :project_title,
            :description, :project_objectives, :project_category, :project_code,
            :classification, :methodology, :methodology_version, :baseline_scenario_description,
            :expected_environmental_outcomes, :location_country, :location_region,
            :project_owner_name, :responsible_team, :project_start_date, :expected_completion_date,
            :crediting_period_start, :crediting_period_end, :estimated_annual_mitigation_tco2e,
            :third_party_verifier, :registry_name, :registry_serial_or_id,
            :project_status, :readiness_stage, :created_by
        )";

        $pdo = $db->getPdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':organization_id' => $data['organization_id'],
                ':domain_profile_id' => $data['domain_profile_id'],
                ':facility_id' => !empty($data['facility_id']) ? (int)$data['facility_id'] : null,
                ':project_title' => $data['project_title'],
                ':description' => $data['description'] ?? null,
                ':project_objectives' => $data['project_objectives'] ?? null,
                ':project_category' => $data['project_category'] ?? 'energy_efficiency',
                ':project_code' => $projectCode,
                ':classification' => $classification,
                ':methodology' => $data['methodology'] ?? null,
                ':methodology_version' => $data['methodology_version'] ?? null,
                ':baseline_scenario_description' => $data['baseline_scenario_description'] ?? null,
                ':expected_environmental_outcomes' => $data['expected_environmental_outcomes'] ?? null,
                ':location_country' => $data['location_country'] ?? 'Global',
                ':location_region' => $data['location_region'] ?? null,
                ':project_owner_name' => $data['project_owner_name'] ?? null,
                ':responsible_team' => $data['responsible_team'] ?? null,
                ':project_start_date' => !empty($data['project_start_date']) ? $data['project_start_date'] : null,
                ':expected_completion_date' => !empty($data['expected_completion_date']) ? $data['expected_completion_date'] : null,
                ':crediting_period_start' => !empty($data['crediting_period_start']) ? $data['crediting_period_start'] : null,
                ':crediting_period_end' => !empty($data['crediting_period_end']) ? $data['crediting_period_end'] : null,
                ':estimated_annual_mitigation_tco2e' => (float)($data['estimated_annual_mitigation_tco2e'] ?? 0.0),
                ':third_party_verifier' => $thirdPartyVerifier,
                ':registry_name' => $registryName,
                ':registry_serial_or_id' => $registrySerial,
                ':project_status' => $data['project_status'] ?? self::STATUS_CONCEPT,
                ':readiness_stage' => self::STAGE_INITIAL_ASSESSMENT,
                ':created_by' => $userId,
            ]);

            $projectId = (int)$pdo->lastInsertId();

            // Write initial classification history record
            self::recordClassificationHistory(
                $projectId,
                (int)$data['organization_id'],
                null,
                $classification,
                'Initial project registration',
                'Project registered in EcoPulse platform',
                $userId
            );

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return self::findById($projectId, (int)$data['organization_id']);
    }

    /**
     * Update project metadata (excluding classification which requires dedicated workflow).
     */
    public static function update(int $id, int $orgId, array $data): bool
    {
        $db = Database::getInstance();
        $fields = [];
        $params = [':id' => $id, ':org_id' => $orgId];

        $allowedFields = [
            'domain_profile_id', 'facility_id', 'project_title', 'description',
            'project_objectives', 'project_category', 'project_code',
            'methodology', 'methodology_version', 'baseline_scenario_description',
            'expected_environmental_outcomes', 'location_country', 'location_region',
            'project_owner_name', 'responsible_team', 'project_start_date', 'expected_completion_date',
            'crediting_period_start', 'crediting_period_end', 'estimated_annual_mitigation_tco2e',
            'third_party_verifier', 'registry_name', 'registry_serial_or_id',
            'project_status'
        ];

        foreach ($allowedFields as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "`{$f}` = :{$f}";
                $params[":{$f}"] = $data[$f];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE `environmental_projects` SET " . implode(', ', $fields) . " WHERE `id` = :id AND `organization_id` = :org_id";
        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Permission-controlled classification transition with mandatory justification and audit trail.
     */
    public static function updateClassification(
        int $id,
        int $orgId,
        string $newClassification,
        string $reason,
        ?string $evidenceNotes,
        int $userId
    ): array {
        if (!in_array($newClassification, self::CLASSIFICATIONS, true)) {
            throw new \InvalidArgumentException("Invalid project classification: {$newClassification}");
        }

        $project = self::findById($id, $orgId);
        if (!$project) {
            throw new \RuntimeException("Project not found");
        }

        $previousClassification = $project['classification'];
        $db = Database::getInstance();

        // Strict verification rules:
        // 1. Cannot transition to verified_environmental_asset without methodology, verifier, and ACCEPTED verification report
        if ($newClassification === self::CLASSIFICATION_VERIFIED_ASSET) {
            if (empty($project['methodology']) || empty($project['third_party_verifier'])) {
                throw new \InvalidArgumentException("Transition to 'verified_environmental_asset' requires a documented methodology and third-party verifier.");
            }

            $checkSql = "SELECT COUNT(*) FROM `project_documents`
                         WHERE `project_id` = :pid AND `organization_id` = :oid
                           AND `review_status` = 'accepted' AND `is_archived` = 0
                           AND `document_type` IN ('verification_report', 'validation_report')";
            $stmtCheck = $db->prepare($checkSql);
            $stmtCheck->execute([':pid' => $id, ':oid' => $orgId]);
            if ((int)$stmtCheck->fetchColumn() === 0) {
                throw new \InvalidArgumentException("Transition to 'verified_environmental_asset' requires an accepted independent verification or validation report in evidence documents.");
            }
        }

        // 2. Cannot transition to issued_carbon_credit without registry details, methodology, serial ID, and ACCEPTED issuance evidence
        if ($newClassification === self::CLASSIFICATION_ISSUED_CREDIT) {
            if (empty($project['registry_name']) || empty($project['registry_serial_or_id']) || empty($project['methodology'])) {
                throw new \InvalidArgumentException("Transition to 'issued_carbon_credit' requires verified registry name, credit serial numbers, and methodology reference.");
            }

            $checkSql = "SELECT COUNT(*) FROM `project_documents`
                         WHERE `project_id` = :pid AND `organization_id` = :oid
                           AND `review_status` = 'accepted' AND `is_archived` = 0
                           AND `document_type` IN ('credit_issuance_notice', 'registry_certificate')";
            $stmtCheck = $db->prepare($checkSql);
            $stmtCheck->execute([':pid' => $id, ':oid' => $orgId]);
            if ((int)$stmtCheck->fetchColumn() === 0) {
                throw new \InvalidArgumentException("Transition to 'issued_carbon_credit' requires an accepted credit issuance notice or registry certificate in evidence documents.");
            }
        }

        // 3. Operational reduction must not retain registry serials
        $pdo = $db->getPdo();
        $pdo->beginTransaction();
        try {
            if ($newClassification === self::CLASSIFICATION_OPERATIONAL_REDUCTION) {
                $updateSql = "UPDATE `environmental_projects`
                              SET `classification` = :classification,
                                  `registry_name` = NULL,
                                  `registry_serial_or_id` = NULL
                              WHERE `id` = :id AND `organization_id` = :org_id";
            } else {
                $updateSql = "UPDATE `environmental_projects`
                              SET `classification` = :classification
                              WHERE `id` = :id AND `organization_id` = :org_id";
            }

            $stmt = $pdo->prepare($updateSql);
            $stmt->execute([
                ':classification' => $newClassification,
                ':id' => $id,
                ':org_id' => $orgId
            ]);

            // Record immutable audit history
            self::recordClassificationHistory(
                $id,
                $orgId,
                $previousClassification,
                $newClassification,
                $reason,
                $evidenceNotes,
                $userId
            );

            // If project is transitioned to an ineligible tier, automatically suspend active marketplace listings
            if ($newClassification === self::CLASSIFICATION_OPERATIONAL_REDUCTION || $newClassification === self::CLASSIFICATION_PROJECT_CANDIDATE) {
                $suspSql = "UPDATE `marketplace_listings`
                            SET `status` = 'suspended',
                                `suspension_reason` = :s_reason,
                                `reviewed_at` = NOW()
                            WHERE `project_id` = :pid AND `status` IN ('published', 'pending_review')";
                $suspStmt = $pdo->prepare($suspSql);
                $suspStmt->execute([
                    ':s_reason' => "Underlying project classification transitioned to ineligible tier: {$newClassification}",
                    ':pid' => $id
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return self::findById($id, $orgId);
    }

    /**
     * Dedicated protected readiness-stage transition service with audit trail.
     */
    public static function updateReadinessStage(
        int $id,
        int $orgId,
        string $newStage,
        string $reason,
        int $userId
    ): array {
        if (!in_array($newStage, self::READINESS_STAGES, true)) {
            throw new \InvalidArgumentException("Invalid readiness stage: {$newStage}");
        }

        $project = self::findById($id, $orgId);
        if (!$project) {
            throw new \RuntimeException("Project not found");
        }

        $previousStage = $project['readiness_stage'];
        if ($previousStage === $newStage) {
            return $project;
        }

        // Governance: cannot jump directly to issued stage without prior independent verification
        if ($newStage === self::STAGE_ISSUED && $previousStage !== self::STAGE_INDEPENDENTLY_VERIFIED) {
            throw new \InvalidArgumentException("Cannot transition to 'issued' stage without first reaching 'independently_verified'.");
        }

        $db = Database::getInstance();
        $pdo = $db->getPdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE `environmental_projects` SET `readiness_stage` = :stage WHERE `id` = :id AND `organization_id` = :org_id");
            $stmt->execute([':stage' => $newStage, ':id' => $id, ':org_id' => $orgId]);

            // Record stage transition in immutable project_stage_history
            $histStmt = $pdo->prepare("INSERT INTO `project_stage_history` (
                `project_id`, `organization_id`, `previous_stage`, `new_stage`, `reason`, `changed_by`, `created_at`
            ) VALUES (
                :pid, :oid, :prev, :new, :reason, :uid, NOW()
            )");
            $histStmt->execute([
                ':pid' => $id,
                ':oid' => $orgId,
                ':prev' => $previousStage,
                ':new' => $newStage,
                ':reason' => $reason,
                ':uid' => $userId
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return self::findById($id, $orgId);
    }

    /**
     * Retrieve stage transition audit history
     */
    public static function getStageHistory(int $projectId, int $orgId): array
    {
        $db = Database::getInstance();
        $sql = "SELECT psh.*, u.name as changed_by_name, u.email as changed_by_email
                FROM `project_stage_history` psh
                JOIN `users` u ON psh.`changed_by` = u.`id`
                WHERE psh.`project_id` = :project_id AND psh.`organization_id` = :org_id
                ORDER BY psh.`created_at` DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([':project_id' => $projectId, ':org_id' => $orgId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Record entry in immutable classification history table.
     */
    public static function recordClassificationHistory(
        int $projectId,
        int $orgId,
        ?string $prevClassification,
        string $newClassification,
        string $reason,
        ?string $evidenceNotes,
        int $userId
    ): void {
        $db = Database::getInstance();
        $sql = "INSERT INTO `project_classification_history` (
            `project_id`, `organization_id`, `previous_classification`,
            `new_classification`, `reason`, `supporting_evidence_notes`,
            `changed_by`, `created_at`
        ) VALUES (
            :project_id, :organization_id, :prev, :new, :reason, :notes, :user_id, NOW()
        )";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':project_id' => $projectId,
            ':organization_id' => $orgId,
            ':prev' => $prevClassification,
            ':new' => $newClassification,
            ':reason' => $reason,
            ':notes' => $evidenceNotes,
            ':user_id' => $userId
        ]);
    }

    /**
     * Retrieve classification history timeline
     */
    public static function getClassificationHistory(int $projectId, int $orgId): array
    {
        $db = Database::getInstance();
        $sql = "SELECT pch.*, u.name as changed_by_name, u.email as changed_by_email
                FROM `project_classification_history` pch
                JOIN `users` u ON pch.`changed_by` = u.`id`
                WHERE pch.`project_id` = :project_id AND pch.`organization_id` = :org_id
                ORDER BY pch.`created_at` DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([':project_id' => $projectId, ':org_id' => $orgId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Soft delete project (archives project while preserving audit logs, reviews, versions, and assessments)
     */
    public static function delete(int $id, int $orgId): bool
    {
        $db = Database::getInstance();
        $pdo = $db->getPdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE `environmental_projects` SET `is_archived` = 1, `archived_at` = NOW() WHERE `id` = :id AND `organization_id` = :org_id");
            $res = $stmt->execute([':id' => $id, ':org_id' => $orgId]);

            // Archive all associated marketplace listings
            $mStmt = $pdo->prepare("UPDATE `marketplace_listings`
                                    SET `status` = 'archived',
                                        `is_archived` = 1,
                                        `suspension_reason` = 'Underlying project archived'
                                    WHERE `project_id` = :pid AND `organization_id` = :org_id AND `is_archived` = 0");
            $mStmt->execute([':pid' => $id, ':org_id' => $orgId]);

            $pdo->commit();
            return $res;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
