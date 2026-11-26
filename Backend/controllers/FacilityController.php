<?php
declare(strict_types=1);

namespace EcoPulse\Controllers;

use EcoPulse\Core\Logger;
use EcoPulse\Core\Request;
use EcoPulse\Core\Response;
use EcoPulse\Models\Facility;

class FacilityController
{
    /**
     * GET /api/facilities
     */
    public function index(Request $request): void
    {
        $org = $request->getOrganization();
        $orgId = (int)$org['id'];

        $domainProfileId = $request->getInt('domain_profile_id') ?: null;
        $search = $request->getString('search') ?: null;
        $status = $request->getString('status') ?: null;
        $limit = max(1, min(100, $request->getInt('limit', 50)));
        $offset = max(0, $request->getInt('offset', 0));

        $result = Facility::listByOrganization($orgId, $domainProfileId, $search, $status, $limit, $offset);
        Response::json($result, 200, 'Facilities retrieved successfully.');
    }

    /**
     * GET /api/facilities/:id
     */
    public function show(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $facilityId = (int)($params['id'] ?? 0);

        $facility = Facility::findByIdAndOrg($facilityId, (int)$org['id']);
        if (!$facility) {
            Response::error('Facility not found or access denied.', 404, 'NOT_FOUND');
            return;
        }

        Response::json($facility, 200, 'Facility details retrieved.');
    }

    /**
     * POST /api/facilities
     */
    public function store(Request $request): void
    {
        $org = $request->getOrganization();
        $orgId = (int)$org['id'];

        $name = $request->getString('name');
        $facilityType = $request->getString('facility_type');
        $country = $request->getString('country');
        $city = $request->getString('city');
        $domainProfileId = $request->getInt('domain_profile_id') ?: null;

        if (empty($name) || empty($facilityType) || empty($country) || empty($city)) {
            Response::error('Facility name, type, country, and city are required fields.', 422, 'VALIDATION_ERROR');
            return;
        }

        $facilityCode = $request->getString('facility_code');
        if (empty($facilityCode)) {
            $facilityCode = 'FAC-' . strtoupper(substr(uniqid(), 8));
        }

        $grossArea = $request->getFloat('gross_area_sqm');
        $data = [
            'organization_id' => $orgId,
            'domain_profile_id' => $domainProfileId,
            'name' => $name,
            'facility_code' => $facilityCode,
            'facility_type' => $facilityType,
            'country' => $country,
            'state_region' => $request->getString('state_region'),
            'city' => $city,
            'postal_code' => $request->getString('postal_code'),
            'timezone' => $request->getString('timezone', 'UTC'),
            'address' => $request->getString('address'),
            'gross_area_sqm' => $grossArea > 0 ? $grossArea : null,
            'operational_control' => $request->getInt('operational_control', 1),
            'metadata' => $request->getArray('metadata') ? json_encode($request->getArray('metadata')) : null,
            'status' => 'active',
        ];

        $facilityId = Facility::create($data);

        Logger::audit(
            action: 'facility.created',
            entityType: 'facilities',
            entityId: $facilityId,
            orgId: $orgId,
            newValues: ['name' => $name, 'facility_code' => $facilityCode]
        );

        $created = Facility::findByIdAndOrg($facilityId, $orgId);
        Response::json($created, 201, 'Facility added successfully.');
    }

    /**
     * PUT /api/facilities/:id
     */
    public function update(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $orgId = (int)$org['id'];
        $facilityId = (int)($params['id'] ?? 0);

        $existing = Facility::findByIdAndOrg($facilityId, $orgId);
        if (!$existing) {
            Response::error('Facility not found or access denied.', 404, 'NOT_FOUND');
            return;
        }

        $name = $request->getString('name', $existing['name']);
        $facilityType = $request->getString('facility_type', $existing['facility_type']);
        $country = $request->getString('country', $existing['country']);
        $city = $request->getString('city', $existing['city']);
        $grossArea = $request->getFloat('gross_area_sqm');

        $data = [
            'name' => $name,
            'facility_code' => $request->getString('facility_code', $existing['facility_code']),
            'domain_profile_id' => $request->getInt('domain_profile_id') ?: $existing['domain_profile_id'],
            'facility_type' => $facilityType,
            'country' => $country,
            'state_region' => $request->getString('state_region', $existing['state_region']),
            'city' => $city,
            'postal_code' => $request->getString('postal_code', $existing['postal_code']),
            'timezone' => $request->getString('timezone', $existing['timezone']),
            'address' => $request->getString('address', $existing['address']),
            'gross_area_sqm' => $grossArea > 0 ? $grossArea : $existing['gross_area_sqm'],
            'operational_control' => $request->getInt('operational_control', (int)$existing['operational_control']),
            'status' => $request->getString('status', $existing['status']),
        ];

        Facility::update($facilityId, $orgId, $data);

        Logger::audit(
            action: 'facility.updated',
            entityType: 'facilities',
            entityId: $facilityId,
            orgId: $orgId,
            oldValues: $existing,
            newValues: $data
        );

        $updated = Facility::findByIdAndOrg($facilityId, $orgId);
        Response::json($updated, 200, 'Facility updated successfully.');
    }

    /**
     * DELETE /api/facilities/:id
     * Deactivates / decommissions a facility (soft delete)
     */
    public function deactivate(Request $request, array $params): void
    {
        $org = $request->getOrganization();
        $orgId = (int)$org['id'];
        $facilityId = (int)($params['id'] ?? 0);

        $existing = Facility::findByIdAndOrg($facilityId, $orgId);
        if (!$existing) {
            Response::error('Facility not found or access denied.', 404, 'NOT_FOUND');
            return;
        }

        Facility::update($facilityId, $orgId, ['status' => 'decommissioned']);

        Logger::audit(
            action: 'facility.decommissioned',
            entityType: 'facilities',
            entityId: $facilityId,
            orgId: $orgId
        );

        Response::json(null, 200, 'Facility deactivated successfully.');
    }
}
