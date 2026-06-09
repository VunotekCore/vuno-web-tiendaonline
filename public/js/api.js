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

// ImageKit URL transformation helper
window.imgTransform = function(url, w, h) {
  if (!url) return url;
  if (url.indexOf('ik.imagekit.io') === -1) return url;
  var sep = url.indexOf('?') !== -1 ? '&' : '?';
  return url + sep + 'tr=w-' + w + ',h-' + h + ',fo-auto';
};
