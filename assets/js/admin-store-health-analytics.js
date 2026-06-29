window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';

  var root = document.querySelector('[data-admin-store-health-analytics]');
  if (!root) return;
  var endpoint = '/api/admin/store-health-analytics.php';

  function qs(selector, scope) { return (scope || document).querySelector(selector); }
  function esc(value) { return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) { return ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[c]; }); }
  function number(value) { return Number(value || 0) || 0; }
  function pct(value) { return String(value == null ? 0 : value) + '%'; }
  function time(value) { if (!value) return '—'; try { return new Date(String(value).replace(' ', 'T')).toLocaleString(); } catch (error) { return value; } }

  async function getJson(url) {
    if (window.Microgifter && window.Microgifter.get) return window.Microgifter.get(url);
    var res = await fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
    return res.json();
  }

  function unwrap(response) { return response && response.data ? response.data : response; }

  function setBanner(status, title, copy) {
    var banner = qs('[data-sha-banner]', root);
    if (!banner) return;
    banner.className = 'mg-admin-store-health-banner ' + status;
    banner.innerHTML = '<span></span><div><strong>' + esc(title) + '</strong><p>' + esc(copy) + '</p></div>';
  }

  function renderMetrics(summary) {
    var box = qs('[data-sha-metrics]', root);
    if (!box) return;
    var cards = [
      ['Recommended actions', summary.total, 'all tracked states'],
      ['Started', summary.started, pct(summary.start_rate)],
      ['Completed', summary.completed, pct(summary.completion_rate)],
      ['Snoozed', summary.snoozed, 'delayed'],
      ['Dismissed', summary.dismissed, pct(summary.dismiss_rate)],
      ['Active merchants', summary.active_merchants, 'with action history'],
      ['Completion rate', pct(summary.completion_rate), 'completed / total'],
      ['Dismiss rate', pct(summary.dismiss_rate), 'dismissed / total']
    ];
    box.innerHTML = cards.map(function (card) {
      return '<article><span>' + esc(card[0]) + '</span><strong>' + esc(card[1]) + '</strong><small>' + esc(card[2]) + '</small></article>';
    }).join('');
  }

  function renderImpactMetrics(impact) {
    var box = qs('[data-sha-impact-metrics]', root);
    if (!box) return;
    impact = impact || {};
    var rewards = impact.reward_outcomes || {};
    var invites = impact.invite_impact || {};
    var cards = [
      ['Rewards issued', rewards.issued || 0, 'wallet items'],
      ['Claims created', rewards.claimed || 0, pct(rewards.claim_rate || 0)],
      ['Redemptions', rewards.redeemed || 0, pct(rewards.redemption_rate || 0)],
      ['Claim rate', pct(rewards.claim_rate || 0), 'claimed / issued'],
      ['Redemption rate', pct(rewards.redemption_rate || 0), 'redeemed / claimed'],
      ['Active invites', invites.active_invites || 0, 'open reward invites'],
      ['Expired invites', invites.expired_invites || 0, 'missed windows'],
      ['Impact status', impact.ready ? 'Ready' : 'Limited', impact.ready ? 'commerce tables connected' : 'waiting for commerce tables']
    ];
    box.innerHTML = cards.map(function (card) {
      return '<article><span>' + esc(card[0]) + '</span><strong>' + esc(card[1]) + '</strong><small>' + esc(card[2]) + '</small></article>';
    }).join('');
  }

  function renderTable(target, headers, rows, emptyCopy) {
    var node = qs(target, root);
    if (!node) return;
    if (!rows.length) {
      node.innerHTML = '<div class="mg-admin-store-health-empty">' + esc(emptyCopy || 'No data yet.') + '</div>';
      return;
    }
    node.innerHTML = '<table><thead><tr>' + headers.map(function (h) { return '<th>' + esc(h) + '</th>'; }).join('') + '</tr></thead><tbody>' + rows.join('') + '</tbody></table>';
  }

  function renderTypes(rows) {
    renderTable('[data-sha-types]', ['Action', 'Total', 'Started', 'Completed', 'Snoozed', 'Dismissed', 'Rate'], rows.map(function (row) {
      return '<tr><td><strong>' + esc(row.action_type || 'action') + '</strong></td><td>' + esc(row.total) + '</td><td>' + esc(row.started) + '</td><td>' + esc(row.completed) + '</td><td>' + esc(row.snoozed) + '</td><td>' + esc(row.dismissed) + '</td><td>' + esc(pct(row.completion_rate)) + '</td></tr>';
    }), 'No Store Health action type data yet.');
  }

  function renderImpactTypes(rows) {
    renderTable('[data-sha-impact-types]', ['Action', 'Actions', 'Rewards', 'Claims', 'Redemptions', 'Claims/action', 'Redeems/action'], rows.map(function (row) {
      return '<tr><td><strong>' + esc(row.action_type || 'action') + '</strong></td><td>' + esc(row.actions) + '</td><td>' + esc(row.rewards) + '</td><td>' + esc(row.claims) + '</td><td>' + esc(row.redemptions) + '</td><td>' + esc(row.claims_per_action) + '</td><td>' + esc(row.redemptions_per_action) + '</td></tr>';
    }), 'No attributed business impact yet.');
  }

  function renderMerchants(rows) {
    renderTable('[data-sha-merchants]', ['Merchant', 'Total', 'Started', 'Completed', 'Dismissed', 'Rate', 'Last action'], rows.map(function (row) {
      return '<tr><td><strong>' + esc(row.merchant_name || ('Merchant #' + row.merchant_user_id)) + '</strong><small>ID ' + esc(row.merchant_user_id) + '</small></td><td>' + esc(row.total) + '</td><td>' + esc(row.started) + '</td><td>' + esc(row.completed) + '</td><td>' + esc(row.dismissed) + '</td><td>' + esc(pct(row.completion_rate)) + '</td><td>' + esc(time(row.last_action_at)) + '</td></tr>';
    }), 'No merchant action data yet.');
  }

  function renderImpactMerchants(rows) {
    renderTable('[data-sha-impact-merchants]', ['Merchant', 'Actions', 'Rewards', 'Claims', 'Redemptions', 'Last impact'], rows.map(function (row) {
      return '<tr><td><strong>' + esc(row.merchant_name || ('Merchant #' + row.merchant_user_id)) + '</strong><small>ID ' + esc(row.merchant_user_id) + '</small></td><td>' + esc(row.actions) + '</td><td>' + esc(row.rewards) + '</td><td>' + esc(row.claims) + '</td><td>' + esc(row.redemptions) + '</td><td>' + esc(time(row.last_impact_at)) + '</td></tr>';
    }), 'No merchant impact data yet.');
  }

  function renderDaily(rows) {
    var node = qs('[data-sha-daily]', root);
    if (!node) return;
    if (!rows.length) {
      node.innerHTML = '<div class="mg-admin-store-health-empty">No daily action movement yet.</div>';
      return;
    }
    var max = rows.reduce(function (m, row) { return Math.max(m, number(row.total)); }, 1);
    node.innerHTML = rows.map(function (row) {
      var width = Math.max(4, Math.round((number(row.total) / max) * 100));
      return '<article><div><strong>' + esc(row.date) + '</strong><span>' + esc(row.total) + ' total · ' + esc(row.completed) + ' completed · ' + esc(row.dismissed) + ' dismissed</span></div><em><i style="width:' + width + '%"></i></em></article>';
    }).join('');
  }

  function renderRecent(rows) {
    var node = qs('[data-sha-recent]', root);
    if (!node) return;
    if (!rows.length) {
      node.innerHTML = '<div class="mg-admin-store-health-empty">No recent Store Health action history yet.</div>';
      return;
    }
    node.innerHTML = rows.map(function (row) {
      return '<article class="is-' + esc(row.status) + '"><div><strong>' + esc(row.title || row.action_type) + '</strong><span>' + esc(row.merchant_name) + ' · ' + esc(row.action_type) + ' · ' + esc(row.condition_key) + '</span></div><b>' + esc(row.status) + '</b><small>' + esc(time(row.updated_at)) + '</small></article>';
    }).join('');
  }

  function renderImpactTimeline(rows) {
    var node = qs('[data-sha-impact-timeline]', root);
    if (!node) return;
    if (!rows.length) {
      node.innerHTML = '<div class="mg-admin-store-health-empty">No attributed action outcomes yet.</div>';
      return;
    }
    node.innerHTML = rows.map(function (row) {
      return '<article class="is-' + esc(row.status) + '"><div><strong>' + esc(row.title || row.action_type) + '</strong><span>' + esc(row.merchant_name) + ' · ' + esc(row.action_type) + ' → ' + esc(row.rewards) + ' rewards · ' + esc(row.claims) + ' claims · ' + esc(row.redemptions) + ' redemptions</span></div><b>' + esc(row.status) + '</b><small>Action ' + esc(time(row.action_at)) + ' · latest outcome ' + esc(time(row.latest_outcome_at)) + '</small></article>';
    }).join('');
  }

  async function load() {
    setBanner('is-loading', 'Loading Store Health analytics', 'Checking merchant action states and business impact attribution.');
    try {
      var data = unwrap(await getJson(endpoint));
      if (!data || data.ok === false) {
        setBanner('is-error', 'Store Health analytics unavailable', data && data.error ? data.error : 'The analytics endpoint did not return data.');
        return;
      }
      renderMetrics(data.summary || {});
      renderImpactMetrics(data.impact || {});
      renderTypes(data.top_types || []);
      renderImpactTypes((data.impact || {}).types || []);
      renderMerchants(data.merchants || []);
      renderImpactMerchants((data.impact || {}).merchants || []);
      renderDaily(data.daily || []);
      renderImpactTimeline((data.impact || {}).timeline || []);
      renderRecent(data.recent || []);
      qs('[data-sha-updated]', root).textContent = new Date().toLocaleString();
      setBanner('is-ready', 'Store Health impact ready', 'Recommendation engagement and downstream commerce outcomes are loaded.');
    } catch (error) {
      setBanner('is-error', 'Store Health analytics failed', error.message || 'Unable to load analytics.');
    }
  }

  var refresh = qs('[data-sha-refresh]', root);
  if (refresh) refresh.addEventListener('click', load);
  load();
})(window, document);
