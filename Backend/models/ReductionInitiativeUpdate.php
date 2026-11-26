<?php
declare(strict_types=1);

namespace EcoPulse\Models;

use EcoPulse\Core\Database;

class ReductionInitiativeUpdate
{
    /**
     * Add a progress update and synchronize the parent initiative's progress % and status.
     */
    public static function addUpdate(array $data): int
    {
        $db = Database::getInstance();
        $initiativeId = (int)$data['initiative_id'];
        $orgId = (int)$data['organization_id'];
        $progress = (float)$data['progress_percentage'];
        $status = $data['status'];
        $measuredTco2e = isset($data['measured_reduction_tco2e']) ? (float)$data['measured_reduction_tco2e'] : null;

        // 1. Insert immutable history entry
        $updateId = (int)$db->insert('reduction_initiative_updates', [
            'initiative_id'            => $initiativeId,
            'organization_id'          => $orgId,
            'update_date'              => $data['update_date'] ?? date('Y-m-d'),
            'description'              => trim($data['description']),
            'progress_percentage'      => $progress,
            'status'                   => $status,
            'measured_reduction_tco2e' => $measuredTco2e,
            'evidence_notes'           => !empty($data['evidence_notes']) ? trim($data['evidence_notes']) : null,
            'supporting_document_url'  => !empty($data['supporting_document_url']) ? trim($data['supporting_document_url']) : null,
            'updated_by'               => (int)$data['updated_by'],
        ]);

        // 2. Synchronize parent initiative current progress and status
        $parentFields = [
            'progress_percentage' => $progress,
            'status' => $status,
        ];

        if ($measuredTco2e !== null && $measuredTco2e > 0) {
            $parentFields['measured_reduction_tco2e'] = $measuredTco2e;
            $parentFields['reduction_verification_status'] = $data['reduction_verification_status'] ?? 'measured';
        }

        $db->update(
            'reduction_initiatives',
            $parentFields,
            '`id` = :id AND `organization_id` = :org_id',
            [':id' => $initiativeId, ':org_id' => $orgId]
        );

        return $updateId;
    }

    /**
     * Retrieve complete immutable progress history for an initiative.
     */
    public static function getHistoryForInitiative(int $initiativeId, int $orgId): array
    {
        $db = Database::getInstance();
        $sql = "SELECT u.*, usr.name as updater_name, usr.email as updater_email
                FROM `reduction_initiative_updates` u
                JOIN `users` usr ON usr.id = u.updated_by
                WHERE u.`initiative_id` = :init_id AND u.`organization_id` = :org_id
                ORDER BY u.`update_date` DESC, u.`id` DESC";

        return $db->fetchAll($sql, [':init_id' => $initiativeId, ':org_id' => $orgId]);
    }
}
