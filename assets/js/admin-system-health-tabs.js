document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-admin-system-health]');
  if (!root) return;
  var buttons = Array.prototype.slice.call(root.querySelectorAll('[data-health-tab]'));
  var panels = Array.prototype.slice.call(root.querySelectorAll('[data-health-tab-panel]'));
  if (!buttons.length || !panels.length) return;

  var storageKey = 'microgifter.systemHealth.activeTab';

  function availableTab(value) {
    return buttons.some(function (button) { return button.dataset.healthTab === value; }) ? value : 'overview';
  }

  function activeFromHash() {
    var hash = String(window.location.hash || '').replace(/^#/, '');
    if (hash.indexOf('health-') === 0) hash = hash.slice(7);
    return availableTab(hash || window.localStorage.getItem(storageKey) || 'overview');
  }

  function activate(tab, updateHash) {
    tab = availableTab(tab);
    buttons.forEach(function (button) {
      var active = button.dataset.healthTab === tab;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    panels.forEach(function (panel) {
      var active = panel.dataset.healthTabPanel === tab;
      panel.classList.toggle('is-active', active);
      if (active) panel.removeAttribute('hidden');
      else panel.setAttribute('hidden', 'hidden');
    });
    try { window.localStorage.setItem(storageKey, tab); } catch (error) {}
    if (updateHash && window.history && window.history.replaceState) {
      window.history.replaceState(null, '', '#health-' + tab);
    }
  }

  buttons.forEach(function (button) {
    button.addEventListener('click', function () {
      activate(button.dataset.healthTab, true);
    });
  });

  window.addEventListener('hashchange', function () {
    activate(activeFromHash(), false);
  });

  activate(activeFromHash(), false);
});
