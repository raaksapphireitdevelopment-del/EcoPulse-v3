# EcoPulse MVP V3 — Final Test Results Matrix

**Verification Date:** November 20, 2026  
**Environment:** PHP 8.2.12 / MySQL 8.0.33 / Headless Chrome (Puppeteer)  
**Host Target:** `http://127.0.0.1:8000`  
**Overall Result:** **193 / 193 Automated Tests Passed (100%)** | **26 / 26 Browser Journey Steps Passed (100%)**  
**Defect Status:** **All Identified Defects Resolved & Verified**  

---

## 1. Executive Test Suite Summary

| Test Suite / Phase | Scope | Total Tests | Passed | Failed | Pass Rate | Status |
| :--- | :--- | :---: | :---: | :---: | :---: | :---: |
| **Phase 1: Backend Foundation** | Database, Auth, Multi-Tenancy, CSRF | 6 | 6 | 0 | 100% | **PASSED** |
| **Phase 2: Org & Domain Ops** | Workspaces, Facilities, Periods, RBAC | 17 | 17 | 0 | 100% | **PASSED** |
| **Phase 3: Calculation Engine** | Emission Factors, Formulas, Scopes, Snapshots | 29 | 29 | 0 | 100% | **PASSED** |
| **Phase 4: Analytics & Reductions** | Aggregation, SBT Targets, Decarbonization | 31 | 31 | 0 | 100% | **PASSED** |
| **Phase 5: Projects & Readiness** | Evidence Storage, SHA-256, Readiness Score | 40 | 40 | 0 | 100% | **PASSED** |
| **Phase 6: Carbon Marketplace** | Eligibility, Moderation, Inquiries, Public | 21 | 21 | 0 | 100% | **PASSED** |
| **Final Security & Integrity Audit** | Anti-Spoofing, Rate Limiting, IDOR, Leaks | 19 | 19 | 0 | 100% | **PASSED** |
| **E2E User Journeys Integration** | Complete 3-Journey Backend Integration | 21 | 21 | 0 | 100% | **PASSED** |
| **Browser Security & Negative Suite** | Unauth Redirects, Cross-Tenant Guard, CSRF | 9 | 9 | 0 | 100% | **PASSED** |
| **Clean-Browser Journey Execution** | Real Chrome Client, Super Admin & Buyer UI | 26 | 26 | 0 | 100% | **PASSED** |
| **TOTALS** | **Comprehensive Full-Spectrum Test Suite** | **219** | **219** | **0** | **100%** | **PASSED** |

---

## 2. Phase 1: Foundation, Authentication & Database (6 Tests)

**File:** `test_phase1.php`

| # | Test Case Description | Target Endpoint / Module | HTTP / Output | Status |
| :---: | :--- | :--- | :---: | :---: |
| 1 | Database connection verification | `Database::getInstance()` | MySQL 8.0.33 | **PASS** |
| 2 | Super Admin credentials verification | `AuthController::login` | ID: 1, Role: `super_admin` | **PASS** |
| 3 | Org Owner & Membership resolution | Multi-Tenant Workspace Resolution | Org 1, Role: `organization_owner` | **PASS** |
| 4 | Enabled Domains retrieval | `Domain::getProfilesByOrganization` | Healthcare, Manufacturing | **PASS** |
| 5 | Full Registration, Password Hashing & Org Association | `AuthController::register` | BCRYPT Hash verified | **PASS** |
| 6 | CSRF Token Generation & Validation | `CsrfMiddleware` | 64-character hex verified | **PASS** |

---

## 3. Phase 2: Organization Management & Multi-Domain Workspaces (17 Tests)

**File:** `test_phase2_security_and_operations.ps1`

| # | Test Case Description | Target Endpoint / Module | HTTP / Output | Status |
| :---: | :--- | :--- | :---: | :---: |
| 1 | Cross-Tenant Isolation: Org 2 cannot read Org 1 facility | `GET /api/facilities/{id}` | HTTP 404 Not Found | **PASS** |
| 2a | CSRF Protection: State-changing POST without CSRF token | `POST /api/facilities` | HTTP 403 Forbidden | **PASS** |
| 2b | CSRF Protection: State-changing POST with tampered token | `POST /api/facilities` | HTTP 403 Forbidden | **PASS** |
| 3 | Session Lifecycle: Session invalidated after logout | `POST /api/auth/logout` | HTTP 401 Unauthorized | **PASS** |
| 4 | Audit Log Immutability: Destructive endpoints rejected | `DELETE /api/audit-logs` | HTTP 404/405 Blocked | **PASS** |
| 5 | Owner views Organization Dashboard | `GET /api/organization` | Org details verified | **PASS** |
| 6 | Owner views enabled domain profiles | `GET /api/domains` | Active domains returned | **PASS** |
| 7 | Owner opens Healthcare workspace | `GET /api/domains/healthcare` | Scope 1/2/3 categories returned | **PASS** |
| 8 | Prevent access to disabled domain for organization | `GET /api/domains/manufacturing` | HTTP 403 DOMAIN_NOT_ENABLED | **PASS** |
| 9 | Owner activates Manufacturing domain | `POST /api/domains/manufacturing/activate` | HTTP 200 Activated | **PASS** |
| 10 | Manufacturing workspace accessible with categories | `GET /api/domains/manufacturing` | 4 categories loaded | **PASS** |
| 11 | Owner creates operational facility | `POST /api/facilities` | HTTP 201 Created | **PASS** |
| 12 | Owner edits and deactivates facility | `PATCH /api/facilities/{id}` | Decommissioned | **PASS** |
| 13 | Owner creates Annual Reporting Period | `POST /api/reporting-periods` | HTTP 201 Created | **PASS** |
| 14 | Duplicate reporting period overlap protection | `POST /api/reporting-periods` | HTTP 409 Conflict | **PASS** |
| 15 | Team Management: Add member and assign role | `POST /api/team/members` | Member created | **PASS** |
| 16 | Privilege Escalation Guard: Admin cannot grant super_admin | `POST /api/team/members` | HTTP 403 Forbidden | **PASS** |
| 17 | RBAC Enforcement: Standard member cannot create facility | `POST /api/facilities` | HTTP 403 Forbidden | **PASS** |

---

## 4. Phase 3: Carbon Calculation Engine & Factors (29 Tests)

**File:** `test_phase3_calculations.ps1`

| # | Test Case Description | Target Endpoint / Module | HTTP / Output | Status |
| :---: | :--- | :--- | :---: | :---: |
| 1 | Create activity record for valid facility and category | `POST /api/activity-data` | Status: `draft` | **PASS** |
| 2 | Retrieve activity record with complete metadata | `GET /api/activity-data/{id}` | Facility, Category, Period verified | **PASS** |
| 3 | Update draft record before calculation | `PUT /api/activity-data/{id}` | Quantity updated | **PASS** |
| 4 | Submit draft record for carbon calculation | `POST /api/activity-data/{id}/calculate` | Status: `submitted`, Emissions computed | **PASS** |
| 5 | Reject record with incompatible/invalid unit | `POST /api/activity-data` | Unit conversion rejected | **PASS** |
| 6 | Reject record with negative quantity | `POST /api/activity-data` | HTTP 422 Validation Error | **PASS** |
| 7 | Reject record referencing nonexistent facility | `POST /api/activity-data` | HTTP 422 Validation Error | **PASS** |
| 8 | Reject record referencing another org's facility | `POST /api/activity-data` | Cross-tenant injection rejected | **PASS** |
| 9 | Reject record referencing closed reporting period | `POST /api/activity-data` | Closed period modification rejected | **PASS** |
| 10 | Cross-tenant isolation on activity records | `GET /api/activity-data/{id}` | HTTP 404 Not Found | **PASS** |
| 11 | Retrieve applicable emission factor for category and region | `GET /api/emission-factors` | 0.71600000 kgCO2e/kWh | **PASS** |
| 12 | Reject calculation when activity unit does not match factor | `POST /api/calculations` | Volume vs Energy rejected | **PASS** |
| 13 | Reject calculation with expired/unvalidated factor | `POST /api/calculations` | Expired factor rejected | **PASS** |
| 14 | Geography matching respects facility location | `CalculationEngine` | National Grid matched | **PASS** |
| 15 | Prevent standard member from approving emission factors | `POST /api/emission-factors/{id}/approve`| HTTP 403 PERMISSION_DENIED | **PASS** |
| 16 | Verify demo factors are flagged with status demo | `GET /api/emission-factors` | `is_demo=1` confirmed | **PASS** |
| 17 | Calculate emissions for known test activity (10,000 kWh * 0.716) | `CalculationEngine` | 7.1600 tCO2e computed | **PASS** |
| 18 | Formula execution string trace recorded in snapshot | `calculation_snapshots` | Mathematical trace logged | **PASS** |
| 19 | Unit conversion verification (5 MWh -> 5000 kWh -> 3.58 t) | `UnitConverter` | 3.5800 tCO2e | **PASS** |
| 20 | Gas-specific conversion (10 kg N2O * 273 = 2.73 tCO2e) | `CalculationEngine` | 2.7300 tCO2e | **PASS** |
| 21 | Verify pre-converted factor not multiplied by extra GWP | `CalculationEngine` | Exactly Q * FactorValue | **PASS** |
| 22 | Scope 1, 2, 3 classification accuracy | `CalculationEngine` | Scope 1 Diesel, Scope 2 Power | **PASS** |
| 23 | Project-level avoided emissions segregated from operational | `AnalyticsEngine` | Avoided strictly segregated | **PASS** |
| 24 | Recalculate creates version N+1 and marks prior not current | `CalculationEngine` | Total versions: 3, is_current=1 | **PASS** |
| 25 | Missing factor flags record / prevents invalid calculation | `CalculationEngine` | Incomplete factor caught | **PASS** |
| 26 | Cross-tenant access to another org's activity records blocked | `GET /api/activity-data/{id}` | HTTP 404 Not Found | **PASS** |
| 27 | Cross-tenant calculation injection prevented | `POST /api/activity-data/{id}/calculate` | Cross-tenant injection denied | **PASS** |
| 28 | Tampered client values ignored; server computes genuine values | `POST /api/activity-data/{id}/calculate` | Tampered value discarded | **PASS** |
| 29 | Non-admin member blocked from approving emission factors | `POST /api/emission-factors/{id}/approve`| HTTP 403 Forbidden | **PASS** |

---

## 5. Phase 4: Analytics, Targets & Decarbonization (31 Tests)

**File:** `test_phase4_analytics_and_reductions.ps1`

| # | Test Case Description | Target Endpoint / Module | HTTP / Output | Status |
| :---: | :--- | :--- | :---: | :---: |
| 1 | Retrieve organization emissions overview | `GET /api/analytics/overview` | Total Operational: 209.653 t | **PASS** |
| 2 | Verify Scope 1 aggregation matches overview | `AnalyticsEngine` | Scope 1: 83.637 t (39.89%) | **PASS** |
| 3 | Verify Scope 2 aggregation matches overview | `AnalyticsEngine` | Scope 2: 126.016 t (60.11%) | **PASS** |
| 4 | Verify Scope 3 aggregation is reported | `AnalyticsEngine` | Scope 3: 0.000 t | **PASS** |
| 5 | Verify unclassified emissions are separately tracked | `AnalyticsEngine` | Unclassified: 0.000 t | **PASS** |
| 6 | Verify avoided emissions strictly segregated | `AnalyticsEngine` | Avoided: 11.220 t segregated | **PASS** |
| 7 | Verify demo/provisional calculations separated | `AnalyticsEngine` | Demo emissions not mixed | **PASS** |
| 8 | Verify superseded calculation versions excluded | `AnalyticsEngine` | Only `is_current=1` included | **PASS** |
| 9 | Verify facility filtering constrains analytics | `GET /api/analytics/overview?facility_id=`| Facility filter respected | **PASS** |
| 10 | Verify domain-wise breakdown returns enabled domains | `GET /api/analytics/domain-breakdown` | 2 domains returned | **PASS** |
| 11 | Verify reporting period filter constrains analytics | `GET /api/analytics/overview?period_id=` | Period filter respected | **PASS** |
| 12 | Verify monthly emission trends structured by month | `GET /api/analytics/monthly-trends` | Structured monthly trends | **PASS** |
| 13 | Period completeness warnings prevent misleading comparisons | `GET /api/analytics/period-completeness` | Completeness flags returned | **PASS** |
| 14 | Cross-tenant analytics isolation (Org 2 cannot read Org 1) | `GET /api/analytics/overview` | Org 2 sees 0 emissions | **PASS** |
| 15 | Identify reduction opportunities from actual activity data | `GET /api/reduction-initiatives/opportunities`| 9 opportunities detected | **PASS** |
| 16 | Ensure opportunities labeled as recommendations, not achieved | `ReductionInitiativeController` | Disclaimer present | **PASS** |
| 17 | Intensity indicators clearly display basis and missing data | `AnalyticsEngine` | Intensity calculated | **PASS** |
| 18 | Create decarbonization initiative | `POST /api/reduction-initiatives` | HTTP 201 Created | **PASS** |
| 19 | Retrieve initiative details with complete metadata | `GET /api/reduction-initiatives/{id}` | Complete metadata verified | **PASS** |
| 20 | Update initiative CapEx and details | `PUT /api/reduction-initiatives/{id}` | CapEx updated | **PASS** |
| 21 | Add milestone progress update | `POST /api/reduction-initiatives/{id}/progress`| Progress recorded | **PASS** |
| 22 | Progress history preservation (no updates overwritten) | `GET /api/reduction-initiatives/{id}/history` | 2 sequential entries preserved | **PASS** |
| 23 | Initiative status transition to completed | `PUT /api/reduction-initiatives/{id}` | Status: `completed` | **PASS** |
| 24 | Create reduction target | `POST /api/reduction-targets` | HTTP 201 Created | **PASS** |
| 25 | Target progress and remaining gap calculation | `GET /api/reduction-targets/{id}` | Progress & Gap computed | **PASS** |
| 26 | Standard member cannot create reduction initiatives | `POST /api/reduction-initiatives` | HTTP 403 Forbidden | **PASS** |
| 27 | Cross-tenant initiative isolation | `GET /api/reduction-initiatives/{id}` | HTTP 404 Not Found | **PASS** |
| 28 | Baseline and target mathematical consistency | `ReductionInitiativeController` | Baseline - Target matched | **PASS** |
| 29 | Expected vs Measured reductions stored in distinct fields | `reduction_initiatives` | Separate columns verified | **PASS** |
| 30 | Anti-Double-Counting: Initiatives do not deduct phantom reductions | `AnalyticsEngine` | Inventory derived purely from data | **PASS** |
| 31 | Operational reductions not classified as carbon credits | `ReductionInitiativeController` | Legitimate internal reduction | **PASS** |

---

## 6. Phase 5: Environmental Projects, Evidence & Readiness (40 Tests)

**File:** `test_phase5_projects_and_readiness.ps1`

| # | Test Case Description | Target Endpoint / Module | HTTP / Output | Status |
| :---: | :--- | :--- | :---: | :---: |
| 1 | Register Operational Reduction project successfully | `POST /api/projects` | HTTP 201 Created | **PASS** |
| 2 | Prevent operational reduction from claiming registry serials | `POST /api/projects` | HTTP 422 Unprocessable | **PASS** |
| 3 | Register Project Candidate undergoing methodology assessment | `POST /api/projects` | HTTP 201 Created | **PASS** |
| 4 | Prevent registering issued credit without registry credentials | `POST /api/projects` | HTTP 422 Unprocessable | **PASS** |
| 5 | Prevent direct project creation as verified environmental asset | `POST /api/projects` | HTTP 422 GOVERNANCE_VIOLATION | **PASS** |
| 6 | Prevent direct project creation as issued carbon credit | `POST /api/projects` | HTTP 422 GOVERNANCE_VIOLATION | **PASS** |
| 7 | Filter projects by classification tier (`project_candidate`) | `GET /api/projects?classification=` | Filter respected | **PASS** |
| 8 | Block promotion to verified asset without accepted evidence | `POST /api/projects/{id}/classification`| HTTP 422 Unprocessable | **PASS** |
| 9 | Immutable classification history preserves previous & new tier | `GET /api/projects/{id}/classification-history`| History preserved | **PASS** |
| 10 | Standard member cannot modify project classification | `POST /api/projects/{id}/classification`| HTTP 403 Forbidden | **PASS** |
| 11 | Upload valid evidence document (PDF PDD) | `POST /api/projects/{id}/documents` | HTTP 201 Created | **PASS** |
| 12 | Cryptographically randomized storage identifier (`doc_<32hex>.pdf`) | `ProjectDocument` | Sanitized random filename | **PASS** |
| 13 | Stored SHA-256 integrity hash matches actual file hash | `ProjectDocument` | SHA-256 hash verified | **PASS** |
| 14 | File resides in protected `storage/documents/{orgId}/` | File System Check | Outside public web root | **PASS** |
| 15 | Authenticated download streaming succeeds | `GET /api/projects/{id}/documents/{docId}/download`| Stream payload received | **PASS** |
| 16 | Reject unsupported and executable file types (PHP/EXE/SH) | `POST /api/projects/{id}/documents` | HTTP 422 INVALID_MIME_TYPE | **PASS** |
| 17 | Cross-tenant isolation blocks Org 2 from downloading Org 1 file | `GET /api/projects/{id}/documents/{docId}/download`| HTTP 404 Not Found | **PASS** |
| 18 | Upload replacement version increments version to 2 | `POST /api/projects/{id}/documents` | Version 2 generated | **PASS** |
| 19 | Prior document version 1 preserved in historical versions | `GET /api/projects/{id}/documents/{docId}/history`| Version 1 preserved | **PASS** |
| 20 | Admin archives evidence document | `PATCH /api/projects/{id}/documents/{docId}` | `review_status = archived` | **PASS** |
| 21 | Newly uploaded evidence document has review_status = 'pending' | `ProjectDocument` | Initial status: `pending` | **PASS** |
| 22 | Admin accepts evidence document review | `POST /api/projects/{id}/documents/{docId}/review`| Status: `accepted` | **PASS** |
| 23 | Admin transitions review status to 'requires_revision' | `POST /api/projects/{id}/documents/{docId}/review`| Status: `requires_revision` | **PASS** |
| 24 | Document review history preserves all sequential decisions | `GET /api/projects/{id}/documents/{docId}/reviews`| Audit log count >= 2 | **PASS** |
| 25 | Standard member cannot review documents | `POST /api/projects/{id}/documents/{docId}/review`| HTTP 403 Forbidden | **PASS** |
| 26 | Incomplete project detected and flagged is_complete = false | `ReadinessAssessmentEngine` | Completeness evaluated | **PASS** |
| 27 | Missing required fields explicitly diagnosed in assessment | `ReadinessAssessmentEngine` | Missing fields flagged | **PASS** |
| 28 | Assessment returns valid 0-100 scores for all 5 dimensions | `ReadinessAssessmentEngine` | 5 dimensions scored | **PASS** |
| 29 | Overall readiness percentage matches sum of weighted scores | `ReadinessAssessmentEngine` | Exactly 70.25% verified | **PASS** |
| 30 | Project with accepted PDD achieves advanced readiness stage | `ReadinessAssessmentEngine` | Stage: `verification_preparation`| **PASS** |
| 31 | Assessment generates structured gap analysis narrative | `ReadinessAssessmentEngine` | Actionable recommendations | **PASS** |
| 32 | Re-running readiness assessment preserves version 1 and creates v2 | `ReadinessAssessmentEngine` | Versioning verified | **PASS** |
| 33 | Cross-tenant isolation blocks Org 2 from viewing Org 1 assessment | `GET /api/projects/{id}/readiness-assessment`| HTTP 404 Not Found | **PASS** |
| 34 | Anti-Greenwash: High readiness never auto-upgrades classification | `ReadinessAssessmentEngine` | Manual governance preserved | **PASS** |
| 35 | Operational inventory emissions strictly segregated from project | `AnalyticsEngine` | No inventory blending | **PASS** |
| 36 | General PUT endpoint ignores readiness_stage mutation | `PUT /api/projects/{id}` | Direct mutation blocked | **PASS** |
| 37 | Dedicated stage transition records immutable audit entry | `POST /api/projects/{id}/stage` | `project_stage_history` entry | **PASS** |
| 38 | Non-admin member cannot transition readiness stage | `POST /api/projects/{id}/stage` | HTTP 403 Forbidden | **PASS** |
| 39 | Soft delete marks project as is_archived=1 and preserves audit | `DELETE /api/projects/{id}` | `is_archived=1` | **PASS** |
| 40 | Migration runner is idempotent with schema_migrations tracking | `MigrationRunner::runAll` | 0 pending, lock verified | **PASS** |

---

## 7. Phase 6: Carbon Marketing Marketplace (21 Tests)

**File:** `test_phase6_marketplace.ps1`

| # | Test Case Description | Target Endpoint / Module | HTTP / Output | Status |
| :---: | :--- | :--- | :---: | :---: |
| 1 | Operational reduction projects cannot be listed | `POST /api/marketplace/listings` | HTTP 422 INELIGIBLE_PROJECT | **PASS** |
| 2 | Unverified project candidates cannot be listed | `POST /api/marketplace/listings` | HTTP 422 INELIGIBLE_PROJECT | **PASS** |
| 3 | Eligible projects endpoint returns verified assets only | `GET /api/marketplace/eligible-projects` | Verified assets only | **PASS** |
| 4 | Organization creates draft listing with generated code | `POST /api/marketplace/listings` | `MKT-20FD3F4D` generated | **PASS** |
| 5 | Reject invalid or negative listing volume | `POST /api/marketplace/listings` | HTTP 422 Validation Error | **PASS** |
| 6 | Organization retrieves their marketplace listings table | `GET /api/marketplace/listings` | Listings returned | **PASS** |
| 7 | Organization submits listing for review (becomes pending_review) | `POST /api/marketplace/listings/{id}/submit`| Status: `pending_review` | **PASS** |
| 8 | Organization cannot self-approve its own listing | `POST /api/admin/marketplace/listings/{id}/moderate`| HTTP 403 Forbidden | **PASS** |
| 9 | Non-super-admin member cannot view moderation queue | `GET /api/admin/marketplace/listings/pending` | HTTP 403 Forbidden | **PASS** |
| 10 | Super Admin inspects pending queue and identifies listing | `GET /api/admin/marketplace/listings/pending` | Queue parsed successfully | **PASS** |
| 11 | Moderation rejection requires non-empty explanation reason | `POST /api/admin/marketplace/listings/{id}/moderate`| HTTP 422 REASON_REQUIRED | **PASS** |
| 12 | Super Admin rejects listing with justification | `POST /api/admin/marketplace/listings/{id}/moderate`| Status: `rejected` | **PASS** |
| 13 | Super Admin approves listing; transitions to published | `POST /api/admin/marketplace/listings/{id}/moderate`| Status: `published` | **PASS** |
| 14 | Public unauthenticated endpoint returns published listing | `GET /api/public/marketplace/listings` | Published listing found | **PASS** |
| 15 | Public search filters accurately by keyword and tier | `GET /api/public/marketplace/listings?q=` | Filter matches | **PASS** |
| 16 | Public detail endpoint masks storage paths and assessments | `GET /api/public/marketplace/listings/{id}` | Internal data masked | **PASS** |
| 17 | Prospective buyer submits inquiry and receives INQ reference | `POST /api/public/marketplace/inquiries` | `INQ-B6F1CF23` returned | **PASS** |
| 18 | Cross-tenant isolation blocks Org 2 from viewing Org 1 inquiry | `GET /api/marketplace/inquiries/{id}` | HTTP 404 Not Found | **PASS** |
| 19 | Organization retrieves received inquiry with initial message | `GET /api/marketplace/inquiries/{id}` | Full payload returned | **PASS** |
| 20 | Organization replies to inquiry; status becomes in_discussion | `POST /api/marketplace/inquiries/{id}/messages`| Reply recorded | **PASS** |
| 21 | Super Admin suspends listing and restores with audit history | `POST /api/admin/marketplace/listings/{id}/suspend`| Suspended & Restored | **PASS** |

---

## 8. Final Security & Integrity Audit Suite (19 Tests)

**File:** `test_final_security_audit.ps1`

| # | Test Case Description | Target Endpoint / Module | HTTP / Output | Status |
| :---: | :--- | :--- | :---: | :---: |
| 1 | Complete PHP syntax check across entire backend (56 files) | `php -l` on all PHP files | 56 files, 0 errors | **PASS** |
| 2 | MigrationRunner executes idempotently under advisory lock | `MigrationRunner::runAll` | 0 pending, lock held | **PASS** |
| 3 | Audit history foreign keys enforce ON DELETE RESTRICT | Information Schema FKs | `RESTRICT` enforced | **PASS** |
| 4 | Safe Client IP ignores untrusted X-Forwarded-For header | `Request::getClientIp` | Recorded IP: `127.0.0.1` | **PASS** |
| 5 | Persistent rate limiting blocks excessive inquiries | `POST /api/public/marketplace/inquiries`| HTTP 429 Rate Limit | **PASS** |
| 6 | Segregation of duties prevents self-approval | `POST /api/admin/marketplace/...` | HTTP 422 Blocked | **PASS** |
| 7 | Moderation rejection requires mandatory reason | `POST /api/admin/marketplace/...` | HTTP 422 Required | **PASS** |
| 8 | Published listing appears in public catalog initially | `GET /api/public/marketplace/listings` | Found in catalog | **PASS** |
| 9 | Downgraded project listing automatically removed from catalog | Dynamic Governance Trigger | Listing hidden | **PASS** |
| 10 | Direct public detail for invalidated listing returns 404 | `GET /api/public/marketplace/listings/{id}`| HTTP 404 Not Found | **PASS** |
| 11 | Cross-tenant isolation blocks Org 2 from reading Org 1 listing | `GET /api/marketplace/listings/{id}` | HTTP 404 Not Found | **PASS** |
| 12 | Cross-tenant isolation blocks Org 2 from reading Org 1 inquiry | `GET /api/marketplace/inquiries/{id}` | HTTP 404 Not Found | **PASS** |
| 13 | IDOR protection blocks Org 2 from injecting messages into Org 1 | `POST /api/marketplace/inquiries/{id}/messages`| HTTP 404 Not Found | **PASS** |
| 14 | State-changing POST without CSRF token rejected | `POST /api/facilities` | HTTP 403 Forbidden | **PASS** |
| 15 | Session completely destroyed upon logout; subsequent GET returns 401 | `GET /api/organization` | HTTP 401 Unauthorized | **PASS** |
| 16 | Public detail output masks physical storage paths | `GET /api/public/marketplace/listings/{id}`| Zero storage paths | **PASS** |
| 17 | Public detail output masks SHA-256 document file hashes | `GET /api/public/marketplace/listings/{id}`| Zero file hashes | **PASS** |
| 18 | Public detail output masks internal readiness assessment scores | `GET /api/public/marketplace/listings/{id}`| Zero readiness scores | **PASS** |
| 19 | Public detail output masks internal admin moderation notes | `GET /api/public/marketplace/listings/{id}`| Zero admin notes | **PASS** |

---

## 9. Complete E2E User Journeys Integration Suite (21 Tests)

**File:** `verify_complete_e2e_journeys.php`

| # | Journey & Step Description | Target Endpoint | HTTP / Output | Status |
| :---: | :--- | :--- | :---: | :---: |
| J1-1 | Client Registration (`Dr. Elena Rostova`) | `POST /api/auth/register` | HTTP 201 Created | **PASS** |
| J1-2 | Session Validation & User Profile | `GET /api/auth/me` | HTTP 200 OK | **PASS** |
| J1-3 | Organization Overview Dashboard | `GET /api/organization` | HTTP 200 OK | **PASS** |
| J1-4 | Active Healthcare Domain Workspace Verified | `GET /api/domains/healthcare` | HTTP 200 OK | **PASS** |
| J1-5 | Create Operational Facility (`Munich BioLab Alpha`) | `POST /api/facilities` | HTTP 201 Created | **PASS** |
| J1-6 | Create Reporting Period (`CY 2025`) | `POST /api/reporting-periods` | HTTP 201 Created | **PASS** |
| J1-7 | Input Activity Data - Verified Category | `POST /api/activity-data` | HTTP 201 Created | **PASS** |
| J1-8 | Calculate Verified Carbon Footprint for Record | `POST /api/activity-data/{id}/calculate` | Computed emissions | **PASS** |
| J1-9 | Carbon Intelligence Overview Analytics | `GET /api/analytics/overview` | Scope 1/2/3 totals | **PASS** |
| J1-10 | Export GHG Intelligence Report (CSV) | `GET /api/analytics/export?type=emissions`| Valid CSV headers | **PASS** |
| J1-11 | Export Activity Records Data (CSV) | `GET /api/analytics/export?type=activity` | Valid CSV headers | **PASS** |
| J1-12 | Create Decarbonization Reduction Initiative | `POST /api/reduction-initiatives` | HTTP 201 Created | **PASS** |
| J1-13 | Register Environmental Project Candidate | `POST /api/projects` | HTTP 201 Created | **PASS** |
| J1-14 | Upload Project Evidence Document (PDD) | `POST /api/projects/{id}/documents` | HTTP 201 Created | **PASS** |
| J1-15 | Algorithmic Readiness Assessment Evaluation | `POST /api/projects/{id}/readiness-assessment`| Readiness computed | **PASS** |
| J2-1 | Super Admin Authentication (`admin@ecopulse.org`) | `POST /api/auth/login` | HTTP 200 OK | **PASS** |
| J2-2 | Super Admin Pending Marketplace Moderation Queue | `GET /api/admin/marketplace/listings/pending` | HTTP 200 OK | **PASS** |
| J2-3 | Super Admin Moderation Audit Trail Access | `GET /api/admin/marketplace/audit-trail` | HTTP 200 OK | **PASS** |
| J3-1 | Public Marketplace Catalog Browsing (No Auth Required) | `GET /api/public/marketplace/listings` | HTTP 200 OK | **PASS** |
| J3-2 | Public Listing Detail Inspection | `GET /api/public/marketplace/listings/{id}` | HTTP 200 OK | **PASS** |
| J3-3 | Submit High-Integrity Buyer Inquiry with Ref Code | `POST /api/public/marketplace/inquiries` | `INQ-` generated | **PASS** |

---

## 10. Browser Security & Negative Testing Suite (9 Tests)

**File:** `06_security_negative_test.js`

| # | Test Scenario | Browser Action / Assertion | Observed Behavior | Status |
| :---: | :--- | :--- | :--- | :---: |
| 1 | Unauthenticated Access Guard | Navigate to `dashboard.html` without session | Redirected to `login.html` | **PASS** |
| 2 | Direct API Unauthenticated Guard | `fetch('/api/facilities')` without session | HTTP 401 Unauthorized | **PASS** |
| 3 | Authenticate Client Owner | Login as `sarah.jenkins@apexhealth.org` | Dashboard loaded, CSRF stored | **PASS** |
| 4a | Cross-Tenant Facility IDOR Protection | Request non-owned facility ID | HTTP 404 Not Found | **PASS** |
| 4b | Cross-Tenant Inquiry IDOR Protection | Request non-owned inquiry thread ID | HTTP 404 Not Found | **PASS** |
| 5 | CSRF Header Enforcement on Mutations | `POST /api/facilities` without CSRF token | HTTP 403 Forbidden | **PASS** |
| 6 | Unapproved / Suspended Listing Isolation | Fetch suspended listing detail | HTTP 404 Not Found | **PASS** |
| 7 | Inquiries against Suspended Listings | Submit inquiry to suspended listing | HTTP 429/422/404 Blocked | **PASS** |
| 8 | Rate Limiting Protection on Inquiries | 6 rapid buyer inquiry submissions | HTTP 429 Too Many Requests | **PASS** |
| 9 | Zero XSS Injection in Rendered Tables | Inspect DOM rendering for script tags | HTML entity encoding verified | **PASS** |

---

## 11. Clean-Browser User Journey Verifications (26 Steps)

**File:** `final_02_complete_user_journeys.js`

| Step | User Journey | Interface Verified | Screenshot Evidence | Status |
| :---: | :--- | :--- | :--- | :---: |
| A1 | Client Journey | Dedicated User & Org Registration | `journey_a01_registered_portal.png` | **PASSED** |
| A2 | Client Journey | Organization Context Verification | Dashboard loaded with correct org ID | **PASSED** |
| A3 | Client Journey | 9 Business Domains Grid View | `journey_a02_domains_view.png` | **PASSED** |
| A4 | Client Journey | Facility Creation (`Munich BioLab Alpha`) | `journey_a03_facility_created.png` | **PASSED** |
| A5 | Client Journey | Reporting Periods & Auto-Seeded CY 2026 | `journey_a04_period_created.png` | **PASSED** |
| A6 | Client Journey | Team Management & Member Roles | `journey_a05_team_member_added.png` | **PASSED** |
| A7 | Client Journey | Healthcare Workspace & Activity Intake | `journey_a06_activity_logged.png` | **PASSED** |
| A8 | Client Journey | Carbon Calculation Engine Real-Time Run | 1,500 L diesel -> 4.029 tCO2e computed | **PASSED** |
| A9 | Client Journey | Carbon Intelligence Analytics & Scope Donut | `journey_a07_carbon_analytics.png` | **PASSED** |
| A10 | Client Journey | Decarbonization Opportunities & Recommendations | Opportunities grid rendered | **PASSED** |
| A11 | Client Journey | Reduction Targets & Milestone Initiatives | `journey_a08_reduction_targets.png` | **PASSED** |
| A12 | Client Journey | Environmental Project Candidate Registration | `journey_a09_project_registered.png` | **PASSED** |
| A13 | Client Journey | Project Detail & Evidence Upload Modal | `journey_a10_project_detail_evidence.png` | **PASSED** |
| A14 | Client Journey | Algorithmic Readiness Assessment Scorecard | `journey_a11_readiness_scorecard.png` | **PASSED** |
| A15 | Client Journey | Client Marketplace Listings Management | `journey_a12_client_marketplace.png` | **PASSED** |
| B1 | Super Admin | Super Admin Authentication (`admin@ecopulse.org`)| `journey_b01_admin_authenticated.png` | **PASSED** |
| B2 | Super Admin | 130-Organization Switcher Dropdown | All tenants listed in header dropdown | **PASSED** |
| B3 | Super Admin | Emission Factor Catalog (50 Active Factors) | `journey_b02_emission_factors.png` | **PASSED** |
| B4 | Super Admin | **Marketplace Moderation Queue (FIX VERIFIED)** | `journey_b03_moderation_queue.png` | **PASSED** |
| B5 | Super Admin | Moderation Audit Trail API & History Table | `journey_b04_moderation_audit_trail.png` | **PASSED** |
| B6 | Super Admin | Segregation of Duties & RBAC Guard | Nav link hidden & HTTP 403 Forbidden | **PASSED** |
| C1 | Public Marketplace | Public Catalog Browsing (14 Live Listings) | `journey_c01_public_catalog.png` | **PASSED** |
| C2 | Public Marketplace | Keyword & Classification Search Filtering | `journey_c02_filtered_results.png` | **PASSED** |
| C3 | Public Marketplace | Shielded Listing Detail (No Storage Paths) | `journey_c03_listing_detail_shielded.png` | **PASSED** |
| C4 | Public Marketplace | Buyer Inquiry Submission & Rate Limiter | `journey_c04_inquiry_submitted.png` | **PASSED** |
| C5 | Public Marketplace | Seller Inquiry Inbox View Loaded | Thread inbox rendered | **PASSED** |
