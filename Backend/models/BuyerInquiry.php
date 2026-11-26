<?php
declare(strict_types=1);

namespace EcoPulse\Models;

use EcoPulse\Core\Database;
use PDO;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class BuyerInquiry
{
    public const STATUS_NEW = 'new';
    public const STATUS_CONTACTED = 'contacted';
    public const STATUS_IN_DISCUSSION = 'in_discussion';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_CONTACTED,
        self::STATUS_IN_DISCUSSION,
        self::STATUS_CLOSED,
        self::STATUS_REJECTED,
    ];

    public const TYPE_GENERAL = 'general';
    public const TYPE_CREDIT_PURCHASE = 'credit_purchase';
    public const TYPE_PARTNERSHIP = 'partnership';
    public const TYPE_VERIFICATION = 'verification_inquiry';

    public const TYPES = [
        self::TYPE_GENERAL,
        self::TYPE_CREDIT_PURCHASE,
        self::TYPE_PARTNERSHIP,
        self::TYPE_VERIFICATION,
    ];

    /**
     * Public buyer inquiry submission with rate-limiting and validation
     */
    public static function submit(array $data, ?string $ipAddress = null, ?int $userId = null): array
    {
        $db = Database::getInstance();
        $listingId = (int)($data['listing_id'] ?? 0);

        // 1. Verify listing is active and published
        $sql = "SELECT `id`, `organization_id`, `listing_title`, `status`, `is_archived`
                FROM `marketplace_listings`
                WHERE `id` = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $listingId]);
        $listing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$listing || $listing['status'] !== 'published' || (int)$listing['is_archived'] === 1) {
            throw new RuntimeException("Inquiries can only be submitted for published active marketplace listings.");
        }

        // 2. Validate input fields
        $buyerName = trim($data['buyer_name'] ?? '');
        $buyerEmail = trim($data['buyer_email'] ?? '');
        $buyerCompany = trim($data['buyer_company'] ?? $data['buyer_organization'] ?? 'Independent Buyer');
        $subject = trim($data['inquiry_subject'] ?? $data['subject'] ?? 'Marketplace Listing Inquiry');
        $message = trim($data['inquiry_message'] ?? $data['initial_message'] ?? $data['message'] ?? '');
        $phone = !empty($data['buyer_phone']) ? trim($data['buyer_phone']) : null;
        $inquiryType = $data['inquiry_type'] ?? self::TYPE_GENERAL;

        if (empty($buyerName) || empty($buyerEmail) || empty($message)) {
            throw new InvalidArgumentException("Missing required inquiry fields: name, email, and message are mandatory.");
        }

        if (!filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid buyer email format.");
        }

        if (!in_array($inquiryType, self::TYPES, true)) {
            $inquiryType = self::TYPE_GENERAL;
        }

        // 3. Rate limiting: check recent submissions from same email or IP (limit: 5 within 15 min)
        $rateSql = "SELECT COUNT(*) FROM `buyer_inquiries`
                    WHERE (`buyer_email` = :email OR `ip_address` = :ip)
                      AND `created_at` > DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
        $rateStmt = $db->prepare($rateSql);
        $rateStmt->execute([
            ':email' => $buyerEmail,
            ':ip' => $ipAddress ?? '127.0.0.1'
        ]);
        if ((int)$rateStmt->fetchColumn() >= 5) {
            throw new RuntimeException("Rate limit reached. Please wait a few minutes before submitting another inquiry.");
        }

        // 4. Generate unique inquiry reference
        $inquiryRef = 'INQ-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $volume = !empty($data['volume_requested_tco2e']) ? (float)$data['volume_requested_tco2e'] : null;
        $targetPrice = !empty($data['target_price_per_tco2e']) ? (float)$data['target_price_per_tco2e'] : null;

        $pdo = $db->getPdo();
        $pdo->beginTransaction();
        try {
            $inqSql = "INSERT INTO `buyer_inquiries` (
                `inquiry_reference`, `listing_id`, `organization_id`, `buyer_user_id`,
                `buyer_name`, `buyer_company`, `buyer_email`, `buyer_phone`,
                `inquiry_subject`, `inquiry_type`, `volume_requested_tco2e`,
                `target_price_per_tco2e`, `intended_use`, `initial_message`,
                `status`, `ip_address`, `created_at`
            ) VALUES (
                :ref, :lid, :oid, :uid,
                :name, :company, :email, :phone,
                :subject, :type, :volume,
                :price, 'esg_disclosure', :message,
                'new', :ip, NOW()
            )";

            $inqStmt = $pdo->prepare($inqSql);
            $inqStmt->execute([
                ':ref' => $inquiryRef,
                ':lid' => $listingId,
                ':oid' => (int)$listing['organization_id'],
                ':uid' => $userId,
                ':name' => $buyerName,
                ':company' => $buyerCompany,
                ':email' => $buyerEmail,
                ':phone' => $phone,
                ':subject' => $subject,
                ':type' => $inquiryType,
                ':volume' => $volume,
                ':price' => $targetPrice,
                ':message' => $message,
                ':ip' => $ipAddress,
            ]);

            $inquiryId = (int)$pdo->lastInsertId();

            // Insert initial message into thread
            $msgSql = "INSERT INTO `inquiry_messages` (
                `inquiry_id`, `organization_id`, `sender_user_id`, `sender_type`, `sender_name`, `message`, `created_at`
            ) VALUES (
                :iid, :oid, :uid, 'buyer', :name, :message, NOW()
            )";
            $msgStmt = $pdo->prepare($msgSql);
            $msgStmt->execute([
                ':iid' => $inquiryId,
                ':oid' => (int)$listing['organization_id'],
                ':uid' => $userId,
                ':name' => $buyerName,
                ':message' => $message
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return [
            'id' => $inquiryId,
            'inquiry_reference' => $inquiryRef,
            'listing_id' => $listingId,
            'listing_title' => $listing['listing_title'],
            'status' => 'new',
            'message' => 'Buyer inquiry submitted successfully.'
        ];
    }

    /**
     * List inquiries for organization client portal (strictly tenant-isolated)
     */
    public static function listByOrg(int $orgId, array $filters = [], int $page = 1, int $limit = 50): array
    {
        $db = Database::getInstance();
        $conditions = ['bi.`organization_id` = :org_id'];
        $params = [':org_id' => $orgId];

        if (!empty($filters['status'])) {
            $conditions[] = 'bi.`status` = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['listing_id'])) {
            $conditions[] = 'bi.`listing_id` = :listing_id';
            $params[':listing_id'] = (int)$filters['listing_id'];
        }

        if (!empty($filters['inquiry_type'])) {
            $conditions[] = 'bi.`inquiry_type` = :inquiry_type';
            $params[':inquiry_type'] = $filters['inquiry_type'];
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(bi.`inquiry_reference` LIKE :search1 OR bi.`buyer_name` LIKE :search2 OR bi.`buyer_company` LIKE :search3 OR bi.`buyer_email` LIKE :search4 OR bi.`inquiry_subject` LIKE :search5)';
            $searchVal = '%' . $filters['search'] . '%';
            $params[':search1'] = $searchVal;
            $params[':search2'] = $searchVal;
            $params[':search3'] = $searchVal;
            $params[':search4'] = $searchVal;
            $params[':search5'] = $searchVal;
        }

        $whereClause = implode(' AND ', $conditions);
        $offset = max(0, ($page - 1) * $limit);

        $sql = "SELECT bi.*,
                       ml.`listing_title`, ml.`listing_code`, ml.`eligible_classification`,
                       (SELECT COUNT(*) FROM `inquiry_messages` WHERE `inquiry_id` = bi.`id`) as message_count
                FROM `buyer_inquiries` bi
                JOIN `marketplace_listings` ml ON bi.`listing_id` = ml.`id`
                WHERE {$whereClause}
                ORDER BY bi.`created_at` DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $countSql = "SELECT COUNT(*) as total FROM `buyer_inquiries` bi WHERE {$whereClause}";
        $countStmt = $db->prepare($countSql);
        foreach ($params as $k => $v) {
            $countStmt->bindValue($k, $v);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        return [
            'data' => $inquiries,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => $total > 0 ? (int)ceil($total / $limit) : 1
        ];
    }

    /**
     * Find single inquiry with full message thread (strictly tenant-isolated)
     */
    public static function findByIdAndOrg(int $id, int $orgId): ?array
    {
        $db = Database::getInstance();
        $sql = "SELECT bi.*,
                       ml.`listing_title`, ml.`listing_code`, ml.`eligible_classification`, ml.`unit_of_measurement`
                FROM `buyer_inquiries` bi
                JOIN `marketplace_listings` ml ON bi.`listing_id` = ml.`id`
                WHERE bi.`id` = :id AND bi.`organization_id` = :org_id";

        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $id, ':org_id' => $orgId]);
        $inquiry = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$inquiry) {
            return null;
        }

        // Attach message thread chronologically
        $msgSql = "SELECT im.*, im.`message` as message_body, u.`name` as user_author_name
                   FROM `inquiry_messages` im
                   LEFT JOIN `users` u ON im.`sender_user_id` = u.`id`
                   WHERE im.`inquiry_id` = :iid
                   ORDER BY im.`created_at` ASC";
        $msgStmt = $db->prepare($msgSql);
        $msgStmt->execute([':iid' => $id]);
        $inquiry['messages'] = $msgStmt->fetchAll(PDO::FETCH_ASSOC);

        return $inquiry;
    }

    /**
     * Add reply message to inquiry thread
     */
    public static function addMessage(int $inquiryId, int $orgId, string $message, int $userId, string $userName): array
    {
        if (empty(trim($message))) {
            throw new InvalidArgumentException("Message content cannot be empty.");
        }

        $inquiry = self::findByIdAndOrg($inquiryId, $orgId);
        if (!$inquiry) {
            throw new RuntimeException("Inquiry not found or access denied.");
        }

        $db = Database::getInstance();

        // Rate limiting: Max 20 reply messages per 15 minutes per inquiry from same sender
        $rateSql = "SELECT COUNT(*) FROM `inquiry_messages`
                    WHERE `inquiry_id` = :iid
                      AND `sender_user_id` = :uid
                      AND `created_at` > DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
        $rateStmt = $db->prepare($rateSql);
        $rateStmt->execute([':iid' => $inquiryId, ':uid' => $userId]);
        if ((int)$rateStmt->fetchColumn() >= 20) {
            throw new RuntimeException("Message rate limit reached (max 20 messages per 15 minutes). Please wait before sending further replies.");
        }

        $pdo = $db->getPdo();
        $pdo->beginTransaction();
        try {
            $sql = "INSERT INTO `inquiry_messages` (
                `inquiry_id`, `organization_id`, `sender_user_id`, `sender_type`, `sender_name`, `message`, `created_at`
            ) VALUES (
                :iid, :oid, :uid, 'organization', :name, :message, NOW()
            )";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':iid' => $inquiryId,
                ':oid' => $orgId,
                ':uid' => $userId,
                ':name' => $userName,
                ':message' => trim($message)
            ]);

            // If status was new, update to contacted
            if ($inquiry['status'] === self::STATUS_NEW) {
                $uStmt = $pdo->prepare("UPDATE `buyer_inquiries` SET `status` = 'contacted', `updated_at` = NOW() WHERE `id` = :id");
                $uStmt->execute([':id' => $inquiryId]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return self::findByIdAndOrg($inquiryId, $orgId);
    }

    /**
     * Update inquiry status
     */
    public static function updateStatus(int $inquiryId, int $orgId, string $status): bool
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException("Invalid inquiry status: {$status}");
        }

        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE `buyer_inquiries` SET `status` = :status, `updated_at` = NOW() WHERE `id` = :id AND `organization_id` = :org_id");
        return $stmt->execute([
            ':status' => $status,
            ':id' => $inquiryId,
            ':org_id' => $orgId
        ]);
    }
}
