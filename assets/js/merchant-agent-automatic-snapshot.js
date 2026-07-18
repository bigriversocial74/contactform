document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var root = document.querySelector('[data-merchant-agent-chat]');
  var panel = root && root.querySelector('[data-merchant-agent-latest-snapshot]');
  if (!root || !panel || !window.Microgifter) return;

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char];
    });
  }
  function data(response) { return response && response.data ? response.data : response; }
  function label(key) { return String(key || '').replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); }); }
  function number(value) { return Number(value || 0).toLocaleString(); }
  function date(value) { var parsed = Date.parse(value || ''); return parsed ? new Date(parsed).toLocaleString() : 'Not available'; }

  function render(snapshot) {
    var metrics = snapshot.metrics || {};
    var opportunities = Array.isArray(snapshot.opportunities) ? snapshot.opportunities : [];
    panel.classList.remove('is-loading', 'is-error');
    panel.innerHTML = '<div class="mg-merchant-latest-snapshot-head"><div><span>Automatic system snapshot</span><h2>' + esc(snapshot.title || 'Latest Merchant Snapshot') + '</h2><p>' + esc(snapshot.summary || '') + '</p></div><div><small>Updated ' + esc(date(snapshot.generated_at)) + '</small><button type="button" data-latest-snapshot-refresh>Refresh now</button></div></div>' +
      '<div class="mg-merchant-latest-snapshot-metrics">' + Object.keys(metrics).slice(0, 9).map(function (key) { return '<article><strong>' + esc(number(metrics[key])) + '</strong><span>' + esc(label(key)) + '</span></article>'; }).join('') + '</div>' +
      '<div class="mg-merchant-latest-snapshot-opportunities"><strong>What needs attention</strong>' + opportunities.slice(0, 4).map(function (item) { return '<article class="is-' + esc(item.priority || 'low') + '"><div><b>' + esc(item.title || 'Opportunity') + '</b><span>' + esc(item.action || '') + '</span></div>' + (Number(item.count || 0) ? '<strong>' + esc(number(item.count)) + '</strong>' : '') + '</article>'; }).join('') + '</div>' +
      '<div class="mg-merchant-latest-snapshot-foot"><span>System generated · No AI credits used</span><span>Window: last ' + esc(snapshot.window_days || 30) + ' days</span></div>';
  }

  async function load(force) {
    panel.classList.add('is-loading');
    panel.innerHTML = '<p>Preparing your latest merchant snapshot…</p>';
    try {
      var response = force ? await Microgifter.post('/api/ai/merchant-agent-snapshot.php', { days: 30 }) : await Microgifter.get('/api/ai/merchant-agent-snapshot.php?days=30');
      var payload = data(response);
      render(payload.snapshot || payload);
    } catch (error) {
      panel.classList.remove('is-loading');
      panel.classList.add('is-error');
      panel.innerHTML = '<strong>Latest snapshot unavailable</strong><p>' + esc((error && error.message) || 'Unable to prepare the system snapshot.') + '</p><button type="button" data-latest-snapshot-refresh>Try again</button>';
    }
  }

  panel.addEventListener('click', function (event) {
    if (event.target.closest('[data-latest-snapshot-refresh]')) load(true);
  });
  load(false);
});