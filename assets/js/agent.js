window.Microgifter = window.Microgifter || {};

(function () {
  'use strict';

  function bindTabs(root) {
    var tabs = Array.from(root.querySelectorAll('[data-agent-tab]'));
    var panels = Array.from(root.querySelectorAll('[data-agent-panel]'));

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        var target = tab.dataset.agentTab;
        tabs.forEach(function (item) {
          item.classList.toggle('is-active', item === tab);
          item.setAttribute('aria-selected', item === tab ? 'true' : 'false');
        });
        panels.forEach(function (panel) {
          var active = panel.dataset.agentPanel === target;
          panel.classList.toggle('is-active', active);
          panel.hidden = !active;
        });
      });
    });
  }

  function bindTest(root) {
    var button = root.querySelector('[data-agent-test]');
    var output = root.querySelector('[data-agent-response]');
    var textarea = root.querySelector('textarea[name="message"]');
    if (!button || !output || !textarea) return;

    button.addEventListener('click', function () {
      var message = textarea.value.trim();
      if (!message) {
        Microgifter.setText(output, 'Add a message to test the workspace.');
        return;
      }
      if (Microgifter.onboarding && !Microgifter.isAuthenticated()) {
        Microgifter.onboarding.saveDraft('agent_test', { message: message });
      }
      Microgifter.setText(output, 'Test agent draft ready: ' + message);
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-mg-agent]').forEach(function (root) {
      bindTabs(root);
      bindTest(root);
    });
  });
})();
