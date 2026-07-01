document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-admin-system-health]');
  if (!root || !window.Microgifter) return;
  var MG = window.Microgifter;
  var runButton = root.querySelector('[data-sql-diagnostics-refresh]');
  var downloadButton = root.querySelector('[data-sql-diagnostics-download]');
  var latestPlan = null;

  function node(tag, className, text) {
    var element = document.createElement(tag);
    if (className) element.className = className;
    if (text !== undefined) element.textContent = String(text);
    return element;
  }

  function label(value) {
    return String(value || '').replace(/_/g, ' ').replace(/-/g, ' ').replace(/\b\w/g, function (character) { return character.toUpperCase(); });
  }

  function downloadText(filename, text) {
    var blob = new Blob([text || ''], { type: 'text/sql;charset=utf-8' });
    var url = URL.createObjectURL(blob);
    var link = document.createElement('a');
    link.href = url;
    link.download = filename || 'microgifter_system_sql_diagnostics.sql';
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(function () { URL.revokeObjectURL(url); }, 800);
  }

  function metricCard(title, value, detail, tone) {
    var card = node('article', tone ? 'is-' + tone : '');
    card.append(node('span', '', title), node('strong', '', value), node('small', '', detail));
    return card;
  }

  function setTone(element, status) {
    if (!element) return;
    element.classList.remove('is-loading', 'is-healthy', 'is-warning', 'is-critical');
    element.classList.add('is-' + (['healthy', 'warning', 'critical'].includes(status) ? status : 'warning'));
  }

  function renderDiagnostics(data) {
    data = data || {};
    latestPlan = data.repair_plan || null;
    var status = data.status || 'warning';
    var panel = root.querySelector('[data-system-sql-diagnostics]');
    var summary = root.querySelector('[data-sql-diagnostics-summary]');
    var metrics = root.querySelector('[data-sql-diagnostics-metrics]');
    var modules = root.querySelector('[data-sql-diagnostics-modules]');
    var findings = root.querySelector('[data-sql-diagnostics-findings]');
    var counts = data.counts || {};

    setTone(panel, status);
    if (summary) {
      summary.classList.remove('is-loading', 'is-healthy', 'is-warning', 'is-critical');
      summary.classList.add('is-' + status);
      summary.textContent = data.summary || 'System SQL diagnostics loaded.';
    }
    if (metrics) {
      metrics.replaceChildren(
        metricCard('Modules', Number(counts.modules || 0).toLocaleString(), Number(counts.healthy_modules || 0).toLocaleString() + ' healthy'),
        metricCard('Critical modules', Number(counts.critical_modules || 0).toLocaleString(), Number(counts.warning_modules || 0).toLocaleString() + ' warning', counts.critical_modules > 0 ? 'critical' : (counts.warning_modules > 0 ? 'warning' : '')),
        metricCard('Findings', Number(counts.findings || 0).toLocaleString(), Number(counts.critical_findings || 0).toLocaleString() + ' critical', counts.critical_findings > 0 ? 'critical' : (counts.warning_findings > 0 ? 'warning' : '')),
        metricCard('Recent SQL errors', Number(counts.recent_sql_errors || 0).toLocaleString(), 'Security/ops log scan', counts.recent_sql_errors > 0 ? 'warning' : ''),
        metricCard('Repair plan', latestPlan && latestPlan.sql ? 'Ready' : 'None', Number(latestPlan && latestPlan.sql_bytes ? latestPlan.sql_bytes : 0).toLocaleString() + ' bytes', latestPlan && latestPlan.sql ? 'warning' : '')
      );
    }
    if (modules) {
      modules.replaceChildren();
      (data.modules || []).slice(0, 12).forEach(function (item) {
        var row = node('article', 'mg-system-sql-row is-' + String(item.status || 'warning'));
        var copy = node('div');
        var detail = [];
        if ((item.missing_tables || []).length) detail.push((item.missing_tables || []).length + ' tables');
        if ((item.missing_columns || []).length) detail.push((item.missing_columns || []).length + ' columns');
        if ((item.missing_enums || []).length) detail.push((item.missing_enums || []).length + ' enum drift');
        copy.append(node('strong', '', item.label || label(item.key)));
        copy.append(node('p', '', item.summary || 'Module diagnostics loaded.'));
        copy.append(node('small', '', detail.length ? 'Missing: ' + detail.join(', ') : (item.migration_hint || 'Ready')));
        row.append(copy, node('span', '', label(item.status)));
        modules.appendChild(row);
      });
      if (!modules.children.length) modules.appendChild(node('p', 'mg-muted', 'No module diagnostics are available.'));
    }
    if (findings) {
      findings.replaceChildren();
      (data.findings || []).slice(0, 14).forEach(function (item) {
        var row = node('article', 'mg-system-sql-row is-' + String(item.severity || 'warning'));
        var copy = node('div');
        copy.append(node('strong', '', item.item || item.type || 'SQL finding'));
        copy.append(node('p', '', item.message || 'A SQL dependency needs review.'));
        copy.append(node('small', '', label(item.module) + ' · ' + (item.repairable ? 'repair SQL available' : (item.migration_hint || 'manual review'))));
        row.append(copy, node('span', '', label(item.severity)));
        findings.appendChild(row);
      });
      if (!findings.children.length) {
        var empty = node('div', 'mg-system-health-empty');
        empty.append(node('strong', '', 'No SQL findings'), node('p', '', 'The current diagnostics catalog did not find missing dependencies.'));
        findings.appendChild(empty);
      }
    }
    if (downloadButton) {
      var canDownload = Boolean(latestPlan && latestPlan.sql);
      downloadButton.disabled = !canDownload;
      downloadButton.dataset.sqlDiagnosticsDownloadEnabled = canDownload ? 'true' : 'false';
      downloadButton.textContent = canDownload ? 'Download diagnostics SQL' : 'Download repair SQL';
    }
    if (runButton) {
      runButton.disabled = false;
      runButton.textContent = 'Run diagnostics';
    }
  }

  async function runDiagnostics(event) {
    if (event) {
      event.preventDefault();
      event.stopImmediatePropagation();
    }
    if (runButton) {
      runButton.disabled = true;
      runButton.textContent = 'Running…';
    }
    var summary = root.querySelector('[data-sql-diagnostics-summary]');
    if (summary) {
      summary.classList.remove('is-healthy', 'is-warning', 'is-critical');
      summary.classList.add('is-loading');
      summary.textContent = 'Running fresh SQL diagnostics…';
    }
    try {
      var response = await MG.get('/api/admin/system-sql-diagnostics.php?_=' + Date.now());
      renderDiagnostics(response.data || response);
      if (MG.toast) MG.toast('System SQL diagnostics refreshed.', 'success');
    } catch (error) {
      renderDiagnostics({ status: 'critical', summary: error.message || 'Unable to run system SQL diagnostics.', counts: {}, modules: [], findings: [] });
      if (MG.toast) MG.toast(error.message || 'Unable to run system SQL diagnostics.', 'error');
    }
  }

  function downloadPlan(event) {
    if (event) {
      event.preventDefault();
      event.stopImmediatePropagation();
    }
    if (!latestPlan || !latestPlan.sql) return;
    downloadText(latestPlan.filename || 'microgifter_system_sql_diagnostics.sql', latestPlan.sql);
  }

  if (runButton) runButton.addEventListener('click', runDiagnostics, true);
  if (downloadButton) downloadButton.addEventListener('click', downloadPlan, true);
  setTimeout(runDiagnostics, 450);
});
