<?php
declare(strict_types=1);

namespace EcoPulse\Models;

use EcoPulse\Core\Database;
use PDO;

class ProjectDocument
{
    public const TYPE_PDD = 'pdd';
    public const TYPE_VALIDATION_REPORT = 'validation_report';
    public const TYPE_VERIFICATION_REPORT = 'verification_report';
    public const TYPE_ISSUANCE_RECORD = 'issuance_record';
    public const TYPE_MONITORING_REPORT = 'monitoring_report';
    public const TYPE_OTHER = 'other';

    public const DOCUMENT_TYPES = [
        self::TYPE_PDD,
        self::TYPE_VALIDATION_REPORT,
        self::TYPE_VERIFICATION_REPORT,
        self::TYPE_ISSUANCE_RECORD,
        self::TYPE_MONITORING_REPORT,
        self::TYPE_OTHER,
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_REQUIRES_REVISION = 'requires_revision';

    public const REVIEW_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_ACCEPTED,
        self::STATUS_REJECTED,
        self::STATUS_REQUIRES_REVISION,
    ];

    /**
     * List active documents for a project
     */
    public static function listByProject(int $projectId, int $orgId, bool $includeArchived = false): array
    {
        $db = Database::getInstance();
        $archivedCondition = $includeArchived ? '' : 'AND pd.`is_archived` = 0';

        $sql = "SELECT pd.*,
                       u.name as uploader_name, u.email as uploader_email,
                       r.name as reviewer_name, r.email as reviewer_email,
                       (SELECT COUNT(*) FROM `project_document_versions` WHERE `document_id` = pd.`id`) as version_count,
                       (SELECT COUNT(*) FROM `document_reviews` WHERE `document_id` = pd.`id`) as review_count
                FROM `project_documents` pd
                LEFT JOIN `users` u ON pd.`uploaded_by` = u.`id`
                LEFT JOIN `users` r ON pd.`reviewed_by` = r.`id`
                WHERE pd.`project_id` = :project_id AND pd.`organization_id` = :org_id {$archivedCondition}
                ORDER BY pd.`created_at` DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute([':project_id' => $projectId, ':org_id' => $orgId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find single document by ID and organization ID
     */
    public static function findById(int $id, int $orgId): ?array
    {
        $db = Database::getInstance();
        $sql = "SELECT pd.*,
                       ep.project_title, ep.project_code, ep.classification as project_classification,
                       u.name as uploader_name, u.email as uploader_email,
                       r.name as reviewer_name, r.email as reviewer_email
                FROM `project_documents` pd
                JOIN `environmental_projects` ep ON pd.`project_id` = ep.`id`
                LEFT JOIN `users` u ON pd.`uploaded_by` = u.`id`
                LEFT JOIN `users` r ON pd.`reviewed_by` = r.`id`
                WHERE pd.`id` = :id AND pd.`organization_id` = :org_id";

        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $id, ':org_id' => $orgId]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);
        return $doc ?: null;
    }

    /**
     * Create a new document record and its initial version
     */
    public static function create(array $data, int $userId): array
    {
        $db = Database::getInstance();

        $documentType = $data['document_type'] ?? self::TYPE_OTHER;
        if (!in_array($documentType, self::DOCUMENT_TYPES, true)) {
            $documentType = self::TYPE_OTHER;
        }

        $sql = "INSERT INTO `project_documents` (
            `project_id`, `organization_id`, `document_type`, `storage_identifier`,
            `file_title`, `original_filename`, `file_path`, `file_size_kb`,
            `mime_type`, `sha256_hash`, `current_version`, `review_status`,
            `description`, `uploaded_by`, `created_at`
        ) VALUES (
            :project_id, :organization_id, :document_type, :storage_identifier,
            :file_title, :original_filename, :file_path, :file_size_kb,
            :mime_type, :sha256_hash, 1, 'pending',
            :description, :uploaded_by, NOW()
        )";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':project_id' => $data['project_id'],
            ':organization_id' => $data['organization_id'],
            ':document_type' => $documentType,
            ':storage_identifier' => $data['storage_identifier'],
            ':file_title' => $data['file_title'],
            ':original_filename' => $data['original_filename'],
            ':file_path' => $data['file_path'],
            ':file_size_kb' => (int)$data['file_size_kb'],
            ':mime_type' => $data['mime_type'],
            ':sha256_hash' => $data['sha256_hash'],
            ':description' => $data['description'] ?? null,
            ':uploaded_by' => $userId
        ]);

        $documentId = (int)$db->lastInsertId();

        // Save version 1 in project_document_versions
        $versionSql = "INSERT INTO `project_document_versions` (
            `document_id`, `project_id`, `organization_id`, `version_number`,
            `storage_identifier`, `original_filename`, `file_size_kb`,
            `mime_type`, `sha256_hash`, `change_summary`, `uploaded_by`, `created_at`
        ) VALUES (
            :document_id, :project_id, :organization_id, 1,
            :storage_identifier, :original_filename, :file_size_kb,
            :mime_type, :sha256_hash, 'Initial document upload', :uploaded_by, NOW()
        )";
        $vStmt = $db->prepare($versionSql);
        $vStmt->execute([
            ':document_id' => $documentId,
            ':project_id' => $data['project_id'],
            ':organization_id' => $data['organization_id'],
            ':storage_identifier' => $data['storage_identifier'],
            ':original_filename' => $data['original_filename'],
            ':file_size_kb' => (int)$data['file_size_kb'],
            ':mime_type' => $data['mime_type'],
            ':sha256_hash' => $data['sha256_hash'],
            ':uploaded_by' => $userId
        ]);

        return self::findById($documentId, (int)$data['organization_id']);
    }

    /**
     * Replace document with a new version, preserving previous version history
     */
    public static function replaceVersion(int $documentId, int $orgId, array $versionData, int $userId): array
    {
        $db = Database::getInstance();
        $doc = self::findById($documentId, $orgId);
        if (!$doc) {
            throw new \RuntimeException("Document not found");
        }

        $newVersionNumber = (int)$doc['current_version'] + 1;

        $pdo = $db->getPdo();
        $pdo->beginTransaction();
        try {
            // 1. Insert new version into project_document_versions
            $versionSql = "INSERT INTO `project_document_versions` (
                `document_id`, `project_id`, `organization_id`, `version_number`,
                `storage_identifier`, `original_filename`, `file_size_kb`,
                `mime_type`, `sha256_hash`, `change_summary`, `uploaded_by`, `created_at`
            ) VALUES (
                :document_id, :project_id, :organization_id, :version_number,
                :storage_identifier, :original_filename, :file_size_kb,
                :mime_type, :sha256_hash, :change_summary, :uploaded_by, NOW()
            )";
            $vStmt = $pdo->prepare($versionSql);
            $vStmt->execute([
                ':document_id' => $documentId,
                ':project_id' => $doc['project_id'],
                ':organization_id' => $orgId,
                ':version_number' => $newVersionNumber,
                ':storage_identifier' => $versionData['storage_identifier'],
                ':original_filename' => $versionData['original_filename'],
                ':file_size_kb' => (int)$versionData['file_size_kb'],
                ':mime_type' => $versionData['mime_type'],
                ':sha256_hash' => $versionData['sha256_hash'],
                ':change_summary' => $versionData['change_summary'] ?? 'Version replacement',
                ':uploaded_by' => $userId
            ]);

            // 2. Update current document record and reset review status to pending
            $updateSql = "UPDATE `project_documents` SET
                `current_version` = :new_version,
                `storage_identifier` = :storage_identifier,
                `original_filename` = :original_filename,
                `file_path` = :file_path,
                `file_size_kb` = :file_size_kb,
                `mime_type` = :mime_type,
                `sha256_hash` = :sha256_hash,
                `review_status` = 'pending',
                `review_comments` = NULL,
                `reviewed_by` = NULL,
                `reviewed_at` = NULL,
                `uploaded_by` = :uploaded_by
                WHERE `id` = :id AND `organization_id` = :org_id";

            $uStmt = $pdo->prepare($updateSql);
            $uStmt->execute([
                ':new_version' => $newVersionNumber,
                ':storage_identifier' => $versionData['storage_identifier'],
                ':original_filename' => $versionData['original_filename'],
                ':file_path' => $versionData['file_path'],
                ':file_size_kb' => (int)$versionData['file_size_kb'],
                ':mime_type' => $versionData['mime_type'],
                ':sha256_hash' => $versionData['sha256_hash'],
                ':uploaded_by' => $userId,
                ':id' => $documentId,
                ':org_id' => $orgId
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return self::findById($documentId, $orgId);
    }

    /**
     * Review document: record review decision in document_reviews and update project_documents
     */
    public static function review(
        int $documentId,
        int $orgId,
        string $status,
        string $comments,
        ?string $revisionReason,
        int $reviewerId
    ): array {
        if (!in_array($status, self::REVIEW_STATUSES, true)) {
            throw new \InvalidArgumentException("Invalid document review status: {$status}");
        }

        $doc = self::findById($documentId, $orgId);
        if (!$doc) {
            throw new \RuntimeException("Document not found");
        }

        $db = Database::getInstance();
        $pdo = $db->getPdo();
        $pdo->beginTransaction();
        try {
            // 1. Insert review decision into immutable document_reviews
            $reviewSql = "INSERT INTO `document_reviews` (
                `document_id`, `document_version`, `project_id`, `organization_id`,
                `reviewer_id`, `review_status`, `review_comments`,
                `rejection_or_revision_reason`, `created_at`
            ) VALUES (
                :document_id, :document_version, :project_id, :organization_id,
                :reviewer_id, :review_status, :review_comments,
                :rejection_or_revision_reason, NOW()
            )";
            $rStmt = $pdo->prepare($reviewSql);
            $rStmt->execute([
                ':document_id' => $documentId,
                ':document_version' => (int)$doc['current_version'],
                ':project_id' => $doc['project_id'],
                ':organization_id' => $orgId,
                ':reviewer_id' => $reviewerId,
                ':review_status' => $status,
                ':review_comments' => $comments,
                ':rejection_or_revision_reason' => $revisionReason
            ]);

            // 2. Update status in project_documents
            $updateSql = "UPDATE `project_documents` SET
                `review_status` = :review_status,
                `review_comments` = :review_comments,
                `reviewed_by` = :reviewed_by,
                `reviewed_at` = NOW()
                WHERE `id` = :id AND `organization_id` = :org_id";

            $uStmt = $pdo->prepare($updateSql);
            $uStmt->execute([
                ':review_status' => $status,
                ':review_comments' => $comments,
                ':reviewed_by' => $reviewerId,
                ':id' => $documentId,
                ':org_id' => $orgId
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return self::findById($documentId, $orgId);
    }

    /**
     * Get version history for a document
     */
    public static function getVersions(int $documentId, int $orgId): array
    {
        $db = Database::getInstance();
        $sql = "SELECT pdv.*, u.name as uploader_name, u.email as uploader_email
                FROM `project_document_versions` pdv
                LEFT JOIN `users` u ON pdv.`uploaded_by` = u.`id`
                WHERE pdv.`document_id` = :doc_id AND pdv.`organization_id` = :org_id
                ORDER BY pdv.`version_number` DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([':doc_id' => $documentId, ':org_id' => $orgId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get review history for a document
     */
    public static function getReviews(int $documentId, int $orgId): array
    {
        $db = Database::getInstance();
        $sql = "SELECT dr.*, u.name as reviewer_name, u.email as reviewer_email
                FROM `document_reviews` dr
                JOIN `users` u ON dr.`reviewer_id` = u.`id`
                WHERE dr.`document_id` = :doc_id AND dr.`organization_id` = :org_id
                ORDER BY dr.`created_at` DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([':doc_id' => $documentId, ':org_id' => $orgId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Archive document
     */
    public static function archive(int $id, int $orgId): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE `project_documents` SET `is_archived` = 1 WHERE `id` = :id AND `organization_id` = :org_id");
        return $stmt->execute([':id' => $id, ':org_id' => $orgId]);
    }
}
