document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var root = document.querySelector('[data-ops-activity]');
  if (!root) return;
  var refresh = root.querySelector('[data-ops-activity-refresh]');
  var form = root.querySelector('[data-ops-activity-filters]');
  var summary = root.querySelector('[data-ops-activity-summary]');
  var status = root.querySelector('[data-ops-activity-status]');
  var list = root.querySelector('[data-ops-activity-list]');
  var MG = window.Microgifter || {};

  function make(tag, cls, text) {
    var node = document.createElement(tag);
    if (cls) node.className = cls;
    if (text !== undefined) node.textContent = String(text);
    return node;
  }
  function label(value) { return String(value || '').replace(/[_.-]+/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); }); }
  function num(value) { return Number(value || 0).toLocaleString(); }
  function fmtDate(value) { var date = new Date(String(value || '').replace(' ', 'T') + 'Z'); return Number.isNaN(date.getTime()) ? String(value || '—') : date.toLocaleString(); }
  function metaSummary(meta) {
    if (!meta || typeof meta !== 'object') return 'No metadata.';
    return Object.keys(meta).slice(0, 5).map(function (key) {
      var value = meta[key];
      if (value && typeof value === 'object') value = Array.isArray(value) ? '[' + value.length + ' items]' : '[object]';
      return label(key) + ': ' + String(value);
    }).join(' · ') || 'No metadata.';
  }
  function params() {
    var data = new FormData(form);
    var query = new URLSearchParams();
    ['q', 'category', 'days', 'limit'].forEach(function (key) {
      var value = String(data.get(key) || '').trim();
      if (value) query.set(key, value);
    });
    return query.toString();
  }
  async function get(path) {
    if (MG.get) return MG.get(path);
    var response = await fetch(path, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    var payload = await response.json().catch(function () { return null; });
    if (!response.ok || !payload || !payload.ok) throw new Error((payload && payload.message) || 'Request failed.');
    return payload;
  }
  function stat(title, value, detail) {
    var card = make('article');
    card.append(make('span', '', title), make('strong', '', value), make('small', '', detail || 'activity'));
    return card;
  }
  function renderSummary(data) {
    var s = data.summary || {};
    summary.replaceChildren(
      stat('Total events', num(s.total), 'selected window'),
      stat('Last 24h', num(s.last_24h), 'new activity'),
      stat('Incidents', num(s.incidents), 'incident actions'),
      stat('Forecasts', num(s.forecasts), 'risk views'),
      stat('Readiness', num(s.readiness), 'install checks'),
      stat('Actors', num(s.actors), 'unique admins')
    );
  }
  function renderList(items) {
    list.replaceChildren();
    if (!items || !items.length) {
      var empty = make('div', 'mg-ops-activity-empty');
      empty.append(make('strong', '', 'No ops activity found'), make('p', '', 'Adjust filters or widen the date window.'));
      list.appendChild(empty);
      return;
    }
    items.forEach(function (item) {
      var row = make('article', 'mg-ops-activity-item');
      var copy = make('div');
      var title = make('strong', '', label(item.action));
      var detail = make('p', '', metaSummary(item.metadata));
      var small = make('small', '', (item.actor ? item.actor.display_name : 'System') + ' · ' + item.entity_type + ' · ' + fmtDate(item.created_at));
      copy.append(title, detail, small);
      var badge = make('span', '', item.category || 'Admin ops');
      row.append(copy, badge);
      list.appendChild(row);
    });
  }
  async function load() {
    if (refresh) { refresh.disabled = true; refresh.textContent = 'Refreshing…'; }
    status.textContent = 'Loading ops activity…';
    try {
      var path = '/api/admin/ops-activity.php?' + params();
      var response = await get(path);
      var data = response.data || response;
      renderSummary(data);
      renderList(data.items || []);
      status.textContent = 'Loaded ' + num((data.items || []).length) + ' activity events.';
    } catch (error) {
      status.textContent = error.message || 'Unable to load ops activity.';
      list.replaceChildren();
    } finally {
      if (refresh) { refresh.disabled = false; refresh.textContent = 'Refresh'; }
    }
  }
  if (refresh) refresh.addEventListener('click', load);
  if (form) {
    form.addEventListener('submit', function (event) { event.preventDefault(); load(); });
    form.addEventListener('reset', function () { setTimeout(load, 0); });
  }
  load();
});
