<?php
declare(strict_types=1);

namespace EcoPulse\Controllers;

use EcoPulse\Core\Database;
use EcoPulse\Core\Logger;
use EcoPulse\Core\Request;
use EcoPulse\Core\Response;
use EcoPulse\Models\DomainProfile;
use EcoPulse\Models\Organization;

class OrganizationController
{
    /**
     * GET /api/organization/dashboard
     */
    public function dashboard(Request $request): void
    {
        $org = $request->getOrganization();
        if (!$org) {
            Response::error('Organization context is required.', 403, 'TENANT_REQUIRED');
            return;
        }

        $db = Database::getInstance();
        $orgId = (int)$org['id'];

        // 1. Fetch full organization profile
        $orgProfile = Organization::findById($orgId);

        // 2. Fetch enabled domain profiles
        $enabledDomains = DomainProfile::listByOrganization($orgId);
        $activeDomains = array_values(array_filter($enabledDomains, fn($d) => $d['status'] === 'active'));

        // 3. Count facilities
        $facilityStats = $db->fetchOne(
            "SELECT COUNT(*) as total, SUM(CASE WHEN `status` = 'active' THEN 1 ELSE 0 END) as active
             FROM `facilities` WHERE `organization_id` = :oid",
            [':oid' => $orgId]
        );

        // 4. Reporting periods overview
        $periodStats = $db->fetchOne(
            "SELECT COUNT(*) as total, SUM(CASE WHEN `is_closed` = 0 THEN 1 ELSE 0 END) as active_periods
             FROM `reporting_periods` WHERE `organization_id` = :oid",
            [':oid' => $orgId]
        );
        $latestPeriod = $db->fetchOne(
            "SELECT * FROM `reporting_periods` WHERE `organization_id` = :oid ORDER BY `start_date` DESC LIMIT 1",
            [':oid' => $orgId]
        );

        // 5. Team member count
        $teamStats = $db->fetchOne(
            "SELECT COUNT(*) as total FROM `organization_members` WHERE `organization_id` = :oid AND `status` = 'active'",
            [':oid' => $orgId]
        );

        // 6. Recent activities from audit log
        $recentActivities = $db->fetchAll(
            "SELECT al.`id`, al.`action`, al.`entity_type`, al.`entity_id`, al.`created_at`, u.`name` as actor_name
             FROM `audit_logs` al
             LEFT JOIN `users` u ON u.`id` = al.`user_id`
             WHERE al.`organization_id` = :oid
             ORDER BY al.`id` DESC LIMIT 8",
            [':oid' => $orgId]
        );

        Response::json([
            'organization' => $orgProfile,
            'user_role' => $request->getOrgRole(),
            'counts' => [
                'facilities_total' => (int)($facilityStats['total'] ?? 0),
                'facilities_active' => (int)($facilityStats['active'] ?? 0),
                'enabled_domains_count' => count($activeDomains),
                'reporting_periods_count' => (int)($periodStats['total'] ?? 0),
                'team_members_count' => (int)($teamStats['total'] ?? 0),
            ],
            'enabled_domains' => $activeDomains,
            'latest_reporting_period' => $latestPeriod,
            'recent_activities' => $recentActivities,
        ], 200, 'Organization dashboard loaded successfully.');
    }

    /**
     * PUT /api/organization/current
     */
    public function update(Request $request): void
    {
        $org = $request->getOrganization();
        $orgId = (int)$org['id'];

        $name = $request->getString('name');
        $industry = $request->getString('industry_primary');
        $country = $request->getString('country');
        $city = $request->getString('city');

        if (empty($name) || empty($country) || empty($city)) {
            Response::error('Organization name, country and city are required.', 422, 'VALIDATION_ERROR');
            return;
        }

        $data = [
            'name' => $name,
            'registration_number' => $request->getString('registration_number'),
            'industry_primary' => $industry,
            'country' => $country,
            'state_region' => $request->getString('state_region'),
            'city' => $city,
            'address' => $request->getString('address'),
            'website' => $request->getString('website'),
        ];

        Organization::update($orgId, $data);

        Logger::audit(
            action: 'organization.updated',
            entityType: 'organizations',
            entityId: $orgId,
            orgId: $orgId,
            newValues: $data
        );

        Response::json(Organization::findById($orgId), 200, 'Organization profile updated.');
    }
}
