document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-admin-system-health]');
  if (!root || !window.Microgifter) return;
  var MG = window.Microgifter;
  var refreshButton = root.querySelector('[data-system-health-refresh]');
  var sqlRefreshButton = root.querySelector('[data-sql-diagnostics-refresh]');
  var sqlDownloadButton = root.querySelector('[data-sql-diagnostics-download]');
  var lastSqlDiagnostics = null;

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

  function downloadText(filename, text) {
    var blob = new Blob([text || ''], { type: 'text/sql;charset=utf-8' });
    var url = URL.createObjectURL(blob);
    var link = document.createElement('a');
    link.href = url;
    link.download = filename || 'microgifter_admin_ops_recovery.sql';
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(function () { URL.revokeObjectURL(url); }, 800);
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

  function recoveryButton(action, text, enabled) {
    var button = node('button', 'mg-btn mg-btn-soft', text);
    button.type = 'button';
    button.dataset.healthAction = action;
    button.dataset.healthActionEnabled = enabled ? 'true' : 'false';
    button.disabled = !enabled;
    return button;
  }

  function renderRecoveryTools(actions) {
    var container = root.querySelector('[data-system-health-recovery]');
    if (!container) return;
    var available = Boolean(actions && Object.values(actions).some(Boolean));
    container.replaceChildren(
      recoveryButton('admin_ops_sql_plan', 'Prepare Admin Ops SQL', Boolean(actions && actions.admin_ops_sql_plan)),
      recoveryButton('migration_plan', 'Migration Plan', Boolean(actions && actions.migration_plan)),
      recoveryButton('verify_storage', 'Verify Storage', Boolean(actions && actions.verify_storage)),
      recoveryButton('retry_notifications', 'Retry Notifications', Boolean(actions && actions.retry_notifications)),
      recoveryButton('clean_uploads', 'Clean Uploads', Boolean(actions && actions.clean_uploads)),
      node('p', '', available ? 'Recovery actions are limited, audited, and available only to super administrators.' : 'Recovery actions require a super administrator session.')
    );
  }

  function renderActions(actions) {
    root.querySelectorAll('[data-health-action]').forEach(function (button) {
      var enabled = Boolean(actions && actions[button.dataset.healthAction]);
      button.disabled = !enabled;
      button.dataset.healthActionEnabled = enabled ? 'true' : 'false';
    });
    renderRecoveryTools(actions || {});
  }

  function renderSqlDiagnostics(data) {
    lastSqlDiagnostics = data || null;
    var panel = root.querySelector('[data-system-sql-diagnostics]');
    var summary = root.querySelector('[data-sql-diagnostics-summary]');
    var metrics = root.querySelector('[data-sql-diagnostics-metrics]');
    var modules = root.querySelector('[data-sql-diagnostics-modules]');
    var findings = root.querySelector('[data-sql-diagnostics-findings]');
    var status = data && data.status ? data.status : 'warning';
    if (panel) setTone(panel, status);
    if (summary) {
      summary.classList.remove('is-loading', 'is-healthy', 'is-warning', 'is-critical');
      summary.classList.add('is-' + status);
      summary.textContent = data && data.summary ? data.summary : 'System SQL diagnostics loaded.';
    }
    var counts = data && data.counts ? data.counts : {};
    if (metrics) {
      metrics.replaceChildren(
        metricCard('Modules', Number(counts.modules || 0).toLocaleString(), Number(counts.healthy_modules || 0).toLocaleString() + ' healthy'),
        metricCard('Critical modules', Number(counts.critical_modules || 0).toLocaleString(), Number(counts.warning_modules || 0).toLocaleString() + ' warning', counts.critical_modules > 0 ? 'critical' : (counts.warning_modules > 0 ? 'warning' : '')),
        metricCard('Findings', Number(counts.findings || 0).toLocaleString(), Number(counts.critical_findings || 0).toLocaleString() + ' critical', counts.critical_findings > 0 ? 'critical' : (counts.warning_findings > 0 ? 'warning' : '')),
        metricCard('Recent SQL errors', Number(counts.recent_sql_errors || 0).toLocaleString(), 'Security/ops log scan', counts.recent_sql_errors > 0 ? 'warning' : ''),
        metricCard('Repairable', Number(counts.repairable_findings || 0).toLocaleString(), 'Auto SQL suggestions', counts.repairable_findings > 0 ? 'warning' : '')
      );
    }
    if (modules) {
      modules.replaceChildren();
      var moduleItems = (data && data.modules ? data.modules : []).slice(0, 12);
      if (!moduleItems.length) {
        modules.appendChild(node('p', 'mg-muted', 'No module diagnostics are available.'));
      }
      moduleItems.forEach(function (item) {
        var row = node('article', 'mg-system-sql-row is-' + String(item.status || 'warning'));
        var copy = node('div');
        copy.append(node('strong', '', item.label || label(item.key)));
        copy.append(node('p', '', item.summary || 'Module diagnostics loaded.'));
        var detail = [];
        if ((item.missing_tables || []).length) detail.push((item.missing_tables || []).length + ' tables');
        if ((item.missing_columns || []).length) detail.push((item.missing_columns || []).length + ' columns');
        if ((item.missing_enums || []).length) detail.push((item.missing_enums || []).length + ' enum drift');
        copy.append(node('small', '', detail.length ? 'Missing: ' + detail.join(', ') : (item.migration_hint || 'Ready')));
        row.append(copy, node('span', '', label(item.status)));
        modules.appendChild(row);
      });
    }
    if (findings) {
      findings.replaceChildren();
      var findingItems = (data && data.findings ? data.findings : []).slice(0, 14);
      if (!findingItems.length) {
        var empty = node('div', 'mg-system-health-empty');
        empty.append(node('strong', '', 'No SQL findings'), node('p', '', 'The current diagnostics catalog did not find missing dependencies.'));
        findings.appendChild(empty);
      }
      findingItems.forEach(function (item) {
        var row = node('article', 'mg-system-sql-row is-' + String(item.severity || 'warning'));
        var copy = node('div');
        copy.append(node('strong', '', item.item || item.type || 'SQL finding'));
        copy.append(node('p', '', item.message || 'A SQL dependency needs review.'));
        copy.append(node('small', '', label(item.module) + ' · ' + (item.repairable ? 'repair SQL available' : (item.migration_hint || 'manual review'))));
        row.append(copy, node('span', '', label(item.severity)));
        findings.appendChild(row);
      });
    }
    if (sqlDownloadButton) {
      var plan = data && data.repair_plan ? data.repair_plan : {};
      sqlDownloadButton.disabled = !(plan.available && plan.sql);
      sqlDownloadButton.dataset.sqlDiagnosticsDownloadEnabled = (!sqlDownloadButton.disabled).toString();
    }
    if (sqlRefreshButton) sqlRefreshButton.disabled = false;
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

  async function loadSqlDiagnostics() {
    if (sqlRefreshButton) {
      sqlRefreshButton.disabled = true;
      sqlRefreshButton.textContent = 'Running…';
    }
    try {
      var response = await MG.get('/api/admin/system-sql-diagnostics.php');
      renderSqlDiagnostics(response.data || response);
    } catch (error) {
      renderSqlDiagnostics({ status: 'critical', summary: error.message || 'Unable to run system SQL diagnostics.', counts: {}, modules: [], findings: [] });
      if (MG.toast) MG.toast(error.message || 'Unable to run system SQL diagnostics.', 'error');
    } finally {
      if (sqlRefreshButton) {
        sqlRefreshButton.disabled = false;
        sqlRefreshButton.textContent = 'Run diagnostics';
      }
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
      await loadSqlDiagnostics();
    } catch (error) {
      renderBanner({ status: 'critical', summary: error.message || 'Unable to load system health.' });
      if (MG.toast) MG.toast(error.message || 'Unable to load system health.', 'error');
      await loadReadiness();
      await loadSqlDiagnostics();
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
      migration_plan: 'Prepare a read-only migration recovery plan? This does not run database migrations.',
      admin_ops_sql_plan: 'Prepare a downloadable admin ops SQL recovery plan? This does not execute database changes.'
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
    if (action === 'admin_ops_sql_plan') {
      return 'Admin ops SQL plan prepared: ' + Number(result.sql_bytes || 0).toLocaleString() + ' bytes.';
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
      var result = data.result || {};
      if (action === 'admin_ops_sql_plan' && result.sql) {
        downloadText(result.filename || 'microgifter_admin_ops_recovery.sql', result.sql);
      }
      if (MG.toast) MG.toast(actionResult(action, result), 'success');
      await load();
    } catch (error) {
      if (MG.toast) MG.toast(error.message || 'Unable to complete the recovery action.', 'error');
    } finally {
      button.textContent = original;
      if (button.dataset.healthActionEnabled === 'true') button.disabled = false;
    }
  }

  if (refreshButton) refreshButton.addEventListener('click', load);
  if (sqlRefreshButton) sqlRefreshButton.addEventListener('click', loadSqlDiagnostics);
  if (sqlDownloadButton) sqlDownloadButton.addEventListener('click', function () {
    var plan = lastSqlDiagnostics && lastSqlDiagnostics.repair_plan ? lastSqlDiagnostics.repair_plan : null;
    if (!plan || !plan.available || !plan.sql) return;
    downloadText(plan.filename || 'microgifter_system_sql_repair.sql', plan.sql);
  });
  root.addEventListener('click', function (event) {
    var button = event.target.closest('[data-health-action]');
    if (button) runAction(button);
  });
  load();
});