/**
 * EcoPulse MVP V3 - User Notification & Toast Component
 */

const EcoPulseNotify = (() => {
  let toastContainer = null;

  const ensureContainer = () => {
    if (!toastContainer) {
      toastContainer = document.getElementById('toast-container');
      if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        document.body.appendChild(toastContainer);
      }
    }
  };

  const show = (message, type = 'info', title = '') => {
    ensureContainer();

    const icons = {
      success: 'bi-check-circle-fill text-success',
      danger: 'bi-exclamation-triangle-fill text-danger',
      warning: 'bi-exclamation-circle-fill text-warning',
      info: 'bi-info-circle-fill text-primary',
    };

    const toast = document.createElement('div');
    toast.className = `ep-card p-3 mb-2 shadow-lg border-start border-4 border-${type === 'danger' ? 'danger' : type === 'success' ? 'success' : 'primary'}`;
    toast.style.minWidth = '300px';
    toast.style.maxWidth = '420px';
    toast.style.animation = 'fadeIn 0.2s ease';

    toast.innerHTML = `
      <div class="d-flex align-items-start gap-2">
        <i class="bi ${icons[type] || icons.info} fs-5 mt-1"></i>
        <div class="flex-grow-1">
          ${title ? `<strong class="d-block mb-1 text-dark">${title}</strong>` : ''}
          <div class="small text-secondary">${message}</div>
        </div>
        <button type="button" class="btn-close btn-close-sm" aria-label="Close"></button>
      </div>
    `;

    toast.querySelector('.btn-close').addEventListener('click', () => {
      toast.remove();
    });

    toastContainer.appendChild(toast);

    setTimeout(() => {
      if (toast.isConnected) {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s ease';
        setTimeout(() => toast.remove(), 300);
      }
    }, 4500);
  };

  return {
    show,
    success: (msg, title = 'Success') => show(msg, 'success', title),
    error: (msg, title = 'Error') => show(msg, 'danger', title),
    warning: (msg, title = 'Warning') => show(msg, 'warning', title),
    info: (msg, title = 'Notice') => show(msg, 'info', title),
  };
})();

window.EcoPulseNotify = EcoPulseNotify;
window.EcoPulseNotification = EcoPulseNotify;
