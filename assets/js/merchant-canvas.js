window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = document.querySelector('[data-merchant-canvas]');
  if (!root || !MG.get || !MG.post) return;

  var selectedSessionId = '';
  var customers = [];
  var pollTimer = null;

  function qs(selector, scope) { return (scope || root).querySelector(selector); }
  function qsa(selector, scope) { return Array.from((scope || root).querySelectorAll(selector)); }
  function payload(response) { return response && response.data ? response.data : response; }
  function clear(node) { if (node) node.replaceChildren(); }
  function setText(selector, value, scope) {
    var node = qs(selector, scope);
    if (node) node.textContent = value == null ? '' : String(value);
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
  function avatarHtml(customer) {
    var src = customer && customer.avatar_url ? String(customer.avatar_url) : '';
    if (src) return '<span class="mg-canvas-avatar"><img src="' + escapeHtml(src) + '" alt=""></span>';
    return '<span class="mg-canvas-avatar">' + escapeHtml(initials(customer && customer.name)) + '</span>';
  }

  function customerCard(item, index) {
    var customer = item.customer || {};
    var last = item.last_event && item.last_event.label ? item.last_event.label : 'Entered store';
    var source = item.source_post && item.source_post.headline ? item.source_post.headline : 'Merchant feed post';
    var offset = (index % 5) * 12;
    return '<button class="mg-canvas-avatar-card' + (item.status === 'idle' ? ' is-idle' : '') + (item.session_id === selectedSessionId ? ' is-active' : '') + '" type="button" data-session-id="' + escapeHtml(item.session_id) + '" style="margin-top:' + offset + 'px">' +
      '<span class="mg-canvas-avatar-status" aria-hidden="true"></span>' +
      avatarHtml(customer) +
      '<span class="mg-canvas-avatar-meta"><strong>' + escapeHtml(customer.name || 'Customer') + '</strong><span>Inside ' + escapeHtml(formatDuration(item.seconds_inside)) + '</span><small title="' + escapeHtml(source) + '">' + escapeHtml(last) + '</small></span>' +
      '</button>';
  }

  function activityItem(item) {
    var customer = item.customer || {};
    var eventLabel = item.last_event && item.last_event.label ? item.last_event.label : 'Inside store';
    return '<article class="mg-canvas-activity-item"><strong>' + escapeHtml(customer.name || 'Customer') + '</strong><span>' + escapeHtml(eventLabel) + ' - ' + escapeHtml(formatDuration(item.seconds_inside)) + '</span></article>';
  }

  function setLiveStatus(message, type) {
    var pill = qs('[data-canvas-live-pill]');
    if (!pill) return;
    pill.textContent = message;
    pill.classList.toggle('is-error', type === 'error');
  }

  function renderCanvas(data) {
    customers = Array.isArray(data.customers) ? data.customers : [];
    setText('[data-canvas-active-count]', String(customers.length));
    setText('[data-canvas-agent-status]', data.summary && data.summary.agent_status ? data.summary.agent_status : 'Watching');
    setLiveStatus(customers.length ? 'Live customers inside' : 'Polling every few seconds', '');

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
    }
  }

  function openDrawer() {
    var drawer = qs('[data-canvas-drawer]');
    if (!drawer) return;
    drawer.classList.add('is-open');
    drawer.setAttribute('aria-hidden', 'false');
  }
  function closeDrawer() {
    var drawer = qs('[data-canvas-drawer]');
    if (!drawer) return;
    drawer.classList.remove('is-open');
    drawer.setAttribute('aria-hidden', 'true');
  }

  function renderCrm(data) {
    var customer = data.customer || {};
    var stats = data.stats || {};
    var session = data.session || {};
    var events = Array.isArray(data.events) ? data.events : [];
    setText('[data-drawer-name]', customer.name || 'Customer CRM');
    var body = qs('[data-drawer-body]');
    if (body) {
      body.innerHTML =
        '<section class="mg-canvas-customer-summary">' + avatarHtml(customer) + '<div><strong>' + escapeHtml(customer.name || 'Customer') + '</strong><span>' + escapeHtml(customer.profile_type || 'customer') + ' - ' + escapeHtml(customer.account_status || 'In system') + '</span><small>Current status: ' + escapeHtml(session.status || 'active') + '</small></div></section>' +
        '<section class="mg-canvas-crm-grid">' +
          '<article class="mg-canvas-crm-stat"><span>Visits</span><strong>' + Number(stats.visit_count || 0).toLocaleString() + '</strong></article>' +
          '<article class="mg-canvas-crm-stat"><span>Messages</span><strong>' + Number(stats.messages_sent || 0).toLocaleString() + '</strong></article>' +
          '<article class="mg-canvas-crm-stat"><span>Rewards</span><strong>' + Number(stats.rewards_received || 0).toLocaleString() + '</strong></article>' +
          '<article class="mg-canvas-crm-stat"><span>Claims</span><strong>' + Number(stats.rewards_claimed || 0).toLocaleString() + '</strong></article>' +
        '</section>' +
        '<section><span class="mg-canvas-eyebrow">Store source</span><p>' + escapeHtml(session.source_post && session.source_post.headline ? session.source_post.headline : 'Feed post') + '</p></section>' +
        '<section><span class="mg-canvas-eyebrow">Session events</span><div class="mg-canvas-event-list">' + (events.length ? events.map(function (event) {
          return '<article><strong>' + escapeHtml(event.label || event.type || 'Store event') + '</strong><span>' + escapeHtml(formatDate(event.created_at)) + '</span></article>';
        }).join('') : '<article><strong>No events yet</strong><span>Customer has just entered the store.</span></article>') + '</div></section>';
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
    openDrawer();
    setText('[data-drawer-name]', 'Loading customer...');
    var drawerBody = qs('[data-drawer-body]');
    if (drawerBody) drawerBody.innerHTML = '<p>Loading CRM context...</p>';
    try {
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

  root.addEventListener('click', function (event) {
    var refresh = event.target.closest('[data-canvas-refresh]');
    if (refresh) return void loadCanvas();
    var close = event.target.closest('[data-drawer-close]');
    if (close) return closeDrawer();
    var avatar = event.target.closest('[data-session-id]');
    if (avatar) return void loadCrm(avatar.dataset.sessionId);
  });

  root.addEventListener('submit', function (event) {
    var form = event.target.closest('[data-message-form]');
    if (!form) return;
    event.preventDefault();
    sendMessage(form);
  });

  loadCanvas();
  pollTimer = window.setInterval(loadCanvas, 7000);
  window.addEventListener('beforeunload', function () { if (pollTimer) window.clearInterval(pollTimer); });
})(window, document);