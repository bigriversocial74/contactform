window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = document.querySelector('[data-merchant-canvas]');
  if (!root || !MG || typeof MG.get !== 'function' || typeof MG.post !== 'function') return;

  var endpoint = '/api/merchant-canvas/trigger-engine.php';
  var state = { data: null, loading: false, installed: false, active: false, editingRule: null };

  function unwrap(response) { return response && response.data ? response.data : response; }
  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[character];
    });
  }
  function number(value) { return Number(value || 0).toLocaleString(); }
  function percent(value) { return Math.max(0, Math.min(100, Number(value || 0))).toFixed(1) + '%'; }
  function durationLabel(seconds) {
    seconds = Number(seconds || 0);
    if (seconds >= 86400 && seconds % 86400 === 0) return (seconds / 86400) + ' day' + (seconds === 86400 ? '' : 's');
    if (seconds >= 3600 && seconds % 3600 === 0) return (seconds / 3600) + ' hour' + (seconds === 3600 ? '' : 's');
    return Math.max(5, Math.round(seconds / 60)) + ' minutes';
  }
  function badge(text, warning) {
    return '<span class="mg-canvas-control-badge' + (warning ? ' is-warning' : '') + '">' + esc(text) + '</span>';
  }
  function drawer() { return document.querySelector('.mg-canvas-control-drawer'); }
  function body() { var node = drawer(); return node ? node.querySelector('[data-control-body]') : null; }
  function tabNav() { var node = drawer(); return node ? node.querySelector('[data-control-tabs]') : null; }

  function setStatus(message, type) {
    var node = body() ? body().querySelector('[data-engine-status]') : null;
    if (!node) return;
    node.textContent = message || '';
    node.className = 'mg-canvas-control-status' + (type ? ' is-' + type : '');
  }

  function summaryCard(label, value, detail) {
    return '<article><span>' + esc(label) + '</span><strong>' + esc(value) + '</strong><small>' + esc(detail || '') + '</small></article>';
  }

  function installTab() {
    var nav = tabNav();
    if (!nav || nav.querySelector('[data-control-tab="engine"]')) return;
    var button = document.createElement('button');
    button.type = 'button';
    button.setAttribute('data-control-tab', 'engine');
    button.setAttribute('data-trigger-engine-tab', '');
    button.textContent = 'Engine';
    nav.appendChild(button);
    state.installed = true;
  }

  function activate() {
    var node = drawer();
    if (!node) return;
    state.active = true;
    installTab();
    node.querySelectorAll('[data-control-tab]').forEach(function (button) {
      button.classList.toggle('is-active', button.hasAttribute('data-trigger-engine-tab'));
    });
    render();
    if (!state.data && !state.loading) load();
  }

  function deactivate() { state.active = false; }

  function campaignOptions(selected) {
    var campaigns = state.data && Array.isArray(state.data.campaigns) ? state.data.campaigns : [];
    return '<option value="">Select an active campaign</option>' + campaigns.map(function (campaign) {
      return '<option value="' + esc(campaign.id) + '"' + (String(campaign.id) === String(selected || '') ? ' selected' : '') + (campaign.ready ? '' : ' disabled') + '>' + esc(campaign.title + (campaign.ready ? '' : ' — not ready')) + '</option>';
    }).join('');
  }

  function zoneOptions(selected) {
    var zones = state.data && Array.isArray(state.data.zones) ? state.data.zones : [];
    return '<option value="">No visual zone context</option>' + zones.filter(function (zone) { return zone.status === 'active'; }).map(function (zone) {
      return '<option value="' + esc(zone.id) + '"' + (String(zone.id) === String(selected || '') ? ' selected' : '') + '>' + esc(zone.name || 'Campaign Zone') + '</option>';
    }).join('');
  }

  function eventOptions(selected) {
    var types = state.data && state.data.event_types ? state.data.event_types : {};
    return Object.keys(types).map(function (key) {
      return '<option value="' + esc(key) + '"' + (key === selected ? ' selected' : '') + '>' + esc(types[key]) + '</option>';
    }).join('');
  }

  function settingsHtml() {
    var settings = state.data.settings || {};
    var summary = state.data.summary || {};
    return '<section class="mg-canvas-control-card"><div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap"><div><h3>Server-authoritative trigger engine</h3><p>Server records—not browser avatar position—create events. Matching rules may send a campaign recommendation notification only. Campaign completion remains the sole reward authority.</p></div>' + badge(settings.execution_mode === 'notification' ? 'Notification mode' : settings.execution_mode === 'dry_run' ? 'Dry run' : 'Paused', settings.execution_mode === 'paused') + '</div>' +
      '<div class="mg-canvas-control-summary" style="margin-top:14px">' +
        summaryCard('Rules', number(summary.enabled_rules || 0), number(summary.rules || 0) + ' total') +
        summaryCard('Events', number(summary.events || 0), 'server normalized') +
        summaryCard('Matched', number(summary.matched || 0), 'dry-run matches') +
        summaryCard('Delivered', number(summary.delivered || 0), 'notifications') +
        summaryCard('Blocked', number(summary.blocked || 0), 'safeguards') +
        summaryCard('Errors', number(summary.errors || 0), 'review required') +
      '</div>' +
      '<form class="mg-canvas-control-form" data-engine-settings-form style="margin-top:14px"><div class="mg-canvas-control-grid">' +
        '<label>Execution mode<select name="execution_mode"><option value="paused"' + (settings.execution_mode === 'paused' ? ' selected' : '') + '>Paused</option><option value="dry_run"' + (settings.execution_mode === 'dry_run' ? ' selected' : '') + '>Dry run only</option><option value="notification"' + (settings.execution_mode === 'notification' ? ' selected' : '') + '>Notification delivery</option></select><small>Notification mode never issues a reward.</small></label>' +
        '<label>Max notifications per run<input type="number" min="1" max="100" name="max_notifications_per_run" value="' + esc(settings.max_notifications_per_run || 10) + '"></label>' +
        '<label>Default cooldown<select name="default_cooldown_seconds"><option value="900"' + (Number(settings.default_cooldown_seconds) === 900 ? ' selected' : '') + '>15 minutes</option><option value="3600"' + (Number(settings.default_cooldown_seconds) === 3600 ? ' selected' : '') + '>1 hour</option><option value="86400"' + (Number(settings.default_cooldown_seconds) === 86400 ? ' selected' : '') + '>1 day</option><option value="604800"' + (Number(settings.default_cooldown_seconds) === 604800 ? ' selected' : '') + '>7 days</option></select></label>' +
      '</div><div class="mg-canvas-control-actions" style="margin-top:12px"><button class="mg-canvas-control-action" type="submit">Save settings</button><button class="mg-canvas-control-action" type="button" data-engine-preview>Run dry preview</button><button class="mg-canvas-control-action is-primary" type="button" data-engine-run>Run server engine</button></div><p class="mg-canvas-control-status" data-engine-status>Last run: ' + esc(settings.last_run_status || 'never') + (settings.last_run_at ? ' · ' + esc(settings.last_run_at) : '') + '</p></form></section>';
  }

  function ruleFormHtml() {
    var rule = state.editingRule || {};
    var settings = state.data.settings || {};
    var eventType = rule.event_type || 'store_entry';
    return '<section class="mg-canvas-control-card"><div style="display:flex;justify-content:space-between;gap:12px;align-items:center"><div><h3>' + (rule.id ? 'Edit trigger rule' : 'Create trigger rule') + '</h3><p>Bind a verified server event to an existing active campaign and its attached active reward.</p></div>' + (rule.id ? '<button class="mg-canvas-control-action" type="button" data-engine-new-rule>New rule</button>' : '') + '</div>' +
      '<form class="mg-canvas-control-form" data-engine-rule-form><input type="hidden" name="rule_id" value="' + esc(rule.id || '') + '"><div class="mg-canvas-control-grid">' +
        '<label>Rule name<input name="name" maxlength="180" required value="' + esc(rule.name || '') + '" placeholder="Return visitor campaign"></label>' +
        '<label>Status<select name="status"><option value="paused"' + (rule.status !== 'enabled' ? ' selected' : '') + '>Paused</option><option value="enabled"' + (rule.status === 'enabled' ? ' selected' : '') + '>Enabled</option></select></label>' +
        '<label>Server event<select name="event_type" data-engine-event-type>' + eventOptions(eventType) + '</select></label>' +
        '<label>Campaign<select name="campaign_id" required>' + campaignOptions(rule.campaign_id) + '</select></label>' +
        '<label>Visual zone context<select name="trigger_zone_id">' + zoneOptions(rule.trigger_zone_id) + '</select><small>Context only. Zone overlap is never an event source.</small></label>' +
        '<label>Priority<select name="priority">' + [1,2,3,4,5].map(function (value) { return '<option value="' + value + '"' + (Number(rule.priority || 3) === value ? ' selected' : '') + '>' + value + '</option>'; }).join('') + '</select></label>' +
        '<label>Minimum probability<input name="minimum_probability" type="number" min="0" max="100" step="0.1" value="' + esc(rule.minimum_probability == null ? 50 : rule.minimum_probability) + '"></label>' +
        '<label>Minimum confidence<input name="minimum_confidence" type="number" min="0" max="100" step="0.1" value="' + esc(rule.minimum_confidence == null ? 30 : rule.minimum_confidence) + '"></label>' +
        '<label data-engine-milestone-row' + (eventType === 'visit_milestone' ? '' : ' hidden') + '>Visit milestone<input name="visit_milestone" type="number" min="2" max="1000" value="' + esc(rule.visit_milestone || 3) + '"></label>' +
        '<label>Cooldown<select name="cooldown_seconds"><option value="900"' + (Number(rule.cooldown_seconds || settings.default_cooldown_seconds) === 900 ? ' selected' : '') + '>15 minutes</option><option value="3600"' + (Number(rule.cooldown_seconds || settings.default_cooldown_seconds) === 3600 ? ' selected' : '') + '>1 hour</option><option value="86400"' + (Number(rule.cooldown_seconds || settings.default_cooldown_seconds || 86400) === 86400 ? ' selected' : '') + '>1 day</option><option value="604800"' + (Number(rule.cooldown_seconds || settings.default_cooldown_seconds) === 604800 ? ' selected' : '') + '>7 days</option></select></label>' +
        '<label>Per-customer daily limit<input name="max_per_customer_day" type="number" min="1" max="20" value="' + esc(rule.max_per_customer_day || 1) + '"></label>' +
      '</div><label>Notification note<textarea name="notification_note" maxlength="1000" rows="4" placeholder="Optional message shown with the campaign recommendation…">' + esc(rule.notification_note || '') + '</textarea></label><label class="mg-campaign-check"><input type="checkbox" name="require_active_session" value="1"' + (rule.require_active_session === false ? '' : ' checked') + '> <span>Require an active Store Canvas customer session</span></label><div class="mg-canvas-control-actions"><button class="mg-canvas-control-action is-primary" type="submit">Save rule</button></div></form></section>';
  }

  function rulesHtml() {
    var rules = Array.isArray(state.data.rules) ? state.data.rules : [];
    return '<section class="mg-canvas-control-card"><h3>Governed event rules</h3><p>Rules evaluate existing campaigns and reward inventory. They cannot create campaigns, issue rewards, or perform peer actions.</p><div class="mg-canvas-control-list" style="margin-top:12px">' + (rules.length ? rules.map(function (rule) {
      var detail = rule.event_label + ' · ' + rule.campaign_title + ' · ' + durationLabel(rule.cooldown_seconds);
      return '<article class="mg-canvas-control-row"><div><strong>' + esc(rule.name) + '</strong><span>' + esc(detail) + '</span><small>Probability ' + percent(rule.minimum_probability) + ' · Confidence ' + percent(rule.minimum_confidence) + (rule.trigger_zone_name ? ' · Zone: ' + esc(rule.trigger_zone_name) : '') + '</small></div><div>' + badge(rule.status, rule.status !== 'enabled') + '<button type="button" data-engine-edit-rule="' + esc(rule.id) + '">Edit</button><button type="button" data-engine-archive-rule="' + esc(rule.id) + '">Archive</button></div></article>';
    }).join('') : '<article class="mg-canvas-control-row"><div><strong>No trigger rules yet</strong><span>Create a paused rule, run a dry preview, then enable it when the evidence and limits look correct.</span></div></article>') + '</div></section>';
  }

  function recentHtml() {
    var recent = Array.isArray(state.data.recent) ? state.data.recent : [];
    return '<section class="mg-canvas-control-card"><h3>Recent engine decisions</h3><p>Every match, skip, block, delivery, and error is retained with an explanation.</p><div class="mg-canvas-control-list" style="margin-top:12px">' + (recent.length ? recent.slice(0, 30).map(function (item) {
      var warning = item.decision === 'blocked' || item.decision === 'error';
      return '<article class="mg-canvas-control-row"><div><strong>' + esc(item.rule_name || item.event_type) + '</strong><span>' + esc((item.campaign_title || 'Campaign') + ' · ' + item.reason_text) + '</span><small>' + esc(item.created_at || '') + ' · Probability ' + percent(item.probability) + ' · Confidence ' + percent(item.confidence) + '</small></div>' + badge(item.decision, warning) + '</article>';
    }).join('') : '<article class="mg-canvas-control-row"><div><strong>No evaluations yet</strong><span>Run a dry preview to create the first auditable decisions.</span></div></article>') + '</div></section>';
  }

  function authorityHtml() {
    return '<section class="mg-canvas-control-card"><h3>Execution authority</h3><div class="mg-canvas-control-list">' +
      '<article class="mg-canvas-control-row"><div><strong>Event source</strong><span>Store sessions, visit history, behavior profiles, and Wallet outcomes queried on the server.</span></div>' + badge('Server only') + '</article>' +
      '<article class="mg-canvas-control-row"><div><strong>Campaign and reward</strong><span>Existing active campaign with its existing attached active reward template.</span></div>' + badge('Canonical') + '</article>' +
      '<article class="mg-canvas-control-row"><div><strong>Customer delivery</strong><span>Clickable campaign recommendation notification with Do Not Message, cooldown, and idempotency enforcement.</span></div>' + badge('Notification only') + '</article>' +
      '<article class="mg-canvas-control-row"><div><strong>Reward lifecycle</strong><span>Campaign completion → Wallet → Inbox → PPPM. Trigger evaluation never writes a Wallet item.</span></div>' + badge('Separated') + '</article>' +
      '<article class="mg-canvas-control-row"><div><strong>Avatar and zone overlap</strong><span>Presentation only. Browser coordinates cannot create trigger events.</span></div>' + badge('Blocked') + '</article>' +
    '</div></section>';
  }

  function render() {
    if (!state.active) return;
    var target = body();
    if (!target) return;
    if (state.loading && !state.data) {
      target.innerHTML = '<section class="mg-canvas-control-card"><h3>Loading server trigger engine…</h3><p>Reading merchant settings, governed rules, eligible campaigns, server events, and recent decisions.</p></section>';
      return;
    }
    if (!state.data) {
      target.innerHTML = '<section class="mg-canvas-control-card"><h3>Trigger engine unavailable</h3><p>Unable to load the server trigger engine.</p><button class="mg-canvas-control-action" type="button" data-engine-retry>Retry</button></section>';
      return;
    }
    if (!state.data.schema_ready) {
      target.innerHTML = '<section class="mg-canvas-control-card"><h3>SQL import required</h3><p>Import <code>database/store_canvas_server_trigger_engine_v1.sql</code>. Missing tables: ' + esc((state.data.missing_tables || []).join(', ')) + '</p><button class="mg-canvas-control-action" type="button" data-engine-retry>Check again</button></section>' + authorityHtml();
      return;
    }
    target.innerHTML = settingsHtml() + ruleFormHtml() + rulesHtml() + recentHtml() + authorityHtml();
  }

  async function load() {
    state.loading = true;
    render();
    try {
      state.data = unwrap(await MG.get(endpoint)) || null;
    } catch (error) {
      state.data = null;
    } finally {
      state.loading = false;
      render();
    }
  }

  async function post(action, values, message) {
    var payload = Object.assign({ action: action }, values || {});
    setStatus(message || 'Working…', 'saving');
    try {
      var response = unwrap(await MG.post(endpoint, payload)) || {};
      if (response.payload) state.data = response.payload;
      setStatus((response.run && response.run.summary) ? ('Completed: ' + number(response.run.summary.evaluations) + ' evaluations · ' + number(response.run.summary.delivered) + ' delivered · ' + number(response.run.summary.blocked) + ' blocked') : 'Saved.', 'success');
      render();
      return response;
    } catch (error) {
      setStatus(error.message || 'Unable to process the trigger engine request.', 'error');
      throw error;
    }
  }

  function formValues(form) {
    var data = {};
    new FormData(form).forEach(function (value, key) { data[key] = value; });
    data.require_active_session = form.elements.require_active_session && form.elements.require_active_session.checked ? 1 : 0;
    return data;
  }

  document.addEventListener('click', function (event) {
    var engineTab = event.target.closest('[data-trigger-engine-tab]');
    if (engineTab) {
      event.preventDefault();
      event.stopPropagation();
      activate();
      return;
    }
    var regularTab = event.target.closest('.mg-canvas-control-drawer [data-control-tab]:not([data-trigger-engine-tab])');
    if (regularTab) deactivate();
    if (!state.active) return;

    if (event.target.closest('[data-engine-retry]')) { load(); return; }
    if (event.target.closest('[data-engine-preview]')) { post('preview', {}, 'Running server-side dry preview…'); return; }
    if (event.target.closest('[data-engine-run]')) {
      var mode = state.data && state.data.settings ? state.data.settings.execution_mode : 'paused';
      if (mode === 'paused') { setStatus('Save Dry Run or Notification mode before running the engine.', 'error'); return; }
      var confirmed = mode !== 'notification' || window.confirm('Run governed notification delivery now? Matching customers may receive one campaign recommendation notification. No rewards will be issued by this engine.');
      if (!confirmed) return;
      post('run', { confirm_notification_delivery: mode === 'notification' ? 1 : 0 }, 'Running server-authoritative evaluation…');
      return;
    }
    var edit = event.target.closest('[data-engine-edit-rule]');
    if (edit) {
      state.editingRule = (state.data.rules || []).find(function (rule) { return String(rule.id) === String(edit.getAttribute('data-engine-edit-rule')); }) || null;
      render();
      return;
    }
    if (event.target.closest('[data-engine-new-rule]')) { state.editingRule = null; render(); return; }
    var archive = event.target.closest('[data-engine-archive-rule]');
    if (archive) {
      if (!window.confirm('Archive this trigger rule? Existing evaluation history will remain.')) return;
      post('archive_rule', { rule_id: archive.getAttribute('data-engine-archive-rule') }, 'Archiving rule…').then(function () { state.editingRule = null; });
    }
  }, true);

  document.addEventListener('change', function (event) {
    if (!state.active || !event.target.matches('[data-engine-event-type]')) return;
    var row = body() ? body().querySelector('[data-engine-milestone-row]') : null;
    if (row) row.hidden = event.target.value !== 'visit_milestone';
  });

  document.addEventListener('submit', function (event) {
    var settingsForm = event.target.closest('[data-engine-settings-form]');
    if (settingsForm) {
      event.preventDefault();
      var values = {};
      new FormData(settingsForm).forEach(function (value, key) { values[key] = value; });
      post('save_settings', values, 'Saving engine settings…');
      return;
    }
    var ruleForm = event.target.closest('[data-engine-rule-form]');
    if (ruleForm) {
      event.preventDefault();
      post('save_rule', formValues(ruleForm), 'Saving governed trigger rule…').then(function (response) {
        state.editingRule = response.rule || null;
      });
    }
  });

  var observer = new MutationObserver(function () {
    installTab();
    if (state.active) {
      var tab = tabNav() ? tabNav().querySelector('[data-trigger-engine-tab]') : null;
      if (tab) tab.classList.add('is-active');
    }
  });
  observer.observe(document.body, { childList:true, subtree:true });
  installTab();
})(window, document);
