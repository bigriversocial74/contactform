window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = document.querySelector('[data-merchant-canvas]');
  if (!root || !MG || typeof MG.get !== 'function' || typeof MG.post !== 'function') return;

  var endpoint = '/api/merchant-canvas/trigger-orchestration.php';
  var state = { data:null, loading:false, active:false, editingEventType:'', observerQueued:false };

  function unwrap(response) { return response && response.data ? response.data : response; }
  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[character];
    });
  }
  function num(value) { return Number(value || 0).toLocaleString(); }
  function pct(value) { return Math.max(0, Math.min(100, Number(value || 0))).toFixed(1) + '%'; }
  function duration(seconds) {
    seconds = Number(seconds || 0);
    if (seconds >= 604800 && seconds % 604800 === 0) return (seconds / 604800) + ' week' + (seconds === 604800 ? '' : 's');
    if (seconds >= 86400 && seconds % 86400 === 0) return (seconds / 86400) + ' day' + (seconds === 86400 ? '' : 's');
    if (seconds >= 3600 && seconds % 3600 === 0) return (seconds / 3600) + ' hour' + (seconds === 3600 ? '' : 's');
    return Math.max(0, Math.round(seconds / 60)) + ' minutes';
  }
  function badge(text, warning) {
    return '<span class="mg-canvas-control-badge' + (warning ? ' is-warning' : '') + '">' + esc(text) + '</span>';
  }
  function drawer() { return document.querySelector('.mg-canvas-control-drawer'); }
  function nav() { var node = drawer(); return node ? node.querySelector('[data-control-tabs]') : null; }
  function body() { var node = drawer(); return node ? node.querySelector('[data-control-body]') : null; }
  function activeMode() { return state.data && state.data.settings ? state.data.settings.execution_mode : 'paused'; }

  function installTab() {
    var tabs = nav();
    if (!tabs || tabs.querySelector('[data-trigger-orchestration-tab]')) return;
    var button = document.createElement('button');
    button.type = 'button';
    button.setAttribute('data-control-tab', 'orchestration');
    button.setAttribute('data-trigger-orchestration-tab', '');
    button.textContent = 'Orchestration';
    tabs.appendChild(button);
  }

  function activate(trigger) {
    state.active = true;
    installTab();
    var panel = drawer();
    if (!panel) return;
    panel.querySelectorAll('[data-control-tab]').forEach(function (button) {
      button.classList.toggle('is-active', button.hasAttribute('data-trigger-orchestration-tab'));
    });
    render();
    if (!state.data && !state.loading) load();
    if (trigger && typeof trigger.focus === 'function') trigger.focus();
  }

  function deactivate() { state.active = false; }

  function summaryCard(label, value, detail) {
    return '<article><span>' + esc(label) + '</span><strong>' + esc(value) + '</strong><small>' + esc(detail || '') + '</small></article>';
  }

  function settingsSection() {
    var settings = state.data.settings || {};
    var summary = state.data.summary || {};
    var schedulerStale = settings.last_scheduler_heartbeat_at && (Date.now() - new Date(settings.last_scheduler_heartbeat_at.replace(' ', 'T')).getTime()) > 7200000;
    return '<section class="mg-canvas-control-card" data-trigger-orchestration-root>' +
      '<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap"><div><h3>Event ingestion & customer interaction orchestration</h3><p>Canonical server activity is queued, evaluated against existing campaigns and rewards, and may deliver a governed campaign recommendation notification. Campaign completion remains the only reward authority.</p></div>' +
      badge(settings.emergency_pause ? 'Emergency paused' : (settings.scheduler_enabled ? 'Scheduler enabled' : 'Manual runs'), settings.emergency_pause || !settings.scheduler_enabled) + '</div>' +
      '<div class="mg-canvas-control-summary" style="margin-top:14px">' +
        summaryCard('Queued', num(summary.queued), 'canonical events') +
        summaryCard('Evaluated', num(summary.evaluated), 'completed queue') +
        summaryCard('Delivered', num(summary.delivered), 'notifications') +
        summaryCard('Blocked', num(summary.blocked), 'safeguards') +
        summaryCard('Retry', num(summary.retry), 'deferred') +
        summaryCard('Dead Letter', num(summary.dead_letter), 'review required') +
      '</div>' +
      '<form class="mg-canvas-control-form" data-orchestration-settings-form style="margin-top:14px"><div class="mg-canvas-control-grid">' +
        '<label>Timezone<input name="timezone" maxlength="64" value="' + esc(settings.timezone || 'UTC') + '" placeholder="America/Phoenix"></label>' +
        '<label>Quiet hours start<input type="time" name="quiet_hours_start" value="' + esc((settings.quiet_hours_start || '').slice(0,5)) + '"></label>' +
        '<label>Quiet hours end<input type="time" name="quiet_hours_end" value="' + esc((settings.quiet_hours_end || '').slice(0,5)) + '"></label>' +
        '<label class="mg-campaign-check"><input type="checkbox" name="ingestion_enabled" value="1"' + (settings.ingestion_enabled ? ' checked' : '') + '> <span>Enable canonical event ingestion</span></label>' +
        '<label class="mg-campaign-check"><input type="checkbox" name="scheduler_enabled" value="1"' + (settings.scheduler_enabled ? ' checked' : '') + '> <span>Allow the CLI scheduler to process this merchant</span></label>' +
        '<label class="mg-campaign-check"><input type="checkbox" name="emergency_pause" value="1"' + (settings.emergency_pause ? ' checked' : '') + '> <span>Global emergency pause for notification delivery</span></label>' +
      '</div><div class="mg-canvas-control-actions" style="margin-top:12px"><button class="mg-canvas-control-action" type="submit">Save orchestration settings</button><button class="mg-canvas-control-action" type="button" data-orchestration-ingest>Run ingestion</button><button class="mg-canvas-control-action" type="button" data-orchestration-preview>Dry preview queue</button><button class="mg-canvas-control-action is-primary" type="button" data-orchestration-run-full>Run ingestion + queue</button></div>' +
      '<p class="mg-canvas-control-status" data-orchestration-status>Last ingestion: ' + esc(settings.last_ingestion_status || 'never') + (settings.last_ingestion_at ? ' · ' + esc(settings.last_ingestion_at) : '') + ' · Scheduler: ' + (settings.scheduler_enabled ? (schedulerStale ? 'heartbeat stale' : (settings.last_scheduler_heartbeat_at || 'awaiting first run')) : 'disabled') + '</p></form></section>';
  }

  function sourceHealthSection() {
    var sources = Array.isArray(state.data.sources) ? state.data.sources : [];
    return '<section class="mg-canvas-control-card"><h3>Ingestion-source health</h3><p>Each adapter advances a durable cursor. Source failures do not rewind successful sources.</p><div class="mg-canvas-control-list" style="margin-top:12px">' +
      (sources.length ? sources.map(function (source) {
        var warning = source.health_status !== 'healthy';
        var detail = 'Ingested ' + num(source.ingested_count) + ' · Skipped ' + num(source.skipped_count);
        if (source.last_error_message) detail += ' · ' + source.last_error_message;
        return '<article class="mg-canvas-control-row"><div><strong>' + esc(source.label) + '</strong><span>' + esc(detail) + '</span><small>Last success: ' + esc(source.last_success_at || 'Never') + '</small></div>' + badge(source.health_status || 'never', warning) + '</article>';
      }).join('') : '<article class="mg-canvas-control-row"><div><strong>No source checkpoints yet</strong><span>Run ingestion to initialize source health.</span></div></article>') +
      '</div></section>';
  }

  function campaignOptions(selected) {
    var campaigns = Array.isArray(state.data.campaigns) ? state.data.campaigns : [];
    return '<option value="">Select an active campaign</option>' + campaigns.map(function (campaign) {
      return '<option value="' + esc(campaign.id) + '"' + (String(campaign.id) === String(selected || '') ? ' selected' : '') + (campaign.ready ? '' : ' disabled') + '>' + esc(campaign.title + (campaign.ready ? '' : ' — not ready')) + '</option>';
    }).join('');
  }

  function zoneOptions(selected) {
    var zones = Array.isArray(state.data.zones) ? state.data.zones : [];
    return '<option value="">No visual-zone context</option>' + zones.filter(function (zone) { return zone.status === 'active'; }).map(function (zone) {
      return '<option value="' + esc(zone.id) + '"' + (String(zone.id) === String(selected || '') ? ' selected' : '') + '>' + esc(zone.name || 'Campaign Zone') + '</option>';
    }).join('');
  }

  function eventOptions(selected) {
    var types = state.data.event_types || {};
    return Object.keys(types).map(function (key) {
      return '<option value="' + esc(key) + '"' + (key === selected ? ' selected' : '') + '>' + esc(types[key]) + '</option>';
    }).join('');
  }

  function matchingRule(eventType) {
    var rules = Array.isArray(state.data.rules) ? state.data.rules : [];
    return rules.find(function (rule) { return String(rule.event_type) === String(eventType || ''); }) || null;
  }

  function matchingPolicy(eventType) {
    var policies = Array.isArray(state.data.policies) ? state.data.policies : [];
    return policies.find(function (policy) { return String(policy.event_type) === String(eventType || ''); }) || null;
  }

  function editorSection() {
    var types = state.data.event_types || {};
    var eventType = state.editingEventType || Object.keys(types)[0] || 'store_entry';
    var rule = matchingRule(eventType) || {};
    var policy = matchingPolicy(eventType) || {};
    var settings = state.data.settings || {};
    var variants = policy.message_variants || {};
    return '<section class="mg-canvas-control-card"><div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap"><div><h3>Interaction policy & campaign rule</h3><p>Configure timing and copy around an existing active campaign. The policy does not create campaigns, rewards, direct messages, or Wallet items.</p></div>' + badge(rule.id ? 'Editing existing rule' : 'New rule', !rule.id) + '</div>' +
      '<form class="mg-canvas-control-form" data-orchestration-rule-form><input type="hidden" name="rule_id" value="' + esc(rule.id || '') + '"><div class="mg-canvas-control-grid">' +
        '<label>Server event<select name="event_type" data-orchestration-event-type>' + eventOptions(eventType) + '</select></label>' +
        '<label>Existing campaign<select name="campaign_id" required>' + campaignOptions(rule.campaign_id) + '</select></label>' +
        '<label>Rule name<input name="rule_name" maxlength="180" required value="' + esc(rule.name || '') + '" placeholder="Return visitor campaign"></label>' +
        '<label>Rule status<select name="rule_status"><option value="paused"' + (rule.status !== 'enabled' ? ' selected' : '') + '>Paused</option><option value="enabled"' + (rule.status === 'enabled' ? ' selected' : '') + '>Enabled</option></select></label>' +
        '<label>Policy name<input name="name" maxlength="180" value="' + esc(policy.name || '') + '" placeholder="Contextual customer follow-up"></label>' +
        '<label>Policy status<select name="status"><option value="paused"' + (policy.status !== 'enabled' ? ' selected' : '') + '>Paused</option><option value="enabled"' + (policy.status === 'enabled' ? ' selected' : '') + '>Enabled</option></select></label>' +
        '<label>Visual-zone context<select name="trigger_zone_id">' + zoneOptions(rule.trigger_zone_id) + '</select><small>Context only; overlap is never event authority.</small></label>' +
        '<label>Priority<select name="priority">' + [1,2,3,4,5].map(function (value) { return '<option value="' + value + '"' + (Number(rule.priority || 3) === value ? ' selected' : '') + '>' + value + '</option>'; }).join('') + '</select></label>' +
        '<label>Minimum probability<input name="minimum_probability" type="number" min="0" max="100" step="0.1" value="' + esc(rule.minimum_probability == null ? 50 : rule.minimum_probability) + '"></label>' +
        '<label>Minimum confidence<input name="minimum_confidence" type="number" min="0" max="100" step="0.1" value="' + esc(rule.minimum_confidence == null ? 30 : rule.minimum_confidence) + '"></label>' +
        '<label' + (eventType === 'visit_milestone' ? '' : ' hidden') + ' data-orchestration-milestone>Visit milestone<input name="visit_milestone" type="number" min="2" max="1000" value="' + esc(rule.visit_milestone || 3) + '"></label>' +
        '<label>Rule cooldown<select name="cooldown_seconds"><option value="900"' + (Number(rule.cooldown_seconds || settings.default_cooldown_seconds) === 900 ? ' selected' : '') + '>15 minutes</option><option value="3600"' + (Number(rule.cooldown_seconds || settings.default_cooldown_seconds) === 3600 ? ' selected' : '') + '>1 hour</option><option value="86400"' + (Number(rule.cooldown_seconds || settings.default_cooldown_seconds || 86400) === 86400 ? ' selected' : '') + '>1 day</option><option value="604800"' + (Number(rule.cooldown_seconds || settings.default_cooldown_seconds) === 604800 ? ' selected' : '') + '>7 days</option></select></label>' +
        '<label>Per-customer daily limit<input name="max_per_customer_day" type="number" min="1" max="20" value="' + esc(rule.max_per_customer_day || 1) + '"></label>' +
        '<label>Delivery delay<input name="delay_seconds" type="number" min="0" max="2592000" value="' + esc(policy.delay_seconds || 0) + '"><small>Seconds after ingestion before evaluation.</small></label>' +
        '<label>Retry delay<input name="retry_delay_seconds" type="number" min="300" max="604800" value="' + esc(policy.retry_delay_seconds || 900) + '"></label>' +
        '<label>Maximum attempts<input name="max_attempts" type="number" min="1" max="100" value="' + esc(policy.max_attempts || 12) + '"></label>' +
        '<label>Greeting mode<select name="greeting_mode"><option value="none"' + (policy.greeting_mode === 'none' ? ' selected' : '') + '>None</option><option value="first_visit"' + (policy.greeting_mode === 'first_visit' ? ' selected' : '') + '>First visit</option><option value="returning"' + (policy.greeting_mode === 'returning' ? ' selected' : '') + '>Returning customer</option><option value="contextual"' + (!policy.greeting_mode || policy.greeting_mode === 'contextual' ? ' selected' : '') + '>Contextual</option></select></label>' +
        '<label>Follow-up mode<select name="follow_up_mode"><option value="none"' + (policy.follow_up_mode === 'none' ? ' selected' : '') + '>None</option><option value="campaign_only"' + (!policy.follow_up_mode || policy.follow_up_mode === 'campaign_only' ? ' selected' : '') + '>Campaign only</option><option value="claim_aware"' + (policy.follow_up_mode === 'claim_aware' ? ' selected' : '') + '>Claim aware</option><option value="redemption_aware"' + (policy.follow_up_mode === 'redemption_aware' ? ' selected' : '') + '>Redemption aware</option></select></label>' +
        '<label>Release after<input name="release_after_seconds" type="number" min="300" max="2592000" value="' + esc(policy.release_after_seconds || 86400) + '"></label>' +
        '<label>Suppress after claim<input name="suppress_after_claim_seconds" type="number" min="0" max="2592000" value="' + esc(policy.suppress_after_claim_seconds == null ? 86400 : policy.suppress_after_claim_seconds) + '"></label>' +
        '<label>Suppress after redemption<input name="suppress_after_redeem_seconds" type="number" min="0" max="7776000" value="' + esc(policy.suppress_after_redeem_seconds == null ? 604800 : policy.suppress_after_redeem_seconds) + '"></label>' +
        '<label>Policy timezone<input name="timezone" maxlength="64" value="' + esc(policy.timezone || settings.timezone || 'UTC') + '"></label>' +
        '<label>Policy quiet start<input type="time" name="quiet_hours_start" value="' + esc((policy.quiet_hours_start || '').slice(0,5)) + '"></label>' +
        '<label>Policy quiet end<input type="time" name="quiet_hours_end" value="' + esc((policy.quiet_hours_end || '').slice(0,5)) + '"></label>' +
      '</div>' +
      '<label>Default notification copy<textarea name="variant_default" maxlength="1000" rows="3" placeholder="A campaign matched your recent store activity…">' + esc(variants.default || rule.notification_note || '') + '</textarea></label>' +
      '<div class="mg-canvas-control-grid"><label>First-visit variation<textarea name="variant_first_visit" maxlength="1000" rows="3">' + esc(variants.first_visit || '') + '</textarea></label><label>Returning-customer variation<textarea name="variant_returning" maxlength="1000" rows="3">' + esc(variants.returning || '') + '</textarea></label></div>' +
      '<label class="mg-campaign-check"><input type="checkbox" name="require_active_session" value="1"' + ((rule.require_active_session === false || policy.require_active_session === false) ? '' : ' checked') + '> <span>Require an active Store Canvas customer session before notification delivery</span></label>' +
      '<div class="mg-canvas-control-actions"><button class="mg-canvas-control-action is-primary" type="submit">Save policy + trigger rule</button></div></form></section>';
  }

  function policiesSection() {
    var policies = Array.isArray(state.data.policies) ? state.data.policies : [];
    return '<section class="mg-canvas-control-card"><h3>Orchestration policies</h3><div class="mg-canvas-control-list" style="margin-top:12px">' +
      (policies.length ? policies.map(function (policy) {
        return '<article class="mg-canvas-control-row"><div><strong>' + esc(policy.name) + '</strong><span>' + esc(policy.event_label + ' · Delay ' + duration(policy.delay_seconds) + ' · Retry ' + duration(policy.retry_delay_seconds)) + '</span><small>' + esc(policy.greeting_mode + ' greeting · ' + policy.follow_up_mode.replace(/_/g,' ') + ' follow-up') + '</small></div><div>' + badge(policy.status, policy.status !== 'enabled') + '<button type="button" data-orchestration-edit="' + esc(policy.event_type) + '">Edit</button></div></article>';
      }).join('') : '<article class="mg-canvas-control-row"><div><strong>No policies yet</strong><span>Create a paused policy and dry-preview it before enabling notification delivery.</span></div></article>') +
      '</div></section>';
  }

  function timelineSection() {
    var items = Array.isArray(state.data.timeline) ? state.data.timeline : [];
    return '<section class="mg-canvas-control-card"><h3>Customer interaction timeline</h3><p>Every queue and evaluation outcome includes its canonical source and explanation.</p><div class="mg-canvas-control-list" style="margin-top:12px">' +
      (items.length ? items.slice(0,50).map(function (item) {
        var decision = item.decision || item.event_status;
        var retry = ['retry','dead_letter','error'].indexOf(item.event_status) !== -1 ? '<button type="button" data-orchestration-retry="' + esc(item.event_id) + '">Requeue</button>' : '';
        var detail = item.source_type + ' · Customer ' + item.customer_user_id + (item.campaign_title ? ' · ' + item.campaign_title : '');
        var reason = item.reason_text || item.last_error_message || item.reason_code || item.event_status;
        return '<article class="mg-canvas-control-row"><div><strong>' + esc(item.event_label) + '</strong><span>' + esc(detail) + '</span><small>' + esc(reason) + ' · ' + esc(item.created_at || item.event_at || '') + '</small></div><div>' + badge(decision, ['blocked','retry','dead_letter','error'].indexOf(decision) !== -1) + retry + '</div></article>';
      }).join('') : '<article class="mg-canvas-control-row"><div><strong>No orchestrated events yet</strong><span>Run canonical ingestion to populate the queue.</span></div></article>') +
      '</div></section>';
  }

  function runsSection() {
    var runs = Array.isArray(state.data.runs) ? state.data.runs : [];
    return '<section class="mg-canvas-control-card"><h3>Scheduler and run history</h3><div class="mg-canvas-control-list" style="margin-top:12px">' +
      (runs.length ? runs.map(function (run) {
        var detail = num(run.events_queued) + ' queued · ' + num(run.events_evaluated) + ' evaluated · ' + num(run.notifications_delivered) + ' delivered';
        return '<article class="mg-canvas-control-row"><div><strong>' + esc(run.run_type + ' · ' + run.execution_mode) + '</strong><span>' + esc(detail) + '</span><small>' + esc(run.started_at || '') + (run.error_message ? ' · ' + esc(run.error_message) : '') + '</small></div>' + badge(run.status, run.status !== 'completed') + '</article>';
      }).join('') : '<article class="mg-canvas-control-row"><div><strong>No scheduler runs yet</strong><span>Manual and scheduled runs will appear here.</span></div></article>') +
      '</div></section>';
  }

  function securitySection() {
    var security = state.data.security || {};
    return '<section class="mg-canvas-control-card"><h3>Authority boundaries</h3><div class="mg-canvas-control-list">' +
      '<article class="mg-canvas-control-row"><div><strong>Canonical server records</strong><span>Store sessions, campaigns, Wallet lifecycle, and behavior profiles are ingestion authority.</span></div>' + badge(security.server_events_only ? 'Enforced' : 'Unavailable', !security.server_events_only) + '</article>' +
      '<article class="mg-canvas-control-row"><div><strong>Campaign recommendation only</strong><span>Orchestration may send a clickable campaign notification after all safeguards pass.</span></div>' + badge(security.notification_only ? 'Enforced' : 'Unavailable', !security.notification_only) + '</article>' +
      '<article class="mg-canvas-control-row"><div><strong>Reward lifecycle</strong><span>Campaign completion remains the only Wallet issuance authority.</span></div>' + badge(security.campaign_completion_reward_authority ? 'Canonical' : 'Unavailable', !security.campaign_completion_reward_authority) + '</article>' +
      '<article class="mg-canvas-control-row"><div><strong>Browser movement and overlap</strong><span>Visual avatar placement never creates an event or customer-impacting action.</span></div>' + badge(security.browser_overlap_authority ? 'Unsafe' : 'Blocked', !!security.browser_overlap_authority) + '</article>' +
      '</div></section>';
  }

  function render() {
    if (!state.active) return;
    var target = body();
    if (!target) return;
    if (state.loading && !state.data) {
      target.innerHTML = '<section class="mg-canvas-control-card" data-trigger-orchestration-root><h3>Loading ingestion and orchestration…</h3><p>Reading canonical-source health, queue status, policies, scheduler runs, and customer interaction history.</p></section>';
      return;
    }
    if (!state.data) {
      target.innerHTML = '<section class="mg-canvas-control-card" data-trigger-orchestration-root><h3>Ingestion and orchestration unavailable</h3><p>Unable to load the merchant orchestration workspace.</p><button class="mg-canvas-control-action" type="button" data-orchestration-reload>Retry</button></section>';
      return;
    }
    if (!state.data.schema_ready) {
      target.innerHTML = '<section class="mg-canvas-control-card" data-trigger-orchestration-root><h3>SQL migration required</h3><p>Import <code>database/trigger_event_ingestion_orchestration_v1.sql</code> to install source checkpoints, policies, scheduler runs, retries, and dead letters.</p><button class="mg-canvas-control-action" type="button" data-orchestration-reload>Check again</button></section>';
      return;
    }
    target.innerHTML = settingsSection() + sourceHealthSection() + editorSection() + policiesSection() + timelineSection() + runsSection() + securitySection();
  }

  async function load() {
    if (state.loading) return;
    state.loading = true;
    render();
    try {
      state.data = unwrap(await MG.get(endpoint)) || null;
    } catch (error) {
      state.data = null;
      if (state.active) {
        var target = body();
        if (target) target.innerHTML = '<section class="mg-canvas-control-card" data-trigger-orchestration-root><h3>Unable to load orchestration</h3><p>' + esc(error.message || 'Request failed.') + '</p><button class="mg-canvas-control-action" type="button" data-orchestration-reload>Retry</button></section>';
      }
    } finally {
      state.loading = false;
      render();
    }
  }

  function status(message, type) {
    var target = body();
    var node = target ? target.querySelector('[data-orchestration-status]') : null;
    if (!node) return;
    node.textContent = message || '';
    node.className = 'mg-canvas-control-status' + (type ? ' is-' + type : '');
  }

  async function post(action, values, message) {
    status(message || 'Working…', 'saving');
    try {
      var response = unwrap(await MG.post(endpoint, Object.assign({ action:action }, values || {}))) || {};
      if (response.payload) state.data = response.payload;
      render();
      status((response.message || 'Saved.'), 'success');
      return response;
    } catch (error) {
      status(error.message || 'Unable to complete the request.', 'error');
      throw error;
    }
  }

  function formObject(form) {
    var data = {};
    new FormData(form).forEach(function (value, key) { data[key] = value; });
    data.require_active_session = form.elements.require_active_session && form.elements.require_active_session.checked ? 1 : 0;
    data.message_variants = {
      default:String(data.variant_default || '').trim(),
      first_visit:String(data.variant_first_visit || '').trim(),
      returning:String(data.variant_returning || '').trim()
    };
    data.notification_note = data.message_variants.default;
    delete data.variant_default;
    delete data.variant_first_visit;
    delete data.variant_returning;
    return data;
  }

  document.addEventListener('click', function (event) {
    var tab = event.target.closest('[data-trigger-orchestration-tab]');
    if (tab) {
      event.preventDefault();
      event.stopPropagation();
      activate(tab);
      return;
    }
    var regular = event.target.closest('.mg-canvas-control-drawer [data-control-tab]:not([data-trigger-orchestration-tab])');
    if (regular) deactivate();
    if (!state.active) return;
    if (event.target.closest('[data-orchestration-reload]')) { load(); return; }
    if (event.target.closest('[data-orchestration-ingest]')) { post('run_ingestion', {}, 'Scanning canonical event sources…'); return; }
    if (event.target.closest('[data-orchestration-preview]')) { post('preview_queue', {}, 'Running queue dry preview…'); return; }
    if (event.target.closest('[data-orchestration-run-full]')) {
      var mode = activeMode();
      var confirmed = mode !== 'notification' || window.confirm('Run canonical ingestion and governed notification delivery now? Matching customers may receive campaign recommendation notifications. No rewards will be issued by orchestration.');
      if (!confirmed) return;
      post('run_full', { confirm_notification_delivery:mode === 'notification' ? 1 : 0 }, 'Running ingestion and orchestration…');
      return;
    }
    var edit = event.target.closest('[data-orchestration-edit]');
    if (edit) {
      state.editingEventType = edit.getAttribute('data-orchestration-edit') || '';
      render();
      var form = body() ? body().querySelector('[data-orchestration-rule-form]') : null;
      if (form) form.scrollIntoView({ block:'start', behavior:'smooth' });
      return;
    }
    var retry = event.target.closest('[data-orchestration-retry]');
    if (retry) {
      if (!window.confirm('Requeue this trigger event for another governed evaluation?')) return;
      post('retry_event', { event_id:retry.getAttribute('data-orchestration-retry') }, 'Requeueing event…');
    }
  }, true);

  document.addEventListener('change', function (event) {
    if (!state.active || !event.target.matches('[data-orchestration-event-type]')) return;
    state.editingEventType = event.target.value || '';
    render();
  });

  document.addEventListener('submit', function (event) {
    var settingsForm = event.target.closest('[data-orchestration-settings-form]');
    if (settingsForm) {
      event.preventDefault();
      var settings = {};
      new FormData(settingsForm).forEach(function (value, key) { settings[key] = value; });
      settings.ingestion_enabled = settingsForm.elements.ingestion_enabled.checked ? 1 : 0;
      settings.scheduler_enabled = settingsForm.elements.scheduler_enabled.checked ? 1 : 0;
      settings.emergency_pause = settingsForm.elements.emergency_pause.checked ? 1 : 0;
      post('save_settings', settings, 'Saving ingestion and orchestration settings…');
      return;
    }
    var ruleForm = event.target.closest('[data-orchestration-rule-form]');
    if (ruleForm) {
      event.preventDefault();
      post('save_policy_rule', formObject(ruleForm), 'Saving policy and trigger rule…').then(function (response) {
        var saved = response.saved || {};
        if (saved.rule) state.editingEventType = saved.rule.event_type || state.editingEventType;
      });
    }
  });

  var observer = new MutationObserver(function () {
    if (state.observerQueued) return;
    state.observerQueued = true;
    window.requestAnimationFrame(function () {
      state.observerQueued = false;
      installTab();
      if (state.active) {
        var tab = nav() ? nav().querySelector('[data-trigger-orchestration-tab]') : null;
        if (tab) tab.classList.add('is-active');
        var target = body();
        if (target && !target.querySelector('[data-trigger-orchestration-root]')) render();
      }
    });
  });
  observer.observe(document.body, { childList:true, subtree:true });
  installTab();
})(window, document);
