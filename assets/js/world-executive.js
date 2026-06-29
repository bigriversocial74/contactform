window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = document.querySelector('[data-world-executive]');
  if (!root || !MG) return;

  function qs(selector, scope) { return (scope || root).querySelector(selector); }
  function qsa(selector, scope) { return Array.from((scope || root).querySelectorAll(selector)); }
  function payload(response) { return response && response.data ? response.data : response; }
  function apiGet(path) {
    if (MG.get) return MG.get(path);
    return fetch(path, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); });
  }
  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
    });
  }
  function num(value) { return new Intl.NumberFormat().format(Number(value || 0)); }

  function setMetric(key, value) {
    qsa('[data-world-exec="' + key + '"]').forEach(function (node) { node.textContent = num(value); });
  }

  function stack(target, rows) {
    if (!target) return;
    target.innerHTML = rows.map(function (row) {
      return '<article><span>' + esc(row.label) + '</span><strong>' + esc(num(row.value)) + '</strong><p>' + esc(row.note || '') + '</p></article>';
    }).join('');
  }

  function renderTrend(rows) {
    var target = qs('[data-world-exec-trend]');
    if (!target) return;
    rows = Array.isArray(rows) ? rows : [];
    if (!rows.length) {
      target.innerHTML = '<p>No 24-hour trend data yet.</p>';
      return;
    }
    var max = Math.max.apply(null, rows.map(function (row) { return Number(row.total || 0); }).concat([1]));
    target.innerHTML = '<div class="mg-world-exec-bars">' + rows.map(function (row) {
      var height = Math.max(6, Math.round((Number(row.total || 0) / max) * 100));
      var label = String(row.bucket || '').slice(11, 16) || '—';
      return '<article><div style="height:' + height + '%"></div><span>' + esc(label) + '</span><em>' + esc(row.total || 0) + '</em></article>';
    }).join('') + '</div>';
  }

  function renderTable(target, rows, emptyText, columns) {
    if (!target) return;
    rows = Array.isArray(rows) ? rows : [];
    if (!rows.length) {
      target.innerHTML = '<p>' + esc(emptyText) + '</p>';
      return;
    }
    target.innerHTML = '<table><thead><tr>' + columns.map(function (column) { return '<th>' + esc(column.label) + '</th>'; }).join('') + '</tr></thead><tbody>' + rows.map(function (row) {
      return '<tr>' + columns.map(function (column) { return '<td>' + esc(column.format ? column.format(row[column.key], row) : row[column.key]) + '</td>'; }).join('') + '</tr>';
    }).join('') + '</tbody></table>';
  }

  function render(data) {
    var summary = data.summary || {};
    setMetric('executive_score', data.executive_score || 0);
    setMetric('active_customers', summary.active_customers || 0);
    setMetric('live_stores', summary.live_stores || 0);
    setMetric('geo_anchored_avatars', summary.geo_anchored_avatars || 0);
    setMetric('gifts_moving', summary.gifts_moving || 0);
    setMetric('claims_today', summary.claims_today || 0);
    setMetric('demand_pulse', summary.demand_pulse || 0);

    var drops = data.reward_drops || {};
    stack(qs('[data-world-exec-drops]'), [
      { label: 'Active drops', value: drops.active_drops || 0, note: 'Reward offers live in clusters.' },
      { label: 'Drop claims today', value: drops.drop_claims_today || 0, note: 'Avatar claims from reward drops.' },
      { label: 'Remaining inventory', value: drops.remaining || 0, note: 'Available rewards still in market.' },
      { label: 'Exhausted drops', value: drops.exhausted || 0, note: 'Drops that reached zero inventory.' }
    ]);

    var conversations = data.conversations || {};
    stack(qs('[data-world-exec-conversations]'), [
      { label: 'Active conversations', value: conversations.active_conversations || 0, note: 'Temporary avatar cluster chats.' },
      { label: 'Messages today', value: conversations.messages_today || 0, note: 'Social signal velocity.' },
      { label: 'Participants', value: conversations.participants || 0, note: 'Active conversation membership.' }
    ]);

    var opportunities = data.opportunities || {};
    stack(qs('[data-world-exec-opportunities]'), [
      { label: 'Open opportunities', value: opportunities.open || 0, note: 'Merchant actions waiting.' },
      { label: 'High priority', value: opportunities.high || 0, note: 'High/urgent opportunity cards.' },
      { label: 'Urgent', value: opportunities.urgent || 0, note: 'Immediate conversion opportunities.' },
      { label: 'Avg score', value: opportunities.score || 0, note: 'Average merchant opportunity score.' }
    ]);

    renderTrend(data.trend || []);
    renderTable(qs('[data-world-exec-merchants]'), data.top_merchants || [], 'No merchant activity yet.', [
      { key: 'merchant_name', label: 'Merchant' },
      { key: 'total_events', label: 'Events', format: num },
      { key: 'claims', label: 'Claims', format: num },
      { key: 'rewards', label: 'Rewards', format: num }
    ]);
    renderTable(qs('[data-world-exec-heat]'), data.heat_zones || [], 'No heat zones active yet.', [
      { key: 'merchant_name', label: 'Location' },
      { key: 'active_avatars', label: 'Avatars', format: num },
      { key: 'geo_anchors', label: 'Geo Anchors', format: num },
      { key: 'last_active_at', label: 'Last Active' }
    ]);
  }

  async function load() {
    try {
      var data = payload(await apiGet('/api/world-canvas/executive.php'));
      render(data || {});
    } catch (error) {
      var trend = qs('[data-world-exec-trend]');
      if (trend) trend.innerHTML = '<p>Unable to load executive dashboard.</p>';
    }
  }

  load();
  window.setInterval(load, 30000);
})(window, document);
