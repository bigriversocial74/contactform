document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  if (!window.Microgifter) return;
  var root = document.querySelector('[data-personal-gifting-agent]');
  if (!root) return;
  var templatesNode = document.getElementById('mg-agent-template-data');
  var selectedNode = document.getElementById('mg-selected-agent-id');
  var templates = templatesNode ? JSON.parse(templatesNode.textContent || '{}') : {};
  var selectedAgentId = selectedNode ? JSON.parse(selectedNode.textContent || '""') : '';
  var layer = document.querySelector('[data-multi-agent-layer]');
  var instanceCanvas = document.querySelector('[data-agent-instance-canvas]');
  var defaultDashboard = root.querySelector('[data-personal-agent-dashboard], .mg-personal-agent-dashboard, .mg-personal-agent-chat-canvas');
  var composer = root.querySelector('[data-personal-agent-composer]');
  var manageModal = document.querySelector('[data-agent-manage-modal]');
  var agents = [];
  var selectedAgent = null;

  function dataOf(response) { return response && response.data ? response.data : (response || {}); }
  function csrf() { var node = document.querySelector('meta[name="csrf-token"]'); return node ? node.content : ''; }
  async function post(url, payload, method) {
    var response = await fetch(url, { method: method || 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf() }, body: JSON.stringify(payload || {}) });
    var json = await response.json();
    if (!response.ok || !json.ok) throw new Error(json.message || 'Unable to complete this agent action.');
    return dataOf(json);
  }
  async function loadAgents() {
    var response = await Microgifter.get('/api/agents/index.php?lifecycle=active');
    agents = dataOf(response).agents || [];
    selectedAgent = agents.find(function (agent) { return agent.id === selectedAgentId; }) || null;
    renderSelected();
  }
  function setDefaultVisible(visible) {
    if (defaultDashboard) defaultDashboard.hidden = !visible;
    if (composer) composer.hidden = !visible;
    if (instanceCanvas) instanceCanvas.hidden = visible;
  }
  function renderSelected() {
    if (!selectedAgent) { setDefaultVisible(true); return; }
    var config = selectedAgent.config || {};
    var template = templates[config.template_key] || {};
    setDefaultVisible(false);
    instanceCanvas.querySelector('[data-agent-instance-name]').textContent = selectedAgent.name;
    instanceCanvas.querySelector('[data-agent-instance-description]').textContent = template.description || 'A focused Microgifter agent workspace.';
    instanceCanvas.querySelector('[data-agent-instance-icon]').textContent = template.icon || '✦';
    instanceCanvas.querySelector('[data-agent-instance-welcome]').textContent = template.welcome || 'How can I help with this workspace?';
    instanceCanvas.querySelector('[data-agent-instance-prompts]').innerHTML = (template.prompts || []).map(function (prompt) { return '<button type="button" data-agent-seed-prompt="' + prompt.replace(/"/g, '&quot;') + '">' + prompt + '</button>'; }).join('');
    instanceCanvas.classList.toggle('is-paused', selectedAgent.runtime_status === 'paused');
  }
  function openSelector() { if (layer) { layer.hidden = false; document.body.classList.add('mg-agent-selector-open'); } }
  function closeSelector() { if (layer) { layer.hidden = true; document.body.classList.remove('mg-agent-selector-open'); } }
  function openManage(agent) {
    selectedAgent = agent || selectedAgent;
    if (!selectedAgent || !manageModal) return;
    manageModal.querySelector('[data-agent-manage-name]').textContent = selectedAgent.name;
    manageModal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('mg-agent-manage-open');
  }
  function closeManage() {
    if (!manageModal) return;
    manageModal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('mg-agent-manage-open');
    var confirm = manageModal.querySelector('[data-agent-delete-confirm]');
    if (confirm) confirm.hidden = true;
  }
  async function createAgent(templateKey) {
    var template = templates[templateKey];
    if (!template || template.status !== 'active') return;
    var data = await post('/api/agents/index.php', { name: template.name, category: template.category, config: { template_key: templateKey, workspace_tab_open: true, workspace_type: template.merchant_required ? 'merchant' : 'personal', objective: template.description } });
    var agent = data.agent;
    await post('/api/agents/status.php', { id: agent.id, status: 'running' });
    window.location.href = '/agent.php?agent_id=' + encodeURIComponent(agent.id);
  }
  async function updateAgentConfig(agent, patch) {
    var config = Object.assign({}, agent.config || {}, patch || {});
    return (await post('/api/agents/item.php?id=' + encodeURIComponent(agent.id), { id: agent.id, config: config }, 'POST')).agent;
  }
  async function performAction(action) {
    if (!selectedAgent) return;
    var status = manageModal.querySelector('[data-agent-manage-status]');
    status.textContent = 'Working…';
    try {
      if (action === 'close') {
        await updateAgentConfig(selectedAgent, { workspace_tab_open: false });
        window.location.href = '/agent.php';
        return;
      }
      if (action === 'pause') {
        await post('/api/agents/status.php', { id: selectedAgent.id, status: 'paused' });
        status.textContent = 'Agent paused.';
        window.setTimeout(function () { window.location.reload(); }, 350);
        return;
      }
      if (action === 'archive') {
        await post('/api/agents/archive.php', { id: selectedAgent.id });
        window.location.href = '/agent.php';
        return;
      }
      if (action === 'delete') {
        manageModal.querySelector('[data-agent-delete-confirm]').hidden = false;
        status.textContent = '';
      }
    } catch (error) { status.textContent = error.message || 'Unable to update this agent.'; }
  }

  document.addEventListener('click', function (event) {
    var add = event.target.closest('[data-agent-add-tab], [data-open-agent-selector]');
    if (add) { event.preventDefault(); openSelector(); return; }
    if (event.target.closest('[data-multi-agent-selector-close]')) { closeSelector(); return; }
    var create = event.target.closest('[data-create-agent-template]');
    if (create) { create.disabled = true; createAgent(create.getAttribute('data-create-agent-template')).catch(function (error) { create.disabled = false; window.alert(error.message); }); return; }
    var sidebarAgent = event.target.closest('[data-sidebar-agent-id]');
    if (sidebarAgent) {
      var id = sidebarAgent.getAttribute('data-sidebar-agent-id');
      var agent = agents.find(function (item) { return item.id === id; });
      if (agent && agent.config && agent.config.workspace_tab_open === false) {
        event.preventDefault();
        updateAgentConfig(agent, { workspace_tab_open: true }).then(function () { window.location.href = '/agent.php?agent_id=' + encodeURIComponent(id); }).catch(function (error) { window.alert(error.message); });
      }
      return;
    }
    var manage = event.target.closest('[data-agent-manage-open], [data-sidebar-agent-manage]');
    if (manage) { event.preventDefault(); var id2 = manage.getAttribute('data-sidebar-agent-manage'); openManage(agents.find(function (item) { return item.id === id2; }) || selectedAgent); return; }
    if (event.target.closest('[data-agent-manage-close]')) { closeManage(); return; }
    var action = event.target.closest('[data-agent-action]');
    if (action) { performAction(action.getAttribute('data-agent-action')); return; }
    var prompt = event.target.closest('[data-agent-seed-prompt]');
    if (prompt) window.alert('This specialized agent workspace is ready for its dedicated conversation runtime: ' + prompt.getAttribute('data-agent-seed-prompt'));
  });

  var search = document.querySelector('[data-multi-agent-search]');
  if (search) search.addEventListener('input', function () {
    var term = search.value.trim().toLowerCase();
    document.querySelectorAll('[data-agent-template-card]').forEach(function (card) { card.hidden = term !== '' && (card.getAttribute('data-agent-search-text') || '').indexOf(term) === -1; });
  });
  document.querySelectorAll('[data-agent-filter]').forEach(function (button) { button.addEventListener('click', function () { document.querySelectorAll('[data-agent-filter]').forEach(function (item) { item.classList.toggle('is-active', item === button); }); var filter = button.getAttribute('data-agent-filter'); document.querySelectorAll('[data-agent-template-card]').forEach(function (card) { card.hidden = filter !== 'all' && card.getAttribute('data-agent-filter-group') !== filter; }); }); });
  var confirmInput = document.querySelector('[data-agent-delete-confirm-input]');
  var finalDelete = document.querySelector('[data-agent-delete-final]');
  if (confirmInput && finalDelete) {
    confirmInput.addEventListener('input', function () { finalDelete.disabled = confirmInput.value.trim() !== 'DELETE'; });
    finalDelete.addEventListener('click', async function () { if (!selectedAgent || finalDelete.disabled) return; finalDelete.disabled = true; try { await post('/api/agents/item.php?id=' + encodeURIComponent(selectedAgent.id), { id: selectedAgent.id }, 'DELETE'); window.location.href = '/agent.php'; } catch (error) { finalDelete.disabled = false; manageModal.querySelector('[data-agent-manage-status]').textContent = error.message; } });
  }
  loadAgents().catch(function () { renderSelected(); });
});
