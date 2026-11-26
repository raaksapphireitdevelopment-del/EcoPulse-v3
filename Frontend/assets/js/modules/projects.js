/**
 * EcoPulse MVP V3 - Environmental Projects, Evidence & Readiness Module
 */

const EcoPulseProjects = (() => {
  let projectsList = [];
  let currentProject = null;
  let activeFilters = {
    classification: '',
    domain_profile_id: '',
    readiness_stage: '',
    search: '',
  };

  /**
   * Escape HTML helper
   */
  const escapeHtml = (str) => {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  };

  /**
   * Classification Tier Badges
   */
  const getClassificationBadge = (classification) => {
    switch (classification) {
      case 'operational_reduction':
        return '<span class="badge bg-secondary text-white"><i class="bi bi-gear-fill me-1"></i>Operational Reduction</span>';
      case 'project_candidate':
        return '<span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Project Candidate</span>';
      case 'verified_environmental_asset':
        return '<span class="badge bg-info text-dark"><i class="bi bi-patch-check-fill me-1"></i>Verified Asset</span>';
      case 'issued_carbon_credit':
        return '<span class="badge bg-success text-white"><i class="bi bi-award-fill me-1"></i>Issued Credit</span>';
      default:
        return `<span class="badge bg-light text-dark">${escapeHtml(classification)}</span>`;
    }
  };

  /**
   * Readiness Stage Badges
   */
  const getStageBadge = (stage) => {
    switch (stage) {
      case 'initial_assessment':
        return '<span class="badge bg-light text-secondary border">Initial Assessment</span>';
      case 'documentation_required':
        return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Documentation Required</span>';
      case 'under_technical_review':
        return '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Under Technical Review</span>';
      case 'methodology_assessment':
        return '<span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">Methodology Assessment</span>';
      case 'verification_preparation':
        return '<span class="badge bg-primary-subtle text-primary border border-primary-subtle">Verification Prep</span>';
      case 'independently_verified':
        return '<span class="badge bg-info text-dark">Independently Verified</span>';
      case 'issued':
        return '<span class="badge bg-success text-white">Issued on Registry</span>';
      default:
        return `<span class="badge bg-light text-dark">${escapeHtml(stage || 'Unassessed')}</span>`;
    }
  };

  /**
   * Document Review Status Badge
   */
  const getReviewStatusBadge = (status) => {
    switch (status) {
      case 'pending':
        return '<span class="badge bg-secondary-subtle text-secondary border">Pending Review</span>';
      case 'under_review':
        return '<span class="badge bg-warning-subtle text-warning-emphasis border">Under Review</span>';
      case 'accepted':
        return '<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-circle me-1"></i>Accepted</span>';
      case 'rejected':
        return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="bi bi-x-circle me-1"></i>Rejected</span>';
      case 'requires_revision':
        return '<span class="badge bg-warning text-dark"><i class="bi bi-pencil-square me-1"></i>Revision Required</span>';
      default:
        return `<span class="badge bg-light text-dark">${escapeHtml(status)}</span>`;
    }
  };

  /**
   * Load Projects Dashboard View
   */
  const loadProjectsView = async () => {
    const container = document.getElementById('view-container');
    if (!container) return;

    container.innerHTML = `
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h2 class="fw-bold mb-1">Environmental Projects & Evidence</h2>
          <p class="text-secondary small mb-0">
            Four-tier project classification, secure evidence management, and multi-dimensional readiness assessment.
          </p>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-success btn-sm" onclick="EcoPulseProjects.exportCsv()">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export Projects (CSV)
          </button>
          <button class="btn btn-outline-secondary btn-sm" onclick="EcoPulseProjects.refresh()">
            <i class="bi bi-arrow-clockwise me-1"></i> Refresh
          </button>
          <button class="btn btn-brand btn-sm" onclick="EcoPulseProjects.openCreateModal()">
            <i class="bi bi-plus-circle me-1"></i> Register Project
          </button>
        </div>
      </div>

      <!-- Regulatory Notice Banner -->
      <div class="alert alert-light border border-secondary border-opacity-25 rounded-3 py-2 px-3 mb-4 small d-flex align-items-center gap-2">
        <i class="bi bi-shield-check text-success fs-5"></i>
        <div class="text-secondary">
          <strong>Environmental Asset Governance:</strong> EcoPulse is a carbon intelligence platform and does not issue carbon credits or independently certify projects. Operational reductions reflect internal energy efficiency and cannot be traded as market credits without formal registry issuance.
        </div>
      </div>

      <!-- KPI Metric Cards -->
      <div class="row g-3 mb-4" id="project-kpi-cards">
        <div class="col-md-3">
          <div class="card border-0 shadow-sm rounded-3 p-3">
            <span class="text-secondary small">Total Projects</span>
            <h3 class="fw-bold text-dark mb-0 mt-1" id="kpi-total-projects">-</h3>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card border-0 shadow-sm rounded-3 p-3 border-start border-4 border-secondary">
            <span class="text-secondary small">Operational Reductions</span>
            <h3 class="fw-bold text-secondary mb-0 mt-1" id="kpi-operational-reductions">-</h3>
            <span class="text-muted" style="font-size: 0.7rem;">Internal Efficiency only</span>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card border-0 shadow-sm rounded-3 p-3 border-start border-4 border-warning">
            <span class="text-secondary small">Project Candidates</span>
            <h3 class="fw-bold text-warning mb-0 mt-1" id="kpi-candidates">-</h3>
            <span class="text-muted" style="font-size: 0.7rem;">Under feasibility evaluation</span>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card border-0 shadow-sm rounded-3 p-3 border-start border-4 border-info">
            <span class="text-secondary small">Verified Assets & Credits</span>
            <h3 class="fw-bold text-info mb-0 mt-1" id="kpi-verified-assets">-</h3>
            <span class="text-muted" style="font-size: 0.7rem;">Audited & Issued</span>
          </div>
        </div>
      </div>

      <!-- Filter Bar -->
      <div class="card border-0 shadow-sm rounded-3 p-3 mb-4">
        <div class="row g-2 align-items-center">
          <div class="col-md-3">
            <input type="text" class="form-control form-control-sm" id="project-search-input"
                   placeholder="Search project title, code, methodology..." oninput="EcoPulseProjects.handleSearch(this.value)">
          </div>
          <div class="col-md-3">
            <select class="form-select form-select-sm" id="project-classification-filter" onchange="EcoPulseProjects.handleFilterChange('classification', this.value)">
              <option value="">All Classifications</option>
              <option value="operational_reduction">Operational Reduction</option>
              <option value="project_candidate">Project Candidate</option>
              <option value="verified_environmental_asset">Verified Environmental Asset</option>
              <option value="issued_carbon_credit">Issued Carbon Credit</option>
            </select>
          </div>
          <div class="col-md-3">
            <select class="form-select form-select-sm" id="project-stage-filter" onchange="EcoPulseProjects.handleFilterChange('readiness_stage', this.value)">
              <option value="">All Readiness Stages</option>
              <option value="initial_assessment">Initial Assessment</option>
              <option value="documentation_required">Documentation Required</option>
              <option value="under_technical_review">Under Technical Review</option>
              <option value="methodology_assessment">Methodology Assessment</option>
              <option value="verification_preparation">Verification Preparation</option>
              <option value="independently_verified">Independently Verified</option>
              <option value="issued">Issued</option>
            </select>
          </div>
          <div class="col-md-3 text-end">
            <button class="btn btn-outline-secondary btn-sm" onclick="EcoPulseProjects.resetFilters()">
              <i class="bi bi-x-circle me-1"></i> Reset Filters
            </button>
          </div>
        </div>
      </div>

      <!-- Projects Table -->
      <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-secondary small text-uppercase">
              <tr>
                <th class="ps-3">Project Title & Code</th>
                <th>Classification Tier</th>
                <th>Domain & Facility</th>
                <th>Annual Mitigation</th>
                <th>Readiness Score & Stage</th>
                <th>Evidence Documents</th>
                <th class="text-end pe-3">Actions</th>
              </tr>
            </thead>
            <tbody id="projects-table-body">
              <tr>
                <td colspan="7" class="text-center py-4 text-muted">
                  <div class="spinner-border spinner-border-sm text-success me-2"></div> Loading projects...
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    `;

    await fetchProjects();
  };

  /**
   * Fetch projects from API
   */
  const fetchProjects = async () => {
    try {
      const queryParams = new URLSearchParams();
      if (activeFilters.classification) queryParams.set('classification', activeFilters.classification);
      if (activeFilters.domain_profile_id) queryParams.set('domain_profile_id', activeFilters.domain_profile_id);
      if (activeFilters.readiness_stage) queryParams.set('readiness_stage', activeFilters.readiness_stage);
      if (activeFilters.search) queryParams.set('search', activeFilters.search);

      const res = await EcoPulseApi.get(`/api/projects?${queryParams.toString()}`);
      projectsList = res?.data?.items || [];

      updateKPIs(projectsList);
      renderProjectsTable(projectsList);
    } catch (err) {
      console.error('Error fetching projects:', err);
      const tbody = document.getElementById('projects-table-body');
      if (tbody) {
        tbody.innerHTML = `
          <tr>
            <td colspan="7" class="text-center text-danger py-4">
              <i class="bi bi-exclamation-triangle me-1"></i> ${escapeHtml(err.message || 'Failed to load projects.')}
            </td>
          </tr>
        `;
      }
    }
  };

  /**
   * Update KPI Counters
   */
  const updateKPIs = (items) => {
    const totalEl = document.getElementById('kpi-total-projects');
    const opEl = document.getElementById('kpi-operational-reductions');
    const candEl = document.getElementById('kpi-candidates');
    const verEl = document.getElementById('kpi-verified-assets');

    if (!totalEl) return;

    let opCount = 0;
    let candCount = 0;
    let verCount = 0;

    items.forEach(p => {
      if (p.classification === 'operational_reduction') opCount++;
      else if (p.classification === 'project_candidate') candCount++;
      else if (p.classification === 'verified_environmental_asset' || p.classification === 'issued_carbon_credit') verCount++;
    });

    totalEl.textContent = items.length;
    opEl.textContent = opCount;
    candEl.textContent = candCount;
    verEl.textContent = verCount;
  };

  /**
   * Render Projects Table Rows
   */
  const renderProjectsTable = (items) => {
    const tbody = document.getElementById('projects-table-body');
    if (!tbody) return;

    if (items.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="7" class="text-center py-5 text-muted">
            <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
            No environmental projects found matching the selected criteria.
          </td>
        </tr>
      `;
      return;
    }

    tbody.innerHTML = items.map(p => {
      const score = p.overall_readiness_percentage !== null ? parseFloat(p.overall_readiness_percentage) : null;
      let scoreDisplay = '<span class="text-muted small">Not Assessed</span>';

      if (score !== null) {
        const barColor = score >= 85 ? 'bg-success' : (score >= 60 ? 'bg-primary' : (score >= 40 ? 'bg-warning' : 'bg-danger'));
        scoreDisplay = `
          <div class="d-flex align-items-center gap-2" style="min-width: 130px;">
            <div class="progress flex-grow-1" style="height: 6px;">
              <div class="progress-bar ${barColor}" role="progressbar" style="width: ${score}%;"></div>
            </div>
            <span class="small fw-bold">${score.toFixed(0)}%</span>
          </div>
          <div class="mt-1">${getStageBadge(p.readiness_stage)}</div>
        `;
      } else {
        scoreDisplay += `<div class="mt-1">${getStageBadge(p.readiness_stage)}</div>`;
      }

      const docCount = parseInt(p.document_count || 0, 10);
      const acceptedCount = parseInt(p.accepted_document_count || 0, 10);

      return `
        <tr>
          <td class="ps-3">
            <a href="#" class="fw-bold text-dark text-decoration-none" onclick="EcoPulseProjects.openProjectDetail(${p.id}); return false;">
              ${escapeHtml(p.project_title)}
            </a>
            <div class="text-secondary small font-monospace">${escapeHtml(p.project_code || 'PRJ-AUTO')}</div>
          </td>
          <td>${getClassificationBadge(p.classification)}</td>
          <td>
            <div class="small fw-semibold text-dark">${escapeHtml(p.domain_name || 'General')}</div>
            <div class="text-secondary small">${escapeHtml(p.facility_name || 'All Facilities')}</div>
          </td>
          <td>
            <strong class="text-dark">${parseFloat(p.estimated_annual_mitigation_tco2e || 0).toLocaleString()}</strong>
            <span class="text-secondary small"> tCO2e/yr</span>
          </td>
          <td>${scoreDisplay}</td>
          <td>
            <span class="badge ${acceptedCount > 0 ? 'bg-success-subtle text-success' : 'bg-light text-secondary'} border">
              <i class="bi bi-file-earmark-text me-1"></i> ${docCount} (${acceptedCount} verified)
            </span>
          </td>
          <td class="text-end pe-3">
            <div class="dropdown">
              <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                Actions
              </button>
              <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                <li>
                  <a class="dropdown-item" href="#" onclick="EcoPulseProjects.openProjectDetail(${p.id}, 'overview'); return false;">
                    <i class="bi bi-eye me-2 text-primary"></i> View Project Dossier
                  </a>
                </li>
                <li>
                  <a class="dropdown-item" href="#" onclick="EcoPulseProjects.openProjectDetail(${p.id}, 'evidence'); return false;">
                    <i class="bi bi-file-earmark-arrow-up me-2 text-info"></i> Manage Evidence Docs
                  </a>
                </li>
                <li>
                  <a class="dropdown-item" href="#" onclick="EcoPulseProjects.openProjectDetail(${p.id}, 'readiness'); return false;">
                    <i class="bi bi-speedometer2 me-2 text-success"></i> Readiness Assessment
                  </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                  <a class="dropdown-item" href="#" onclick="EcoPulseProjects.openClassificationModal(${p.id}); return false;">
                    <i class="bi bi-shield-lock me-2 text-warning"></i> Change Classification
                  </a>
                </li>
              </ul>
            </div>
          </td>
        </tr>
      `;
    }).join('');
  };

  /**
   * Filter Handlers
   */
  const handleFilterChange = (filterKey, value) => {
    activeFilters[filterKey] = value;
    fetchProjects();
  };

  const handleSearch = (val) => {
    activeFilters.search = val.trim();
    fetchProjects();
  };

  const resetFilters = () => {
    activeFilters = {
      classification: '',
      domain_profile_id: '',
      readiness_stage: '',
      search: '',
    };
    const sInput = document.getElementById('project-search-input');
    const cSelect = document.getElementById('project-classification-filter');
    const stSelect = document.getElementById('project-stage-filter');
    if (sInput) sInput.value = '';
    if (cSelect) cSelect.value = '';
    if (stSelect) stSelect.value = '';
    fetchProjects();
  };

  const refresh = () => fetchProjects();

  /**
   * Open Registration / Edit Modal
   */
  const openCreateModal = async () => {
    // Fetch domain profiles for dropdown
    let domains = [];
    try {
      const res = await EcoPulseApi.get('/api/domains/profiles');
      domains = res?.data || [];
    } catch (e) {
      console.warn('Could not load domain profiles:', e);
    }

    const modalHtml = `
      <div class="modal fade" id="projectCreateModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
          <div class="modal-content border-0 shadow">
            <form onsubmit="EcoPulseProjects.submitCreateProject(event)">
              <div class="modal-header">
                <h5 class="modal-title fw-bold">Register Environmental Project</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <!-- Classification Tier Selector -->
                <div class="mb-3">
                  <label class="form-label fw-bold small text-secondary">PROJECT CLASSIFICATION TIER <span class="text-danger">*</span></label>
                  <select class="form-select" id="p-classification" required onchange="EcoPulseProjects.onClassificationChange(this.value)">
                    <option value="operational_reduction" selected>1. Operational Reduction (Internal Efficiency — Non-Marketable)</option>
                    <option value="project_candidate">2. Project Candidate (Under Feasibility & Methodology Assessment)</option>
                    <option value="verified_environmental_asset">3. Verified Environmental Asset (Independent Third-Party Verification)</option>
                    <option value="issued_carbon_credit">4. Issued Carbon Credit (Registry Issuance with Verified Serial)</option>
                  </select>
                  <div class="form-text small" id="p-classification-help">
                    Operational reductions represent internal efficiency improvements and cannot claim registry serials or carbon credit status.
                  </div>
                </div>

                <div class="row g-3 mb-3">
                  <div class="col-md-8">
                    <label class="form-label small">Project Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="p-title" required placeholder="e.g. Hospital Boiler Heat Recovery & Electrification">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label small">Domain Profile <span class="text-danger">*</span></label>
                    <select class="form-select" id="p-domain" required>
                      ${domains.map(d => `<option value="${d.id}">${escapeHtml(d.domain_name || d.name)}</option>`).join('')}
                    </select>
                  </div>
                </div>

                <div class="row g-3 mb-3">
                  <div class="col-md-6">
                    <label class="form-label small">Project Category</label>
                    <select class="form-select" id="p-category">
                      <option value="energy_efficiency" selected>Energy Efficiency</option>
                      <option value="renewable_energy">Renewable Energy</option>
                      <option value="fuel_switch">Fuel Switching</option>
                      <option value="methane_capture">Methane Capture</option>
                      <option value="waste_to_energy">Waste to Energy</option>
                      <option value="afforestation">Afforestation / Reforestation</option>
                      <option value="industrial_process">Industrial Process Optimization</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small">Estimated Annual Mitigation (tCO2e/yr) <span class="text-danger">*</span></label>
                    <input type="number" step="0.0001" min="0.0001" class="form-control" id="p-mitigation" required placeholder="e.g. 850.50">
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label small">Baseline Scenario Description <span class="text-danger">*</span></label>
                  <textarea class="form-control" id="p-baseline" rows="2" required placeholder="Describe business-as-usual operations and historical emissions activity..."></textarea>
                </div>

                <div class="row g-3 mb-3">
                  <div class="col-md-6">
                    <label class="form-label small">Crediting Period Start <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="p-credit-start" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small">Crediting Period End <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="p-credit-end" required>
                  </div>
                </div>

                <div class="row g-3 mb-3">
                  <div class="col-md-6">
                    <label class="form-label small">Methodology Standard</label>
                    <input type="text" class="form-control" id="p-methodology" placeholder="e.g. Verra VM0007, Gold Standard TPDDTEC, CDM AMS-III.C">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small">Third-Party Verifier (if applicable)</label>
                    <input type="text" class="form-control" id="p-verifier" placeholder="e.g. TÜV SÜD, DNV, SGS, Bureau Veritas">
                  </div>
                </div>

                <!-- Conditional Registry Fields -->
                <div class="row g-3 mb-3 d-none" id="registry-fields-group">
                  <div class="col-md-6">
                    <label class="form-label small">Carbon Registry Name</label>
                    <input type="text" class="form-control" id="p-registry-name" placeholder="e.g. Verra Registry, Gold Standard Registry">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small">Registry Serial Number / Reference ID</label>
                    <input type="text" class="form-control" id="p-registry-serial" placeholder="e.g. VCS-1234-2024-5678">
                  </div>
                </div>

                <div class="row g-3 mb-3">
                  <div class="col-md-6">
                    <label class="form-label small">Country / Jurisdiction</label>
                    <input type="text" class="form-control" id="p-country" value="Global">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small">Project Lead / Owner Name</label>
                    <input type="text" class="form-control" id="p-owner" placeholder="e.g. Dr. Sarah Jenkins">
                  </div>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-brand btn-sm" id="p-submit-btn">Register Project</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    `;

    // Remove old modal if exists
    const old = document.getElementById('projectCreateModal');
    if (old) old.remove();

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('projectCreateModal'));
    modal.show();
  };

  /**
   * Handle classification change in modal
   */
  const onClassificationChange = (tier) => {
    const help = document.getElementById('p-classification-help');
    const regGroup = document.getElementById('registry-fields-group');

    if (tier === 'operational_reduction') {
      help.innerHTML = '<span class="text-secondary">Operational reductions represent internal efficiency improvements and cannot claim registry serials or carbon credit status.</span>';
      if (regGroup) regGroup.classList.add('d-none');
    } else if (tier === 'project_candidate') {
      help.innerHTML = '<span class="text-warning">Undergoing methodology & additionality evaluation. Must not be represented as verified or issued.</span>';
      if (regGroup) regGroup.classList.add('d-none');
    } else if (tier === 'verified_environmental_asset') {
      help.innerHTML = '<span class="text-info">Requires third-party validation/verification statement from an accredited audit body.</span>';
      if (regGroup) regGroup.classList.remove('d-none');
    } else if (tier === 'issued_carbon_credit') {
      help.innerHTML = '<span class="text-success">Requires authentic registry name, verified serial numbers, and completed issuance record.</span>';
      if (regGroup) regGroup.classList.remove('d-none');
    }
  };

  /**
   * Submit Create Project
   */
  const submitCreateProject = async (e) => {
    e.preventDefault();
    const btn = document.getElementById('p-submit-btn');
    if (btn) btn.disabled = true;

    try {
      const payload = {
        project_title: document.getElementById('p-title').value.trim(),
        domain_profile_id: parseInt(document.getElementById('p-domain').value, 10),
        classification: document.getElementById('p-classification').value,
        project_category: document.getElementById('p-category').value,
        estimated_annual_mitigation_tco2e: parseFloat(document.getElementById('p-mitigation').value),
        baseline_scenario_description: document.getElementById('p-baseline').value.trim(),
        crediting_period_start: document.getElementById('p-credit-start').value,
        crediting_period_end: document.getElementById('p-credit-end').value,
        methodology: document.getElementById('p-methodology').value.trim() || null,
        third_party_verifier: document.getElementById('p-verifier').value.trim() || null,
        registry_name: document.getElementById('p-registry-name')?.value.trim() || null,
        registry_serial_or_id: document.getElementById('p-registry-serial')?.value.trim() || null,
        location_country: document.getElementById('p-country').value.trim() || 'Global',
        project_owner_name: document.getElementById('p-owner').value.trim() || null,
      };

      const res = await EcoPulseApi.post('/api/projects', payload);
      const modalEl = document.getElementById('projectCreateModal');
      if (modalEl) {
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
      }

      await fetchProjects();
    } catch (err) {
      alert(err.message || 'Failed to register project.');
    } finally {
      if (btn) btn.disabled = false;
    }
  };

  /**
   * Open Classification Transition Modal
   */
  const openClassificationModal = (projectId) => {
    const project = projectsList.find(p => p.id === projectId);
    if (!project) return;

    const modalHtml = `
      <div class="modal fade" id="classificationChangeModal" tabindex="-1">
        <div class="modal-dialog">
          <div class="modal-content border-0 shadow">
            <form onsubmit="EcoPulseProjects.submitClassificationChange(event, ${projectId})">
              <div class="modal-header">
                <h5 class="modal-title fw-bold">Update Project Classification Tier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <div class="mb-3">
                  <span class="text-secondary small d-block">Current Classification:</span>
                  <div>${getClassificationBadge(project.classification)}</div>
                </div>

                <div class="mb-3">
                  <label class="form-label small fw-bold">New Target Classification Tier <span class="text-danger">*</span></label>
                  <select class="form-select" id="new-tier" required>
                    <option value="operational_reduction" ${project.classification === 'operational_reduction' ? 'selected' : ''}>Operational Reduction (Internal Efficiency)</option>
                    <option value="project_candidate" ${project.classification === 'project_candidate' ? 'selected' : ''}>Project Candidate (Feasibility Assessment)</option>
                    <option value="verified_environmental_asset" ${project.classification === 'verified_environmental_asset' ? 'selected' : ''}>Verified Environmental Asset (Third-Party Audited)</option>
                    <option value="issued_carbon_credit" ${project.classification === 'issued_carbon_credit' ? 'selected' : ''}>Issued Carbon Credit (Serial Issued)</option>
                  </select>
                </div>

                <div class="mb-3">
                  <label class="form-label small fw-bold">Mandatory Justification / Reason <span class="text-danger">*</span></label>
                  <textarea class="form-control" id="tier-reason" rows="3" required placeholder="State regulatory and evidence basis for this classification change..."></textarea>
                </div>

                <div class="mb-3">
                  <label class="form-label small">Supporting Evidence Reference Notes</label>
                  <input type="text" class="form-control" id="tier-notes" placeholder="e.g. Audit Statement Doc #12, Verra Registry link">
                </div>

                <div class="alert alert-warning small py-2">
                  <i class="bi bi-shield-exclamation me-1"></i> Every classification change is permanently recorded in the immutable audit history.
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-brand btn-sm">Confirm Transition</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    `;

    const old = document.getElementById('classificationChangeModal');
    if (old) old.remove();

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('classificationChangeModal'));
    modal.show();
  };

  /**
   * Submit Classification Change
   */
  const submitClassificationChange = async (e, projectId) => {
    e.preventDefault();
    try {
      const payload = {
        classification: document.getElementById('new-tier').value,
        reason: document.getElementById('tier-reason').value.trim(),
        evidence_notes: document.getElementById('tier-notes').value.trim() || null,
      };

      await EcoPulseApi.post(`/api/projects/${projectId}/classification`, payload);

      const modalEl = document.getElementById('classificationChangeModal');
      if (modalEl) {
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
      }

      await fetchProjects();
    } catch (err) {
      alert(err.message || 'Failed to update classification.');
    }
  };

  /**
   * Open Detailed Project Dossier View
   */
  const openProjectDetail = async (projectId, defaultTab = 'overview') => {
    try {
      const res = await EcoPulseApi.get(`/api/projects/${projectId}`);
      currentProject = res?.data;
      if (!currentProject) return;

      renderDetailModal(currentProject, defaultTab);
    } catch (err) {
      alert(err.message || 'Failed to load project details.');
    }
  };

  /**
   * Render Detail Modal
   */
  const renderDetailModal = (p, activeTab = 'overview') => {
    const modalHtml = `
      <div class="modal fade" id="projectDetailModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
          <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom">
              <div>
                <div class="d-flex align-items-center gap-2">
                  <h5 class="modal-title fw-bold mb-0">${escapeHtml(p.project_title)}</h5>
                  ${getClassificationBadge(p.classification)}
                </div>
                <div class="text-secondary small mt-1">
                  Code: <span class="font-monospace">${escapeHtml(p.project_code || 'PRJ-AUTO')}</span> |
                  Domain: <strong>${escapeHtml(p.domain_name || 'General')}</strong> |
                  Facility: <strong>${escapeHtml(p.facility_name || 'All Facilities')}</strong>
                </div>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
              <!-- Dossier Navigation Tabs -->
              <ul class="nav nav-tabs px-3 pt-2 bg-light border-bottom" role="tablist">
                <li class="nav-item">
                  <button class="nav-link ${activeTab === 'overview' ? 'active' : ''}" data-bs-toggle="tab" data-bs-target="#tab-overview">
                    <i class="bi bi-info-circle me-1"></i> Overview & Metrics
                  </button>
                </li>
                <li class="nav-item">
                  <button class="nav-link ${activeTab === 'evidence' ? 'active' : ''}" data-bs-toggle="tab" data-bs-target="#tab-evidence">
                    <i class="bi bi-file-earmark-check me-1"></i> Evidence Documents (${(p.documents || []).length})
                  </button>
                </li>
                <li class="nav-item">
                  <button class="nav-link ${activeTab === 'readiness' ? 'active' : ''}" data-bs-toggle="tab" data-bs-target="#tab-readiness">
                    <i class="bi bi-speedometer2 me-1"></i> Readiness Assessment
                  </button>
                </li>
                <li class="nav-item">
                  <button class="nav-link ${activeTab === 'history' ? 'active' : ''}" data-bs-toggle="tab" data-bs-target="#tab-history" onclick="EcoPulseProjects.loadClassificationHistory(${p.id})">
                    <i class="bi bi-clock-history me-1"></i> Classification Audit History
                  </button>
                </li>
              </ul>

              <!-- Tab Contents -->
              <div class="tab-content p-4">
                <!-- 1. Overview Tab -->
                <div class="tab-pane fade ${activeTab === 'overview' ? 'show active' : ''}" id="tab-overview">
                  <div class="row g-4">
                    <div class="col-md-8">
                      <h6 class="fw-bold text-secondary text-uppercase small">Project Objectives & Scope</h6>
                      <p class="text-dark">${escapeHtml(p.project_objectives || p.description || 'No detailed narrative provided.')}</p>

                      <h6 class="fw-bold text-secondary text-uppercase small mt-4">Baseline Scenario</h6>
                      <div class="p-3 bg-light rounded-3 text-secondary small border">
                        ${escapeHtml(p.baseline_scenario_description || 'No baseline scenario description documented.')}
                      </div>

                      <h6 class="fw-bold text-secondary text-uppercase small mt-4">Expected Environmental Outcomes</h6>
                      <p class="text-secondary small">${escapeHtml(p.expected_environmental_outcomes || 'Mitigation of operational greenhouse gas emissions.')}</p>
                    </div>

                    <div class="col-md-4">
                      <div class="card border-0 bg-light p-3 rounded-3 mb-3">
                        <span class="text-secondary small">Annual Mitigation Estimate</span>
                        <h4 class="fw-bold text-success mb-0 mt-1">${parseFloat(p.estimated_annual_mitigation_tco2e || 0).toLocaleString()} <span class="fs-6 text-secondary">tCO2e/yr</span></h4>
                      </div>

                      <div class="border rounded-3 p-3 small mb-3">
                        <div class="mb-2"><strong>Methodology:</strong> ${escapeHtml(p.methodology || 'Not Specified')}</div>
                        <div class="mb-2"><strong>Crediting Period:</strong> ${escapeHtml(p.crediting_period_start || '-')} to ${escapeHtml(p.crediting_period_end || '-')}</div>
                        <div class="mb-2"><strong>Verifier:</strong> ${escapeHtml(p.third_party_verifier || 'None designated')}</div>
                        <div class="mb-2"><strong>Registry:</strong> ${escapeHtml(p.registry_name || 'N/A')}</div>
                        <div><strong>Serial Number:</strong> <span class="font-monospace">${escapeHtml(p.registry_serial_or_id || 'N/A')}</span></div>
                      </div>

                      <div class="border rounded-3 p-3 small">
                        <div class="mb-2"><strong>Location:</strong> ${escapeHtml(p.location_country || 'Global')}${p.location_region ? ', ' + escapeHtml(p.location_region) : ''}</div>
                        <div class="mb-2"><strong>Project Lead:</strong> ${escapeHtml(p.project_owner_name || '-')}</div>
                        <div><strong>Team:</strong> ${escapeHtml(p.responsible_team || '-')}</div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- 2. Evidence Documents Tab -->
                <div class="tab-pane fade ${activeTab === 'evidence' ? 'show active' : ''}" id="tab-evidence">
                  <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-secondary small">Verification statements, PDDs, and monitoring reports supporting this project.</span>
                    <button class="btn btn-outline-success btn-sm" onclick="EcoPulseProjects.openUploadDocModal(${p.id})">
                      <i class="bi bi-cloud-arrow-up me-1"></i> Upload Evidence Document
                    </button>
                  </div>

                  <div class="table-responsive border rounded-3 overflow-hidden">
                    <table class="table table-hover align-middle mb-0">
                      <thead class="table-light text-secondary small">
                        <tr>
                          <th>Document Type</th>
                          <th>Filename & Title</th>
                          <th>Version</th>
                          <th>Integrity Hash (SHA-256)</th>
                          <th>Review Status</th>
                          <th>Uploaded</th>
                          <th class="text-end">Actions</th>
                        </tr>
                      </thead>
                      <tbody id="docs-table-body">
                        ${renderDocumentsTable(p.documents || [], p.id)}
                      </tbody>
                    </table>
                  </div>
                </div>

                <!-- 3. Readiness Assessment Tab -->
                <div class="tab-pane fade ${activeTab === 'readiness' ? 'show active' : ''}" id="tab-readiness">
                  <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                      <h6 class="fw-bold mb-0">Algorithmic Readiness Scorecard</h6>
                      <span class="text-secondary small">Multi-dimensional evaluation of Additionality, Baseline, Monitoring, Permanence, and Verification.</span>
                    </div>
                    <button class="btn btn-brand btn-sm" onclick="EcoPulseProjects.runReadinessAssessment(${p.id})">
                      <i class="bi bi-play-circle me-1"></i> Run Readiness Assessment
                    </button>
                  </div>

                  <div id="readiness-assessment-container">
                    ${renderReadinessCard(p.current_readiness, p.id)}
                  </div>
                </div>

                <!-- 4. Classification History Tab -->
                <div class="tab-pane fade ${activeTab === 'history' ? 'show active' : ''}" id="tab-history">
                  <h6 class="fw-bold text-secondary text-uppercase small mb-3">Immutable Classification History</h6>
                  <div id="classification-history-container">
                    <div class="text-muted small">Loading history...</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    `;

    const old = document.getElementById('projectDetailModal');
    if (old) old.remove();

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('projectDetailModal'));
    modal.show();
  };

  /**
   * Render Documents Table
   */
  const renderDocumentsTable = (docs, projectId) => {
    if (!docs || docs.length === 0) {
      return `
        <tr>
          <td colspan="7" class="text-center py-4 text-muted">
            <i class="bi bi-file-earmark-x fs-3 d-block mb-1 text-secondary"></i>
            No verification evidence documents attached yet.
          </td>
        </tr>
      `;
    }

    return docs.map(d => {
      const hashShort = d.sha256_hash ? d.sha256_hash.substring(0, 10) + '...' : '-';
      return `
        <tr>
          <td><span class="badge bg-light text-dark border text-uppercase">${escapeHtml(d.document_type || 'other')}</span></td>
          <td>
            <a href="/api/projects/${projectId}/documents/${d.id}/download" target="_blank" class="fw-bold text-dark text-decoration-none">
              <i class="bi bi-file-earmark-pdf text-danger me-1"></i> ${escapeHtml(d.file_title || d.original_filename)}
            </a>
            <div class="text-secondary small">${escapeHtml(d.original_filename)} (${d.file_size_kb || 0} KB)</div>
          </td>
          <td><span class="badge bg-light text-secondary border">v${d.current_version || 1}</span></td>
          <td><span class="font-monospace small text-muted" title="${escapeHtml(d.sha256_hash)}">${hashShort}</span></td>
          <td>${getReviewStatusBadge(d.review_status)}</td>
          <td class="text-secondary small">${d.created_at ? d.created_at.substring(0, 10) : '-'}</td>
          <td class="text-end">
            <div class="btn-group btn-group-sm">
              <a href="/api/projects/${projectId}/documents/${d.id}/download" class="btn btn-outline-secondary btn-sm" title="Download">
                <i class="bi bi-download"></i>
              </a>
              <button class="btn btn-outline-secondary btn-sm" title="Review Document" onclick="EcoPulseProjects.openReviewDocModal(${projectId}, ${d.id})">
                <i class="bi bi-check2-circle"></i>
              </button>
            </div>
          </td>
        </tr>
      `;
    }).join('');
  };

  /**
   * Render Readiness Card View
   */
  const renderReadinessCard = (assessment, projectId) => {
    if (!assessment) {
      return `
        <div class="card border-0 bg-light p-4 text-center rounded-3">
          <i class="bi bi-speedometer2 fs-1 text-secondary mb-2"></i>
          <h6 class="fw-bold">No Readiness Assessment Recorded</h6>
          <p class="text-secondary small mb-3">
            Click "Run Readiness Assessment" above to evaluate additionality, baseline rigor, monitoring, permanence, and verification evidence.
          </p>
          <div>
            <button class="btn btn-brand btn-sm" onclick="EcoPulseProjects.runReadinessAssessment(${projectId})">
              <i class="bi bi-play-circle me-1"></i> Run Assessment Now
            </button>
          </div>
        </div>
      `;
    }

    const dims = assessment.dimensions || {};
    const overall = parseFloat(assessment.overall_readiness_percentage || 0);
    const isComplete = assessment.is_complete;

    return `
      <!-- Completeness Warning if Incomplete -->
      ${!isComplete ? `
        <div class="alert alert-warning border-0 rounded-3 mb-4 d-flex align-items-center gap-2 small">
          <i class="bi bi-exclamation-triangle-fill fs-5"></i>
          <div>
            <strong>Assessment Incomplete:</strong> Mandatory parameters are missing (${(assessment.missing_required_fields || []).join(', ')}).
            The score below reflects preliminary inputs only and cannot establish credit candidate eligibility.
          </div>
        </div>
      ` : ''}

      <!-- Overall Score Gauge -->
      <div class="card border-0 shadow-sm p-4 rounded-3 mb-4">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <span class="text-secondary small">OVERALL READINESS INDICATOR</span>
            <div class="d-flex align-items-baseline gap-2 mt-1">
              <h1 class="fw-bold text-dark mb-0">${overall.toFixed(1)}%</h1>
              <div>${getStageBadge(assessment.readiness_stage)}</div>
            </div>
          </div>
          <div class="text-end text-secondary small">
            <div>Version: <strong>v${assessment.assessment_version || 1}</strong></div>
            <div>Assessed: <strong>${assessment.assessed_at || assessment.created_at || 'Recent'}</strong></div>
          </div>
        </div>
      </div>

      <!-- 5-Dimension Scorecard Grid -->
      <h6 class="fw-bold text-secondary text-uppercase small mb-3">Dimension Rubric Breakdown</h6>
      <div class="row g-3 mb-4">
        ${renderDimensionCard('Additionality', dims.additionality, '25%')}
        ${renderDimensionCard('Baseline Rigor', dims.baseline_rigor, '20%')}
        ${renderDimensionCard('Monitoring Readiness', dims.monitoring, '20%')}
        ${renderDimensionCard('Permanence & Risk', dims.permanence, '10%')}
        ${renderDimensionCard('Verification Readiness', dims.verification_readiness, '25%')}
      </div>

      <!-- Gap Analysis & Recommendations -->
      <div class="row g-3">
        <div class="col-md-6">
          <div class="card border-0 bg-light p-3 rounded-3 h-100">
            <h6 class="fw-bold small text-secondary text-uppercase">Gap Analysis & Scoring Deductions</h6>
            <div class="text-dark small whitespace-pre-wrap">${escapeHtml(assessment.gap_analysis_summary || 'No gaps detected.')}</div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="card border-0 bg-light p-3 rounded-3 h-100">
            <h6 class="fw-bold small text-secondary text-uppercase">Prioritized Recommendations</h6>
            <div class="text-dark small whitespace-pre-wrap">${escapeHtml(assessment.recommendations || 'No recommendations.')}</div>
          </div>
        </div>
      </div>
    `;
  };

  /**
   * Helper to render single dimension scorecard
   */
  const renderDimensionCard = (name, dim, weight) => {
    if (!dim) return '';
    const score = parseInt(dim.score || 0, 10);
    const weighted = parseFloat(dim.weighted_score || 0);
    const barColor = score >= 85 ? 'bg-success' : (score >= 60 ? 'bg-primary' : (score >= 40 ? 'bg-warning' : 'bg-danger'));

    return `
      <div class="col-md">
        <div class="card border-0 shadow-sm p-3 rounded-3 h-100">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <span class="text-secondary small fw-bold">${name}</span>
            <span class="badge bg-light text-secondary border" style="font-size: 0.65rem;">${weight}</span>
          </div>
          <div class="d-flex align-items-baseline gap-2 mb-2">
            <h4 class="fw-bold text-dark mb-0">${score}</h4>
            <span class="text-secondary small">/ 100</span>
          </div>
          <div class="progress" style="height: 6px;">
            <div class="progress-bar ${barColor}" style="width: ${score}%;"></div>
          </div>
          <div class="text-muted mt-2" style="font-size: 0.72rem;">
            Weighted: <strong>${weighted.toFixed(1)}%</strong>
          </div>
        </div>
      </div>
    `;
  };

  /**
   * Trigger Algorithmic Readiness Assessment
   */
  const runReadinessAssessment = async (projectId) => {
    try {
      const res = await EcoPulseApi.post(`/api/projects/${projectId}/readiness-assessments`, {});
      const newAssessment = res?.data;

      // Update in current project and re-render readiness view
      if (currentProject && currentProject.id === projectId) {
        currentProject.current_readiness = newAssessment;
        const cont = document.getElementById('readiness-assessment-container');
        if (cont) {
          cont.innerHTML = renderReadinessCard(newAssessment, projectId);
        }
      }

      await fetchProjects();
    } catch (err) {
      alert(err.message || 'Failed to run readiness assessment.');
    }
  };

  /**
   * Load Classification Audit History
   */
  const loadClassificationHistory = async (projectId) => {
    const cont = document.getElementById('classification-history-container');
    if (!cont) return;

    try {
      const res = await EcoPulseApi.get(`/api/projects/${projectId}/classification-history`);
      const history = res?.data || [];

      if (history.length === 0) {
        cont.innerHTML = '<div class="text-muted small">No classification transitions recorded yet.</div>';
        return;
      }

      cont.innerHTML = `
        <div class="timeline position-relative ps-3 border-start">
          ${history.map(h => `
            <div class="mb-3 position-relative">
              <div class="d-flex align-items-center gap-2">
                <span class="fw-bold small text-dark">${escapeHtml(h.changed_by_name || 'System')}</span>
                <span class="text-secondary" style="font-size: 0.72rem;">${h.created_at || ''}</span>
              </div>
              <div class="my-1">
                ${h.previous_classification ? getClassificationBadge(h.previous_classification) : '<span class="badge bg-light text-secondary">None</span>'}
                <i class="bi bi-arrow-right mx-1 text-secondary"></i>
                ${getClassificationBadge(h.new_classification)}
              </div>
              <div class="text-dark small"><strong>Reason:</strong> ${escapeHtml(h.reason)}</div>
              ${h.supporting_evidence_notes ? `<div class="text-secondary small">Ref: ${escapeHtml(h.supporting_evidence_notes)}</div>` : ''}
            </div>
          `).join('')}
        </div>
      `;
    } catch (err) {
      cont.innerHTML = `<div class="text-danger small">${escapeHtml(err.message || 'Failed to load history.')}</div>`;
    }
  };

  /**
   * Open Upload Evidence Document Modal
   */
  const openUploadDocModal = (projectId) => {
    const modalHtml = `
      <div class="modal fade" id="uploadEvidenceModal" tabindex="-1">
        <div class="modal-dialog">
          <div class="modal-content border-0 shadow">
            <form onsubmit="EcoPulseProjects.submitUploadDoc(event, ${projectId})">
              <div class="modal-header">
                <h5 class="modal-title fw-bold">Upload Verification Evidence</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <div class="mb-3">
                  <label class="form-label small fw-bold">Document Type <span class="text-danger">*</span></label>
                  <select class="form-select" id="doc-type" required>
                    <option value="pdd">Project Design Document (PDD)</option>
                    <option value="validation_report">Validation Report</option>
                    <option value="verification_report">Verification Report</option>
                    <option value="monitoring_report">Monitoring Report</option>
                    <option value="issuance_record">Issuance Record / Registry Statement</option>
                    <option value="other">Other Supporting Documentation</option>
                  </select>
                </div>

                <div class="mb-3">
                  <label class="form-label small fw-bold">Document Title</label>
                  <input type="text" class="form-control" id="doc-title" placeholder="e.g. Third-Party Validation Statement 2026">
                </div>

                <div class="mb-3">
                  <label class="form-label small fw-bold">Select File (PDF, PNG, JPG, DOCX - max 10MB) <span class="text-danger">*</span></label>
                  <input type="file" class="form-control" id="doc-file" required accept=".pdf,.png,.jpg,.jpeg,.docx">
                </div>

                <div class="mb-3">
                  <label class="form-label small">Description / Notes</label>
                  <textarea class="form-control" id="doc-desc" rows="2" placeholder="Summary of document contents..."></textarea>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-brand btn-sm" id="upload-doc-btn">Upload & Verify</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    `;

    const old = document.getElementById('uploadEvidenceModal');
    if (old) old.remove();

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('uploadEvidenceModal'));
    modal.show();
  };

  /**
   * Submit Upload Document
   */
  const submitUploadDoc = async (e, projectId) => {
    e.preventDefault();
    const btn = document.getElementById('upload-doc-btn');
    if (btn) btn.disabled = true;

    try {
      const fileInput = document.getElementById('doc-file');
      if (!fileInput.files || fileInput.files.length === 0) {
        alert('Please select a file to upload.');
        return;
      }

      const formData = new FormData();
      formData.append('file', fileInput.files[0]);
      formData.append('document_type', document.getElementById('doc-type').value);
      formData.append('file_title', document.getElementById('doc-title').value.trim());
      formData.append('description', document.getElementById('doc-desc').value.trim());

      await EcoPulseApi.post(`/api/projects/${projectId}/documents`, formData);

      const modalEl = document.getElementById('uploadEvidenceModal');
      if (modalEl) {
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
      }

      // Re-fetch project detail
      await openProjectDetail(projectId, 'evidence');
    } catch (err) {
      alert(err.message || 'Failed to upload document.');
    } finally {
      if (btn) btn.disabled = false;
    }
  };

  /**
   * Open Review Document Modal
   */
  const openReviewDocModal = (projectId, docId) => {
    const modalHtml = `
      <div class="modal fade" id="reviewDocModal" tabindex="-1">
        <div class="modal-dialog">
          <div class="modal-content border-0 shadow">
            <form onsubmit="EcoPulseProjects.submitDocReview(event, ${projectId}, ${docId})">
              <div class="modal-header">
                <h5 class="modal-title fw-bold">Review Evidence Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <div class="mb-3">
                  <label class="form-label small fw-bold">Review Decision <span class="text-danger">*</span></label>
                  <select class="form-select" id="rev-status" required>
                    <option value="accepted">Accepted (Evidence Verified)</option>
                    <option value="under_review">Under Technical Review</option>
                    <option value="requires_revision">Requires Revision</option>
                    <option value="rejected">Rejected</option>
                  </select>
                </div>

                <div class="mb-3">
                  <label class="form-label small fw-bold">Review Comments <span class="text-danger">*</span></label>
                  <textarea class="form-control" id="rev-comments" rows="3" required placeholder="Enter review notes and verification findings..."></textarea>
                </div>

                <div class="mb-3">
                  <label class="form-label small">Revision / Rejection Reason</label>
                  <input type="text" class="form-control" id="rev-reason" placeholder="State specific remediation requirements...">
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-brand btn-sm">Submit Review Decision</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    `;

    const old = document.getElementById('reviewDocModal');
    if (old) old.remove();

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('reviewDocModal'));
    modal.show();
  };

  /**
   * Submit Document Review
   */
  const submitDocReview = async (e, projectId, docId) => {
    e.preventDefault();
    try {
      const payload = {
        review_status: document.getElementById('rev-status').value,
        review_comments: document.getElementById('rev-comments').value.trim(),
        rejection_or_revision_reason: document.getElementById('rev-reason').value.trim() || null,
      };

      await EcoPulseApi.post(`/api/projects/${projectId}/documents/${docId}/review`, payload);

      const modalEl = document.getElementById('reviewDocModal');
      if (modalEl) {
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
      }

      await openProjectDetail(projectId, 'evidence');
    } catch (err) {
      alert(err.message || 'Failed to submit review.');
    }
  };

  const exportCsv = () => {
    if (!projectsList || projectsList.length === 0) {
      alert('No projects loaded to export.');
      return;
    }
    const headers = ['Project Code', 'Title', 'Classification', 'Domain', 'Readiness Stage', 'Readiness Score', 'Estimated Mitigation (tCO2e)', 'Status'];
    const rows = projectsList.map(p => [
      `"${(p.project_code || '').replace(/"/g, '""')}"`,
      `"${(p.title || '').replace(/"/g, '""')}"`,
      `"${(p.classification || '').replace(/"/g, '""')}"`,
      `"${(p.domain_name || '').replace(/"/g, '""')}"`,
      `"${(p.readiness_stage || '').replace(/"/g, '""')}"`,
      p.readiness_score !== null ? p.readiness_score : '',
      p.estimated_mitigation_tco2e ?? 0,
      `"${(p.status || '').replace(/"/g, '""')}"`,
    ]);
    const csvContent = '\uFEFF' + [headers.join(','), ...rows.map(r => r.join(','))].join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `ecopulse_environmental_projects_${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  };

  return {
    loadProjectsView,
    refresh,
    handleFilterChange,
    handleSearch,
    resetFilters,
    openCreateModal,
    onClassificationChange,
    submitCreateProject,
    openClassificationModal,
    submitClassificationChange,
    openProjectDetail,
    loadClassificationHistory,
    openUploadDocModal,
    submitUploadDoc,
    openReviewDocModal,
    submitDocReview,
    runReadinessAssessment,
    exportCsv,
  };
})();