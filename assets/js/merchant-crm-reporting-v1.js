document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var hero = document.querySelector('[data-crm-desktop-hero]');
  if (!hero || !window.Microgifter) return;

  var contacts = [];
  var reportLoaded = false;

  function number(value) { return Number(value || 0); }
  function text(selector, value) {
    var node = hero.querySelector(selector);
    if (node) node.textContent = String(value == null ? 0 : value);
  }
  function setWidth(selector, value) {
    var node = hero.querySelector(selector);
    if (node) node.style.width = Math.max(0, Math.min(100, number(value))) + '%';
  }
  function percent(part, whole) { return whole > 0 ? Math.round((part / whole) * 100) : 0; }
  function card(selector) {
    var node = hero.querySelector(selector);
    return node ? node.closest('.mg-crm-kpi') : null;
  }
  function trendText(trend) {
    if (!trend) return 'Current';
    var change = number(trend.change);
    return change === 0 ? 'No change' : ((change > 0 ? '+' : '') + change + ' vs prior');
  }
  function renderTrend(selector, trend, definition) {
    var container = card(selector);
    if (!container) return;
    var label = container.querySelector('.mg-crm-kpi-meta b');
    var detail = container.querySelector('.mg-crm-kpi-meta span');
    var line = container.querySelector('.mg-crm-kpi-spark path:not(.fill)');
    var fill = container.querySelector('.mg-crm-kpi-spark .fill');
    var direction = trend && trend.direction ? trend.direction : 'flat';
    if (label) label.textContent = trendText(trend);
    if (detail) detail.textContent = trend ? 'selected vs prior window' : 'current CRM state';
    container.dataset.trend = direction;
    if (definition) {
      container.title = definition;
      container.setAttribute('aria-description', definition);
    }
    var path = direction === 'up'
      ? 'M0 22 24 20 48 18 72 12 96 14 120 5'
      : (direction === 'down' ? 'M0 6 24 10 48 9 72 16 96 17 120 23' : 'M0 14 24 14 48 13 72 14 96 13 120 14');
    if (line) line.setAttribute('d', path);
    if (fill) fill.setAttribute('d', path + ' 120 28 0 28Z');
  }
  function pipelineStyles(pipeline) {
    var total = Math.max(1, number(pipeline.new) + number(pipeline.engaged) + number(pipeline.nurturing) + number(pipeline.ready) + number(pipeline.converted));
    ['new', 'engaged', 'nurturing', 'ready', 'converted'].forEach(function (key) {
      hero.style.setProperty('--pipe-' + key, Math.max(1, number(pipeline[key]) / total * 100) + 'fr');
    });
  }

  function render(report) {
    report = report || {};
    var metrics = report.metrics || {};
    var health = report.audience_health || {};
    var pipeline = report.pipeline || {};
    var trends = report.trends || {};
    var definitions = report.definitions || {};

    text('[data-crm-desktop-high]', metrics.high_intent);
    text('[data-crm-desktop-followup]', metrics.needs_followup);
    text('[data-crm-desktop-claims]', metrics.claims_redeems);
    text('[data-crm-desktop-messages]', metrics.messages);
    text('[data-crm-desktop-active]', metrics.active_conversations);
    text('[data-crm-desktop-verified]', metrics.verified_contacts);
    text('[data-crm-desktop-review]', metrics.review_queue);

    text('[data-crm-health-score]', health.score);
    text('[data-crm-health-verified]', number(health.verified_percent) + '%');
    text('[data-crm-health-engaged]', number(health.engaged_percent) + '%');
    text('[data-crm-health-responsive]', number(health.responsive_percent) + '%');
    text('[data-crm-health-intent]', number(health.high_intent_percent) + '%');
    setWidth('[data-crm-health-bar="verified"]', health.verified_percent);
    setWidth('[data-crm-health-bar="engaged"]', health.engaged_percent);
    setWidth('[data-crm-health-bar="responsive"]', health.responsive_percent);
    setWidth('[data-crm-health-bar="intent"]', health.high_intent_percent);

    var ring = hero.querySelector('[data-crm-health-ring]');
    if (ring) ring.style.setProperty('--health', Math.max(0, Math.min(100, number(health.score))));
    var status = hero.querySelector('[data-crm-health-status]');
    if (status) {
      status.textContent = health.status || 'Unavailable';
      status.dataset.health = number(health.score) >= 80 ? 'good' : (number(health.score) >= 60 ? 'developing' : 'attention');
    }

    pipelineStyles(pipeline);
    ['new', 'engaged', 'nurturing', 'ready', 'converted'].forEach(function (key) {
      text('[data-crm-pipeline-' + key + ']', number(pipeline[key]));
      text('[data-crm-pipeline-' + key + '-pct]', percent(number(pipeline[key]), number(metrics.total_contacts)) + '%');
    });
    text('[data-crm-conversion-rate]', number(report.conversion_rate) + '%');

    renderTrend('[data-crm-desktop-high]', null, definitions.high_intent);
    renderTrend('[data-crm-desktop-followup]', null, definitions.needs_followup);
    renderTrend('[data-crm-desktop-claims]', trends.claims_redeems, definitions.claims_redeems);
    renderTrend('[data-crm-desktop-messages]', trends.messages, definitions.messages);
    renderTrend('[data-crm-desktop-active]', trends.active_conversations, definitions.active_conversations);
    renderTrend('[data-crm-desktop-verified]', null, 'Active contacts with a verified Microgifter account email.');
    renderTrend('[data-crm-desktop-review]', null, definitions.review_queue);

    hero.dataset.reportingState = report.schema_ready === false ? 'unavailable' : 'ready';
    hero.dataset.reportingGeneratedAt = report.generated_at || '';
    reportLoaded = true;
  }

  function fallback(days) {
    var total = contacts.length;
    var metrics = contacts.reduce(function (acc, contact) {
      var stats = contact.crm_stats || {};
      var score = number(contact.crm_score || stats.score);
      var claimed = number(contact.claimed_count) + number(contact.redeemed_count);
      var issued = number(contact.issued_count || contact.wallet_count);
      if (score >= 75) acc.high_intent++;
      if (issued > claimed) acc.needs_followup++;
      if (contact.email_verified) acc.verified_contacts++;
      if (!contact.email_verified || !contact.has_account) acc.review_queue++;
      if (score >= 50) acc.responsive++;
      if (number(stats.messages || contact.message_count) > 0 || issued > 0 || claimed > 0) acc.engaged++;
      if (claimed > 0) acc.pipeline.converted++;
      else if (score >= 75) acc.pipeline.ready++;
      else if (issued > 0) acc.pipeline.nurturing++;
      else if (score >= 35) acc.pipeline.engaged++;
      else acc.pipeline.new++;
      return acc;
    }, { high_intent: 0, needs_followup: 0, claims_redeems: 0, messages: 0, active_conversations: 0, verified_contacts: 0, review_queue: 0, responsive: 0, engaged: 0, total_contacts: total, pipeline: { new: 0, engaged: 0, nurturing: 0, ready: 0, converted: 0 } });
    var verified = percent(metrics.verified_contacts, total);
    var engaged = percent(metrics.engaged, total);
    var responsive = percent(metrics.responsive, total);
    var high = percent(metrics.high_intent, total);
    var review = percent(metrics.review_queue, total);
    var score = total ? Math.round((verified * .35) + (engaged * .30) + (responsive * .20) + ((100 - review) * .15)) : 0;
    return {
      schema_ready: false,
      window_days: days,
      metrics: metrics,
      audience_health: { score: score, status: score >= 80 ? 'Good' : (score >= 60 ? 'Developing' : 'Needs attention'), verified_percent: verified, engaged_percent: engaged, responsive_percent: responsive, high_intent_percent: high },
      pipeline: metrics.pipeline,
      conversion_rate: percent(metrics.pipeline.converted, total),
      trends: {},
      definitions: {}
    };
  }

  async function load(days) {
    hero.dataset.reportingState = 'loading';
    try {
      var response = await Microgifter.get('/api/merchant/crm-reporting.php?days=' + encodeURIComponent(days));
      render(response.data || response);
    } catch (error) {
      render(fallback(days));
      hero.dataset.reportingState = 'fallback';
    }
  }

  document.addEventListener('mg:crm-contacts:rendered', function (event) {
    contacts = (event.detail && event.detail.contacts) || [];
    if (!reportLoaded) render(fallback(number(hero.dataset.rangeDays || 30)));
  });
  document.addEventListener('mg:crm-messages:refresh', function () {
    var range = hero.querySelector('[data-crm-desktop-range]');
    load(number((range || {}).value || 30));
  });

  var range = hero.querySelector('[data-crm-desktop-range]');
  if (range) range.addEventListener('change', function () {
    var days = number(range.value || 30);
    hero.setAttribute('data-range-days', String(days));
    load(days);
  });
  load(number((range || {}).value || 30));
});
