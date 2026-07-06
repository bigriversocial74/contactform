document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-admin-system-health]');
  if (!root || !window.Microgifter) return;

  var MG = window.Microgifter;
  var refreshButton = root.querySelector('[data-file-reference-refresh]');

  function node(tag, className, text) {
    var item = document.createElement(tag);
    if (className) item.className = className;
    if (text !== undefined) item.textContent = String(text);
    return item;
  }

  function label(value) {
    return String(value || '').replace(/_/g, ' ').replace(/-/g, ' ').replace(/\b\w/g, function (character) { return character.toUpperCase(); });
  }

  function formatCount(value) {
    return Number(value || 0).toLocaleString();
  }

  function toneForStatus(status) {
    if (status === 'critical') return 'critical';
    if (status === 'protected' || status === 'not_present') return 'healthy';
    return 'warning';
  }

  function setTone(element, status) {
    if (!element) return;
    element.classList.remove('is-loading', 'is-healthy', 'is-warning', 'is-critical');
    element.classList.add('is-' + toneForStatus(status));
  }

  function metricCard(title, value, detail, tone) {
    var card = node('article', tone ? 'is-' + tone : '');
    card.append(node('span', '', title), node('strong', '', value), node('small', '', detail));
    return card;
  }

  function renderFileReferences(data) {
    var panel = root.querySelector('[data-file-reference-diagnostics]');
    var summary = root.querySelector('[data-file-reference-summary]');
    var metrics = root.querySelector('[data-file-reference-metrics]');
    var items = root.querySelector('[data-file-reference-items]');
    var findings = root.querySelector('[data-file-reference-findings]');
    var status = data && data.status ? data.status : 'review_required';
    var counts = data && data.counts ? data.counts : {};

    if (panel) setTone(panel, status);
    if (summary) {
      summary.classList.remove('is-loading', 'is-healthy', 'is-warning', 'is-critical');
      summary.classList.add(counts.missing_protected > 0 ? 'is-critical' : 'is-warning');
      summary.textContent = data && data.summary ? data.summary : 'File reference diagnostics loaded.';
    }

    if (metrics) {
      metrics.replaceChildren(
        metricCard('Candidates', formatCount(counts.candidates), 'Review-sensitive paths'),
        metricCard('Protected', formatCount(counts.protected), 'Active paths preserved'),
        metricCard('Referenced', formatCount(counts.referenced_review_required), 'Needs manual review', counts.referenced_review_required > 0 ? 'warning' : ''),
        metricCard('Duplicates', formatCount(counts.duplicate_candidate), 'Checksum matches found', counts.duplicate_candidate > 0 ? 'warning' : ''),
        metricCard('Action-ready', formatCount(counts.delete_ready), 'Read-only list should remain empty', counts.delete_ready > 0 ? 'critical' : '')
      );
    }

    if (items) {
      items.replaceChildren();
      var rows = data && data.items ? data.items : [];
      if (!rows.length) items.appendChild(node('p', 'mg-muted', 'No file reference candidates are available.'));
      rows.forEach(function (item) {
        var row = node('article', 'mg-system-sql-row is-' + toneForStatus(item.status));
        var copy = node('div');
        copy.append(node('strong', '', item.path || item.key || 'File candidate'));
        copy.append(node('p', '', item.reason || 'Review-sensitive path.'));
        copy.append(node('small', '', label(item.status) + ' · ' + label(item.classification) + ' · ' + formatCount(item.reference_count) + ' reference' + (Number(item.reference_count || 0) === 1 ? '' : 's')));
        row.append(copy, node('span', '', item.exists ? 'Present' : 'Missing'));
        items.appendChild(row);
      });
    }

    if (findings) {
      findings.replaceChildren();
      var findingRows = (data && data.items ? data.items : []).filter(function (item) {
        return item.status !== 'protected' && item.status !== 'not_present';
      });
      if (!findingRows.length) {
        var empty = node('div', 'mg-system-health-empty');
        empty.append(node('strong', '', 'No active reference findings'), node('p', '', 'No review-sensitive file candidates currently need attention.'));
        findings.appendChild(empty);
      }
      findingRows.forEach(function (item) {
        var row = node('article', 'mg-system-sql-row is-' + toneForStatus(item.status));
        var copy = node('div');
        var refs = item.references || [];
        var firstRefs = refs.slice(0, 3).map(function (ref) { return ref.file + ' → ' + ref.token; }).join(' · ');
        copy.append(node('strong', '', item.path || item.key || 'File candidate'));
        copy.append(node('p', '', firstRefs || item.reason || 'Manual review required.'));
        copy.append(node('small', '', label(item.status) + (item.checksum_sha256 ? ' · checksum ' + String(item.checksum_sha256).slice(0, 12) : '')));
        row.append(copy, node('span', '', label(item.status)));
        findings.appendChild(row);
      });
    }

    if (refreshButton) refreshButton.disabled = false;
  }

  async function loadFileReferences() {
    if (refreshButton) {
      refreshButton.disabled = true;
      refreshButton.textContent = 'Running…';
    }
    try {
      var response = await MG.get('/api/admin/legacy-file-diagnostics.php');
      renderFileReferences(response.data || response);
    } catch (error) {
      renderFileReferences({ status: 'critical', summary: error.message || 'Unable to load file reference diagnostics.', counts: {}, items: [] });
      if (MG.toast) MG.toast(error.message || 'Unable to load file reference diagnostics.', 'error');
    } finally {
      if (refreshButton) {
        refreshButton.disabled = false;
        refreshButton.textContent = 'Run file checks';
      }
    }
  }

  if (refreshButton) refreshButton.addEventListener('click', loadFileReferences);
  loadFileReferences();
});
