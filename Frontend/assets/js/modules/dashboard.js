/**
 * EcoPulse MVP V3 - Organization Dashboard Module
 */

const EcoPulseDashboard = (() => {
  const loadDashboard = async () => {
    const container = document.getElementById('view-container');
    if (!container) return;

    container.innerHTML = `
      <div class="d-flex justify-content-center py-5">
        <div class="spinner-border text-success" role="status">
          <span class="visually-hidden">Loading dashboard...</span>
        </div>
      </div>
    `;

    try {
      const res = await window.EcoPulseApi.get('/api/organization/dashboard');
      const data = res.data;
      const org = data.organization;
      const counts = data.counts;

      container.innerHTML = `
        <!-- Organization Header Banner -->
        <div class="ep-card mb-4 border-start border-4 border-success">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
              <div class="d-flex align-items-center gap-2 mb-1">
                <h3 class="fw-bold mb-0">${escapeHtml(org.name)}</h3>
                <span class="badge bg-success-subtle text-success border border-success-subtle">${escapeHtml(org.status)}</span>
              </div>
              <div class="text-secondary small d-flex flex-wrap align-items-center gap-3">
                <span><i class="bi bi-briefcase me-1"></i>${escapeHtml(org.industry_primary)}</span>
                <span><i class="bi bi-geo-alt me-1"></i>${escapeHtml(org.city)}, ${escapeHtml(org.country)}</span>
                ${org.registration_number ? `<span><i class="bi bi-card-text me-1"></i>${escapeHtml(org.registration_number)}</span>` : ''}
              </div>
            </div>
            <div class="d-flex align-items-center gap-2">
              <button class="btn btn-brand-outline btn-sm" onclick="EcoPulseApp.navigateTo('domains')">
                <i class="bi bi-grid-3x3-gap me-1"></i> Manage Domains
              </button>
              <button class="btn btn-brand btn-sm" onclick="EcoPulseFacilities.openAddModal()">
                <i class="bi bi-plus-circle me-1"></i> Add Facility
              </button>
            </div>
          </div>
        </div>

        <!-- 4 Metric Stat Cards -->
        <div class="row g-3 mb-4">
          <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
              <div>
                <div class="text-secondary small fw-medium">Operational Facilities</div>
                <div class="fs-3 fw-bold text-dark mt-1">${counts.facilities_active} <span class="fs-6 text-muted fw-normal">/ ${counts.facilities_total}</span></div>
              </div>
              <div class="stat-icon-wrapper bg-success-subtle text-success">
                <i class="bi bi-buildings"></i>
              </div>
            </div>
          </div>

          <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
              <div>
                <div class="text-secondary small fw-medium">Enabled Business Domains</div>
                <div class="fs-3 fw-bold text-dark mt-1">${counts.enabled_domains_count} <span class="fs-6 text-muted fw-normal">/ 9 Active</span></div>
              </div>
              <div class="stat-icon-wrapper bg-primary-subtle text-primary">
                <i class="bi bi-diagram-3"></i>
              </div>
            </div>
          </div>

          <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
              <div>
                <div class="text-secondary small fw-medium">Reporting Periods</div>
                <div class="fs-3 fw-bold text-dark mt-1">${counts.reporting_periods_count}</div>
              </div>
              <div class="stat-icon-wrapper bg-warning-subtle text-warning">
                <i class="bi bi-calendar-check"></i>
              </div>
            </div>
          </div>

          <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
              <div>
                <div class="text-secondary small fw-medium">Team Members</div>
                <div class="fs-3 fw-bold text-dark mt-1">${counts.team_members_count}</div>
              </div>
              <div class="stat-icon-wrapper bg-info-subtle text-info">
                <i class="bi bi-people"></i>
              </div>
            </div>
          </div>
        </div>

        <!-- Enabled Domains Grid -->
        <div class="row g-4 mb-4">
          <div class="col-lg-8">
            <div class="ep-card h-100">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0">Active Domain Workspaces</h5>
                <button class="btn btn-link btn-sm text-brand p-0 text-decoration-none" onclick="EcoPulseApp.navigateTo('domains')">
                  Configure Domains <i class="bi bi-arrow-right"></i>
                </button>
              </div>

              ${data.enabled_domains.length === 0 ? `
                <div class="text-center py-4 text-muted">
                  <i class="bi bi-inbox fs-1 mb-2 d-block"></i>
                  No domains enabled yet. Click "Manage Domains" to enable your first sector.
                </div>
              ` : `
                <div class="row g-3">
                  ${data.enabled_domains.map(d => `
                    <div class="col-md-6">
                      <div class="ep-card p-3 h-100 border bg-light bg-opacity-25 hover-shadow">
                        <div class="d-flex align-items-start justify-content-between mb-2">
                          <div class="d-flex align-items-center gap-2">
                            <i class="bi ${escapeHtml(d.domain_icon || 'bi-gear')} fs-4 text-brand"></i>
                            <div>
                              <strong class="d-block text-dark">${escapeHtml(d.domain_name)}</strong>
                              <span class="text-secondary" style="font-size: 0.75rem;">Base Year: ${d.base_year}</span>
                            </div>
                          </div>
                          <span class="badge bg-success-subtle text-success small">Active</span>
                        </div>
                        <p class="text-secondary small mb-3" style="font-size: 0.8rem;">${escapeHtml(d.domain_description)}</p>
                        <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                          <span class="small text-muted"><i class="bi bi-buildings me-1"></i>${d.facilities_count || 0} Facilities</span>
                          <button class="btn btn-sm btn-brand py-1 px-3" onclick="EcoPulseWorkspace.openDomain('${d.domain_code}')">
                            Open Workspace <i class="bi bi-chevron-right ms-1"></i>
                          </button>
                        </div>
                      </div>
                    </div>
                  `).join('')}
                </div>
              `}
            </div>
          </div>

          <!-- Recent Activity & Audit Trail -->
          <div class="col-lg-4">
            <div class="ep-card h-100">
              <h5 class="fw-bold mb-3">Recent Operational Activity</h5>
              ${data.recent_activities.length === 0 ? `
                <div class="text-center py-4 text-muted small">No recent logs recorded.</div>
              ` : `
                <div class="list-group list-group-flush small">
                  ${data.recent_activities.map(act => `
                    <div class="list-group-item px-0 py-2 border-0 border-bottom">
                      <div class="d-flex justify-content-between align-items-center mb-1">
                        <strong class="text-dark">${escapeHtml(act.action)}</strong>
                        <span class="text-muted" style="font-size: 0.72rem;">${formatDate(act.created_at)}</span>
                      </div>
                      <div class="text-secondary" style="font-size: 0.76rem;">
                        <i class="bi bi-person me-1"></i>${escapeHtml(act.actor_name || 'System')} &bull; Entity: ${escapeHtml(act.entity_type)} #${act.entity_id}
                      </div>
                    </div>
                  `).join('')}
                </div>
              `}
            </div>
          </div>
        </div>
      `;

    } catch (err) {
      if (err.code === 'TENANT_REQUIRED' || (err.message && err.message.toLowerCase().includes('organization context'))) {
        container.innerHTML = `
          <div class="ep-card text-center py-5">
            <div class="text-muted mb-3"><i class="bi bi-buildings fs-1"></i></div>
            <h5 class="fw-bold">No Active Organization Selected</h5>
            <p class="text-secondary small mb-3">Please use the organization selector in the top bar to switch to an active organization.</p>
          </div>
        `;
        return;
      }
      container.innerHTML = `
        <div class="alert alert-danger">
          <strong>Failed to load organization dashboard:</strong> ${escapeHtml(err.message)}
        </div>
      `;
    }
  };

  const escapeHtml = (str) => {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  };

  const formatDate = (isoString) => {
    if (!isoString) return '';
    const date = new Date(isoString);
    return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
  };

  return {
    loadDashboard,
    escapeHtml,
    formatDate,
  };
})();

window.EcoPulseDashboard = EcoPulseDashboard;
