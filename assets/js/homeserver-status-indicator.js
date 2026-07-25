(function (window, document) {
  'use strict';

  var API_URL = '/api/homeserver/devices.php';
  var ONLINE_WINDOW_MS = 10 * 60 * 1000;
  var REFRESH_INTERVAL_MS = 60 * 1000;
  var devices = [];
  var lastLoadFailed = false;
  var modal = null;
  var refreshTimer = null;

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function parseUtc(value) {
    var raw = String(value || '').trim();
    if (!raw) return null;
    if (!/[zZ]|[+-]\d\d:\d\d$/.test(raw)) raw = raw.replace(' ', 'T') + 'Z';
    var date = new Date(raw);
    return Number.isNaN(date.getTime()) ? null : date;
  }

  function formatDate(value) {
    var date = parseUtc(value);
    return date ? date.toLocaleString() : 'Not yet';
  }

  function compactId(value) {
    var text = String(value || '');
    if (text.length <= 20) return text || 'Not assigned';
    return text.slice(0, 8) + '…' + text.slice(-8);
  }

  function isOnline(device) {
    if (!device || String(device.status || '').toLowerCase() !== 'active') return false;
    var lastSeen = parseUtc(device.last_seen_at);
    return Boolean(lastSeen && Date.now() - lastSeen.getTime() <= ONLINE_WINDOW_MS);
  }

  function statusSummary() {
    var active = devices.filter(function (device) {
      return String(device.status || '').toLowerCase() === 'active';
    });
    var online = active.filter(isOnline);
    var revoked = devices.filter(function (device) {
      return String(device.status || '').toLowerCase() === 'revoked';
    });

    if (lastLoadFailed) {
      return { online: false, label: 'Status unavailable', detail: 'Microgifter could not load the HomeServer connection record.' };
    }
    if (online.length > 0) {
      return { online: true, label: 'Connected', detail: online.length === 1 ? '1 HomeServer checked in recently.' : online.length + ' HomeServers checked in recently.' };
    }
    if (active.length > 0) {
      return { online: false, label: 'Offline or degraded', detail: 'A paired HomeServer has not checked in during the last 10 minutes.' };
    }
    if (revoked.length > 0) {
      return { online: false, label: 'Revoked', detail: 'The saved HomeServer connection has been revoked.' };
    }
    return { online: false, label: 'Not paired', detail: 'No active HomeServer is connected to this account.' };
  }

  function createTrigger() {
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'mg-homeserver-status-trigger is-loading';
    button.setAttribute('data-homeserver-status-trigger', '');
    button.setAttribute('aria-label', 'HomeServer status loading');
    button.setAttribute('title', 'HomeServer status loading');
    button.innerHTML = '<span class="mg-homeserver-status-dot" aria-hidden="true"></span>';
    button.addEventListener('click', openModal);
    return button;
  }

  function mountTriggers() {
    var locations = [
      { logo: '.mg-app-sidebar-brand .mg-sidebar-logo', parent: '.mg-app-sidebar-brand' },
      { logo: '.mg-header-left .mg-header-mobile-brand', parent: '.mg-header-left' }
    ];

    locations.forEach(function (location) {
      var logo = document.querySelector(location.logo);
      var parent = document.querySelector(location.parent);
      if (!logo || !parent || parent.querySelector(':scope > [data-homeserver-status-trigger]')) return;
      logo.insertAdjacentElement('afterend', createTrigger());
    });
    updateTriggers();
  }

  function updateTriggers() {
    var summary = statusSummary();
    document.querySelectorAll('[data-homeserver-status-trigger]').forEach(function (button) {
      button.classList.remove('is-loading', 'is-online');
      if (summary.online) button.classList.add('is-online');
      button.setAttribute('aria-label', 'HomeServer: ' + summary.label);
      button.setAttribute('title', 'HomeServer: ' + summary.label);
    });
  }

  function createModal() {
    if (modal) return modal;
    modal = document.createElement('div');
    modal.className = 'mg-homeserver-status-modal';
    modal.setAttribute('data-homeserver-status-modal', '');
    modal.setAttribute('aria-hidden', 'true');
    modal.hidden = true;
    modal.innerHTML = [
      '<button class="mg-homeserver-status-backdrop" type="button" data-homeserver-status-close aria-label="Close HomeServer status"></button>',
      '<section class="mg-homeserver-status-dialog" role="dialog" aria-modal="true" aria-labelledby="mg-homeserver-status-title">',
      '  <header class="mg-homeserver-status-head">',
      '    <div><p>Private local infrastructure</p><h2 id="mg-homeserver-status-title">HomeServer Status</h2></div>',
      '    <button class="mg-homeserver-status-close" type="button" data-homeserver-status-close aria-label="Close">×</button>',
      '  </header>',
      '  <div class="mg-homeserver-status-body" data-homeserver-status-body></div>',
      '</section>'
    ].join('');
    document.body.appendChild(modal);
    modal.querySelectorAll('[data-homeserver-status-close]').forEach(function (button) {
      button.addEventListener('click', closeModal);
    });
    return modal;
  }

  function deviceMarkup(device) {
    var online = isOnline(device);
    var status = String(device.status || '').toLowerCase();
    var stateLabel = status === 'revoked' ? 'Revoked' : (online ? 'Online' : 'Offline');
    return [
      '<article class="mg-homeserver-device">',
      '  <div>',
      '    <h4>' + escapeHtml(device.server_name || 'Microgifter HomeServer') + '</h4>',
      '    <p>Version ' + escapeHtml(device.version || 'Unknown') + ' · ' + escapeHtml(compactId(device.device_id)) + '</p>',
      '    <p>Last seen: ' + escapeHtml(formatDate(device.last_seen_at)) + '</p>',
      '  </div>',
      '  <span class="mg-homeserver-device-state' + (online ? ' is-online' : '') + '">' + escapeHtml(stateLabel) + '</span>',
      '</article>'
    ].join('');
  }

  function renderModal() {
    var root = createModal().querySelector('[data-homeserver-status-body]');
    var summary = statusSummary();
    var sorted = devices.slice().sort(function (a, b) {
      var aDate = parseUtc(a.last_seen_at);
      var bDate = parseUtc(b.last_seen_at);
      return (bDate ? bDate.getTime() : 0) - (aDate ? aDate.getTime() : 0);
    });
    var activeCount = devices.filter(function (device) {
      return String(device.status || '').toLowerCase() === 'active';
    }).length;

    root.innerHTML = [
      '<div class="mg-homeserver-overview">',
      '  <article class="mg-homeserver-status-card">',
      '    <span>Cloud connection</span>',
      '    <strong><i class="mg-homeserver-state-light' + (summary.online ? ' is-online' : '') + '" aria-hidden="true"></i>' + escapeHtml(summary.label) + '</strong>',
      '    <p>' + escapeHtml(summary.detail) + '</p>',
      '  </article>',
      '  <article class="mg-homeserver-status-card">',
      '    <span>Local service</span>',
      '    <strong>Managed on Windows</strong>',
      '    <p>Local API and database health are reported inside the HomeServer Control Center.</p>',
      '  </article>',
      '</div>',
      '<section class="mg-homeserver-section">',
      '  <div class="mg-homeserver-section-head">',
      '    <div><p>Connection records</p><h3>Paired HomeServers</h3></div>',
      '    <span class="mg-homeserver-chip">' + activeCount + ' active</span>',
      '  </div>',
      '  <div class="mg-homeserver-device-list">',
      sorted.length ? sorted.slice(0, 6).map(deviceMarkup).join('') : '<div class="mg-homeserver-empty">No HomeServer has been paired with this account yet.</div>',
      '  </div>',
      '</section>',
      '<section class="mg-homeserver-section">',
      '  <div class="mg-homeserver-section-head">',
      '    <div><p>Local model manager</p><h3>Ollama Models</h3></div>',
      '    <span class="mg-homeserver-chip">Planned</span>',
      '  </div>',
      '  <div class="mg-homeserver-ollama-stub">',
      '    <div class="mg-homeserver-ollama-row"><strong>Ollama provider</strong><span>Not synchronized yet</span></div>',
      '    <div class="mg-homeserver-empty">Available local LLM models will appear here after the HomeServer Model Center and Ollama inventory bridge are enabled in a later phase.</div>',
      '  </div>',
      '</section>',
      '<div class="mg-homeserver-status-actions">',
      '  <button type="button" data-homeserver-status-refresh>Refresh status</button>',
      '  <a href="/account-homeserver.php">Manage HomeServer</a>',
      '</div>'
    ].join('');

    var refresh = root.querySelector('[data-homeserver-status-refresh]');
    if (refresh) refresh.addEventListener('click', function () { loadStatus(true); });
  }

  function openModal() {
    var element = createModal();
    renderModal();
    element.hidden = false;
    element.setAttribute('aria-hidden', 'false');
    document.body.classList.add('mg-homeserver-status-open');
    var close = element.querySelector('.mg-homeserver-status-close');
    if (close) close.focus();
    loadStatus(true);
  }

  function closeModal() {
    if (!modal) return;
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('mg-homeserver-status-open');
  }

  async function loadStatus(forceRender) {
    try {
      var response = await window.fetch(API_URL, {
        method: 'GET',
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
        cache: 'no-store'
      });
      var payload = await response.json().catch(function () { return {}; });
      if (!response.ok || payload.ok === false) throw new Error(payload.message || 'Unable to load HomeServer status.');
      var data = payload.data && typeof payload.data === 'object' ? payload.data : payload;
      devices = Array.isArray(data.devices) ? data.devices : [];
      lastLoadFailed = false;
    } catch (error) {
      lastLoadFailed = true;
    }
    updateTriggers();
    if (forceRender && modal && !modal.hidden) renderModal();
  }

  function install() {
    mountTriggers();
    createModal();
    loadStatus(false);
    refreshTimer = window.setInterval(function () { loadStatus(false); }, REFRESH_INTERVAL_MS);

    var observer = new MutationObserver(function () { mountTriggers(); });
    observer.observe(document.body, { childList: true, subtree: true });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && modal && !modal.hidden) closeModal();
    });
    document.addEventListener('visibilitychange', function () {
      if (!document.hidden) loadStatus(Boolean(modal && !modal.hidden));
    });
    window.addEventListener('beforeunload', function () {
      if (refreshTimer) window.clearInterval(refreshTimer);
      observer.disconnect();
    }, { once: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', install, { once: true });
  } else {
    install();
  }
})(window, document);
