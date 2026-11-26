/**
 * EcoPulse MVP V3 - Team Management Module
 */

const EcoPulseTeam = (() => {
  const loadTeamView = async () => {
    const container = document.getElementById('view-container');
    if (!container) return;

    container.innerHTML = `
      <div class="d-flex justify-content-center py-5">
        <div class="spinner-border text-success" role="status"></div>
      </div>
    `;

    try {
      const res = await window.EcoPulseApi.get('/api/team');
      const members = res.data || [];

      container.innerHTML = `
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
          <div>
            <h4 class="fw-bold mb-1">Team & Permissions</h4>
            <p class="text-secondary small mb-0">Manage organization members, assign roles, and grant domain workspace permissions.</p>
          </div>
          <div>
            <button class="btn btn-brand btn-sm" onclick="EcoPulseTeam.openInviteModal()">
              <i class="bi bi-person-plus me-1"></i> Add Team Member
            </button>
          </div>
        </div>

        <div class="ep-card p-0 overflow-hidden">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light text-secondary small text-uppercase">
                <tr>
                  <th class="ps-3">Member Name & Email</th>
                  <th>Organization Role</th>
                  <th>Status</th>
                  <th>Joined Date</th>
                  <th>Last Sign In</th>
                  <th class="text-end pe-3">Actions</th>
                </tr>
              </thead>
              <tbody>
                ${members.map(m => `
                  <tr>
                    <td class="ps-3">
                      <strong class="d-block text-dark">${EcoPulseDashboard.escapeHtml(m.name)}</strong>
                      <span class="text-muted" style="font-size: 0.76rem;">${EcoPulseDashboard.escapeHtml(m.email)}</span>
                    </td>
                    <td>
                      <span class="badge ${m.org_role === 'organization_owner' ? 'bg-success text-white' : m.org_role === 'organization_admin' ? 'bg-primary text-white' : 'bg-light text-dark border'} small text-capitalize">
                        ${m.org_role.replace('organization_', '')}
                      </span>
                    </td>
                    <td>
                      <span class="badge ${m.membership_status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'} small">
                        ${EcoPulseDashboard.escapeHtml(m.membership_status)}
                      </span>
                    </td>
                    <td class="small text-secondary">
                      ${EcoPulseDashboard.formatDate(m.joined_at)}
                    </td>
                    <td class="small text-secondary">
                      ${m.last_login_at ? EcoPulseDashboard.formatDate(m.last_login_at) : 'Never'}
                    </td>
                    <td class="text-end pe-3">
                      ${m.membership_status === 'active' ? `
                        <button class="btn btn-sm btn-outline-danger py-0 px-2" onclick="EcoPulseTeam.toggleMemberStatus(${m.membership_id}, 'inactive', '${EcoPulseDashboard.escapeHtml(m.name)}')">
                          Deactivate
                        </button>
                      ` : `
                        <button class="btn btn-sm btn-outline-success py-0 px-2" onclick="EcoPulseTeam.toggleMemberStatus(${m.membership_id}, 'active', '${EcoPulseDashboard.escapeHtml(m.name)}')">
                          Reactivate
                        </button>
                      `}
                    </td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>
        </div>
      `;

    } catch (err) {
      container.innerHTML = `<div class="alert alert-danger">${EcoPulseDashboard.escapeHtml(err.message)}</div>`;
    }
  };

  const openInviteModal = () => {
    const modalEl = document.getElementById('teamModal');
    if (!modalEl) return;

    document.getElementById('team-form').reset();
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
  };

  const saveMember = async (event) => {
    event.preventDefault();

    const payload = {
      name: document.getElementById('member_name').value.trim(),
      email: document.getElementById('member_email').value.trim(),
      org_role: document.getElementById('member_role').value,
      phone: document.getElementById('member_phone').value.trim(),
    };

    try {
      await window.EcoPulseApi.post('/api/team', payload);
      EcoPulseNotify.success(`Member '${payload.name}' added to team.`);

      const modalInstance = bootstrap.Modal.getInstance(document.getElementById('teamModal'));
      if (modalInstance) modalInstance.hide();

      loadTeamView();

    } catch (err) {
      EcoPulseNotify.error(err.message || 'Failed to add team member.');
    }
  };

  const toggleMemberStatus = async (membershipId, newStatus, name) => {
    const actionText = newStatus === 'active' ? 'reactivate' : 'deactivate';
    if (!confirm(`Are you sure you want to ${actionText} membership for '${name}'?`)) return;

    try {
      await window.EcoPulseApi.put(`/api/team/${membershipId}`, { status: newStatus });
      EcoPulseNotify.success(`Membership for '${name}' ${actionText}d.`);
      loadTeamView();
    } catch (err) {
      EcoPulseNotify.error(err.message || `Failed to ${actionText} member.`);
    }
  };

  return {
    loadTeamView,
    openInviteModal,
    saveMember,
    toggleMemberStatus,
  };
})();

window.EcoPulseTeam = EcoPulseTeam;
