document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var tabs = document.querySelector('[data-agent-tabs]');
  var list = document.querySelector('[data-saved-agent-list]');
  if (!tabs && !list) return;

  var state = { agents: [], loading: false };

  function currentAgentId() {
    return new URLSearchParams(window.location.search).get('agent');
  }

  function draftKey(id) {
    return 'mg_agent_category_' + (id || 'default');
  }

  function readDraft(id) {
    try {
      var value = JSON.parse(localStorage.getItem(draftKey(id)) || 'null');
      return value && typeof value === 'object' ? value : { category: null, values: {} };
    } catch (error) {
      return { category: null, values: {} };
    }
  }

  function storeAgentDraft(agent) {
    if (!agent || !agent.id) return;
    localStorage.setItem(draftKey(agent.id), JSON.stringify({
      category: agent.category || null,
      values: agent.config || {}
    }));
  }

  function formatTime(value) {
    var date = value ? new Date(value) : null;
    if (!date || Number.isNaN(date.getTime())) return 'Updated recently';
    return 'Updated ' + date.toLocaleString([], { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
  }

  function setCanvasStatus(text) {
    var node = document.querySelector('[data-agent-canvas-status]');
    if (node) node.textContent = text;
  }

  function activeAgent() {
    var id = currentAgentId();
    return state.agents.find(function (agent) { return agent.id === id; }) || null;
  }

  function renderTabs() {
    if (!tabs) return;
    tabs.querySelectorAll('[data-custom-agent-tab]').forEach(function (node) { node.remove(); });
  }

  function renderSavedAgents() {
    if (!list) return;
    list.innerHTML = '';

    if (!state.agents.length) {
      var empty = document.createElement('p');
      empty.className = 'mg-saved-agent-empty';
      empty.textContent = state.loading ? 'Loading agents…' : 'No saved agents yet.';
      list.appendChild(empty);
      return;
    }

    state.agents.forEach(function (agent) {
      var isRunning = agent.runtime_status === 'running';
      var row = document.createElement('div');
      row.className = 'mg-saved-agent-row' + (isRunning ? ' is-running' : '') + (currentAgentId() === agent.id ? ' is-active' : '');
      row.dataset.savedAgentDynamic = agent.id;

      var open = document.createElement('button');
      open.type = 'button';
      open.className = 'mg-saved-agent-open';
      open.dataset.loadAgent = agent.id;
      open.innerHTML = '<strong></strong><span>Saved agent workspace</span><div class="mg-agent-runtime-meta"><span class="mg-agent-runtime-status"></span><time></time></div>';
      open.querySelector('strong').textContent = agent.name;
      open.querySelector('.mg-agent-runtime-status').textContent = isRunning ? 'Running' : 'Paused';
      open.querySelector('.mg-agent-runtime-status').classList.toggle('is-running', isRunning);
      open.querySelector('time').textContent = formatTime(agent.updated_at);
      open.querySelector('time').dateTime = agent.updated_at || '';

      var actions = document.createElement('div');
      actions.className = 'mg-saved-agent-actions';
      actions.innerHTML = '<button type="button" class="mg-agent-inline-action" data-edit-agent-name>Edit</button><button type="button" class="mg-saved-agent-menu" data-saved-agent-menu aria-expanded="false">•••</button><div class="mg-saved-agent-popover" data-saved-agent-popover hidden><button type="button" data-load-agent>Load agent</button><button type="button" data-archive-agent>Archive agent</button><button type="button" class="is-danger" data-delete-agent>Delete agent</button></div>';
      actions.querySelector('[data-saved-agent-menu]').setAttribute('aria-label', 'Manage ' + agent.name);
      actions.querySelector('[data-load-agent]').dataset.loadAgent = agent.id;

      var toggle = document.createElement('button');
      toggle.type = 'button';
      toggle.className = 'mg-agent-runtime-toggle ' + (isRunning ? 'is-pause' : 'is-start');
      toggle.dataset.agentRuntimeToggle = agent.id;
      toggle.dataset.nextStatus = isRunning ? 'paused' : 'running';
      toggle.setAttribute('aria-label', (isRunning ? 'Pause ' : 'Start ') + agent.name);
      toggle.textContent = isRunning ? 'Pause agent' : 'Start agent';

      row.appendChild(open);
      row.appendChild(actions);
      row.appendChild(toggle);
      list.appendChild(row);
    });
  }

  function render() {
    renderTabs();
    renderSavedAgents();
    var active = activeAgent();
    if (active) {
      storeAgentDraft(active);
      var isRunning = active.runtime_status === 'running';
      setCanvasStatus(isRunning ? 'Saved · Running' : 'Saved · Paused');
      var saveButton = document.querySelector('[data-save-agent]');
      if (saveButton) {
        saveButton.disabled = false;
        saveButton.textContent = isRunning ? 'Pause agent' : 'Start agent';
        saveButton.classList.toggle('is-running', isRunning);
        saveButton.setAttribute('aria-label', isRunning ? 'Pause this saved agent' : 'Start this saved agent');
      }
    }
    document.dispatchEvent(new CustomEvent('mg:agents:rendered', { detail: { agents: state.agents, active: active } }));
  }

  async function loadAgents() {
    state.loading = true;
    renderSavedAgents();
    try {
      var response = await Microgifter.get('/api/agents/index.php?lifecycle=active');
      state.agents = response.data && Array.isArray(response.data.agents) ? response.data.agents : [];
      state.agents.forEach(storeAgentDraft);
      state.loading = false;
      render();
      return state.agents;
    } catch (error) {
      state.loading = false;
      renderSavedAgents();
      setCanvasStatus(error.message || 'Unable to load agents');
      throw error;
    }
  }

  function applyAgentUpdate(agent) {
    if (!agent || !agent.id) return null;
    var index = state.agents.findIndex(function (item) { return item.id === agent.id; });
    if (index === -1) state.agents.unshift(agent);
    else state.agents[index] = Object.assign({}, state.agents[index], agent);
    storeAgentDraft(agent);
    render();
    return state.agents.find(function (item) { return item.id === agent.id; }) || null;
  }

  function setRuntimeStatus(id, status) {
    var agent = state.agents.find(function (item) { return item.id === id; });
    if (!agent || !['running', 'paused'].includes(status)) return null;
    var previous = Object.assign({}, agent);
    agent.runtime_status = status;
    agent.updated_at = new Date().toISOString();
    render();
    return previous;
  }

  window.Microgifter = window.Microgifter || {};
  window.Microgifter.agents = {
    refresh: loadAgents,
    getActive: activeAgent,
    getAll: function () { return state.agents.slice(); },
    applyUpdate: applyAgentUpdate,
    setRuntimeStatus: setRuntimeStatus
  };

  async function saveCurrentAgent() {
    var id = currentAgentId();
    var existing = activeAgent();
    var draft = readDraft(id || 'default');

    if (existing) {
      var updated = await Microgifter.patch('/api/agents/item.php', {
        id: existing.id,
        name: existing.name,
        category: draft.category,
        config: draft.values || {}
      });
      applyAgentUpdate(updated.data.agent);
      setCanvasStatus('Saved to account');
      return updated.data.agent;
    }

    var created = await Microgifter.post('/api/agents/index.php', {
      name: 'Agent ' + (state.agents.length + 1),
      category: draft.category,
      config: draft.values || {}
    });
    var agent = created.data.agent;
    state.agents.unshift(agent);
    storeAgentDraft(agent);
    window.location.href = '/agent.php?agent=' + encodeURIComponent(agent.id);
    return agent;
  }

  function closeMenus() {
    document.querySelectorAll('[data-saved-agent-popover]').forEach(function (node) { node.hidden = true; });
    document.querySelectorAll('[data-saved-agent-menu]').forEach(function (node) { node.setAttribute('aria-expanded', 'false'); });
  }

  async function deleteAgent(id) {
    var agent = state.agents.find(function (item) { return item.id === id; });
    if (!agent || !window.confirm('Delete "' + agent.name + '" permanently? Purchase, claim, and invoice history will remain preserved.')) return;
    await Microgifter.delete('/api/agents/item.php', { id: id });
    state.agents = state.agents.filter(function (item) { return item.id !== id; });
    localStorage.removeItem(draftKey(id));
    window.location.href = '/agent.php';
  }

  document.addEventListener('click', async function (event) {
    var tabDelete = event.target.closest('[data-agent-tab-delete]');
    if (tabDelete) {
      event.preventDefault();
      event.stopPropagation();
      await deleteAgent(tabDelete.dataset.agentTabDelete);
      return;
    }

    var load = event.target.closest('[data-load-agent]');
    if (load) {
      event.preventDefault();
      event.stopPropagation();
      window.location.href = '/agent.php?agent=' + encodeURIComponent(load.dataset.loadAgent);
      return;
    }

    var menu = event.target.closest('[data-saved-agent-menu]');
    if (menu) {
      event.preventDefault();
      event.stopImmediatePropagation();
      var popover = menu.parentElement.querySelector('[data-saved-agent-popover]');
      var open = popover.hidden;
      closeMenus();
      popover.hidden = !open;
      menu.setAttribute('aria-expanded', open ? 'true' : 'false');
      return;
    }

    var archive = event.target.closest('[data-archive-agent]');
    if (archive) {
      event.preventDefault();
      event.stopPropagation();
      var archiveRow = archive.closest('[data-saved-agent-dynamic], .mg-saved-agent-row');
      var archiveId = archiveRow && archiveRow.dataset.savedAgentDynamic;
      if (!archiveId) return;
      if (!window.confirm('Archive this agent? You can keep purchase and invoice history attached while removing it from the active list.')) return;
      await Microgifter.post('/api/agents/archive.php', { id: archiveId });
      state.agents = state.agents.filter(function (agent) { return agent.id !== archiveId; });
      if (currentAgentId() === archiveId) window.location.href = '/agent.php';
      else render();
      return;
    }

    var remove = event.target.closest('[data-delete-agent]');
    if (remove) {
      event.preventDefault();
      event.stopPropagation();
      var deleteRow = remove.closest('.mg-saved-agent-row');
      var deleteId = deleteRow && deleteRow.dataset.savedAgentDynamic;
      if (deleteId) await deleteAgent(deleteId);
      return;
    }

    var save = event.target.closest('[data-save-agent]');
    if (save && !activeAgent()) {
      event.preventDefault();
      save.disabled = true;
      try { await saveCurrentAgent(); }
      catch (error) { setCanvasStatus(error.message || 'Unable to save agent'); save.disabled = false; }
    }
  });

  document.addEventListener('click', function (event) {
    if (event.target.closest('[data-saved-agent-menu], [data-saved-agent-popover]')) return;
    closeMenus();
  });
  loadAgents();
});