document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-merchant-agent-chat]');
  if (!root || !window.Microgifter) return;

  var actionSelect = root.querySelector('[data-agent-chat-approval]');
  var modeInput = root.querySelector('[data-agent-chat-mode]');
  if (!actionSelect || !modeInput) return;

  function hasAdvancedOption() {
    return !!actionSelect.querySelector('option[value="admin_operator"]');
  }

  function addAdvancedOption() {
    if (hasAdvancedOption()) return;
    var option = document.createElement('option');
    option.value = 'admin_operator';
    option.textContent = 'Advanced run';
    option.setAttribute('data-agent-admin-operator', '1');
    actionSelect.appendChild(option);

    var note = document.createElement('div');
    note.className = 'mg-agent-context-summary mg-agent-super-operator-note';
    note.textContent = 'Advanced run mode is available on this account.';
    var summary = root.querySelector('[data-agent-chat-summary]');
    if (summary && summary.parentNode && !root.querySelector('.mg-agent-super-operator-note')) {
      summary.parentNode.insertBefore(note, summary.nextSibling);
    }
  }

  function syncMode() {
    if (actionSelect.value === 'admin_operator') {
      modeInput.value = 'execute_plan';
      root.setAttribute('data-agent-admin-operator-active', '1');
    } else {
      modeInput.value = 'advisor';
      root.removeAttribute('data-agent-admin-operator-active');
    }
    modeInput.dispatchEvent(new Event('change', { bubbles: true }));
  }

  actionSelect.addEventListener('change', syncMode);

  Microgifter.get('/api/ai/merchant-agent-chat.php').then(function (response) {
    var data = response && response.data ? response.data : response;
    if (data && data.admin_operator_available) addAdvancedOption();
    syncMode();
  }).catch(function () {
    syncMode();
  });
});
