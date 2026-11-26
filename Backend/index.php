<?php
declare(strict_types=1);

/**
 * EcoPulse MVP V3 - Master REST API Front Controller
 */

namespace EcoPulse;

use EcoPulse\Controllers\ActivityDataController;
use EcoPulse\Controllers\AnalyticsController;
use EcoPulse\Controllers\AuthController;
use EcoPulse\Controllers\CarbonCalculationController;
use EcoPulse\Controllers\DomainController;
use EcoPulse\Controllers\EmissionFactorController;
use EcoPulse\Controllers\FacilityController;
use EcoPulse\Controllers\MarketplaceController;
use EcoPulse\Controllers\OrganizationController;
use EcoPulse\Controllers\ProjectController;
use EcoPulse\Controllers\ReductionInitiativeController;
use EcoPulse\Controllers\ReductionTargetController;
use EcoPulse\Controllers\ReportingPeriodController;
use EcoPulse\Controllers\TeamController;
use EcoPulse\Core\Database;
use EcoPulse\Core\Logger;
use EcoPulse\Core\Request;
use EcoPulse\Core\Response;
use EcoPulse\Core\Router;
use EcoPulse\Core\Session;
use EcoPulse\Middleware\AuthMiddleware;
use EcoPulse\Middleware\CorsMiddleware;
use EcoPulse\Middleware\CsrfMiddleware;
use EcoPulse\Middleware\RoleMiddleware;
use EcoPulse\Middleware\TenantMiddleware;
use Throwable;

// Static Frontend File Serving for Development Server
$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (!str_starts_with($reqPath, '/api')) {
    $frontendDir = realpath(__DIR__ . '/../Frontend');
    $targetPath = null;
    if ($reqPath === '/portal/login.html') {
        header("Location: /login.html", true, 302);
        exit;
    }
    if ($reqPath === '/portal' || $reqPath === '/portal/') {
        header("Location: /portal/client.html", true, 302);
        exit;
    }
    if ($reqPath === '/' || $reqPath === '' || $reqPath === '/index.html') {
        $targetPath = $frontendDir . DIRECTORY_SEPARATOR . 'index.html';
    } elseif (str_starts_with($reqPath, '/Frontend/')) {
        $targetPath = realpath($frontendDir . DIRECTORY_SEPARATOR . substr($reqPath, 10));
    } else {
        $targetPath = realpath($frontendDir . DIRECTORY_SEPARATOR . ltrim($reqPath, '/\\'));
    }

    if ($targetPath && file_exists($targetPath) && is_file($targetPath) && str_starts_with($targetPath, $frontendDir)) {
        $ext = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
        $mimes = [
            'html' => 'text/html; charset=UTF-8',
            'css'  => 'text/css; charset=UTF-8',
            'js'   => 'application/javascript; charset=UTF-8',
            'json' => 'application/json',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'svg'  => 'image/svg+xml',
            'ico'  => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2'=> 'font/woff2',
            'ttf'  => 'font/ttf',
        ];
        $contentType = $mimes[$ext] ?? 'application/octet-stream';
        header("Content-Type: {$contentType}");
        readfile($targetPath);
        exit;
    }
}

// 1. Error Reporting Configuration
error_reporting(E_ALL);
ini_set('display_errors', '0');
date_default_timezone_set('UTC');

// 2. Exception Handling
set_exception_handler(function (Throwable $e) {
    Logger::error('Uncaught Exception: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);

    Response::error(
        'An unexpected server error occurred.',
        500,
        'INTERNAL_SERVER_ERROR',
        ['message' => $e->getMessage()]
    );
});

// 3. Autoloader
spl_autoload_register(function (string $class) {
    $prefix = 'EcoPulse\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $parts = explode('\\', $relativeClass);

    $folder = strtolower(array_shift($parts));
    $file = __DIR__ . '/' . $folder . '/' . implode('/', $parts) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// 4. Constants & Session
require_once __DIR__ . '/config/constants.php';
Session::start();

// 5. Initialize Router and Request
$router = new Router();
$request = new Request();

// Global Middleware
$router->use(new CorsMiddleware());
$router->use(new CsrfMiddleware());

// Helper Middleware Instances
$auth = new AuthMiddleware();
$tenant = new TenantMiddleware();
$adminRole = new RoleMiddleware(['organization_owner', 'organization_admin']);
$superAdminRole = new RoleMiddleware(['super_admin']);

// 6. Routes

// System Health Check
$router->get('/api/health', function (Request $req) {
    try {
        $db = Database::getInstance();
        $dbVersion = $db->fetchOne('SELECT VERSION() as v')['v'];
        Response::json([
            'status' => 'healthy',
            'product' => 'EcoPulse MVP V3',
            'database' => 'connected',
            'db_version' => $dbVersion,
            'timestamp' => gmdate('Y-m-d H:i:s'),
        ], 200, 'EcoPulse Backend is operational.');
    } catch (Throwable $e) {
        Response::error('Health check failed: ' . $e->getMessage(), 500, 'DB_UNAVAILABLE');
    }
});

// Auth Endpoints
$router->post('/api/auth/register', [AuthController::class, 'register']);
$router->post('/api/auth/login', [AuthController::class, 'login']);
$router->post('/api/auth/logout', [AuthController::class, 'logout']);
$router->get('/api/auth/me', [AuthController::class, 'me']);
$router->post('/api/auth/switch-org', [AuthController::class, 'switchOrganization'], [$auth]);

// Organization Dashboard & Management
$router->get('/api/organization/dashboard', [OrganizationController::class, 'dashboard'], [$auth, $tenant]);
$router->put('/api/organization/current', [OrganizationController::class, 'update'], [$auth, $tenant, $adminRole]);

// Domains & Workspaces
$router->get('/api/domains', [DomainController::class, 'index'], [$auth]);
$router->get('/api/domains/profiles', [DomainController::class, 'profiles'], [$auth, $tenant]);
$router->post('/api/domains/profiles', [DomainController::class, 'enable'], [$auth, $tenant, $adminRole]);
$router->post('/api/domains/profiles/:id/disable', [DomainController::class, 'disable'], [$auth, $tenant, $adminRole]);
$router->get('/api/domains/:code/workspace', [DomainController::class, 'workspace'], [$auth, $tenant]);

// Facility Management
$router->get('/api/facilities', [FacilityController::class, 'index'], [$auth, $tenant]);
$router->get('/api/facilities/:id', [FacilityController::class, 'show'], [$auth, $tenant]);
$router->post('/api/facilities', [FacilityController::class, 'store'], [$auth, $tenant, $adminRole]);
$router->put('/api/facilities/:id', [FacilityController::class, 'update'], [$auth, $tenant, $adminRole]);
$router->delete('/api/facilities/:id', [FacilityController::class, 'deactivate'], [$auth, $tenant, $adminRole]);

// Reporting Periods Management
$router->get('/api/reporting-periods', [ReportingPeriodController::class, 'index'], [$auth, $tenant]);
$router->get('/api/reporting-periods/:id', [ReportingPeriodController::class, 'show'], [$auth, $tenant]);
$router->post('/api/reporting-periods', [ReportingPeriodController::class, 'store'], [$auth, $tenant, $adminRole]);
$router->put('/api/reporting-periods/:id', [ReportingPeriodController::class, 'update'], [$auth, $tenant, $adminRole]);
$router->post('/api/reporting-periods/:id/close', [ReportingPeriodController::class, 'close'], [$auth, $tenant, $adminRole]);

// Team Management
$router->get('/api/team', [TeamController::class, 'index'], [$auth, $tenant]);
$router->post('/api/team', [TeamController::class, 'store'], [$auth, $tenant, $adminRole]);
$router->put('/api/team/:id', [TeamController::class, 'update'], [$auth, $tenant, $adminRole]);

// Emission Factors Catalog & Activity Categories
$router->get('/api/emission-factors', [EmissionFactorController::class, 'index'], [$auth]);
$router->get('/api/emission-factors/:id', [EmissionFactorController::class, 'show'], [$auth]);
$router->post('/api/emission-factors', [EmissionFactorController::class, 'store'], [$auth]);
$router->post('/api/emission-factors/:id/validate', [EmissionFactorController::class, 'validateFactor'], [$auth]);
$router->get('/api/activity-categories', [EmissionFactorController::class, 'categories'], [$auth]);

// Activity Data Records & Intake Workflow
$router->get('/api/activity-records/export', [ActivityDataController::class, 'exportCsv'], [$auth, $tenant]);
$router->get('/api/activity-records', [ActivityDataController::class, 'index'], [$auth, $tenant]);
$router->get('/api/activity-records/:id', [ActivityDataController::class, 'show'], [$auth, $tenant]);
$router->post('/api/activity-records', [ActivityDataController::class, 'store'], [$auth, $tenant]);
$router->put('/api/activity-records/:id', [ActivityDataController::class, 'update'], [$auth, $tenant]);
$router->post('/api/activity-records/:id/submit', [ActivityDataController::class, 'submit'], [$auth, $tenant]);
$router->post('/api/activity-records/:id/validate', [ActivityDataController::class, 'validate'], [$auth, $tenant, $adminRole]);
$router->post('/api/activity-records/:id/reject', [ActivityDataController::class, 'reject'], [$auth, $tenant, $adminRole]);
$router->delete('/api/activity-records/:id', [ActivityDataController::class, 'destroy'], [$auth, $tenant]);

// Carbon Calculations & Footprint Engine
$router->post('/api/calculations/record/:id', [CarbonCalculationController::class, 'calculateRecord'], [$auth, $tenant]);
$router->post('/api/calculations/recalculate', [CarbonCalculationController::class, 'batchRecalculate'], [$auth, $tenant, $adminRole]);
$router->get('/api/calculations/history/:record_id', [CarbonCalculationController::class, 'history'], [$auth, $tenant]);
$router->get('/api/calculations/summary', [CarbonCalculationController::class, 'summary'], [$auth, $tenant]);

// Carbon Intelligence & Analytics
$router->get('/api/analytics/export', [AnalyticsController::class, 'exportCsv'], [$auth, $tenant]);
$router->get('/api/analytics/overview', [AnalyticsController::class, 'overview'], [$auth, $tenant]);
$router->get('/api/analytics/emissions-by-scope', [AnalyticsController::class, 'emissionsByScope'], [$auth, $tenant]);
$router->get('/api/analytics/emissions-by-domain', [AnalyticsController::class, 'emissionsByDomain'], [$auth, $tenant]);
$router->get('/api/analytics/emissions-by-facility', [AnalyticsController::class, 'emissionsByFacility'], [$auth, $tenant]);
$router->get('/api/analytics/emissions-by-category', [AnalyticsController::class, 'emissionsByCategory'], [$auth, $tenant]);
$router->get('/api/analytics/emissions-trends', [AnalyticsController::class, 'emissionsTrends'], [$auth, $tenant]);
$router->get('/api/analytics/emission-intensity', [AnalyticsController::class, 'emissionIntensity'], [$auth, $tenant]);
$router->get('/api/analytics/reduction-opportunities', [AnalyticsController::class, 'reductionOpportunities'], [$auth, $tenant]);

// Reduction Initiatives & Decarbonization Roadmap
$router->get('/api/reduction-initiatives', [ReductionInitiativeController::class, 'index'], [$auth, $tenant]);
$router->get('/api/reduction-initiatives/:id', [ReductionInitiativeController::class, 'show'], [$auth, $tenant]);
$router->post('/api/reduction-initiatives', [ReductionInitiativeController::class, 'store'], [$auth, $tenant, $adminRole]);
$router->put('/api/reduction-initiatives/:id', [ReductionInitiativeController::class, 'update'], [$auth, $tenant, $adminRole]);
$router->post('/api/reduction-initiatives/:id/status', [ReductionInitiativeController::class, 'updateStatus'], [$auth, $tenant, $adminRole]);
$router->post('/api/reduction-initiatives/:id/progress', [ReductionInitiativeController::class, 'addProgress'], [$auth, $tenant]);
$router->get('/api/reduction-initiatives/:id/history', [ReductionInitiativeController::class, 'history'], [$auth, $tenant]);

// Reduction Targets
$router->get('/api/reduction-targets', [ReductionTargetController::class, 'index'], [$auth, $tenant]);
$router->post('/api/reduction-targets', [ReductionTargetController::class, 'store'], [$auth, $tenant, $adminRole]);
$router->put('/api/reduction-targets/:id', [ReductionTargetController::class, 'update'], [$auth, $tenant, $adminRole]);
$router->delete('/api/reduction-targets/:id', [ReductionTargetController::class, 'destroy'], [$auth, $tenant, $adminRole]);

// Phase 5: Environmental Projects, Evidence & Readiness Assessment
// Projects CRUD & Governance
$router->get('/api/projects', [ProjectController::class, 'index'], [$auth, $tenant]);
$router->get('/api/projects/:id', [ProjectController::class, 'show'], [$auth, $tenant]);
$router->post('/api/projects', [ProjectController::class, 'store'], [$auth, $tenant]);
$router->put('/api/projects/:id', [ProjectController::class, 'update'], [$auth, $tenant, $adminRole]);
$router->post('/api/projects/:id/classification', [ProjectController::class, 'updateClassification'], [$auth, $tenant, $adminRole]);
$router->get('/api/projects/:id/classification-history', [ProjectController::class, 'classificationHistory'], [$auth, $tenant]);
$router->post('/api/projects/:id/readiness-stage', [ProjectController::class, 'updateReadinessStage'], [$auth, $tenant, $adminRole]);
$router->get('/api/projects/:id/readiness-stage-history', [ProjectController::class, 'getStageHistory'], [$auth, $tenant]);
$router->get('/api/projects/:id/stage-history', [ProjectController::class, 'getStageHistory'], [$auth, $tenant]);
$router->post('/api/projects/:id/status', [ProjectController::class, 'updateStatus'], [$auth, $tenant, $adminRole]);
$router->delete('/api/projects/:id', [ProjectController::class, 'destroy'], [$auth, $tenant, $adminRole]);

// Evidence Document Management & Review
$router->get('/api/projects/:id/documents', [ProjectController::class, 'listDocuments'], [$auth, $tenant]);
$router->post('/api/projects/:id/documents', [ProjectController::class, 'uploadDocument'], [$auth, $tenant]);
$router->get('/api/projects/:id/documents/:docId/download', [ProjectController::class, 'downloadDocument'], [$auth, $tenant]);
$router->post('/api/projects/:id/documents/:docId/replace', [ProjectController::class, 'replaceDocument'], [$auth, $tenant]);
$router->post('/api/projects/:id/documents/:docId/review', [ProjectController::class, 'reviewDocument'], [$auth, $tenant, $adminRole]);
$router->get('/api/projects/:id/documents/:docId/history', [ProjectController::class, 'documentHistory'], [$auth, $tenant]);
$router->delete('/api/projects/:id/documents/:docId', [ProjectController::class, 'deleteDocument'], [$auth, $tenant, $adminRole]);

// Algorithmic Readiness Assessment
$router->post('/api/projects/:id/readiness-assessments', [ProjectController::class, 'assessReadiness'], [$auth, $tenant]);
$router->get('/api/projects/:id/readiness-assessments', [ProjectController::class, 'getReadiness'], [$auth, $tenant]);
$router->get('/api/projects/:id/readiness-assessments/history', [ProjectController::class, 'readinessHistory'], [$auth, $tenant]);
$router->get('/api/projects/:id/readiness-assessments/:assessmentId', [ProjectController::class, 'showAssessment'], [$auth, $tenant]);
$router->get('/api/projects/:id/readiness-assessments/:assessmentId/gaps', [ProjectController::class, 'getReadinessGaps'], [$auth, $tenant]);

// =========================================================================
// Phase 6: Carbon Marketing Marketplace
// =========================================================================

// Public Marketplace Browsing & Inquiries
$router->get('/api/marketplace/listings', [MarketplaceController::class, 'browse']);
$router->get('/api/marketplace/listings/:id', [MarketplaceController::class, 'show']);
$router->post('/api/marketplace/inquiries', [MarketplaceController::class, 'submitInquiry']);

// Organization Client Marketplace Management
$router->get('/api/organization/marketplace/eligible-projects', [MarketplaceController::class, 'eligibleProjects'], [$auth, $tenant]);
$router->get('/api/organization/marketplace/listings', [MarketplaceController::class, 'myListings'], [$auth, $tenant]);
$router->get('/api/organization/marketplace/listings/:id', [MarketplaceController::class, 'showMyListing'], [$auth, $tenant]);
$router->post('/api/organization/marketplace/listings', [MarketplaceController::class, 'storeListing'], [$auth, $tenant, $adminRole]);
$router->post('/api/organization/marketplace/listings/:id/submit', [MarketplaceController::class, 'submitListing'], [$auth, $tenant, $adminRole]);
$router->post('/api/organization/marketplace/listings/:id/archive', [MarketplaceController::class, 'archiveListing'], [$auth, $tenant, $adminRole]);
$router->get('/api/organization/marketplace/inquiries', [MarketplaceController::class, 'myInquiries'], [$auth, $tenant]);
$router->get('/api/organization/marketplace/inquiries/:id', [MarketplaceController::class, 'showInquiry'], [$auth, $tenant]);
$router->post('/api/organization/marketplace/inquiries/:id/messages', [MarketplaceController::class, 'sendInquiryMessage'], [$auth, $tenant]);
$router->patch('/api/organization/marketplace/inquiries/:id/status', [MarketplaceController::class, 'updateInquiryStatus'], [$auth, $tenant]);

// Super Admin Marketplace Moderation
$router->get('/api/admin/marketplace/listings/pending', [MarketplaceController::class, 'pendingListings'], [$auth, $superAdminRole]);
$router->post('/api/admin/marketplace/listings/:id/approve', [MarketplaceController::class, 'approveListing'], [$auth, $superAdminRole]);
$router->post('/api/admin/marketplace/listings/:id/reject', [MarketplaceController::class, 'rejectListing'], [$auth, $superAdminRole]);
$router->post('/api/admin/marketplace/listings/:id/suspend', [MarketplaceController::class, 'suspendListing'], [$auth, $superAdminRole]);
$router->post('/api/admin/marketplace/listings/:id/restore', [MarketplaceController::class, 'restoreListing'], [$auth, $superAdminRole]);
$router->get('/api/admin/marketplace/moderation-history', [MarketplaceController::class, 'moderationHistory'], [$auth, $superAdminRole]);

// Dispatch
$router->dispatch($request);


