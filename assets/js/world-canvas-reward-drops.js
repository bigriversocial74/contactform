window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = document.querySelector('[data-world-canvas]');
  if (!root || !MG) return;

  var activeContext = null;

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
  function apiPost(path, body) {
    if (MG.post) return MG.post(path, body || {});
    return fetch(path, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': MG.getCsrfToken ? MG.getCsrfToken() : '' }, body: JSON.stringify(body || {}) }).then(function (r) { return r.json(); });
  }
  function toast(message, type) { if (MG.toast) MG.toast(message, type || 'info'); }

  function slot() { return qs('[data-world-reward-drop-slot]'); }

  function renderDrops(drops) {
    drops = Array.isArray(drops) ? drops : [];
    var list = drops.length ? drops.map(function (drop) {
      var status = drop.claimed_by_viewer ? 'Claimed' : (drop.quantity_remaining <= 0 ? 'Exhausted' : 'Claim');
      var disabled = drop.claimed_by_viewer || drop.quantity_remaining <= 0 || drop.status !== 'active';
      return '<article class="mg-world-reward-drop-card"><div><strong>' + escapeHtml(drop.reward_label || drop.title || 'Reward drop') + '</strong><p>' + escapeHtml(drop.reward_description || drop.title || 'World Canvas reward') + '</p><span>' + escapeHtml(drop.quantity_remaining) + ' of ' + escapeHtml(drop.quantity_total) + ' left</span></div><button type="button" data-world-reward-claim="' + escapeHtml(drop.id) + '"' + (disabled ? ' disabled' : '') + '>' + escapeHtml(status) + '</button></article>';
    }).join('') : '<p class="mg-world-reward-drop-empty">No reward drops are attached to this conversation yet.</p>';

    return '<section class="mg-world-reward-drops"><header><div><span>Reward Drops</span><strong>Drop rewards into this cluster</strong></div></header>' + list + renderCreateForm() + '</section>';
  }

  function renderCreateForm() {
    return '<details class="mg-world-reward-drop-create"><summary>Create merchant reward drop</summary><form data-world-reward-drop-form><label>Reward title<input type="text" name="reward_label" maxlength="180" placeholder="Example: Free coffee reward" required></label><label>Description<textarea name="reward_description" maxlength="1200" placeholder="Short terms or redemption details"></textarea></label><div class="mg-world-reward-drop-row"><label>Quantity<input type="number" name="quantity_total" min="1" max="500" value="10"></label><label>Hours live<input type="number" name="expires_hours" min="1" max="168" value="48"></label></div><button type="submit">Create Reward Drop</button></form></details>';
  }

  async function loadDrops() {
    if (!activeContext) return;
    var target = slot();
    if (!target) return;
    target.innerHTML = '<section class="mg-world-reward-drops"><p class="mg-world-reward-drop-empty">Loading reward drops...</p></section>';
    try {
      var query = activeContext.conversation && activeContext.conversation.id ? 'conversation_id=' + encodeURIComponent(activeContext.conversation.id) : 'cluster_key=' + encodeURIComponent(activeContext.cluster_key || '');
      var data = payload(await apiGet('/api/world-canvas/reward-drops.php?' + query));
      target.innerHTML = renderDrops(data.drops || []);
    } catch (error) {
      target.innerHTML = '<section class="mg-world-reward-drops"><p class="mg-world-reward-drop-empty">' + escapeHtml(error.message || 'Reward drops are not available yet.') + '</p>' + renderCreateForm() + '</section>';
    }
  }

  async function createDrop(form) {
    if (!activeContext) return;
    var fd = new FormData(form);
    var rewardLabel = String(fd.get('reward_label') || '').trim();
    if (!rewardLabel) return;
    var button = form.querySelector('button[type="submit"]');
    if (button) button.disabled = true;
    try {
      await apiPost('/api/world-canvas/reward-drops.php', {
        conversation_id: activeContext.conversation ? activeContext.conversation.id : '',
        cluster_key: activeContext.cluster_key || '',
        location_key: activeContext.location_key || '',
        title: rewardLabel,
        reward_label: rewardLabel,
        reward_description: String(fd.get('reward_description') || '').trim(),
        quantity_total: Number(fd.get('quantity_total') || 10),
        expires_hours: Number(fd.get('expires_hours') || 48)
      });
      toast('Reward drop created for this World Canvas cluster.', 'success');
      loadDrops();
    } catch (error) {
      toast(error.message || 'Unable to create reward drop.', 'error');
    } finally {
      if (button) button.disabled = false;
    }
  }

  async function claimDrop(dropId) {
    if (!dropId) return;
    try {
      var data = payload(await apiPost('/api/world-canvas/reward-drop-claim.php', { drop_id: dropId }));
      var claim = data.claim || {};
      toast(claim.claim_code ? 'Reward claimed: ' + claim.claim_code : 'Reward claimed.', 'success');
      loadDrops();
    } catch (error) {
      toast(error.message || 'Unable to claim reward drop.', 'error');
    }
  }

  document.addEventListener('mg:world-conversation-opened', function (event) {
    activeContext = event.detail || null;
    loadDrops();
  });

  document.addEventListener('submit', function (event) {
    var form = event.target.closest('[data-world-reward-drop-form]');
    if (!form) return;
    event.preventDefault();
    createDrop(form);
  });

  document.addEventListener('click', function (event) {
    var claim = event.target.closest('[data-world-reward-claim]');
    if (!claim) return;
    claimDrop(claim.dataset.worldRewardClaim || '');
  });
})(window, document);
