document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-admin-system-health]');
  if (!root || !window.Microgifter) return;
  var MG = window.Microgifter;
  var refreshButton = root.querySelector('[data-system-health-refresh]');

  function node(tag, className, text) {
    var item = document.createElement(tag);
    if (className) item.className = className;
    if (text !== undefined) item.textContent = String(text);
    return item;
  }

  function label(value) {
    return String(value || '').replace(/_/g, ' ').replace(/-/g, ' ').replace(/\b\w/g, function (character) { return character.toUpperCase(); });
  }

  function detailValue(value) {
    if (value === true) return 'Yes';
    if (value === false) return 'No';
    if (value === null || value === undefined || value === '') return '—';
    return String(value);
  }

  function formatBytes(value) {
    var bytes = Number(value || 0);
    if (!Number.isFinite(bytes) || bytes < 1) return '0 B';
    var units = ['B', 'KB', 'MB', 'GB', 'TB'];
    var unit = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    return (bytes / Math.pow(1024, unit)).toLocaleString(undefined, { maximumFractionDigits: unit > 1 ? 1 : 0 }) + ' ' + units[unit];
  }

  function formatDate(value) {
    if (!value) return '—';
    var raw = String(value);
    var parsed = new Date(raw.indexOf('T') === -1 ? raw.replace(' ', 'T') + 'Z' : raw);
    return Number.isNaN(parsed.getTime()) ? raw : parsed.toLocaleString();
  }

  function setTone(element, status) {
    if (!element) return;
    element.classList.remove('is-loading', 'is-healthy', 'is-warning', 'is-critical');
    element.classList.add('is-' + (['healthy', 'warning', 'critical'].includes(status) ? status : 'warning'));
  }

  function renderBanner(data) {
    var banner = root.querySelector('[data-system-health-banner]');
    if (!banner) return;
    setTone(banner, data.status);
    var copy = banner.querySelector('div');
    copy.replaceChildren(node('strong', '', label(data.status)), node('p', '', data.summary || 'System health loaded.'));
  }

  function renderServices(services) {
    Object.keys(services || {}).forEach(function (key) {
      var service = services[key] || {};
      var card = root.querySelector('[data-health-service="' + key + '"]');
      if (!card) return;
      setTone(card, service.status);
      var status = card.querySelector('[data-health-status]');
      if (status) status.textContent = label(service.status);
      var summary = card.querySelector('p');
      if (summary) summary.textContent = service.summary || 'No service summary is available.';
      var details = card.querySelector('[data-health-details]');
      details.replaceChildren();
      Object.keys(service.details || {}).forEach(function (detailKey) {
        var row = node('div');
        row.append(node('dt', '', label(detailKey)), node('dd', '', detailValue(service.details[detailKey])));
        details.appendChild(row);
      });
      if (!details.children.length) {
        var fallback = node('div');
        fallback.append(node('dt', '', 'Status'), node('dd', '', label(service.status)));
        details.appendChild(fallback);
      }
    });
  }

  function metricCard(title, value, detail, tone) {
    var card = node('article', tone ? 'is-' + tone : '');
    card.append(node('span', '', title), node('strong', '', value), node('small', '', detail));
    return card;
  }

  function renderMetrics(metrics) {
    var container = root.querySelector('[data-system-health-metrics]');
    if (!container) return;
    var media = metrics.media || {};
    var notifications = metrics.notifications || {};
    var missingDetail = media.scan_limited
      ? 'Checked the newest ' + Number(media.checked_files || 0).toLocaleString() + ' files'
      : 'Checked ' + Number(media.checked_files || 0).toLocaleString() + ' files';
    container.replaceChildren(
      metricCard('Media files', Number(media.media_files || 0).toLocaleString(), 'Ready persistent assets'),
      metricCard('Storage used', formatBytes(media.storage_used_bytes), media.storage_free_bytes == null ? 'Capacity unavailable' : formatBytes(media.storage_free_bytes) + ' free'),
      metricCard('Unattached uploads', Number(media.unattached_uploads || 0).toLocaleString(), 'Eligible for later cleanup', media.unattached_uploads > 0 ? 'warning' : ''),
      metricCard('Missing files', Number(media.missing_files || 0).toLocaleString(), missingDetail, media.missing_files > 0 ? 'critical' : ''),
      metricCard('Queued notifications', Number(notifications.queued || 0).toLocaleString(), Number(notifications.overdue || 0).toLocaleString() + ' overdue', notifications.overdue > 0 ? 'warning' : ''),
      metricCard('Failed notifications', Number(notifications.failed || 0).toLocaleString(), Number(notifications.retrying || 0).toLocaleString() + ' scheduled to retry', notifications.failed > 0 ? 'critical' : '')
    );
  }

  function renderReadiness(data) {
    var panel = root.querySelector('[data-system-health-readiness]');
    if (!panel) return;
    var status = panel.querySelector('[data-readiness-status]');
    var summary = panel.querySelector('[data-readiness-summary]');
    var grid = panel.querySelector('[data-readiness-grid]');
    setTone(panel, data.status || (data.ready ? 'healthy' : 'critical'));
    if (status) status.textContent = data.ready ? 'Ready' : 'Needs attention';
    if (summary) summary.textContent = data.summary || 'Admin ops readiness loaded.';
    if (!grid) return;
    grid.replaceChildren();
    (data.sections || []).forEach(function (section) {
      var card = node('article', 'mg-system-health-readiness-card is-' + (section.ready ? 'healthy' : 'critical'));
      var header = node('header');
      header.append(node('strong', '', section.label || label(section.key)), node('span', '', section.ready ? 'Ready' : Number(section.missing_count || 0).toLocaleString() + ' missing'));
      var list = node('ul');
      (section.checks || []).filter(function (check) { return !check.ready; }).slice(0, 8).forEach(function (check) {
        list.appendChild(node('li', '', check.label || check.key));
      });
      if (!list.children.length) list.appendChild(node('li', '', 'All required items are present.'));
      card.append(header, list);
      grid.appendChild(card);
    });
  }

  function renderWarnings(items) {
    var container = root.querySelector('[data-system-health-warnings]');
    if (!container) return;
    container.replaceChildren();
    if (!Array.isArray(items) || !items.length) {
      var empty = node('div', 'mg-system-health-empty');
      empty.append(node('strong', '', 'No recent warnings'), node('p', '', 'No warning, error, or critical events are currently listed.'));
      container.appendChild(empty);
      return;
    }
    items.forEach(function (item) {
      var row = node('article', 'mg-system-health-warning is-' + String(item.severity || 'warning').toLowerCase());
      var copy = node('div');
      copy.append(node('strong', '', item.title || 'Operational warning'));
      if (item.message) copy.append(node('p', '', item.message));
      copy.append(node('small', '', label(item.source) + ' · ' + formatDate(item.created_at)));
      row.append(copy, node('span', '', label(item.severity)));
      container.appendChild(row);
    });
  }

  function renderActions(actions) {
    root.querySelectorAll('[data-health-action]').forEach(function (button) {
      var enabled = Boolean(actions && actions[button.dataset.healthAction]);
      button.disabled = !enabled;
      button.dataset.healthActionEnabled = enabled ? 'true' : 'false';
    });
    var actionPanel = root.querySelector('[data-system-health-actions]');
    var note = actionPanel ? actionPanel.querySelector('p') : null;
    if (note) {
      note.textContent = actions && Object.values(actions).some(Boolean)
        ? 'Recovery actions are limited, audited, and available only to super administrators.'
        : 'Recovery actions require a super administrator session.';
    }
  }

  function render(data) {
    renderBanner(data);
    renderServices(data.services || {});
    renderMetrics(data.metrics || {});
    renderWarnings(data.warnings || []);
    renderActions(data.actions || {});
    var updated = root.querySelector('[data-system-health-updated]');
    if (updated) updated.textContent = formatDate(data.generated_at);
  }

  async function loadReadiness() {
    try {
      var response = await MG.get('/api/admin/admin-ops-readiness.php');
      renderReadiness(response.data || response);
    } catch (error) {
      renderReadiness({ status: 'critical', ready: false, summary: error.message || 'Unable to load admin ops readiness.', sections: [] });
    }
  }

  async function load() {
    if (refreshButton) {
      refreshButton.disabled = true;
      refreshButton.textContent = 'Refreshing…';
    }
    try {
      var response = await MG.get('/api/admin/system-health.php');
      render(response.data || response);
      await loadReadiness();
    } catch (error) {
      renderBanner({ status: 'critical', summary: error.message || 'Unable to load system health.' });
      if (MG.toast) MG.toast(error.message || 'Unable to load system health.', 'error');
      await loadReadiness();
    } finally {
      if (refreshButton) {
        refreshButton.disabled = false;
        refreshButton.textContent = 'Refresh';
      }
    }
  }

  function actionConfirmation(action) {
    return {
      verify_storage: 'Run a protected write, read, and delete verification on persistent storage?',
      retry_notifications: 'Requeue up to 100 eligible failed notification deliveries?',
      clean_uploads: 'Archive and remove up to 100 unattached uploads older than 24 hours?',
      migration_plan: 'Prepare a read-only migration recovery plan? This does not run database migrations.'
    }[action] || 'Run this recovery action?';
  }

  function actionResult(action, result) {
    if (action === 'verify_storage') return 'Persistent storage passed its write and read verification.';
    if (action === 'retry_notifications') return Number(result.retried || 0).toLocaleString() + ' notification deliveries queued for retry.';
    if (action === 'clean_uploads') return Number(result.archived || 0).toLocaleString() + ' abandoned uploads archived; ' + Number(result.files_deleted || 0).toLocaleString() + ' files removed.';
    if (action === 'migration_plan') {
      var missing = Number(result.missing_count || 0).toLocaleString();
      var command = result.command || 'php scripts/run_migrations.php';
      return missing + ' missing migration file(s). Run: ' + command;
    }
    return 'Recovery action completed.';
  }

  async function runAction(button) {
    var action = button.dataset.healthAction;
    if (button.dataset.healthActionEnabled !== 'true') return;
    if (!window.confirm(actionConfirmation(action))) return;
    var original = button.textContent;
    button.disabled = true;
    button.textContent = 'Running…';
    try {
      var response = await MG.post('/api/admin/system-health-action.php', { action: action });
      var data = response.data || response;
      if (MG.toast) MG.toast(actionResult(action, data.result || {}), 'success');
      await load();
    } catch (error) {
      if (MG.toast) MG.toast(error.message || 'Unable to complete the recovery action.', 'error');
    } finally {
      button.textContent = original;
      if (button.dataset.healthActionEnabled === 'true') button.disabled = false;
    }
  }

  if (refreshButton) refreshButton.addEventListener('click', load);
  root.addEventListener('click', function (event) {
    var button = event.target.closest('[data-health-action]');
    if (button) runAction(button);
  });
  load();
});
