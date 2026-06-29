window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = document.querySelector('[data-world-canvas]');
  if (!root || !MG) return;

  function qs(selector, scope) { return (scope || document).querySelector(selector); }
  function payload(response) { return response && response.data ? response.data : response; }
  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
    });
  }
  function apiGet(path) {
    if (MG.get) return MG.get(path);
    return fetch(path, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); });
  }

  function card(insight) {
    var action = insight.action_label && insight.action_href ? '<a href="' + escapeHtml(insight.action_href) + '">' + escapeHtml(insight.action_label) + '</a>' : (insight.action_label ? '<button type="button" data-world-insight-action="' + escapeHtml(insight.type || '') + '">' + escapeHtml(insight.action_label) + '</button>' : '');
    return '<article class="mg-world-insight-card is-' + escapeHtml(insight.severity || 'info') + '"><header><span>' + escapeHtml(insight.type || 'insight') + '</span><strong>' + escapeHtml(insight.score || 0) + '</strong></header><h3>' + escapeHtml(insight.title || 'World insight') + '</h3><p>' + escapeHtml(insight.summary || '') + '</p>' + (insight.recommendation ? '<em>' + escapeHtml(insight.recommendation) + '</em>' : '') + action + '</article>';
  }

  function render(target, data, compact) {
    var insights = Array.isArray(data.insights) ? data.insights : [];
    if (!target) return;
    if (!insights.length) {
      target.innerHTML = '<p class="mg-world-insight-empty">No insights yet. World Canvas will generate recommendations as activity forms.</p>';
      return;
    }
    target.innerHTML = '<div class="mg-world-insight-engine ' + (compact ? 'is-compact' : '') + '">' + insights.map(card).join('') + '</div>';
  }

  async function loadGlobalInsights() {
    var panel = qs('[data-world-insights]', root);
    if (!panel) return;
    panel.innerHTML = '<p class="mg-world-insight-empty">Generating World Canvas insights...</p>';
    try {
      var data = payload(await apiGet('/api/world-canvas/insights.php'));
      render(panel, data, false);
    } catch (error) {
      panel.innerHTML = '<p class="mg-world-insight-empty">Insights are not available yet.</p>';
    }
  }

  function ensureDrawerSlot() {
    var body = qs('[data-world-drawer-body]');
    if (!body) return null;
    var existing = qs('[data-world-drawer-insights]', body);
    if (existing) return existing;
    var slot = document.createElement('section');
    slot.className = 'mg-world-drawer-insights';
    slot.dataset.worldDrawerInsights = '1';
    slot.innerHTML = '<p class="mg-world-insight-empty">Generating cluster insights...</p>';
    var rewardSlot = qs('[data-world-reward-drop-slot]', body);
    if (rewardSlot && rewardSlot.parentNode) rewardSlot.parentNode.insertBefore(slot, rewardSlot);
    else body.insertBefore(slot, body.firstChild);
    return slot;
  }

  async function loadClusterInsights(context) {
    if (!context) return;
    var slot = ensureDrawerSlot();
    if (!slot) return;
    slot.innerHTML = '<p class="mg-world-insight-empty">Generating cluster insights...</p>';
    var conversation = context.conversation || {};
    var query = [];
    if (conversation.id) query.push('conversation_id=' + encodeURIComponent(conversation.id));
    if (context.cluster_key) query.push('cluster_key=' + encodeURIComponent(context.cluster_key));
    if (context.location_key) query.push('location_key=' + encodeURIComponent(context.location_key));
    try {
      var data = payload(await apiGet('/api/world-canvas/insights.php?' + query.join('&')));
      render(slot, data, true);
    } catch (error) {
      slot.innerHTML = '<p class="mg-world-insight-empty">Cluster insights are not available yet.</p>';
    }
  }

  document.addEventListener('mg:world-conversation-opened', function (event) {
    loadClusterInsights(event.detail || null);
  });

  document.addEventListener('click', function (event) {
    var action = event.target.closest('[data-world-insight-action]');
    if (!action) return;
    if (action.dataset.worldInsightAction === 'drop_recommendation' || action.textContent.toLowerCase().indexOf('reward drop') !== -1) {
      var details = qs('.mg-world-reward-drop-create');
      if (details) details.open = true;
    }
  });

  loadGlobalInsights();
  window.setInterval(loadGlobalInsights, 20000);
})(window, document);
