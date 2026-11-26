<?php
declare(strict_types=1);

namespace EcoPulse\Middleware;

use EcoPulse\Core\Request;
use EcoPulse\Core\Response;

class TenantMiddleware
{
    public function __invoke(Request $request): ?bool
    {
        $org = $request->getOrganization();
        $user = $request->getUser();

        if ($user && $user['system_role'] === 'super_admin') {
            return null; // Super Admin can operate across all or without active tenant
        }

        if (!$org || empty($org['id'])) {
            Response::error('Organization context is required to perform this action.', 403, 'TENANT_REQUIRED');
            return false;
        }

        return null;
    }
}
