window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';
  var MG = window.Microgifter;
  var root = document.querySelector('[data-intelligence-dashboard]');
  if (!root || !MG.get || !MG.post) return;

  var horizon = root.querySelector('[data-demand-horizon]');
  var locationFilter = root.querySelector('[data-demand-location]');
  var productFilter = root.querySelector('[data-demand-product]');
  var cohort = root.querySelector('[data-demand-cohort]');
  var loading = false;
  var optionsLoaded = false;

  function qs(selector) { return root.querySelector(selector); }
  function hide(node, value) { if (node) node.classList.toggle('mg-hidden', Boolean(value)); }
  function clear(node) { if (node) node.replaceChildren(); }
  function payload(response) { return response && response.data ? response.data : response; }
  function money(cents) { return new Intl.NumberFormat(undefined, { style:'currency', currency:'USD', maximumFractionDigits:0 }).format(Number(cents || 0) / 100); }
  function number(value) { return Number(value || 0).toLocaleString(); }
  function percent(value) { return (Number(value || 0) * 100).toFixed(1) + '%'; }
  function label(value) { return String(value || '').replace(/[_-]+/g, ' ').replace(/\b\w/g, function (letter) { return letter.toUpperCase(); }); }
  function busy(button, value, text) { if (MG.setBusy) MG.setBusy(button, value, text); else if (button) button.disabled = value; }

  function element(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = String(text);
    return node;
  }

  function metric(title, value, note) {
    var card = element('article', 'mg-kpi');
    card.append(element('span', '', title), element('strong', '', value), element('small', '', note));
    return card;
  }

  function renderKpis(totals, privacy) {
    var wrap = qs('[data-demand-kpis]'); clear(wrap);
    var suppressed = privacy && privacy.scope_suppressed;
    wrap.append(
      metric('Committed value', suppressed ? 'Suppressed' : money(totals.committed_value_cents), 'Outstanding prepaid demand'),
      metric('Realized value', suppressed ? 'Suppressed' : money(totals.realized_value_cents), 'Completed redemptions'),
      metric('Commitments', suppressed ? 'Suppressed' : number(totals.commitments), 'Purchased Microgifts in horizon'),
      metric('Purchasers', suppressed ? 'Below cohort' : number(totals.purchasers), 'Unique prepaid demand sources'),
      metric('Claimed', suppressed ? 'Suppressed' : number(totals.claimed), 'Near-term redemption demand'),
      metric('Redeemed', suppressed ? 'Suppressed' : number(totals.redeemed), 'Realized visits or fulfillment')
    );
  }

  function svgNode(name, attrs) {
    var node = document.createElementNS('http://www.w3.org/2000/svg', name);
    Object.keys(attrs || {}).forEach(function (key) { node.setAttribute(key, String(attrs[key])); });
    return node;
  }

  function renderChart(rows) {
    var wrap = qs('[data-demand-chart]'); clear(wrap);
    if (!rows.length) { wrap.appendChild(element('div', 'mg-empty', 'No prepaid demand in this window.')); return; }
    var visible = rows.filter(function (row) { return !row.suppressed; });
    if (!visible.length) { wrap.appendChild(element('div', 'mg-empty', 'Daily values are hidden because every cohort is below the privacy threshold.')); return; }
    var width = 900, height = 280, padX = 48, padY = 28;
    var max = Math.max.apply(null, visible.map(function (row) { return Math.max(Number(row.committed_value_cents || 0), Number(row.realized_value_cents || 0)); }).concat([1]));
    var svg = svgNode('svg', { viewBox:'0 0 ' + width + ' ' + height, role:'img', 'aria-label':'Committed and realized prepaid demand by expected date' });
    svg.appendChild(svgNode('line', { x1:padX, y1:height-padY, x2:width-padX, y2:height-padY, class:'mg-chart-axis' }));
    function points(key) {
      return visible.map(function (row, index) {
        var x = visible.length === 1 ? width / 2 : padX + index * ((width - padX * 2) / (visible.length - 1));
        var y = height - padY - (Number(row[key] || 0) / max) * (height - padY * 2);
        return x.toFixed(1) + ',' + y.toFixed(1);
      }).join(' ');
    }
    svg.appendChild(svgNode('polyline', { points:points('committed_value_cents'), class:'mg-chart-line is-committed' }));
    svg.appendChild(svgNode('polyline', { points:points('realized_value_cents'), class:'mg-chart-line is-realized' }));
    visible.forEach(function (row, index) {
      var x = visible.length === 1 ? width / 2 : padX + index * ((width - padX * 2) / (visible.length - 1));
      if (index === 0 || index === visible.length - 1 || index % Math.max(1, Math.floor(visible.length / 6)) === 0) {
        var text = svgNode('text', { x:x, y:height-7, class:'mg-chart-label', 'text-anchor':'middle' });
        text.textContent = String(row.date).slice(5);
        svg.appendChild(text);
      }
    });
    wrap.appendChild(svg);
    var legend = element('div', 'mg-chart-legend');
    legend.append(element('span', 'is-committed', 'Committed'), element('span', 'is-realized', 'Realized'));
    wrap.appendChild(legend);
  }

  function renderLifecycle(totals, privacy) {
    var wrap = qs('[data-demand-lifecycle]'); clear(wrap);
    var suppressed = privacy && privacy.scope_suppressed;
    [['Purchased',totals.purchased],['Sent',totals.sent],['Claimed',totals.claimed],['Redeemed',totals.redeemed],['Cancelled',totals.cancelled],['Refunded',totals.refunded],['Expired',totals.expired],['Replaced',totals.replaced]].forEach(function (item) {
      var card = element('div', 'mg-lifecycle-item'); card.append(element('strong', '', suppressed ? '—' : number(item[1])), element('span', '', item[0])); wrap.appendChild(card);
    });
  }

  function renderProducts(items) {
    var body = qs('[data-demand-products]'); clear(body);
    if (!items.length) {
      var row = document.createElement('tr'); var cell = document.createElement('td'); cell.colSpan = 6; cell.className = 'mg-empty'; cell.textContent = 'No product cohort meets the privacy threshold.'; row.appendChild(cell); body.appendChild(row); return;
    }
    items.forEach(function (item) {
      var row = document.createElement('tr');
      [item.title, number(item.commitments), money(item.committed_value_cents), money(item.realized_value_cents), number(item.claimed), number(item.redeemed)].forEach(function (value, index) {
        var cell = document.createElement('td'); if (index === 0) { var strong = document.createElement('strong'); strong.textContent = value; cell.appendChild(strong); } else cell.textContent = value; row.appendChild(cell);
      });
      body.appendChild(row);
    });
  }

  function renderLocations(items) {
    var wrap = qs('[data-demand-locations]'); clear(wrap);
    if (!items.length) { wrap.appendChild(element('div', 'mg-empty', 'No location cohort meets the privacy threshold.')); return; }
    items.forEach(function (item) {
      var row = element('div', 'mg-location-row'); var identity = element('div'); identity.append(element('strong', '', item.name), element('span', '', number(item.commitments) + ' commitments')); row.append(identity, element('b', '', money(item.committed_value_cents))); wrap.appendChild(row);
    });
  }

  function renderSignals(items) {
    var wrap = qs('[data-demand-signals]'); clear(wrap);
    if (!items.length) { wrap.appendChild(element('div', 'mg-empty', 'No open demand recommendations.')); return; }
    items.forEach(function (item) {
      var card = element('article', 'mg-signal-card is-' + item.level);
      var head = element('header'); var identity = element('div'); identity.append(element('span', '', label(item.level) + ' · Recommendation only'), element('h3', '', item.summary));
      var confidence = element('strong', '', Math.round(Number(item.confidence || 0) * 100) + '% confidence'); head.append(identity, confidence); card.appendChild(head);
      var details = element('p', '', 'Source: ' + label(item.source) + '. No action occurs automatically.'); card.appendChild(details);
      var recommendation = item.recommendation || {};
      if (recommendation.action) card.appendChild(element('div', 'mg-recommendation', 'Suggested action: ' + label(recommendation.action)));
      if (item.orchestration) {
        var orchestration = element('div', 'mg-orchestration-state');
        orchestration.append(element('strong', '', 'Agent handoff: ' + label(item.orchestration.status)), element('span', '', item.orchestration.requires_approval ? 'Approval required before execution.' : 'Subject to Stage 16 policy.'));
        card.appendChild(orchestration);
      } else card.appendChild(element('div', 'mg-orchestration-state', 'Not routed to an agent workflow.'));
      wrap.appendChild(card);
    });
  }

  function renderSnapshot(snapshot, stage4) {
    var wrap = qs('[data-demand-snapshot]'); clear(wrap);
    if (!snapshot && !(stage4 && stage4.intelligence) && !(stage4 && stage4.forecast && stage4.forecast.length)) {
      wrap.appendChild(element('div', 'mg-empty', 'No Stage 4F or Stage 15 forecast context exists for this scope and horizon.'));
      return;
    }
    var grid = element('dl', 'mg-snapshot-grid');
    if (snapshot) {
      [['Stage 15 snapshot',snapshot.snapshot_date],['Weighted score',money(snapshot.weighted_demand_score)],['7-day velocity',money(Number(snapshot.velocity_7d || 0))],['30-day velocity',money(Number(snapshot.velocity_30d || 0))],['Conversion',snapshot.conversion_rate === null ? 'Not available' : percent(snapshot.conversion_rate)],['Unique users',number(snapshot.unique_users)]].forEach(function (pair) {
        var div = element('div'); div.append(element('dt', '', pair[0]), element('dd', '', pair[1])); grid.appendChild(div);
      });
    }
    if (stage4 && stage4.intelligence) {
      var score = element('div'); score.append(element('dt', '', 'Stage 4F demand score'), element('dd', '', number(stage4.intelligence.demand_score))); grid.appendChild(score);
    }
    if (stage4 && Array.isArray(stage4.forecast) && stage4.forecast.length) {
      var latest = stage4.forecast[stage4.forecast.length - 1];
      var forecast = element('div'); forecast.append(element('dt', '', 'Latest forecast point'), element('dd', '', money(Number(latest.predicted_value || 0)))); grid.appendChild(forecast);
    }
    wrap.appendChild(grid);
  }

  function populateOptions(options) {
    if (optionsLoaded) return;
    (options.locations || []).forEach(function (item) { var option = document.createElement('option'); option.value = item.public_id; option.textContent = item.name; locationFilter.appendChild(option); });
    (options.products || []).forEach(function (item) { var option = document.createElement('option'); option.value = item.public_id; option.textContent = item.title; productFilter.appendChild(option); });
    optionsLoaded = true;
  }

  async function load() {
    if (loading) return;
    loading = true; hide(qs('[data-demand-error]'), true); hide(qs('[data-demand-signin]'), true); hide(qs('[data-demand-content]'), true); hide(qs('[data-demand-loading]'), false);
    qs('[data-demand-status]').textContent = 'Reconciling prepaid Microgifts and loading demand intelligence…';
    var path = '/api/merchant/committed-demand.php?horizon_days=' + encodeURIComponent(horizon.value) + '&location_id=' + encodeURIComponent(locationFilter.value) + '&product_id=' + encodeURIComponent(productFilter.value) + '&minimum_cohort_size=' + encodeURIComponent(cohort.value);
    var today = new Date();
    var from = new Date(today.getTime() - 30 * 86400000).toISOString().slice(0,10);
    var to = today.toISOString().slice(0,10);
    try {
      var responses = await Promise.all([
        MG.get(path),
        MG.get('/api/intelligence/overview.php?from=' + encodeURIComponent(from) + '&to=' + encodeURIComponent(to)).catch(function () { return null; }),
      ]);
      var data = payload(responses[0]);
      var stage4 = payload(responses[1]) || {};
      populateOptions(data.options || {});
      renderKpis(data.totals || {}, data.privacy || {}); renderChart(data.trend || []); renderLifecycle(data.totals || {}, data.privacy || {}); renderProducts(data.products || []); renderLocations(data.locations || []); renderSignals(data.signals || []); renderSnapshot(data.snapshot || null, stage4);
      qs('[data-demand-window]').textContent = data.window.start + ' through ' + data.window.end;
      qs('[data-demand-privacy]').textContent = 'Daily, grouped, and filtered scope values below ' + data.privacy.minimum_cohort_size + ' unique purchasers are suppressed. Customer identities are never exposed.';
      hide(qs('[data-demand-content]'), false); qs('[data-demand-status]').textContent = 'Committed demand intelligence loaded.';
    } catch (error) {
      if (error && (error.status === 401 || error.status === 403)) hide(qs('[data-demand-signin]'), false);
      else { hide(qs('[data-demand-error]'), false); qs('[data-demand-error-message]').textContent = error.message || 'Unable to load committed demand.'; }
      qs('[data-demand-status]').textContent = '';
    } finally { loading = false; hide(qs('[data-demand-loading]'), true); }
  }

  qs('[data-demand-refresh]').addEventListener('click', load);
  qs('[data-demand-retry]').addEventListener('click', load);
  qs('[data-run-forecast]').addEventListener('click', async function () {
    var button = this; busy(button, true, 'Running…');
    try { await MG.post('/api/intelligence/forecast.php', { model_key:'global_value_moving_average', as_of_date:new Date().toISOString().slice(0,10), horizon_days:30 }); if (MG.toast) MG.toast('Canonical forecast queued.', 'success'); await load(); }
    catch (error) { if (MG.toast) MG.toast(error.message || 'Unable to run forecast.', 'error'); }
    finally { busy(button, false); }
  });
  qs('[data-export-form]').addEventListener('submit', async function (event) {
    event.preventDefault(); var form = new FormData(this); var output = qs('[data-export-status]'); output.textContent = 'Queuing export…';
    try {
      var response = payload(await MG.post('/api/intelligence/exports.php', { export_type:'daily_facts', format:form.get('format'), privacy_mode:form.get('privacy_mode'), minimum_cohort_size:Number(cohort.value) }));
      output.textContent = 'Export ' + response.export_id + ' queued.';
    } catch (error) { output.textContent = error.message || 'Unable to queue export.'; }
  });
  load();
})(window, document);
