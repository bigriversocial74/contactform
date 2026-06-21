document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  document.querySelectorAll('[data-agent-sidebar]').forEach(function (sidebar) {
    var tabs = sidebar.querySelectorAll('[data-agent-side-tab]');
    var panels = sidebar.querySelectorAll('[data-agent-side-panel]');
    if (!tabs.length || !panels.length) return;

    function activate(name) {
      tabs.forEach(function (tab) {
        var active = tab.dataset.agentSideTab === name;
        tab.classList.toggle('is-active', active);
        tab.setAttribute('aria-selected', active ? 'true' : 'false');
        tab.tabIndex = active ? 0 : -1;
      });

      panels.forEach(function (panel) {
        var active = panel.dataset.agentSidePanel === name;
        panel.classList.toggle('is-active', active);
        panel.hidden = !active;
      });
    }

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        activate(tab.dataset.agentSideTab);
      });
      tab.addEventListener('keydown', function (event) {
        if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
        event.preventDefault();
        event.stopPropagation();
        var items = Array.prototype.slice.call(tabs);
        var index = items.indexOf(tab);
        var next = event.key === 'ArrowRight' ? index + 1 : index - 1;
        if (next < 0) next = items.length - 1;
        if (next >= items.length) next = 0;
        items[next].focus();
        activate(items[next].dataset.agentSideTab);
      });
    });

    var requested = new URLSearchParams(window.location.search).get('side');
    var initial = requested === 'merchant' ? 'merchant' : 'agents';
    activate(initial);
  });
});