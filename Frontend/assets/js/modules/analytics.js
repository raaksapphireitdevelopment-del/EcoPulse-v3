/**
 * EcoPulse MVP V3 - Carbon Intelligence & Emission Analytics Module
 * Provides unified analytics across all 9 business domains with Chart.js visualizations.
 */

const EcoPulseAnalytics = (() => {
  let charts = {};
  let currentFilters = {
    facility_id: '',
    reporting_period_id: '',
    domain_profile_id: '',
    scope: '',
    calculation_status: '',
    date_from: '',
    date_to: '',
  };

  /**
   * Destroy existing Chart.js instances to avoid canvas reuse errors.
   */
  const destroyCharts = () => {
    Object.keys(charts).forEach(key => {
      if (charts[key]) {
        charts[key].destroy();
        delete charts[key];
      }
    });
  };

  /**
   * Load Carbon Intelligence Dashboard View
   */
  const loadDashboard = async () => {
    const container = document.getElementById('view-container');
    if (!container) return;

    container.innerHTML = `
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h2 class="fw-bold mb-1">Carbon Intelligence Dashboard</h2>
          <p class="text-secondary small mb-0">
            Real-time GHG operational footprint, scope distributions, and decoupled avoided emissions.
          </p>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-success btn-sm" onclick="EcoPulseAnalytics.exportReport()">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export Report (CSV)
          </button>
          <button class="btn btn-outline-secondary btn-sm" onclick="EcoPulseAnalytics.refresh()">
            <i class="bi bi-arrow-clockwise me-1"></i> Refresh
          </button>
        </div>
      </div>

      <!-- Dashboard Filter Bar -->
      <div class="ep-card p-3 mb-4 bg-white border">
        <div class="row g-2 align-items-center">
          <div class="col-md-3">
            <label class="form-label small text-muted mb-1">Facility</label>
            <select id="filter-facility" class="form-select form-select-sm" onchange="EcoPulseAnalytics.applyFilters()">
              <option value="">All Facilities</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label small text-muted mb-1">Reporting Period</label>
            <select id="filter-period" class="form-select form-select-sm" onchange="EcoPulseAnalytics.applyFilters()">
              <option value="">All Periods</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small text-muted mb-1">Scope</label>
            <select id="filter-scope" class="form-select form-select-sm" onchange="EcoPulseAnalytics.applyFilters()">
              <option value="">All Scopes</option>
              <option value="scope_1">Scope 1</option>
              <option value="scope_2">Scope 2</option>
              <option value="scope_3">Scope 3</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small text-muted mb-1">Status</label>
            <select id="filter-status" class="form-select form-select-sm" onchange="EcoPulseAnalytics.applyFilters()">
              <option value="">All Calculation States</option>
              <option value="validated">Validated Only</option>
              <option value="demo">Demo / Provisional</option>
            </select>
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <button class="btn btn-brand btn-sm w-100" onclick="EcoPulseAnalytics.resetFilters()">
              <i class="bi bi-x-circle me-1"></i> Reset Filters
            </button>
          </div>
        </div>
      </div>

      <!-- Main KPI Row -->
      <div id="analytics-kpi-container" class="row g-3 mb-4">
        <div class="col-12 text-center py-4">
          <div class="spinner-border text-success" role="status"></div>
        </div>
      </div>

      <!-- Charts Row 1: Scope Donut + Domain Distribution -->
      <div class="row g-3 mb-4">
        <div class="col-lg-5">
          <div class="card border-0 shadow-sm rounded-3 p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h6 class="fw-bold mb-0">Emissions by Scope</h6>
              <span class="badge bg-light text-secondary border">GHG Protocol</span>
            </div>
            <div style="position: relative; height: 260px;">
              <canvas id="scopeDonutChart"></canvas>
            </div>
            <div id="scope-legend" class="mt-2 text-center small text-secondary"></div>
          </div>
        </div>

        <div class="col-lg-7">
          <div class="card border-0 shadow-sm rounded-3 p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h6 class="fw-bold mb-0">Domain Distribution</h6>
              <span class="badge bg-light text-secondary border">Active Domains</span>
            </div>
            <div style="position: relative; height: 260px;">
              <canvas id="domainBarChart"></canvas>
            </div>
          </div>
        </div>
      </div>

      <!-- Charts Row 2: Monthly Trends + Facility Ranking -->
      <div class="row g-3 mb-4">
        <div class="col-lg-7">
          <div class="card border-0 shadow-sm rounded-3 p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h6 class="fw-bold mb-0">Monthly Operational Trends</h6>
              <span class="badge bg-light text-secondary border">tCO2e / Month</span>
            </div>
            <div style="position: relative; height: 260px;">
              <canvas id="trendLineChart"></canvas>
            </div>
          </div>
        </div>

        <div class="col-lg-5">
          <div class="card border-0 shadow-sm rounded-3 p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h6 class="fw-bold mb-0">Emissions by Facility</h6>
              <span class="badge bg-light text-secondary border">Top Emitters</span>
            </div>
            <div style="position: relative; height: 260px;">
              <canvas id="facilityBarChart"></canvas>
            </div>
          </div>
        </div>
      </div>

      <!-- Intensity Indicators Section -->
      <div class="card border-0 shadow-sm rounded-3 p-3 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h6 class="fw-bold mb-0">Emission Intensity Indicators</h6>
            <p class="text-muted small mb-0">Calculated strictly where valid, compatible numerator and denominator data exist.</p>
          </div>
          <span class="badge bg-light text-success border">Verified Basis</span>
        </div>
        <div class="row g-3" id="intensity-container">
          <div class="col-12 text-center py-2 text-muted small">Loading intensity metrics...</div>
        </div>
      </div>
    `;

    await loadFilterOptions();
    await fetchData();
  };

  /**
   * Load options for facilities and reporting periods in filters.
   */
  const loadFilterOptions = async () => {
    try {
      const [facRes, perRes] = await Promise.all([
        EcoPulseApi.get('/api/facilities'),
        EcoPulseApi.get('/api/reporting-periods'),
      ]);

      const facSelect = document.getElementById('filter-facility');
      if (facSelect && facRes.success && facRes.data.facilities) {
        facRes.data.facilities.forEach(f => {
          const opt = document.createElement('option');
          opt.value = f.id;
          opt.textContent = `${f.name} (${f.city || f.country})`;
          facSelect.appendChild(opt);
        });
      }

      const perSelect = document.getElementById('filter-period');
      if (perSelect && perRes.success && perRes.data) {
        perRes.data.forEach(p => {
          const opt = document.createElement('option');
          opt.value = p.id;
          opt.textContent = p.name;
          perSelect.appendChild(opt);
        });
      }
    } catch (err) {
      console.warn('Failed to load filter options:', err);
    }
  };

  /**
   * Fetch all analytics data and render charts and KPI cards.
   */
  const fetchData = async () => {
    destroyCharts();
    const query = new URLSearchParams();
    Object.keys(currentFilters).forEach(k => {
      if (currentFilters[k]) query.append(k, currentFilters[k]);
    });
    const qs = query.toString() ? `?${query.toString()}` : '';

    try {
      const [overviewRes, scopeRes, domainRes, facRes, trendRes, intensityRes] = await Promise.all([
        EcoPulseApi.get(`/api/analytics/overview${qs}`),
        EcoPulseApi.get(`/api/analytics/emissions-by-scope${qs}`),
        EcoPulseApi.get(`/api/analytics/emissions-by-domain${qs}`),
        EcoPulseApi.get(`/api/analytics/emissions-by-facility${qs}`),
        EcoPulseApi.get(`/api/analytics/emissions-trends${qs}`),
        EcoPulseApi.get(`/api/analytics/emission-intensity${qs}`),
      ]);

      renderKpis(overviewRes.data);
      renderScopeDonut(scopeRes.data);
      renderDomainBar(domainRes.data);
      renderTrendLine(trendRes.data);
      renderFacilityBar(facRes.data);
      renderIntensity(intensityRes.data);
    } catch (err) {
      console.error('Analytics fetch error:', err);
      EcoPulseNotify.error('Failed to load analytics data.');
    }
  };

  /**
   * Render Main KPI Cards
   */
  const renderKpis = (data) => {
    const container = document.getElementById('analytics-kpi-container');
    if (!container || !data) return;

    const kpis = data.kpis || {};
    const opTotal = (kpis.total_operational_tco2e || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const s1 = (kpis.scope_1_tco2e || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const s2 = (kpis.scope_2_tco2e || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const s3 = (kpis.scope_3_tco2e || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const avoided = (kpis.avoided_emissions_tco2e || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const pending = kpis.pending_records || 0;
    const validated = kpis.validated_calculations || 0;

    container.innerHTML = `
      <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-success">
          <div class="text-muted small fw-semibold">TOTAL OPERATIONAL FOOTPRINT</div>
          <div class="fs-3 fw-bold text-dark mt-1">${opTotal} <span class="fs-6 fw-normal text-muted">tCO2e</span></div>
          <div class="small text-secondary mt-1"><i class="bi bi-shield-check text-success me-1"></i>Scopes 1, 2 & 3 Combined</div>
        </div>
      </div>

      <div class="col-md-2">
        <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
          <div class="text-muted small fw-semibold">SCOPE 1 (DIRECT)</div>
          <div class="fs-4 fw-bold text-dark mt-1">${s1} <span class="fs-6 fw-normal text-muted">tCO2e</span></div>
          <div class="small text-muted mt-1">Fuel & Process</div>
        </div>
      </div>

      <div class="col-md-2">
        <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
          <div class="text-muted small fw-semibold">SCOPE 2 (ELECTRICITY)</div>
          <div class="fs-4 fw-bold text-dark mt-1">${s2} <span class="fs-6 fw-normal text-muted">tCO2e</span></div>
          <div class="small text-muted mt-1">Purchased Grid</div>
        </div>
      </div>

      <div class="col-md-2">
        <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
          <div class="text-muted small fw-semibold">SCOPE 3 (VALUE CHAIN)</div>
          <div class="fs-4 fw-bold text-dark mt-1">${s3} <span class="fs-6 fw-normal text-muted">tCO2e</span></div>
          <div class="small text-muted mt-1">Upstream / Downstream</div>
        </div>
      </div>

      <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-info">
          <div class="text-muted small fw-semibold">AVOIDED EMISSIONS (SEGREGATED)</div>
          <div class="fs-3 fw-bold text-info mt-1">${avoided} <span class="fs-6 fw-normal text-muted">tCO2e</span></div>
          <div class="small text-secondary mt-1"><i class="bi bi-info-circle text-info me-1"></i>Kept Separate from Inventory</div>
        </div>
      </div>
    `;
  };

  /**
   * Render Scope Donut Chart
   */
  const renderScopeDonut = (data) => {
    const ctx = document.getElementById('scopeDonutChart')?.getContext('2d');
    if (!ctx || !data || !data.scopes) return;

    const scopes = data.scopes;
    const s1 = scopes.scope_1?.tonnes || 0;
    const s2 = scopes.scope_2?.tonnes || 0;
    const s3 = scopes.scope_3?.tonnes || 0;
    const unc = scopes.unclassified?.tonnes || 0;

    charts['scopeDonut'] = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Scope 1 (Direct)', 'Scope 2 (Electricity)', 'Scope 3 (Value Chain)', 'Unclassified'],
        datasets: [{
          data: [s1, s2, s3, unc],
          backgroundColor: ['#2d6a4f', '#52b788', '#95d5b2', '#adb5bd'],
          borderWidth: 2,
          borderColor: '#ffffff',
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom' },
          tooltip: {
            callbacks: {
              label: (context) => ` ${context.label}: ${context.raw.toFixed(2)} tCO2e`,
            },
          },
        },
      },
    });
  };

  /**
   * Render Domain Bar Chart
   */
  const renderDomainBar = (data) => {
    const ctx = document.getElementById('domainBarChart')?.getContext('2d');
    if (!ctx || !data || !data.domains) return;

    const domains = data.domains;
    const labels = domains.map(d => d.domain_name);
    const opTotals = domains.map(d => d.operational_total_tco2e);

    charts['domainBar'] = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{
          label: 'Operational Emissions (tCO2e)',
          data: opTotals,
          backgroundColor: '#1b4332',
          borderRadius: 4,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: { beginAtZero: true, title: { display: true, text: 'tCO2e' } },
        },
        plugins: { legend: { display: false } },
      },
    });
  };

  /**
   * Render Monthly Trendlines
   */
  const renderTrendLine = (data) => {
    const ctx = document.getElementById('trendLineChart')?.getContext('2d');
    if (!ctx || !data || !data.monthly_trends) return;

    const trends = data.monthly_trends;
    const labels = trends.map(t => t.month);
    const totals = trends.map(t => t.operational_total_tco2e);

    charts['trendLine'] = new Chart(ctx, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: 'Operational Footprint (tCO2e)',
          data: totals,
          borderColor: '#2d6a4f',
          backgroundColor: 'rgba(45, 106, 79, 0.1)',
          fill: true,
          tension: 0.3,
          pointRadius: 4,
          pointBackgroundColor: '#1b4332',
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: { beginAtZero: true, title: { display: true, text: 'tCO2e' } },
        },
      },
    });
  };

  /**
   * Render Facility Bar Chart
   */
  const renderFacilityBar = (data) => {
    const ctx = document.getElementById('facilityBarChart')?.getContext('2d');
    if (!ctx || !data || !data.facilities) return;

    const facs = data.facilities.slice(0, 5); // top 5
    const labels = facs.map(f => f.facility_name);
    const values = facs.map(f => f.operational_total_tco2e);

    charts['facilityBar'] = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{
          label: 'Emissions (tCO2e)',
          data: values,
          backgroundColor: '#40916c',
          borderRadius: 4,
        }],
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          x: { beginAtZero: true, title: { display: true, text: 'tCO2e' } },
        },
        plugins: { legend: { display: false } },
      },
    });
  };

  /**
   * Render Intensity Metrics
   */
  const renderIntensity = (data) => {
    const container = document.getElementById('intensity-container');
    if (!container || !data || !data.indicators) return;

    if (data.indicators.length === 0) {
      container.innerHTML = `<div class="col-12 text-center text-muted small py-2">No intensity indicators available.</div>`;
      return;
    }

    container.innerHTML = data.indicators.map(ind => {
      const isCalculated = (ind.status === 'calculated');
      const valText = isCalculated ? `${ind.value} ${ind.unit}` : 'Insufficient Data';
      const badge = isCalculated
        ? `<span class="badge bg-success-subtle text-success">Calculated</span>`
        : `<span class="badge bg-secondary-subtle text-secondary">Data Required</span>`;

      return `
        <div class="col-md-3">
          <div class="p-3 border rounded-3 bg-light bg-opacity-50 h-100">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <span class="fw-semibold small text-dark">${ind.indicator}</span>
              ${badge}
            </div>
            <div class="fs-5 fw-bold ${isCalculated ? 'text-dark' : 'text-muted'} mb-1">${valText}</div>
            <p class="text-secondary small mb-0" style="font-size: 0.75rem;">${ind.calculation_basis}</p>
          </div>
        </div>
      `;
    }).join('');
  };

  /**
   * Apply filters from input controls
   */
  const applyFilters = () => {
    currentFilters.facility_id = document.getElementById('filter-facility')?.value || '';
    currentFilters.reporting_period_id = document.getElementById('filter-period')?.value || '';
    currentFilters.scope = document.getElementById('filter-scope')?.value || '';
    currentFilters.calculation_status = document.getElementById('filter-status')?.value || '';
    fetchData();
  };

  const resetFilters = () => {
    currentFilters = {
      facility_id: '',
      reporting_period_id: '',
      domain_profile_id: '',
      scope: '',
      calculation_status: '',
      date_from: '',
      date_to: '',
    };
    const f1 = document.getElementById('filter-facility');
    const f2 = document.getElementById('filter-period');
    const f3 = document.getElementById('filter-scope');
    const f4 = document.getElementById('filter-status');
    if (f1) f1.value = '';
    if (f2) f2.value = '';
    if (f3) f3.value = '';
    if (f4) f4.value = '';
    fetchData();
  };

  const refresh = () => {
    fetchData();
  };

  const exportReport = async () => {
    try {
      const query = new URLSearchParams();
      Object.keys(currentFilters).forEach(k => {
        if (currentFilters[k]) query.append(k, currentFilters[k]);
      });
      const qs = query.toString() ? `?${query.toString()}` : '';
      const baseUrl = window.EcoPulseConfig ? window.EcoPulseConfig.API_BASE : '';
      const activeOrgId = EcoPulseApi.getActiveOrgId();

      const headers = { 'Accept': 'text/csv' };
      if (activeOrgId) headers['X-Organization-Id'] = activeOrgId;

      const res = await fetch(`${baseUrl}/api/analytics/export${qs}`, {
        method: 'GET',
        headers: headers,
        credentials: 'include'
      });

      if (!res.ok) throw new Error('Export request failed');
      const blob = await res.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `ecopulse_emissions_report_${new Date().toISOString().slice(0, 10)}.csv`;
      document.body.appendChild(a);
      a.click();
      a.remove();
      window.URL.revokeObjectURL(url);
      if (window.EcoPulseNotify) EcoPulseNotify.success('Emissions report exported successfully.');
    } catch (err) {
      console.error('Export error:', err);
      if (window.EcoPulseNotify) EcoPulseNotify.error('Failed to export emissions report.');
    }
  };

  return {
    loadDashboard,
    applyFilters,
    resetFilters,
    refresh,
    exportReport,
  };
})();
