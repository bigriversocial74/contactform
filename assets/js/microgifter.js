window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;

  MG.qs = function (selector, scope) {
    return (scope || document).querySelector(selector);
  };

  MG.qsa = function (selector, scope) {
    return Array.prototype.slice.call((scope || document).querySelectorAll(selector));
  };

  MG.getCsrfToken = function () {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  };

  MG.isAuthenticated = function () {
    return Boolean(document.body && document.body.dataset.authenticated === 'true');
  };

  MG.setText = function (selectorOrNode, value, scope) {
    var node = typeof selectorOrNode === 'string' ? MG.qs(selectorOrNode, scope) : selectorOrNode;
    if (node) node.textContent = value === undefined || value === null ? '' : String(value);
  };

  MG.readForm = function (form) {
    var data = {};
    if (!form) return data;
    new FormData(form).forEach(function (value, key) { data[key] = value; });
    return data;
  };

  MG.setStatus = function (target, message, type) {
    var node = typeof target === 'string' ? MG.qs(target) : target;
    if (!node) return;
    node.textContent = message || '';
    node.classList.toggle('is-visible', Boolean(message));
    node.classList.toggle('is-success', type === 'success');
    node.classList.toggle('is-error', type === 'error');
  };

  MG.setBusy = function (button, busy, busyText) {
    if (!button) return;
    if (busy) {
      button.dataset.originalText = button.textContent;
      button.dataset.busyText = busyText || 'Working...';
      button.textContent = button.dataset.busyText;
      button.disabled = true;
      return;
    }
    var resolvedText = button.textContent;
    if (!resolvedText || resolvedText === button.dataset.busyText) {
      button.textContent = button.dataset.originalText || resolvedText;
    }
    button.disabled = false;
    delete button.dataset.busyText;
    delete button.dataset.originalText;
  };

  MG.toast = function (message, type) {
    var node = MG.qs('[data-mg-toast]');
    if (!node) {
      node = document.createElement('div');
      node.className = 'mg-toast';
      node.setAttribute('data-mg-toast', '');
      document.body.appendChild(node);
    }
    node.textContent = message || '';
    node.classList.toggle('is-visible', Boolean(message));
    node.classList.toggle('is-success', type === 'success');
    node.classList.toggle('is-error', type === 'error');
    if (message) {
      window.clearTimeout(node._mgTimer);
      node._mgTimer = window.setTimeout(function () {
        node.classList.remove('is-visible', 'is-success', 'is-error');
      }, 4200);
    }
  };

  MG.ensurePwaInstallSupport = function () {
    if (!document.querySelector('link[rel="manifest"]')) {
      var link = document.createElement('link');
      link.rel = 'manifest';
      link.href = '/manifest.php';
      document.head.appendChild(link);
    }
    if (!('serviceWorker' in navigator)) return;
    var local = ['localhost', '127.0.0.1', '::1'].indexOf(window.location.hostname) !== -1;
    if (!window.isSecureContext && !local) return;
    navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function () {});
  };

  document.addEventListener('DOMContentLoaded', function () {
    document.documentElement.classList.add('mg-js-ready');
    document.documentElement.classList.toggle('mg-is-authenticated', MG.isAuthenticated());
    document.documentElement.classList.toggle('mg-is-guest', !MG.isAuthenticated());
    MG.ensurePwaInstallSupport();
  });
})(window, document);
