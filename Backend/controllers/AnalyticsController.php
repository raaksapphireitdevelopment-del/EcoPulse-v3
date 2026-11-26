<?php
declare(strict_types=1);

namespace EcoPulse\Controllers;

use EcoPulse\Core\Request;
use EcoPulse\Core\Response;
use EcoPulse\Services\AnalyticsEngine;
use Throwable;

class AnalyticsController
{
    private function extractFilters(Request $request): array
    {
        return [
            'facility_id'         => $request->getInt('facility_id') ?: null,
            'reporting_period_id' => $request->getInt('reporting_period_id') ?: null,
            'domain_profile_id'   => $request->getInt('domain_profile_id') ?: null,
            'scope'               => $request->getString('scope') ?: null,
            'calculation_status'  => $request->getString('calculation_status') ?: null,
            'date_from'           => $request->getString('date_from') ?: null,
            'date_to'             => $request->getString('date_to') ?: null,
        ];
    }

    /**
     * GET /api/analytics/overview
     */
    public function overview(Request $request): void
    {
        $org = $request->getOrganization();
        $filters = $this->extractFilters($request);

        try {
            $data = AnalyticsEngine::getOverview((int)$org['id'], $filters);
            Response::json($data, 200, 'Emissions overview retrieved successfully.');
        } catch (Throwable $e) {
            Response::error('Failed to retrieve emissions overview: ' . $e->getMessage(), 500, 'ANALYTICS_ERROR');
        }
    }

    /**
     * GET /api/analytics/emissions-by-scope
     */
    public function emissionsByScope(Request $request): void
    {
        $org = $request->getOrganization();
        $filters = $this->extractFilters($request);

        try {
            $data = AnalyticsEngine::getEmissionsByScope((int)$org['id'], $filters);
            Response::json($data, 200, 'Emissions by scope retrieved successfully.');
        } catch (Throwable $e) {
            Response::error('Failed to retrieve scope emissions: ' . $e->getMessage(), 500, 'ANALYTICS_ERROR');
        }
    }

    /**
     * GET /api/analytics/emissions-by-domain
     */
    public function emissionsByDomain(Request $request): void
    {
        $org = $request->getOrganization();
        $filters = $this->extractFilters($request);

        try {
            $data = AnalyticsEngine::getEmissionsByDomain((int)$org['id'], $filters);
            Response::json($data, 200, 'Emissions by domain retrieved successfully.');
        } catch (Throwable $e) {
            Response::error('Failed to retrieve domain emissions: ' . $e->getMessage(), 500, 'ANALYTICS_ERROR');
        }
    }

    /**
     * GET /api/analytics/emissions-by-facility
     */
    public function emissionsByFacility(Request $request): void
    {
        $org = $request->getOrganization();
        $filters = $this->extractFilters($request);

        try {
            $data = AnalyticsEngine::getEmissionsByFacility((int)$org['id'], $filters);
            Response::json($data, 200, 'Emissions by facility retrieved successfully.');
        } catch (Throwable $e) {
            Response::error('Failed to retrieve facility emissions: ' . $e->getMessage(), 500, 'ANALYTICS_ERROR');
        }
    }

    /**
     * GET /api/analytics/emissions-by-category
     */
    public function emissionsByCategory(Request $request): void
    {
        $org = $request->getOrganization();
        $filters = $this->extractFilters($request);

        try {
            $data = AnalyticsEngine::getEmissionsByCategory((int)$org['id'], $filters);
            Response::json($data, 200, 'Emissions by category retrieved successfully.');
        } catch (Throwable $e) {
            Response::error('Failed to retrieve category emissions: ' . $e->getMessage(), 500, 'ANALYTICS_ERROR');
        }
    }

    /**
     * GET /api/analytics/emissions-trends
     */
    public function emissionsTrends(Request $request): void
    {
        $org = $request->getOrganization();
        $filters = $this->extractFilters($request);

        try {
            $data = AnalyticsEngine::getEmissionTrends((int)$org['id'], $filters);
            Response::json($data, 200, 'Emission trends retrieved successfully.');
        } catch (Throwable $e) {
            Response::error('Failed to retrieve emission trends: ' . $e->getMessage(), 500, 'ANALYTICS_ERROR');
        }
    }

    /**
     * GET /api/analytics/emission-intensity
     */
    public function emissionIntensity(Request $request): void
    {
        $org = $request->getOrganization();
        $filters = $this->extractFilters($request);

        try {
            $data = AnalyticsEngine::getEmissionIntensity((int)$org['id'], $filters);
            Response::json($data, 200, 'Emission intensity indicators retrieved successfully.');
        } catch (Throwable $e) {
            Response::error('Failed to retrieve intensity indicators: ' . $e->getMessage(), 500, 'ANALYTICS_ERROR');
        }
    }

    /**
     * GET /api/analytics/reduction-opportunities
     */
    public function reductionOpportunities(Request $request): void
    {
        $org = $request->getOrganization();
        $filters = $this->extractFilters($request);

        try {
            $data = AnalyticsEngine::identifyOpportunities((int)$org['id'], $filters);
            Response::json($data, 200, 'Reduction opportunities identified successfully.');
        } catch (Throwable $e) {
            Response::error('Failed to identify opportunities: ' . $e->getMessage(), 500, 'ANALYTICS_ERROR');
        }
    }

    /**
     * GET /api/analytics/export
     */
    public function exportCsv(Request $request): void
    {
        $org = $request->getOrganization();
        $filters = $this->extractFilters($request);

        try {
            $overview = AnalyticsEngine::getOverview((int)$org['id'], $filters);
            $scopes = AnalyticsEngine::getEmissionsByScope((int)$org['id'], $filters);
            $domains = AnalyticsEngine::getEmissionsByDomain((int)$org['id'], $filters);
            $facilities = AnalyticsEngine::getEmissionsByFacility((int)$org['id'], $filters);
            $trends = AnalyticsEngine::getEmissionTrends((int)$org['id'], $filters);

            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="ecopulse_emissions_report_' . date('Ymd_His') . '.csv"');
            header('Pragma: no-cache');
            header('Expires: 0');

            $output = fopen('php://output', 'w');
            fputs($output, "\xEF\xBB\xBF");

            fputcsv($output, ['EcoPulse MVP V3 - GHG Emissions Intelligence Report']);
            fputcsv($output, ['Organization', $org['name'] ?? 'Organization #' . $org['id']]);
            fputcsv($output, ['Generated At', gmdate('Y-m-d H:i:s') . ' UTC']);
            fputcsv($output, ['Standards Compliance', 'GHG Protocol Corporate Standard / ISO 14064-1']);
            fputcsv($output, []);

            fputcsv($output, ['--- OPERATIONAL FOOTPRINT SUMMARY (tCO2e) ---']);
            fputcsv($output, ['Total Operational Emissions (Scopes 1+2+3)', $overview['kpis']['total_operational_tco2e'] ?? 0]);
            fputcsv($output, ['Scope 1 (Direct Fuel & Process)', $overview['kpis']['scope_1_tco2e'] ?? 0]);
            fputcsv($output, ['Scope 2 (Location-Based Electricity)', $overview['kpis']['scope_2_tco2e'] ?? 0]);
            fputcsv($output, ['Scope 3 (Value Chain / Scope 3)', $overview['kpis']['scope_3_tco2e'] ?? 0]);
            fputcsv($output, ['Avoided Emissions (Decoupled / Segregated)', $overview['kpis']['avoided_emissions_tco2e'] ?? 0]);
            fputcsv($output, []);

            fputcsv($output, ['--- EMISSIONS BY SCOPE ---']);
            fputcsv($output, ['Scope Name', 'Emissions (tCO2e)', 'Percentage of Footprint', 'Calculation Records Count']);
            foreach ($scopes['scopes'] ?? [] as $scopeKey => $scopeData) {
                fputcsv($output, [
                    ucwords(str_replace('_', ' ', (string)$scopeKey)),
                    $scopeData['tonnes'] ?? 0,
                    ($scopeData['percentage'] ?? 0) . '%',
                    $scopeData['record_count'] ?? 0
                ]);
            }
            fputcsv($output, []);

            fputcsv($output, ['--- EMISSIONS BY BUSINESS DOMAIN ---']);
            fputcsv($output, ['Domain Code', 'Domain Name', 'Scope 1 (tCO2e)', 'Scope 2 (tCO2e)', 'Scope 3 (tCO2e)', 'Total Operational (tCO2e)', 'Avoided (tCO2e)']);
            foreach ($domains['domains'] ?? [] as $d) {
                fputcsv($output, [
                    $d['domain_code'] ?? '',
                    $d['domain_name'] ?? '',
                    $d['scope_1_tco2e'] ?? 0,
                    $d['scope_2_tco2e'] ?? 0,
                    $d['scope_3_tco2e'] ?? 0,
                    $d['operational_total_tco2e'] ?? 0,
                    $d['avoided_emissions_tco2e'] ?? 0
                ]);
            }
            fputcsv($output, []);

            fputcsv($output, ['--- EMISSIONS BY OPERATIONAL FACILITY ---']);
            fputcsv($output, ['Facility Name', 'City', 'Country', 'Scope 1 (tCO2e)', 'Scope 2 (tCO2e)', 'Scope 3 (tCO2e)', 'Total Operational (tCO2e)', 'Avoided (tCO2e)']);
            foreach ($facilities['facilities'] ?? [] as $f) {
                fputcsv($output, [
                    $f['facility_name'] ?? '',
                    $f['city'] ?? '',
                    $f['country'] ?? '',
                    $f['scope_1_tco2e'] ?? 0,
                    $f['scope_2_tco2e'] ?? 0,
                    $f['scope_3_tco2e'] ?? 0,
                    $f['operational_total_tco2e'] ?? 0,
                    $f['avoided_emissions_tco2e'] ?? 0
                ]);
            }
            fputcsv($output, []);

            fputcsv($output, ['--- MONTHLY EMISSIONS TRENDS ---']);
            fputcsv($output, ['Month', 'Scope 1 (tCO2e)', 'Scope 2 (tCO2e)', 'Scope 3 (tCO2e)', 'Operational Footprint (tCO2e)']);
            foreach ($trends['monthly_trends'] ?? [] as $m) {
                fputcsv($output, [
                    $m['month'] ?? '',
                    $m['scope_1_tco2e'] ?? 0,
                    $m['scope_2_tco2e'] ?? 0,
                    $m['scope_3_tco2e'] ?? 0,
                    $m['operational_total_tco2e'] ?? 0
                ]);
            }

            fclose($output);
            exit;
        } catch (Throwable $e) {
            Response::error('Failed to export emissions report: ' . $e->getMessage(), 500, 'EXPORT_ERROR');
        }
    }
}
