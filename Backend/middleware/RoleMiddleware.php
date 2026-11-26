<?php
declare(strict_types=1);

namespace EcoPulse\Middleware;

use EcoPulse\Core\Request;
use EcoPulse\Core\Response;

class RoleMiddleware
{
    private array $allowedRoles;

    public function __construct(array $allowedRoles)
    {
        $this->allowedRoles = $allowedRoles;
    }

    public function __invoke(Request $request): ?bool
    {
        $user = $request->getUser();
        if (!$user) {
            Response::error('Authentication required.', 401, 'UNAUTHORIZED');
            return false;
        }

        $systemRole = $user['system_role'];
        $orgRole = $request->getOrgRole();

        // Super Admin bypasses organizational checks
        if ($systemRole === 'super_admin') {
            return null;
        }

        // Check if matching system role or org role
        if (in_array($systemRole, $this->allowedRoles, true) || ($orgRole && in_array($orgRole, $this->allowedRoles, true))) {
            return null;
        }

        Response::error('Access denied. Insufficient permissions for this action.', 403, 'FORBIDDEN');
        return false;
    }
}
