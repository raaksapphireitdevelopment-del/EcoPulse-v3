<?php
declare(strict_types=1);

namespace EcoPulse\Middleware;

use EcoPulse\Core\Auth;
use EcoPulse\Core\Database;
use EcoPulse\Core\Request;
use EcoPulse\Core\Response;
use EcoPulse\Core\Session;

class AuthMiddleware
{
    public function __invoke(Request $request): ?bool
    {
        Session::start();
        $userId = Auth::id();

        if ($userId === null) {
            Response::error('Authentication required. Please log in to proceed.', 401, 'UNAUTHORIZED');
            return false;
        }

        $db = Database::getInstance();
        $user = $db->fetchOne(
            'SELECT `id`, `name`, `email`, `system_role`, `status` FROM `users` WHERE `id` = :id LIMIT 1',
            [':id' => $userId]
        );

        if (!$user || $user['status'] !== 'active') {
            Auth::logout();
            Response::error('User account is inactive or suspended.', 403, 'FORBIDDEN');
            return false;
        }

        $request->setUser($user);

        // Resolve Active Organization
        $activeOrgId = Session::getActiveOrgId();

        // If no active org in session, find default primary org for user
        if ($activeOrgId === null && $user['system_role'] !== 'buyer') {
            $membership = $db->fetchOne(
                'SELECT `organization_id`, `org_role` FROM `organization_members` WHERE `user_id` = :uid AND `status` = "active" ORDER BY `id` ASC LIMIT 1',
                [':uid' => $userId]
            );
            if ($membership) {
                $activeOrgId = (int)$membership['organization_id'];
                Session::setActiveOrgId($activeOrgId);
            } elseif ($user['system_role'] === 'super_admin') {
                $firstOrg = $db->fetchOne('SELECT `id` FROM `organizations` WHERE `status` = "active" ORDER BY `id` ASC LIMIT 1');
                if ($firstOrg) {
                    $activeOrgId = (int)$firstOrg['id'];
                    Session::setActiveOrgId($activeOrgId);
                }
            }
        }

        if ($activeOrgId !== null) {
            $org = $db->fetchOne(
                'SELECT `id`, `name`, `industry_primary`, `country`, `status` FROM `organizations` WHERE `id` = :id LIMIT 1',
                [':id' => $activeOrgId]
            );
            $membership = $db->fetchOne(
                'SELECT `org_role`, `status` FROM `organization_members` WHERE `organization_id` = :oid AND `user_id` = :uid LIMIT 1',
                [':oid' => $activeOrgId, ':uid' => $userId]
            );

            if ($org && ($user['system_role'] === 'super_admin' || ($membership && $membership['status'] === 'active'))) {
                $request->setOrganization($org, $membership['org_role'] ?? ($user['system_role'] === 'super_admin' ? 'organization_owner' : null));
            }
        }

        return null;
    }
}
