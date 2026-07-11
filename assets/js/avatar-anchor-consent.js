window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  if (!MG) return;

  var STORAGE_KEY = 'mg_avatar_anchor_consent_v2';
  var activeSession = null;
  var modal = null;
  var pending = false;

  function payload(response) { return response && response.data ? response.data : response; }
  function toast(message, type) { if (MG.toast) MG.toast(message, type || 'info'); }
  function apiGet(path) {
    return MG.get ? MG.get(path) : fetch(path, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (response) { return response.json(); });
  }
  function apiPost(path, body) {
    return MG.post ? MG.post(path, body || {}) : fetch(path, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': MG.getCsrfToken ? MG.getCsrfToken() : '' },
      body: JSON.stringify(body || {})
    }).then(function (response) { return response.json(); });
  }
  function hasStoredConsent() { return window.localStorage && window.localStorage.getItem(STORAGE_KEY) === 'yes'; }
  function saveStoredConsent() { if (window.localStorage) window.localStorage.setItem(STORAGE_KEY, 'yes'); }
  function revokeStoredConsent() { if (window.localStorage) window.localStorage.removeItem(STORAGE_KEY); }

  function ensureModal() {
    if (modal) return modal;
    modal = document.createElement('section');
    modal.className = 'mg-avatar-anchor-modal';
    modal.hidden = true;
    modal.innerHTML =
      '<article class="mg-avatar-anchor-card">' +
        '<span>Store Canvas → World Canvas</span>' +
        '<h2>Place your avatar at this merchant location?</h2>' +
        '<p>With your permission, Microgifter will anchor your avatar to the merchant’s saved latitude and longitude while you are inside the Store Canvas. When you exit, your avatar will enter World Canvas from that same location.</p>' +
        '<div class="mg-avatar-anchor-actions"><button type="button" data-avatar-anchor-skip>Not now</button><button type="button" data-avatar-anchor-allow>Place my avatar here</button></div>' +
      '</article>';
    document.body.appendChild(modal);
    return modal;
  }

  function openModal() { ensureModal().hidden = false; }
  function closeModal() { ensureModal().hidden = true; }

  async function loadActiveSession() {
    try {
      var data = payload(await apiGet('/api/store/session-status.php'));
      activeSession = data && data.active_session ? data.active_session : null;
      return activeSession;
    } catch (error) {
      return null;
    }
  }

  async function saveAnchor() {
    if (pending) return;
    pending = true;
    try {
      var session = activeSession || await loadActiveSession();
      if (!session) throw new Error('Enter a merchant store before placing your avatar.');
      await apiPost('/api/store/avatar-anchor.php', { consent: 'yes' });
      saveStoredConsent();
      closeModal();
      toast('Your avatar is anchored to this merchant location.', 'success');
    } catch (error) {
      closeModal();
      toast(error.message || 'Unable to place your avatar at this merchant location.', 'error');
    } finally {
      pending = false;
    }
  }

  async function maybePrompt() {
    var session = await loadActiveSession();
    if (!session) return;
    if (hasStoredConsent()) {
      saveAnchor();
      return;
    }
    openModal();
  }

  document.addEventListener('click', function (event) {
    if (event.target.closest('[data-avatar-anchor-allow]')) {
      saveAnchor();
      return;
    }
    if (event.target.closest('[data-avatar-anchor-skip]')) {
      closeModal();
      return;
    }
    if (event.target.closest('[data-avatar-anchor-revoke]')) {
      revokeStoredConsent();
      toast('Avatar location preference cleared.', 'info');
    }
  });

  document.addEventListener('mg:store-entered', maybePrompt);
  window.setTimeout(maybePrompt, 1600);
})(window, document);
