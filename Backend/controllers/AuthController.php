<?php
declare(strict_types=1);

namespace EcoPulse\Controllers;

use EcoPulse\Core\Auth;
use EcoPulse\Core\Database;
use EcoPulse\Core\Logger;
use EcoPulse\Core\Request;
use EcoPulse\Core\Response;
use EcoPulse\Core\Session;
use EcoPulse\Models\Organization;
use EcoPulse\Models\OrganizationMember;
use EcoPulse\Models\User;

class AuthController
{
    /**
     * POST /api/auth/register
     */
    public function register(Request $request): void
    {
        $accountType = $request->getString('account_type', 'organization'); // 'organization' or 'buyer'
        $name = $request->getString('name');
        $email = strtolower($request->getString('email'));
        $password = $request->getString('password');
        $phone = $request->getString('phone', '');

        // Validation
        if (empty($name) || strlen($name) < 2) {
            Response::error('Full name is required (at least 2 characters).', 422, 'VALIDATION_ERROR', ['name' => 'Name too short.']);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('A valid email address is required.', 422, 'VALIDATION_ERROR', ['email' => 'Invalid email format.']);
            return;
        }

        if (strlen($password) < 8) {
            Response::error('Password must be at least 8 characters long.', 422, 'VALIDATION_ERROR', ['password' => 'Password too short.']);
            return;
        }

        if (User::findByEmail($email)) {
            Response::error('An account with this email address already exists.', 409, 'CONFLICT', ['email' => 'Email already registered.']);
            return;
        }

        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            $systemRole = ($accountType === 'buyer') ? 'buyer' : 'user';
            $passwordHash = Auth::hashPassword($password);

            $userId = User::create([
                'name' => $name,
                'email' => $email,
                'password_hash' => $passwordHash,
                'system_role' => $systemRole,
                'phone' => $phone,
                'status' => 'active',
            ]);

            $orgId = null;

            if ($accountType === 'organization') {
                $orgName = $request->getString('organization_name', $name . ' Enterprises');
                $industry = $request->getString('industry_primary', 'Healthcare');
                $country = $request->getString('country', 'India');
                $city = $request->getString('city', 'Mumbai');
                $domainCode = $request->getString('initial_domain', 'healthcare');

                $orgId = Organization::create([
                    'name' => $orgName,
                    'registration_number' => $request->getString('registration_number', 'REG-' . strtoupper(substr(uniqid(), 7))),
                    'industry_primary' => $industry,
                    'country' => $country,
                    'city' => $city,
                    'address' => $request->getString('address', ''),
                    'website' => $request->getString('website', ''),
                    'status' => 'active',
                ]);

                // Create organization owner membership
                OrganizationMember::create($orgId, $userId, 'organization_owner', 'active');

                // Activate requested initial domain profile
                $domain = $db->fetchOne('SELECT `id`, `name` FROM `domains` WHERE `code` = :code LIMIT 1', [':code' => $domainCode]);
                if ($domain) {
                    $db->insert('domain_profiles', [
                        'organization_id' => $orgId,
                        'domain_id' => $domain['id'],
                        'profile_name' => $orgName . ' - ' . $domain['name'] . ' Profile',
                        'base_year' => (int)date('Y'),
                        'config_settings' => json_encode(['setup_complete' => true]),
                        'status' => 'active',
                    ]);
                }

                // Create default active reporting period for current year
                $currentYear = date('Y');
                $db->insert('reporting_periods', [
                    'organization_id' => $orgId,
                    'name' => 'Calendar Year ' . $currentYear,
                    'period_type' => 'annual',
                    'start_date' => $currentYear . '-01-01',
                    'end_date' => $currentYear . '-12-31',
                    'is_closed' => 0,
                ]);
            }

            $db->commit();

            // Fetch newly created user and auto login
            $user = User::findById($userId);
            Auth::login($user, $orgId);

            Logger::audit(
                action: 'user.registered',
                entityType: 'users',
                entityId: $userId,
                orgId: $orgId,
                userId: $userId,
                newValues: ['name' => $name, 'email' => $email, 'role' => $systemRole, 'account_type' => $accountType]
            );

            Response::json([
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'system_role' => $user['system_role'],
                ],
                'active_organization_id' => $orgId,
                'csrf_token' => Session::getCsrfToken(),
            ], 201, 'Account successfully created.');

        } catch (\Throwable $e) {
            $db->rollBack();
            Logger::error('Registration error: ' . $e->getMessage());
            Response::error('Failed to create account: ' . $e->getMessage(), 500, 'REGISTRATION_FAILED');
        }
    }

    /**
     * POST /api/auth/login
     */
    public function login(Request $request): void
    {
        $email = strtolower($request->getString('email'));
        $password = $request->getString('password');

        if (empty($email) || empty($password)) {
            Response::error('Email and password are required.', 422, 'VALIDATION_ERROR');
            return;
        }

        $user = User::findByEmail($email);
        if (!$user) {
            Response::error('Invalid email or password.', 401, 'INVALID_CREDENTIALS');
            return;
        }

        if ($user['status'] !== 'active') {
            Response::error('This account has been deactivated or suspended.', 403, 'ACCOUNT_INACTIVE');
            return;
        }

        if (!Auth::verifyPassword($password, $user['password_hash'])) {
            Response::error('Invalid email or password.', 401, 'INVALID_CREDENTIALS');
            return;
        }

        // Resolve organizations for user
        $orgs = User::getUserOrganizations((int)$user['id']);
        $primaryOrgId = !empty($orgs) ? (int)$orgs[0]['id'] : null;

        // Start secure session
        Auth::login($user, $primaryOrgId);

        Logger::audit(
            action: 'user.login',
            entityType: 'users',
            entityId: (int)$user['id'],
            orgId: $primaryOrgId,
            userId: (int)$user['id']
        );

        Response::json([
            'user' => [
                'id' => (int)$user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'system_role' => $user['system_role'],
            ],
            'active_organization' => !empty($orgs) ? $orgs[0] : null,
            'organizations' => $orgs,
            'csrf_token' => Session::getCsrfToken(),
        ], 200, 'Login successful.');
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(Request $request): void
    {
        $userId = Auth::id();
        if ($userId) {
            Logger::audit(
                action: 'user.logout',
                entityType: 'users',
                entityId: $userId,
                userId: $userId
            );
        }

        Auth::logout();
        Response::json(null, 200, 'Logged out successfully.');
    }

    /**
     * GET /api/auth/me
     */
    public function me(Request $request): void
    {
        Session::start();
        $userId = Auth::id();

        if (!$userId) {
            Response::error('Not authenticated.', 401, 'UNAUTHORIZED');
            return;
        }

        $user = User::findById($userId);
        if (!$user || $user['status'] !== 'active') {
            Auth::logout();
            Response::error('User session invalid or inactive.', 401, 'UNAUTHORIZED');
            return;
        }

        $orgs = User::getUserOrganizations((int)$user['id']);
        $activeOrgId = Session::getActiveOrgId();

        $activeOrg = null;
        if ($activeOrgId) {
            foreach ($orgs as $o) {
                if ((int)$o['id'] === $activeOrgId) {
                    $activeOrg = $o;
                    break;
                }
            }
            if (!$activeOrg && !empty($orgs)) {
                $activeOrg = $orgs[0];
                Session::setActiveOrgId((int)$activeOrg['id']);
            }
        } elseif (!empty($orgs)) {
            $activeOrg = $orgs[0];
            Session::setActiveOrgId((int)$activeOrg['id']);
        }

        $enabledDomains = $activeOrg ? Organization::getEnabledDomains((int)$activeOrg['id']) : [];

        Response::json([
            'user' => [
                'id' => (int)$user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'system_role' => $user['system_role'],
                'phone' => $user['phone'],
                'avatar_url' => $user['avatar_url'],
            ],
            'active_organization' => $activeOrg,
            'enabled_domains' => $enabledDomains,
            'organizations' => $orgs,
            'csrf_token' => Session::getCsrfToken(),
        ], 200, 'Session verified.');
    }

    /**
     * POST /api/auth/switch-org
     */
    public function switchOrganization(Request $request): void
    {
        $userId = Auth::id();
        if (!$userId) {
            Response::error('Authentication required.', 401, 'UNAUTHORIZED');
            return;
        }

        $targetOrgId = $request->getInt('organization_id');
        if ($targetOrgId <= 0) {
            Response::error('Invalid organization ID.', 422, 'VALIDATION_ERROR');
            return;
        }

        $user = User::findById($userId);
        if ($user['system_role'] !== 'super_admin') {
            $membership = OrganizationMember::findMembership($targetOrgId, $userId);
            if (!$membership || $membership['status'] !== 'active') {
                Response::error('You do not have access to this organization.', 403, 'FORBIDDEN');
                return;
            }
        }

        Session::setActiveOrgId($targetOrgId);

        Response::json([
            'active_organization_id' => $targetOrgId,
        ], 200, 'Switched active organization.');
    }
}
