<?php
declare(strict_types=1);

namespace EcoPulse\Controllers;

use EcoPulse\Core\Auth;
use EcoPulse\Core\Database;
use EcoPulse\Core\Logger;
use EcoPulse\Core\Request;
use EcoPulse\Core\Response;
use EcoPulse\Models\OrganizationMember;
use EcoPulse\Models\User;

class TeamController
{
    /**
     * GET /api/team
     * List all members of current organization
     */
    public function index(Request $request): void
    {
        $org = $request->getOrganization();
        $db = Database::getInstance();

        $sql = "SELECT om.id as membership_id, om.org_role, om.status as membership_status, om.created_at as joined_at,
                       u.id as user_id, u.name, u.email, u.phone, u.last_login_at
                FROM `organization_members` om
                INNER JOIN `users` u ON u.id = om.user_id
                WHERE om.organization_id = :oid
                ORDER BY FIELD(om.org_role, 'organization_owner', 'organization_admin', 'organization_member'), u.name ASC";

        $members = $db->fetchAll($sql, [':oid' => (int)$org['id']]);
        Response::json($members, 200, 'Team members retrieved.');
    }

    /**
     * POST /api/team
     * Add or invite team member to organization
     */
    public function store(Request $request): void
    {
        $org = $request->getOrganization();
        $orgId = (int)$org['id'];

        $name = $request->getString('name');
        $email = strtolower($request->getString('email'));
        $role = $request->getString('org_role', 'organization_member');
        $phone = $request->getString('phone', '');

        // Validation
        if (empty($name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Valid name and email are required.', 422, 'VALIDATION_ERROR');
            return;
        }

        // Strict security rule: Do not allow assigning super_admin
        if ($role === 'super_admin' || $role === ROLE_SUPER_ADMIN) {
            Response::error('Organization administrators cannot grant the super_admin role.', 403, 'FORBIDDEN');
            return;
        }

        if (!in_array($role, ['organization_owner', 'organization_admin', 'organization_member'], true)) {
            $role = 'organization_member';
        }

        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            $user = User::findByEmail($email);
            $userId = null;

            if ($user) {
                $userId = (int)$user['id'];
                // Check if already a member of this organization
                $existingMembership = OrganizationMember::findMembership($orgId, $userId);
                if ($existingMembership) {
                    if ($existingMembership['status'] === 'active') {
                        $db->rollBack();
                        Response::error('This user is already an active member of this organization.', 409, 'ALREADY_MEMBER');
                        return;
                    }
                    // Reactivate membership
                    $db->update('organization_members', [
                        'status' => 'active',
                        'org_role' => $role,
                    ], '`id` = :id', [':id' => $existingMembership['id']]);

                    $db->commit();
                    Response::json(['user_id' => $userId, 'org_role' => $role], 200, 'Membership reactivated.');
                    return;
                }
            } else {
                // Create user account with default initial password
                $tempPassword = $request->getString('password', 'EcoPulse@' . rand(1000, 9999));
                $userId = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password_hash' => Auth::hashPassword($tempPassword),
                    'system_role' => 'user',
                    'phone' => $phone,
                    'status' => 'active',
                ]);
            }

            // Create organization membership
            $membershipId = OrganizationMember::create($orgId, $userId, $role, 'active');

            $db->commit();

            Logger::audit(
                action: 'team.member_added',
                entityType: 'organization_members',
                entityId: $membershipId,
                orgId: $orgId,
                newValues: ['user_id' => $userId, 'email' => $email, 'role' => $role]
            );

            Response::json([
                'membership_id' => $membershipId,
                'user_id' => $userId,
                'name' => $name,
                'email' => $email,
                'org_role' => $role,
            ], 201, "Member '{$name}' added to organization.");

        } catch (\Throwable $e) {
            $db->rollBack();
            Logger::error('Failed to add team member: ' . $e->getMessage());
            Response::error('Failed to add team member: ' . $e->getMessage(), 500, 'SERVER_ERROR');
        }
    }

    /**
     * PUT /api/team/:id
     * Update member role or status
     */
    public function update(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $orgId = (int)$org['id'];
        $membershipId = (int)($params['id'] ?? 0);

        $db = Database::getInstance();
        $membership = $db->fetchOne(
            "SELECT * FROM `organization_members` WHERE `id` = :id AND `organization_id` = :oid LIMIT 1",
            [':id' => $membershipId, ':oid' => $orgId]
        );

        if (!$membership) {
            Response::error('Team member record not found.', 404, 'NOT_FOUND');
            return;
        }

        $newRole = $request->getString('org_role', $membership['org_role']);
        $newStatus = $request->getString('status', $membership['status']);

        // Prevent granting super_admin
        if ($newRole === 'super_admin' || $newRole === ROLE_SUPER_ADMIN) {
            Response::error('Organization administrators cannot grant the super_admin role.', 403, 'FORBIDDEN');
            return;
        }

        // Check: do not deactivate the last organization owner
        if ($membership['org_role'] === 'organization_owner' && ($newStatus === 'inactive' || $newRole !== 'organization_owner')) {
            $ownerCount = (int)($db->fetchOne(
                "SELECT COUNT(*) as count FROM `organization_members` WHERE `organization_id` = :oid AND `org_role` = 'organization_owner' AND `status` = 'active'",
                [':oid' => $orgId]
            )['count'] ?? 0);

            if ($ownerCount <= 1) {
                Response::error('Cannot modify or deactivate the only organization owner. Assign another owner first.', 422, 'LAST_OWNER_PROTECTION');
                return;
            }
        }

        $data = [
            'org_role' => in_array($newRole, ['organization_owner', 'organization_admin', 'organization_member'], true) ? $newRole : $membership['org_role'],
            'status' => in_array($newStatus, ['active', 'inactive'], true) ? $newStatus : $membership['status'],
        ];

        $db->update('organization_members', $data, '`id` = :id', [':id' => $membershipId]);

        Logger::audit(
            action: 'team.member_updated',
            entityType: 'organization_members',
            entityId: $membershipId,
            orgId: $orgId,
            oldValues: $membership,
            newValues: $data
        );

        Response::json(null, 200, 'Team member updated.');
    }
}
