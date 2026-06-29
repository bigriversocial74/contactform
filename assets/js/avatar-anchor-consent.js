window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  if (!MG) return;

  var STORAGE_KEY = 'mg_avatar_anchor_consent_v1';
  var activeSession = null;
  var modal = null;
  var pending = false;

  function payload(response) { return response && response.data ? response.data : response; }
  function toast(message, type) { if (MG.toast) MG.toast(message, type || 'info'); }
  function apiGet(path) { return MG.get ? MG.get(path) : fetch(path, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }); }
  function apiPost(path, body) { return MG.post ? MG.post(path, body || {}) : fetch(path, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': MG.getCsrfToken ? MG.getCsrfToken() : '' }, body: JSON.stringify(body || {}) }).then(function (r) { return r.json(); }); }
  function hasStoredConsent() { return window.localStorage && window.localStorage.getItem(STORAGE_KEY) === 'yes'; }
  function saveStoredConsent() { if (window.localStorage) window.localStorage.setItem(STORAGE_KEY, 'yes'); }
  function revokeStoredConsent() { if (window.localStorage) window.localStorage.removeItem(STORAGE_KEY); }

  function ensureModal() {
    if (modal) return modal;
    modal = document.createElement('section');
    modal.className = 'mg-avatar-anchor-modal';
    modal.hidden = true;
    modal.innerHTML = '<article class="mg-avatar-anchor-card"><span>World Canvas</span><h2>Place your avatar on the world map?</h2><p>Microgifter can save your avatar coordinate for this active store session so World Canvas can anchor your avatar and connect it to nearby conversations. This is optional.</p><div class="mg-avatar-anchor-actions"><button type="button" data-avatar-anchor-skip>Not now</button><button type="button" data-avatar-anchor-allow>Allow for this avatar</button></div></article>';
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

  function getPosition() {
    return new Promise(function (resolve, reject) {
      if (!navigator.geolocation) {
        reject(new Error('Browser position is not available.'));
        return;
      }
      navigator.geolocation.getCurrentPosition(resolve, reject, { enableHighAccuracy: false, timeout: 9000, maximumAge: 300000 });
    });
  }

  async function saveAnchor() {
    if (pending) return;
    pending = true;
    try {
      var session = activeSession || await loadActiveSession();
      if (!session) throw new Error('Enter a merchant store before placing your avatar.');
      var position = await getPosition();
      var coords = position.coords || {};
      await apiPost('/api/store/avatar-anchor.php', {
        consent: 'yes',
        avatar_latitude: coords.latitude,
        avatar_longitude: coords.longitude,
        avatar_accuracy: Math.round(coords.accuracy || 0)
      });
      saveStoredConsent();
      closeModal();
      toast('Your avatar is now anchored for World Canvas.', 'success');
    } catch (error) {
      toast(error.message || 'Unable to place your avatar.', 'error');
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
      toast('Avatar map anchoring preference cleared.', 'info');
    }
  });

  document.addEventListener('mg:store-entered', maybePrompt);
  window.setTimeout(maybePrompt, 1600);
})(window, document);
