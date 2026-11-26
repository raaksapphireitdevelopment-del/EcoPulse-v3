# EcoPulse MVP V3 — Deployment Readiness Checklist

**Document Version:** 3.0  
**Target Environment:** Production / Staging Cloud Linux (e.g., Ubuntu 22.04 LTS / AWS / GCP / DigitalOcean)  
**Application Stack:** PHP 8.2+ / MySQL 8.0+ / Apache or Nginx / Vanilla JS ES6+ / Bootstrap 5 / Chart.js  

---

## 1. Server & Runtime Prerequisites

| Requirement | Production Target | Status / Verification Method | Notes |
| :--- | :--- | :---: | :--- |
| **PHP Runtime** | PHP 8.2.0 or higher | ✅ Verified (8.2.12) | Strict types enabled across all controllers, core, and models |
| **PHP Extensions** | `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`, `json`, `curl` | ✅ Verified Active | `fileinfo` required for deep MIME verification; `openssl` for crypto tokens |
| **PHP Configuration** | `opcache.enable = 1`<br>`display_errors = 0`<br>`log_errors = 1`<br>`upload_max_filesize = 25M`<br>`post_max_size = 30M`<br>`memory_limit = 256M` | ⚠️ Pending Prod INI | Development used `display_errors=0` in `index.php`; ensure production `php.ini` sets `display_errors=Off` |
| **Database Server** | MySQL 8.0.30+ (or MariaDB 10.6+) | ✅ Verified (8.0.33) | `innodb_file_per_table = 1`, `character-set-server = utf8mb4`, `collation-server = utf8mb4_unicode_ci` |
| **Web Server / Reverse Proxy** | Apache 2.4+ (with `mod_rewrite`, `mod_headers`) or Nginx 1.22+ | ✅ Verified (`.htaccess` present) | Forward all `/api/*` traffic to `Backend/index.php`; serve `Frontend/*` as static assets |

---

## 2. Environment Variables Configuration

Create a protected `.env` file or export server system environment variables before launch.

| Variable Name | Required | Example Production Value | Purpose |
| :--- | :---: | :--- | :--- |
| `DB_HOST` | **YES** | `10.0.1.15` (Private VPC IP) | MySQL database host (do NOT expose to public internet) |
| `DB_PORT` | **YES** | `3306` | MySQL port |
| `DB_NAME` | **YES** | `ecopulse_prod` | Dedicated production database schema name |
| `DB_USER` | **YES** | `ecopulse_app` | Dedicated database user with restricted DML privileges |
| `DB_PASS` | **YES** | `StrongVaultGeneratedPassword#2026!` | Strong password retrieved from secrets manager |
| `TRUSTED_PROXIES` | **YES** | `10.0.0.2,10.0.0.3` (Load Balancer IPs) | Comma-separated list of trusted upstream reverse proxies. Enforces anti-spoofing in `Request::getClientIp()` |
| `APP_ENV` | **YES** | `production` | Declares application operating mode |

> [!CAUTION]
> Never commit database passwords, API secrets, or `.env` files into source control repositories.

---

## 3. Web Server & Security Hardening

### A. HTTP Security Headers
Configure your reverse proxy (Nginx / Apache) or cloud CDN (Cloudflare / AWS CloudFront) to inject standard security headers:

```apache
# Apache VirtualHost / Directory Security Header Configuration
Header always set Strict-Transport-Security "max-age=63072000; includeSubDomains; preload"
Header always set X-Content-Type-Options "nosniff"
Header always set X-Frame-Options "DENY"
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; font-src 'self' https://cdn.jsdelivr.net; img-src 'self' data:; connect-src 'self';"
```

### B. Session Security Settings
The EcoPulse core session manager (`Backend/core/Session.php`) automatically configures:
- `cookie_httponly = true`: Blocks JavaScript access to the `ecopulse_v3_sess` cookie.
- `cookie_samesite = 'Lax'`: Mitigates Cross-Site Request Forgery (CSRF).
- `cookie_secure = true`: Automatically activated when incoming traffic is on HTTPS (port 443).
- `session.use_strict_mode = 1`: Disallows uninitialized session IDs.
- Inactivity Timeout: 7,200 seconds (2 hours) with automatic rotation.

### C. File Upload Security
- Dedicated Evidence Directory: `Backend/storage/documents/{orgId}/` must be located outside the public web root.
- Ownership & Permissions: File storage directory owned by web server user (`www-data:www-data`), permissions `0750`.
- Cryptographic Identifiers: Uploaded files are renamed using cryptographically secure random names (`doc_<32hex>.pdf`) to prevent path traversal and execution.
- Real MIME Verification: Verification through PHP `finfo_file()` prevents executable script masquerading.

---

## 4. Database Setup & Migrations

- [x] **Schema Concurrency Lock:** `MigrationRunner` uses MySQL advisory lock `GET_LOCK('ecopulse_migrations', 10)` to prevent race conditions during multi-instance autoscaling deployments.
- [x] **Schema Migration Tracking:** Table `schema_migrations` records executed migrations. The runner is idempotent:
  ```bash
  php Backend/database/MigrationRunner.php
  ```
- [x] **Referential Integrity:** Foreign keys on `audit_logs`, `project_documents`, `project_stage_history`, and `inquiry_messages` enforce `ON DELETE RESTRICT` to guarantee compliance audit permanence.
- [ ] **Automated Backup Strategy:**
  - Automated daily `mysqldump` snapshot stored in offsite encrypted cold storage (e.g., AWS S3 Glacier with Object Lock).
  - Point-in-time recovery enabled via MySQL binary logging (`binlog`).

---

## 5. Performance Tuning & Caching

- [ ] **OPcache Activation:** In production `php.ini`, configure:
  ```ini
  opcache.enable=1
  opcache.memory_consumption=128
  opcache.interned_strings_buffer=16
  opcache.max_accelerated_files=10000
  opcache.validate_timestamps=0
  opcache.revalidate_freq=0
  ```
- [x] **Database Indexing:** Composite indexes exist on:
  - `activity_records (organization_id, facility_id, reporting_period_id)`
  - `emission_factors (category_code, is_active, is_demo)`
  - `buyer_inquiries (listing_id, ip_address, created_at)`
  - `audit_logs (organization_id, created_at)`
- [ ] **Gzip / Brotli Compression:** Enable on static `.js`, `.css`, and `.svg` files in Apache/Nginx.

---

## 6. Operational Monitoring & Logging

- [x] **Structured Audit Logging:** Every compliance event (calculations, approvals, stage transitions, uploads) is recorded in `audit_logs` with actor user ID, client IP, timestamp, old values, and new values.
- [x] **Exception Handling:** Uncaught PHP exceptions are logged via `EcoPulse\Core\Logger` with full stack traces while masking internal error details from HTTP clients (returning standard JSON with HTTP 500).
- [ ] **Disk Usage Alerting:** Set up CloudWatch / Prometheus alert when disk storage on `Backend/storage/` reaches 80% capacity.
- [ ] **Uptime & Health Checks:** Point uptime monitoring service (e.g., UptimeRobot, Datadog) to:
  ```http
  GET /api/health
  ```
  Expected response: `200 OK` with `{"status":"healthy","database":"connected"}`.

---

## 7. Pre-Deployment Defect Resolution

Prior to public production release, apply the single identified frontend defect patch documented in `KNOWN_LIMITATIONS.md`:
- [ ] **Moderation Queue Response Object Handling:** Update `Frontend/js/marketplace.js:148` from `queue.map(...)` to `(queue.data || queue).map(...)` to render the Super Admin pending moderation table.
