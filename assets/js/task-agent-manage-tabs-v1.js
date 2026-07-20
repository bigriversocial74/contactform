document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var selectedNode = document.getElementById('mg-selected-agent-id');
  var selectedAgentId = selectedNode ? JSON.parse(selectedNode.textContent || '""') : '';
  if (!selectedAgentId) return;

  var modal = document.querySelector('[data-agent-manage-modal]');
  if (!modal) return;

  var selectedAgent = null;

  function csrf() {
    var node = document.querySelector('meta[name="csrf-token"]');
    return node ? node.content : '';
  }

  async function request(method, url, payload) {
    var options = { method: method, credentials: 'same-origin', headers: { Accept: 'application/json' } };
    if (payload) {
      options.headers['Content-Type'] = 'application/json';
      options.headers['X-CSRF-Token'] = csrf();
      options.body = JSON.stringify(payload);
    }
    var response = await fetch(url, options);
    var json = await response.json();
    if (!response.ok || !json.ok) throw new Error(json.message || 'Unable to complete the agent request.');
    return json.data || json;
  }

  function selectTab(name) {
    modal.querySelectorAll('[data-agent-manage-tab]').forEach(function (button) {
      var active = button.getAttribute('data-agent-manage-tab') === name;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    modal.querySelectorAll('[data-agent-manage-panel]').forEach(function (panel) {
      panel.hidden = panel.getAttribute('data-agent-manage-panel') !== name;
    });
  }

  function openModal(tab) {
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('mg-agent-manage-open');
    selectTab(tab || 'manage');
  }

  async function loadSelectedAgent() {
    var data = await request('GET', '/api/agents/index.php?lifecycle=active');
    selectedAgent = (data.agents || []).find(function (agent) { return agent.id === selectedAgentId; }) || null;
  }

  async function duplicateAgent() {
    if (!selectedAgent) await loadSelectedAgent();
    if (!selectedAgent) throw new Error('Unable to find this agent.');

    var config = Object.assign({}, selectedAgent.config || {}, { workspace_tab_open: true });
    var data = await request('POST', '/api/agents/index.php', {
      name: selectedAgent.name,
      category: selectedAgent.category,
      config: config
    });
    var agent = data.agent;
    await request('POST', '/api/agents/status.php', { id: agent.id, status: 'running' });
    window.location.href = '/agent.php?agent_id=' + encodeURIComponent(agent.id);
  }

  document.addEventListener('click', function (event) {
    var tabButton = event.target.closest('[data-agent-manage-tab]');
    if (tabButton) {
      event.preventDefault();
      event.stopImmediatePropagation();
      selectTab(tabButton.getAttribute('data-agent-manage-tab'));
      return;
    }

    var tabSettings = event.target.closest('[data-agent-tab-settings]');
    if (tabSettings) {
      var id = tabSettings.getAttribute('data-agent-tab-settings') || '';
      if (id === selectedAgentId) {
        event.preventDefault();
        event.stopImmediatePropagation();
        openModal('settings');
      } else if (id) {
        event.preventDefault();
        window.location.href = '/agent.php?agent_id=' + encodeURIComponent(id) + '&manage=settings';
      }
      return;
    }

    var manageButton = event.target.closest('[data-agent-manage-open]');
    if (manageButton) {
      event.preventDefault();
      window.setTimeout(function () { selectTab('manage'); }, 0);
      return;
    }

    var duplicate = event.target.closest('[data-agent-action="duplicate"]');
    if (duplicate) {
      event.preventDefault();
      event.stopImmediatePropagation();
      duplicate.disabled = true;
      duplicateAgent().catch(function (error) {
        duplicate.disabled = false;
        var status = modal.querySelector('[data-agent-manage-status]');
        if (status) status.textContent = error.message;
      });
    }
  }, true);

  loadSelectedAgent().catch(function () {});

  var requestedTab = new URLSearchParams(window.location.search).get('manage');
  if (requestedTab === 'settings') {
    openModal('settings');
  }
});