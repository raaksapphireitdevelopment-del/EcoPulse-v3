/**
 * EcoPulse MVP V3 - Client Configuration
 */

const EcoPulseConfig = (() => {
  // Determine API Base URL dynamically
  const getApiBaseUrl = () => {
    // If running in development with custom override
    if (window.ECOPULSE_API_URL) {
      return window.ECOPULSE_API_URL;
    }

    const origin = window.location.origin;
    const pathname = window.location.pathname;

    // If running under XAMPP subfolder, e.g. /ecopulse/Frontend/...
    if (pathname.includes('/ecopulse/')) {
      return `${origin}/ecopulse/Backend/index.php`;
    }

    // If Frontend and Backend are siblings in root, e.g. http://localhost/Frontend/
    if (pathname.includes('/Frontend/')) {
      const basePath = pathname.substring(0, pathname.indexOf('/Frontend/'));
      return `${origin}${basePath}/Backend/index.php`;
    }

    // Default to port 8000 for local PHP built-in server or relative /Backend/index.php
    if (origin.includes(':3000') || origin.includes(':5500') || origin.includes(':8000')) {
      return origin.includes(':8000') ? origin : 'http://127.0.0.1:8000';
    }

    return `${origin}/Backend/index.php`;
  };

  return {
    API_BASE: getApiBaseUrl(),
    CSRF_HEADER: 'X-CSRF-Token',
    ORG_HEADER: 'X-Organization-Id',
  };
})();

window.EcoPulseConfig = EcoPulseConfig;
