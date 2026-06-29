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

  function card(item) {
    var href = item.action_href || '';
    var button = href ? '<a href="' + escapeHtml(href) + '">' + escapeHtml(item.action_label || 'Open') + '</a>' : '<button type="button" data-world-opportunity-button="' + escapeHtml(item.action_type || '') + '">' + escapeHtml(item.action_label || 'Take action') + '</button>';
    return '<article class="mg-world-opportunity-card is-' + escapeHtml(item.priority || 'medium') + '"><header><span>' + escapeHtml(item.priority || 'medium') + '</span><strong>' + escapeHtml(item.score || 0) + '</strong></header><h3>' + escapeHtml(item.title || 'Merchant opportunity') + '</h3><p>' + escapeHtml(item.summary || '') + '</p>' + (item.recommended_action ? '<em>' + escapeHtml(item.recommended_action) + '</em>' : '') + button + '</article>';
  }

  function render(target, data, compact) {
    if (!target) return;
    if (!data || data.merchant_enabled === false) {
      target.innerHTML = '<p class="mg-world-opportunity-empty">Merchant opportunities appear for merchant accounts.</p>';
      return;
    }
    var list = Array.isArray(data.opportunities) ? data.opportunities : [];
    if (!list.length) {
      target.innerHTML = '<p class="mg-world-opportunity-empty">No merchant opportunities yet.</p>';
      return;
    }
    target.innerHTML = '<div class="mg-world-opportunity-grid ' + (compact ? 'is-compact' : '') + '">' + list.map(card).join('') + '</div>';
  }

  function query(context) {
    var q = [];
    context = context || {};
    var conversation = context.conversation || {};
    if (conversation.id) q.push('conversation_id=' + encodeURIComponent(conversation.id));
    if (context.cluster_key) q.push('cluster_key=' + encodeURIComponent(context.cluster_key));
    if (context.location_key) q.push('location_key=' + encodeURIComponent(context.location_key));
    return q.length ? '?' + q.join('&') : '';
  }

  async function load(target, context, compact) {
    if (!target) return;
    target.innerHTML = '<p class="mg-world-opportunity-empty">Generating merchant opportunities...</p>';
    try {
      var data = payload(await apiGet('/api/world-canvas/opportunities.php' + query(context)));
      render(target, data, compact);
    } catch (error) {
      target.innerHTML = '<p class="mg-world-opportunity-empty">Merchant opportunities are not available yet.</p>';
    }
  }

  function drawerSlot() {
    var body = qs('[data-world-drawer-body]');
    if (!body) return null;
    var slot = qs('[data-world-drawer-opportunities]', body);
    if (slot) return slot;
    slot = document.createElement('section');
    slot.className = 'mg-world-drawer-opportunities';
    slot.dataset.worldDrawerOpportunities = '1';
    var rewardSlot = qs('[data-world-reward-drop-slot]', body);
    if (rewardSlot && rewardSlot.parentNode) rewardSlot.parentNode.insertBefore(slot, rewardSlot);
    else body.insertBefore(slot, body.firstChild);
    return slot;
  }

  document.addEventListener('mg:world-conversation-opened', function (event) {
    load(drawerSlot(), event.detail || null, true);
  });

  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-world-opportunity-button]');
    if (!button) return;
    var action = button.dataset.worldOpportunityButton || '';
    if (action === 'create_reward_drop' || action === 'duplicate_drop') {
      var details = qs('.mg-world-reward-drop-create');
      if (details) details.open = true;
      return;
    }
    if (action === 'message_cluster') {
      var textarea = qs('[data-world-conversation-input]');
      if (textarea) textarea.focus();
    }
  });

  load(qs('[data-world-opportunities]', root), null, false);
  window.setInterval(function () { load(qs('[data-world-opportunities]', root), null, false); }, 30000);
})(window, document);
