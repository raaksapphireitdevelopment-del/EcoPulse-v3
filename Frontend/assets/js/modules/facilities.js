/**
 * EcoPulse MVP V3 - Facility Management Module
 */

const EcoPulseFacilities = (() => {
  let cachedProfiles = [];

  const loadFacilitiesView = async (search = '', status = '') => {
    const container = document.getElementById('view-container');
    if (!container) return;

    container.innerHTML = `
      <div class="d-flex justify-content-center py-5">
        <div class="spinner-border text-success" role="status"></div>
      </div>
    `;

    try {
      const [facRes, profRes] = await Promise.all([
        window.EcoPulseApi.get(`/api/facilities?search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}`),
        window.EcoPulseApi.get('/api/domains/profiles'),
      ]);

      cachedProfiles = (profRes.data || []).filter(p => p.status === 'active');
      const facilities = facRes.data?.facilities || [];
      const total = facRes.data?.total || 0;

      container.innerHTML = `
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
          <div>
            <h4 class="fw-bold mb-1">Facility Management</h4>
            <p class="text-secondary small mb-0">Manage industrial sites, plants, hospitals, and real-estate buildings operated under your organization.</p>
          </div>
          <div>
            <button class="btn btn-brand btn-sm" onclick="EcoPulseFacilities.openAddModal()">
              <i class="bi bi-plus-circle me-1"></i> Add Facility
            </button>
          </div>
        </div>

        <!-- Filter / Search Bar -->
        <div class="ep-card p-3 mb-4">
          <div class="row g-2 align-items-center">
            <div class="col-md-5">
              <div class="input-group input-group-sm">
                <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="facility-search-input" class="form-control" placeholder="Search by facility name, code, or city..." value="${EcoPulseDashboard.escapeHtml(search)}">
              </div>
            </div>
            <div class="col-md-4">
              <select id="facility-status-select" class="form-select form-select-sm">
                <option value="">All Operational Statuses</option>
                <option value="active" ${status === 'active' ? 'selected' : ''}>Active</option>
                <option value="decommissioned" ${status === 'decommissioned' ? 'selected' : ''}>Decommissioned</option>
              </select>
            </div>
            <div class="col-md-3 text-md-end">
              <button class="btn btn-sm btn-outline-secondary px-3" onclick="EcoPulseFacilities.applyFilters()">
                Filter
              </button>
              ${search || status ? `<button class="btn btn-sm btn-link text-decoration-none" onclick="EcoPulseFacilities.loadFacilitiesView()">Reset</button>` : ''}
            </div>
          </div>
        </div>

        <!-- Facilities Table -->
        <div class="ep-card p-0 overflow-hidden">
          ${facilities.length === 0 ? `
            <div class="text-center py-5 text-muted">
              <i class="bi bi-building-slash fs-1 d-block mb-2"></i>
              No facilities found matching criteria. Click "Add Facility" to create one.
            </div>
          ` : `
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary small text-uppercase">
                  <tr>
                    <th class="ps-3">Facility Name & Code</th>
                    <th>Domain</th>
                    <th>Type</th>
                    <th>Location</th>
                    <th>Floor Area</th>
                    <th>Status</th>
                    <th class="text-end pe-3">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  ${facilities.map(f => `
                    <tr>
                      <td class="ps-3">
                        <strong class="d-block text-dark">${EcoPulseDashboard.escapeHtml(f.name)}</strong>
                        <span class="text-muted" style="font-size: 0.76rem;">${EcoPulseDashboard.escapeHtml(f.facility_code || 'N/A')}</span>
                      </td>
                      <td>
                        <span class="badge bg-light text-dark border">
                          <i class="bi ${EcoPulseDashboard.escapeHtml(f.domain_icon || 'bi-gear')} me-1 text-success"></i>
                          ${EcoPulseDashboard.escapeHtml(f.domain_name || 'General')}
                        </span>
                      </td>
                      <td class="small text-secondary">${EcoPulseDashboard.escapeHtml(f.facility_type)}</td>
                      <td class="small text-secondary">
                        ${EcoPulseDashboard.escapeHtml(f.city)}, ${EcoPulseDashboard.escapeHtml(f.country)}
                      </td>
                      <td class="small text-secondary">
                        ${f.gross_area_sqm ? `${Number(f.gross_area_sqm).toLocaleString()} m²` : '—'}
                      </td>
                      <td>
                        <span class="badge ${f.status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'} small">
                          ${EcoPulseDashboard.escapeHtml(f.status)}
                        </span>
                      </td>
                      <td class="text-end pe-3">
                        <button class="btn btn-sm btn-outline-primary py-0 px-2 me-1" onclick="EcoPulseFacilities.openEditModal(${f.id})">
                          <i class="bi bi-pencil"></i>
                        </button>
                        ${f.status === 'active' ? `
                          <button class="btn btn-sm btn-outline-danger py-0 px-2" onclick="EcoPulseFacilities.deactivateFacility(${f.id}, '${EcoPulseDashboard.escapeHtml(f.name)}')">
                            <i class="bi bi-archive"></i>
                          </button>
                        ` : ''}
                      </td>
                    </tr>
                  `).join('')}
                </tbody>
              </table>
            </div>
            <div class="p-3 border-top d-flex justify-content-between align-items-center small text-secondary">
              <span>Showing ${facilities.length} of ${total} facilities</span>
            </div>
          `}
        </div>
      `;

    } catch (err) {
      container.innerHTML = `<div class="alert alert-danger">${EcoPulseDashboard.escapeHtml(err.message)}</div>`;
    }
  };

  const applyFilters = () => {
    const search = document.getElementById('facility-search-input')?.value.trim() || '';
    const status = document.getElementById('facility-status-select')?.value || '';
    loadFacilitiesView(search, status);
  };

  const openAddModal = () => {
    const modalEl = document.getElementById('facilityModal');
    if (!modalEl) return;

    document.getElementById('facilityModalTitle').textContent = 'Add Operational Facility';
    document.getElementById('facility-form').reset();
    document.getElementById('facility_id').value = '';

    // Populate domain profiles dropdown
    const select = document.getElementById('facility_domain_profile_id');
    select.innerHTML = '<option value="">-- Associate Business Domain --</option>' +
      cachedProfiles.map(p => `<option value="${p.id}">${EcoPulseDashboard.escapeHtml(p.profile_name)} (${EcoPulseDashboard.escapeHtml(p.domain_name)})</option>`).join('');

    const modal = new bootstrap.Modal(modalEl);
    modal.show();
  };

  const openEditModal = async (id) => {
    const modalEl = document.getElementById('facilityModal');
    if (!modalEl) return;

    try {
      const res = await window.EcoPulseApi.get(`/api/facilities/${id}`);
      const f = res.data;

      document.getElementById('facilityModalTitle').textContent = 'Edit Facility Details';
      document.getElementById('facility_id').value = f.id;
      document.getElementById('facility_name').value = f.name;
      document.getElementById('facility_code').value = f.facility_code || '';
      document.getElementById('facility_type').value = f.facility_type;
      document.getElementById('facility_country').value = f.country;
      document.getElementById('facility_state').value = f.state_region || '';
      document.getElementById('facility_city').value = f.city;
      document.getElementById('facility_postal').value = f.postal_code || '';
      document.getElementById('facility_timezone').value = f.timezone || 'UTC';
      document.getElementById('facility_area').value = f.gross_area_sqm || '';
      document.getElementById('facility_address').value = f.address || '';
      document.getElementById('facility_status').value = f.status || 'active';

      const select = document.getElementById('facility_domain_profile_id');
      select.innerHTML = '<option value="">-- Associate Business Domain --</option>' +
        cachedProfiles.map(p => `<option value="${p.id}" ${f.domain_profile_id == p.id ? 'selected' : ''}>${EcoPulseDashboard.escapeHtml(p.profile_name)} (${EcoPulseDashboard.escapeHtml(p.domain_name)})</option>`).join('');

      const modal = new bootstrap.Modal(modalEl);
      modal.show();

    } catch (err) {
      EcoPulseNotify.error(err.message || 'Failed to fetch facility details.');
    }
  };

  const saveFacility = async (event) => {
    event.preventDefault();
    const id = document.getElementById('facility_id').value;

    const payload = {
      name: document.getElementById('facility_name').value.trim(),
      facility_code: document.getElementById('facility_code').value.trim(),
      domain_profile_id: document.getElementById('facility_domain_profile_id').value || null,
      facility_type: document.getElementById('facility_type').value.trim(),
      country: document.getElementById('facility_country').value.trim(),
      state_region: document.getElementById('facility_state').value.trim(),
      city: document.getElementById('facility_city').value.trim(),
      postal_code: document.getElementById('facility_postal').value.trim(),
      timezone: document.getElementById('facility_timezone').value.trim() || 'UTC',
      gross_area_sqm: document.getElementById('facility_area').value || null,
      address: document.getElementById('facility_address').value.trim(),
      status: document.getElementById('facility_status').value || 'active',
    };

    try {
      if (id) {
        await window.EcoPulseApi.put(`/api/facilities/${id}`, payload);
        EcoPulseNotify.success('Facility details updated.');
      } else {
        await window.EcoPulseApi.post('/api/facilities', payload);
        EcoPulseNotify.success('New facility registered.');
      }

      const modalInstance = bootstrap.Modal.getInstance(document.getElementById('facilityModal'));
      if (modalInstance) modalInstance.hide();

      loadFacilitiesView();

    } catch (err) {
      EcoPulseNotify.error(err.message || 'Error saving facility.');
    }
  };

  const deactivateFacility = async (id, name) => {
    if (!confirm(`Are you sure you want to decommission/deactivate facility '${name}'?`)) return;

    try {
      await window.EcoPulseApi.delete(`/api/facilities/${id}`);
      EcoPulseNotify.warning(`Facility '${name}' decommissioned.`);
      loadFacilitiesView();
    } catch (err) {
      EcoPulseNotify.error(err.message || 'Failed to deactivate facility.');
    }
  };

  return {
    loadFacilitiesView,
    applyFilters,
    openAddModal,
    openEditModal,
    saveFacility,
    deactivateFacility,
  };
})();

window.EcoPulseFacilities = EcoPulseFacilities;
