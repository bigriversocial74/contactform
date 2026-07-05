document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-location-redemption-manager]');
  if (!root) return;

  var panels = Array.prototype.slice.call(root.querySelectorAll('[data-location-section]'));
  var tabs = Array.prototype.slice.call(root.querySelectorAll('[data-location-tab]'));
  var form = root.querySelector('[data-location-form]');

  function resetAddLocationForm() {
    if (!form) return;

    form.reset();

    if (form.elements.location_id) form.elements.location_id.value = '';
    if (form.elements.country_code) form.elements.country_code.value = 'US';
    if (form.elements.timezone) form.elements.timezone.value = form.elements.timezone.defaultValue || 'America/Phoenix';
    if (form.elements.status) form.elements.status.value = 'active';
    if (form.elements.claim_code) {
      form.elements.claim_code.value = '';
      form.elements.claim_code.required = true;
      form.elements.claim_code.placeholder = 'PHX-001';
    }

    var help = form.querySelector('[data-location-code-help]');
    if (help) help.textContent = 'Required for a new location. Codes are stored securely and cannot be displayed again.';

    var status = form.querySelector('[data-location-status]');
    if (status) status.textContent = '';
  }

  function currentPanelForHash(hash) {
    return hash === '#location-editor-panel' || hash === '#locations-add-location' ? 'add-location' : 'overview';
  }

  function setActiveTab(panelName, activeTab) {
    tabs.forEach(function (tab) {
      var isActive = activeTab ? tab === activeTab : tab.dataset.locationTab === panelName && tab.dataset.locationDefault === 'true';
      tab.classList.toggle('is-active', isActive);
      if (isActive) tab.setAttribute('aria-current', 'page');
      else tab.removeAttribute('aria-current');
    });
  }

  function activatePanel(panelName, activeTab, shouldScroll) {
    panels.forEach(function (panel) {
      var isActive = panel.dataset.locationSection === panelName;
      panel.hidden = !isActive;
      panel.classList.toggle('is-active', isActive);
    });

    setActiveTab(panelName, activeTab);

    if (shouldScroll) {
      var target = panelName === 'add-location' ? root.querySelector('#location-editor-panel') : root.querySelector('#locations-overview');
      if (target && typeof target.scrollIntoView === 'function') {
        window.requestAnimationFrame(function () {
          target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
      }
    }
  }

  root.addEventListener('click', function (event) {
    var tab = event.target.closest('[data-location-tab]');
    if (tab && root.contains(tab)) {
      event.preventDefault();
      var panelName = tab.dataset.locationTab || currentPanelForHash(tab.getAttribute('href'));
      activatePanel(panelName, tab, true);
      if (tab.getAttribute('href')) history.replaceState(null, '', tab.getAttribute('href'));
      return;
    }

    var addTrigger = event.target.closest('[data-location-open-add]');
    if (addTrigger && root.contains(addTrigger)) {
      event.preventDefault();
      resetAddLocationForm();
      activatePanel('add-location', root.querySelector('[data-location-tab="add-location"]'), true);
      history.replaceState(null, '', '#location-editor-panel');
      return;
    }

    var editableLocation = event.target.closest('[data-location]');
    if (editableLocation && root.contains(editableLocation)) {
      activatePanel('add-location', root.querySelector('[data-location-tab="add-location"]'), true);
      history.replaceState(null, '', '#location-editor-panel');
    }
  });

  window.addEventListener('hashchange', function () {
    var panelName = currentPanelForHash(window.location.hash);
    activatePanel(panelName, root.querySelector('[data-location-tab="' + panelName + '"]'), false);
  });

  var initialPanel = currentPanelForHash(window.location.hash);
  activatePanel(initialPanel, root.querySelector('[data-location-tab="' + initialPanel + '"]'), false);
});
