document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var page = document.querySelector('[data-archived-agents-page]');
  if (!page) return;

  var list = page.querySelector('[data-archived-agent-list]');
  var empty = page.querySelector('[data-archived-agent-empty]');
  var count = page.querySelector('[data-archived-agent-count]');
  var agents = [];

  function formatDate(value) {
    var date = value ? new Date(value) : null;
    if (!date || Number.isNaN(date.getTime())) return 'Archived date unavailable';
    return 'Archived ' + date.toLocaleString([], {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: 'numeric',
      minute: '2-digit'
    });
  }

  function render() {
    list.innerHTML = '';
    count.textContent = agents.length + (agents.length === 1 ? ' agent' : ' agents');
    empty.hidden = agents.length > 0;

    agents.forEach(function (agent) {
      var card = document.createElement('article');
      card.className = 'mg-archived-agent-card';
      card.dataset.archivedAgent = agent.id;
      var categoryLabel = agent.category ? String(agent.category).replace(/[-_]/g, ' ') : 'Saved gifting workspace';

      card.innerHTML = '<div class="mg-archived-agent-main"><span class="mg-archived-agent-icon">A</span><div><strong></strong><p></p><div class="mg-archived-agent-meta"><span></span><span></span></div></div></div><div class="mg-archived-agent-actions"><button type="button" data-restore-archived-agent>Restore agent</button><button type="button" class="is-danger" data-delete-archived-agent>Delete permanently</button></div>';
      card.querySelector('strong').textContent = agent.name || 'Archived agent';
      card.querySelector('p').textContent = 'This agent is archived and no longer appears in the active sidebar.';
      var meta = card.querySelectorAll('.mg-archived-agent-meta span');
      meta[0].textContent = categoryLabel;
      meta[1].textContent = formatDate(agent.archived_at);
      list.appendChild(card);
    });
  }

  async function load() {
    try {
      var response = await Microgifter.get('/api/agents/index.php?lifecycle=archived');
      agents = response.data && Array.isArray(response.data.agents) ? response.data.agents : [];
      render();
    } catch (error) {
      agents = [];
      render();
      empty.hidden = false;
      var message = empty.querySelector('p');
      if (message) message.textContent = error.message || 'Unable to load archived agents.';
    }
  }

  list.addEventListener('click', async function (event) {
    var card = event.target.closest('[data-archived-agent]');
    if (!card) return;
    var id = card.dataset.archivedAgent;
    var agent = agents.find(function (item) { return item.id === id; });

    if (event.target.closest('[data-restore-archived-agent]')) {
      event.preventDefault();
      await Microgifter.post('/api/agents/restore.php', { id: id });
      agents = agents.filter(function (item) { return item.id !== id; });
      render();
      return;
    }

    if (event.target.closest('[data-delete-archived-agent]')) {
      event.preventDefault();
      if (!agent || !window.confirm('Delete "' + agent.name + '" permanently? Purchase, claim, and invoice history will remain preserved.')) return;
      await Microgifter.delete('/api/agents/item.php', { id: id });
      agents = agents.filter(function (item) { return item.id !== id; });
      render();
    }
  });

  load();
});