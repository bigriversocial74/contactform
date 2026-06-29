window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = document.querySelector('[data-merchant-canvas]');
  if (!root || !MG.get || !MG.post) return;

  var selectedSessionId = '';
  var customers = [];
  var pollTimer = null;
  var rewardOptions = { schema_ready: false, campaigns: [], templates: [], can_send_reward: false };
  var rewardOptionsLoaded = false;
  var drawer = root.querySelector('[data-canvas-drawer]');

  function portalDrawer() {
    if (!drawer || !document.body) return null;
    if (drawer.parentElement !== document.body) document.body.appendChild(drawer);
    drawer.setAttribute('data-canvas-drawer-portal', 'body');
    return drawer;
  }

  function qs(selector, scope) {
    if (scope) return scope.querySelector(selector);
    var node = root.querySelector(selector);
    if (node) return node;
    if (drawer && drawer.isConnected) return drawer.querySelector(selector);
    return document.querySelector(selector);
  }
  function qsa(selector, scope) { return Array.from((scope || root).querySelectorAll(selector)); }
  function payload(response) { return response && response.data ? response.data : response; }
  function clear(node) { if (node) node.replaceChildren(); }
  function setText(selector, value, scope) {
    qsa(selector, scope || document).forEach(function (node) { node.textContent = value == null ? '' : String(value); });
  }
  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
    });
  }
  function busy(button, value, text) {
    if (!button) return;
    if (MG.setBusy) return MG.setBusy(button, value, text);
    if (value) button.dataset.originalLabel = button.textContent;
    button.disabled = value;
    button.textContent = value ? (text || 'Working...') : (button.dataset.originalLabel || button.textContent);
  }
  function initials(name) {
    return String(name || 'C').split(/\s+/).filter(Boolean).slice(0, 2).map(function (part) { return part[0]; }).join('').toUpperCase() || 'C';
  }
  function formatDuration(seconds) {
    seconds = Math.max(0, Number(seconds || 0));
    if (seconds < 60) return String(seconds) + ' sec';
    var minutes = Math.floor(seconds / 60);
    if (minutes < 60) return String(minutes) + ' min';
    var hours = Math.floor(minutes / 60);
    return String(hours) + ' hr ' + String(minutes % 60) + ' min';
  }
  function formatDate(value) {
    if (!value) return '';
    var parsed = new Date(String(value).replace(' ', 'T') + (String(value).indexOf('T') === -1 ? 'Z' : ''));
    if (Number.isNaN(parsed.getTime())) return String(value);
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(parsed);
  }
  function formatTime(value) {
    if (!value) return '';
    var parsed = new Date(String(value).replace(' ', 'T') + (String(value).indexOf('T') === -1 ? 'Z' : ''));
    if (Number.isNaN(parsed.getTime())) return '';
    return new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' }).format(parsed);
  }
  function formatNumber(value) { return Number(value || 0).toLocaleString(); }
  function moneyLabel(cents, currency) {
    cents = Number(cents || 0);
    currency = currency || 'USD';
    if (!cents) return '';
    try { return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency }).format(cents / 100); }
    catch (error) { return '$' + (cents / 100).toFixed(2); }
  }
  function rewardValueLabel(template) {
    template = template || {};
    if (template.value_type === 'percent' && template.value_percent != null) return String(template.value_percent).replace(/\.0+$/, '') + '% off';
    if (template.value_amount_cents) return moneyLabel(template.value_amount_cents, template.currency || 'USD');
    return template.reward_type ? String(template.reward_type).replace(/_/g, ' ') : 'Reward';
  }
  function avatarHtml(customer) {
    var src = customer && customer.avatar_url ? String(customer.avatar_url) : '';
    if (src) return '<span class="mg-canvas-avatar"><img src="' + escapeHtml(src) + '" alt=""></span>';
    return '<span class="mg-canvas-avatar">' + escapeHtml(initials(customer && customer.name)) + '</span>';
  }

  function setLiveStatus(message, type) {
    var pill = qs('[data-canvas-live-pill]');
    if (!pill) return;
    pill.textContent = message;
    pill.classList.toggle('is-error', type === 'error');
    pill.classList.toggle('is-live', type === 'live');
    pill.classList.toggle('is-warn', type === 'warn');
  }

  function setCanvasState(message, type) {
    var state = qs('[data-canvas-state]');
    if (!state) return;
    state.textContent = message;
    state.classList.toggle('is-error', type === 'error');
    state.classList.toggle('is-live', type === 'live');
    state.classList.toggle('is-warn', type === 'warn');
  }

  function customerCard(item, index) {
    var customer = item.customer || {};
    var last = item.last_event && item.last_event.label ? item.last_event.label : 'Entered store';
    var source = item.source_post && item.source_post.headline ? item.source_post.headline : 'Merchant feed post';
    var offset = (index % 5) * 12;
    var classes = 'mg-canvas-avatar-card' + (item.status === 'idle' ? ' is-idle' : '') + (item.is_test ? ' is-test' : '') + (item.session_id === selectedSessionId ? ' is-active' : '');
    return '<button class="' + classes + '" type="button" data-session-id="' + escapeHtml(item.session_id) + '" style="margin-top:' + offset + 'px">' +
      '<span class="mg-canvas-avatar-status" aria-hidden="true"></span>' +
      avatarHtml(customer) +
      '<span class="mg-canvas-avatar-meta"><strong>' + escapeHtml(customer.name || 'Customer') + '</strong><span>Inside ' + escapeHtml(formatDuration(item.seconds_inside)) + '</span><small title="' + escapeHtml(source) + '">' + escapeHtml(last) + '</small></span>' +
      (item.is_test ? '<em>Test</em>' : '') +
      '</button>';
  }

  function activityItem(item) {
    var customer = item.customer || {};
    var eventLabel = item.last_event && item.last_event.label ? item.last_event.label : 'Inside store';
    return '<article class="mg-canvas-activity-item"><strong>' + escapeHtml(customer.name || 'Customer') + '</strong><span>' + escapeHtml(eventLabel) + ' - ' + escapeHtml(formatDuration(item.seconds_inside)) + '</span></article>';
  }

  function renderSummary(summary) {
    summary = summary || {};
    setText('[data-canvas-active-count]', formatNumber(summary.active_customers || customers.length));
    setText('[data-canvas-today-entries]', formatNumber(summary.today_entries));
    setText('[data-canvas-today-events]', formatNumber(summary.today_events));
    setText('[data-canvas-history-rows]', formatNumber(summary.history_rows));
    setText('[data-canvas-test-count]', formatNumber(summary.test_avatars));
  }

  function renderChatTabs() {
    var tabs = qs('[data-chat-tabs]');
    if (!tabs) return;
    clear(tabs);
    if (!customers.length) {
      tabs.innerHTML = '<button type="button" disabled>No active chats</button>';
      return;
    }
    customers.slice(0, 12).forEach(function (item, index) {
      var customer = item.customer || {};
      var label = customer.name || ('Visitor ' + (index + 1));
      var button = document.createElement('button');
      button.type = 'button';
      button.dataset.chatSessionId = item.session_id || '';
      button.className = item.session_id === selectedSessionId ? 'is-active' : '';
      button.innerHTML = '<span>' + escapeHtml(label) + '</span><small>×</small>';
      tabs.appendChild(button);
    });
    var add = document.createElement('button');
    add.type = 'button';
    add.disabled = true;
    add.textContent = '+';
    tabs.appendChild(add);
  }

  function renderCanvas(data) {
    customers = Array.isArray(data.customers) ? data.customers : [];
    var summary = data.summary || {};
    renderSummary(summary);
    renderChatTabs();
    setText('[data-canvas-agent-status]', summary.agent_status || 'Watching');
    setLiveStatus(customers.length ? 'Live customers inside' : 'Database connected', customers.length ? 'live' : '');
    setCanvasState(customers.length ? 'Canvas live: customer sessions are rendering from the active database.' : 'Database connected, waiting for customers. Use Add Test Avatar if you want to verify the visual layer now.', customers.length ? 'live' : '');

    var layer = qs('[data-canvas-customers]');
    if (layer) {
      clear(layer);
      layer.insertAdjacentHTML('beforeend', customers.map(customerCard).join(''));
    }
    var empty = qs('[data-canvas-empty]');
    if (empty) empty.classList.toggle('is-hidden', customers.length > 0);

    var activity = qs('[data-canvas-activity]');
    if (activity) {
      clear(activity);
      if (!customers.length) activity.innerHTML = '<p>Canvas activity will appear as customers enter, idle, message, claim, or leave.</p>';
      else activity.insertAdjacentHTML('beforeend', customers.slice(0, 8).map(activityItem).join(''));
    }
  }

  async function loadCanvas() {
    try {
      var data = payload(await MG.get('/api/merchant-canvas/active-users.php'));
      renderCanvas(data || {});
    } catch (error) {
      setLiveStatus(error.message || 'Unable to load canvas', 'error');
      setCanvasState(error.message || 'Unable to load canvas. Run diagnostics to confirm the active database and table status.', 'error');
    }
  }

  function healthRow(label, value, state) {
    return '<article class="mg-canvas-health-row' + (state ? ' is-' + escapeHtml(state) : '') + '"><span>' + escapeHtml(label) + '</span><strong>' + escapeHtml(value == null ? '—' : value) + '</strong></article>';
  }

  function renderHealth(data) {
    var health = qs('[data-canvas-health]');
    var status = qs('[data-canvas-health-status]');
    if (!health) return;
    var schema = data.schema || {};
    var stats = data.stats || {};
    var database = data.database || {};
    var tables = schema.tables || {};
    var html = '';
    html += '<section class="mg-canvas-health-section"><h3>Connection</h3>';
    html += healthRow('Active database', database.name || 'unknown', database.name ? 'ready' : 'warn');
    html += healthRow('Driver', database.driver || 'unknown', 'ready');
    html += healthRow('Merchant user', data.merchant && data.merchant.user_id ? data.merchant.user_id : 'unknown', 'ready');
    html += healthRow('Schema status', schema.ready ? 'ready' : 'missing', schema.ready ? 'ready' : 'error');
    html += '</section><section class="mg-canvas-health-section"><h3>Merchant activity</h3>';
    html += healthRow('Active customers', stats.active_customers, 'ready');
    html += healthRow('Today entries', stats.today_entries, 'ready');
    html += healthRow('Today events', stats.today_events, 'ready');
    html += healthRow('Store history rows', stats.history_rows, 'ready');
    html += healthRow('Test avatars', stats.test_avatars, stats.test_avatars ? 'warn' : 'ready');
    html += '</section><section class="mg-canvas-health-section mg-canvas-health-section-wide"><h3>Tables</h3>';
    Object.keys(tables).forEach(function (table) {
      var item = tables[table] || {};
      html += healthRow(table, (item.exists ? 'found' : 'missing') + (item.rows == null ? '' : ' · ' + formatNumber(item.rows) + ' rows'), item.exists ? 'ready' : 'error');
    });
    html += '</section>';
    health.innerHTML = html;
    if (status) {
      status.textContent = schema.ready ? 'Ready' : 'Missing tables';
      status.classList.toggle('is-error', !schema.ready);
    }
    setCanvasState(schema.ready ? 'Diagnostics passed. The Store Canvas tables are visible in the active database.' : 'Diagnostics found missing Store Canvas tables: ' + (schema.missing || []).join(', '), schema.ready ? 'live' : 'error');
  }

  async function loadHealth(openPanel) {
    var diagnostics = qs('[data-canvas-diagnostics]');
    var status = qs('[data-canvas-health-status]');
    if (openPanel && diagnostics) diagnostics.open = true;
    if (status) status.textContent = 'Checking...';
    try {
      var data = payload(await MG.get('/api/merchant-canvas/health.php'));
      renderHealth(data || {});
    } catch (error) {
      if (status) {
        status.textContent = 'Error';
        status.classList.add('is-error');
      }
      var health = qs('[data-canvas-health]');
      if (health) health.innerHTML = '<p class="mg-canvas-health-error">' + escapeHtml(error.message || 'Unable to run diagnostics.') + '</p>';
      setCanvasState(error.message || 'Unable to run diagnostics.', 'error');
    }
  }

  async function loadRewardOptions(force) {
    if (rewardOptionsLoaded && !force) return rewardOptions;
    try {
      rewardOptions = payload(await MG.get('/api/merchant-canvas/reward-options.php')) || rewardOptions;
      rewardOptions.campaigns = Array.isArray(rewardOptions.campaigns) ? rewardOptions.campaigns : [];
      rewardOptions.templates = Array.isArray(rewardOptions.templates) ? rewardOptions.templates : [];
      rewardOptionsLoaded = true;
    } catch (error) {
      rewardOptions = { schema_ready: false, campaigns: [], templates: [], can_send_reward: false, error: error.message || 'Reward options unavailable.' };
      rewardOptionsLoaded = true;
    }
    return rewardOptions;
  }

  function selectOptions(items, selectedId, labelFn) {
    return items.map(function (item) {
      return '<option value="' + escapeHtml(item.id) + '"' + (item.id === selectedId ? ' selected' : '') + '>' + escapeHtml(labelFn(item)) + '</option>';
    }).join('');
  }

  function renderRewardPanel(customer) {
    customer = customer || {};
    var isTest = customer.profile_type === 'test_customer' || customer.account_status === 'Test avatar';
    if (isTest) return '<section class="mg-canvas-reward-panel" data-reward-panel hidden><p class="mg-canvas-reward-note">Reward sending requires a real customer account. Test avatars are only for canvas visual testing.</p></section>';
    if (!rewardOptionsLoaded) return '<section class="mg-canvas-reward-panel" data-reward-panel hidden><p class="mg-canvas-reward-note">Loading reward options...</p></section>';
    if (!rewardOptions.schema_ready) return '<section class="mg-canvas-reward-panel" data-reward-panel hidden><p class="mg-canvas-reward-note is-error">Reward delivery setup is not ready. Run diagnostics and confirm campaign, wallet, and Store Canvas tables are installed.</p></section>';
    if (!rewardOptions.can_send_reward || !rewardOptions.campaigns.length || !rewardOptions.templates.length) return '<section class="mg-canvas-reward-panel" data-reward-panel hidden><p class="mg-canvas-reward-note">Create an active campaign and reward template before sending Store Canvas rewards.</p></section>';
    var defaultCampaign = rewardOptions.campaigns[0] || {};
    var defaultTemplateId = defaultCampaign.reward_template_id || (rewardOptions.templates[0] && rewardOptions.templates[0].id) || '';
    return '<section class="mg-canvas-reward-panel" data-reward-panel hidden>' +
      '<form data-reward-form>' +
        '<label>Campaign<select name="campaign_id" required>' + selectOptions(rewardOptions.campaigns, defaultCampaign.id, function (item) { return item.title + ' · ' + item.campaign_type; }) + '</select></label>' +
        '<label>Reward<select name="reward_template_id" required>' + selectOptions(rewardOptions.templates, defaultTemplateId, function (item) { return item.title + ' · ' + rewardValueLabel(item); }) + '</select></label>' +
        '<label>Expires<select name="expiration_days"><option value="">Use template rule</option><option value="7">7 days</option><option value="14">14 days</option><option value="30">30 days</option><option value="60">60 days</option></select></label>' +
        '<label>Note<textarea name="note" rows="3" maxlength="1000" placeholder="Optional customer note..."></textarea></label>' +
        '<button class="mg-btn mg-btn-primary" type="submit" data-reward-submit>Send Reward</button>' +
        '<p class="mg-canvas-form-status" data-reward-status role="status"></p>' +
      '</form>' +
    '</section>';
  }

  async function updateTestAvatar(action, button) {
    busy(button, true, action === 'clear' ? 'Clearing...' : 'Adding...');
    try {
      await MG.post('/api/merchant-canvas/test-avatar.php', { action: action });
      await loadCanvas();
      await loadHealth(false);
    } catch (error) {
      setCanvasState(error.message || 'Unable to update test avatars.', 'error');
    } finally {
      busy(button, false);
    }
  }

  function openDrawer() {
    var activeDrawer = portalDrawer();
    if (!activeDrawer) return;
    activeDrawer.classList.add('is-open');
    activeDrawer.setAttribute('aria-hidden', 'false');
  }
  function closeDrawer() {
    var activeDrawer = portalDrawer();
    if (!activeDrawer) return;
    activeDrawer.classList.remove('is-open');
    activeDrawer.setAttribute('aria-hidden', 'true');
  }

  function renderChatThread(customer, session, events) {
    var name = customer.name || 'Visitor';
    var entered = events[0] && events[0].created_at ? formatTime(events[0].created_at) : 'Now';
    var last = events[events.length - 1] || {};
    var lastLabel = last.label || last.type || 'Browsing the store';
    return '<section class="mg-canvas-chat-thread">' +
      '<div class="mg-canvas-chat-day">Today</div>' +
      '<article class="mg-canvas-chat-message">' + avatarHtml(customer) + '<div class="mg-canvas-chat-bubble-card"><header><strong>' + escapeHtml(name) + '</strong><span>' + escapeHtml(entered) + '</span></header><p>Hi, I am inside the store and looking at this offer.</p></div></article>' +
      '<article class="mg-canvas-chat-message is-agent"><div class="mg-canvas-chat-bubble-card"><header><strong>Merchant</strong><span>Auto</span></header><p>Welcome in. I can help with rewards, campaigns, gift offers, or loyalty questions.</p></div>' + avatarHtml({ name: 'Merchant' }) + '</article>' +
      '<article class="mg-canvas-chat-message"><span class="mg-canvas-avatar">' + escapeHtml(initials(name)) + '</span><div class="mg-canvas-chat-bubble-card"><header><strong>' + escapeHtml(name) + '</strong><span>' + escapeHtml(formatTime(last.created_at) || 'Now') + '</span></header><p>' + escapeHtml(lastLabel) + '</p></div></article>' +
    '</section>';
  }

  function renderCrm(data) {
    var customer = data.customer || {};
    var stats = data.stats || {};
    var session = data.session || {};
    var events = Array.isArray(data.events) ? data.events : [];
    var canOpenReward = rewardOptionsLoaded && rewardOptions.can_send_reward && customer.profile_type !== 'test_customer' && customer.account_status !== 'Test avatar';
    setText('[data-drawer-name]', customer.name || 'Visitor chat');
    renderChatTabs();
    var body = qs('[data-drawer-body]');
    if (body) {
      body.innerHTML =
        '<section class="mg-canvas-customer-summary">' + avatarHtml(customer) + '<div><strong>' + escapeHtml(customer.name || 'Customer') + '</strong><span>' + escapeHtml(customer.profile_type || 'customer') + ' · ' + escapeHtml(customer.account_status || 'In system') + '</span><small>Current status: ' + escapeHtml(session.status || 'active') + '</small></div></section>' +
        renderChatThread(customer, session, events) +
        '<section class="mg-canvas-crm-grid">' +
          '<article class="mg-canvas-crm-stat"><span>Visits</span><strong>' + Number(stats.visit_count || 0).toLocaleString() + '</strong></article>' +
          '<article class="mg-canvas-crm-stat"><span>Messages</span><strong>' + Number(stats.messages_sent || 0).toLocaleString() + '</strong></article>' +
          '<article class="mg-canvas-crm-stat"><span>Rewards</span><strong>' + Number(stats.rewards_received || 0).toLocaleString() + '</strong></article>' +
          '<article class="mg-canvas-crm-stat"><span>Claims</span><strong>' + Number(stats.rewards_claimed || 0).toLocaleString() + '</strong></article>' +
        '</section>' +
        '<section class="mg-canvas-action-grid"><button type="button" data-drawer-focus-message>Reply</button><button type="button" data-drawer-toggle-reward' + (canOpenReward ? '' : ' disabled') + '>Send Reward</button><button type="button" disabled>Add to Campaign</button><button type="button" disabled>Follow-Up</button></section>' +
        renderRewardPanel(customer) +
        '<section><span class="mg-canvas-eyebrow">Store source</span><p>' + escapeHtml(session.source_post && session.source_post.headline ? session.source_post.headline : 'Feed post / Store Canvas') + '</p></section>';
    }

    var form = qs('[data-message-form]');
    var message = form ? qs('[name="message"]', form) : null;
    var submit = qs('[data-message-submit]');
    if (message) message.disabled = false;
    if (submit) submit.disabled = false;
    var status = qs('[data-message-status]');
    if (status) {
      status.textContent = '';
      status.className = 'mg-canvas-form-status';
    }
  }

  async function loadCrm(sessionId) {
    selectedSessionId = String(sessionId || '');
    qsa('[data-session-id]').forEach(function (button) { button.classList.toggle('is-active', button.dataset.sessionId === selectedSessionId); });
    renderChatTabs();
    openDrawer();
    setText('[data-drawer-name]', 'Loading chat...');
    var drawerBody = qs('[data-drawer-body]');
    if (drawerBody) drawerBody.innerHTML = '<div class="mg-canvas-chat-empty"><strong>Loading chat...</strong><p>Pulling customer CRM and message context.</p></div>';
    try {
      await loadRewardOptions(false);
      var data = payload(await MG.get('/api/merchant-canvas/customer-crm.php?session_id=' + encodeURIComponent(selectedSessionId)));
      renderCrm(data || {});
    } catch (error) {
      if (drawerBody) drawerBody.innerHTML = '<p>' + escapeHtml(error.message || 'Unable to load customer CRM.') + '</p>';
    }
  }

  async function sendMessage(form) {
    if (!selectedSessionId) return;
    var input = form.elements.message;
    var body = String(input.value || '').trim();
    if (!body) return;
    var button = qs('[data-message-submit]', form);
    var status = qs('[data-message-status]', form);
    busy(button, true, 'Sending...');
    if (status) {
      status.className = 'mg-canvas-form-status';
      status.textContent = '';
    }
    try {
      await MG.post('/api/merchant-canvas/send-message.php', { session_id: selectedSessionId, message: body });
      input.value = '';
      if (status) {
        status.textContent = 'Message sent to customer IN/OUT Box.';
        status.className = 'mg-canvas-form-status is-success';
      }
      await loadCanvas();
      await loadCrm(selectedSessionId);
    } catch (error) {
      if (status) {
        status.textContent = error.message || 'Unable to send message.';
        status.className = 'mg-canvas-form-status is-error';
      }
    } finally {
      busy(button, false);
    }
  }

  async function sendReward(form) {
    if (!selectedSessionId || !form) return;
    var button = qs('[data-reward-submit]', form);
    var status = qs('[data-reward-status]', form);
    var data = {
      session_id: selectedSessionId,
      campaign_id: form.elements.campaign_id ? form.elements.campaign_id.value : '',
      reward_template_id: form.elements.reward_template_id ? form.elements.reward_template_id.value : '',
      expiration_days: form.elements.expiration_days ? form.elements.expiration_days.value : '',
      note: form.elements.note ? form.elements.note.value : '',
      idempotency_key: 'canvas-reward-' + selectedSessionId + '-' + Date.now()
    };
    busy(button, true, 'Sending...');
    if (status) {
      status.className = 'mg-canvas-form-status';
      status.textContent = '';
    }
    try {
      var result = payload(await MG.post('/api/merchant-canvas/send-reward.php', data));
      if (status) {
        status.textContent = (result && result.reward && result.reward.title ? result.reward.title + ' sent.' : 'Reward sent to customer IN/OUT Box.');
        status.className = 'mg-canvas-form-status is-success';
      }
      if (form.elements.note) form.elements.note.value = '';
      await loadCanvas();
      await loadCrm(selectedSessionId);
    } catch (error) {
      if (status) {
        status.textContent = error.message || 'Unable to send reward.';
        status.className = 'mg-canvas-form-status is-error';
      }
    } finally {
      busy(button, false);
    }
  }

  document.addEventListener('click', function (event) {
    var inRoot = root.contains(event.target);
    var inDrawer = drawer && drawer.contains(event.target);
    if (!inRoot && !inDrawer) return;

    var tab = event.target.closest('[data-chat-session-id]');
    if (tab && tab.dataset.chatSessionId) return void loadCrm(tab.dataset.chatSessionId);
    var refresh = event.target.closest('[data-canvas-refresh]');
    if (refresh) return void loadCanvas();
    var health = event.target.closest('[data-canvas-health-refresh]');
    if (health) return void loadHealth(true);
    var addTest = event.target.closest('[data-canvas-test-add]');
    if (addTest) return void updateTestAvatar('add', addTest);
    var clearTest = event.target.closest('[data-canvas-test-clear]');
    if (clearTest) return void updateTestAvatar('clear', clearTest);
    var focusMessage = event.target.closest('[data-drawer-focus-message]');
    if (focusMessage) {
      var messageInput = qs('[data-message-form] textarea');
      if (messageInput) messageInput.focus();
      return;
    }
    var toggleReward = event.target.closest('[data-drawer-toggle-reward]');
    if (toggleReward) {
      var panel = qs('[data-reward-panel]');
      if (panel) panel.hidden = !panel.hidden;
      return;
    }
    var close = event.target.closest('[data-drawer-close]');
    if (close) return closeDrawer();
    var avatar = inRoot ? event.target.closest('[data-session-id]') : null;
    if (avatar) return void loadCrm(avatar.datasetSessionId || avatar.dataset.sessionId);
  });

  document.addEventListener('submit', function (event) {
    var inRoot = root.contains(event.target);
    var inDrawer = drawer && drawer.contains(event.target);
    if (!inRoot && !inDrawer) return;
    var rewardForm = event.target.closest('[data-reward-form]');
    if (rewardForm) {
      event.preventDefault();
      sendReward(rewardForm);
      return;
    }
    var form = event.target.closest('[data-message-form]');
    if (!form) return;
    event.preventDefault();
    sendMessage(form);
  });

  portalDrawer();
  renderChatTabs();
  loadCanvas();
  loadHealth(false);
  loadRewardOptions(false);
  pollTimer = window.setInterval(loadCanvas, 7000);
  window.addEventListener('beforeunload', function () { if (pollTimer) window.clearInterval(pollTimer); });
})(window, document);
