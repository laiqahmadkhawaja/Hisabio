/**
 * Hisabio - AJAX Fetch Wrapper with CSRF & Error Management
 */

const HisabioAJAX = {
  getCsrfToken: function() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  },

  request: async function(url, options = {}) {
    const defaultHeaders = {
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': this.getCsrfToken(),
      'Accept': 'application/json'
    };

    if (!(options.body instanceof FormData)) {
      defaultHeaders['Content-Type'] = 'application/json';
    }

    options.headers = { ...defaultHeaders, ...(options.headers || {}) };

    try {
      const response = await fetch(url, options);
      const data = await response.json();
      
      if (!response.ok) {
        throw new Error(data.message || `HTTP error! Status: ${response.status}`);
      }
      
      return data;
    } catch (error) {
      console.error('AJAX Error:', error);
      Hisabio.toast(error.message || 'An unexpected network error occurred.', 'error');
      throw error;
    }
  },

  get: function(url) {
    return this.request(url, { method: 'GET' });
  },

  post: function(url, body) {
    const isFormData = body instanceof FormData;
    return this.request(url, {
      method: 'POST',
      body: isFormData ? body : JSON.stringify(body)
    });
  }
};
