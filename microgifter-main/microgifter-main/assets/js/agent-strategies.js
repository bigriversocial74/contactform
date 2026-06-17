document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-agent-control-center]');
  if (!root || !window.Microgifter) return;

  var MG = window.Microgifter;
  var tabs = Array.prototype.slice.call(root.querySelectorAll('[data-agent-control-tab]'));
  var panels = Array.prototype.slice.call(root.querySelectorAll('[data-agent-control-panel]'));
  var strategyPanel = root.querySelector('[data-agent-control-panel="strategies"]');
  if (!strategyPanel) return;

  var list = strategyPanel.querySelector('[data-strategy-list]');
  var loading = strategyPanel.querySelector('[data-strategy-loading]');
  var empty = strategyPanel.querySelector('[data-strategy-empty]');
  var errorState = strategyPanel.querySelector('[data-strategy-error]');
  var errorMessage = strategyPanel.querySelector('[data-strategy-error-message]');
  var statusText = strategyPanel.querySelector('[data-strategy-status-text]');
  var statusFilter = strategyPanel.querySelector('[data-strategy-status]');
  var agentFilter = strategyPanel.querySelector('[data-strategy-agent-filter]');
  var pagination = strategyPanel.querySelector('[data-strategy-pagination]');
  var moreButton = strategyPanel.querySelector('[data-strategy-more]');
  var editor = strategyPanel.querySelector('[data-strategy-editor]');
  var form = strategyPanel.querySelector('[data-strategy-form]');
  var agentSelect = form.elements.agent_id;
  var formStatus = strategyPanel.querySelector('[data-strategy-form-status]');
  var editorTitle = strategyPanel.querySelector('[data-strategy-editor-title]');
  var editorEyebrow = strategyPanel.querySelector('[data-strategy-editor-eyebrow]');
  var saveButton = strategyPanel.querySelector('[data-strategy-save]');
  var agents = [];
  var strategies = [];
  var nextCursor = null;
  var loadingStrategies = false;
  var activeView = 'builder';

  function data(response) {
    return response && response.data ? response.data : response;
  }

  function hide(node, hidden) {
    if (node) node.hidden = Boolean(hidden);
  }

  function text(node, value) {
    if (node) node.textContent = value == null ? '' : String(value);
  }

  function element(tag, className, value) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (value !== undefined) node.textContent = String(value);
    return node;
  }

  function label(value) {
    return String(value || '').replace(/[_-]+/g, ' ').replace(/\b\w/g, function (letter) { return letter.toUpperCase(); });
  }

  function formatDate(value) {
    var date = new Date(String(value || '').replace(' ', 'T') + (String(value || '').includes('Z') ? '' : 'Z'));
    return Number.isNaN(date.getTime()) ? '' : date.toLocaleString([], { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
  }

  function setView(view, updateUrl) {
    activeView = view === 'strategies' ? 'strategies' : 'builder';
    tabs.forEach(function (tab) {
      var selected = tab.dataset.agentControlTab === activeView;
      tab.classList.toggle('is-active', selected);
      tab.setAttribute('aria-selected', selected ? 'true' : 'false');
    });
    panels.forEach(function (panel) { panel.hidden = panel.dataset.agentControlPanel !== activeView; });
    if (updateUrl) {
      var url = new URL(window.location.href);
      if (activeView === 'strategies') url.searchParams.set('view', 'strategies');
      else url.searchParams.delete('view');
      window.history.replaceState({}, '', url);
    }
    if (activeView === 'strategies' && !agents.length) loadAgents().then(function () { return loadStrategies(false); });
  }

  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () { setView(tab.dataset.agentControlTab, true); });
  });

  function populateAgentSelects() {
    [agentFilter, agentSelect].forEach(function (select) {
      var current = select.value;
      Array.prototype.slice.call(select.options).forEach(function (option, index) {
        if (index > 0) option.remove();
      });
      agents.forEach(function (agent) {
        var option = document.createElement('option');
        option.value = agent.id;
        option.textContent = agent.name + (agent.runtime_status === 'running' ? ' · Running' : ' · Paused');
        select.appendChild(option);
      });
      if (current && agents.some(function (agent) { return agent.id === current; })) select.value = current;
    });
  }

  async function loadAgents() {
    var response = await MG.get('/api/agents/index.php?lifecycle=active');
    agents = data(response).agents || [];
    populateAgentSelects();
    var requested = new URLSearchParams(window.location.search).get('agent');
    if (requested && agents.some(function (agent) { return agent.id === requested; })) {
      agentFilter.value = requested;
      agentSelect.value = requested;
    }
  }

  function stateButton(action, strategy, title, className) {
    var button = element('button', className || 'mg-btn mg-btn-soft', title);
    button.type = 'button';
    button.dataset.strategyAction = action;
    button.dataset.strategyId = strategy.id;
    button.dataset.strategyVersion = String(strategy.version);
    return button;
  }

  function renderStrategy(strategy) {
    var card = element('article', 'mg-strategy-card is-' + strategy.status);
    card.dataset.strategyId = strategy.id;

    var head = element('header');
    var identity = element('div');
    var eyebrow = element('span', 'mg-strategy-card-eyebrow', strategy.agent.name + ' · v' + strategy.version);
    var title = element('h3', '', strategy.name);
    identity.append(eyebrow, title);
    head.append(identity, element('span', 'mg-strategy-state', label(strategy.status)));
    card.appendChild(head);

    card.appendChild(element('p', 'mg-strategy-objective', strategy.objective));

    var facts = element('dl', 'mg-strategy-facts');
    [
      ['Trigger', label(strategy.trigger.type)],
      ['Actions', strategy.action_catalog.length],
      ['Run limit', strategy.max_actions_per_run],
      ['Approval', strategy.requires_approval ? 'Always required' : 'Risk based'],
      ['Updated', formatDate(strategy.updated_at)]
    ].forEach(function (fact) {
      var item = element('div');
      item.append(element('dt', '', fact[0]), element('dd', '', fact[1]));
      facts.appendChild(item);
    });
    card.appendChild(facts);

    var actions = element('div', 'mg-strategy-card-actions');
    if (strategy.permissions.can_edit) actions.appendChild(stateButton('edit', strategy, 'Edit'));
    if (strategy.permissions.can_activate) actions.appendChild(stateButton('activate', strategy, strategy.status === 'paused' ? 'Resume strategy' : 'Activate', 'mg-btn mg-btn-primary'));
    if (strategy.permissions.can_pause) actions.appendChild(stateButton('pause', strategy, 'Pause', 'mg-btn mg-btn-soft'));
    if (strategy.permissions.can_retire) actions.appendChild(stateButton('retire', strategy, 'Retire', 'mg-btn mg-btn-danger'));
    card.appendChild(actions);
    return card;
  }

  function render(append) {
    if (!append) list.replaceChildren();
    strategies.forEach(function (strategy) {
      if (!append || !list.querySelector('[data-strategy-id="' + CSS.escape(strategy.id) + '"]')) list.appendChild(renderStrategy(strategy));
    });
    hide(list, strategies.length === 0);
    hide(empty, strategies.length !== 0);
    hide(pagination, !nextCursor);
  }

  async function loadStrategies(append) {
    if (loadingStrategies) return;
    loadingStrategies = true;
    hide(errorState, true);
    hide(empty, true);
    if (!append) { hide(list, true); hide(loading, false); }
    text(statusText, 'Loading agent strategies…');
    var query = new URLSearchParams({ status: statusFilter.value, limit: '24' });
    if (agentFilter.value) query.set('agent_id', agentFilter.value);
    if (append && nextCursor) query.set('cursor', nextCursor);
    try {
      var response = await MG.get('/api/agents/strategies.php?' + query.toString());
      var payload = data(response).strategies;
      if (append) strategies = strategies.concat(payload.items || []);
      else strategies = payload.items || [];
      nextCursor = payload.next_cursor || null;
      render(append);
      text(statusText, strategies.length ? strategies.length + ' strategies loaded.' : 'No strategies in this view.');
    } catch (error) {
      hide(errorState, false);
      text(errorMessage, error.message || 'Unable to load strategies.');
      text(statusText, '');
    } finally {
      loadingStrategies = false;
      hide(loading, true);
      if (moreButton) moreButton.disabled = false;
    }
  }

  function parseObject(value, fieldName) {
    var trimmed = String(value || '').trim();
    if (!trimmed) return {};
    var parsed;
    try { parsed = JSON.parse(trimmed); }
    catch (error) { throw new Error(fieldName + ' must contain valid JSON.'); }
    if (!parsed || Array.isArray(parsed) || typeof parsed !== 'object') throw new Error(fieldName + ' must be a JSON object.');
    return parsed;
  }

  function selectedActions() {
    return Array.prototype.slice.call(form.querySelectorAll('[name="action_catalog"]:checked')).map(function (input) { return input.value; });
  }

  function resetForm() {
    form.reset();
    form.elements.strategy_id.value = '';
    form.elements.version.value = '';
    form.elements.max_actions_per_run.value = '10';
    form.elements.requires_approval.checked = true;
    form.elements.trigger_config.value = '{}';
    form.elements.policy.value = '{}';
    form.querySelector('[value="create_operational_alert"]').checked = true;
    form.querySelector('[value="acknowledge_demand_signal"]').checked = true;
    text(formStatus, '');
  }

  function openEditor(strategy) {
    resetForm();
    if (strategy) {
      text(editorEyebrow, label(strategy.status) + ' strategy · version ' + strategy.version);
      text(editorTitle, 'Edit strategy');
      text(saveButton, 'Save changes');
      form.elements.strategy_id.value = strategy.id;
      form.elements.version.value = String(strategy.version);
      form.elements.agent_id.value = strategy.agent.id;
      form.elements.name.value = strategy.name;
      form.elements.objective.value = strategy.objective;
      form.elements.trigger_type.value = strategy.trigger.type;
      form.elements.max_actions_per_run.value = String(strategy.max_actions_per_run);
      form.elements.requires_approval.checked = Boolean(strategy.requires_approval);
      form.elements.trigger_config.value = JSON.stringify(strategy.trigger.config || {}, null, 2);
      form.elements.policy.value = JSON.stringify(strategy.policy || {}, null, 2);
      Array.prototype.slice.call(form.querySelectorAll('[name="action_catalog"]')).forEach(function (input) { input.checked = strategy.action_catalog.includes(input.value); });
    } else {
      text(editorEyebrow, 'Draft strategy');
      text(editorTitle, 'Create strategy');
      text(saveButton, 'Save draft');
      var requested = agentFilter.value || new URLSearchParams(window.location.search).get('agent');
      if (requested) form.elements.agent_id.value = requested;
    }
    editor.hidden = false;
    editor.scrollIntoView({ behavior: 'smooth', block: 'start' });
    form.elements.name.focus();
  }

  function closeEditor() {
    editor.hidden = true;
    resetForm();
  }

  async function submitMutation(body, button) {
    if (button) MG.setBusy ? MG.setBusy(button, true, 'Saving…') : button.disabled = true;
    try {
      var response = await MG.post('/api/agents/strategies.php', body);
      var strategy = data(response).strategy;
      var index = strategies.findIndex(function (item) { return item.id === strategy.id; });
      if (index >= 0) strategies[index] = strategy;
      else strategies.unshift(strategy);
      render(false);
      text(statusText, response.message || 'Strategy updated.');
      return strategy;
    } finally {
      if (button) MG.setBusy ? MG.setBusy(button, false) : button.disabled = false;
    }
  }

  form.addEventListener('submit', async function (event) {
    event.preventDefault();
    text(formStatus, '');
    var actions = selectedActions();
    if (!actions.length) { text(formStatus, 'Choose at least one allowed action.'); return; }
    var body;
    try {
      body = {
        action: form.elements.strategy_id.value ? 'update' : 'create',
        strategy_id: form.elements.strategy_id.value || undefined,
        version: form.elements.version.value ? Number(form.elements.version.value) : undefined,
        agent_id: form.elements.agent_id.value,
        name: form.elements.name.value.trim(),
        objective: form.elements.objective.value.trim(),
        trigger_type: form.elements.trigger_type.value,
        trigger_config: parseObject(form.elements.trigger_config.value, 'Trigger configuration'),
        policy: parseObject(form.elements.policy.value, 'Policy'),
        action_catalog: actions,
        max_actions_per_run: Number(form.elements.max_actions_per_run.value),
        requires_approval: form.elements.requires_approval.checked
      };
      var strategy = await submitMutation(body, saveButton);
      closeEditor();
      text(statusText, strategy.name + ' saved as ' + label(strategy.status) + '.');
    } catch (error) {
      text(formStatus, error.message || 'Unable to save strategy.');
    }
  });

  list.addEventListener('click', async function (event) {
    var button = event.target.closest('[data-strategy-action]');
    if (!button) return;
    var strategy = strategies.find(function (item) { return item.id === button.dataset.strategyId; });
    if (!strategy) return;
    var action = button.dataset.strategyAction;
    if (action === 'edit') { openEditor(strategy); return; }
    if (action === 'retire' && !window.confirm('Retire this strategy? Retired strategies cannot be reactivated.')) return;
    try {
      var updated = await submitMutation({ action: action, strategy_id: strategy.id, version: strategy.version }, button);
      text(statusText, updated.name + ' is now ' + label(updated.status) + '.');
    } catch (error) {
      text(statusText, error.message || 'Unable to update strategy.');
    }
  });

  strategyPanel.querySelector('[data-strategy-create]').addEventListener('click', function () { openEditor(null); });
  strategyPanel.querySelector('[data-strategy-close]').addEventListener('click', closeEditor);
  strategyPanel.querySelector('[data-strategy-cancel]').addEventListener('click', closeEditor);
  strategyPanel.querySelector('[data-strategy-refresh]').addEventListener('click', function () { nextCursor = null; loadStrategies(false); });
  strategyPanel.querySelector('[data-strategy-retry]').addEventListener('click', function () { loadStrategies(false); });
  moreButton.addEventListener('click', function () { moreButton.disabled = true; loadStrategies(true); });
  statusFilter.addEventListener('change', function () { nextCursor = null; loadStrategies(false); });
  agentFilter.addEventListener('change', function () { nextCursor = null; loadStrategies(false); });

  setView(new URLSearchParams(window.location.search).get('view'), false);
});
