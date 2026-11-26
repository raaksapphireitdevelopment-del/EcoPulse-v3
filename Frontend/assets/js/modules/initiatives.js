/**
 * EcoPulse MVP V3 - Reduction Initiatives & Opportunities Module
 */

const EcoPulseInitiatives = (() => {
  let initiativesList = [];
  let opportunitiesList = [];

  /**
   * Load Reduction Initiatives View
   */
  const loadInitiativesView = async () => {
    const container = document.getElementById('view-container');
    if (!container) return;

    container.innerHTML = `
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h2 class="fw-bold mb-1">Reduction Initiatives</h2>
          <p class="text-secondary small mb-0">
            Decarbonization projects, baseline-to-target tracking, implementation milestones, and measured verification.
          </p>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-success btn-sm" onclick="EcoPulseInitiatives.exportCsv()">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export Initiatives (CSV)
          </button>
          <button class="btn btn-outline-secondary btn-sm" onclick="EcoPulseInitiatives.refresh()">
            <i class="bi bi-arrow-clockwise me-1"></i> Refresh
          </button>
          <button class="btn btn-brand btn-sm" onclick="EcoPulseInitiatives.openCreateModal()">
            <i class="bi bi-plus-circle me-1"></i> New Initiative
          </button>
        </div>
      </div>

      <!-- Initiatives Table Card -->
      <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-secondary small text-uppercase">
              <tr>
                <th class="ps-3">Initiative Name</th>
                <th>Domain & Facility</th>
                <th>Target Scope</th>
                <th>Expected Reduction</th>
                <th>Progress</th>
                <th>Verification</th>
                <th>Status</th>
                <th class="text-end pe-3">Actions</th>
              </tr>
            </thead>
            <tbody id="initiatives-table-body">
              <tr>
                <td colspan="8" class="text-center py-4 text-muted">
                  <div class="spinner-border spinner-border-sm text-success me-2" role="status"></div>
                  Loading initiatives...
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    `;

    await fetchInitiatives();
  };

  /**
   * Fetch all initiatives
   */
  const fetchInitiatives = async () => {
    const tbody = document.getElementById('initiatives-table-body');
    try {
      const res = await EcoPulseApi.get('/api/reduction-initiatives');
      if (!res.success || !res.data.initiatives) {
        if (tbody) tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted">Failed to load initiatives.</td></tr>`;
        return;
      }

      initiativesList = res.data.initiatives;
      if (initiativesList.length === 0) {
        if (tbody) {
          tbody.innerHTML = `
            <tr>
              <td colspan="8" class="text-center py-5 text-muted">
                <i class="bi bi-kanban fs-2 d-block mb-2 text-secondary"></i>
                No reduction initiatives created yet.<br>
                <button class="btn btn-brand btn-sm mt-3" onclick="EcoPulseInitiatives.openCreateModal()">
                  <i class="bi bi-plus-circle me-1"></i> Create First Initiative
                </button>
              </td>
            </tr>`;
        }
        return;
      }

      if (tbody) {
        tbody.innerHTML = initiativesList.map(item => {
          const statusBadges = {
            proposed: 'bg-secondary',
            approved: 'bg-info text-dark',
            in_progress: 'bg-primary',
            on_hold: 'bg-warning text-dark',
            completed: 'bg-success',
            cancelled: 'bg-danger',
          };
          const badgeClass = statusBadges[item.status] || 'bg-secondary';
          const progress = parseFloat(item.progress_percentage || 0);

          return `
            <tr>
              <td class="ps-3">
                <span class="fw-bold text-dark d-block">${item.title}</span>
                <span class="text-muted small">${item.description ? item.description.substring(0, 50) + '...' : ''}</span>
              </td>
              <td>
                <span class="d-block small fw-semibold text-dark">${item.domain_name}</span>
                <span class="text-muted small">${item.facility_name || 'All Facilities'}</span>
              </td>
              <td>
                <span class="badge bg-light text-secondary border text-uppercase">${item.target_scope.replace('_', ' ')}</span>
              </td>
              <td>
                <span class="fw-semibold text-dark">${parseFloat(item.estimated_annual_tco2e_reduction || 0).toFixed(2)} tCO2e</span>
                <span class="text-muted small d-block">${item.expected_reduction_percentage ? item.expected_reduction_percentage + '%' : ''}</span>
              </td>
              <td style="min-width: 120px;">
                <div class="d-flex align-items-center gap-2">
                  <div class="progress flex-grow-1" style="height: 6px;">
                    <div class="progress-bar bg-success" style="width: ${progress}%;"></div>
                  </div>
                  <span class="small fw-semibold text-muted">${progress}%</span>
                </div>
              </td>
              <td>
                <span class="badge bg-light text-dark border small">${item.reduction_verification_status || 'unverified'}</span>
              </td>
              <td>
                <span class="badge ${badgeClass} small text-uppercase">${item.status.replace('_', ' ')}</span>
              </td>
              <td class="text-end pe-3">
                <button class="btn btn-outline-secondary btn-sm" onclick="EcoPulseInitiatives.openDetailModal(${item.id})">
                  <i class="bi bi-eye"></i> View & Track
                </button>
              </td>
            </tr>
          `;
        }).join('');
      }
    } catch (err) {
      console.error(err);
      if (tbody) tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-danger">Error fetching initiatives.</td></tr>`;
    }
  };

  /**
   * Load Reduction Opportunities View
   */
  const loadOpportunitiesView = async () => {
    const container = document.getElementById('view-container');
    if (!container) return;

    container.innerHTML = `
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h2 class="fw-bold mb-1">Reduction Opportunities</h2>
          <p class="text-secondary small mb-0">
            Algorithmic decarbonization recommendations derived from activity data benchmarks.
          </p>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-secondary btn-sm" onclick="EcoPulseInitiatives.refreshOpportunities()">
            <i class="bi bi-arrow-clockwise me-1"></i> Refresh
          </button>
        </div>
      </div>

      <div class="alert alert-info border-0 rounded-3 p-3 mb-4 d-flex align-items-center gap-3">
        <i class="bi bi-info-circle-fill fs-4 text-info"></i>
        <div class="small">
          <strong>Analytical Notice:</strong> Reduction opportunities are analytical recommendations generated from your high-emission categories. They do not constitute measured or achieved reductions.
        </div>
      </div>

      <div class="row g-3" id="opportunities-cards-container">
        <div class="col-12 text-center py-5">
          <div class="spinner-border text-success" role="status"></div>
        </div>
      </div>
    `;

    await fetchOpportunities();
  };

  const fetchOpportunities = async () => {
    const container = document.getElementById('opportunities-cards-container');
    try {
      const res = await EcoPulseApi.get('/api/analytics/reduction-opportunities');
      if (!res.success || !res.data.opportunities) {
        if (container) container.innerHTML = `<div class="col-12 text-center py-4 text-muted">Failed to load opportunities.</div>`;
        return;
      }

      opportunitiesList = res.data.opportunities;
      if (opportunitiesList.length === 0) {
        if (container) {
          container.innerHTML = `
            <div class="col-12 text-center py-5 text-muted">
              <i class="bi bi-lightbulb fs-2 d-block mb-2 text-secondary"></i>
              No high-emission opportunities identified yet.<br>
              Record additional activity data to generate custom decarbonization recommendations.
            </div>`;
        }
        return;
      }

      if (container) {
        container.innerHTML = opportunitiesList.map((opp, idx) => {
          const priorityBadges = {
            critical: 'bg-danger',
            high: 'bg-warning text-dark',
            medium: 'bg-info text-dark',
            low: 'bg-secondary',
          };
          const badgeClass = priorityBadges[opp.priority] || 'bg-secondary';

          return `
            <div class="col-md-6">
              <div class="card border-0 shadow-sm rounded-3 p-3 h-100 bg-white">
                <div class="d-flex justify-content-between align-items-start mb-2">
                  <span class="badge ${badgeClass} text-uppercase small">${opp.priority} Priority</span>
                  <span class="badge bg-light text-secondary border">${opp.domain}</span>
                </div>
                <h5 class="fw-bold text-dark mb-1">${opp.title}</h5>
                <p class="text-muted small mb-2"><i class="bi bi-geo-alt me-1"></i>${opp.facility_name || 'Organization-wide'}</p>
                <div class="bg-light p-2 rounded-2 mb-3">
                  <div class="d-flex justify-content-between small">
                    <span class="text-muted">Potential Reduction:</span>
                    <strong class="text-success">${opp.potential_reduction_tco2e} tCO2e</strong>
                  </div>
                </div>
                <p class="small text-secondary mb-2">${opp.reason_for_recommendation}</p>
                <div class="p-2 border-start border-3 border-success bg-light rounded-end small mb-3">
                  <strong>Suggested Action:</strong> ${opp.suggested_action}
                </div>
                <div class="mt-auto d-flex justify-content-between align-items-center">
                  <span class="text-muted small" style="font-size: 0.72rem;">${opp.estimation_assumptions}</span>
                  <button class="btn btn-outline-success btn-sm" onclick="EcoPulseInitiatives.convertOpportunityToInitiative(${idx})">
                    <i class="bi bi-arrow-right-circle me-1"></i> Convert to Initiative
                  </button>
                </div>
              </div>
            </div>
          `;
        }).join('');
      }
    } catch (err) {
      console.error(err);
      if (container) container.innerHTML = `<div class="col-12 text-center py-4 text-danger">Error loading opportunities.</div>`;
    }
  };

  /**
   * Convert an opportunity into an active initiative modal
   */
  const convertOpportunityToInitiative = (oppIdx) => {
    const opp = opportunitiesList[oppIdx];
    if (!opp) return;

    openCreateModal({
      title: opp.title,
      description: `${opp.suggested_action}\n\nAssumptions: ${opp.estimation_assumptions}`,
      estimated_annual_tco2e_reduction: opp.potential_reduction_tco2e,
      facility_id: opp.facility_id,
      activity_category_id: opp.activity_category_id,
    });
  };

  /**
   * Open Modal to Create New Initiative
   */
  const openCreateModal = async (prefill = {}) => {
    // Populate domains and facilities
    const [domRes, facRes] = await Promise.all([
      EcoPulseApi.get('/api/domains/profiles'),
      EcoPulseApi.get('/api/facilities'),
    ]);

    const domOptions = (domRes.data || []).map(dp => `<option value="${dp.id}">${dp.domain_name} (${dp.profile_name})</option>`).join('');
    const facOptions = `<option value="">All Facilities</option>` + (facRes.data?.facilities || []).map(f => `<option value="${f.id}">${f.name}</option>`).join('');

    const modalHtml = `
      <div class="modal fade" id="initiativeCreateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <form onsubmit="EcoPulseInitiatives.submitCreate(event)">
              <div class="modal-header">
                <h5 class="modal-title fw-bold">Create Decarbonization Initiative</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <div class="row g-3">
                  <div class="col-md-8">
                    <label class="form-label small fw-semibold">Initiative Title <span class="text-danger">*</span></label>
                    <input type="text" id="init-title" class="form-control" required value="${prefill.title || ''}">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label small fw-semibold">Domain Profile <span class="text-danger">*</span></label>
                    <select id="init-domain-profile" class="form-select" required>
                      ${domOptions}
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small fw-semibold">Target Facility</label>
                    <select id="init-facility" class="form-select">
                      ${facOptions}
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small fw-semibold">Target Scope</label>
                    <select id="init-scope" class="form-select">
                      <option value="all">All Scopes</option>
                      <option value="scope_1">Scope 1</option>
                      <option value="scope_2">Scope 2</option>
                      <option value="scope_3">Scope 3</option>
                    </select>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label small fw-semibold">Expected Reduction (tCO2e)</label>
                    <input type="number" step="0.0001" id="init-reduction" class="form-control" value="${prefill.estimated_annual_tco2e_reduction || ''}">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label small fw-semibold">Estimated CapEx</label>
                    <input type="number" step="0.01" id="init-capex" class="form-control" placeholder="e.g. 25000">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label small fw-semibold">Target Completion Year</label>
                    <input type="number" id="init-comp-year" class="form-control" value="${new Date().getFullYear() + 1}">
                  </div>
                  <div class="col-12">
                    <label class="form-label small fw-semibold">Description & Milestones</label>
                    <textarea id="init-desc" class="form-control" rows="3">${prefill.description || ''}</textarea>
                  </div>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-brand btn-sm px-4">Create Initiative</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    `;

    document.getElementById('initiativeCreateModal')?.remove();
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('initiativeCreateModal'));
    modal.show();
  };

  /**
   * Submit Create Initiative Form
   */
  const submitCreate = async (e) => {
    e.preventDefault();
    const payload = {
      title: document.getElementById('init-title').value,
      domain_profile_id: parseInt(document.getElementById('init-domain-profile').value, 10),
      facility_id: document.getElementById('init-facility').value ? parseInt(document.getElementById('init-facility').value, 10) : null,
      target_scope: document.getElementById('init-scope').value,
      estimated_annual_tco2e_reduction: parseFloat(document.getElementById('init-reduction').value || 0),
      estimated_capex: parseFloat(document.getElementById('init-capex').value || 0),
      target_completion_year: parseInt(document.getElementById('init-comp-year').value, 10),
      description: document.getElementById('init-desc').value,
      status: 'proposed',
    };

    try {
      const res = await EcoPulseApi.post('/api/reduction-initiatives', payload);
      if (res.success) {
        EcoPulseNotify.success('Reduction initiative created successfully!');
        bootstrap.Modal.getInstance(document.getElementById('initiativeCreateModal')).hide();
        loadInitiativesView();
      } else {
        EcoPulseNotify.error(res.message || 'Failed to create initiative.');
      }
    } catch (err) {
      EcoPulseNotify.error(err.message);
    }
  };

  /**
   * Open Initiative Detail & Progress Drawer Modal
   */
  const openDetailModal = async (id) => {
    try {
      const res = await EcoPulseApi.get(`/api/reduction-initiatives/${id}`);
      if (!res.success) return;
      const init = res.data;

      const updatesHtml = (init.updates_history || []).map(u => `
        <div class="p-2 border-bottom">
          <div class="d-flex justify-content-between small">
            <strong>${u.update_date} — ${u.progress_percentage}% Completed</strong>
            <span class="badge bg-light text-dark border text-uppercase">${u.status}</span>
          </div>
          <p class="small text-secondary mb-1 mt-1">${u.description}</p>
          ${u.evidence_notes ? `<div class="small text-muted"><i class="bi bi-file-earmark-text me-1"></i>Evidence: ${u.evidence_notes}</div>` : ''}
          <div class="small text-muted" style="font-size: 0.72rem;">Updated by: ${u.updater_name}</div>
        </div>
      `).join('') || `<div class="p-3 text-center text-muted small">No progress updates recorded yet.</div>`;

      const detailHtml = `
        <div class="modal fade" id="initiativeDetailModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg">
            <div class="modal-content">
              <div class="modal-header">
                <div>
                  <h5 class="modal-title fw-bold">${init.title}</h5>
                  <span class="badge bg-light text-secondary border">${init.domain_name}</span>
                  <span class="badge bg-success-subtle text-success border ms-1">${init.status.toUpperCase()}</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <div class="row g-3 mb-4">
                  <div class="col-md-4">
                    <div class="p-2 bg-light rounded text-center">
                      <span class="small text-muted d-block">Expected Reduction</span>
                      <strong class="fs-5 text-dark">${parseFloat(init.estimated_annual_tco2e_reduction || 0).toFixed(2)} tCO2e</strong>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="p-2 bg-light rounded text-center">
                      <span class="small text-muted d-block">Measured Reduction</span>
                      <strong class="fs-5 text-success">${parseFloat(init.measured_reduction_tco2e || 0).toFixed(2)} tCO2e</strong>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="p-2 bg-light rounded text-center">
                      <span class="small text-muted d-block">Current Progress</span>
                      <strong class="fs-5 text-primary">${init.progress_percentage}%</strong>
                    </div>
                  </div>
                </div>

                <h6 class="fw-bold mb-2">Record Progress Milestone</h6>
                <form onsubmit="EcoPulseInitiatives.submitProgress(event, ${init.id})">
                  <div class="row g-2 mb-3">
                    <div class="col-md-3">
                      <label class="form-label small">Progress %</label>
                      <input type="number" id="prog-pct" class="form-control form-control-sm" required min="0" max="100" value="${init.progress_percentage}">
                    </div>
                    <div class="col-md-4">
                      <label class="form-label small">Status Transition</label>
                      <select id="prog-status" class="form-select form-select-sm">
                        <option value="proposed" ${init.status === 'proposed' ? 'selected' : ''}>Proposed</option>
                        <option value="approved" ${init.status === 'approved' ? 'selected' : ''}>Approved</option>
                        <option value="in_progress" ${init.status === 'in_progress' ? 'selected' : ''}>In Progress</option>
                        <option value="on_hold" ${init.status === 'on_hold' ? 'selected' : ''}>On Hold</option>
                        <option value="completed" ${init.status === 'completed' ? 'selected' : ''}>Completed</option>
                        <option value="cancelled" ${init.status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                      </select>
                    </div>
                    <div class="col-md-5">
                      <label class="form-label small">Measured Reduction (tCO2e, if evidence)</label>
                      <input type="number" step="0.0001" id="prog-measured" class="form-control form-control-sm" placeholder="Optional">
                    </div>
                    <div class="col-12">
                      <label class="form-label small">Milestone Description <span class="text-danger">*</span></label>
                      <input type="text" id="prog-desc" class="form-control form-control-sm" required placeholder="e.g. Completed inverter wiring and synchronized with meter">
                    </div>
                    <div class="col-12">
                      <label class="form-label small">Evidence Notes</label>
                      <input type="text" id="prog-evidence" class="form-control form-control-sm" placeholder="e.g. Utility interconnection approval letter reference #772">
                    </div>
                    <div class="col-12 text-end">
                      <button type="submit" class="btn btn-brand btn-sm px-3">
                        <i class="bi bi-check-circle me-1"></i> Save Progress Milestone
                      </button>
                    </div>
                  </div>
                </form>

                <h6 class="fw-bold mb-2 mt-4">Progress History & Audit Trail</h6>
                <div class="border rounded-3 p-2 bg-white" style="max-height: 200px; overflow-y: auto;">
                  ${updatesHtml}
                </div>
              </div>
            </div>
          </div>
        </div>
      `;

      document.getElementById('initiativeDetailModal')?.remove();
      document.body.insertAdjacentHTML('beforeend', detailHtml);
      const modal = new bootstrap.Modal(document.getElementById('initiativeDetailModal'));
      modal.show();
    } catch (err) {
      console.error(err);
      EcoPulseNotify.error('Failed to load initiative details.');
    }
  };

  /**
   * Submit Progress Milestone
   */
  const submitProgress = async (e, id) => {
    e.preventDefault();
    const payload = {
      progress_percentage: parseFloat(document.getElementById('prog-pct').value),
      status: document.getElementById('prog-status').value,
      description: document.getElementById('prog-desc').value,
      evidence_notes: document.getElementById('prog-evidence').value || null,
    };

    const measuredVal = document.getElementById('prog-measured').value;
    if (measuredVal) {
      payload.measured_reduction_tco2e = parseFloat(measuredVal);
    }

    try {
      const res = await EcoPulseApi.post(`/api/reduction-initiatives/${id}/progress`, payload);
      if (res.success) {
        EcoPulseNotify.success('Progress milestone recorded!');
        bootstrap.Modal.getInstance(document.getElementById('initiativeDetailModal')).hide();
        loadInitiativesView();
      } else {
        EcoPulseNotify.error(res.message || 'Failed to record progress.');
      }
    } catch (err) {
      EcoPulseNotify.error(err.message);
    }
  };

  const refresh = () => fetchInitiatives();
  const refreshOpportunities = () => fetchOpportunities();

  const exportCsv = () => {
    if (!initiativesList || initiativesList.length === 0) {
      alert('No initiatives available to export.');
      return;
    }
    const headers = ['Title', 'Scope', 'Target Reduction (tCO2e)', 'Achieved Reduction (tCO2e)', 'Progress %', 'Status', 'Estimated Cost', 'Estimated Savings', 'Target Completion'];
    const rows = initiativesList.map(item => [
      `"${(item.title || '').replace(/"/g, '""')}"`,
      `"${(item.scope || '').replace(/"/g, '""')}"`,
      item.target_reduction_tco2e ?? 0,
      item.achieved_reduction_tco2e ?? 0,
      item.progress_percentage ?? 0,
      `"${(item.status || '').replace(/"/g, '""')}"`,
      item.estimated_capex ?? 0,
      item.annual_opex_savings ?? 0,
      `"${(item.target_completion_date || '').replace(/"/g, '""')}"`,
    ]);
    const csvContent = '\uFEFF' + [headers.join(','), ...rows.map(r => r.join(','))].join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `ecopulse_reduction_initiatives_${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  };

  return {
    loadInitiativesView,
    loadOpportunitiesView,
    openCreateModal,
    submitCreate,
    openDetailModal,
    submitProgress,
    convertOpportunityToInitiative,
    refresh,
    refreshOpportunities,
    exportCsv,
  };
})();
