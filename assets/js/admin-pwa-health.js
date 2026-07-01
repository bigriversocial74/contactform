document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var root = document.querySelector('[data-admin-system-health]');
  if (!root || !window.Microgifter) return;
  var MG = window.Microgifter;
  var grid = root.querySelector('[data-pwa-health-grid]');

  function label(value) {
    return String(value || '').replace(/_/g, ' ').replace(/-/g, ' ').replace(/\b\w/g, function (character) { return character.toUpperCase(); });
  }

  function formatDate(value) {
    if (!value) return 'Admin self-test only';
    var raw = String(value);
    var parsed = new Date(raw.indexOf('T') === -1 ? raw.replace(' ', 'T') + 'Z' : raw);
    return Number.isNaN(parsed.getTime()) ? raw : parsed.toLocaleString();
  }

  function card(title, value, detail, tone) {
    var item = document.createElement('article');
    if (tone) item.className = 'is-' + tone;
    item.innerHTML = '<span></span><strong></strong><small></small>';
    item.querySelector('span').textContent = title;
    item.querySelector('strong').textContent = value;
    item.querySelector('small').textContent = detail;
    return item;
  }

  function render(data) {
    if (!grid) return;
    data = data || {};
    var swSupported = 'serviceWorker' in navigator;
    var swActive = swSupported && Boolean(navigator.serviceWorker.controller);
    var notificationSupported = 'Notification' in window;
    var permission = notificationSupported ? Notification.permission : 'unsupported';
    var pushConfigured = Boolean(data.vapid_public_key_configured && data.vapid_private_key_configured);
    var providerAvailable = Boolean(data.provider_available);
    var lastTest = data.last_test_notification_result || {};
    grid.replaceChildren(
      card('Service worker', swActive ? 'Active' : (swSupported ? 'Available' : 'Unsupported'), data.service_worker_file_present ? 'Worker file present' : 'Missing /sw.js', data.service_worker_file_present ? '' : 'critical'),
      card('Permission support', notificationSupported ? label(permission) : 'Unsupported', swSupported ? 'Browser capability checked' : 'Service workers unavailable', notificationSupported ? '' : 'critical'),
      card('Push endpoint', pushConfigured ? 'Configured' : 'Missing', providerAvailable ? 'Provider package available' : 'Provider package not installed', pushConfigured ? (providerAvailable ? '' : 'warning') : 'critical'),
      card('Active subscriptions', Number(data.active_subscriptions_count || 0).toLocaleString(), 'Authenticated PWA subscribers'),
      card('Failed delivery', Number(data.failed_delivery_count || 0).toLocaleString(), 'PWA delivery failures', data.failed_delivery_count > 0 ? 'warning' : ''),
      card('Last test', label(lastTest.status || 'Not sent yet'), formatDate(lastTest.created_at))
    );
  }

  async function load() {
    try {
      var response = await MG.get('/api/admin/system-health.php');
      var data = response.data || response;
      render(data.pwa_notifications || {});
    } catch (error) {
      render({ failed_delivery_count: 0, last_test_notification_result: { status: 'load failed' } });
    }
  }

  root.addEventListener('click', function (event) {
    var button = event.target.closest('[data-health-action="test_pwa_notification"]');
    if (button) setTimeout(load, 1800);
  });

  load();
});

document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var root = document.querySelector('[data-admin-system-health]');
  if (!root || !window.Microgifter) return;
  var MG = window.Microgifter;
  var run = root.querySelector('[data-sql-diagnostics-refresh]');
  var download = root.querySelector('[data-sql-diagnostics-download]');
  var plan = null;
  function setSummary(text) { var summary = root.querySelector('[data-sql-diagnostics-summary]'); if (summary) summary.textContent = text; }
  function save(name, text) { var blob = new Blob([text || ''], { type: 'text/plain;charset=utf-8' }); var url = URL.createObjectURL(blob); var link = document.createElement('a'); link.href = url; link.download = name || 'microgifter_system_sql_diagnostics.sql'; document.body.appendChild(link); link.click(); link.remove(); setTimeout(function () { URL.revokeObjectURL(url); }, 800); }
  function apply(data) { data = data || {}; plan = data.repair_plan || null; if (run) { run.disabled = false; run.textContent = 'Run diagnostics'; } if (download) { download.disabled = !(plan && plan.sql); download.textContent = plan && plan.sql ? 'Download diagnostics SQL' : 'Download repair SQL'; } if (data.summary) setSummary(data.summary); }
  async function refresh(event) { if (event) { event.preventDefault(); event.stopImmediatePropagation(); } if (run) { run.disabled = true; run.textContent = 'Running…'; } setSummary('Running fresh SQL diagnostics…'); try { var response = await MG.get('/api/admin/system-sql-diagnostics.php?_=' + Date.now()); apply(response.data || response); if (MG.toast) MG.toast('System SQL diagnostics refreshed.', 'success'); } catch (error) { if (run) { run.disabled = false; run.textContent = 'Run diagnostics'; } setSummary(error.message || 'Unable to run system SQL diagnostics.'); if (MG.toast) MG.toast(error.message || 'Unable to run system SQL diagnostics.', 'error'); } }
  if (run) run.addEventListener('click', refresh, true);
  if (download) download.addEventListener('click', function (event) { event.preventDefault(); event.stopImmediatePropagation(); if (plan && plan.sql) save(plan.filename, plan.sql); }, true);
  setTimeout(refresh, 500);
});
