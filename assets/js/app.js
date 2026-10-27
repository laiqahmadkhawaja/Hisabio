/**
 * Hisabio - Core Frontend Application
 * Strict No-Modal UI, Toast Notifications, Offline Detection, Button States
 */

const Hisabio = {
  // Toast Notification System (Strict No-Modal Policy)
  toast: function(message, type = 'info', duration = 3500) {
    let container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
      <span>${message}</span>
      <button type="button" style="background:none; border:none; color:inherit; font-size:16px; cursor:pointer;" aria-label="Close">&times;</button>
    `;

    toast.querySelector('button').addEventListener('click', () => {
      toast.remove();
    });

    container.appendChild(toast);

    setTimeout(() => {
      if (toast.parentNode) {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.25s ease';
        setTimeout(() => toast.remove(), 250);
      }
    }, duration);
  },

  // Save button loading handler
  initForms: function() {
    document.querySelectorAll('form[data-loading-state]').forEach(form => {
      form.addEventListener('submit', function(e) {
        if (!form.checkValidity()) {
          return;
        }
        const btn = form.querySelector('button[type="submit"]');
        if (btn && !btn.disabled) {
          btn.dataset.originalText = btn.innerHTML;
          btn.disabled = true;
          btn.innerHTML = `<span class="spinner"></span> <span>${btn.dataset.loadingText || 'Saving...'}</span>`;
        }
      });
    });
  },

  // Network Status / Offline Banner
  initOfflineSupport: function() {
    const banner = document.getElementById('offline-banner');
    if (!banner) return;

    function updateStatus() {
      if (!navigator.onLine) {
        banner.style.display = 'block';
        banner.textContent = 'You are currently offline. New entries cannot be saved until connection is restored.';
      } else {
        banner.style.display = 'none';
      }
    }

    window.addEventListener('online', () => {
      updateStatus();
      Hisabio.toast('Internet connection restored.', 'success');
    });

    window.addEventListener('offline', () => {
      updateStatus();
      Hisabio.toast('You are offline. Transactions require network to sync.', 'error');
    });

    updateStatus();
  },

  // PWA Service Worker Registration
  initPWA: function() {
    if ('serviceWorker' in navigator && window.location.protocol.startsWith('http')) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('service-worker.js')
          .then(reg => console.log('Service Worker registered:', reg.scope))
          .catch(err => console.log('Service Worker registration skipped/failed:', err));
      });
    }
  }
};

document.addEventListener('DOMContentLoaded', () => {
  Hisabio.initForms();
  Hisabio.initOfflineSupport();
  Hisabio.initPWA();
});
