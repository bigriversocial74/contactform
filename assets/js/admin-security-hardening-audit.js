document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-admin-system-health]');
  if (!root || !window.Microgifter) return;
  var MG = window.Microgifter;
  var button = root.querySelector('[data-security-audit-refresh]');

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
    element.classList.remove('is-loading', 'is-healthy', 'is-warning', 'is-critical', 'is-info');
    element.classList.add('is-' + (['healthy', 'warning', 'critical', 'info'].includes(status) ? status : 'warning'));
  }

  function metricCard(title, value, detail, tone) {
    var card = node('article', tone ? 'is-' + tone : '');
    card.append(node('span', '', title), node('strong', '', value), node('small', '', detail));
    return card;
  }

  function render(data) {
    data = data || {};
    var panel = root.querySelector('[data-security-hardening-audit]');
    var summary = root.querySelector('[data-security-audit-summary]');
    var metrics = root.querySelector('[data-security-audit-metrics]');
    var categories = root.querySelector('[data-security-audit-categories]');
    var checks = root.querySelector('[data-security-audit-checks]');
    var counts = data.counts || {};
    var status = data.status || 'warning';

    setTone(panel, status);
    if (summary) {
      summary.classList.remove('is-loading', 'is-healthy', 'is-warning', 'is-critical', 'is-info');
      summary.classList.add('is-' + status);
      summary.textContent = data.summary || 'Security hardening audit loaded.';
    }

    if (metrics) {
      metrics.replaceChildren(
        metricCard('Checks', Number(counts.checks || 0).toLocaleString(), 'Automated controls reviewed'),
        metricCard('Healthy', Number(counts.healthy || 0).toLocaleString(), 'Passing checks'),
        metricCard('Warnings', Number(counts.warning || 0).toLocaleString(), 'Needs review', counts.warning > 0 ? 'warning' : ''),
        metricCard('Critical', Number(counts.critical || 0).toLocaleString(), 'Needs action', counts.critical > 0 ? 'critical' : ''),
        metricCard('Categories', Number((data.categories || []).length).toLocaleString(), 'Runtime, DB, files, headers')
      );
    }

    if (categories) {
      categories.replaceChildren();
      (data.categories || []).forEach(function (item) {
        var row = node('article', 'mg-system-sql-row is-' + String(item.status || 'warning'));
        var copy = node('div');
        copy.append(node('strong', '', item.label || label(item.key)));
        copy.append(node('p', '', Number(item.healthy || 0).toLocaleString() + ' healthy · ' + Number(item.warning || 0).toLocaleString() + ' warning · ' + Number(item.critical || 0).toLocaleString() + ' critical'));
        copy.append(node('small', '', 'Automated hardening category'));
        row.append(copy, node('span', '', label(item.status)));
        categories.appendChild(row);
      });
      if (!categories.children.length) categories.appendChild(node('p', 'mg-muted', 'No security categories are available.'));
    }

    if (checks) {
      checks.replaceChildren();
      var important = (data.checks || []).filter(function (item) { return item.status === 'critical' || item.status === 'warning'; });
      if (!important.length) important = (data.checks || []).slice(0, 12);
      important.slice(0, 18).forEach(function (item) {
        var row = node('article', 'mg-system-sql-row is-' + String(item.status || 'warning'));
        var copy = node('div');
        var recs = item.recommendations || [];
        copy.append(node('strong', '', item.label || label(item.key)));
        copy.append(node('p', '', item.summary || 'Hardening check loaded.'));
        copy.append(node('small', '', label(item.category) + (recs.length ? ' · ' + recs[0] : '')));
        row.append(copy, node('span', '', label(item.status)));
        checks.appendChild(row);
      });
      if (!checks.children.length) {
        var empty = node('div', 'mg-system-health-empty');
        empty.append(node('strong', '', 'No hardening issues'), node('p', '', 'The automated hardening audit did not find critical or warning items.'));
        checks.appendChild(empty);
      }
    }

    if (button) {
      button.disabled = false;
      button.textContent = 'Run audit';
    }
  }

  async function load(event) {
    if (event) event.preventDefault();
    if (button) {
      button.disabled = true;
      button.textContent = 'Running…';
    }
    var summary = root.querySelector('[data-security-audit-summary]');
    if (summary) {
      summary.classList.remove('is-healthy', 'is-warning', 'is-critical', 'is-info');
      summary.classList.add('is-loading');
      summary.textContent = 'Running security hardening audit…';
    }
    try {
      var response = await MG.get('/api/admin/security-hardening-audit.php?_=' + Date.now());
      render(response.data || response);
    } catch (error) {
      render({ status: 'critical', summary: error.message || 'Unable to run security hardening audit.', counts: {}, categories: [], checks: [] });
      if (MG.toast) MG.toast(error.message || 'Unable to run security hardening audit.', 'error');
    }
  }

  if (button) button.addEventListener('click', load);
  setTimeout(load, 700);
});
