window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';

  var root = document.querySelector('[data-world-canvas]');
  var MG = window.Microgifter;
  var map = root && root.querySelector('[data-world-map]');
  if (!root || !MG || !map) return;

  var state = { tab: 'overview', settings: null, drops: [], tools: [], catalog: [], loading: false };
  var tabs = [['overview','Overview'],['active','Active'],['drafts','Drafts'],['tools','Tools'],['settings','Locations']];

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[character];
    });
  }
  function token() {
    var meta = document.querySelector('meta[name="csrf-token"],meta[name="mg-csrf-token"]');
    return meta ? meta.getAttribute('content') || '' : (window.MG_CSRF_TOKEN || '');
  }
  function addressText(location) {
    return [location.address_line1, location.city, location.region, location.postal_code, location.country_code].filter(Boolean).join(', ');
  }
  function activeDrops() { return state.drops.filter(function (drop) { return ['launching','active','scheduled'].indexOf(drop.status) > -1; }); }
  function draftDrops() { return state.drops.filter(function (drop) { return drop.owned && drop.status === 'draft'; }); }
  function listText(items) { return (items || []).map(function (item) { return '<code>' + esc(item) + '</code>'; }).join(', '); }

  function ensureButton() {
    var button = map.querySelector('[data-world-merchant-settings-open]');
    if (button) return button;
    button = document.createElement('button');
    button.type = 'button';
    button.className = 'mg-world-merchant-settings-btn';
    button.dataset.worldMerchantSettingsOpen = '1';
    button.innerHTML = '<span>World Dashboard</span>';
    map.appendChild(button);
    return button;
  }

  function ensurePanel() {
    var panel = document.querySelector('[data-world-merchant-settings-panel]');
    if (panel) return panel;
    panel = document.createElement('aside');
    panel.className = 'mg-world-merchant-settings-panel mg-world-dashboard-panel';
    panel.dataset.worldMerchantSettingsPanel = '1';
    panel.setAttribute('aria-hidden', 'true');
    panel.innerHTML =
      '<div class="mg-world-merchant-settings-head"><div><span>World Dashboard</span><strong>My World</strong><small>Campaign Drop Zones, tools, and merchant location anchors.</small></div><button type="button" data-world-merchant-settings-close aria-label="Close">×</button></div>' +
      '<div class="mg-world-dashboard-tabs" data-world-dashboard-tabs></div>' +
      '<div class="mg-world-merchant-settings-body" data-world-merchant-settings-body><p>Loading World Dashboard…</p></div>';
    document.body.appendChild(panel);
    return panel;
  }

  function tabNav() {
    return tabs.map(function (tab) {
      return '<button type="button" data-world-dashboard-tab="' + tab[0] + '" class="' + (state.tab === tab[0] ? 'is-active' : '') + '">' + tab[1] + '</button>';
    }).join('');
  }
  function metric(label, value) { return '<div class="mg-world-dashboard-metric"><b>' + esc(value) + '</b><span>' + esc(label) + '</span></div>'; }
  function dropCard(drop) {
    return '<article class="mg-world-dashboard-card"><div><strong>' + esc(drop.campaign_title || drop.drop_name || 'Campaign Drop Zone') + '</strong><span>' + esc((drop.status || 'draft').toUpperCase()) + '</span></div><p>' + esc((drop.interest_count || 0) + ' interested · ' + (drop.radius_meters || 0) + 'm radius') + '</p><button type="button" data-dashboard-open-drop="' + esc(drop.id) + '">Open Drop Zone</button></article>';
  }
  function toolCard(tool, locked) {
    return '<article class="mg-world-tool-card ' + (locked ? 'is-locked' : '') + '"><div><strong>' + esc(tool.name) + '</strong><span>' + esc((tool.category || 'tool') + ' · ' + (tool.rarity || 'common')) + '</span></div><p>' + esc(tool.description || 'World Canvas tool') + '</p><em>' + (locked ? 'Locked / coming soon' : esc((tool.status || 'owned').toUpperCase())) + '</em></article>';
  }

  function overviewHtml() {
    var active = activeDrops();
    var drafts = draftDrops();
    return '<section class="mg-world-dashboard-section"><div class="mg-world-dashboard-metrics">' +
      metric('Active / Scheduled', active.length) + metric('Saved Drafts', drafts.length) + metric('Owned Tools', state.tools.length) + metric('Catalog Items', state.catalog.length) +
      '</div><div class="mg-world-dashboard-split"><div><h3>Active Campaign Drop Zones</h3>' +
      (active.length ? active.slice(0, 4).map(dropCard).join('') : '<div class="mg-world-dashboard-empty">No active Campaign Drop Zones.</div>') +
      '</div><div><h3>Tool Status</h3>' +
      (state.tools.length ? state.tools.slice(0, 3).map(function (tool) { return toolCard(tool, false); }).join('') : '<div class="mg-world-dashboard-empty">No tools loaded.</div>') +
      '</div></div></section>';
  }
  function activeHtml() {
    var active = activeDrops();
    return '<section class="mg-world-dashboard-section"><h3>Active Campaign Drop Zones</h3>' + (active.length ? active.map(dropCard).join('') : '<div class="mg-world-dashboard-empty">No active or scheduled Drop Zones.</div>') + '</section>';
  }
  function draftsHtml() {
    var drafts = draftDrops();
    return '<section class="mg-world-dashboard-section"><h3>Saved Drafts</h3>' + (drafts.length ? drafts.map(dropCard).join('') : '<div class="mg-world-dashboard-empty">No saved Drop Zone drafts.</div>') + '</section>';
  }
  function toolsHtml() {
    var ownedKeys = {};
    state.tools.forEach(function (tool) { ownedKeys[tool.tool_id || tool.id] = true; });
    var locked = state.catalog.filter(function (tool) { return !ownedKeys[tool.id]; });
    return '<section class="mg-world-dashboard-section"><h3>Tools / Equipment</h3><p class="mg-world-dashboard-note">World Canvas tools do not issue rewards or execute campaigns.</p><h4>Owned</h4>' +
      (state.tools.length ? state.tools.map(function (tool) { return toolCard(tool, false); }).join('') : '<div class="mg-world-dashboard-empty">No owned tools loaded.</div>') +
      '<h4>Catalog</h4>' + (locked.length ? locked.map(function (tool) { return toolCard(tool, true); }).join('') : '<div class="mg-world-dashboard-empty">No additional catalog tools.</div>') + '</section>';
  }

  function locationForm(location) {
    var address = addressText(location);
    var mapped = location.latitude != null && location.longitude != null;
    var mapsUrl = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(address || location.name || '');
    return '<form class="mg-world-merchant-location-form ' + (mapped ? 'is-mapped' : 'is-missing') + '" data-world-merchant-location-form data-location-id="' + esc(location.public_id) + '">' +
      '<div><strong>' + esc(location.name || 'Merchant location') + '</strong><span>' + esc(address || location.location_code || 'No address saved') + '</span>' + (Number(location.is_primary) ? '<em>Primary</em>' : '') + '<mark>' + (mapped ? 'Mapped' : 'Missing coordinates') + '</mark></div>' +
      '<label>Latitude<input name="latitude" inputmode="decimal" value="' + esc(location.latitude == null ? '' : location.latitude) + '" placeholder="33.4484"></label>' +
      '<label>Longitude<input name="longitude" inputmode="decimal" value="' + esc(location.longitude == null ? '' : location.longitude) + '" placeholder="-112.0740"></label>' +
      '<label>Arrival radius meters<input name="world_zone_radius_meters" inputmode="numeric" value="' + esc(location.world_zone_radius_meters || 250) + '"></label>' +
      '<input type="hidden" name="location_id" value="' + esc(location.public_id) + '"><input type="hidden" name="geo_accuracy_meters" value="' + esc(location.geo_accuracy_meters || 0) + '">' +
      '<div class="mg-world-merchant-location-actions"><button type="submit">Save Location</button><button type="button" data-world-use-current-location>Use current location</button><button type="button" data-world-find-location>Find on World Canvas</button><a href="' + esc(mapsUrl) + '" target="_blank" rel="noopener">Search address</a><a href="/merchant-locations.php">Edit location</a></div><p data-world-location-status></p></form>';
  }
  function settingsHtml() {
    var payload = state.settings || {};
    var optional = payload.optional_missing_columns || [];
    if (payload.error) return '<section class="mg-world-dashboard-section"><h3>Locations</h3><div class="mg-world-merchant-settings-empty"><strong>Settings unavailable</strong><p>' + esc(payload.error) + '</p></div></section>';
    if (!payload.schema_ready) return '<section class="mg-world-dashboard-section"><h3>Locations</h3><div class="mg-world-merchant-settings-empty"><strong>World geo schema needs repair</strong><p>Required missing: ' + (listText(payload.missing_columns) || 'unknown') + '.</p></div></section>';
    var locations = payload.locations || [];
    return '<section class="mg-world-dashboard-section"><h3>Merchant Location Anchors</h3>' +
      (optional.length ? '<p class="mg-world-dashboard-note">Optional fields missing: ' + listText(optional) + '.</p>' : '<p class="mg-world-dashboard-note">These coordinates anchor Store Canvas entry and World Canvas exit.</p>') +
      (locations.length ? locations.map(locationForm).join('') : '<div class="mg-world-merchant-settings-empty"><strong>No merchant locations</strong><p>Add a merchant location first.</p><a href="/merchant-locations.php">Open merchant locations</a></div>') + '</section>';
  }

  function renderDashboard() {
    var panel = ensurePanel();
    var nav = panel.querySelector('[data-world-dashboard-tabs]');
    var body = panel.querySelector('[data-world-merchant-settings-body]');
    if (nav) nav.innerHTML = tabNav();
    if (!body) return;
    if (state.loading) body.innerHTML = '<div class="mg-world-dashboard-empty">Loading World Dashboard…</div>';
    else if (state.tab === 'active') body.innerHTML = activeHtml();
    else if (state.tab === 'drafts') body.innerHTML = draftsHtml();
    else if (state.tab === 'tools') body.innerHTML = toolsHtml();
    else if (state.tab === 'settings') body.innerHTML = settingsHtml();
    else body.innerHTML = overviewHtml();
    bindLocationForms(body);
  }

  function focusLocation(form) {
    var id = form.dataset.locationId || '';
    var node = map.querySelector('[data-world-node-id="' + CSS.escape(id) + '"], [data-world-detail-id="' + CSS.escape(id) + '"]');
    if (!node) return;
    node.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
    node.classList.add('is-active');
    setTimeout(function () { node.classList.remove('is-active'); }, 1800);
  }

  function useCurrentLocation(form) {
    var status = form.querySelector('[data-world-location-status]');
    if (!navigator.geolocation) { if (status) status.textContent = 'Browser location is unavailable.'; return; }
    if (status) status.textContent = 'Reading browser location…';
    navigator.geolocation.getCurrentPosition(function (position) {
      form.elements.latitude.value = position.coords.latitude.toFixed(7);
      form.elements.longitude.value = position.coords.longitude.toFixed(7);
      form.elements.geo_accuracy_meters.value = Math.round(position.coords.accuracy || 0);
      if (status) status.textContent = 'Location filled. Save the merchant location.';
    }, function () {
      if (status) status.textContent = 'Unable to read browser location.';
    }, { enableHighAccuracy: false, timeout: 8000, maximumAge: 300000 });
  }

  function bindLocationForms(body) {
    body.querySelectorAll('[data-world-merchant-location-form]').forEach(function (form) {
      form.addEventListener('click', function (event) {
        if (event.target.closest('[data-world-use-current-location]')) useCurrentLocation(form);
        if (event.target.closest('[data-world-find-location]')) focusLocation(form);
      });
      form.addEventListener('submit', async function (event) {
        event.preventDefault();
        var status = form.querySelector('[data-world-location-status]');
        var data = Object.fromEntries(new FormData(form).entries());
        var csrf = token();
        data.csrf_token = csrf;
        data._csrf = csrf;
        data.csrf = csrf;
        data.geo_source = 'world_dashboard';
        try {
          if (status) status.textContent = 'Saving…';
          await MG.post('/api/world-canvas/merchant-world-settings.php', data);
          if (status) status.textContent = 'Location saved.';
          await loadDashboard(false);
          document.dispatchEvent(new CustomEvent('mg:world-merchant-settings-saved'));
        } catch (error) {
          if (status) status.textContent = error.message || 'Unable to save location.';
        }
      });
    });
  }

  async function loadDashboard(open) {
    var panel = ensurePanel();
    if (open) { panel.classList.add('is-open'); panel.setAttribute('aria-hidden', 'false'); }
    if (!state.settings && !state.drops.length) state.loading = true;
    renderDashboard();
    var settingsRequest = MG.get('/api/world-canvas/merchant-world-settings.php').then(function (response) { state.settings = response.data || response || {}; }).catch(function (error) { state.settings = { error: error.message || 'Unable to load location settings.' }; });
    var dropsRequest = MG.get('/api/world-canvas/target-drops.php').then(function (response) { var data = response.data || response || {}; state.drops = data.drops || []; }).catch(function () { state.drops = []; });
    var toolsRequest = MG.get('/api/world-canvas/intercept-tools.php').then(function (response) { var data = response.data || response || {}; state.tools = data.tools || []; state.catalog = data.catalog || []; }).catch(function () { state.tools = []; state.catalog = []; });
    await Promise.allSettled([settingsRequest, dropsRequest, toolsRequest]);
    state.loading = false;
    renderDashboard();
  }

  ensureButton().addEventListener('click', function () { loadDashboard(true); });
  document.addEventListener('click', function (event) {
    var tab = event.target.closest('[data-world-dashboard-tab]');
    if (tab) { state.tab = tab.dataset.worldDashboardTab || 'overview'; renderDashboard(); return; }
    if (event.target.closest('[data-world-merchant-settings-close]')) {
      var panel = ensurePanel();
      panel.classList.remove('is-open');
      panel.setAttribute('aria-hidden', 'true');
      return;
    }
    var openDrop = event.target.closest('[data-dashboard-open-drop]');
    if (openDrop) {
      var button = map.querySelector('[data-target-drop-id="' + CSS.escape(openDrop.dataset.dashboardOpenDrop) + '"]');
      if (button) button.click();
    }
  });
  window.setInterval(function () { loadDashboard(false); }, 20000);
  document.addEventListener('mg:world-merchant-settings-saved', function () { setTimeout(function () { loadDashboard(false); }, 500); });
  document.addEventListener('mg:world-target-drop-saved', function () { setTimeout(function () { loadDashboard(false); }, 500); });
  loadDashboard(false);
})(window, document);
