(function (window, document) {
  'use strict';

  var STATUS_API_URL = '/api/homeserver/devices.php';
  var RELEASE_API_URL = '/api/homeserver/latest-release.php';
  var ONLINE_WINDOW_MS = 10 * 60 * 1000;
  var REFRESH_INTERVAL_MS = 60 * 1000;
  var devices = [];
  var entitlement = {
    state: 'loading',
    included: false,
    can_download: false,
    can_manage: false,
    package_name: 'Checking access',
    subscription_status: 'loading',
    active_device_count: 0,
    device_limit: 0,
    remaining_device_slots: 0,
    upgrade_url: '/account-subscriptions.php?homeserver=provider-access',
    software_authority: 'vp3',
    vp3_account_url: 'https://vp3.me'
  };
  var latestRelease = null;
  var releaseSchemaReady = true;
  var canManageReleases = false;
  var releaseAdminUrl = null;
  var lastLoadFailed = false;
  var releaseLoadFailed = false;
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

  function formatBytes(value) {
    var bytes = Number(value || 0);
    if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
    if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return bytes + ' B';
  }

  function compactId(value) {
    var text = String(value || '');
    if (text.length <= 20) return text || 'Not assigned';
    return text.slice(0, 8) + '…' + text.slice(-8);
  }

  function numericVersion(value) {
    var raw = String(value || '').trim().replace(/^v/i, '').split(/[+-]/)[0];
    if (!/^\d+(?:\.\d+){1,3}$/.test(raw)) return null;
    return raw.split('.').map(function (part) { return Number(part); });
  }

  function compareVersions(left, right) {
    var a = numericVersion(left);
    var b = numericVersion(right);
    if (!a || !b) return String(left || '').localeCompare(String(right || ''), undefined, { numeric: true, sensitivity: 'base' });
    var length = Math.max(a.length, b.length);
    for (var index = 0; index < length; index += 1) {
      var aPart = Number(a[index] || 0);
      var bPart = Number(b[index] || 0);
      if (aPart > bPart) return 1;
      if (aPart < bPart) return -1;
    }
    return 0;
  }

  function isOnline(device) {
    if (!device || String(device.status || '').toLowerCase() !== 'active') return false;
    var lastSeen = parseUtc(device.last_seen_at);
    return Boolean(lastSeen && Date.now() - lastSeen.getTime() <= ONLINE_WINDOW_MS);
  }

  function activeDevices() {
    return devices.filter(function (device) {
      return String(device.status || '').toLowerCase() === 'active';
    });
  }

  function onlineDevices() {
    return activeDevices().filter(isOnline);
  }

  function revokedDevices() {
    return devices.filter(function (device) {
      return String(device.status || '').toLowerCase() === 'revoked';
    });
  }

  function hasUpdateAvailable() {
    return false;
  }

  function statusSummary() {
    var active = activeDevices();
    var online = onlineDevices();
    var revoked = revokedDevices();
    var state = String(entitlement.state || 'not_included').toLowerCase();
    var subscriptionStatus = String(entitlement.subscription_status || '').toLowerCase();

    if (lastLoadFailed) {
      return { tone: 'muted', online: false, label: 'Status unavailable', detail: 'Microgifter could not load the HomeServer connection record.' };
    }
    if (state === 'owner_required') {
      return { tone: 'warning', online: false, label: 'Account owner required', detail: entitlement.message || 'HomeServer is managed by the merchant workspace owner.' };
    }
    if (!entitlement.included) {
      if (state === 'suspended' || active.length > 0 || revoked.length > 0) {
        return { tone: 'blocked', online: false, label: 'Subscription attention', detail: entitlement.message || 'HomeServer cloud access is not active for this account.' };
      }
      return { tone: 'muted', online: false, label: 'Microgifter not connected', detail: entitlement.message || 'Enable Microgifter provider access after licensing HomeServer through VP3.' };
    }
    if (online.length > 0) {
      if (hasUpdateAvailable()) {
        return { tone: 'warning', online: true, label: 'Connected · update available', detail: 'HomeServer is connected and a newer signed installer is available.' };
      }
      if (subscriptionStatus === 'past_due' || subscriptionStatus === 'cancel_pending') {
        return { tone: 'warning', online: true, label: 'Connected · account attention', detail: 'HomeServer remains connected while the subscription needs attention.' };
      }
      return { tone: 'online', online: true, label: 'Connected', detail: online.length === 1 ? '1 HomeServer checked in recently.' : online.length + ' HomeServers checked in recently.' };
    }
    if (active.length > 0) {
      return { tone: 'warning', online: false, label: 'Offline or degraded', detail: 'A paired HomeServer has not checked in during the last 10 minutes.' };
    }
    if (revoked.length > 0) {
      return { tone: 'blocked', online: false, label: 'Revoked', detail: 'The saved HomeServer connection has been revoked.' };
    }
    return { tone: 'ready', online: false, label: 'Ready to connect', detail: 'License and install HomeServer through VP3, then connect Microgifter with a one-time Sync Code.' };
  }

  function releaseSummary() {
    return { label: 'Managed by VP3', className: ' is-current' };
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
      button.classList.remove('is-loading', 'is-online', 'is-ready', 'is-warning', 'is-blocked', 'is-muted');
      button.classList.add('is-' + summary.tone);
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

  function releaseMarkup() {
    return [
      '<div class="mg-homeserver-release-card">',
      '  <div class="mg-homeserver-release-info">',
      '    <div class="mg-homeserver-release-title"><strong>VP3 HomeServer</strong><span class="mg-homeserver-release-state is-current">Software authority</span></div>',
      '    <p>VP3 manages the HomeServer license, registered device, Windows installer, signed release channels, and software updates.</p>',
      '  </div>',
      '  <a class="mg-homeserver-release-download" href="https://vp3.me" rel="noopener">Open VP3</a>',
      '</div>'
    ].join('');
  }

  function allowanceLabel() {
    var active = Number(entitlement.active_device_count || activeDevices().length || 0);
    if (entitlement.device_limit === null) return active + ' active · unlimited';
    return active + ' of ' + Number(entitlement.device_limit || 0) + ' active';
  }

  function renderModal() {
    var root = createModal().querySelector('[data-homeserver-status-body]');
    var summary = statusSummary();
    var releaseState = releaseSummary();
    var sorted = devices.slice().sort(function (a, b) {
      var aDate = parseUtc(a.last_seen_at);
      var bDate = parseUtc(b.last_seen_at);
      return (bDate ? bDate.getTime() : 0) - (aDate ? aDate.getTime() : 0);
    });
    var activeCount = activeDevices().length;
    var statusClass = summary.tone === 'online' ? ' is-online' : ' is-' + summary.tone;

    root.innerHTML = [
      '<div class="mg-homeserver-overview">',
      '  <article class="mg-homeserver-status-card">',
      '    <span>Microgifter connection</span>',
      '    <strong><i class="mg-homeserver-state-light' + statusClass + '" aria-hidden="true"></i>' + escapeHtml(summary.label) + '</strong>',
      '    <p>' + escapeHtml(summary.detail) + '</p>',
      '  </article>',
      '  <article class="mg-homeserver-status-card">',
      '    <span>Microgifter access</span>',
      '    <strong>' + escapeHtml(entitlement.package_name || 'Free Wallet') + '</strong>',
      '    <p>' + escapeHtml(allowanceLabel()) + ' · ' + escapeHtml(String(entitlement.subscription_status || 'unknown').replace(/_/g, ' ')) + '</p>',
      '  </article>',
      '</div>',
      '<section class="mg-homeserver-section mg-homeserver-release-section">',
      '  <div class="mg-homeserver-section-head">',
      '    <div><p>Software authority</p><h3>VP3 HomeServer</h3></div>',
      '    <span class="mg-homeserver-chip' + releaseState.className + '">' + escapeHtml(releaseState.label) + '</span>',
      '  </div>',
      releaseMarkup(),
      '</section>',
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
      (canManageReleases && releaseAdminUrl ? '  <a class="is-secondary" href="' + escapeHtml(releaseAdminUrl) + '">Release admin</a>' : ''),
      '  <a class="is-secondary" href="https://vp3.me" rel="noopener">Open VP3</a>',
      (entitlement.can_manage ? '  <a href="/account-homeserver.php">Manage Microgifter Connection</a>' : '  <a href="' + escapeHtml(entitlement.upgrade_url || '/account-subscriptions.php?homeserver=provider-access') + '">Enable Microgifter Access</a>'),
      '</div>'
    ].join('');

    var refresh = root.querySelector('[data-homeserver-status-refresh]');
    if (refresh) refresh.addEventListener('click', function () { loadAll(true); });
  }

  function openModal() {
    var element = createModal();
    renderModal();
    element.hidden = false;
    element.setAttribute('aria-hidden', 'false');
    document.body.classList.add('mg-homeserver-status-open');
    var close = element.querySelector('.mg-homeserver-status-close');
    if (close) close.focus();
    loadAll(true);
  }

  function closeModal() {
    if (!modal) return;
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('mg-homeserver-status-open');
  }

  async function loadStatus() {
    try {
      var response = await window.fetch(STATUS_API_URL, {
        method: 'GET',
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
        cache: 'no-store'
      });
      var payload = await response.json().catch(function () { return {}; });
      if (!response.ok || payload.ok === false) throw new Error(payload.message || 'Unable to load HomeServer status.');
      var data = payload.data && typeof payload.data === 'object' ? payload.data : payload;
      devices = Array.isArray(data.devices) ? data.devices : [];
      entitlement = data.entitlement && typeof data.entitlement === 'object' ? data.entitlement : entitlement;
      lastLoadFailed = false;
    } catch (error) {
      lastLoadFailed = true;
    }
  }

  async function loadRelease() {
    latestRelease = null;
    releaseSchemaReady = false;
    canManageReleases = false;
    releaseAdminUrl = null;
    releaseLoadFailed = false;
  }

  async function loadAll(forceRender) {
    await loadStatus();
    await loadRelease();
    updateTriggers();
    if (forceRender && modal && !modal.hidden) renderModal();
  }

  function install() {
    mountTriggers();
    createModal();
    loadAll(false);
    refreshTimer = window.setInterval(function () { loadAll(false); }, REFRESH_INTERVAL_MS);

    var observer = new MutationObserver(function () { mountTriggers(); });
    observer.observe(document.body, { childList: true, subtree: true });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && modal && !modal.hidden) closeModal();
    });
    document.addEventListener('visibilitychange', function () {
      if (!document.hidden) loadAll(Boolean(modal && !modal.hidden));
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
