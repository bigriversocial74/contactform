(function () {
  'use strict';

  function isLocalHost() {
    return ['localhost', '127.0.0.1', '::1'].indexOf(window.location.hostname) !== -1;
  }

  function ensureManifestLink() {
    if (document.querySelector('link[rel="manifest"]')) return;
    var link = document.createElement('link');
    link.rel = 'manifest';
    link.href = '/manifest.php';
    document.head.appendChild(link);
  }

  function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) return;
    if (!window.isSecureContext && !isLocalHost()) return;
    navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function () {});
  }

  function boot() {
    ensureManifestLink();
    registerServiceWorker();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else {
    boot();
  }
})();
