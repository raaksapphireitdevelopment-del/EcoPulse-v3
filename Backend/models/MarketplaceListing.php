<?php
declare(strict_types=1);

namespace EcoPulse\Models;

use EcoPulse\Core\Database;
use PDO;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class MarketplaceListing
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_PUBLISHED,
        self::STATUS_REJECTED,
        self::STATUS_SUSPENDED,
        self::STATUS_ARCHIVED,
    ];

    /**
     * Public browsing of published listings
     */
    public static function listPublic(array $filters = [], int $page = 1, int $limit = 20): array
    {
        $db = Database::getInstance();
        $conditions = [
            'ml.`status` = "published"',
            'ml.`is_archived` = 0',
            'ep.`is_archived` = 0',
            'ep.`classification` IN ("verified_environmental_asset", "issued_carbon_credit")',
            '(
                (ep.`classification` = "verified_environmental_asset" AND EXISTS (
                    SELECT 1 FROM `project_documents` pd
                    WHERE pd.`project_id` = ep.`id`
                      AND pd.`review_status` = "accepted"
                      AND pd.`is_archived` = 0
                      AND pd.`document_type` IN ("verification_report", "validation_report")
                ))
                OR
                (ep.`classification` = "issued_carbon_credit" AND ep.`registry_name` IS NOT NULL AND ep.`registry_name` != "" AND ep.`registry_serial_or_id` IS NOT NULL AND ep.`registry_serial_or_id` != "" AND ep.`methodology` IS NOT NULL AND ep.`methodology` != "" AND EXISTS (
                    SELECT 1 FROM `project_documents` pd
                    WHERE pd.`project_id` = ep.`id`
                      AND pd.`review_status` = "accepted"
                      AND pd.`is_archived` = 0
                      AND pd.`document_type` IN ("credit_issuance_notice", "registry_certificate")
                ))
            )'
        ];
        $params = [];

        if (!empty($filters['search'])) {
            $conditions[] = '(ml.`listing_title` LIKE :search1 OR ml.`description` LIKE :search2 OR ml.`listing_code` LIKE :search3 OR ml.`methodology` LIKE :search4)';
            $sVal = '%' . $filters['search'] . '%';
            $params[':search1'] = $sVal;
            $params[':search2'] = $sVal;
            $params[':search3'] = $sVal;
            $params[':search4'] = $sVal;
        }

        if (!empty($filters['domain_profile_id'])) {
            $conditions[] = 'ml.`domain_profile_id` = :domain_profile_id';
            $params[':domain_profile_id'] = (int)$filters['domain_profile_id'];
        }

        if (!empty($filters['location_country'])) {
            $conditions[] = 'ml.`location_country` = :location_country';
            $params[':location_country'] = $filters['location_country'];
        }

        if (!empty($filters['classification_tier'])) {
            $conditions[] = 'ml.`eligible_classification` = :classification_tier';
            $params[':classification_tier'] = $filters['classification_tier'];
        }

        if (!empty($filters['methodology'])) {
            $conditions[] = 'ml.`methodology` LIKE :methodology';
            $params[':methodology'] = '%' . $filters['methodology'] . '%';
        }

        $whereClause = implode(' AND ', $conditions);
        $offset = max(0, ($page - 1) * $limit);

        $sortOrder = 'ml.`published_at` DESC';
        if (($filters['sort'] ?? '') === 'volume_desc') {
            $sortOrder = 'ml.`available_volume_tco2e` DESC';
        } elseif (($filters['sort'] ?? '') === 'volume_asc') {
            $sortOrder = 'ml.`available_volume_tco2e` ASC';
        }

        $sql = "SELECT ml.`id`, ml.`listing_code`, ml.`listing_title`, ml.`headline`, ml.`description`,
                       ml.`eligible_classification` as classification_tier,
                       ml.`location_country`, ml.`location_region`, ml.`methodology`, ml.`methodology_version`,
                       ml.`third_party_verifier`, ml.`registry_name`, ml.`registry_serial_or_id`,
                       ml.`vintage_year`, ml.`total_volume_tco2e`, ml.`available_volume_tco2e`,
                       ml.`unit_of_measurement`, ml.`project_start_date`, ml.`crediting_period_start`,
                       ml.`crediting_period_end`, ml.`evidence_summary`, ml.`indicative_price_per_tco2e`,
                       ml.`currency`, ml.`status`, ml.`published_at`, ml.`created_at`,
                       o.`name` as organization_name,
                       d.`name` as domain_name, d.`code` as domain_code, d.`icon` as domain_icon
                FROM `marketplace_listings` ml
                JOIN `organizations` o ON ml.`organization_id` = o.`id`
                JOIN `environmental_projects` ep ON ml.`project_id` = ep.`id`
                LEFT JOIN `domain_profiles` dp ON ml.`domain_profile_id` = dp.`id`
                LEFT JOIN `domains` d ON dp.`domain_id` = d.`id`
                WHERE {$whereClause}
                ORDER BY {$sortOrder}
                LIMIT :limit OFFSET :offset";

        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $countSql = "SELECT COUNT(*) as total FROM `marketplace_listings` ml JOIN `environmental_projects` ep ON ml.`project_id` = ep.`id` WHERE {$whereClause}";
        $countStmt = $db->prepare($countSql);
        foreach ($params as $k => $v) {
            $countStmt->bindValue($k, $v);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        return [
            'data' => $listings,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => $total > 0 ? (int)ceil($total / $limit) : 1
        ];
    }

    /**
     * Public detail view of a published listing
     */
    public static function findPublicById(int|string $idOrCode): ?array
    {
        $db = Database::getInstance();
        $isCode = is_string($idOrCode) && !is_numeric($idOrCode);
        $idClause = $isCode ? 'ml.`listing_code` = :id' : 'ml.`id` = :id';

        $sql = "SELECT ml.`id`, ml.`listing_code`, ml.`listing_title`, ml.`headline`, ml.`description`,
                       ml.`eligible_classification` as classification_tier,
                       ml.`location_country`, ml.`location_region`, ml.`methodology`, ml.`methodology_version`,
                       ml.`third_party_verifier`, ml.`registry_name`, ml.`registry_serial_or_id`,
                       ml.`vintage_year`, ml.`total_volume_tco2e`, ml.`available_volume_tco2e`,
                       ml.`unit_of_measurement`, ml.`project_start_date`, ml.`crediting_period_start`,
                       ml.`crediting_period_end`, ml.`evidence_summary`, ml.`indicative_price_per_tco2e`,
                       ml.`currency`, ml.`status`, ml.`published_at`, ml.`created_at`,
                       o.`name` as organization_name,
                       d.`name` as domain_name, d.`code` as domain_code, d.`icon` as domain_icon,
                       ep.`project_code`, ep.`project_category`
                FROM `marketplace_listings` ml
                JOIN `organizations` o ON ml.`organization_id` = o.`id`
                JOIN `environmental_projects` ep ON ml.`project_id` = ep.`id`
                LEFT JOIN `domain_profiles` dp ON ml.`domain_profile_id` = dp.`id`
                LEFT JOIN `domains` d ON dp.`domain_id` = d.`id`
                WHERE ({$idClause}) AND ml.`status` = 'published' AND ml.`is_archived` = 0
                  AND ep.`is_archived` = 0
                  AND ep.`classification` IN ('verified_environmental_asset', 'issued_carbon_credit')
                  AND (
                        (ep.`classification` = 'verified_environmental_asset' AND EXISTS (
                            SELECT 1 FROM `project_documents` pd
                            WHERE pd.`project_id` = ep.`id`
                              AND pd.`review_status` = 'accepted'
                              AND pd.`is_archived` = 0
                              AND pd.`document_type` IN ('verification_report', 'validation_report')
                        ))
                        OR
                        (ep.`classification` = 'issued_carbon_credit' AND ep.`registry_name` IS NOT NULL AND ep.`registry_name` != '' AND ep.`registry_serial_or_id` IS NOT NULL AND ep.`registry_serial_or_id` != '' AND ep.`methodology` IS NOT NULL AND ep.`methodology` != '' AND EXISTS (
                            SELECT 1 FROM `project_documents` pd
                            WHERE pd.`project_id` = ep.`id`
                              AND pd.`review_status` = 'accepted'
                              AND pd.`is_archived` = 0
                              AND pd.`document_type` IN ('credit_issuance_notice', 'registry_certificate')
                        ))
                  )";

        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $idOrCode]);
        $listing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$listing) {
            return null;
        }

        // Retrieve public evidence summary (document titles, types, acceptance timestamps - NO file paths or storage IDs)
        $docSql = "SELECT `document_type`, `file_title`, `original_filename`, `review_status`, `reviewed_at`
                   FROM `project_documents`
                   WHERE `project_id` = (SELECT `project_id` FROM `marketplace_listings` WHERE `id` = :lid)
                     AND `review_status` = 'accepted'
                     AND `is_archived` = 0
                   ORDER BY `created_at` ASC";
        $docStmt = $db->prepare($docSql);
        $docStmt->execute([':lid' => $listing['id']]);
        $listing['accepted_evidence_documents'] = $docStmt->fetchAll(PDO::FETCH_ASSOC);
        $listing['public_evidence'] = $listing['accepted_evidence_documents'];

        return $listing;
    }

    /**
     * Get projects eligible for marketplace listing for an organization
     */
    public static function getEligibleProjects(int $orgId): array
    {
        $db = Database::getInstance();
        $sql = "SELECT ep.`id`, ep.`project_title`, ep.`project_code`, ep.`classification`,
                       ep.`methodology`, ep.`third_party_verifier`, ep.`registry_name`, ep.`registry_serial_or_id`,
                       ep.`estimated_annual_mitigation_tco2e`, ep.`location_country`, ep.`location_region`,
                       ep.`domain_profile_id`, ep.`project_start_date`, ep.`crediting_period_start`, ep.`crediting_period_end`,
                       d.`name` as domain_name,
                       (SELECT COUNT(*) FROM `project_documents` WHERE `project_id` = ep.`id` AND `review_status` = 'accepted' AND `is_archived` = 0) as accepted_docs_count,
                       (SELECT COUNT(*) FROM `marketplace_listings` WHERE `project_id` = ep.`id` AND `is_archived` = 0 AND `status` != 'archived') as existing_listings_count
                FROM `environmental_projects` ep
                JOIN `domain_profiles` dp ON ep.`domain_profile_id` = dp.`id`
                JOIN `domains` d ON dp.`domain_id` = d.`id`
                WHERE ep.`organization_id` = :org_id
                  AND ep.`is_archived` = 0
                  AND ep.`classification` IN ('verified_environmental_asset', 'issued_carbon_credit')
                ORDER BY ep.`created_at` DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute([':org_id' => $orgId]);
        $all = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Filter strictly by required evidence acceptance
        $eligible = [];
        foreach ($all as $p) {
            $isEligible = false;
            if ($p['classification'] === 'verified_environmental_asset') {
                // Must have at least 1 accepted verification/validation report
                $chkSql = "SELECT COUNT(*) FROM `project_documents`
                           WHERE `project_id` = :pid AND `review_status` = 'accepted' AND `is_archived` = 0
                             AND `document_type` IN ('verification_report', 'validation_report')";
                $chk = $db->prepare($chkSql);
                $chk->execute([':pid' => $p['id']]);
                if ((int)$chk->fetchColumn() > 0) {
                    $isEligible = true;
                }
            } elseif ($p['classification'] === 'issued_carbon_credit') {
                // Must have accepted issuance notice and registry serial
                if (!empty($p['registry_name']) && !empty($p['registry_serial_or_id'])) {
                    $chkSql = "SELECT COUNT(*) FROM `project_documents`
                               WHERE `project_id` = :pid AND `review_status` = 'accepted' AND `is_archived` = 0
                                 AND `document_type` IN ('credit_issuance_notice', 'registry_certificate')";
                    $chk = $db->prepare($chkSql);
                    $chk->execute([':pid' => $p['id']]);
                    if ((int)$chk->fetchColumn() > 0) {
                        $isEligible = true;
                    }
                }
            }

            if ($isEligible) {
                $eligible[] = $p;
            }
        }

        return $eligible;
    }

    /**
     * Create listing from eligible project
     */
    public static function create(array $data, int $orgId, int $userId): array
    {
        $db = Database::getInstance();
        $projectId = (int)($data['project_id'] ?? 0);

        // 1. Validate project ownership and eligibility
        $project = EnvironmentalProject::findById($projectId, $orgId);
        if (!$project) {
            throw new RuntimeException("Project not found or access denied.");
        }

        // Strict eligibility check
        $classification = $project['classification'];
        if ($classification === 'operational_reduction' || $classification === 'project_candidate') {
            throw new InvalidArgumentException("Projects classified as '{$classification}' are internal or candidate projects and are strictly ineligible for marketplace listing.");
        }

        if ($classification === 'verified_environmental_asset') {
            $chkSql = "SELECT COUNT(*) FROM `project_documents`
                       WHERE `project_id` = :pid AND `review_status` = 'accepted' AND `is_archived` = 0
                         AND `document_type` IN ('verification_report', 'validation_report')";
            $chk = $db->prepare($chkSql);
            $chk->execute([':pid' => $projectId]);
            if ((int)$chk->fetchColumn() === 0) {
                throw new InvalidArgumentException("Verified environmental asset requires an accepted independent verification report before creating a marketplace listing.");
            }
        }

        if ($classification === 'issued_carbon_credit') {
            if (empty($project['registry_name']) || empty($project['registry_serial_or_id']) || empty($project['methodology'])) {
                throw new InvalidArgumentException("Issued carbon credit requires verified registry credentials, serial numbers, and methodology reference.");
            }
            $chkSql = "SELECT COUNT(*) FROM `project_documents`
                       WHERE `project_id` = :pid AND `review_status` = 'accepted' AND `is_archived` = 0
                         AND `document_type` IN ('credit_issuance_notice', 'registry_certificate')";
            $chk = $db->prepare($chkSql);
            $chk->execute([':pid' => $projectId]);
            if ((int)$chk->fetchColumn() === 0) {
                throw new InvalidArgumentException("Issued carbon credit requires an accepted credit issuance notice or certificate before creating a marketplace listing.");
            }
        }

        $listingCode = 'MKT-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $title = !empty($data['listing_title']) ? trim($data['listing_title']) : (!empty($data['title']) ? trim($data['title']) : $project['project_title']);
        $headline = !empty($data['headline']) ? trim($data['headline']) : substr($title, 0, 100);
        $description = !empty($data['description']) ? trim($data['description']) : (!empty($data['summary_description']) ? trim($data['summary_description']) : ($project['description'] ?? 'Verified environmental project asset'));

        $volume = isset($data['total_volume_tco2e']) ? (float)$data['total_volume_tco2e'] : (isset($data['offered_quantity']) ? (float)$data['offered_quantity'] : (float)$project['estimated_annual_mitigation_tco2e']);
        if ($volume <= 0) {
            throw new InvalidArgumentException("Offered listing volume must be greater than zero.");
        }
        $availVolume = (float)($data['available_volume_tco2e'] ?? $volume);
        $vintage = !empty($data['vintage_year']) ? (int)$data['vintage_year'] : (int)date('Y');
        $price = (float)($data['indicative_price_per_tco2e'] ?? $data['unit_price'] ?? 0.0);
        $currency = !empty($data['currency']) ? strtoupper(trim($data['currency'])) : 'USD';

        $pdo = $db->getPdo();
        $pdo->beginTransaction();
        try {
            $sql = "INSERT INTO `marketplace_listings` (
                `organization_id`, `project_id`, `listing_code`, `domain_profile_id`,
                `listing_title`, `headline`, `description`, `eligible_classification`,
                `location_country`, `location_region`, `methodology`, `methodology_version`,
                `third_party_verifier`, `registry_name`, `registry_serial_or_id`,
                `vintage_year`, `total_volume_tco2e`, `available_volume_tco2e`,
                `unit_of_measurement`, `project_start_date`, `crediting_period_start`,
                `crediting_period_end`, `evidence_summary`, `indicative_price_per_tco2e`,
                `currency`, `status`, `created_by`, `created_at`
            ) VALUES (
                :org_id, :project_id, :code, :domain_profile_id,
                :title, :headline, :description, :classification,
                :country, :region, :methodology, :methodology_version,
                :verifier, :registry_name, :registry_serial,
                :vintage, :volume, :avail_volume,
                'tCO2e', :p_start, :c_start,
                :c_end, :evidence_summary, :price,
                :currency, 'draft', :user_id, NOW()
            )";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':org_id' => $orgId,
                ':project_id' => $projectId,
                ':code' => $listingCode,
                ':domain_profile_id' => $project['domain_profile_id'],
                ':title' => $title,
                ':headline' => $headline,
                ':description' => $description,
                ':classification' => $classification,
                ':country' => $project['location_country'] ?? 'Global',
                ':region' => $project['location_region'] ?? null,
                ':methodology' => $project['methodology'] ?? 'Documented Standard',
                ':methodology_version' => $project['methodology_version'] ?? null,
                ':verifier' => $project['third_party_verifier'] ?? null,
                ':registry_name' => $project['registry_name'] ?? null,
                ':registry_serial' => $project['registry_serial_or_id'] ?? null,
                ':vintage' => $vintage,
                ':volume' => $volume,
                ':avail_volume' => $availVolume,
                ':p_start' => $project['project_start_date'] ?? null,
                ':c_start' => $project['crediting_period_start'] ?? null,
                ':c_end' => $project['crediting_period_end'] ?? null,
                ':evidence_summary' => $data['evidence_summary'] ?? 'Independent verification documents reviewed and accepted.',
                ':price' => $price,
                ':currency' => $currency,
                ':user_id' => $userId,
            ]);

            $listingId = (int)$pdo->lastInsertId();

            // Record initial history
            $histStmt = $pdo->prepare("INSERT INTO `marketplace_listing_reviews` (
                `listing_id`, `organization_id`, `previous_status`, `new_status`, `action`,
                `reviewer_id`, `reason`, `comments`, `created_at`
            ) VALUES (
                :lid, :oid, NULL, 'draft', 'created', :uid, 'Initial draft creation', 'Listing draft created by organization', NOW()
            )");
            $histStmt->execute([
                ':lid' => $listingId,
                ':oid' => $orgId,
                ':uid' => $userId
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return self::findByOrg($listingId, $orgId);
    }

    /**
     * Organization client list of their listings
     */
    public static function listByOrg(int $orgId, array $filters = [], int $page = 1, int $limit = 50): array
    {
        $db = Database::getInstance();
        $conditions = [
            'ml.`organization_id` = :org_id',
            'ml.`is_archived` = 0'
        ];
        $params = [':org_id' => $orgId];

        if (!empty($filters['status'])) {
            $conditions[] = 'ml.`status` = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(ml.`listing_title` LIKE :search1 OR ml.`listing_code` LIKE :search2)';
            $sVal = '%' . $filters['search'] . '%';
            $params[':search1'] = $sVal;
            $params[':search2'] = $sVal;
        }

        $whereClause = implode(' AND ', $conditions);
        $offset = max(0, ($page - 1) * $limit);

        $sql = "SELECT ml.*,
                       ep.`project_code`,
                       d.`name` as domain_name,
                       (SELECT COUNT(*) FROM `buyer_inquiries` WHERE `listing_id` = ml.`id`) as inquiry_count
                FROM `marketplace_listings` ml
                JOIN `environmental_projects` ep ON ml.`project_id` = ep.`id`
                LEFT JOIN `domain_profiles` dp ON ml.`domain_profile_id` = dp.`id`
                LEFT JOIN `domains` d ON dp.`domain_id` = d.`id`
                WHERE {$whereClause}
                ORDER BY ml.`created_at` DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $countSql = "SELECT COUNT(*) as total FROM `marketplace_listings` ml WHERE {$whereClause}";
        $countStmt = $db->prepare($countSql);
        foreach ($params as $k => $v) {
            $countStmt->bindValue($k, $v);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        return [
            'data' => $listings,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => $total > 0 ? (int)ceil($total / $limit) : 1
        ];
    }

    /**
     * Find single listing owned by org
     */
    public static function findByOrg(int $id, int $orgId): ?array
    {
        $db = Database::getInstance();
        $sql = "SELECT ml.*,
                       ep.`project_title` as orig_project_title, ep.`project_code`,
                       d.`name` as domain_name,
                       (SELECT COUNT(*) FROM `buyer_inquiries` WHERE `listing_id` = ml.`id`) as inquiry_count
                FROM `marketplace_listings` ml
                JOIN `environmental_projects` ep ON ml.`project_id` = ep.`id`
                LEFT JOIN `domain_profiles` dp ON ml.`domain_profile_id` = dp.`id`
                LEFT JOIN `domains` d ON dp.`domain_id` = d.`id`
                WHERE ml.`id` = :id AND ml.`organization_id` = :org_id";

        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $id, ':org_id' => $orgId]);
        $listing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$listing) {
            return null;
        }

        // Attach review history
        $hSql = "SELECT mlr.*, u.`name` as reviewer_name
                 FROM `marketplace_listing_reviews` mlr
                 JOIN `users` u ON mlr.`reviewer_id` = u.`id`
                 WHERE mlr.`listing_id` = :lid
                 ORDER BY mlr.`created_at` DESC";
        $hStmt = $db->prepare($hSql);
        $hStmt->execute([':lid' => $id]);
        $listing['review_history'] = $hStmt->fetchAll(PDO::FETCH_ASSOC);

        return $listing;
    }

    /**
     * Submit listing for review
     */
    public static function submitForReview(int $id, int $orgId, int $userId): array
    {
        $listing = self::findByOrg($id, $orgId);
        if (!$listing) {
            throw new RuntimeException("Listing not found or access denied.");
        }

        if (!in_array($listing['status'], [self::STATUS_DRAFT, self::STATUS_REJECTED], true)) {
            throw new InvalidArgumentException("Only draft or rejected listings can be submitted for review. Current status: {$listing['status']}");
        }

        $project = EnvironmentalProject::findById((int)$listing['project_id'], $orgId);
        if (!$project || (int)$project['is_archived'] === 1 || in_array($project['classification'], ['operational_reduction', 'project_candidate'], true)) {
            throw new InvalidArgumentException("Project eligibility violation: The underlying project is archived or has an ineligible classification tier.");
        }

        $db = Database::getInstance();
        $pdo = $db->getPdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE `marketplace_listings` SET `status` = 'pending_review', `submitted_at` = NOW() WHERE `id` = :id AND `organization_id` = :org_id");
            $stmt->execute([':id' => $id, ':org_id' => $orgId]);

            $histStmt = $pdo->prepare("INSERT INTO `marketplace_listing_reviews` (
                `listing_id`, `organization_id`, `previous_status`, `new_status`, `action`,
                `reviewer_id`, `reason`, `comments`, `created_at`
            ) VALUES (
                :lid, :oid, :prev, 'pending_review', 'submitted_for_review', :uid, 'Submitted for marketplace review', 'Organization submitted listing for Super Admin review', NOW()
            )");
            $histStmt->execute([
                ':lid' => $id,
                ':oid' => $orgId,
                ':prev' => $listing['status'],
                ':uid' => $userId
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return self::findByOrg($id, $orgId);
    }

    /**
     * Soft delete / archive listing
     */
    public static function archive(int $id, int $orgId, int $userId): bool
    {
        $listing = self::findByOrg($id, $orgId);
        if (!$listing) {
            return false;
        }

        $db = Database::getInstance();
        $pdo = $db->getPdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE `marketplace_listings` SET `status` = 'archived', `is_archived` = 1 WHERE `id` = :id AND `organization_id` = :org_id");
            $stmt->execute([':id' => $id, ':org_id' => $orgId]);

            $histStmt = $pdo->prepare("INSERT INTO `marketplace_listing_reviews` (
                `listing_id`, `organization_id`, `previous_status`, `new_status`, `action`,
                `reviewer_id`, `reason`, `comments`, `created_at`
            ) VALUES (
                :lid, :oid, :prev, 'archived', 'archived', :uid, 'Listing archived', 'Listing moved to archive', NOW()
            )");
            $histStmt->execute([
                ':lid' => $id,
                ':oid' => $orgId,
                ':prev' => $listing['status'],
                ':uid' => $userId
            ]);

            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * SUPER ADMIN: List pending listings for moderation queue
     */
    public static function listPending(int $page = 1, int $limit = 50): array
    {
        $db = Database::getInstance();
        $offset = max(0, ($page - 1) * $limit);

        $sql = "SELECT ml.*,
                       o.`name` as organization_name,
                       ep.`project_code`, ep.`project_title` as orig_project_title,
                       d.`name` as domain_name,
                       (SELECT COUNT(*) FROM `project_documents` WHERE `project_id` = ml.`project_id` AND `review_status` = 'accepted' AND `is_archived` = 0) as accepted_docs_count
                FROM `marketplace_listings` ml
                JOIN `organizations` o ON ml.`organization_id` = o.`id`
                JOIN `environmental_projects` ep ON ml.`project_id` = ep.`id`
                LEFT JOIN `domain_profiles` dp ON ml.`domain_profile_id` = dp.`id`
                LEFT JOIN `domains` d ON dp.`domain_id` = d.`id`
                WHERE ml.`status` = 'pending_review' AND ml.`is_archived` = 0
                ORDER BY ml.`submitted_at` ASC
                LIMIT :limit OFFSET :offset";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $countSql = "SELECT COUNT(*) as total FROM `marketplace_listings` WHERE `status` = 'pending_review' AND `is_archived` = 0";
        $total = (int)$db->fetchOne($countSql)['total'];

        return [
            'data' => $listings,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => $total > 0 ? (int)ceil($total / $limit) : 1
        ];
    }

    /**
     * SUPER ADMIN: Approve listing for publication
     */
    public static function approve(int $id, int $adminUserId, ?string $comments = null): array
    {
        $db = Database::getInstance();
        $sql = "SELECT * FROM `marketplace_listings` WHERE `id` = :id AND `is_archived` = 0";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $listing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$listing) {
            throw new RuntimeException("Marketplace listing not found.");
        }

        if ($listing['status'] !== self::STATUS_PENDING_REVIEW) {
            throw new InvalidArgumentException("Only listings in 'pending_review' status can be approved. Current status: {$listing['status']}");
        }

        // Segregation of duties: Listing creator cannot self-approve their own listing
        if ((int)$listing['created_by'] === $adminUserId) {
            throw new InvalidArgumentException("Segregation of duties violation: Listing creator cannot self-approve their own marketplace listing.");
        }

        // Re-verify underlying project eligibility at approval time
        $project = EnvironmentalProject::findById((int)$listing['project_id'], (int)$listing['organization_id']);
        if (!$project || (int)$project['is_archived'] === 1 || in_array($project['classification'], ['operational_reduction', 'project_candidate'], true)) {
            throw new InvalidArgumentException("Listing approval failed: Underlying project is archived or has an ineligible classification tier.");
        }

        // Re-verify required evidence presence
        if ($project['classification'] === 'verified_environmental_asset') {
            $chkSql = "SELECT COUNT(*) FROM `project_documents`
                       WHERE `project_id` = :pid AND `review_status` = 'accepted' AND `is_archived` = 0
                         AND `document_type` IN ('verification_report', 'validation_report')";
            $chk = $db->prepare($chkSql);
            $chk->execute([':pid' => $project['id']]);
            if ((int)$chk->fetchColumn() === 0) {
                throw new InvalidArgumentException("Listing approval failed: The underlying verified asset has no active accepted verification report.");
            }
        } elseif ($project['classification'] === 'issued_carbon_credit') {
            if (empty($project['registry_name']) || empty($project['registry_serial_or_id']) || empty($project['methodology'])) {
                throw new InvalidArgumentException("Listing approval failed: Issued carbon credit requires registry credentials, serial numbers, and methodology.");
            }
            $chkSql = "SELECT COUNT(*) FROM `project_documents`
                       WHERE `project_id` = :pid AND `review_status` = 'accepted' AND `is_archived` = 0
                         AND `document_type` IN ('credit_issuance_notice', 'registry_certificate')";
            $chk = $db->prepare($chkSql);
            $chk->execute([':pid' => $project['id']]);
            if ((int)$chk->fetchColumn() === 0) {
                throw new InvalidArgumentException("Listing approval failed: The underlying issued credit has no active accepted issuance evidence.");
            }
        }

        $pdo = $db->getPdo();
        $pdo->beginTransaction();
        try {
            $uSql = "UPDATE `marketplace_listings` SET
                        `status` = 'published',
                        `reviewed_by` = :uid,
                        `reviewed_at` = NOW(),
                        `published_at` = NOW(),
                        `admin_review_notes` = :notes,
                        `rejection_reason` = NULL
                     WHERE `id` = :id";
            $uStmt = $pdo->prepare($uSql);
            $uStmt->execute([
                ':uid' => $adminUserId,
                ':notes' => $comments ?: 'Listing approved for publication',
                ':id' => $id
            ]);

            $histStmt = $pdo->prepare("INSERT INTO `marketplace_listing_reviews` (
                `listing_id`, `organization_id`, `previous_status`, `new_status`, `action`,
                `reviewer_id`, `reason`, `comments`, `created_at`
            ) VALUES (
                :lid, :oid, 'pending_review', 'published', 'approved',
                :uid, 'Super Admin approval', :comments, NOW()
            )");
            $histStmt->execute([
                ':lid' => $id,
                ':oid' => $listing['organization_id'],
                ':uid' => $adminUserId,
                ':comments' => $comments ?: 'Approved by Super Admin'
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return self::findPublicById($id) ?? $listing;
    }

    /**
     * SUPER ADMIN: Reject listing
     */
    public static function reject(int $id, int $adminUserId, string $reason, ?string $comments = null): array
    {
        if (empty(trim($reason))) {
            throw new InvalidArgumentException("A mandatory rejection reason is required.");
        }

        $db = Database::getInstance();
        $sql = "SELECT * FROM `marketplace_listings` WHERE `id` = :id AND `is_archived` = 0";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $listing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$listing) {
            throw new RuntimeException("Marketplace listing not found.");
        }

        $pdo = $db->getPdo();
        $pdo->beginTransaction();
        try {
            $uSql = "UPDATE `marketplace_listings` SET
                        `status` = 'rejected',
                        `reviewed_by` = :uid,
                        `reviewed_at` = NOW(),
                        `rejection_reason` = :reason,
                        `admin_review_notes` = :comments
                     WHERE `id` = :id";
            $uStmt = $pdo->prepare($uSql);
            $uStmt->execute([
                ':uid' => $adminUserId,
                ':reason' => $reason,
                ':comments' => $comments,
                ':id' => $id
            ]);

            $histStmt = $pdo->prepare("INSERT INTO `marketplace_listing_reviews` (
                `listing_id`, `organization_id`, `previous_status`, `new_status`, `action`,
                `reviewer_id`, `reason`, `comments`, `created_at`
            ) VALUES (
                :lid, :oid, :prev, 'rejected', 'rejected',
                :uid, :reason, :comments, NOW()
            )");
            $histStmt->execute([
                ':lid' => $id,
                ':oid' => $listing['organization_id'],
                ':prev' => $listing['status'],
                ':uid' => $adminUserId,
                ':reason' => $reason,
                ':comments' => $comments
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $listing['status'] = 'rejected';
        $listing['rejection_reason'] = $reason;
        return $listing;
    }

    /**
     * SUPER ADMIN: Suspend active published listing
     */
    public static function suspend(int $id, int $adminUserId, string $reason): array
    {
        if (empty(trim($reason))) {
            throw new InvalidArgumentException("A mandatory suspension reason is required.");
        }

        $db = Database::getInstance();
        $sql = "SELECT * FROM `marketplace_listings` WHERE `id` = :id AND `is_archived` = 0";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $listing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$listing) {
            throw new RuntimeException("Marketplace listing not found.");
        }

        $pdo = $db->getPdo();
        $pdo->beginTransaction();
        try {
            $uSql = "UPDATE `marketplace_listings` SET
                        `status` = 'suspended',
                        `suspension_reason` = :reason
                     WHERE `id` = :id";
            $uStmt = $pdo->prepare($uSql);
            $uStmt->execute([
                ':reason' => $reason,
                ':id' => $id
            ]);

            $histStmt = $pdo->prepare("INSERT INTO `marketplace_listing_reviews` (
                `listing_id`, `organization_id`, `previous_status`, `new_status`, `action`,
                `reviewer_id`, `reason`, `comments`, `created_at`
            ) VALUES (
                :lid, :oid, :prev, 'suspended', 'suspended',
                :uid, :reason, 'Listing suspended by Super Admin', NOW()
            )");
            $histStmt->execute([
                ':lid' => $id,
                ':oid' => $listing['organization_id'],
                ':prev' => $listing['status'],
                ':uid' => $adminUserId,
                ':reason' => $reason
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $listing['status'] = 'suspended';
        $listing['suspension_reason'] = $reason;
        return $listing;
    }

    /**
     * SUPER ADMIN: Restore suspended listing
     */
    public static function restore(int $id, int $adminUserId, ?string $comments = null): array
    {
        $db = Database::getInstance();
        $sql = "SELECT * FROM `marketplace_listings` WHERE `id` = :id AND `is_archived` = 0";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $listing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$listing) {
            throw new RuntimeException("Marketplace listing not found.");
        }

        $pdo = $db->getPdo();
        $pdo->beginTransaction();
        try {
            $uSql = "UPDATE `marketplace_listings` SET
                        `status` = 'published',
                        `suspension_reason` = NULL
                     WHERE `id` = :id";
            $uStmt = $pdo->prepare($uSql);
            $uStmt->execute([':id' => $id]);

            $histStmt = $pdo->prepare("INSERT INTO `marketplace_listing_reviews` (
                `listing_id`, `organization_id`, `previous_status`, `new_status`, `action`,
                `reviewer_id`, `reason`, `comments`, `created_at`
            ) VALUES (
                :lid, :oid, 'suspended', 'published', 'restored',
                :uid, 'Listing restored by Super Admin', :comments, NOW()
            )");
            $histStmt->execute([
                ':lid' => $id,
                ':oid' => $listing['organization_id'],
                ':uid' => $adminUserId,
                ':comments' => $comments ?: 'Restored to published status'
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $listing['status'] = 'published';
        return $listing;
    }

    /**
     * SUPER ADMIN: Moderation history timeline
     */
    public static function getModerationHistory(int $listingId = 0): array
    {
        $db = Database::getInstance();
        $whereClause = $listingId > 0 ? 'WHERE mlr.`listing_id` = :lid' : '';
        $params = $listingId > 0 ? [':lid' => $listingId] : [];

        $sql = "SELECT mlr.*,
                       ml.`listing_title`, ml.`listing_code`,
                       u.`name` as reviewer_name, u.`email` as reviewer_email
                FROM `marketplace_listing_reviews` mlr
                JOIN `marketplace_listings` ml ON mlr.`listing_id` = ml.`id`
                JOIN `users` u ON mlr.`reviewer_id` = u.`id`
                {$whereClause}
                ORDER BY mlr.`created_at` DESC
                LIMIT 100";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
