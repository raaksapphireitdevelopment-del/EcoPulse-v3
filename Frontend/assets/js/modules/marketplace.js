/**
 * EcoPulse MVP V3 - Carbon Marketing Marketplace Module
 * Handles organization listings, buyer inquiry threading, and Super Admin moderation.
 */

const EcoPulseMarketplace = (() => {
  let listings = [];
  let inquiries = [];
  let currentInquiry = null;

  const escapeHtml = (str) => {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  };

  const getStatusBadge = (status) => {
    switch (status) {
      case 'draft':
        return '<span class="badge bg-secondary">Draft</span>';
      case 'pending_review':
        return '<span class="badge bg-warning text-dark"><i class="bi bi-clock-history me-1"></i>Pending Review</span>';
      case 'approved':
        return '<span class="badge bg-info text-dark"><i class="bi bi-check2-circle me-1"></i>Approved</span>';
      case 'published':
        return '<span class="badge bg-success text-white"><i class="bi bi-globe me-1"></i>Published</span>';
      case 'rejected':
        return '<span class="badge bg-danger text-white"><i class="bi bi-x-circle me-1"></i>Rejected</span>';
      case 'suspended':
        return '<span class="badge bg-dark text-white"><i class="bi bi-pause-circle me-1"></i>Suspended</span>';
      case 'archived':
        return '<span class="badge bg-light text-secondary border">Archived</span>';
      default:
        return `<span class="badge bg-light text-dark">${escapeHtml(status)}</span>`;
    }
  };

  const getInquiryStatusBadge = (status) => {
    switch (status) {
      case 'new':
        return '<span class="badge bg-primary text-white">New</span>';
      case 'contacted':
        return '<span class="badge bg-info text-dark">Contacted</span>';
      case 'in_discussion':
        return '<span class="badge bg-warning text-dark">In Discussion</span>';
      case 'closed':
        return '<span class="badge bg-success text-white">Closed</span>';
      case 'rejected':
        return '<span class="badge bg-secondary text-white">Rejected</span>';
      default:
        return `<span class="badge bg-light text-dark">${escapeHtml(status)}</span>`;
    }
  };

  // ==========================================
  // ORGANIZATION LISTINGS VIEW
  // ==========================================

  const loadMarketplaceView = async () => {
    const container = document.getElementById('view-container');
    if (!container) return;

    container.innerHTML = `
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h2 class="fw-bold mb-1">Carbon Marketing Marketplace</h2>
          <p class="text-muted mb-0">Showcase verified environmental assets and issued credits to prospective buyers</p>
        </div>
        <div class="d-flex gap-2">
          <a href="../marketplace.html" target="_blank" class="btn btn-outline-secondary">
            <i class="bi bi-box-arrow-up-right me-1"></i> View Public Marketplace
          </a>
          <button class="btn btn-brand" onclick="EcoPulseMarketplace.openCreateListingModal()">
            <i class="bi bi-plus-lg me-1"></i> Create Listing
          </button>
        </div>
      </div>

      <div class="alert alert-info d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-shield-exclamation fs-4 me-3 text-info"></i>
        <div class="small">
          <strong>Marketplace Governance Disclaimer:</strong> EcoPulse is a carbon intelligence and project management platform. We do not issue carbon credits, certify projects, operate a financial exchange, or process payments. Only projects classified as <em>Verified Environmental Asset</em> or <em>Issued Carbon Credit</em> with accepted independent audit reports are eligible for listing.
        </div>
      </div>

      <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
          <h5 class="card-title mb-0 fw-bold">My Organization's Listings</h5>
          <button class="btn btn-sm btn-outline-secondary" onclick="EcoPulseMarketplace.loadMarketplaceView()">
            <i class="bi bi-arrow-clockwise me-1"></i> Refresh
          </button>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-3">Listing Code</th>
                  <th>Title & Project</th>
                  <th>Classification</th>
                  <th>Offered Volume</th>
                  <th>Indicative Price</th>
                  <th>Status</th>
                  <th>Created</th>
                  <th class="text-end pe-3">Actions</th>
                </tr>
              </thead>
              <tbody id="org-listings-tbody">
                <tr>
                  <td colspan="8" class="text-center py-4 text-muted">
                    <div class="spinner-border spinner-border-sm text-secondary me-2"></div>
                    Loading marketplace listings...
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    `;

    try {
      const res = await EcoPulseApi.get('/api/organization/marketplace/listings');
      const raw = res.data;
      listings = Array.isArray(raw) ? raw : (raw?.data || raw?.listings || []);
      renderOrgListingsTable();
    } catch (err) {
      document.getElementById('org-listings-tbody').innerHTML = `
        <tr>
          <td colspan="8" class="text-center py-4 text-danger">
            <i class="bi bi-exclamation-triangle me-1"></i> ${escapeHtml(err.message || 'Failed to load listings')}
          </td>
        </tr>
      `;
    }
  };

  const renderOrgListingsTable = () => {
    const tbody = document.getElementById('org-listings-tbody');
    if (!tbody) return;

    if (listings.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="8" class="text-center py-5 text-muted">
            <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
            No marketplace listings created yet.<br>
            <small>Create a listing from an eligible project with accepted independent audit evidence.</small>
          </td>
        </tr>
      `;
      return;
    }

    tbody.innerHTML = listings.map(l => {
      const isDraftOrRejected = l.status === 'draft' || l.status === 'rejected';
      const isArchived = l.status === 'archived';

      return `
        <tr>
          <td class="ps-3 fw-bold text-dark font-monospace">${escapeHtml(l.listing_code || '—')}</td>
          <td>
            <div class="fw-semibold text-dark">${escapeHtml(l.title)}</div>
            <small class="text-muted">${escapeHtml(l.project_title || 'Project #' + l.project_id)}</small>
          </td>
          <td>
            ${l.classification_tier === 'issued_carbon_credit' 
              ? '<span class="badge bg-success text-white">Issued Credit</span>' 
              : '<span class="badge bg-info text-dark">Verified Asset</span>'}
          </td>
          <td>
            <strong>${Number(l.offered_quantity).toLocaleString()}</strong>
            <span class="text-muted small"> tCO2e</span>
          </td>
          <td>
            ${l.unit_price ? `<strong>${Number(l.unit_price).toFixed(2)}</strong> <span class="text-muted small">${escapeHtml(l.currency || 'USD')}</span>` : '<span class="text-muted">Inquire</span>'}
          </td>
          <td>${getStatusBadge(l.status)}</td>
          <td class="small text-muted">${l.created_at ? l.created_at.substring(0, 10) : '—'}</td>
          <td class="text-end pe-3">
            <div class="btn-group btn-group-sm">
              ${isDraftOrRejected ? `
                <button class="btn btn-outline-primary" title="Submit for Super Admin Review" onclick="EcoPulseMarketplace.submitListing(${l.id})">
                  <i class="bi bi-send me-1"></i> Submit
                </button>
              ` : ''}
              ${!isArchived ? `
                <button class="btn btn-outline-danger" title="Archive Listing" onclick="EcoPulseMarketplace.archiveListing(${l.id})">
                  <i class="bi bi-archive"></i>
                </button>
              ` : ''}
            </div>
          </td>
        </tr>
      `;
    }).join('');
  };

  const openCreateListingModal = async () => {
    let modalEl = document.getElementById('createListingModal');
    if (!modalEl) {
      modalEl = document.createElement('div');
      modalEl.className = 'modal fade';
      modalEl.id = 'createListingModal';
      modalEl.tabIndex = -1;
      modalEl.innerHTML = `
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title fw-bold">Create Marketplace Listing</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="create-listing-form" onsubmit="EcoPulseMarketplace.handleCreateListing(event)">
              <div class="modal-body">
                <div class="alert alert-warning py-2 small mb-3">
                  <i class="bi bi-info-circle me-1"></i> Only projects with classification <strong>Verified Environmental Asset</strong> or <strong>Issued Carbon Credit</strong> with at least one accepted independent audit document are eligible.
                </div>

                <div class="mb-3">
                  <label class="form-label fw-semibold">Select Eligible Project <span class="text-danger">*</span></label>
                  <select class="form-select" id="listing-project-select" required onchange="EcoPulseMarketplace.handleProjectSelectChange(this)">
                    <option value="">-- Loading eligible projects... --</option>
                  </select>
                </div>

                <div class="row g-3 mb-3">
                  <div class="col-md-8">
                    <label class="form-label fw-semibold">Listing Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="listing-title" required placeholder="e.g. 2026 Solar Photovoltaic Generation Offsets">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label fw-semibold">Offered Volume (tCO2e) <span class="text-danger">*</span></label>
                    <input type="number" step="0.001" min="0.001" class="form-control" id="listing-volume" required placeholder="1000.00">
                  </div>
                </div>

                <div class="row g-3 mb-3">
                  <div class="col-md-6">
                    <label class="form-label fw-semibold">Indicative Unit Price (Optional)</label>
                    <div class="input-group">
                      <span class="input-group-text">$</span>
                      <input type="number" step="0.01" min="0" class="form-control" id="listing-price" placeholder="15.00">
                      <span class="input-group-text">USD / tCO2e</span>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-semibold">Primary Contact Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="listing-contact-email" required placeholder="sustainability@company.com">
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label fw-semibold">Contact Person Name</label>
                  <input type="text" class="form-control" id="listing-contact-name" placeholder="Lead Sustainability Manager">
                </div>

                <div class="mb-3">
                  <label class="form-label fw-semibold">Public Summary Description <span class="text-danger">*</span></label>
                  <textarea class="form-control" id="listing-summary" rows="3" required placeholder="Describe the environmental impact, project vintage, co-benefits, and location..."></textarea>
                </div>

                <div class="mb-3">
                  <label class="form-label fw-semibold">Inquiry Terms & Notes</label>
                  <textarea class="form-control" id="listing-notes" rows="2" placeholder="Minimum order quantities, transfer stipulations, delivery timelines..."></textarea>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-brand" id="save-listing-btn">
                  <i class="bi bi-check-lg me-1"></i> Save as Draft Listing
                </button>
              </div>
            </form>
          </div>
        </div>
      `;
      document.body.appendChild(modalEl);
    }

    const select = document.getElementById('listing-project-select');
    select.innerHTML = '<option value="">-- Loading eligible projects... --</option>';

    try {
      const res = await EcoPulseApi.get('/api/organization/marketplace/eligible-projects');
      const eligible = res.data || [];
      if (eligible.length === 0) {
        select.innerHTML = '<option value="" disabled selected>No eligible projects found. Upgrade a project to Verified Asset or Issued Credit with accepted audit evidence.</option>';
      } else {
        select.innerHTML = '<option value="" selected>-- Choose an eligible project --</option>' + eligible.map(p => `
          <option value="${p.id}" data-title="${escapeHtml(p.title)}" data-volume="${p.estimated_co2e_reduction || ''}">
            [${p.classification === 'issued_carbon_credit' ? 'Issued Credit' : 'Verified Asset'}] ${escapeHtml(p.title)} (Max ~${Number(p.estimated_co2e_reduction || 0).toLocaleString()} tCO2e)
          </option>
        `).join('');
      }
    } catch (err) {
      select.innerHTML = `<option value="" disabled selected>Error: ${escapeHtml(err.message)}</option>`;
    }

    const modal = new bootstrap.Modal(modalEl);
    modal.show();
  };

  const handleProjectSelectChange = (select) => {
    const option = select.options[select.selectedIndex];
    if (option && option.dataset.title) {
      const titleInput = document.getElementById('listing-title');
      if (!titleInput.value) {
        titleInput.value = option.dataset.title;
      }
      const volumeInput = document.getElementById('listing-volume');
      if (!volumeInput.value && option.dataset.volume) {
        volumeInput.value = option.dataset.volume;
      }
    }
  };

  const handleCreateListing = async (event) => {
    event.preventDefault();
    const btn = document.getElementById('save-listing-btn');
    btn.disabled = true;

    try {
      const payload = {
        project_id: parseInt(document.getElementById('listing-project-select').value),
        title: document.getElementById('listing-title').value.trim(),
        summary_description: document.getElementById('listing-summary').value.trim(),
        offered_quantity: parseFloat(document.getElementById('listing-volume').value),
        unit_price: document.getElementById('listing-price').value ? parseFloat(document.getElementById('listing-price').value) : null,
        currency: 'USD',
        contact_email: document.getElementById('listing-contact-email').value.trim(),
        contact_name: document.getElementById('listing-contact-name').value.trim(),
        terms_and_notes: document.getElementById('listing-notes').value.trim(),
      };

      const res = await EcoPulseApi.post('/api/organization/marketplace/listings', payload);
      EcoPulseNotification.success(res.message || 'Marketplace listing created successfully as draft.');

      const modalEl = document.getElementById('createListingModal');
      const modal = bootstrap.Modal.getInstance(modalEl);
      if (modal) modal.hide();

      await loadMarketplaceView();
    } catch (err) {
      EcoPulseNotification.error(err.message || 'Failed to create listing');
    } finally {
      btn.disabled = false;
    }
  };

  const submitListing = async (id) => {
    if (!confirm('Submit this listing for Super Admin marketplace moderation? Once approved, it will be visible on the public Carbon Marketing Marketplace.')) {
      return;
    }

    try {
      const res = await EcoPulseApi.post(`/api/organization/marketplace/listings/${id}/submit`);
      EcoPulseNotification.success(res.message || 'Listing submitted for review.');
      await loadMarketplaceView();
    } catch (err) {
      EcoPulseNotification.error(err.message || 'Failed to submit listing');
    }
  };

  const archiveListing = async (id) => {
    if (!confirm('Are you sure you want to archive this marketplace listing? It will no longer be listed.')) {
      return;
    }

    try {
      const res = await EcoPulseApi.post(`/api/organization/marketplace/listings/${id}/archive`);
      EcoPulseNotification.success(res.message || 'Listing archived.');
      await loadMarketplaceView();
    } catch (err) {
      EcoPulseNotification.error(err.message || 'Failed to archive listing');
    }
  };

  // ==========================================
  // BUYER INQUIRIES VIEW & THREADING
  // ==========================================

  const loadInquiriesView = async () => {
    const container = document.getElementById('view-container');
    if (!container) return;

    container.innerHTML = `
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h2 class="fw-bold mb-1">Buyer Inquiries & Leads</h2>
          <p class="text-muted mb-0">Manage incoming prospective buyer inquiries for your marketplace listings</p>
        </div>
        <button class="btn btn-sm btn-outline-secondary" onclick="EcoPulseMarketplace.loadInquiriesView()">
          <i class="bi bi-arrow-clockwise me-1"></i> Refresh
        </button>
      </div>

      <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 border-bottom">
          <h5 class="card-title mb-0 fw-bold">Inquiries Inbox</h5>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-3">Reference</th>
                  <th>Listing</th>
                  <th>Buyer Name & Org</th>
                  <th>Contact Email</th>
                  <th>Inquiry Type</th>
                  <th>Status</th>
                  <th>Received</th>
                  <th class="text-end pe-3">Actions</th>
                </tr>
              </thead>
              <tbody id="org-inquiries-tbody">
                <tr>
                  <td colspan="8" class="text-center py-4 text-muted">
                    <div class="spinner-border spinner-border-sm text-secondary me-2"></div>
                    Loading buyer inquiries...
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    `;

    try {
      const res = await EcoPulseApi.get('/api/organization/marketplace/inquiries');
      const raw = res.data;
      inquiries = Array.isArray(raw) ? raw : (raw?.data || raw?.inquiries || []);
      renderInquiriesTable();
    } catch (err) {
      document.getElementById('org-inquiries-tbody').innerHTML = `
        <tr>
          <td colspan="8" class="text-center py-4 text-danger">
            <i class="bi bi-exclamation-triangle me-1"></i> ${escapeHtml(err.message || 'Failed to load inquiries')}
          </td>
        </tr>
      `;
    }
  };

  const renderInquiriesTable = () => {
    const tbody = document.getElementById('org-inquiries-tbody');
    if (!tbody) return;

    if (inquiries.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="8" class="text-center py-5 text-muted">
            <i class="bi bi-envelope-open fs-2 d-block mb-2 text-secondary"></i>
            No buyer inquiries received yet.<br>
            <small>Once your listings are approved and published, prospective buyers can submit inquiries.</small>
          </td>
        </tr>
      `;
      return;
    }

    tbody.innerHTML = inquiries.map(inq => `
      <tr>
        <td class="ps-3 font-monospace fw-bold text-dark">${escapeHtml(inq.inquiry_reference || '—')}</td>
        <td>
          <div class="fw-semibold text-dark">${escapeHtml(inq.listing_title || 'Listing #' + inq.listing_id)}</div>
          <small class="text-muted font-monospace">${escapeHtml(inq.listing_code || '')}</small>
        </td>
        <td>
          <div class="fw-semibold text-dark">${escapeHtml(inq.buyer_name)}</div>
          <small class="text-muted">${escapeHtml(inq.buyer_organization || 'Independent Buyer')}</small>
        </td>
        <td><a href="mailto:${escapeHtml(inq.buyer_email)}" class="text-decoration-none">${escapeHtml(inq.buyer_email)}</a></td>
        <td><span class="badge bg-light text-dark border">${escapeHtml(inq.inquiry_type || 'general')}</span></td>
        <td>${getInquiryStatusBadge(inq.status)}</td>
        <td class="small text-muted">${inq.created_at ? inq.created_at.substring(0, 10) : '—'}</td>
        <td class="text-end pe-3">
          <button class="btn btn-sm btn-outline-primary" onclick="EcoPulseMarketplace.openInquiryThreadModal(${inq.id})">
            <i class="bi bi-chat-text me-1"></i> Open Thread
          </button>
        </td>
      </tr>
    `).join('');
  };

  const openInquiryThreadModal = async (id) => {
    let modalEl = document.getElementById('inquiryThreadModal');
    if (!modalEl) {
      modalEl = document.createElement('div');
      modalEl.className = 'modal fade';
      modalEl.id = 'inquiryThreadModal';
      modalEl.tabIndex = -1;
      modalEl.innerHTML = `
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <div>
                <h5 class="modal-title fw-bold" id="thread-title">Buyer Inquiry Thread</h5>
                <span class="text-muted small" id="thread-subtitle">Reference: —</span>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="thread-body">
              <div class="text-center py-4"><div class="spinner-border text-secondary"></div></div>
            </div>
            <div class="modal-footer bg-light d-flex justify-content-between">
              <div class="d-flex align-items-center gap-2">
                <label class="small fw-semibold text-muted mb-0">Status:</label>
                <select class="form-select form-select-sm" id="thread-status-select" onchange="EcoPulseMarketplace.updateInquiryStatus(this.value)">
                  <option value="new">New</option>
                  <option value="contacted">Contacted</option>
                  <option value="in_discussion">In Discussion</option>
                  <option value="closed">Closed</option>
                  <option value="rejected">Rejected</option>
                </select>
              </div>
              <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
          </div>
        </div>
      `;
      document.body.appendChild(modalEl);
    }

    const modal = new bootstrap.Modal(modalEl);
    modal.show();

    const threadBody = document.getElementById('thread-body');
    threadBody.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-secondary"></div></div>';

    try {
      const res = await EcoPulseApi.get(`/api/organization/marketplace/inquiries/${id}`);
      currentInquiry = res.data;

      document.getElementById('thread-title').textContent = `${currentInquiry.inquiry_reference} — ${currentInquiry.subject || 'Inquiry'}`;
      document.getElementById('thread-subtitle').textContent = `From ${currentInquiry.buyer_name} (${currentInquiry.buyer_email}) • Listing: ${currentInquiry.listing_title}`;
      document.getElementById('thread-status-select').value = currentInquiry.status;

      renderThreadMessages();
    } catch (err) {
      threadBody.innerHTML = `<div class="alert alert-danger">${escapeHtml(err.message || 'Failed to load inquiry')}</div>`;
    }
  };

  const renderThreadMessages = () => {
    const threadBody = document.getElementById('thread-body');
    if (!threadBody || !currentInquiry) return;

    const messages = currentInquiry.messages || [];

    threadBody.innerHTML = `
      <div class="card mb-3 bg-light border-0">
        <div class="card-body">
          <div class="d-flex justify-content-between mb-2">
            <div>
              <strong>${escapeHtml(currentInquiry.buyer_name)}</strong> 
              <span class="text-muted small">&lt;${escapeHtml(currentInquiry.buyer_email)}&gt;</span>
              ${currentInquiry.buyer_organization ? `<span class="badge bg-secondary-subtle text-dark ms-2">${escapeHtml(currentInquiry.buyer_organization)}</span>` : ''}
            </div>
            <span class="text-muted small">${currentInquiry.created_at || ''}</span>
          </div>
          <div class="text-dark bg-white p-3 rounded border">
            ${escapeHtml(currentInquiry.message)}
          </div>
        </div>
      </div>

      <h6 class="fw-bold mt-4 mb-3"><i class="bi bi-chat-dots me-1"></i> Conversation Thread</h6>

      <div class="d-flex flex-column gap-3 mb-4" id="thread-messages-list">
        ${messages.length === 0 ? `
          <div class="text-muted small text-center py-2">No reply messages yet. Send a response below.</div>
        ` : messages.map(msg => `
          <div class="p-3 rounded border ${msg.sender_type === 'organization_member' || msg.sender_type === 'organization' ? 'bg-success-subtle border-success-subtle ms-4' : 'bg-white border-light-subtle me-4'}">
            <div class="d-flex justify-content-between small text-muted mb-1">
              <strong>${msg.sender_type === 'organization_member' || msg.sender_type === 'organization' ? '<i class="bi bi-building me-1"></i> Organization Reply' : '<i class="bi bi-person me-1"></i> Buyer Message'}</strong>
              <span>${msg.created_at || ''}</span>
            </div>
            <div class="text-dark">${escapeHtml(msg.message_body || msg.message || '')}</div>
          </div>
        `).join('')}
      </div>

      <form id="reply-message-form" onsubmit="EcoPulseMarketplace.sendThreadReply(event)">
        <div class="mb-2">
          <label class="form-label small fw-semibold">Send Reply to Buyer</label>
          <textarea class="form-control" id="reply-message-body" rows="3" required placeholder="Write your response regarding pricing, vintage, documentation, or contract parameters..."></textarea>
        </div>
        <div class="text-end">
          <button type="submit" class="btn btn-brand btn-sm" id="send-reply-btn">
            <i class="bi bi-send me-1"></i> Send Reply
          </button>
        </div>
      </form>
    `;
  };

  const sendThreadReply = async (event) => {
    event.preventDefault();
    if (!currentInquiry) return;

    const textarea = document.getElementById('reply-message-body');
    const btn = document.getElementById('send-reply-btn');
    const msg = textarea.value.trim();
    if (!msg) return;

    btn.disabled = true;

    try {
      const res = await EcoPulseApi.post(`/api/organization/marketplace/inquiries/${currentInquiry.id}/messages`, {
        message: msg,
      });

      EcoPulseNotification.success('Reply recorded.');
      textarea.value = '';

      const updated = await EcoPulseApi.get(`/api/organization/marketplace/inquiries/${currentInquiry.id}`);
      currentInquiry = updated.data;
      renderThreadMessages();
      await loadInquiriesView();
    } catch (err) {
      EcoPulseNotification.error(err.message || 'Failed to send reply');
    } finally {
      btn.disabled = false;
    }
  };

  const updateInquiryStatus = async (status) => {
    if (!currentInquiry) return;

    try {
      await EcoPulseApi.patch(`/api/organization/marketplace/inquiries/${currentInquiry.id}/status`, {
        status: status,
      });
      EcoPulseNotification.success(`Inquiry status updated to ${status}.`);
      currentInquiry.status = status;
      await loadInquiriesView();
    } catch (err) {
      EcoPulseNotification.error(err.message || 'Failed to update status');
    }
  };

  // ==========================================
  // SUPER ADMIN MODERATION QUEUE VIEW
  // ==========================================

  const loadModerationView = async () => {
    const container = document.getElementById('view-container');
    if (!container) return;

    container.innerHTML = `
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h2 class="fw-bold mb-1"><i class="bi bi-shield-check text-success me-2"></i>Marketplace Moderation Queue</h2>
          <p class="text-muted mb-0">Super Admin governance for reviewing and verifying carbon marketing listings before publication</p>
        </div>
        <button class="btn btn-sm btn-outline-secondary" onclick="EcoPulseMarketplace.loadModerationView()">
          <i class="bi bi-arrow-clockwise me-1"></i> Refresh Queue
        </button>
      </div>

      <ul class="nav nav-tabs mb-4" id="moderationTabs">
        <li class="nav-item">
          <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-pending">
            <i class="bi bi-clock-history me-1"></i> Pending Review Queue
          </button>
        </li>
        <li class="nav-item">
          <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-audit" onclick="EcoPulseMarketplace.loadAuditHistory()">
            <i class="bi bi-journal-text me-1"></i> Moderation Audit Trail
          </button>
        </li>
      </ul>

      <div class="tab-content">
        <div class="tab-pane fade show active" id="tab-pending">
          <div class="card shadow-sm border-0">
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th class="ps-3">Listing Code</th>
                      <th>Organization</th>
                      <th>Title & Project</th>
                      <th>Tier</th>
                      <th>Offered Volume</th>
                      <th>Submitted Date</th>
                      <th class="text-end pe-3">Governance Actions</th>
                    </tr>
                  </thead>
                  <tbody id="moderation-queue-tbody">
                    <tr>
                      <td colspan="7" class="text-center py-4 text-muted">
                        <div class="spinner-border spinner-border-sm text-secondary me-2"></div>
                        Loading moderation queue...
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="tab-pane fade" id="tab-audit">
          <div class="card shadow-sm border-0">
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th class="ps-3">Date</th>
                      <th>Listing</th>
                      <th>Action</th>
                      <th>Moderator</th>
                      <th>Comments / Rejection Reason</th>
                    </tr>
                  </thead>
                  <tbody id="moderation-audit-tbody">
                    <tr>
                      <td colspan="5" class="text-center py-4 text-muted">Click to load audit trail</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    `;

    try {
      const res = await EcoPulseApi.get('/api/admin/marketplace/listings/pending');
      const raw = res.data;
      const queue = Array.isArray(raw) ? raw : (raw?.data || raw?.listings || []);
      renderModerationQueue(queue);
    } catch (err) {
      document.getElementById('moderation-queue-tbody').innerHTML = `
        <tr>
          <td colspan="7" class="text-center py-4 text-danger">
            <i class="bi bi-exclamation-triangle me-1"></i> ${escapeHtml(err.message || 'Failed to load queue')}
          </td>
        </tr>
      `;
    }
  };

  const renderModerationQueue = (rawQueue) => {
    const tbody = document.getElementById('moderation-queue-tbody');
    if (!tbody) return;

    const queue = Array.isArray(rawQueue) ? rawQueue : (rawQueue?.data || rawQueue?.listings || []);

    if (queue.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="7" class="text-center py-5 text-muted">
            <i class="bi bi-check2-circle fs-2 d-block mb-2 text-success"></i>
            Moderation queue is empty.<br>
            <small>All submitted listings have been reviewed.</small>
          </td>
        </tr>
      `;
      return;
    }

    tbody.innerHTML = queue.map(l => `
      <tr>
        <td class="ps-3 font-monospace fw-bold text-dark">${escapeHtml(l.listing_code || '—')}</td>
        <td>
          <div class="fw-semibold text-dark">${escapeHtml(l.organization_name || 'Org #' + l.organization_id)}</div>
          <small class="text-muted">ID: ${l.organization_id}</small>
        </td>
        <td>
          <div class="fw-semibold text-dark">${escapeHtml(l.listing_title || l.title || 'Untitled Listing')}</div>
          <small class="text-muted">${escapeHtml(l.orig_project_title || l.project_title || '')}</small>
        </td>
        <td>
          ${(l.eligible_classification || l.classification_tier) === 'issued_carbon_credit' 
            ? '<span class="badge bg-success text-white">Issued Credit</span>' 
            : '<span class="badge bg-info text-dark">Verified Asset</span>'}
        </td>
        <td>
          <strong>${Number(l.total_volume_tco2e || l.available_volume_tco2e || l.offered_quantity || 0).toLocaleString()}</strong> tCO2e
        </td>
        <td class="small text-muted">${l.submitted_at ? l.submitted_at.substring(0, 16) : '—'}</td>
        <td class="text-end pe-3">
          <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-success" onclick="EcoPulseMarketplace.approveListing(${l.id})">
              <i class="bi bi-check-lg me-1"></i> Approve
            </button>
            <button class="btn btn-outline-danger" onclick="EcoPulseMarketplace.promptRejectListing(${l.id})">
              <i class="bi bi-x-lg me-1"></i> Reject
            </button>
          </div>
        </td>
      </tr>
    `).join('');
  };

  const approveListing = async (id) => {
    const comments = prompt('Enter approval notes or verification confirmation (optional):', 'Meets platform governance and documentation criteria.');
    if (comments === null) return;

    try {
      const res = await EcoPulseApi.post(`/api/admin/marketplace/listings/${id}/approve`, {
        comments: comments,
      });
      EcoPulseNotification.success(res.message || 'Listing approved and published to public marketplace.');
      await loadModerationView();
    } catch (err) {
      EcoPulseNotification.error(err.message || 'Approval failed');
    }
  };

  const promptRejectListing = async (id) => {
    const reason = prompt('MANDATORY: Enter detailed rejection reason explaining governance or documentation deficiency:');
    if (reason === null) return;
    if (!reason.trim()) {
      alert('Rejection reason is required by EcoPulse Governance.');
      return;
    }

    try {
      const res = await EcoPulseApi.post(`/api/admin/marketplace/listings/${id}/reject`, {
        rejection_reason: reason.trim(),
      });
      EcoPulseNotification.success(res.message || 'Listing rejected.');
      await loadModerationView();
    } catch (err) {
      EcoPulseNotification.error(err.message || 'Rejection failed');
    }
  };

  const loadAuditHistory = async () => {
    const tbody = document.getElementById('moderation-audit-tbody');
    if (!tbody) return;

    tbody.innerHTML = `
      <tr>
        <td colspan="5" class="text-center py-4 text-muted">
          <div class="spinner-border spinner-border-sm text-secondary me-2"></div>
          Loading audit trail...
        </td>
      </tr>
    `;

    try {
      const res = await EcoPulseApi.get('/api/admin/marketplace/moderation-history');
      const raw = res.data;
      const audit = Array.isArray(raw) ? raw : (raw?.data || raw?.history || []);

      if (audit.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="5" class="text-center py-4 text-muted">No moderation history recorded yet.</td>
          </tr>
        `;
        return;
      }

      tbody.innerHTML = audit.map(a => `
        <tr>
          <td class="ps-3 small text-muted">${a.created_at || '—'}</td>
          <td>
            <span class="font-monospace fw-bold">${escapeHtml(a.listing_code || 'ID:' + a.listing_id)}</span>
            <div class="small text-muted">${escapeHtml(a.listing_title || '')}</div>
          </td>
          <td>
            ${a.action === 'approved' ? '<span class="badge bg-success">Approved</span>' : ''}
            ${a.action === 'rejected' ? '<span class="badge bg-danger">Rejected</span>' : ''}
            ${a.action === 'suspended' ? '<span class="badge bg-dark">Suspended</span>' : ''}
            ${a.action === 'restored' ? '<span class="badge bg-info">Restored</span>' : ''}
          </td>
          <td>
            <div class="fw-semibold text-dark">${escapeHtml(a.reviewer_name || 'Admin')}</div>
            <small class="text-muted">${escapeHtml(a.reviewer_email || '')}</small>
          </td>
          <td class="small text-dark">${escapeHtml(a.comments || a.rejection_reason || '—')}</td>
        </tr>
      `).join('');
    } catch (err) {
      tbody.innerHTML = `
        <tr>
          <td colspan="5" class="text-center py-4 text-danger">
            <i class="bi bi-exclamation-triangle me-1"></i> ${escapeHtml(err.message || 'Failed to load audit trail')}
          </td>
        </tr>
      `;
    }
  };

  return {
    loadMarketplaceView,
    openCreateListingModal,
    handleProjectSelectChange,
    handleCreateListing,
    submitListing,
    archiveListing,
    loadInquiriesView,
    openInquiryThreadModal,
    sendThreadReply,
    updateInquiryStatus,
    loadModerationView,
    approveListing,
    promptRejectListing,
    loadAuditHistory,
  };
})();
