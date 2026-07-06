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

  function formatBytes(value) {
    var bytes = Number(value || 0);
    if (!bytes) return '0 B';
    var units = ['B', 'KB', 'MB', 'GB'];
    var index = 0;
    while (bytes >= 1024 && index < units.length - 1) {
      bytes = bytes / 1024;
      index += 1;
    }
    return (index === 0 ? bytes.toFixed(0) : bytes.toFixed(1)) + ' ' + units[index];
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

  function shortChecksum(value) {
    return value ? String(value).slice(0, 12) : 'none';
  }

  function buildDuplicateMap(rows) {
    var map = {};
    rows.forEach(function (item) {
      var checksum = item.checksum_sha256;
      if (!checksum) return;
      if (!map[checksum]) map[checksum] = [];
      map[checksum].push(item.path || item.key || 'unknown');
    });
    return map;
  }

  function duplicateText(item, duplicateMap) {
    var checksum = item.checksum_sha256;
    if (!checksum || !duplicateMap[checksum] || duplicateMap[checksum].length < 2) return '';
    return 'Matches: ' + duplicateMap[checksum].filter(function (path) {
      return path !== item.path;
    }).join(', ');
  }

  function fileMetaText(item, duplicateMap) {
    var parts = [label(item.status), label(item.classification), label(item.type)];
    if (item.exists) parts.push('Present'); else parts.push('Missing');
    if (item.size_bytes !== null && item.size_bytes !== undefined) parts.push(formatBytes(item.size_bytes));
    if (item.directory_summary) {
      parts.push(formatCount(item.directory_summary.files) + ' files');
      parts.push(formatCount(item.directory_summary.directories) + ' dirs');
      parts.push(formatBytes(item.directory_summary.bytes));
    }
    parts.push(formatCount(item.reference_count) + ' reference' + (Number(item.reference_count || 0) === 1 ? '' : 's'));
    if (item.checksum_sha256) parts.push('checksum ' + shortChecksum(item.checksum_sha256));
    var duplicate = duplicateText(item, duplicateMap);
    if (duplicate) parts.push(duplicate);
    return parts.join(' · ');
  }

  function renderReferences(refs) {
    if (!refs || !refs.length) return 'No code references returned.';
    return refs.slice(0, 4).map(function (ref) { return ref.file + ' → ' + ref.token; }).join(' · ');
  }

  function renderFileReferences(data) {
    var panel = root.querySelector('[data-file-reference-diagnostics]');
    var summary = root.querySelector('[data-file-reference-summary]');
    var metrics = root.querySelector('[data-file-reference-metrics]');
    var items = root.querySelector('[data-file-reference-items]');
    var findings = root.querySelector('[data-file-reference-findings]');
    var status = data && data.status ? data.status : 'review_required';
    var counts = data && data.counts ? data.counts : {};
    var rows = data && data.items ? data.items : [];
    var duplicateMap = buildDuplicateMap(rows);

    if (panel) setTone(panel, status);
    if (summary) {
      summary.classList.remove('is-loading', 'is-healthy', 'is-warning', 'is-critical');
      summary.classList.add(counts.missing_protected > 0 ? 'is-critical' : 'is-warning');
      summary.textContent = (data && data.summary ? data.summary : 'File reference diagnostics loaded.') + (data && data.catalog_version ? ' Catalog: ' + data.catalog_version + '.' : '');
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
      if (!rows.length) items.appendChild(node('p', 'mg-muted', 'No file reference candidates are available.'));
      rows.forEach(function (item) {
        var row = node('article', 'mg-system-sql-row is-' + toneForStatus(item.status));
        var copy = node('div');
        copy.append(node('strong', '', item.path || item.key || 'File candidate'));
        copy.append(node('p', '', item.reason || 'Review-sensitive path.'));
        copy.append(node('small', '', fileMetaText(item, duplicateMap)));
        var badge = item.status === 'duplicate_candidate' ? 'Duplicate' : (item.exists ? 'Present' : 'Missing');
        row.append(copy, node('span', '', badge));
        items.appendChild(row);
      });
    }

    if (findings) {
      findings.replaceChildren();
      var findingRows = rows.filter(function (item) {
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
        var duplicate = duplicateText(item, duplicateMap);
        copy.append(node('strong', '', item.path || item.key || 'File candidate'));
        copy.append(node('p', '', duplicate || renderReferences(item.references || [])));
        copy.append(node('small', '', fileMetaText(item, duplicateMap)));
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
