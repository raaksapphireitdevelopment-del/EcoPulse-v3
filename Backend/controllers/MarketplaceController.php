<?php
declare(strict_types=1);

namespace EcoPulse\Controllers;

use EcoPulse\Core\Request;
use EcoPulse\Core\Response;
use EcoPulse\Core\Logger;
use EcoPulse\Models\MarketplaceListing;
use EcoPulse\Models\BuyerInquiry;
use Throwable;
use InvalidArgumentException;
use RuntimeException;

class MarketplaceController
{
    // =========================================================================
    // PUBLIC MARKETPLACE ENDPOINTS
    // =========================================================================

    /**
     * GET /api/marketplace/listings
     */
    public function browse(Request $request): void
    {
        $filters = [
            'search' => $request->getString('search'),
            'domain_profile_id' => $request->getInt('domain_profile_id'),
            'location_country' => $request->getString('location_country') ?: $request->getString('country'),
            'classification_tier' => $request->getString('classification_tier') ?: $request->getString('classification'),
            'methodology' => $request->getString('methodology'),
            'sort' => $request->getString('sort', 'latest'),
        ];

        $page = max(1, $request->getInt('page', 1));
        $limit = min(50, max(1, $request->getInt('limit', 20)));

        $result = MarketplaceListing::listPublic($filters, $page, $limit);
        Response::json($result, 200, 'Published marketplace listings retrieved successfully.');
    }

    /**
     * GET /api/marketplace/listings/:id
     */
    public function show(Request $request, array $params): void
    {
        $idOrCode = $params['id'] ?? '';
        if (empty($idOrCode)) {
            Response::error('Listing identifier is required.', 400, 'BAD_REQUEST');
            return;
        }

        $listing = MarketplaceListing::findPublicById($idOrCode);
        if (!$listing) {
            Response::error('Marketplace listing not found or not published.', 404, 'NOT_FOUND');
            return;
        }

        Response::json($listing, 200, 'Marketplace listing details retrieved successfully.');
    }

    /**
     * POST /api/marketplace/inquiries
     */
    public function submitInquiry(Request $request): void
    {
        $body = $request->getBody();
        $user = $request->getUser();
        $userId = $user ? (int)$user['id'] : null;
        $ip = $request->getIp();

        try {
            $inquiry = BuyerInquiry::submit($body, $ip, $userId);
            Logger::audit(
                action: 'buyer.inquiry_submitted',
                entityType: 'buyer_inquiries',
                entityId: (int)$inquiry['id'],
                orgId: 0,
                userId: $userId,
                newValues: ['ref' => $inquiry['inquiry_reference'], 'listing_id' => $inquiry['listing_id']]
            );

            Response::json($inquiry, 201, 'Inquiry submitted successfully. The project owner has been notified.');
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422, 'VALIDATION_FAILED');
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 429, 'RATE_LIMITED_OR_INVALID');
        } catch (Throwable $e) {
            Response::error('Failed to submit inquiry: ' . $e->getMessage(), 500, 'INQUIRY_FAILED');
        }
    }

    // =========================================================================
    // ORGANIZATION CLIENT MARKETPLACE ENDPOINTS
    // =========================================================================

    /**
     * GET /api/organization/marketplace/eligible-projects
     */
    public function eligibleProjects(Request $request): void
    {
        $org = $request->getOrganization();
        $orgId = (int)$org['id'];

        $projects = MarketplaceListing::getEligibleProjects($orgId);
        Response::json($projects, 200, 'Eligible projects retrieved successfully.');
    }

    /**
     * GET /api/organization/marketplace/listings
     */
    public function myListings(Request $request): void
    {
        $org = $request->getOrganization();
        $orgId = (int)$org['id'];

        $filters = [
            'status' => $request->getString('status'),
            'search' => $request->getString('search'),
        ];
        $page = max(1, $request->getInt('page', 1));
        $limit = min(50, max(1, $request->getInt('limit', 20)));

        $result = MarketplaceListing::listByOrg($orgId, $filters, $page, $limit);
        Response::json($result, 200, 'Organization listings retrieved successfully.');
    }

    /**
     * GET /api/organization/marketplace/listings/:id
     */
    public function showMyListing(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $orgId = (int)$org['id'];
        $id = (int)($params['id'] ?? 0);

        $listing = MarketplaceListing::findByOrg($id, $orgId);
        if (!$listing) {
            Response::error('Listing not found or access denied.', 404, 'NOT_FOUND');
            return;
        }

        Response::json($listing, 200, 'Listing details retrieved successfully.');
    }

    /**
     * POST /api/organization/marketplace/listings
     */
    public function storeListing(Request $request): void
    {
        $org = $request->getOrganization();
        $user = $request->getUser();
        $orgId = (int)$org['id'];
        $userId = (int)$user['id'];

        $body = $request->getBody();
        if (empty($body['project_id'])) {
            Response::error('project_id is mandatory.', 422, 'VALIDATION_FAILED');
            return;
        }

        try {
            $listing = MarketplaceListing::create($body, $orgId, $userId);
            Logger::audit(
                action: 'marketplace.listing_created',
                entityType: 'marketplace_listings',
                entityId: (int)$listing['id'],
                orgId: $orgId,
                userId: $userId,
                newValues: ['code' => $listing['listing_code'], 'status' => 'draft']
            );

            Response::json($listing, 201, 'Marketplace listing draft created successfully.');
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422, 'GOVERNANCE_VIOLATION');
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 404, 'NOT_FOUND');
        } catch (Throwable $e) {
            Response::error('Failed to create listing: ' . $e->getMessage(), 500, 'CREATE_FAILED');
        }
    }

    /**
     * POST /api/organization/marketplace/listings/:id/submit
     */
    public function submitListing(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $user = $request->getUser();
        $orgId = (int)$org['id'];
        $userId = (int)$user['id'];
        $id = (int)($params['id'] ?? 0);

        try {
            $listing = MarketplaceListing::submitForReview($id, $orgId, $userId);
            Logger::audit(
                action: 'marketplace.listing_submitted',
                entityType: 'marketplace_listings',
                entityId: $id,
                orgId: $orgId,
                userId: $userId,
                newValues: ['status' => 'pending_review']
            );

            Response::json($listing, 200, 'Listing submitted for Super Admin moderation successfully.');
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422, 'INVALID_STATE');
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 404, 'NOT_FOUND');
        } catch (Throwable $e) {
            Response::error('Failed to submit listing: ' . $e->getMessage(), 500, 'SUBMIT_FAILED');
        }
    }

    /**
     * POST /api/organization/marketplace/listings/:id/archive
     */
    public function archiveListing(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $user = $request->getUser();
        $orgId = (int)$org['id'];
        $userId = (int)$user['id'];
        $id = (int)($params['id'] ?? 0);

        $success = MarketplaceListing::archive($id, $orgId, $userId);
        if (!$success) {
            Response::error('Listing not found or access denied.', 404, 'NOT_FOUND');
            return;
        }

        Logger::audit(
            action: 'marketplace.listing_archived',
            entityType: 'marketplace_listings',
            entityId: $id,
            orgId: $orgId,
            userId: $userId
        );

        Response::json(['archived' => true], 200, 'Listing archived successfully.');
    }

    /**
     * GET /api/organization/marketplace/inquiries
     */
    public function myInquiries(Request $request): void
    {
        $org = $request->getOrganization();
        $orgId = (int)$org['id'];

        $filters = [
            'status' => $request->getString('status'),
            'listing_id' => $request->getInt('listing_id'),
            'inquiry_type' => $request->getString('inquiry_type'),
            'search' => $request->getString('search'),
        ];
        $page = max(1, $request->getInt('page', 1));
        $limit = min(50, max(1, $request->getInt('limit', 20)));

        $result = BuyerInquiry::listByOrg($orgId, $filters, $page, $limit);
        Response::json($result, 200, 'Buyer inquiries retrieved successfully.');
    }

    /**
     * GET /api/organization/marketplace/inquiries/:id
     */
    public function showInquiry(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $orgId = (int)$org['id'];
        $id = (int)($params['id'] ?? 0);

        $inquiry = BuyerInquiry::findByIdAndOrg($id, $orgId);
        if (!$inquiry) {
            Response::error('Inquiry not found or access denied.', 404, 'NOT_FOUND');
            return;
        }

        Response::json($inquiry, 200, 'Inquiry details and message history retrieved successfully.');
    }

    /**
     * POST /api/organization/marketplace/inquiries/:id/messages
     */
    public function sendInquiryMessage(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $user = $request->getUser();
        $orgId = (int)$org['id'];
        $userId = (int)$user['id'];
        $userName = (string)$user['name'];
        $id = (int)($params['id'] ?? 0);

        $message = $request->getString('message');
        if (empty(trim($message))) {
            Response::error('Message cannot be empty.', 422, 'VALIDATION_FAILED');
            return;
        }

        try {
            $updatedInquiry = BuyerInquiry::addMessage($id, $orgId, $message, $userId, $userName);
            Logger::audit(
                action: 'buyer.inquiry_reply_sent',
                entityType: 'buyer_inquiries',
                entityId: $id,
                orgId: $orgId,
                userId: $userId
            );

            Response::json($updatedInquiry, 200, 'Reply sent successfully.');
        } catch (RuntimeException $e) {
            $isRateLimit = str_contains(strtolower($e->getMessage()), 'rate limit');
            Response::error($e->getMessage(), $isRateLimit ? 429 : 404, $isRateLimit ? 'RATE_LIMITED' : 'NOT_FOUND');
        } catch (Throwable $e) {
            Response::error('Failed to post reply: ' . $e->getMessage(), 500, 'MESSAGE_FAILED');
        }
    }

    /**
     * PATCH /api/organization/marketplace/inquiries/:id/status
     */
    public function updateInquiryStatus(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $user = $request->getUser();
        $orgId = (int)$org['id'];
        $userId = (int)$user['id'];
        $id = (int)($params['id'] ?? 0);

        $status = $request->getString('status');
        if (!in_array($status, BuyerInquiry::STATUSES, true)) {
            Response::error("Invalid inquiry status: {$status}.", 422, 'VALIDATION_FAILED');
            return;
        }

        $success = BuyerInquiry::updateStatus($id, $orgId, $status);
        if (!$success) {
            Response::error('Inquiry not found or access denied.', 404, 'NOT_FOUND');
            return;
        }

        Logger::audit(
            action: 'buyer.inquiry_status_updated',
            entityType: 'buyer_inquiries',
            entityId: $id,
            orgId: $orgId,
            userId: $userId,
            newValues: ['status' => $status]
        );

        Response::json(['status' => $status], 200, 'Inquiry status updated successfully.');
    }

    // =========================================================================
    // SUPER ADMIN MODERATION ENDPOINTS
    // =========================================================================

    /**
     * GET /api/admin/marketplace/listings/pending
     */
    public function pendingListings(Request $request): void
    {
        $page = max(1, $request->getInt('page', 1));
        $limit = min(100, max(1, $request->getInt('limit', 50)));

        $result = MarketplaceListing::listPending($page, $limit);
        Response::json($result, 200, 'Pending marketplace listings queue retrieved.');
    }

    /**
     * POST /api/admin/marketplace/listings/:id/approve
     */
    public function approveListing(Request $request, array $params): void
    {
        $user = $request->getUser();
        $adminId = (int)$user['id'];
        $id = (int)($params['id'] ?? 0);
        $comments = $request->getString('comments');

        try {
            $listing = MarketplaceListing::approve($id, $adminId, $comments);
            Logger::audit(
                action: 'admin.marketplace_listing_approved',
                entityType: 'marketplace_listings',
                entityId: $id,
                orgId: 0,
                userId: $adminId,
                newValues: ['status' => 'published']
            );

            Response::json($listing, 200, 'Marketplace listing approved and published successfully.');
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422, 'INVALID_STATE');
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 404, 'NOT_FOUND');
        } catch (Throwable $e) {
            Response::error('Failed to approve listing: ' . $e->getMessage(), 500, 'APPROVE_FAILED');
        }
    }

    /**
     * POST /api/admin/marketplace/listings/:id/reject
     */
    public function rejectListing(Request $request, array $params): void
    {
        $user = $request->getUser();
        $adminId = (int)$user['id'];
        $id = (int)($params['id'] ?? 0);

        $reason = $request->getString('reason') ?: $request->getString('rejection_reason');
        $comments = $request->getString('comments');

        if (empty(trim($reason))) {
            Response::error('A mandatory rejection reason is required for audit integrity.', 422, 'REASON_REQUIRED');
            return;
        }

        try {
            $listing = MarketplaceListing::reject($id, $adminId, $reason, $comments);
            Logger::audit(
                action: 'admin.marketplace_listing_rejected',
                entityType: 'marketplace_listings',
                entityId: $id,
                orgId: 0,
                userId: $adminId,
                newValues: ['status' => 'rejected', 'reason' => $reason]
            );

            Response::json($listing, 200, 'Marketplace listing rejected.');
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422, 'INVALID_STATE');
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 404, 'NOT_FOUND');
        } catch (Throwable $e) {
            Response::error('Failed to reject listing: ' . $e->getMessage(), 500, 'REJECT_FAILED');
        }
    }

    /**
     * POST /api/admin/marketplace/listings/:id/suspend
     */
    public function suspendListing(Request $request, array $params): void
    {
        $user = $request->getUser();
        $adminId = (int)$user['id'];
        $id = (int)($params['id'] ?? 0);

        $reason = $request->getString('reason');
        if (empty(trim($reason))) {
            Response::error('A mandatory suspension reason is required.', 422, 'REASON_REQUIRED');
            return;
        }

        try {
            $listing = MarketplaceListing::suspend($id, $adminId, $reason);
            Logger::audit(
                action: 'admin.marketplace_listing_suspended',
                entityType: 'marketplace_listings',
                entityId: $id,
                orgId: 0,
                userId: $adminId,
                newValues: ['status' => 'suspended', 'reason' => $reason]
            );

            Response::json($listing, 200, 'Marketplace listing suspended.');
        } catch (Throwable $e) {
            Response::error('Failed to suspend listing: ' . $e->getMessage(), 500, 'SUSPEND_FAILED');
        }
    }

    /**
     * POST /api/admin/marketplace/listings/:id/restore
     */
    public function restoreListing(Request $request, array $params): void
    {
        $user = $request->getUser();
        $adminId = (int)$user['id'];
        $id = (int)($params['id'] ?? 0);
        $comments = $request->getString('comments');

        try {
            $listing = MarketplaceListing::restore($id, $adminId, $comments);
            Logger::audit(
                action: 'admin.marketplace_listing_restored',
                entityType: 'marketplace_listings',
                entityId: $id,
                orgId: 0,
                userId: $adminId,
                newValues: ['status' => 'published']
            );

            Response::json($listing, 200, 'Marketplace listing restored to published status.');
        } catch (Throwable $e) {
            Response::error('Failed to restore listing: ' . $e->getMessage(), 500, 'RESTORE_FAILED');
        }
    }

    /**
     * GET /api/admin/marketplace/moderation-history
     */
    public function moderationHistory(Request $request): void
    {
        $listingId = $request->getInt('listing_id', 0);
        $history = MarketplaceListing::getModerationHistory($listingId);
        Response::json($history, 200, 'Marketplace moderation history retrieved successfully.');
    }
}
