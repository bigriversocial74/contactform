document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-admin-system-health]');
  if (!root || !window.Microgifter) return;

  var MG = window.Microgifter;
  var refreshButton = root.querySelector('[data-data-integrity-refresh]');

  function node(tag, className, text) {
    var item = document.createElement(tag);
    if (className) item.className = className;
    if (text !== undefined) item.textContent = String(text);
    return item;
  }

  function label(value) {
    return String(value || '').replace(/_/g, ' ').replace(/-/g, ' ').replace(/\b\w/g, function (character) { return character.toUpperCase(); });
  }

  function setTone(element, status) {
    if (!element) return;
    element.classList.remove('is-loading', 'is-healthy', 'is-warning', 'is-critical');
    element.classList.add('is-' + (['healthy', 'warning', 'critical'].includes(status) ? status : 'warning'));
  }

  function metricCard(title, value, detail, tone) {
    var card = node('article', tone ? 'is-' + tone : '');
    card.append(node('span', '', title), node('strong', '', value), node('small', '', detail));
    return card;
  }

  function formatCount(value) {
    return Number(value || 0).toLocaleString();
  }

  function renderDataIntegrity(data) {
    var panel = root.querySelector('[data-data-integrity-diagnostics]');
    var summary = root.querySelector('[data-data-integrity-summary]');
    var metrics = root.querySelector('[data-data-integrity-metrics]');
    var groups = root.querySelector('[data-data-integrity-groups]');
    var checks = root.querySelector('[data-data-integrity-checks]');
    var status = data && data.status ? data.status : 'warning';

    if (panel) setTone(panel, status);
    if (summary) {
      summary.classList.remove('is-loading', 'is-healthy', 'is-warning', 'is-critical');
      summary.classList.add('is-' + status);
      summary.textContent = data && data.summary ? data.summary : 'Data integrity diagnostics loaded.';
    }

    var counts = data && data.counts ? data.counts : {};
    if (metrics) {
      metrics.replaceChildren(
        metricCard('Groups', formatCount(counts.groups), 'Diagnostic groups checked'),
        metricCard('Checks', formatCount(counts.checks), 'Read-only checks run'),
        metricCard('Critical', formatCount(counts.critical), 'Needs immediate review', counts.critical > 0 ? 'critical' : ''),
        metricCard('Warnings', formatCount(counts.warning), 'Needs review', counts.warning > 0 ? 'warning' : ''),
        metricCard('Unavailable', formatCount(counts.not_available), 'Skipped by missing tables/columns', counts.not_available > 0 ? 'warning' : '')
      );
    }

    if (groups) {
      groups.replaceChildren();
      var groupItems = (data && data.groups ? data.groups : []).slice(0, 12);
      if (!groupItems.length) {
        groups.appendChild(node('p', 'mg-muted', 'No integrity groups are available.'));
      }
      groupItems.forEach(function (item) {
        var row = node('article', 'mg-system-sql-row is-' + String(item.status || 'warning'));
        var copy = node('div');
        var groupCounts = item.counts || {};
        copy.append(node('strong', '', item.label || label(item.key)));
        copy.append(node('p', '', formatCount(groupCounts.checks) + ' checks · ' + formatCount(groupCounts.critical) + ' critical · ' + formatCount(groupCounts.warning) + ' warning'));
        copy.append(node('small', '', groupCounts.not_available > 0 ? formatCount(groupCounts.not_available) + ' unavailable checks' : 'All checks available'));
        row.append(copy, node('span', '', label(item.status)));
        groups.appendChild(row);
      });
    }

    if (checks) {
      checks.replaceChildren();
      var findingItems = (data && data.checks ? data.checks : []).filter(function (item) {
        return ['critical', 'warning'].includes(String(item.status || ''));
      }).slice(0, 14);
      if (!findingItems.length) {
        var empty = node('div', 'mg-system-health-empty');
        empty.append(node('strong', '', 'No integrity findings'), node('p', '', 'The current read-only checks did not detect data drift.'));
        checks.appendChild(empty);
      }
      findingItems.forEach(function (item) {
        var row = node('article', 'mg-system-sql-row is-' + String(item.status || 'warning'));
        var copy = node('div');
        copy.append(node('strong', '', item.label || item.key || 'Integrity finding'));
        copy.append(node('p', '', item.summary || 'A data integrity issue needs review.'));
        copy.append(node('small', '', label(item.group) + ' · ' + formatCount(item.count) + ' record' + (Number(item.count || 0) === 1 ? '' : 's')));
        row.append(copy, node('span', '', label(item.status)));
        checks.appendChild(row);
      });
    }

    if (refreshButton) refreshButton.disabled = false;
  }

  async function loadDataIntegrity() {
    if (refreshButton) {
      refreshButton.disabled = true;
      refreshButton.textContent = 'Running…';
    }
    try {
      var response = await MG.get('/api/admin/data-integrity-diagnostics.php');
      renderDataIntegrity(response.data || response);
    } catch (error) {
      renderDataIntegrity({ status: 'critical', summary: error.message || 'Unable to load data integrity diagnostics.', counts: {}, groups: [], checks: [] });
      if (MG.toast) MG.toast(error.message || 'Unable to load data integrity diagnostics.', 'error');
    } finally {
      if (refreshButton) {
        refreshButton.disabled = false;
        refreshButton.textContent = 'Run integrity checks';
      }
    }
  }

  if (refreshButton) refreshButton.addEventListener('click', loadDataIntegrity);
  loadDataIntegrity();
});
