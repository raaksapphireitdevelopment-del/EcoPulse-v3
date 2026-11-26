<?php
declare(strict_types=1);

namespace EcoPulse\Controllers;

use EcoPulse\Core\Logger;
use EcoPulse\Core\Request;
use EcoPulse\Core\Response;
use EcoPulse\Models\Domain;
use EcoPulse\Models\DomainProfile;
use EcoPulse\Models\Facility;
use EcoPulse\Models\ReportingPeriod;

class DomainController
{
    /**
     * GET /api/domains
     * List all 9 system supported domains
     */
    public function index(Request $request): void
    {
        $domains = Domain::listAll();
        Response::json($domains, 200, 'Domains retrieved successfully.');
    }

    /**
     * GET /api/domains/profiles
     * List enabled domain profiles for the current organization
     */
    public function profiles(Request $request): void
    {
        $org = $request->getOrganization();
        if (!$org) {
            Response::error('Organization context is required.', 403, 'TENANT_REQUIRED');
            return;
        }

        $profiles = DomainProfile::listByOrganization((int)$org['id']);
        Response::json($profiles, 200, 'Organization domain profiles retrieved.');
    }

    /**
     * POST /api/domains/profiles
     * Enable a new domain for the current organization (Owner/Admin only)
     */
    public function enable(Request $request): void
    {
        $org = $request->getOrganization();
        $domainId = $request->getInt('domain_id');
        $profileName = $request->getString('profile_name');
        $baseYear = $request->getInt('base_year', (int)date('Y'));

        if ($domainId <= 0) {
            Response::error('A valid domain ID is required.', 422, 'VALIDATION_ERROR');
            return;
        }

        $domain = Domain::findById($domainId);
        if (!$domain) {
            Response::error('Specified domain does not exist.', 404, 'NOT_FOUND');
            return;
        }

        if (empty($profileName)) {
            $profileName = $org['name'] . ' - ' . $domain['name'] . ' Profile';
        }

        $profileId = DomainProfile::enable((int)$org['id'], $domainId, $profileName, $baseYear);

        Logger::audit(
            action: 'domain.enabled',
            entityType: 'domain_profiles',
            entityId: $profileId,
            orgId: (int)$org['id'],
            newValues: ['domain_code' => $domain['code'], 'profile_name' => $profileName]
        );

        Response::json([
            'domain_profile_id' => $profileId,
            'domain' => $domain,
            'profile_name' => $profileName,
        ], 201, "Domain '{$domain['name']}' workspace enabled successfully.");
    }

    /**
     * POST /api/domains/profiles/:id/disable
     * Deactivate a domain profile (Owner/Admin only)
     */
    public function disable(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $profileId = (int)($params['id'] ?? 0);

        if ($profileId <= 0) {
            Response::error('Invalid domain profile ID.', 422, 'VALIDATION_ERROR');
            return;
        }

        DomainProfile::disable($profileId, (int)$org['id']);

        Logger::audit(
            action: 'domain.disabled',
            entityType: 'domain_profiles',
            entityId: $profileId,
            orgId: (int)$org['id']
        );

        Response::json(null, 200, 'Domain profile deactivated.');
    }

    /**
     * GET /api/domains/:code/workspace
     * Dynamic workspace data for a specific domain
     */
    public function workspace(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $code = strtolower(trim($params['code'] ?? ''));

        $domain = Domain::findByCode($code);
        if (!$domain) {
            Response::error("Unknown domain code '{$code}'.", 404, 'NOT_FOUND');
            return;
        }

        // Verify that this domain is enabled for the organization
        $profile = DomainProfile::findByOrgAndDomainCode((int)$org['id'], $code);
        if (!$profile || $profile['status'] !== 'active') {
            Response::error(
                "The '{$domain['name']}' workspace is not enabled for your organization. Please enable it in organization settings.",
                403,
                'DOMAIN_NOT_ENABLED'
            );
            return;
        }

        // Fetch activity categories for this domain
        $categories = Domain::getActivityCategories((int)$domain['id']);

        // Group categories by Scope
        $groupedCategories = [
            'scope_1' => [],
            'scope_2' => [],
            'scope_3' => [],
        ];
        foreach ($categories as $cat) {
            $scope = $cat['scope'];
            if (isset($groupedCategories[$scope])) {
                $groupedCategories[$scope][] = $cat;
            }
        }

        // Fetch facilities associated with this domain profile (fallback to organization facilities)
        $facilitiesRes = Facility::listByOrganization((int)$org['id'], (int)$profile['id'], null, 'active');
        if (empty($facilitiesRes['facilities'])) {
            $facilitiesRes = Facility::listByOrganization((int)$org['id'], null, null, 'active');
        }

        // Fetch active reporting periods
        $periods = ReportingPeriod::listByOrganization((int)$org['id']);

        Response::json([
            'domain' => $domain,
            'domain_profile' => $profile,
            'categories' => $categories,
            'categories_by_scope' => $groupedCategories,
            'facilities' => $facilitiesRes['facilities'],
            'reporting_periods' => $periods,
        ], 200, "{$domain['name']} workspace loaded.");
    }
}
