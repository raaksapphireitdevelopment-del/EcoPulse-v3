/**
 * EcoPulse MVP V3 - Domains Management Module
 */

const EcoPulseDomains = (() => {
  const loadDomainsView = async () => {
    const container = document.getElementById('view-container');
    if (!container) return;

    container.innerHTML = `
      <div class="d-flex justify-content-center py-5">
        <div class="spinner-border text-success" role="status"></div>
      </div>
    `;

    try {
      const [allDomainsRes, orgProfilesRes] = await Promise.all([
        window.EcoPulseApi.get('/api/domains'),
        window.EcoPulseApi.get('/api/domains/profiles'),
      ]);

      const allDomains = allDomainsRes.data || [];
      const orgProfiles = orgProfilesRes.data || [];

      // Create lookup of active profiles by domain_id
      const activeMap = {};
      orgProfiles.forEach(p => {
        if (p.status === 'active') {
          activeMap[p.domain_id] = p;
        }
      });

      container.innerHTML = `
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
          <div>
            <h4 class="fw-bold mb-1">Supported Business Domains</h4>
            <p class="text-secondary small mb-0">Manage which of the 9 operational domain workspaces are activated for your organization.</p>
          </div>
          <div>
            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fs-6">
              ${Object.keys(activeMap).length} of 9 Enabled
            </span>
          </div>
        </div>

        <div class="row g-3">
          ${allDomains.map(d => {
            const profile = activeMap[d.id];
            const isEnabled = !!profile;

            return `
              <div class="col-md-6 col-lg-4">
                <div class="ep-card h-100 ${isEnabled ? 'border-success shadow-sm' : 'bg-light bg-opacity-50'}">
                  <div class="d-flex justify-content-between align-items-start mb-2">
                    <div class="d-flex align-items-center gap-2">
                      <div class="auth-brand-logo ${isEnabled ? 'bg-brand' : 'bg-secondary'}" style="width: 38px; height: 38px; font-size: 1.15rem;">
                        <i class="bi ${EcoPulseDashboard.escapeHtml(d.icon)}"></i>
                      </div>
                      <div>
                        <h6 class="fw-bold mb-0 text-dark">${EcoPulseDashboard.escapeHtml(d.name)}</h6>
                        <span class="text-muted" style="font-size: 0.75rem;">code: ${EcoPulseDashboard.escapeHtml(d.code)}</span>
                      </div>
                    </div>
                    <span class="badge ${isEnabled ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'} small">
                      ${isEnabled ? 'Enabled' : 'Disabled'}
                    </span>
                  </div>

                  <p class="small text-secondary mb-3" style="font-size: 0.82rem; min-height: 48px;">
                    ${EcoPulseDashboard.escapeHtml(d.description)}
                  </p>

                  <div class="pt-3 border-top mt-auto d-flex justify-content-between align-items-center">
                    ${isEnabled ? `
                      <button class="btn btn-sm btn-outline-danger py-1" onclick="EcoPulseDomains.disableDomain(${profile.id}, '${d.name}')">
                        Deactivate
                      </button>
                      <button class="btn btn-sm btn-brand py-1 px-3" onclick="EcoPulseWorkspace.openDomain('${d.code}')">
                        Open Workspace <i class="bi bi-arrow-right"></i>
                      </button>
                    ` : `
                      <button class="btn btn-sm btn-success py-1 px-3 w-100" onclick="EcoPulseDomains.enableDomain(${d.id}, '${d.name}')">
                        <i class="bi bi-toggle-on me-1"></i> Activate Domain
                      </button>
                    `}
                  </div>
                </div>
              </div>
            `;
          }).join('')}
        </div>
      `;

    } catch (err) {
      container.innerHTML = `<div class="alert alert-danger">${EcoPulseDashboard.escapeHtml(err.message)}</div>`;
    }
  };

  const enableDomain = async (domainId, domainName) => {
    if (!confirm(`Activate the ${domainName} operational domain workspace for your organization?`)) return;

    try {
      await window.EcoPulseApi.post('/api/domains/profiles', {
        domain_id: domainId,
        base_year: new Date().getFullYear(),
      });
      EcoPulseNotify.success(`${domainName} workspace activated.`);
      loadDomainsView();
      // Refresh sidebar navigation
      if (window.EcoPulseApp) EcoPulseApp.refreshSidebar();
    } catch (err) {
      EcoPulseNotify.error(err.message || 'Failed to activate domain.');
    }
  };

  const disableDomain = async (profileId, domainName) => {
    if (!confirm(`Deactivate the ${domainName} workspace? Existing facility data and calculation records will be preserved.`)) return;

    try {
      await window.EcoPulseApi.post(`/api/domains/profiles/${profileId}/disable`, {});
      EcoPulseNotify.warning(`${domainName} workspace deactivated.`);
      loadDomainsView();
      if (window.EcoPulseApp) EcoPulseApp.refreshSidebar();
    } catch (err) {
      EcoPulseNotify.error(err.message || 'Failed to deactivate domain.');
    }
  };

  return {
    loadDomainsView,
    enableDomain,
    disableDomain,
  };
})();

window.EcoPulseDomains = EcoPulseDomains;
