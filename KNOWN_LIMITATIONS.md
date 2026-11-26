# EcoPulse MVP V3 — Known Limitations & Technical Considerations

**Document Version:** 3.1  
**Verification Date:** November 20, 2026  
**Status:** Defect Resolved & Final Release Validated  

This document details resolved issues, acceptable MVP engineering trade-offs, and recommended enhancements for Phase 7 of EcoPulse MVP V3.

---

## 1. Resolved Frontend Defect: Moderation Queue Array Normalization

### Defect Summary & Resolution
- **Component:** Super Admin Portal — Carbon Marketplace Moderation View (`Frontend/assets/js/modules/marketplace.js`)
- **Initial Symptom:** `queue.map is not a function` in the browser console when opening the moderation tab, caused by the backend returning a paginated object `{ success: true, data: { data: [...], total: ... } }` while the UI expected a raw array.
- **Correction Applied:**
  In `Frontend/assets/js/modules/marketplace.js`:
  1. Updated `loadModerationView()` to normalize the incoming response:
     ```javascript
     const raw = res.data;
     const queue = Array.isArray(raw) ? raw : (raw?.data || raw?.listings || []);
     ```
  2. Updated `renderModerationQueue()` to defensively ensure `queue` is an array:
     ```javascript
     const queue = Array.isArray(rawQueue) ? rawQueue : (rawQueue?.data || rawQueue?.listings || []);
     ```
  3. Added proper field fallbacks for title, volume, and classification tier:
     ```javascript
     ${escapeHtml(l.listing_title || l.title || 'Untitled Listing')}
     ${(l.eligible_classification || l.classification_tier) === 'issued_carbon_credit' ? '...' : '...'}
     <strong>${Number(l.total_volume_tco2e || l.available_volume_tco2e || l.offered_quantity || 0).toLocaleString()}</strong> tCO2e
     ```
  4. Updated `loadAuditHistory()` with identical array normalization for moderation history.
- **Verification Evidence:**
  - Fresh headless Chrome execution verified the Moderation Queue table renders all pending listings cleanly without errors.
  - Browser console logs confirmed **0 fatal errors** and **0 "queue.map is not a function"** occurrences.
  - Screenshots confirmed: `moderation_01_queue_fixed.png` and `journey_b03_moderation_queue.png`.
  - Approve action, Reject action, Suspend action, and Restore action verified operational.
  - Non-super-admin RBAC segregation confirmed (navigation link hidden via `d-none`, API calls rejected with `HTTP 403 Forbidden`).

---

## 2. Security & Rate Limiting Thresholds

### IP Rate Limiting on Buyer Inquiries
- **Implementation:** Persistent rate limiter in MySQL (`buyer_inquiries` table tracking timestamp and IP address).
- **Rule:** Maximum of 5 buyer inquiry submissions per 15 minutes per IP address.
- **Operational Behavior:** Rapid automated testing or multiple team members submitting inquiries from the same corporate NAT/gateway will trigger `HTTP 429 Too Many Requests`.
- **Recommended Practice:** Automated test harnesses should run the dedicated reset helper (`scratch/reset_test_rate_limits.php`) between repeated test passes, or configure an IP whitelist for test runners via environment configuration in Phase 7.

---

## 3. Business Logic & Regulatory Integrity Constraints

### Reporting Period Overlap Guard
- **Rule:** An organization cannot create overlapping active reporting periods with identical date spans.
- **Operational Behavior:** When a new organization registers, a default period (`Calendar Year 2026`, Jan 1 – Dec 31) is automatically seeded. Attempting to create an annual period for the same year returns `HTTP 409 Conflict (PERIOD_OVERLAP_DETECTED)`.
- **Purpose:** Strict regulatory guard adhering to GHG Protocol anti-double-counting standards.

### Strict Governance Separation of Duties
- **Rule:** The user who registers or manages a project listing cannot self-approve or moderate the listing.
- **Operational Behavior:** Attempting to call the moderation approval endpoint while authenticated as the listing organization returns `HTTP 422 SELF_APPROVAL_PROHIBITED` or `HTTP 403 Forbidden`. Only dedicated platform super administrators who did not create the listing can publish listings.

---

## 4. Architectural MVP Trade-Offs

### Evidence Document Storage
- **Current MVP Implementation:** Uploaded PDF and DOCX files are stored on local server filesystem in `Backend/storage/documents/{orgId}/` using cryptographically generated identifiers (`doc_<32hex>.pdf`), with metadata and SHA-256 hashes recorded in MySQL.
- **Production Scaling Consideration:** In multi-server or containerized Kubernetes environments, local disk storage must be mounted via a shared network filesystem (NFS) or refactored to an S3-compatible cloud object storage service (AWS S3 / Google Cloud Storage / Azure Blob Storage).

### Single-Process PHP Server vs Reverse Proxy
- **Current Setup:** Application served via PHP 8.2 development server (`php -S 127.0.0.1:8000`).
- **Production Architecture:** The application should be fronted by an enterprise reverse proxy (Nginx or Apache) with TLS/SSL termination, HTTP/2 or HTTP/3, and OPcache enabled.

---

## 5. Recommended Phase 7 Roadmap & Enhancements

| Area | Recommended Enhancement | Business Value |
| :--- | :--- | :--- |
| **Cloud Storage** | Implement an Amazon S3 / Google Cloud Storage adapter for `Backend/storage/`. | Enables multi-region scalability and high-availability document storage. |
| **Registry Integration** | Add REST API sync connectors to carbon registries (Verra VCS, Gold Standard, Climate Action Reserve). | Automates serial number verification and real-time retirement status checks. |
| **Batch Intake** | Add bulk CSV/Excel upload and parsing wizard for facility activity intake data. | Reduces manual onboarding effort for large enterprise clients with thousands of meters. |
| **Notifications** | Add transactional email alerts (SendGrid / AWS SES) and WebSocket updates for inquiry negotiations. | Informs buyers and sellers in real time when new messages or offers arrive. |
