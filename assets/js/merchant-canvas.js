window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';
  var MG = window.Microgifter;
  var root = document.querySelector('[data-merchant-canvas]');
  if (!root || !MG.get || !MG.post) return;
  var selectedSessionId = '', pollTimer = null, schemaReady = false;
  function qs(s, r) { return (r || root).querySelector(s); }
  function qsa(s, r) { return Array.from((r || root).querySelectorAll(s)); }
  function data(r) { return r && r.data ? r.data : r; }
  function esc(v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; }); }
  function text(s, v) { var n = qs(s); if (n) n.textContent = String(v == null ? '' : v); }
  function clear(n) { if (n) n.replaceChildren(); }
  function busy(b, on, label) { if (!b) return; if (MG.setBusy) return MG.setBusy(b, on, label); if (on) b.dataset.originalLabel = b.textContent; b.disabled = !!on; b.textContent = on ? (label || 'Working…') : (b.dataset.originalLabel || b.textContent); }
  function initials(name) { return String(name || 'C').split(/\s+/).filter(Boolean).slice(0, 2).map(function (p) { return p[0]; }).join('').toUpperCase() || 'C'; }
  function duration(seconds) { seconds = Math.max(0, Number(seconds || 0)); if (seconds < 60) return seconds + ' sec'; var m = Math.floor(seconds / 60); if (m < 60) return m + ' min'; var h = Math.floor(m / 60); return h + ' hr ' + (m % 60) + ' min'; }
  function fmt(value) { if (!value) return ''; var d = new Date(String(value).replace(' ', 'T') + (String(value).indexOf('T') === -1 ? 'Z' : '')); return Number.isNaN(d.getTime()) ? String(value) : new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(d); }
  function avatar(customer) { var src = customer && customer.avatar_url ? String(customer.avatar_url) : ''; return src ? '<span class="mg-canvas-avatar"><img src="' + esc(src) + '" alt=""></span>' : '<span class="mg-canvas-avatar">' + esc(initials(customer && customer.name)) + '</span>'; }
  function showSchema(status) {
    var panel = qs('[data-store-schema-panel]'), details = qs('[data-store-schema-details]');
    if (!panel) return;
    schemaReady = !!(status && status.schema_ready);
    panel.classList.toggle('is-ready', schemaReady);
    panel.classList.toggle('is-missing', !schemaReady);
    text('[data-store-schema-title]', schemaReady ? 'Stage 20 Store Canvas schema is ready' : 'Store Canvas schema needs setup');
    text('[data-store-schema-message]', status && status.guidance ? status.guidance : 'Unable to verify Store Canvas schema.');
    if (details) {
      var tables = status && Array.isArray(status.tables) ? status.tables : [];
      var missing = tables.filter(function (t) { return !t.installed || (t.missing_columns || []).length; });
      details.hidden = schemaReady && missing.length === 0;
      details.innerHTML = '<div class="mg-canvas-schema-actions"><code>' + esc((status && status.install_command) || 'mysql < database/stage_20_agent_store_canvas.sql') + '</code></div>' +
        '<div class="mg-canvas-schema-table-list">' + tables.map(function (t) {
          var ok = t.installed && !(t.missing_columns || []).length;
          return '<article class="' + (ok ? 'is-ok' : 'is-bad') + '"><strong>' + esc(t.name) + '</strong><span>' + (ok ? 'Ready' : (!t.installed ? 'Missing table' : 'Missing columns: ' + esc((t.missing_columns || []).join(', ')))) + '</span><small>' + (t.row_count === null ? '—' : Number(t.row_count || 0).toLocaleString() + ' rows') + '</small></article>';
        }).join('') + '</div>';
    }
  }
  async function checkSchema() { var response = data(await MG.get('/api/store/schema-status.php')) || {}; showSchema(response); return response.schema_ready === true; }
  function card(item, index) { var customer = item.customer || {}, last = item.last_event && item.last_event.label ? item.last_event.label : 'Entered store', source = item.source_post && item.source_post.headline ? item.source_post.headline : 'Merchant feed post', offset = (index % 5) * 12; return '<button class="mg-canvas-avatar-card' + (item.status === 'idle' ? ' is-idle' : '') + (item.session_id === selectedSessionId ? ' is-active' : '') + '" type="button" data-session-id="' + esc(item.session_id) + '" style="margin-top:' + offset + 'px"><span class="mg-canvas-avatar-status" aria-hidden="true"></span>' + avatar(customer) + '<span class="mg-canvas-avatar-meta"><strong>' + esc(customer.name || 'Customer') + '</strong><span>Inside ' + esc(duration(item.seconds_inside)) + '</span><small title="' + esc(source) + '">' + esc(last) + '</small></span></button>'; }
  function renderCanvas(payload) {
    var customers = Array.isArray(payload.customers) ? payload.customers : [];
    text('[data-canvas-active-count]', customers.length);
    text('[data-canvas-agent-status]', payload.summary && payload.summary.agent_status ? payload.summary.agent_status : 'Watching');
    text('[data-canvas-live-pill]', customers.length ? 'Live customers inside' : 'Polling every few seconds');
    var layer = qs('[data-canvas-customers]'); clear(layer); if (layer) layer.insertAdjacentHTML('beforeend', customers.map(card).join(''));
    var empty = qs('[data-canvas-empty]'); if (empty) empty.classList.toggle('is-hidden', customers.length > 0);
    var activity = qs('[data-canvas-activity]');
    if (activity) { clear(activity); activity.innerHTML = customers.length ? customers.slice(0, 8).map(function (item) { var c = item.customer || {}, label = item.last_event && item.last_event.label ? item.last_event.label : 'Inside store'; return '<article class="mg-canvas-activity-item"><strong>' + esc(c.name || 'Customer') + '</strong><span>' + esc(label) + ' · ' + esc(duration(item.seconds_inside)) + '</span></article>'; }).join('') : '<p>Canvas activity will appear as customers enter, idle, message, claim, reward, or leave.</p>'; }
  }
  async function loadCanvas() { try { if (!schemaReady) schemaReady = await checkSchema(); if (!schemaReady) { text('[data-canvas-live-pill]', 'Schema setup needed'); return; } renderCanvas(data(await MG.get('/api/merchant-canvas/active-users.php')) || {}); } catch (error) { text('[data-canvas-live-pill]', error.message || 'Unable to load canvas'); } }
  function openDrawer() { var d = qs('[data-canvas-drawer]'); if (d) { d.classList.add('is-open'); d.setAttribute('aria-hidden', 'false'); } }
  function closeDrawer() { var d = qs('[data-canvas-drawer]'); if (d) { d.classList.remove('is-open'); d.setAttribute('aria-hidden', 'true'); } }
  function renderCrm(payload) {
    var customer = payload.customer || {}, stats = payload.stats || {}, session = payload.session || {}, events = Array.isArray(payload.events) ? payload.events : [];
    text('[data-drawer-name]', customer.name || 'Customer CRM');
    var body = qs('[data-drawer-body]');
    if (body) body.innerHTML = '<section class="mg-canvas-customer-summary">' + avatar(customer) + '<div><strong>' + esc(customer.name || 'Customer') + '</strong><span>' + esc(customer.profile_type || 'customer') + ' · ' + esc(customer.account_status || 'In system') + '</span><small>Current status: ' + esc(session.status || 'active') + '</small></div></section><section class="mg-canvas-crm-grid"><article class="mg-canvas-crm-stat"><span>Visits</span><strong>' + Number(stats.visit_count || 0).toLocaleString() + '</strong></article><article class="mg-canvas-crm-stat"><span>Messages</span><strong>' + Number(stats.messages_sent || 0).toLocaleString() + '</strong></article><article class="mg-canvas-crm-stat"><span>Rewards</span><strong>' + Number(stats.rewards_received || 0).toLocaleString() + '</strong></article><article class="mg-canvas-crm-stat"><span>Claims</span><strong>' + Number(stats.rewards_claimed || 0).toLocaleString() + '</strong></article></section><section><span class="mg-canvas-eyebrow">Store source</span><p>' + esc(session.source_post && session.source_post.headline ? session.source_post.headline : 'Feed post') + '</p></section><section><span class="mg-canvas-eyebrow">Session events</span><div class="mg-canvas-event-list">' + (events.length ? events.map(function (e) { return '<article><strong>' + esc(e.label || e.type || 'Store event') + '</strong><span>' + esc(fmt(e.created_at)) + '</span></article>'; }).join('') : '<article><strong>No events yet</strong><span>Customer has just entered the store.</span></article>') + '</div></section>';
    var message = qs('[name="message"]', qs('[data-message-form]')), submit = qs('[data-message-submit]'), status = qs('[data-message-status]');
    if (message) message.disabled = false; if (submit) submit.disabled = false; if (status) { status.textContent = ''; status.className = 'mg-canvas-form-status'; }
  }
  async function loadCrm(id) { selectedSessionId = String(id || ''); qsa('[data-session-id]').forEach(function (b) { b.classList.toggle('is-active', b.dataset.sessionId === selectedSessionId); }); openDrawer(); text('[data-drawer-name]', 'Loading customer…'); var body = qs('[data-drawer-body]'); if (body) body.innerHTML = '<p>Loading CRM context…</p>'; try { renderCrm(data(await MG.get('/api/merchant-canvas/customer-crm.php?session_id=' + encodeURIComponent(selectedSessionId))) || {}); } catch (error) { if (body) body.innerHTML = '<p>' + esc(error.message || 'Unable to load customer CRM.') + '</p>'; } }
  async function sendMessage(form) {
    if (!selectedSessionId) return;
    var input = form.elements.message, body = String(input.value || '').trim(), button = qs('[data-message-submit]', form), status = qs('[data-message-status]', form);
    if (!body) return;
    busy(button, true, 'Sending…'); if (status) { status.className = 'mg-canvas-form-status'; status.textContent = 'Sending message…'; }
    try { var response = data(await MG.post('/api/merchant-canvas/send-message.php', { session_id: selectedSessionId, message: body })) || {}; input.value = ''; if (status) { status.textContent = response.thread_id ? 'Message delivered to customer Messages. Thread ' + response.thread_id : 'Message sent to customer Messages.'; status.className = 'mg-canvas-form-status is-success'; } await loadCanvas(); await loadCrm(selectedSessionId); } catch (error) { if (status) { status.textContent = error.message || 'Unable to send message.'; status.className = 'mg-canvas-form-status is-error'; } } finally { busy(button, false); }
  }
  root.addEventListener('click', function (event) { var schema = event.target.closest('[data-store-schema-refresh]'); if (schema) return void checkSchema().then(loadCanvas).catch(function (e) { showSchema({ schema_ready: false, guidance: e.message || 'Unable to inspect schema.', tables: [] }); }); var refresh = event.target.closest('[data-canvas-refresh]'); if (refresh) return void loadCanvas(); var close = event.target.closest('[data-drawer-close]'); if (close) return closeDrawer(); var avatar = event.target.closest('[data-session-id]'); if (avatar) return void loadCrm(avatar.datasetSessionId || avatar.dataset.sessionId); });
  root.addEventListener('submit', function (event) { var form = event.target.closest('[data-message-form]'); if (!form) return; event.preventDefault(); sendMessage(form); });
  checkSchema().then(loadCanvas).catch(loadCanvas);
  pollTimer = window.setInterval(loadCanvas, 7000);
  window.addEventListener('beforeunload', function () { if (pollTimer) window.clearInterval(pollTimer); });
})(window, document);
