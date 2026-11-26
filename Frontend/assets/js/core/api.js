/**
 * EcoPulse MVP V3 - Core Fetch API Client
 * Centralized HTTP request handler with secure session credentials, CSRF management, and error mapping
 */

const EcoPulseApi = (() => {
  let csrfToken = sessionStorage.getItem('ep_csrf_token') || '';
  let activeOrgId = sessionStorage.getItem('ep_active_org_id') || '';

  const setCsrfToken = (token) => {
    if (token) {
      csrfToken = token;
      sessionStorage.setItem('ep_csrf_token', token);
    }
  };

  const setActiveOrgId = (orgId) => {
    if (orgId) {
      activeOrgId = String(orgId);
      sessionStorage.setItem('ep_active_org_id', activeOrgId);
    } else {
      activeOrgId = '';
      sessionStorage.removeItem('ep_active_org_id');
    }
  };

  const getActiveOrgId = () => activeOrgId;

  const request = async (endpoint, options = {}) => {
    const baseUrl = window.EcoPulseConfig ? window.EcoPulseConfig.API_BASE : '';
    
    // Normalize url
    let url = endpoint.startsWith('http') ? endpoint : `${baseUrl}${endpoint.startsWith('/') ? '' : '/'}${endpoint}`;

    const headers = new Headers(options.headers || {});

    // Set JSON content-type if not multipart/form-data
    if (!(options.body instanceof FormData) && !headers.has('Content-Type')) {
      headers.set('Content-Type', 'application/json');
    }

    // Set CSRF token if available
    if (csrfToken && !headers.has('X-CSRF-Token')) {
      headers.set('X-CSRF-Token', csrfToken);
    }

    // Set Active Org header if set
    if (activeOrgId && !headers.has('X-Organization-Id')) {
      headers.set('X-Organization-Id', activeOrgId);
    }

    headers.set('Accept', 'application/json');

    const config = {
      method: options.method || 'GET',
      headers,
      credentials: 'include', // Ensure PHP session cookies are transmitted
      ...options,
    };

    // Auto-serialize JSON body
    if (config.body && typeof config.body === 'object' && !(config.body instanceof FormData)) {
      config.body = JSON.stringify(config.body);
    }

    try {
      const response = await fetch(url, config);

      // Capture updated CSRF token from headers if sent
      const newCsrf = response.headers.get('X-CSRF-Token');
      if (newCsrf) {
        setCsrfToken(newCsrf);
      }

      // Handle 204 No Content
      if (response.status === 204) {
        return { success: true, data: null };
      }

      let json;
      try {
        json = await response.json();
      } catch (err) {
        throw new Error(`Server returned status ${response.status} with non-JSON response.`);
      }

      // Update CSRF token from response payload if present
      if (json && json.data && json.data.csrf_token) {
        setCsrfToken(json.data.csrf_token);
      }

      if (!response.ok || (json && json.success === false)) {
        const error = new Error(json?.error?.message || `Request failed with HTTP status ${response.status}`);
        error.status = response.status;
        error.code = json?.error?.code || 'REQUEST_FAILED';
        error.details = json?.error?.details || {};
        throw error;
      }

      return json;
    } catch (err) {
      console.error(`[EcoPulse API Error] ${options.method || 'GET'} ${endpoint}:`, err);
      throw err;
    }
  };

  return {
    get: (endpoint, options = {}) => request(endpoint, { ...options, method: 'GET' }),
    post: (endpoint, body, options = {}) => request(endpoint, { ...options, method: 'POST', body }),
    put: (endpoint, body, options = {}) => request(endpoint, { ...options, method: 'PUT', body }),
    delete: (endpoint, options = {}) => request(endpoint, { ...options, method: 'DELETE' }),
    setCsrfToken,
    setActiveOrgId,
    getActiveOrgId,
  };
})();

window.EcoPulseApi = EcoPulseApi;
