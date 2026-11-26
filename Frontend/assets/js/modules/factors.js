/**
 * EcoPulse MVP V3 - Emission Factors Catalog Module
 * Allows searching, filtering, and administrator validation of GHG factors.
 */

const EcoPulseFactors = (() => {
  let factorsList = [];
  let currentFilter = {
    search: '',
    scope: '',
    validation_status: '',
    geography: '',
  };

  const loadFactorsView = async () => {
    const container = document.getElementById('view-container');
    if (!container) return;

    container.innerHTML = `
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h2 class="fw-bold mb-1">Emission Factor Catalog</h2>
          <p class="text-secondary small mb-0">
            Authoritative GHG conversion coefficients from DEFRA 2024, IPCC AR6, US EPA eGRID, and verified national registries.
          </p>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-success btn-sm" onclick="EcoPulseFactors.exportCsv()">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export Factors (CSV)
          </button>
          <button class="btn btn-outline-secondary btn-sm" onclick="EcoPulseFactors.refresh()">
            <i class="bi bi-arrow-clockwise me-1"></i> Refresh
          </button>
        </div>
      </div>

      <!-- Filters Bar -->
      <div class="ep-card p-3 mb-4 bg-white border">
        <div class="row g-2 align-items-center">
          <div class="col-md-4">
            <div class="input-group input-group-sm">
              <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
              <input type="text" id="ef-search" class="form-control" placeholder="Search by name, source, or reference..."
                     onkeyup="if(event.key==='Enter') EcoPulseFactors.applyFilter()">
            </div>
          </div>
          <div class="col-md-2">
            <select id="ef-scope" class="form-select form-select-sm" onchange="EcoPulseFactors.applyFilter()">
              <option value="">All Scopes</option>
              <option value="scope_1">Scope 1 (Direct)</option>
              <option value="scope_2">Scope 2 (Electricity)</option>
              <option value="scope_3">Scope 3 (Value Chain)</option>
            </select>
          </div>
          <div class="col-md-2">
            <select id="ef-status" class="form-select form-select-sm" onchange="EcoPulseFactors.applyFilter()">
              <option value="">All Statuses</option>
              <option value="validated">Validated</option>
              <option value="pending_review">Pending Review</option>
              <option value="demo">Demo / Sandbox</option>
              <option value="expired">Expired</option>
            </select>
          </div>
          <div class="col-md-2">
            <select id="ef-geo" class="form-select form-select-sm" onchange="EcoPulseFactors.applyFilter()">
              <option value="">All Geographies</option>
              <option value="Global">Global</option>
              <option value="India">India</option>
              <option value="United States">United States</option>
              <option value="United Kingdom">United Kingdom</option>
            </select>
          </div>
          <div class="col-md-2">
            <button class="btn btn-brand btn-sm w-100" onclick="EcoPulseFactors.applyFilter()">
              <i class="bi bi-funnel me-1"></i> Filter
            </button>
          </div>
        </div>
      </div>

      <!-- Factors Table Card -->
      <div class="ep-card">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr class="small text-secondary">
                <th>Factor Name</th>
                <th>Category & Domain</th>
                <th>Scope</th>
                <th>Factor Value</th>
                <th>Activity Unit</th>
                <th>Geography</th>
                <th>Source & Ref</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody id="factors-table-body">
              <tr>
                <td colspan="9" class="text-center py-4 text-muted">
                  <div class="spinner-border spinner-border-sm text-success me-2"></div> Loading emission factors...
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    `;

    await fetchFactors();
  };

  const fetchFactors = async () => {
    const tbody = document.getElementById('factors-table-body');
    if (!tbody) return;

    try {
      const queryParams = new URLSearchParams();
      if (currentFilter.search) queryParams.set('search', currentFilter.search);
      if (currentFilter.scope) queryParams.set('scope', currentFilter.scope);
      if (currentFilter.validation_status) queryParams.set('validation_status', currentFilter.validation_status);
      if (currentFilter.geography) queryParams.set('geography', currentFilter.geography);
      queryParams.set('limit', '100');

      const res = await window.EcoPulseApi.get(`/api/emission-factors?${queryParams.toString()}`);
      factorsList = res.data.factors || [];

      if (factorsList.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="9" class="text-center py-5 text-muted">
              <i class="bi bi-inbox fs-2 d-block mb-2"></i>
              No emission factors match the selected filters.
            </td>
          </tr>
        `;
        return;
      }

      tbody.innerHTML = factorsList.map(f => {
        const isDemo = (Number(f.is_demo) === 1 || f.validation_status === 'demo');
        const statusBadge = isDemo
          ? '<span class="badge bg-warning text-dark"><i class="bi bi-flask me-1"></i>Demo</span>'
          : (f.validation_status === 'validated' || f.validation_status === 'verified')
            ? '<span class="badge bg-success-subtle text-success border border-success"><i class="bi bi-check-circle me-1"></i>Validated</span>'
            : `<span class="badge bg-secondary">${f.validation_status}</span>`;

        const scopeBadge = f.scope === 'scope_1'
          ? '<span class="badge-scope-1">Scope 1</span>'
          : f.scope === 'scope_2'
            ? '<span class="badge-scope-2">Scope 2</span>'
            : '<span class="badge-scope-3">Scope 3</span>';

        return `
          <tr>
            <td>
              <strong class="d-block text-dark">${EcoPulseDashboard.escapeHtml(f.factor_name)}</strong>
              <small class="text-muted">v${EcoPulseDashboard.escapeHtml(f.version)} &bull; ${EcoPulseDashboard.escapeHtml(f.gas_detail || 'CO2e')}</small>
            </td>
            <td>
              <span class="d-block small text-dark">${EcoPulseDashboard.escapeHtml(f.category_name || '-')}</span>
              <span class="badge bg-light text-secondary border" style="font-size: 0.7rem;">${EcoPulseDashboard.escapeHtml(f.domain_name || '-')}</span>
            </td>
            <td>${scopeBadge}</td>
            <td>
              <strong class="text-dark font-monospace">${Number(f.factor_value).toFixed(6)}</strong>
              <small class="text-muted d-block" style="font-size: 0.72rem;">${EcoPulseDashboard.escapeHtml(f.co2e_unit)}</small>
            </td>
            <td><span class="badge bg-white text-dark border">${EcoPulseDashboard.escapeHtml(f.activity_unit)}</span></td>
            <td><i class="bi bi-geo-alt text-secondary me-1"></i>${EcoPulseDashboard.escapeHtml(f.geography)}</td>
            <td>
              <span class="d-block small text-truncate" style="max-width: 180px;" title="${EcoPulseDashboard.escapeHtml(f.source)}">
                ${EcoPulseDashboard.escapeHtml(f.source)}
              </span>
              <small class="text-muted text-truncate d-block" style="max-width: 180px;" title="${EcoPulseDashboard.escapeHtml(f.source_reference || '')}">
                ${EcoPulseDashboard.escapeHtml(f.source_reference || '')}
              </small>
            </td>
            <td>${statusBadge}</td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-secondary py-0 px-2" title="View Formula Reference" onclick="EcoPulseFactors.viewDetails(${f.id})">
                <i class="bi bi-info-circle"></i>
              </button>
            </td>
          </tr>
        `;
      }).join('');

    } catch (err) {
      tbody.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-danger">Failed to load factors: ${EcoPulseDashboard.escapeHtml(err.message)}</td></tr>`;
    }
  };

  const applyFilter = () => {
    currentFilter.search = document.getElementById('ef-search')?.value.trim() || '';
    currentFilter.scope = document.getElementById('ef-scope')?.value || '';
    currentFilter.validation_status = document.getElementById('ef-status')?.value || '';
    currentFilter.geography = document.getElementById('ef-geo')?.value || '';
    fetchFactors();
  };

  const refresh = () => {
    fetchFactors();
  };

  const viewDetails = (id) => {
    const f = factorsList.find(item => item.id == id);
    if (!f) return;
    alert(
      `Factor: ${f.factor_name}\nValue: ${f.factor_value} ${f.co2e_unit}/${f.activity_unit}\nScope: ${f.scope}\nSource: ${f.source}\nRef: ${f.source_reference || 'N/A'}\nGeography: ${f.geography}\nStatus: ${f.validation_status} (Demo: ${f.is_demo ? 'Yes' : 'No'})`
    );
  };

  const exportCsv = () => {
    if (!factorsList || factorsList.length === 0) {
      alert('No emission factors loaded to export.');
      return;
    }
    const headers = ['Factor Name', 'Category', 'Domain', 'Scope', 'Factor Value', 'Unit', 'Geography', 'Source', 'Reference URL', 'Status'];
    const rows = factorsList.map(f => [
      `"${(f.factor_name || '').replace(/"/g, '""')}"`,
      `"${(f.category_name || '').replace(/"/g, '""')}"`,
      `"${(f.domain_name || '').replace(/"/g, '""')}"`,
      `"${(f.scope || '').replace(/"/g, '""')}"`,
      f.factor_value ?? 0,
      `"${(f.activity_unit || f.unit || '').replace(/"/g, '""')}"`,
      `"${(f.geography || '').replace(/"/g, '""')}"`,
      `"${(f.source || '').replace(/"/g, '""')}"`,
      `"${(f.source_reference || '').replace(/"/g, '""')}"`,
      `"${(f.validation_status || '').replace(/"/g, '""')}"`,
    ]);
    const csvContent = '\uFEFF' + [headers.join(','), ...rows.map(r => r.join(','))].join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `ecopulse_emission_factors_${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  };

  return {
    loadFactorsView,
    applyFilter,
    refresh,
    viewDetails,
    exportCsv,
  };
})();

window.EcoPulseFactors = EcoPulseFactors;
