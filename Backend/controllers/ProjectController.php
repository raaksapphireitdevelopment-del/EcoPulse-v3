<?php
declare(strict_types=1);

namespace EcoPulse\Controllers;

use EcoPulse\Core\Request;
use EcoPulse\Core\Response;
use EcoPulse\Core\Logger;
use EcoPulse\Models\EnvironmentalProject;
use EcoPulse\Models\ProjectDocument;
use EcoPulse\Models\ProjectReadinessAssessment;
use EcoPulse\Services\ReadinessAssessmentEngine;
use Throwable;

class ProjectController
{
    /**
     * Allowed upload MIME types and extensions
     */
    private const ALLOWED_EXTENSIONS = ['pdf', 'png', 'jpg', 'jpeg', 'docx'];
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/png',
        'image/jpeg',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/zip',
    ];
    private const MAX_FILE_SIZE_BYTES = 10 * 1024 * 1024; // 10 MB

    /**
     * GET /api/projects
     */
    public function index(Request $request): void
    {
        $org = $request->getOrganization();
        $orgId = (int)$org['id'];

        $filters = [
            'domain_profile_id' => $request->getInt('domain_profile_id'),
            'facility_id' => $request->getInt('facility_id'),
            'classification' => $request->getString('classification'),
            'project_status' => $request->getString('project_status'),
            'readiness_stage' => $request->getString('readiness_stage'),
            'search' => $request->getString('search'),
        ];

        $page = max(1, $request->getInt('page', 1));
        $limit = min(100, max(1, $request->getInt('limit', 20)));

        $result = EnvironmentalProject::list($orgId, $filters, $page, $limit);
        Response::json($result, 200, 'Environmental projects retrieved successfully.');
    }

    /**
     * GET /api/projects/:id
     */
    public function show(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $orgId = (int)$org['id'];
        $id = (int)($params['id'] ?? 0);

        $project = EnvironmentalProject::findById($id, $orgId);
        if (!$project) {
            Response::error('Project not found or access denied.', 404, 'NOT_FOUND');
            return;
        }

        // Attach documents and current readiness assessment
        $project['documents'] = ProjectDocument::listByProject($id, $orgId);
        $project['current_readiness'] = ProjectReadinessAssessment::getCurrentAssessment($id, $orgId);

        Response::json($project, 200, 'Project details retrieved successfully.');
    }

    /**
     * POST /api/projects
     */
    public function store(Request $request): void
    {
        $org = $request->getOrganization();
        $user = $request->getUser();
        $orgId = (int)$org['id'];
        $userId = (int)$user['id'];

        $title = $request->getString('project_title');
        $domainProfileId = $request->getInt('domain_profile_id');

        if (empty($title) || $domainProfileId <= 0) {
            Response::error('Missing required fields: project_title and domain_profile_id.', 422, 'VALIDATION_FAILED');
            return;
        }

        $classification = $request->getString('classification', EnvironmentalProject::CLASSIFICATION_OPERATIONAL_REDUCTION);
        if (!in_array($classification, EnvironmentalProject::CLASSIFICATIONS, true)) {
            Response::error("Invalid classification tier: {$classification}.", 422, 'VALIDATION_FAILED');
            return;
        }

        // Governance: Direct creation as verified or issued is prohibited without prior accepted evidence
        if ($classification === EnvironmentalProject::CLASSIFICATION_VERIFIED_ASSET || $classification === EnvironmentalProject::CLASSIFICATION_ISSUED_CREDIT) {
            Response::error("Direct registration as '{$classification}' is prohibited. Projects must be registered as 'operational_reduction' or 'project_candidate' and transitioned only after independent evidence documents are uploaded, reviewed, and accepted.", 422, 'GOVERNANCE_VIOLATION');
            return;
        }

        // Governance: operational_reduction cannot have registry serials or pretend to be carbon credit
        if ($classification === EnvironmentalProject::CLASSIFICATION_OPERATIONAL_REDUCTION) {
            if (!empty($request->getString('registry_serial_or_id'))) {
                Response::error('Operational reductions represent internal efficiency and cannot claim registry serial numbers or carbon credit status.', 422, 'GOVERNANCE_VIOLATION');
                return;
            }
        }

        // Governance: issued_carbon_credit requires registry name, serial ID, and methodology
        if ($classification === EnvironmentalProject::CLASSIFICATION_ISSUED_CREDIT) {
            if (empty($request->getString('registry_name')) || empty($request->getString('registry_serial_or_id')) || empty($request->getString('methodology'))) {
                Response::error('Registration as an issued carbon credit requires verified registry name, credit serial number, and methodology.', 422, 'GOVERNANCE_VIOLATION');
                return;
            }
        }

        $data = [
            'organization_id' => $orgId,
            'domain_profile_id' => $domainProfileId,
            'facility_id' => $request->getInt('facility_id') ?: null,
            'project_title' => $title,
            'description' => $request->getString('description') ?: null,
            'project_objectives' => $request->getString('project_objectives') ?: null,
            'project_category' => $request->getString('project_category') ?: 'energy_efficiency',
            'project_code' => $request->getString('project_code') ?: null,
            'classification' => $classification,
            'methodology' => $request->getString('methodology') ?: null,
            'methodology_version' => $request->getString('methodology_version') ?: null,
            'baseline_scenario_description' => $request->getString('baseline_scenario_description') ?: null,
            'expected_environmental_outcomes' => $request->getString('expected_environmental_outcomes') ?: null,
            'location_country' => $request->getString('location_country') ?: 'Global',
            'location_region' => $request->getString('location_region') ?: null,
            'project_owner_name' => $request->getString('project_owner_name') ?: null,
            'responsible_team' => $request->getString('responsible_team') ?: null,
            'project_start_date' => $request->getString('project_start_date') ?: null,
            'expected_completion_date' => $request->getString('expected_completion_date') ?: null,
            'crediting_period_start' => $request->getString('crediting_period_start') ?: null,
            'crediting_period_end' => $request->getString('crediting_period_end') ?: null,
            'estimated_annual_mitigation_tco2e' => $request->getFloat('estimated_annual_mitigation_tco2e', 0.0),
            'third_party_verifier' => $request->getString('third_party_verifier') ?: null,
            'registry_name' => $request->getString('registry_name') ?: null,
            'registry_serial_or_id' => $request->getString('registry_serial_or_id') ?: null,
            'project_status' => $request->getString('project_status') ?: EnvironmentalProject::STATUS_CONCEPT,
        ];

        try {
            $project = EnvironmentalProject::create($data, $userId);
            Logger::audit(
                action: 'project.created',
                entityType: 'environmental_projects',
                entityId: (int)$project['id'],
                orgId: $orgId,
                userId: $userId,
                newValues: ['title' => $title, 'classification' => $classification]
            );

            Response::json($project, 201, 'Environmental project registered successfully.');
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422, 'GOVERNANCE_VIOLATION');
        } catch (Throwable $e) {
            Response::error('Failed to create project: ' . $e->getMessage(), 500, 'CREATE_FAILED');
        }
    }

    /**
     * PUT /api/projects/:id
     */
    public function update(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $user = $request->getUser();
        $orgId = (int)$org['id'];
        $userId = (int)$user['id'];
        $id = (int)($params['id'] ?? 0);

        $project = EnvironmentalProject::findById($id, $orgId);
        if (!$project) {
            Response::error('Project not found or access denied.', 404, 'NOT_FOUND');
            return;
        }

        $body = $request->getBody();
        // Prevent modifying classification or readiness stage via general PUT
        unset($body['classification'], $body['organization_id'], $body['created_by'], $body['readiness_stage']);

        try {
            EnvironmentalProject::update($id, $orgId, $body);
            Logger::audit(
                action: 'project.updated',
                entityType: 'environmental_projects',
                entityId: $id,
                orgId: $orgId,
                userId: $userId,
                newValues: $body
            );

            $updated = EnvironmentalProject::findById($id, $orgId);
            Response::json($updated, 200, 'Project updated successfully.');
        } catch (Throwable $e) {
            Response::error('Failed to update project: ' . $e->getMessage(), 500, 'UPDATE_FAILED');
        }
    }

    /**
     * POST /api/projects/:id/classification
     * Permission-controlled classification transition with mandatory justification and audit trail.
     */
    public function updateClassification(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $user = $request->getUser();
        $orgId = (int)$org['id'];
        $userId = (int)$user['id'];
        $id = (int)($params['id'] ?? 0);

        // RBAC check: only org_admin, org_owner, or super_admin
        $role = $request->getOrgRole() ?? '';
        if (!in_array($role, ['organization_owner', 'organization_admin', 'super_admin'], true)) {
            Response::error('Only organization administrators can update project classification.', 403, 'FORBIDDEN');
            return;
        }

        $newClassification = $request->getString('classification');
        $reason = $request->getString('reason');
        $evidenceNotes = $request->getString('evidence_notes') ?: null;

        if (empty($newClassification) || empty($reason)) {
            Response::error('Classification and justification reason are mandatory.', 422, 'VALIDATION_FAILED');
            return;
        }

        try {
            $updated = EnvironmentalProject::updateClassification(
                $id,
                $orgId,
                $newClassification,
                $reason,
                $evidenceNotes,
                $userId
            );

            Logger::audit(
                action: 'project.classification_changed',
                entityType: 'environmental_projects',
                entityId: $id,
                orgId: $orgId,
                userId: $userId,
                newValues: ['classification' => $newClassification, 'reason' => $reason]
            );

            Response::json($updated, 200, 'Project classification updated successfully.');
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422, 'GOVERNANCE_VIOLATION');
        } catch (Throwable $e) {
            Response::error('Failed to update classification: ' . $e->getMessage(), 500, 'UPDATE_FAILED');
        }
    }

    /**
     * GET /api/projects/:id/classification-history
     */
    public function classificationHistory(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $orgId = (int)$org['id'];
        $id = (int)($params['id'] ?? 0);

        $project = EnvironmentalProject::findById($id, $orgId);
        if (!$project) {
            Response::error('Project not found or access denied.', 404, 'NOT_FOUND');
            return;
        }

        $history = EnvironmentalProject::getClassificationHistory($id, $orgId);
        Response::json($history, 200, 'Project classification history retrieved.');
    }

    /**
     * POST /api/projects/:id/readiness-stage
     * Dedicated protected readiness-stage transition service with role enforcement and audit logging.
     */
    public function updateReadinessStage(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $user = $request->getUser();
        $orgId = (int)$org['id'];
        $userId = (int)$user['id'];
        $id = (int)($params['id'] ?? 0);

        // RBAC check: only org_admin, org_owner, or super_admin
        $role = $request->getOrgRole() ?? '';
        if (!in_array($role, ['organization_owner', 'organization_admin', 'super_admin'], true)) {
            Response::error('Only organization administrators can update project readiness stage.', 403, 'FORBIDDEN');
            return;
        }

        $newStage = $request->getString('readiness_stage') ?: $request->getString('stage');
        $reason = $request->getString('reason');

        if (empty($newStage) || empty($reason)) {
            Response::error('Readiness stage and justification reason are mandatory.', 422, 'VALIDATION_FAILED');
            return;
        }

        try {
            $updated = EnvironmentalProject::updateReadinessStage(
                $id,
                $orgId,
                $newStage,
                $reason,
                $userId
            );

            Logger::audit(
                action: 'project.readiness_stage_changed',
                entityType: 'environmental_projects',
                entityId: $id,
                orgId: $orgId,
                userId: $userId,
                newValues: ['stage' => $newStage, 'reason' => $reason]
            );

            Response::json($updated, 200, 'Project readiness stage updated successfully.');
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422, 'VALIDATION_FAILED');
        } catch (Throwable $e) {
            Response::error('Failed to update readiness stage: ' . $e->getMessage(), 500, 'UPDATE_FAILED');
        }
    }

    /**
     * GET /api/projects/:id/readiness-stage-history
     */
    public function getStageHistory(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $orgId = (int)$org['id'];
        $id = (int)($params['id'] ?? 0);

        $project = EnvironmentalProject::findById($id, $orgId);
        if (!$project) {
            Response::error('Project not found or access denied.', 404, 'NOT_FOUND');
            return;
        }

        $history = EnvironmentalProject::getStageHistory($id, $orgId);
        Response::json($history, 200, 'Project readiness stage history retrieved successfully.');
    }

    /**
     * POST /api/projects/:id/status
     */
    public function updateStatus(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $user = $request->getUser();
        $orgId = (int)$org['id'];
        $userId = (int)$user['id'];
        $id = (int)($params['id'] ?? 0);

        $status = $request->getString('status');
        $readinessStage = $request->getString('readiness_stage') ?: null;

        if (empty($status) || !in_array($status, EnvironmentalProject::STATUSES, true)) {
            Response::error("Invalid project status: {$status}.", 422, 'VALIDATION_FAILED');
            return;
        }

        $updateData = ['project_status' => $status];
        if (!empty($readinessStage) && in_array($readinessStage, EnvironmentalProject::READINESS_STAGES, true)) {
            $updateData['readiness_stage'] = $readinessStage;
        }

        try {
            EnvironmentalProject::update($id, $orgId, $updateData);
            Logger::audit(
                action: 'project.status_updated',
                entityType: 'environmental_projects',
                entityId: $id,
                orgId: $orgId,
                userId: $userId,
                newValues: $updateData
            );

            $project = EnvironmentalProject::findById($id, $orgId);
            Response::json($project, 200, 'Project status updated successfully.');
        } catch (Throwable $e) {
            Response::error('Failed to update status: ' . $e->getMessage(), 500, 'UPDATE_FAILED');
        }
    }

    /**
     * DELETE /api/projects/:id
     */
    public function destroy(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $user = $request->getUser();
        $orgId = (int)$org['id'];
        $userId = (int)$user['id'];
        $id = (int)($params['id'] ?? 0);

        $role = $request->getOrgRole() ?? '';
        if (!in_array($role, ['organization_owner', 'organization_admin', 'super_admin'], true)) {
            Response::error('Only organization administrators can delete projects.', 403, 'FORBIDDEN');
            return;
        }

        $project = EnvironmentalProject::findById($id, $orgId);
        if (!$project) {
            Response::error('Project not found or access denied.', 404, 'NOT_FOUND');
            return;
        }

        try {
            EnvironmentalProject::delete($id, $orgId);
            Logger::audit(
                action: 'project.deleted',
                entityType: 'environmental_projects',
                entityId: $id,
                orgId: $orgId,
                userId: $userId,
                oldValues: ['title' => $project['project_title']]
            );

            Response::json(null, 200, 'Project deleted successfully.');
        } catch (Throwable $e) {
            Response::error('Failed to delete project: ' . $e->getMessage(), 500, 'DELETE_FAILED');
        }
    }

    // =========================================================================
    // EVIDENCE DOCUMENT CONTROLLER ACTIONS
    // =========================================================================

    /**
     * GET /api/projects/:id/documents
     */
    public function listDocuments(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $orgId = (int)$org['id'];
        $id = (int)($params['id'] ?? 0);

        $project = EnvironmentalProject::findById($id, $orgId);
        if (!$project) {
            Response::error('Project not found or access denied.', 404, 'NOT_FOUND');
            return;
        }

        $docs = ProjectDocument::listByProject($id, $orgId);
        Response::json($docs, 200, 'Project documents retrieved successfully.');
    }

    /**
     * POST /api/projects/:id/documents
     * Secure multipart document upload with MIME validation, SHA-256 hash, and cryptographic filename.
     */
    public function uploadDocument(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $user = $request->getUser();
        $orgId = (int)$org['id'];
        $userId = (int)$user['id'];
        $projectId = (int)($params['id'] ?? 0);

        $project = EnvironmentalProject::findById($projectId, $orgId);
        if (!$project) {
            Response::error('Project not found or access denied.', 404, 'NOT_FOUND');
            return;
        }

        // Validate file presence
        $file = $request->getFile('file') ?? ($_FILES['file'] ?? null);
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Response::error('No valid file was uploaded.', 422, 'FILE_MISSING');
            return;
        }

        // 1. Validate file size
        $fileSize = (int)$file['size'];
        if ($fileSize > self::MAX_FILE_SIZE_BYTES) {
            Response::error('File size exceeds the 10 MB limit.', 422, 'FILE_TOO_LARGE');
            return;
        }

        // 2. Validate extension
        $origName = $file['name'];
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            Response::error("File extension '.{$ext}' is not permitted. Allowed: " . implode(', ', self::ALLOWED_EXTENSIONS), 422, 'INVALID_FILE_TYPE');
            return;
        }

        // 3. Inspect real MIME type via finfo_file
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($realMime, self::ALLOWED_MIME_TYPES, true)) {
            Response::error("Unsupported MIME type ({$realMime}). Executable scripts are rejected.", 422, 'INVALID_MIME_TYPE');
            return;
        }

        // 4. Calculate SHA-256 integrity hash
        $sha256 = hash_file('sha256', $file['tmp_name']);

        // 5. Generate cryptographic storage identifier
        $storageId = 'doc_' . bin2hex(random_bytes(16)) . '.' . $ext;

        // 6. Secure directory outside public root
        $targetDir = dirname(__DIR__) . '/storage/documents/' . $orgId;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $destPath = $targetDir . '/' . $storageId;
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            Response::error('Failed to move uploaded file to secure storage.', 500, 'UPLOAD_STORAGE_FAILED');
            return;
        }

        $docType = $request->getString('document_type', ProjectDocument::TYPE_OTHER);
        $fileTitle = $request->getString('file_title') ?: pathinfo($origName, PATHINFO_FILENAME);
        $description = $request->getString('description') ?: null;

        $docData = [
            'project_id' => $projectId,
            'organization_id' => $orgId,
            'document_type' => $docType,
            'storage_identifier' => $storageId,
            'file_title' => $fileTitle,
            'original_filename' => $origName,
            'file_path' => 'storage/documents/' . $orgId . '/' . $storageId,
            'file_size_kb' => (int)ceil($fileSize / 1024),
            'mime_type' => $realMime,
            'sha256_hash' => $sha256,
            'description' => $description,
        ];

        try {
            $createdDoc = ProjectDocument::create($docData, $userId);
            Logger::audit(
                action: 'document.uploaded',
                entityType: 'project_documents',
                entityId: (int)$createdDoc['id'],
                orgId: $orgId,
                userId: $userId,
                newValues: ['filename' => $origName, 'type' => $docType, 'sha256' => $sha256]
            );

            Response::json($createdDoc, 201, 'Verification evidence document uploaded securely.');
        } catch (Throwable $e) {
            Response::error('Database error storing document metadata: ' . $e->getMessage(), 500, 'STORE_FAILED');
        }
    }

    /**
     * GET /api/projects/:id/documents/:docId/download
     * Authenticated streaming proxy enforcing tenant isolation.
     */
    public function downloadDocument(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $orgId = (int)$org['id'];
        $docId = (int)($params['docId'] ?? 0);

        $doc = ProjectDocument::findById($docId, $orgId);
        if (!$doc) {
            Response::error('Document not found or access denied.', 404, 'NOT_FOUND');
            return;
        }

        $filePath = dirname(__DIR__) . '/' . $doc['file_path'];
        if (!file_exists($filePath)) {
            Response::error('Physical file not found on server.', 404, 'FILE_NOT_FOUND');
            return;
        }

        // Verify disk hash against stored integrity hash
        $currentHash = hash_file('sha256', $filePath);
        if ($currentHash !== $doc['sha256_hash']) {
            Response::error('File integrity verification failed (SHA-256 mismatch).', 500, 'INTEGRITY_ERROR');
            return;
        }

        // Stream file securely
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $doc['mime_type']);
        header('Content-Disposition: attachment; filename="' . basename($doc['original_filename']) . '"');
        header('Expires: 0');
        header('Cache-Control: private, no-cache, no-store, must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        header('X-Content-Type-Options: nosniff');

        readfile($filePath);
        exit;
    }

    /**
     * POST /api/projects/:id/documents/:docId/replace
     */
    public function replaceDocument(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $user = $request->getUser();
        $orgId = (int)$org['id'];
        $userId = (int)$user['id'];
        $docId = (int)($params['docId'] ?? 0);

        $doc = ProjectDocument::findById($docId, $orgId);
        if (!$doc) {
            Response::error('Document not found or access denied.', 404, 'NOT_FOUND');
            return;
        }

        $file = $request->getFile('file') ?? ($_FILES['file'] ?? null);
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Response::error('No replacement file uploaded.', 422, 'FILE_MISSING');
            return;
        }

        $fileSize = (int)$file['size'];
        if ($fileSize > self::MAX_FILE_SIZE_BYTES) {
            Response::error('File size exceeds the 10 MB limit.', 422, 'FILE_TOO_LARGE');
            return;
        }

        $origName = $file['name'];
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            Response::error("File extension '.{$ext}' is not permitted.", 422, 'INVALID_FILE_TYPE');
            return;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($realMime, self::ALLOWED_MIME_TYPES, true)) {
            Response::error("Unsupported MIME type ({$realMime}).", 422, 'INVALID_MIME_TYPE');
            return;
        }

        $sha256 = hash_file('sha256', $file['tmp_name']);
        $storageId = 'doc_' . bin2hex(random_bytes(16)) . '.' . $ext;

        $targetDir = dirname(__DIR__) . '/storage/documents/' . $orgId;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $destPath = $targetDir . '/' . $storageId;
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            Response::error('Failed to move replacement file to storage.', 500, 'UPLOAD_STORAGE_FAILED');
            return;
        }

        $versionData = [
            'storage_identifier' => $storageId,
            'original_filename' => $origName,
            'file_path' => 'storage/documents/' . $orgId . '/' . $storageId,
            'file_size_kb' => (int)ceil($fileSize / 1024),
            'mime_type' => $realMime,
            'sha256_hash' => $sha256,
            'change_summary' => $request->getString('change_summary') ?: 'Uploaded updated version',
        ];

        try {
            $updatedDoc = ProjectDocument::replaceVersion($docId, $orgId, $versionData, $userId);
            Logger::audit(
                action: 'document.version_replaced',
                entityType: 'project_documents',
                entityId: $docId,
                orgId: $orgId,
                userId: $userId,
                newValues: ['new_version' => $updatedDoc['current_version'], 'sha256' => $sha256]
            );

            Response::json($updatedDoc, 200, 'Document updated to new version successfully.');
        } catch (Throwable $e) {
            if (isset($destPath) && file_exists($destPath)) {
                @unlink($destPath);
            }
            Response::error('Failed to replace document: ' . $e->getMessage(), 500, 'REPLACE_FAILED');
        }
    }

    /**
     * POST /api/projects/:id/documents/:docId/review
     */
    public function reviewDocument(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $user = $request->getUser();
        $orgId = (int)$org['id'];
        $userId = (int)$user['id'];
        $docId = (int)($params['docId'] ?? 0);

        // RBAC check
        $role = $request->getOrgRole() ?? '';
        if (!in_array($role, ['organization_owner', 'organization_admin', 'super_admin'], true)) {
            Response::error('Only organization administrators can submit document reviews.', 403, 'FORBIDDEN');
            return;
        }

        $status = $request->getString('review_status') ?: $request->getString('status');
        $comments = $request->getString('review_comments') ?: $request->getString('comments') ?: $request->getString('notes');
        $revisionReason = $request->getString('rejection_or_revision_reason') ?: $request->getString('reason') ?: null;

        if (empty($status) || empty($comments)) {
            Response::error('Review status and comments are required.', 422, 'VALIDATION_FAILED');
            return;
        }

        try {
            $reviewedDoc = ProjectDocument::review($docId, $orgId, $status, $comments, $revisionReason, $userId);
            Logger::audit(
                action: 'document.reviewed',
                entityType: 'project_documents',
                entityId: $docId,
                orgId: $orgId,
                userId: $userId,
                newValues: ['review_status' => $status, 'comments' => $comments]
            );

            Response::json($reviewedDoc, 200, 'Document review decision recorded.');
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422, 'VALIDATION_FAILED');
        } catch (Throwable $e) {
            Response::error('Failed to review document: ' . $e->getMessage(), 500, 'REVIEW_FAILED');
        }
    }

    /**
     * GET /api/projects/:id/documents/:docId/history
     */
    public function documentHistory(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $orgId = (int)$org['id'];
        $docId = (int)($params['docId'] ?? 0);

        $doc = ProjectDocument::findById($docId, $orgId);
        if (!$doc) {
            Response::error('Document not found or access denied.', 404, 'NOT_FOUND');
            return;
        }

        $versions = ProjectDocument::getVersions($docId, $orgId);
        $reviews = ProjectDocument::getReviews($docId, $orgId);

        Response::json([
            'document' => $doc,
            'versions' => $versions,
            'reviews' => $reviews,
        ], 200, 'Document history retrieved successfully.');
    }

    /**
     * DELETE /api/projects/:id/documents/:docId
     */
    public function deleteDocument(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $user = $request->getUser();
        $orgId = (int)$org['id'];
        $userId = (int)$user['id'];
        $docId = (int)($params['docId'] ?? 0);

        $role = $request->getOrgRole() ?? '';
        if (!in_array($role, ['organization_owner', 'organization_admin', 'super_admin'], true)) {
            Response::error('Only organization administrators can archive documents.', 403, 'FORBIDDEN');
            return;
        }

        $doc = ProjectDocument::findById($docId, $orgId);
        if (!$doc) {
            Response::error('Document not found or access denied.', 404, 'NOT_FOUND');
            return;
        }

        try {
            ProjectDocument::archive($docId, $orgId);
            Logger::audit(
                action: 'document.archived',
                entityType: 'project_documents',
                entityId: $docId,
                orgId: $orgId,
                userId: $userId,
                oldValues: ['filename' => $doc['original_filename']]
            );

            Response::json(null, 200, 'Document archived successfully.');
        } catch (Throwable $e) {
            Response::error('Failed to archive document: ' . $e->getMessage(), 500, 'ARCHIVE_FAILED');
        }
    }

    // =========================================================================
    // READINESS ASSESSMENT CONTROLLER ACTIONS
    // =========================================================================

    /**
     * POST /api/projects/:id/readiness-assessments
     * Execute algorithmic readiness evaluation and persist new assessment version.
     */
    public function assessReadiness(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $user = $request->getUser();
        $orgId = (int)$org['id'];
        $userId = (int)$user['id'];
        $id = (int)($params['id'] ?? 0);

        $project = EnvironmentalProject::findById($id, $orgId);
        if (!$project) {
            Response::error('Project not found or access denied.', 404, 'NOT_FOUND');
            return;
        }

        $documents = ProjectDocument::listByProject($id, $orgId);
        $weights = $request->getArray('weights') ?: ReadinessAssessmentEngine::DEFAULT_WEIGHTS;

        try {
            $engine = new ReadinessAssessmentEngine();
            $assessmentData = $engine->evaluateProject($project, $documents, $weights);

            $savedAssessment = ProjectReadinessAssessment::saveAssessment(
                $assessmentData,
                $assessmentData['dimension_breakdown'],
                $userId
            );

            Logger::audit(
                action: 'project.readiness_assessed',
                entityType: 'project_readiness_assessments',
                entityId: (int)$savedAssessment['id'],
                orgId: $orgId,
                userId: $userId,
                newValues: [
                    'version' => $savedAssessment['assessment_version'],
                    'overall_percentage' => $savedAssessment['overall_readiness_percentage'],
                    'readiness_stage' => $savedAssessment['readiness_stage']
                ]
            );

            Response::json($savedAssessment, 201, 'Readiness assessment executed and persisted.');
        } catch (Throwable $e) {
            Response::error('Readiness assessment failed: ' . $e->getMessage(), 500, 'ASSESSMENT_FAILED');
        }
    }

    /**
     * GET /api/projects/:id/readiness-assessments
     */
    public function getReadiness(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $orgId = (int)$org['id'];
        $id = (int)($params['id'] ?? 0);

        $project = EnvironmentalProject::findById($id, $orgId);
        if (!$project) {
            Response::error('Project not found or access denied.', 404, 'NOT_FOUND');
            return;
        }

        $assessment = ProjectReadinessAssessment::getCurrentAssessment($id, $orgId);
        if (!$assessment) {
            Response::json([
                'project_id' => $id,
                'has_assessment' => false,
                'message' => 'No readiness assessment has been run yet for this project.'
            ], 200, 'No active readiness assessment.');
            return;
        }

        Response::json($assessment, 200, 'Current readiness assessment retrieved.');
    }

    /**
     * GET /api/projects/:id/readiness-assessments/history
     */
    public function readinessHistory(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $orgId = (int)$org['id'];
        $id = (int)($params['id'] ?? 0);

        $project = EnvironmentalProject::findById($id, $orgId);
        if (!$project) {
            Response::error('Project not found or access denied.', 404, 'NOT_FOUND');
            return;
        }

        $history = ProjectReadinessAssessment::getHistory($id, $orgId);
        Response::json($history, 200, 'Readiness assessment history retrieved.');
    }

    /**
     * GET /api/projects/:id/readiness-assessments/:assessmentId
     */
    public function showAssessment(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $orgId = (int)$org['id'];
        $assessmentId = (int)($params['assessmentId'] ?? 0);

        $assessment = ProjectReadinessAssessment::getAssessmentById($assessmentId, $orgId);
        if (!$assessment) {
            Response::error('Assessment version not found or access denied.', 404, 'NOT_FOUND');
            return;
        }

        Response::json($assessment, 200, 'Assessment version details retrieved.');
    }

    /**
     * GET /api/projects/:id/readiness-assessments/:assessmentId/gaps
     */
    public function getReadinessGaps(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $orgId = (int)$org['id'];
        $assessmentId = (int)($params['assessmentId'] ?? 0);

        $assessment = ProjectReadinessAssessment::getAssessmentById($assessmentId, $orgId);
        if (!$assessment) {
            Response::error('Assessment version not found or access denied.', 404, 'NOT_FOUND');
            return;
        }

        Response::json([
            'is_complete' => (bool)$assessment['is_complete'],
            'completeness_status' => $assessment['completeness_status'],
            'missing_required_fields' => $assessment['missing_required_fields'] ?? [],
            'gap_analysis_summary' => $assessment['gap_analysis_summary'],
            'recommendations' => $assessment['recommendations'],
            'dimensions' => $assessment['dimensions'] ?? [],
        ], 200, 'Gap analysis and recommendations retrieved.');
    }
}