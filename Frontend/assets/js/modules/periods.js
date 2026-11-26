/**
 * EcoPulse MVP V3 - Reporting Periods Module
 */

const EcoPulsePeriods = (() => {
  const loadPeriodsView = async () => {
    const container = document.getElementById('view-container');
    if (!container) return;

    container.innerHTML = `
      <div class="d-flex justify-content-center py-5">
        <div class="spinner-border text-success" role="status"></div>
      </div>
    `;

    try {
      const res = await window.EcoPulseApi.get('/api/reporting-periods');
      const periods = res.data || [];

      container.innerHTML = `
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
          <div>
            <h4 class="fw-bold mb-1">Reporting Periods</h4>
            <p class="text-secondary small mb-0">Define fiscal, annual, or quarterly carbon accounting windows and lock closed cycles.</p>
          </div>
          <div>
            <button class="btn btn-brand btn-sm" onclick="EcoPulsePeriods.openAddModal()">
              <i class="bi bi-calendar-plus me-1"></i> Create Period
            </button>
          </div>
        </div>

        <div class="ep-card p-0 overflow-hidden">
          ${periods.length === 0 ? `
            <div class="text-center py-5 text-muted">
              <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
              No reporting periods created. Click "Create Period" to start tracking.
            </div>
          ` : `
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary small text-uppercase">
                  <tr>
                    <th class="ps-3">Period Name</th>
                    <th>Cycle Type</th>
                    <th>Date Window</th>
                    <th>Activity Data</th>
                    <th>Status</th>
                    <th class="text-end pe-3">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  ${periods.map(p => `
                    <tr>
                      <td class="ps-3">
                        <strong class="d-block text-dark">${EcoPulseDashboard.escapeHtml(p.name)}</strong>
                      </td>
                      <td>
                        <span class="badge bg-light text-dark border text-capitalize">
                          ${EcoPulseDashboard.escapeHtml(p.period_type)}
                        </span>
                      </td>
                      <td class="small text-secondary">
                        <i class="bi bi-clock-history me-1"></i>${p.start_date} to ${p.end_date}
                      </td>
                      <td class="small text-secondary">
                        <span class="badge bg-light text-dark border">
                          ${p.activity_records_count || 0} Records
                        </span>
                      </td>
                      <td>
                        <span class="badge ${p.is_closed ? 'bg-secondary-subtle text-secondary' : 'bg-success-subtle text-success'} small">
                          ${p.is_closed ? 'Closed & Locked' : 'Active Intake'}
                        </span>
                      </td>
                      <td class="text-end pe-3">
                        ${!p.is_closed ? `
                          <button class="btn btn-sm btn-outline-warning py-0 px-2 me-1" onclick="EcoPulsePeriods.closePeriod(${p.id}, '${EcoPulseDashboard.escapeHtml(p.name)}')">
                            <i class="bi bi-lock me-1"></i> Close Period
                          </button>
                        ` : `
                          <span class="text-muted small"><i class="bi bi-lock-fill"></i> Locked</span>
                        `}
                      </td>
                    </tr>
                  `).join('')}
                </tbody>
              </table>
            </div>
          `}
        </div>
      `;

    } catch (err) {
      container.innerHTML = `<div class="alert alert-danger">${EcoPulseDashboard.escapeHtml(err.message)}</div>`;
    }
  };

  const openAddModal = () => {
    const modalEl = document.getElementById('periodModal');
    if (!modalEl) return;

    document.getElementById('period-form').reset();
    const currentYear = new Date().getFullYear();
    document.getElementById('period_name').value = `FY ${currentYear} (Annual)`;
    document.getElementById('period_start').value = `${currentYear}-01-01`;
    document.getElementById('period_end').value = `${currentYear}-12-31`;

    const modal = new bootstrap.Modal(modalEl);
    modal.show();
  };

  const savePeriod = async (event) => {
    event.preventDefault();

    const payload = {
      name: document.getElementById('period_name').value.trim(),
      period_type: document.getElementById('period_type').value,
      start_date: document.getElementById('period_start').value,
      end_date: document.getElementById('period_end').value,
      status: 'active',
    };

    try {
      await window.EcoPulseApi.post('/api/reporting-periods', payload);
      EcoPulseNotify.success(`Reporting period '${payload.name}' created.`);

      const modalInstance = bootstrap.Modal.getInstance(document.getElementById('periodModal'));
      if (modalInstance) modalInstance.hide();

      loadPeriodsView();

    } catch (err) {
      EcoPulseNotify.error(err.message || 'Failed to create reporting period.');
    }
  };

  const closePeriod = async (id, name) => {
    if (!confirm(`Lock and close reporting period '${name}'? Once closed, further activity ingestion will be sealed for audit integrity.`)) return;

    try {
      await window.EcoPulseApi.post(`/api/reporting-periods/${id}/close`, {});
      EcoPulseNotify.warning(`Reporting period '${name}' is now closed and locked.`);
      loadPeriodsView();
    } catch (err) {
      EcoPulseNotify.error(err.message || 'Failed to close period.');
    }
  };

  return {
    loadPeriodsView,
    openAddModal,
    savePeriod,
    closePeriod,
  };
})();

window.EcoPulsePeriods = EcoPulsePeriods;
