// API fetch routing: redirect /api/* to PHP server in HMR mode
(function() {
  var meta = document.querySelector('meta[name="api-base"]');
  var base = meta ? meta.getAttribute('content') || '' : '';
  if (base) {
    var orig = window.fetch;
    window.fetch = function(u, opts) {
      if (typeof u === 'string' && u.indexOf('/api/') === 0) {
        u = base + u;
        opts = Object.assign({ credentials: 'include' }, opts || {});
      }
      return orig.call(this, u, opts);
    };
  }
})();

// Axios-based public API helper
(function() {
  var meta = document.querySelector('meta[name="api-base"]');
  var base = meta ? meta.getAttribute('content') || '' : '';
  window.__api = axios.create({ withCredentials: true });
  if (base) {
    window.__api.defaults.baseURL = base;
  }
  // Copy CSRF token from meta if present
  var csrfMeta = document.querySelector('meta[name="csrf-token"]');
  if (csrfMeta) {
    window.__api.defaults.headers.common['X-CSRF-Token'] = csrfMeta.getAttribute('content');
  }
})();

// ImageKit URL transformation helper
window.imgTransform = function(url, w, h, extras) {
  if (!url) return url;
  if (url.indexOf('ik.imagekit.io') === -1) return url;
  var sep = url.indexOf('?') !== -1 ? '&' : '?';
  var base = url + sep + 'tr=w-' + w + ',h-' + h + ',c-crop,fo-auto';
  return extras ? base + ',' + extras : base;
};
