window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var roots = Array.from(document.querySelectorAll('[data-social-feed], [data-newsfeed]'));
  if (!roots.length) return;

  var postStates = new Map();
  var loadingPosts = new Set();
  var activeSession = null;
  var modalPostId = '';
  var heartbeatTimer = null;

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
    if (MG.post) return MG.post(path, body);
    return fetch(path, {
      method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': MG.getCsrfToken ? MG.getCsrfToken() : '' }, body: JSON.stringify(body || {})
    }).then(function (r) { return r.json(); });
  }
  function toast(message, type) {
    if (MG.toast) MG.toast(message, type || 'info');
  }
  function signIn() {
    window.location.href = '/signin.php?return=' + encodeURIComponent(window.location.pathname + window.location.search);
  }
  function notifyStoreEntered() {
    try { document.dispatchEvent(new CustomEvent('mg:store-entered', { detail: { session: activeSession } })); } catch (error) {}
  }
  function buttonClass(state) {
    if (state === 'inside_this') return 'mg-store-enter-btn is-active';
    if (state === 'inside_other') return 'mg-store-enter-btn is-switch';
    return 'mg-store-enter-btn';
  }
  function rowClass(state) {
    if (state === 'inside_this') return 'mg-store-presence-row is-inside';
    if (state === 'inside_other') return 'mg-store-presence-row is-other';
    return 'mg-store-presence-row';
  }
  function noticeText(state) {
    if (!state) return 'Enter this merchant store with your customer avatar.';
    return state.notice || 'Enter this merchant store with your customer avatar.';
  }

  function ensurePill() {
    var pill = document.querySelector('[data-store-active-pill]');
    if (pill) return pill;
    pill = document.createElement('div');
    pill.className = 'mg-store-active-pill';
    pill.dataset.storeActivePill = '1';
    pill.hidden = true;
    document.body.appendChild(pill);
    return pill;
  }
  function renderPill() {
    var pill = ensurePill();
    if (!activeSession || !activeSession.merchant) {
      pill.hidden = true;
      pill.innerHTML = '';
      return;
    }
    var name = activeSession.merchant.name || 'Merchant Store';
    pill.hidden = false;
    pill.innerHTML = '<span>Shopping in</span><strong>' + escapeHtml(name) + '</strong><button type="button" data-avatar-anchor-prompt>Map Avatar</button><button type="button" data-store-global-exit>Exit</button>';
  }

  function ensureModal() {
    var modal = document.querySelector('[data-store-switch-modal]');
    if (modal) return modal;
    modal = document.createElement('section');
    modal.className = 'mg-store-switch-modal';
    modal.dataset.storeSwitchModal = '1';
    modal.hidden = true;
    modal.innerHTML = '<article class="mg-store-switch-card"><span>Switch store</span><h2>Move your avatar?</h2><p data-store-switch-copy>You are currently inside another merchant store.</p><div class="mg-store-switch-actions"><button class="mg-store-switch-cancel" type="button" data-store-switch-cancel>Stay in Current Store</button><button class="mg-store-switch-confirm" type="button" data-store-switch-confirm>Exit and Enter Store</button></div></article>';
    document.body.appendChild(modal);
    return modal;
  }
  function openSwitchModal(postId, result) {
    modalPostId = String(postId || '');
    var modal = ensureModal();
    var currentName = result && result.current && result.current.merchant ? result.current.merchant.name : 'your current store';
    var targetName = result && result.target_merchant ? result.target_merchant.name : 'this merchant store';
    modal.querySelector('[data-store-switch-copy]').textContent = 'You are currently shopping inside ' + currentName + '. Entering ' + targetName + ' will exit ' + currentName + ' and move your avatar into the new merchant location.';
    modal.hidden = false;
  }
  function closeSwitchModal() {
    modalPostId = '';
    var modal = ensureModal();
    modal.hidden = true;
  }

  function renderCard(card, state) {
    if (!card || !state || !state.entry_enabled) return;
    var row = card.querySelector('[data-store-presence-row]');
    if (!row) {
      row = document.createElement('div');
      row.dataset.storePresenceRow = '1';
      var stats = card.querySelector('.mg-feed-stats');
      if (stats && stats.parentNode) stats.parentNode.insertBefore(row, stats.nextSibling);
      else card.appendChild(row);
    }
    row.className = rowClass(state.state);
    var primaryLabel = state.label || 'Enter Store';
    var secondary = '';
    if (state.state === 'inside_this') {
      secondary = '<button class="mg-store-exit-btn" type="button" data-store-exit>Exit Store</button><button class="mg-store-exit-btn" type="button" data-avatar-anchor-prompt>Map Avatar</button>';
    }
    row.innerHTML = '<div class="mg-store-presence-copy"><span class="mg-store-presence-dot" aria-hidden="true"></span><span>' + escapeHtml(noticeText(state)) + '</span></div><div class="mg-store-presence-actions"><button class="' + buttonClass(state.state) + '" type="button" data-store-enter>' + escapeHtml(primaryLabel) + '</button>' + secondary + '</div>';
  }

  async function loadPostState(card) {
    var postId = card && card.dataset ? card.dataset.postId : '';
    if (!postId || loadingPosts.has(postId)) return;
    loadingPosts.add(postId);
    try {
      var data = payload(await apiGet('/api/store/session-status.php?post_id=' + encodeURIComponent(postId)));
      if (data && data.active_session !== undefined) activeSession = data.active_session || null;
      if (data && data.post_state) {
        postStates.set(postId, data.post_state);
        renderCard(card, data.post_state);
      }
      renderPill();
    } catch (error) {
      // Non-merchant posts or unavailable store canvas states do not need to block the feed.
    } finally {
      loadingPosts.delete(postId);
    }
  }

  function scanCards(root) {
    Array.from(root.querySelectorAll('[data-post-id]')).forEach(function (card) {
      loadPostState(card);
    });
  }
  function refreshAllCards() {
    postStates = new Map();
    roots.forEach(scanCards);
    loadGlobalStatus();
  }

  async function loadGlobalStatus() {
    try {
      var data = payload(await apiGet('/api/store/session-status.php'));
      activeSession = data && data.active_session ? data.active_session : null;
      renderPill();
    } catch (error) {}
  }

  async function heartbeat() {
    if (!activeSession) return;
    try {
      var data = payload(await apiPost('/api/store/heartbeat.php', {}));
      activeSession = data && data.active_session ? data.active_session : null;
      renderPill();
    } catch (error) {
      activeSession = null;
      renderPill();
    }
  }

  async function enterStore(card, forceSwitch) {
    var postId = card && card.dataset ? card.dataset.postId : '';
    if (!postId) return;
    var state = postStates.get(postId);
    if (state && state.state === 'login_required') return signIn();
    if (state && state.state === 'owner') {
      window.location.href = '/merchant-canvas.php';
      return;
    }
    var button = card.querySelector('[data-store-enter]');
    if (button) {
      button.disabled = true;
      button.dataset.originalLabel = button.textContent;
      button.textContent = forceSwitch ? 'Switching…' : 'Entering…';
    }
    try {
      var data = payload(await apiPost('/api/store/enter.php', { post_id: postId, switch_store: Boolean(forceSwitch) }));
      if (data && data.requires_confirmation) {
        openSwitchModal(postId, data);
        return;
      }
      activeSession = data && data.session ? data.session : activeSession;
      toast('Your avatar entered the merchant store.', 'success');
      notifyStoreEntered();
      refreshAllCards();
    } catch (error) {
      toast(error.message || 'Unable to enter store.', 'error');
    } finally {
      if (button) {
        button.disabled = false;
        button.textContent = button.dataset.originalLabel || 'Enter Store';
      }
    }
  }

  async function exitStore() {
    try {
      await apiPost('/api/store/exit.php', {});
      activeSession = null;
      toast('Exited merchant store.', 'success');
      refreshAllCards();
    } catch (error) {
      toast(error.message || 'Unable to exit store.', 'error');
    }
  }

  document.addEventListener('click', function (event) {
    var anchorPrompt = event.target.closest('[data-avatar-anchor-prompt]');
    if (anchorPrompt) {
      notifyStoreEntered();
      return;
    }
    var enter = event.target.closest('[data-store-enter]');
    if (enter) {
      var card = enter.closest('[data-post-id]');
      if (card) enterStore(card, false);
      return;
    }
    if (event.target.closest('[data-store-exit]') || event.target.closest('[data-store-global-exit]')) {
      exitStore();
      return;
    }
    if (event.target.closest('[data-store-switch-cancel]')) {
      closeSwitchModal();
      return;
    }
    if (event.target.closest('[data-store-switch-confirm]')) {
      var postId = modalPostId;
      closeSwitchModal();
      var card = document.querySelector('[data-post-id="' + CSS.escape(postId) + '"]');
      if (card) enterStore(card, true);
    }
  });

  roots.forEach(function (root) {
    scanCards(root);
    var observer = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        Array.from(mutation.addedNodes || []).forEach(function (node) {
          if (!node || node.nodeType !== 1) return;
          if (node.matches && node.matches('[data-post-id]')) loadPostState(node);
          if (node.querySelectorAll) Array.from(node.querySelectorAll('[data-post-id]')).forEach(loadPostState);
        });
      });
    });
    observer.observe(root, { childList: true, subtree: true });
  });

  loadGlobalStatus();
  heartbeatTimer = window.setInterval(heartbeat, 30000);
  window.addEventListener('beforeunload', function () { if (heartbeatTimer) window.clearInterval(heartbeatTimer); });
})(window, document);