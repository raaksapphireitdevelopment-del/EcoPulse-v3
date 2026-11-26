/**
 * EcoPulse MVP V3 - Multi-Domain Workspace & Carbon Engine
 * Interactive activity data intake, calculation visualization,
 * immutable audit snapshots, and Chart.js analytics.
 */

const EcoPulseWorkspace = (() => {
  let currentDomainCode = '';
  let workspaceData = null;
  let activeScopeChart = null;
  let activeCategoryChart = null;

  const openDomain = async (domainCode) => {
    currentDomainCode = domainCode;
    const container = document.getElementById('view-container');
    if (!container) return;

    container.innerHTML = `
      <div class="d-flex justify-content-center py-5">
        <div class="spinner-border text-success" role="status"></div>
      </div>
    `;

    try {
      const res = await window.EcoPulseApi.get(`/api/domains/${domainCode}/workspace`);
      workspaceData = res.data;
      renderWorkspace(container);

      // Update active nav links in sidebar
      document.querySelectorAll('.portal-nav-link').forEach(link => {
        link.classList.remove('active');
        if (link.dataset.domain === domainCode) {
          link.classList.add('active');
        }
      });

      // Load initial calculation summary and activity records
      await loadSummaryAndRecords();

    } catch (err) {
      if (err.status === 403) {
        container.innerHTML = `
          <div class="ep-card text-center py-5">
            <i class="bi bi-shield-lock-fill text-warning fs-1 mb-3 d-block"></i>
            <h4 class="fw-bold">Domain Workspace Locked</h4>
            <p class="text-secondary mx-auto mb-4" style="max-width: 500px;">
              ${EcoPulseDashboard.escapeHtml(err.message)}
            </p>
            <button class="btn btn-brand" onclick="EcoPulseApp.navigateTo('domains')">
              <i class="bi bi-grid-3x3-gap me-1"></i> Go to Domain Settings
            </button>
          </div>
        `;
      } else {
        container.innerHTML = `<div class="alert alert-danger">${EcoPulseDashboard.escapeHtml(err.message)}</div>`;
      }
    }
  };

  const renderWorkspace = (container) => {
    const d = workspaceData.domain;
    const p = workspaceData.domain_profile;
    const categoriesByScope = workspaceData.categories_by_scope;
    const facilities = workspaceData.facilities || [];
    const periods = workspaceData.reporting_periods || [];

    container.innerHTML = `
      <!-- Domain Workspace Banner -->
      <div class="workspace-banner mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
          <div class="d-flex align-items-center gap-3">
            <div class="auth-brand-logo bg-white text-dark" style="width: 52px; height: 52px; font-size: 1.7rem;">
              <i class="bi ${EcoPulseDashboard.escapeHtml(d.icon)} text-brand"></i>
            </div>
            <div>
              <div class="d-flex align-items-center gap-2 mb-1">
                <h3 class="fw-bold mb-0 text-white">${EcoPulseDashboard.escapeHtml(d.name)} Workspace</h3>
                <span class="badge bg-white bg-opacity-25 text-white border border-white border-opacity-25 small">Active Profile</span>
              </div>
              <p class="mb-0 text-white-50 small">${EcoPulseDashboard.escapeHtml(d.description)}</p>
            </div>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-light btn-sm fw-semibold" onclick="EcoPulseWorkspace.openIntakeModal()">
              <i class="bi bi-plus-circle me-1 text-success"></i> Record Activity Data
            </button>
            <button class="btn btn-outline-light btn-sm" onclick="EcoPulseWorkspace.triggerBatchRecalculate()">
              <i class="bi bi-arrow-repeat me-1"></i> Recalculate Period
            </button>
          </div>
        </div>
      </div>

      <!-- Operational Context Bar (Facility & Reporting Period Selector) -->
      <div class="ep-card p-3 mb-4 bg-white border">
        <div class="row g-3 align-items-center">
          <div class="col-md-5">
            <label class="form-label small text-secondary fw-semibold mb-1">
              <i class="bi bi-building me-1 text-success"></i> Filter Operational Facility
            </label>
            <select id="ws-facility-select" class="form-select form-select-sm" onchange="EcoPulseWorkspace.onContextChange()">
              <option value="">All Domain Facilities</option>
              ${facilities.map(f => `<option value="${f.id}">${EcoPulseDashboard.escapeHtml(f.name)} (${EcoPulseDashboard.escapeHtml(f.city)})</option>`).join('')}
            </select>
          </div>

          <div class="col-md-5">
            <label class="form-label small text-secondary fw-semibold mb-1">
              <i class="bi bi-calendar-event me-1 text-primary"></i> Filter Reporting Period
            </label>
            <select id="ws-period-select" class="form-select form-select-sm" onchange="EcoPulseWorkspace.onContextChange()">
              <option value="">All Reporting Periods</option>
              ${periods.map(pr => `<option value="${pr.id}">${EcoPulseDashboard.escapeHtml(pr.name)} (${pr.start_date} to ${pr.end_date})</option>`).join('')}
            </select>
          </div>

          <div class="col-md-2 text-md-end pt-md-3">
            <button class="btn btn-sm btn-outline-secondary w-100" onclick="EcoPulseWorkspace.onContextChange()">
              <i class="bi bi-funnel me-1"></i> Apply
            </button>
          </div>
        </div>
      </div>

      <!-- Live Carbon Footprint KPI Cards -->
      <div class="row g-3 mb-4" id="ws-kpi-container">
        <div class="col-md-3 col-sm-6">
          <div class="ep-card h-100 border-start border-4 border-success">
            <span class="text-secondary small fw-semibold d-block mb-1">Operational Footprint (1+2+3)</span>
            <div class="d-flex align-items-baseline gap-2">
              <h3 class="fw-bold mb-0 text-dark" id="kpi-operational-tonnes">0.000</h3>
              <span class="text-muted small">tCO₂e</span>
            </div>
            <div class="text-muted small mt-2" id="kpi-status-note">Loading...</div>
          </div>
        </div>

        <div class="col-md-3 col-sm-6">
          <div class="ep-card h-100 border-start border-4 border-primary">
            <span class="text-secondary small fw-semibold d-block mb-1">Scope 1 & Scope 2</span>
            <div class="d-flex align-items-baseline gap-2">
              <h3 class="fw-bold mb-0 text-dark" id="kpi-scope12-tonnes">0.000</h3>
              <span class="text-muted small">tCO₂e</span>
            </div>
            <div class="text-muted small mt-2">
              S1: <span id="kpi-s1">0.00</span> | S2: <span id="kpi-s2">0.00</span>
            </div>
          </div>
        </div>

        <div class="col-md-3 col-sm-6">
          <div class="ep-card h-100 border-start border-4 border-warning">
            <span class="text-secondary small fw-semibold d-block mb-1">Scope 3 (Value Chain)</span>
            <div class="d-flex align-items-baseline gap-2">
              <h3 class="fw-bold mb-0 text-dark" id="kpi-scope3-tonnes">0.000</h3>
              <span class="text-muted small">tCO₂e</span>
            </div>
            <div class="text-muted small mt-2">Upstream & Downstream</div>
          </div>
        </div>

        <div class="col-md-3 col-sm-6">
          <div class="ep-card h-100 border-start border-4 border-info">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span class="text-secondary small fw-semibold">Avoided Emissions</span>
              <span class="badge bg-info-subtle text-info border border-info" style="font-size: 0.65rem;">Segregated</span>
            </div>
            <div class="d-flex align-items-baseline gap-2">
              <h3 class="fw-bold mb-0 text-info" id="kpi-avoided-tonnes">0.000</h3>
              <span class="text-muted small">tCO₂e</span>
            </div>
            <div class="text-muted small mt-2">Never mixed with Scopes 1-3</div>
          </div>
        </div>
      </div>

      <!-- Visual Analytics & Category Hierarchy Row -->
      <div class="row g-4 mb-4">
        <!-- Scope Breakdown Chart -->
        <div class="col-lg-4">
          <div class="ep-card h-100">
            <h6 class="fw-bold mb-3"><i class="bi bi-pie-chart me-2 text-success"></i>Scope Breakdown</h6>
            <div style="position: relative; height: 220px;">
              <canvas id="scopeDonutChart"></canvas>
            </div>
            <div class="mt-3 text-center small text-muted" id="scopeLegendNote"></div>
          </div>
        </div>

        <!-- Category Emissions Bar Chart -->
        <div class="col-lg-8">
          <div class="ep-card h-100">
            <h6 class="fw-bold mb-3"><i class="bi bi-bar-chart-line me-2 text-primary"></i>Top Emission Categories (tCO₂e)</h6>
            <div style="position: relative; height: 220px;">
              <canvas id="categoryBarChart"></canvas>
            </div>
          </div>
        </div>
      </div>

      <!-- Activity Data Log Table Card -->
      <div class="ep-card mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <div>
            <h5 class="fw-bold mb-0"><i class="bi bi-table me-2 text-success"></i>Activity Data Records & Audit Snapshots</h5>
            <small class="text-muted">Recorded activity inputs, conversion parameters, and verified carbon calculations.</small>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-success" onclick="EcoPulseWorkspace.exportRecords()">
              <i class="bi bi-file-earmark-arrow-down me-1"></i> Export CSV
            </button>
            <button class="btn btn-sm btn-brand" onclick="EcoPulseWorkspace.openIntakeModal()">
              <i class="bi bi-plus-lg me-1"></i> Add Entry
            </button>
            <button class="btn btn-sm btn-outline-secondary" onclick="EcoPulseWorkspace.refreshRecords()">
              <i class="bi bi-arrow-clockwise"></i>
            </button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr class="small text-secondary">
                <th>Date</th>
                <th>Facility & Period</th>
                <th>Category & Scope</th>
                <th>Quantity / Unit</th>
                <th>Emissions (tCO₂e)</th>
                <th>Status</th>
                <th>Calculation Formula Snapshot</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody id="ws-activity-records-body">
              <tr>
                <td colspan="8" class="text-center py-4 text-muted">
                  <div class="spinner-border spinner-border-sm text-success me-2"></div> Loading activity data...
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    `;
  };

  const onContextChange = async () => {
    await loadSummaryAndRecords();
  };

  const loadSummaryAndRecords = async () => {
    const facilityId = document.getElementById('ws-facility-select')?.value || null;
    const periodId = document.getElementById('ws-period-select')?.value || null;
    const domainProfileId = workspaceData?.domain_profile?.id || null;

    // 1. Fetch Summary
    try {
      const query = new URLSearchParams();
      if (facilityId) query.set('facility_id', facilityId);
      if (periodId) query.set('reporting_period_id', periodId);
      if (domainProfileId) query.set('domain_profile_id', domainProfileId);

      const summaryRes = await window.EcoPulseApi.get(`/api/calculations/summary?${query.toString()}`);
      updateKPIsAndCharts(summaryRes.data);
    } catch (e) {
      console.warn('Failed to load calculation summary:', e);
    }

    // 2. Fetch Activity Records
    await loadRecords(facilityId, periodId, domainProfileId);
  };

  const updateKPIsAndCharts = (summaryData) => {
    const s = summaryData.summary;
    const sb = summaryData.status_breakdown;
    const cb = summaryData.category_breakdown || [];

    // KPIs
    const opEl = document.getElementById('kpi-operational-tonnes');
    if (opEl) opEl.textContent = Number(s.operational_total_tonnes).toFixed(3);

    const s12El = document.getElementById('kpi-scope12-tonnes');
    if (s12El) s12El.textContent = (Number(s.scope_1_tonnes) + Number(s.scope_2_tonnes)).toFixed(3);

    const s1El = document.getElementById('kpi-s1');
    if (s1El) s1El.textContent = Number(s.scope_1_tonnes).toFixed(2);

    const s2El = document.getElementById('kpi-s2');
    if (s2El) s2El.textContent = Number(s.scope_2_tonnes).toFixed(2);

    const s3El = document.getElementById('kpi-scope3-tonnes');
    if (s3El) s3El.textContent = Number(s.scope_3_tonnes).toFixed(3);

    const avEl = document.getElementById('kpi-avoided-tonnes');
    if (avEl) avEl.textContent = Number(s.avoided_emissions_tonnes).toFixed(3);

    const noteEl = document.getElementById('kpi-status-note');
    if (noteEl) {
      noteEl.innerHTML = `
        <span class="text-success"><i class="bi bi-check-circle"></i> ${sb.validated}t validated</span> &bull;
        <span class="text-warning"><i class="bi bi-flask"></i> ${sb.demo}t demo</span>
      `;
    }

    // Render / Update Chart.js Charts if Chart library available
    if (typeof Chart !== 'undefined') {
      renderCharts(s, cb);
    }
  };

  const renderCharts = (summary, categoryBreakdown) => {
    // 1. Donut Chart for Scopes
    const donutCtx = document.getElementById('scopeDonutChart')?.getContext('2d');
    if (donutCtx) {
      if (activeScopeChart) activeScopeChart.destroy();
      activeScopeChart = new Chart(donutCtx, {
        type: 'doughnut',
        data: {
          labels: ['Scope 1 (Direct)', 'Scope 2 (Electricity)', 'Scope 3 (Value Chain)', 'Avoided Emissions'],
          datasets: [{
            data: [
              Number(summary.scope_1_tonnes),
              Number(summary.scope_2_tonnes),
              Number(summary.scope_3_tonnes),
              Number(summary.avoided_emissions_tonnes),
            ],
            backgroundColor: ['#28a745', '#0d6efd', '#ffc107', '#0dcaf0'],
            borderWidth: 2,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
          }
        }
      });
    }

    // 2. Bar Chart for Categories
    const barCtx = document.getElementById('categoryBarChart')?.getContext('2d');
    if (barCtx) {
      if (activeCategoryChart) activeCategoryChart.destroy();
      const topCategories = categoryBreakdown.slice(0, 6);
      activeCategoryChart = new Chart(barCtx, {
        type: 'bar',
        data: {
          labels: topCategories.map(c => c.category_name.length > 20 ? c.category_name.substring(0, 20) + '...' : c.category_name),
          datasets: [{
            label: 'Emissions (tCO₂e)',
            data: topCategories.map(c => Number(c.total_tonnes)),
            backgroundColor: '#198754',
            borderRadius: 4,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: { beginAtZero: true, title: { display: true, text: 'tCO₂e' } }
          },
          plugins: {
            legend: { display: false }
          }
        }
      });
    }
  };

  const loadRecords = async (facilityId, periodId, domainProfileId) => {
    const tbody = document.getElementById('ws-activity-records-body');
    if (!tbody) return;

    try {
      const q = new URLSearchParams();
      if (facilityId) q.set('facility_id', facilityId);
      if (periodId) q.set('reporting_period_id', periodId);
      if (domainProfileId) q.set('domain_profile_id', domainProfileId);
      q.set('limit', '50');

      const res = await window.EcoPulseApi.get(`/api/activity-records?${q.toString()}`);
      const records = res.data.records || [];

      if (records.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="8" class="text-center py-5 text-muted">
              <i class="bi bi-card-checklist fs-2 d-block mb-2 text-secondary"></i>
              No activity records logged in this operational view yet.
              <div class="mt-2">
                <button class="btn btn-sm btn-brand" onclick="EcoPulseWorkspace.openIntakeModal()">
                  <i class="bi bi-plus-lg me-1"></i> Add First Record
                </button>
              </div>
            </td>
          </tr>
        `;
        return;
      }

      tbody.innerHTML = records.map(r => {
        const isAvoided = (r.calc_scope === 'avoided_emissions');
        const emissionsDisplay = r.tonnes_co2e !== null
          ? `<strong class="${isAvoided ? 'text-info' : 'text-dark'}">${Number(r.tonnes_co2e).toFixed(4)}</strong> <small class="text-muted">tCO₂e</small>`
          : '<span class="text-muted small">Pending Calc</span>';

        const statusBadges = {
          'draft': '<span class="badge bg-secondary">Draft</span>',
          'submitted': '<span class="badge bg-primary">Submitted</span>',
          'validated': '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Validated</span>',
          'rejected': `<span class="badge bg-danger" title="${EcoPulseDashboard.escapeHtml(r.rejection_reason || '')}">Rejected</span>`,
        };

        const scopeBadge = r.calc_scope === 'avoided_emissions'
          ? '<span class="badge bg-info text-dark">Avoided</span>'
          : r.calc_scope === 'scope_1'
            ? '<span class="badge-scope-1">Scope 1</span>'
            : r.calc_scope === 'scope_2'
              ? '<span class="badge-scope-2">Scope 2</span>'
              : '<span class="badge-scope-3">Scope 3</span>';

        return `
          <tr>
            <td>
              <span class="d-block small text-dark fw-semibold">${r.activity_date}</span>
              <small class="text-muted" style="font-size: 0.72rem;">ID: #${r.id}</small>
            </td>
            <td>
              <strong class="d-block text-dark small">${EcoPulseDashboard.escapeHtml(r.facility_name)}</strong>
              <small class="text-muted">${EcoPulseDashboard.escapeHtml(r.period_name)}</small>
            </td>
            <td>
              <span class="d-block small text-dark">${EcoPulseDashboard.escapeHtml(r.category_name)}</span>
              ${scopeBadge}
            </td>
            <td>
              <strong>${Number(r.quantity).toLocaleString()}</strong>
              <span class="badge bg-white text-dark border small ms-1">${EcoPulseDashboard.escapeHtml(r.unit)}</span>
            </td>
            <td>${emissionsDisplay}</td>
            <td>${statusBadges[r.status] || r.status}</td>
            <td>
              <small class="text-muted font-monospace d-block text-truncate" style="max-width: 220px;" title="${EcoPulseDashboard.escapeHtml(r.calculation_formula || 'None')}">
                ${EcoPulseDashboard.escapeHtml(r.calculation_formula || 'Calculation pending')}
              </small>
              ${r.calc_version ? `<span class="badge bg-light text-secondary border" style="font-size: 0.65rem;">v${r.calc_version} snapshot</span>` : ''}
            </td>
            <td class="text-end">
              <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-secondary" title="View Audit Snapshot History" onclick="EcoPulseWorkspace.viewHistory(${r.id})">
                  <i class="bi bi-clock-history"></i>
                </button>
                ${r.status === 'draft' ? `
                  <button class="btn btn-outline-primary" title="Submit for Calculation" onclick="EcoPulseWorkspace.submitRecord(${r.id})">
                    <i class="bi bi-send"></i>
                  </button>
                ` : ''}
                ${(r.status === 'submitted' || r.status === 'draft') ? `
                  <button class="btn btn-outline-success" title="Validate Record" onclick="EcoPulseWorkspace.validateRecord(${r.id})">
                    <i class="bi bi-check-lg"></i>
                  </button>
                  <button class="btn btn-outline-danger" title="Reject Record" onclick="EcoPulseWorkspace.rejectRecord(${r.id})">
                    <i class="bi bi-x-lg"></i>
                  </button>
                ` : ''}
                <button class="btn btn-outline-success" title="Recalculate Record" onclick="EcoPulseWorkspace.recalculateRecord(${r.id})">
                  <i class="bi bi-calculator"></i>
                </button>
                ${(r.status === 'draft' || r.status === 'rejected') ? `
                  <button class="btn btn-outline-danger" title="Delete Record" onclick="EcoPulseWorkspace.deleteRecord(${r.id})">
                    <i class="bi bi-trash"></i>
                  </button>
                ` : ''}
              </div>
            </td>
          </tr>
        `;
      }).join('');

    } catch (e) {
      tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-danger">Failed to load records: ${EcoPulseDashboard.escapeHtml(e.message)}</td></tr>`;
    }
  };

  const refreshRecords = () => {
    loadSummaryAndRecords();
  };

  // Activity Intake Modal Management
  const openIntakeModal = () => {
    if (!workspaceData) return;
    const facilities = workspaceData.facilities || [];
    const periods = workspaceData.reporting_periods || [];
    const categoriesByScope = workspaceData.categories_by_scope;
    const allCategories = [
      ...categoriesByScope.scope_1,
      ...categoriesByScope.scope_2,
      ...categoriesByScope.scope_3,
    ];

    if (facilities.length === 0) {
      EcoPulseNotify.show('Please create at least one facility in this domain before logging activity data.', 'warning');
      return;
    }
    if (periods.length === 0) {
      EcoPulseNotify.show('Please configure a reporting period before logging activity data.', 'warning');
      return;
    }

    // Modal HTML injection
    let modalEl = document.getElementById('activityIntakeModal');
    if (!modalEl) {
      modalEl = document.createElement('div');
      modalEl.id = 'activityIntakeModal';
      modalEl.className = 'modal fade';
      modalEl.tabIndex = -1;
      document.body.appendChild(modalEl);
    }

    modalEl.innerHTML = `
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <form id="activityIntakeForm" onsubmit="EcoPulseWorkspace.handleIntakeSubmit(event)">
            <div class="modal-header">
              <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle-fill text-success me-2"></i>Record Activity Data</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label small fw-semibold">Operational Facility <span class="text-danger">*</span></label>
                  <select class="form-select" id="intake_facility_id" required>
                    ${facilities.map(f => `<option value="${f.id}">${EcoPulseDashboard.escapeHtml(f.name)} (${f.country || 'Global'})</option>`).join('')}
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-semibold">Reporting Period <span class="text-danger">*</span></label>
                  <select class="form-select" id="intake_period_id" required>
                    ${periods.map(p => `<option value="${p.id}">${EcoPulseDashboard.escapeHtml(p.name)} (${p.status})</option>`).join('')}
                  </select>
                </div>

                <div class="col-md-12">
                  <label class="form-label small fw-semibold">Activity Category <span class="text-danger">*</span></label>
                  <select class="form-select" id="intake_category_id" required onchange="EcoPulseWorkspace.onCategorySelect(this.value)">
                    <option value="">-- Choose Category --</option>
                    ${allCategories.map(c => `<option value="${c.id}" data-unit="${c.standard_unit}" data-scope="${c.scope}">${EcoPulseDashboard.escapeHtml(c.name)} (${c.standard_unit})</option>`).join('')}
                  </select>
                </div>

                <div class="col-md-4">
                  <label class="form-label small fw-semibold">Activity Date <span class="text-danger">*</span></label>
                  <input type="date" class="form-control" id="intake_date" required value="${new Date().toISOString().split('T')[0]}">
                </div>

                <div class="col-md-4">
                  <label class="form-label small fw-semibold">Quantity <span class="text-danger">*</span></label>
                  <input type="number" step="any" min="0.0001" class="form-control" id="intake_quantity" required placeholder="e.g. 1500">
                </div>

                <div class="col-md-4">
                  <label class="form-label small fw-semibold">Unit of Measure <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="intake_unit" required placeholder="e.g. kWh, Liters, kg">
                  <div class="form-text" id="intake_unit_hint">Standard unit recommended</div>
                </div>

                <div class="col-md-6">
                  <label class="form-label small fw-semibold">Data Source Type</label>
                  <select class="form-select" id="intake_source_type">
                    <option value="invoice" selected>Invoice / Utility Bill</option>
                    <option value="meter_read">Direct Meter Read</option>
                    <option value="sensor">IoT / Automated Sensor</option>
                    <option value="estimate">Engineering Estimate</option>
                    <option value="manual">Manual Log</option>
                  </select>
                </div>

                <div class="col-md-6">
                  <label class="form-label small fw-semibold">Supporting Document Link</label>
                  <input type="url" class="form-control" id="intake_doc_url" placeholder="https://docs.company.com/bill-102.pdf">
                </div>

                <div class="col-md-12">
                  <label class="form-label small fw-semibold">Description / Notes</label>
                  <textarea class="form-control" id="intake_description" rows="2" placeholder="Optional notes for verifier/auditor..."></textarea>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
              <button type="button" class="btn btn-outline-secondary btn-sm" onclick="EcoPulseWorkspace.submitIntakeForm('draft')">
                <i class="bi bi-file-earmark me-1"></i> Save Draft
              </button>
              <button type="button" class="btn btn-brand btn-sm px-4" onclick="EcoPulseWorkspace.submitIntakeForm('submitted')">
                <i class="bi bi-calculator me-1"></i> Submit & Calculate
              </button>
            </div>
          </form>
        </div>
      </div>
    `;

    const bsModal = new bootstrap.Modal(modalEl);
    bsModal.show();
  };

  const onCategorySelect = (categoryId) => {
    const sel = document.getElementById('intake_category_id');
    const opt = sel.options[sel.selectedIndex];
    const unit = opt.getAttribute('data-unit') || '';
    const unitInput = document.getElementById('intake_unit');
    const unitHint = document.getElementById('intake_unit_hint');

    if (unitInput && unit) {
      unitInput.value = unit;
      if (unitHint) unitHint.textContent = `Standard Category Unit: ${unit}`;
    }
  };

  const submitIntakeForm = async (targetStatus) => {
    const facilityId = document.getElementById('intake_facility_id')?.value;
    const periodId = document.getElementById('intake_period_id')?.value;
    const categoryId = document.getElementById('intake_category_id')?.value;
    const date = document.getElementById('intake_date')?.value;
    const quantity = parseFloat(document.getElementById('intake_quantity')?.value || '0');
    const unit = document.getElementById('intake_unit')?.value.trim();
    const sourceType = document.getElementById('intake_source_type')?.value;
    const docUrl = document.getElementById('intake_doc_url')?.value.trim();
    const description = document.getElementById('intake_description')?.value.trim();

    if (!facilityId || !periodId || !categoryId || !date || quantity <= 0 || !unit) {
      EcoPulseNotify.show('Please fill in all required fields. Quantity must be > 0.', 'error');
      return;
    }

    try {
      const payload = {
        facility_id: parseInt(facilityId),
        reporting_period_id: parseInt(periodId),
        activity_category_id: parseInt(categoryId),
        domain_profile_id: workspaceData?.domain_profile?.id,
        activity_date: date,
        quantity: quantity,
        unit: unit,
        data_source_type: sourceType,
        supporting_document_url: docUrl || null,
        description: description || null,
        status: targetStatus,
      };

      const res = await window.EcoPulseApi.post('/api/activity-records', payload);

      const modalEl = document.getElementById('activityIntakeModal');
      const bsModal = bootstrap.Modal.getInstance(modalEl);
      if (bsModal) bsModal.hide();

      EcoPulseNotify.show(res.message || 'Activity record saved!', 'success');
      await loadSummaryAndRecords();

    } catch (err) {
      EcoPulseNotify.show(err.message || 'Failed to save activity record', 'error');
    }
  };

  const handleIntakeSubmit = (e) => {
    e.preventDefault();
    submitIntakeForm('submitted');
  };

  // Actions on individual records
  const submitRecord = async (recordId) => {
    try {
      const res = await window.EcoPulseApi.post(`/api/activity-records/${recordId}/submit`, {});
      EcoPulseNotify.show(res.message || 'Record submitted!', 'success');
      await loadSummaryAndRecords();
    } catch (err) {
      EcoPulseNotify.show(err.message || 'Failed to submit record', 'error');
    }
  };

  const validateRecord = async (recordId) => {
    if (!confirm('Validate this activity record and finalize emissions?')) return;
    try {
      const res = await window.EcoPulseApi.post(`/api/activity-records/${recordId}/validate`, {});
      EcoPulseNotify.show(res.message || 'Record validated!', 'success');
      await loadSummaryAndRecords();
    } catch (err) {
      EcoPulseNotify.show(err.message || 'Failed to validate record', 'error');
    }
  };

  const rejectRecord = async (recordId) => {
    const reason = prompt('Please enter reason for rejection:');
    if (reason === null) return;
    if (!reason.trim()) {
      alert('Rejection reason is required.');
      return;
    }

    try {
      const res = await window.EcoPulseApi.post(`/api/activity-records/${recordId}/reject`, { rejection_reason: reason.trim() });
      EcoPulseNotify.show(res.message || 'Record marked as rejected', 'warning');
      await loadSummaryAndRecords();
    } catch (err) {
      EcoPulseNotify.show(err.message || 'Failed to reject record', 'error');
    }
  };

  const recalculateRecord = async (recordId) => {
    try {
      const res = await window.EcoPulseApi.post(`/api/calculations/record/${recordId}`, { reason: 'User Manual Recalculation' });
      EcoPulseNotify.show('Recalculation complete: ' + res.data.tonnes_co2e + ' tCO₂e', 'success');
      await loadSummaryAndRecords();
    } catch (err) {
      EcoPulseNotify.show(err.message || 'Calculation failed', 'error');
    }
  };

  const deleteRecord = async (recordId) => {
    if (!confirm('Are you sure you want to delete this activity record?')) return;
    try {
      const res = await window.EcoPulseApi.delete(`/api/activity-records/${recordId}`);
      EcoPulseNotify.show('Record deleted', 'success');
      await loadSummaryAndRecords();
    } catch (err) {
      EcoPulseNotify.show(err.message || 'Failed to delete record', 'error');
    }
  };

  const triggerBatchRecalculate = async () => {
    const periodId = document.getElementById('ws-period-select')?.value || null;
    const facilityId = document.getElementById('ws-facility-select')?.value || null;

    if (!confirm('Recalculate carbon emissions for all matching activity records?')) return;

    try {
      const payload = {
        reporting_period_id: periodId ? parseInt(periodId) : null,
        facility_id: facilityId ? parseInt(facilityId) : null,
        reason: 'Period Workspace Recalculation',
      };
      const res = await window.EcoPulseApi.post('/api/calculations/recalculate', payload);
      EcoPulseNotify.show(`Recalculated ${res.data.calculated} records successfully.`, 'success');
      await loadSummaryAndRecords();
    } catch (err) {
      EcoPulseNotify.show(err.message || 'Batch recalculation error', 'error');
    }
  };

  // View immutable calculation version history
  const viewHistory = async (recordId) => {
    try {
      const res = await window.EcoPulseApi.get(`/api/calculations/history/${recordId}`);
      const history = res.data.history || [];
      const record = res.data.activity_record;

      let historyModal = document.getElementById('calcHistoryModal');
      if (!historyModal) {
        historyModal = document.createElement('div');
        historyModal.id = 'calcHistoryModal';
        historyModal.className = 'modal fade';
        historyModal.tabIndex = -1;
        document.body.appendChild(historyModal);
      }

      historyModal.innerHTML = `
        <div class="modal-dialog modal-xl modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i>Immutable Calculation History</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="p-3 bg-light rounded mb-3">
                <div class="row small">
                  <div class="col-md-3"><strong>Record ID:</strong> #${record.id}</div>
                  <div class="col-md-3"><strong>Facility:</strong> ${EcoPulseDashboard.escapeHtml(record.facility_name)}</div>
                  <div class="col-md-3"><strong>Category:</strong> ${EcoPulseDashboard.escapeHtml(record.category_name)}</div>
                  <div class="col-md-3"><strong>Activity:</strong> ${Number(record.quantity).toLocaleString()} ${EcoPulseDashboard.escapeHtml(record.unit)}</div>
                </div>
              </div>

              <h6>Snapshot Version Audits (${history.length} Snapshots)</h6>
              <div class="table-responsive">
                <table class="table table-bordered table-sm small align-middle">
                  <thead class="table-light">
                    <tr>
                      <th>Ver</th>
                      <th>Status</th>
                      <th>Snapshot Factor</th>
                      <th>Norm Qty & Unit</th>
                      <th>GWP</th>
                      <th>Emissions</th>
                      <th>Formula Audit Trail</th>
                      <th>Calculated At</th>
                      <th>Reason / Trigger</th>
                    </tr>
                  </thead>
                  <tbody>
                    ${history.length === 0 ? '<tr><td colspan="9" class="text-center py-3 text-muted">No calculation snapshots recorded yet.</td></tr>' : ''}
                    ${history.map(h => `
                      <tr class="${h.is_current ? 'table-success table-opacity-25' : ''}">
                        <td>
                          <strong>v${h.version}</strong>
                          ${h.is_current ? '<span class="badge bg-success ms-1">Current</span>' : '<span class="badge bg-secondary ms-1">Archived</span>'}
                        </td>
                        <td>
                          <span class="badge ${h.calculation_status === 'validated' ? 'bg-success' : 'bg-warning text-dark'}">${h.calculation_status}</span>
                        </td>
                        <td>
                          <strong>${EcoPulseDashboard.escapeHtml(h.factor_name || 'Factor #' + h.emission_factor_id)}</strong>
                          <span class="d-block text-muted" style="font-size: 0.7rem;">${Number(h.factor_value_snapshot).toFixed(6)} kgCO2e/${EcoPulseDashboard.escapeHtml(h.normalized_unit || '')}</span>
                        </td>
                        <td>${Number(h.normalized_quantity || h.activity_quantity_snapshot).toFixed(2)} ${EcoPulseDashboard.escapeHtml(h.normalized_unit || '')}</td>
                        <td>${h.gwp_applied ? `${h.gwp_applied} (${h.gwp_source_snapshot || 'AR6'})` : '1.0 (CO2e)'}</td>
                        <td>
                          <strong>${Number(h.tonnes_co2e).toFixed(4)} t</strong>
                          <span class="d-block text-muted" style="font-size: 0.7rem;">${Number(h.kg_co2e).toFixed(2)} kg</span>
                        </td>
                        <td>
                          <code class="text-dark small" style="word-break: break-all;">${EcoPulseDashboard.escapeHtml(h.calculation_formula)}</code>
                        </td>
                        <td>${h.calculated_at}</td>
                        <td>
                          <small class="d-block">${EcoPulseDashboard.escapeHtml(h.recalculation_reason || 'Initial')}</small>
                          <small class="text-muted">by ${EcoPulseDashboard.escapeHtml(h.triggered_by_name || 'System')}</small>
                        </td>
                      </tr>
                    `).join('')}
                  </tbody>
                </table>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
          </div>
        </div>
      `;

      const bsModal = new bootstrap.Modal(historyModal);
      bsModal.show();

    } catch (err) {
      EcoPulseNotify.show(err.message || 'Failed to load calculation history', 'error');
    }
  };

  const exportRecords = async () => {
    try {
      const facilityId = document.getElementById('ws-facility-select')?.value || '';
      const periodId = document.getElementById('ws-period-select')?.value || '';
      const domainProfileId = workspaceData?.domain_profile?.id || '';

      const query = new URLSearchParams();
      if (facilityId) query.set('facility_id', facilityId);
      if (periodId) query.set('reporting_period_id', periodId);
      if (domainProfileId) query.set('domain_profile_id', domainProfileId);

      const qs = query.toString() ? `?${query.toString()}` : '';
      const baseUrl = window.EcoPulseConfig ? window.EcoPulseConfig.API_BASE : '';
      const activeOrgId = EcoPulseApi.getActiveOrgId();

      const headers = { 'Accept': 'text/csv' };
      if (activeOrgId) headers['X-Organization-Id'] = activeOrgId;

      const res = await fetch(`${baseUrl}/api/activity-records/export${qs}`, {
        method: 'GET',
        headers: headers,
        credentials: 'include'
      });

      if (!res.ok) throw new Error('Export request failed');
      const blob = await res.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `ecopulse_activity_records_${currentDomainCode || 'workspace'}_${new Date().toISOString().slice(0, 10)}.csv`;
      document.body.appendChild(a);
      a.click();
      a.remove();
      window.URL.revokeObjectURL(url);
      if (window.EcoPulseNotify) EcoPulseNotify.show('Activity records exported successfully.', 'success');
    } catch (err) {
      console.error('Export error:', err);
      if (window.EcoPulseNotify) EcoPulseNotify.show('Failed to export activity records.', 'error');
    }
  };

  return {
    openDomain,
    onContextChange,
    openIntakeModal,
    onCategorySelect,
    submitIntakeForm,
    handleIntakeSubmit,
    refreshRecords,
    exportRecords,
    submitRecord,
    validateRecord,
    rejectRecord,
    recalculateRecord,
    deleteRecord,
    triggerBatchRecalculate,
    viewHistory,
  };
})();

window.EcoPulseWorkspace = EcoPulseWorkspace;
