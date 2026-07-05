document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-distribution-reach-center]');
  if (!root) return;

  var panels = Array.prototype.slice.call(root.querySelectorAll('[data-distribution-section]'));
  var tabs = Array.prototype.slice.call(root.querySelectorAll('[data-distribution-tab]'));
  var form = root.querySelector('[data-program-form]');

  function panelForHash(hash) {
    return hash === '#distribution-editor' || hash === '#distribution-create' ? 'create' : 'overview';
  }

  function setActiveTab(panelName, activeTab) {
    tabs.forEach(function (tab) {
      var isActive = activeTab ? tab === activeTab : tab.dataset.distributionTab === panelName && tab.dataset.distributionDefault === 'true';
      tab.classList.toggle('is-active', isActive);
      if (isActive) tab.setAttribute('aria-current', 'page');
      else tab.removeAttribute('aria-current');
    });
  }

  function activatePanel(panelName, activeTab, shouldScroll) {
    panels.forEach(function (panel) {
      var isActive = panel.dataset.distributionSection === panelName;
      panel.hidden = !isActive;
      panel.classList.toggle('is-active', isActive);
    });

    setActiveTab(panelName, activeTab);

    if (shouldScroll) {
      var target = panelName === 'create' ? root.querySelector('#distribution-editor') : root.querySelector('#distribution-overview');
      if (target && typeof target.scrollIntoView === 'function') {
        window.requestAnimationFrame(function () {
          target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
      }
    }
  }

  function clearCreateStatus() {
    var status = root.querySelector('[data-program-status-message]');
    if (status) status.textContent = '';
  }

  root.addEventListener('click', function (event) {
    var tab = event.target.closest('[data-distribution-tab]');
    if (tab && root.contains(tab)) {
      event.preventDefault();
      var panelName = tab.dataset.distributionTab || panelForHash(tab.getAttribute('href'));
      activatePanel(panelName, tab, true);
      if (tab.getAttribute('href')) history.replaceState(null, '', tab.getAttribute('href'));
      return;
    }

    var createTrigger = event.target.closest('[data-distribution-open-create], [data-program-new]');
    if (createTrigger && root.contains(createTrigger)) {
      clearCreateStatus();
      activatePanel('create', root.querySelector('[data-distribution-tab="create"]'), true);
      history.replaceState(null, '', '#distribution-editor');
      if (form && createTrigger.hasAttribute('data-distribution-open-create') && !createTrigger.hasAttribute('data-program-new')) {
        form.reset();
        if (form.elements.program_id) form.elements.program_id.value = '';
        if (form.elements.status) form.elements.status.value = 'draft';
      }
    }
  });

  window.addEventListener('hashchange', function () {
    var panelName = panelForHash(window.location.hash);
    activatePanel(panelName, root.querySelector('[data-distribution-tab="' + panelName + '"]'), false);
  });

  var initialPanel = panelForHash(window.location.hash);
  activatePanel(initialPanel, root.querySelector('[data-distribution-tab="' + initialPanel + '"]'), false);
});
