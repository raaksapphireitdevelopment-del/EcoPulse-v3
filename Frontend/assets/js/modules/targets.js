/**
 * EcoPulse MVP V3 - Reduction Targets Module
 */

const EcoPulseTargets = (() => {
  let targetsList = [];

  const loadTargetsView = async () => {
    const container = document.getElementById('view-container');
    if (!container) return;

    container.innerHTML = `
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h2 class="fw-bold mb-1">Reduction Targets</h2>
          <p class="text-secondary small mb-0">
            Organizational, facility, and scope-level decarbonization goals with real-time inventory gap tracking.
          </p>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-secondary btn-sm" onclick="EcoPulseTargets.refresh()">
            <i class="bi bi-arrow-clockwise me-1"></i> Refresh
          </button>
          <button class="btn btn-brand btn-sm" onclick="EcoPulseTargets.openCreateModal()">
            <i class="bi bi-plus-circle me-1"></i> Add Target
          </button>
        </div>
      </div>

      <div class="row g-3" id="targets-container">
        <div class="col-12 text-center py-5">
          <div class="spinner-border text-success" role="status"></div>
        </div>
      </div>
    `;

    await fetchTargets();
  };

  const fetchTargets = async () => {
    const container = document.getElementById('targets-container');
    try {
      const res = await EcoPulseApi.get('/api/reduction-targets');
      if (!res.success || !res.data) {
        if (container) container.innerHTML = `<div class="col-12 text-center py-4 text-muted">Failed to load reduction targets.</div>`;
        return;
      }

      targetsList = res.data;
      if (targetsList.length === 0) {
        if (container) {
          container.innerHTML = `
            <div class="col-12 text-center py-5 text-muted">
              <i class="bi bi-crosshair fs-2 d-block mb-2 text-secondary"></i>
              No reduction targets established yet.<br>
              <button class="btn btn-brand btn-sm mt-3" onclick="EcoPulseTargets.openCreateModal()">
                <i class="bi bi-plus-circle me-1"></i> Establish 2030 or Net-Zero Target
              </button>
            </div>`;
        }
        return;
      }

      if (container) {
        container.innerHTML = targetsList.map(t => {
          const progress = parseFloat(t.progress_percentage || 0);
          const baseline = parseFloat(t.baseline_emissions_tco2e || 0);
          const goal = parseFloat(t.target_emissions_tco2e || 0);
          const current = parseFloat(t.current_emissions_tco2e || 0);
          const remaining = parseFloat(t.remaining_reduction_tco2e || 0);

          return `
            <div class="col-md-6 col-lg-4">
              <div class="card border-0 shadow-sm rounded-3 p-3 h-100 bg-white">
                <div class="d-flex justify-content-between align-items-start mb-2">
                  <span class="badge bg-light text-secondary border text-uppercase">${t.target_level} Target</span>
                  <span class="badge bg-success-subtle text-success border">Target Year ${t.target_year}</span>
                </div>
                <h5 class="fw-bold text-dark mb-1">${t.title}</h5>
                <p class="text-muted small mb-3">Scope: <strong class="text-uppercase">${t.scope.replace('_', ' ')}</strong></p>

                <!-- Target Progress Metric -->
                <div class="mb-3">
                  <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted">Progress to Goal:</span>
                    <strong class="text-success">${progress}% Achieved</strong>
                  </div>
                  <div class="progress" style="height: 8px;">
                    <div class="progress-bar bg-success" style="width: ${progress}%;"></div>
                  </div>
                </div>

                <div class="row g-2 text-center mb-3">
                  <div class="col-4 p-2 bg-light rounded">
                    <span class="small text-muted d-block" style="font-size: 0.7rem;">Baseline</span>
                    <strong class="small text-dark">${baseline.toFixed(1)} t</strong>
                  </div>
                  <div class="col-4 p-2 bg-light rounded">
                    <span class="small text-muted d-block" style="font-size: 0.7rem;">Current</span>
                    <strong class="small text-primary">${current.toFixed(1)} t</strong>
                  </div>
                  <div class="col-4 p-2 bg-light rounded">
                    <span class="small text-muted d-block" style="font-size: 0.7rem;">Goal</span>
                    <strong class="small text-success">${goal.toFixed(1)} t</strong>
                  </div>
                </div>

                <div class="mt-auto d-flex justify-content-between align-items-center pt-2 border-top">
                  <span class="text-muted small" style="font-size: 0.75rem;">Gap: <strong>${remaining.toFixed(2)} tCO2e</strong></span>
                  <button class="btn btn-outline-danger btn-sm p-1" title="Delete Target" onclick="EcoPulseTargets.deleteTarget(${t.id})">
                    <i class="bi bi-trash"></i>
                  </button>
                </div>
              </div>
            </div>
          `;
        }).join('');
      }
    } catch (err) {
      console.error(err);
      if (container) container.innerHTML = `<div class="col-12 text-center py-4 text-danger">Error fetching targets.</div>`;
    }
  };

  /**
   * Open Target Creation Modal
   */
  const openCreateModal = () => {
    const modalHtml = `
      <div class="modal fade" id="targetCreateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <form onsubmit="EcoPulseTargets.submitCreate(event)">
              <div class="modal-header">
                <h5 class="modal-title fw-bold">Add Reduction Target</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <div class="mb-3">
                  <label class="form-label small fw-semibold">Target Title <span class="text-danger">*</span></label>
                  <input type="text" id="target-title" class="form-control" required placeholder="e.g. 2030 Science-Based 42% Absolute Reduction">
                </div>
                <div class="row g-2 mb-3">
                  <div class="col-6">
                    <label class="form-label small fw-semibold">Target Level</label>
                    <select id="target-level" class="form-select form-select-sm">
                      <option value="organization">Organization-wide</option>
                      <option value="domain">Domain Profile</option>
                      <option value="facility">Facility</option>
                      <option value="scope">Scope-level</option>
                    </select>
                  </div>
                  <div class="col-6">
                    <label class="form-label small fw-semibold">Target Scope</label>
                    <select id="target-scope" class="form-select form-select-sm">
                      <option value="all">All Operational Scopes</option>
                      <option value="scope_1">Scope 1</option>
                      <option value="scope_2">Scope 2</option>
                      <option value="scope_3">Scope 3</option>
                    </select>
                  </div>
                </div>
                <div class="row g-2 mb-3">
                  <div class="col-6">
                    <label class="form-label small fw-semibold">Baseline Year</label>
                    <input type="number" id="target-base-year" class="form-control form-control-sm" value="2024">
                  </div>
                  <div class="col-6">
                    <label class="form-label small fw-semibold">Target Year <span class="text-danger">*</span></label>
                    <input type="number" id="target-goal-year" class="form-control form-control-sm" required value="2030">
                  </div>
                </div>
                <div class="row g-2 mb-3">
                  <div class="col-6">
                    <label class="form-label small fw-semibold">Baseline Emissions (tCO2e) <span class="text-danger">*</span></label>
                    <input type="number" step="0.0001" id="target-base-emissions" class="form-control form-control-sm" required placeholder="e.g. 100.00">
                  </div>
                  <div class="col-6">
                    <label class="form-label small fw-semibold">Target Emissions (tCO2e) <span class="text-danger">*</span></label>
                    <input type="number" step="0.0001" id="target-goal-emissions" class="form-control form-control-sm" required placeholder="e.g. 58.00">
                  </div>
                </div>
                <div class="mb-3">
                  <label class="form-label small fw-semibold">Notes / Methodology Reference</label>
                  <textarea id="target-notes" class="form-control form-control-sm" rows="2" placeholder="e.g. Aligned with SBTI 1.5C Trajectory"></textarea>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-brand btn-sm px-4">Save Target</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    `;

    document.getElementById('targetCreateModal')?.remove();
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('targetCreateModal'));
    modal.show();
  };

  /**
   * Submit Create Target Form
   */
  const submitCreate = async (e) => {
    e.preventDefault();
    const payload = {
      title: document.getElementById('target-title').value,
      target_level: document.getElementById('target-level').value,
      scope: document.getElementById('target-scope').value,
      baseline_year: parseInt(document.getElementById('target-base-year').value, 10),
      target_year: parseInt(document.getElementById('target-goal-year').value, 10),
      baseline_emissions_tco2e: parseFloat(document.getElementById('target-base-emissions').value),
      target_emissions_tco2e: parseFloat(document.getElementById('target-goal-emissions').value),
      notes: document.getElementById('target-notes').value,
    };

    try {
      const res = await EcoPulseApi.post('/api/reduction-targets', payload);
      if (res.success) {
        EcoPulseNotify.success('Target created successfully!');
        bootstrap.Modal.getInstance(document.getElementById('targetCreateModal')).hide();
        loadTargetsView();
      } else {
        EcoPulseNotify.error(res.message || 'Failed to create target.');
      }
    } catch (err) {
      EcoPulseNotify.error(err.message);
    }
  };

  const deleteTarget = async (id) => {
    if (!confirm('Are you sure you want to remove this reduction target?')) return;
    try {
      const res = await EcoPulseApi.delete(`/api/reduction-targets/${id}`);
      if (res.success) {
        EcoPulseNotify.success('Target removed.');
        loadTargetsView();
      } else {
        EcoPulseNotify.error(res.message || 'Failed to delete target.');
      }
    } catch (err) {
      EcoPulseNotify.error(err.message);
    }
  };

  const refresh = () => fetchTargets();

  return {
    loadTargetsView,
    openCreateModal,
    submitCreate,
    deleteTarget,
    refresh,
  };
})();
