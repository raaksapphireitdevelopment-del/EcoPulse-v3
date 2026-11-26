/**
 * EcoPulse MVP V3 - Client Authentication Manager
 */

const EcoPulseAuth = (() => {
  let currentUser = null;
  let activeOrg = null;
  let userOrganizations = [];

  const getUser = () => currentUser;
  const getActiveOrg = () => activeOrg;
  const getOrganizations = () => userOrganizations;
  const isAuthenticated = () => !!currentUser;

  const login = async (email, password) => {
    const res = await window.EcoPulseApi.post('/api/auth/login', { email, password });
    if (res && res.data) {
      currentUser = res.data.user;
      activeOrg = res.data.active_organization;
      userOrganizations = res.data.organizations || [];

      if (activeOrg) {
        window.EcoPulseApi.setActiveOrgId(activeOrg.id);
      }
    }
    return res;
  };

  const register = async (payload) => {
    const res = await window.EcoPulseApi.post('/api/auth/register', payload);
    if (res && res.data) {
      currentUser = res.data.user;
      if (res.data.active_organization_id) {
        window.EcoPulseApi.setActiveOrgId(res.data.active_organization_id);
      }
    }
    return res;
  };

  const logout = async () => {
    try {
      await window.EcoPulseApi.post('/api/auth/logout', {});
    } catch (e) {
      console.warn('Logout API warning:', e);
    } finally {
      currentUser = null;
      activeOrg = null;
      userOrganizations = [];
      window.EcoPulseApi.setActiveOrgId('');
      window.EcoPulseApi.setCsrfToken('');
      window.location.href = '/login.html';
    }
  };

  const checkSession = async () => {
    try {
      const res = await window.EcoPulseApi.get('/api/auth/me');
      if (res && res.data) {
        currentUser = res.data.user;
        activeOrg = res.data.active_organization;
        userOrganizations = res.data.organizations || [];
        if (activeOrg) {
          window.EcoPulseApi.setActiveOrgId(activeOrg.id);
        }
        return { authenticated: true, user: currentUser, organization: activeOrg };
      }
    } catch (err) {
      currentUser = null;
      activeOrg = null;
      userOrganizations = [];
      return { authenticated: false, error: err };
    }
    return { authenticated: false };
  };

  const switchOrganization = async (orgId) => {
    const res = await window.EcoPulseApi.post('/api/auth/switch-org', { organization_id: orgId });
    if (res && res.data) {
      window.EcoPulseApi.setActiveOrgId(orgId);
      await checkSession();
    }
    return res;
  };

  return {
    getUser,
    getActiveOrg,
    getOrganizations,
    isAuthenticated,
    login,
    register,
    logout,
    checkSession,
    switchOrganization,
  };
})();

window.EcoPulseAuth = EcoPulseAuth;
