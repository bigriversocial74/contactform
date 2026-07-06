document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  if (!window.Microgifter) return;

  var host = document.querySelector('[data-merchant-crm-messages]');
  if (!host) return;

  var state = {
    threads: [],
    current: null,
    activeContactId: '',
    activeContactLabel: '',
    sending: false
  };

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character];
    });
  }

  function fmt(value) {
    if (!value) return '—';
    try { return new Date(String(value).replace(' ', 'T')).toLocaleString(); }
    catch (error) { return String(value); }
  }

  function qs(selector, root) { return (root || document).querySelector(selector); }
  function qsa(selector, root) { return Array.prototype.slice.call((root || document).querySelectorAll(selector)); }

  function busy(button, on, label) {
    if (!button) return;
    if (!button.dataset.originalText) button.dataset.originalText = button.textContent;
    button.disabled = !!on;
    button.setAttribute('aria-busy', on ? 'true' : 'false');
    button.textContent = on ? (label || 'Sending...') : button.dataset.originalText;
  }

  function statusNode(form) {
    var node = qs('[data-crm-message-status]', form) || qs('[data-crm-message-status]');
    if (!node) {
      node = document.createElement('div');
      node.className = 'mg-form-status';
      node.setAttribute('data-crm-message-status', '');
      if (form && typeof form.appendChild === 'function') form.appendChild(node);
    }
    if (node) {
      node.style.display = 'block';
      node.style.visibility = 'visible';
    }
    return node;
  }

  function setStatus(form, type, html) {
    var node = statusNode(form);
    if (!node) return;
    node.dataset.statusType = type;
    node.classList.remove('is-success', 'is-error', 'is-loading');
    node.classList.add(type === 'success' ? 'is-success' : (type === 'error' ? 'is-error' : 'is-loading'));
    node.innerHTML = html;
  }

  function threadCard(thread) {
    return '<article class="mg-product-card ' + (thread.unread ? 'is-unread' : '') + '" data-crm-thread="' + esc(thread.id) + '"><button type="button"><span><strong>' + esc(thread.contact_name || thread.contact_email || thread.subject || 'CRM conversation') + '</strong><span>' + esc(thread.campaign_title || 'CRM') + ' · ' + esc(thread.campaign_type || '') + '</span><small>' + esc(thread.latest_message || 'Open conversation') + ' · ' + esc(fmt(thread.latest_at)) + '</small></span><span class="mg-card-meta"><em>' + (thread.unread ? 'unread' : 'read') + '</em></span></button></article>';
  }

  function messageBubble(message) {
    return '<article class="mg-message-bubble ' + (message.mine ? 'is-mine' : '') + '"><strong>' + esc(message.sender_name || 'User') + '</strong><p>' + esc(message.body || '') + '</p><small>' + esc(fmt(message.created_at)) + '</small></article>';
  }

  function proofHtml(response, mode) {
    var data = response && response.data ? response.data : response;
    var message = data.message || {};
    var sent = mode === 'thread' || message.delivered_via === 'microgifter_thread';
    var chips = [];
    var thread = data.thread_id || message.thread_id || '';
    var messageId = data.message_id || message.message_id || '';

    if (thread) chips.push('Thread ' + thread);
    if (messageId) chips.push('Message ' + messageId);
    if (message.notification_id) chips.push('Notification ' + message.notification_id);
    if (message.recipient_user_id) chips.push('Customer user ' + message.recipient_user_id);
    if (message.duplicate) chips.push('Duplicate protected');
    if (message.email_delivery) chips.push('Email fallback queued');

    var links = thread ? '<div class="mg-crm-proof-actions"><a class="mg-btn mg-btn-soft" href="/merchant-crm.php?tab=messages&thread=' + encodeURIComponent(thread) + '">Open CRM thread</a><a class="mg-btn mg-btn-soft" href="/messages.php?thread=' + encodeURIComponent(thread) + '">Customer message URL</a></div>' : '';
    return '<div class="mg-crm-delivery-proof"><strong>' + (sent ? 'Message delivered to customer Messages.' : 'Message queued for email fallback.') + '</strong><p>' + esc(state.activeContactLabel || 'CRM contact') + '</p><div>' + chips.map(function (chip) { return '<span>' + esc(chip) + '</span>'; }).join('') + '</div>' + links + '</div>';
  }

  function setActiveContact(contactId, label) {
    state.activeContactId = contactId || state.activeContactId || '';
    state.activeContactLabel = label || state.activeContactLabel || '';

    qsa('[data-crm-message-modal], [data-customer-message-panel], [data-customer-messages-panel]').forEach(function (modal) {
      if (state.activeContactId) modal.dataset.activeContactId = state.activeContactId;
      if (state.activeContactLabel) modal.dataset.activeContactLabel = state.activeContactLabel;
    });
  }

  function rememberContactFromClick(target) {
    var row = target.closest && target.closest('tr[data-contact-id]');
    if (!row) return;
    var contactId = row.getAttribute('data-contact-id') || '';
    var name = row.querySelector('td:nth-child(2) strong');
    var email = row.getAttribute('data-contact-email') || '';
    setActiveContact(contactId, (name && name.textContent ? name.textContent.trim() : '') || email || contactId);
  }

  function normalizeUuid(value) {
    value = String(value || '').trim();
    return /^[0-9a-f-]{36}$/i.test(value) ? value.toLowerCase() : '';
  }

  function fieldValue(form, selectors) {
    for (var index = 0; index < selectors.length; index++) {
      var node = qs(selectors[index], form) || qs(selectors[index]);
      if (node && typeof node.value !== 'undefined' && String(node.value || '').trim()) return String(node.value || '').trim();
      if (node && node.dataset && node.dataset.value) return String(node.dataset.value || '').trim();
    }
    return '';
  }

  function closestDataValue(form, keys) {
    var node = form;
    while (node && node !== document) {
      if (node.dataset) {
        for (var index = 0; index < keys.length; index++) {
          if (node.dataset[keys[index]]) return String(node.dataset[keys[index]] || '').trim();
        }
      }
      node = node.parentNode;
    }
    return '';
  }

  function resolveContactId(form) {
    var modal = qs('[data-crm-message-modal]');
    return normalizeUuid(
      fieldValue(form, [
        '[name="contact_id"]',
        '[name="campaign_contact_id"]',
        '[data-crm-message-contact-id]',
        '[data-campaign-contact-id]'
      ]) ||
      closestDataValue(form, ['activeContactId', 'contactId', 'campaignContactId']) ||
      state.activeContactId ||
      (modal && modal.dataset.activeContactId) ||
      ''
    );
  }

  function resolveThreadId(form) {
    return normalizeUuid(
      fieldValue(form, [
        '[name="thread_id"]',
        '[name="thread"]',
        '[data-crm-thread-id]',
        '[data-thread-id]'
      ]) ||
      closestDataValue(form, ['threadId', 'crmThreadId']) ||
      ''
    );
  }

  function bodyElement(form) {
    return qs('[data-crm-message-body]', form) ||
      qs('[data-customer-message-body]', form) ||
      qs('textarea[name="message"]', form) ||
      qs('textarea[name="body"]', form) ||
      qs('textarea', form);
  }

  function sendButton(form) {
    return qs('[data-crm-message-submit]', form) ||
      qs('[data-customer-message-submit]', form) ||
      qs('[data-customer-message-send]', form) ||
      qs('button[type="submit"]', form) ||
      qsa('button', form).find(function (button) { return /send\s+message/i.test(button.textContent || ''); });
  }

  function formFromSendButton(button) {
    return button.closest('[data-crm-message-form], [data-customer-message-form], [data-crm-customer-message-form], form') ||
      button.closest('[data-crm-message-modal], [data-customer-message-panel], [data-customer-messages-panel], .mg-crm-modal-panel, .mg-crm-drawer-panel');
  }

  function isMessageSendButton(target) {
    var button = target && target.closest ? target.closest('[data-crm-message-submit], [data-customer-message-submit], [data-customer-message-send], button') : null;
    if (!button) return null;
    if (button.matches('[data-crm-message-submit], [data-customer-message-submit], [data-customer-message-send]')) return button;
    if (!/send\s+message/i.test(button.textContent || '')) return null;
    return formFromSendButton(button) ? button : null;
  }

  async function sendDirectMessage(event, explicitForm) {
    var form = explicitForm || (event.target && event.target.closest ? event.target.closest('[data-crm-message-form], [data-customer-message-form], [data-crm-customer-message-form], form') : null);
    if (!form) return;

    event.preventDefault();
    event.stopPropagation();
    if (event.stopImmediatePropagation) event.stopImmediatePropagation();

    if (state.sending) return;

    var threadId = resolveThreadId(form);
    var contactId = resolveContactId(form);
    var bodyNode = bodyElement(form);
    var body = bodyNode ? bodyNode.value.trim() : '';
    var button = sendButton(form);
    var mode = threadId ? 'thread' : 'contact';

    if (!threadId && !contactId) {
      setStatus(form, 'error', '<strong>Message not sent.</strong><br>Missing CRM contact ID or thread ID. Close this panel, reopen the contact row, and try again.');
      return;
    }
    if (!body) {
      setStatus(form, 'error', '<strong>Message not sent.</strong><br>Write a message before sending.');
      if (bodyNode) bodyNode.focus();
      return;
    }

    setStatus(form, 'loading', '<strong>Sending message...</strong><br>Creating the CRM thread, participant row, message row, and notification.');
    busy(button, true, 'Sending...');
    state.sending = true;

    try {
      var payload = threadId ? {
        thread_id: threadId,
        body: body
      } : {
        contact_id: contactId,
        message: body,
        idempotency_key: 'crm-message-ui:' + contactId + ':' + Date.now()
      };
      var endpoint = threadId ? '/api/merchant/crm-messages.php' : '/api/merchant/crm-message.php';
      var response = await Microgifter.post(endpoint, payload);
      var data = response.data || response;
      var message = data.message || {};
      var proofThread = data.thread_id || message.thread_id || threadId;
      var proofMessage = data.message_id || message.message_id || '';

      if (!proofThread || !proofMessage) throw new Error('Message endpoint returned without thread/message proof.');

      if (bodyNode) bodyNode.value = '';
      setStatus(form, 'success', proofHtml(response, mode));
      document.dispatchEvent(new CustomEvent('mg:crm-messages:refresh', { detail: { thread_id: proofThread } }));
      document.dispatchEvent(new CustomEvent('mg:notifications:refresh'));
      if (Microgifter.toast) Microgifter.toast(mode === 'thread' || message.delivered_via === 'microgifter_thread' ? 'Message delivered to customer Messages.' : 'Message queued for email fallback.');
    } catch (error) {
      setStatus(form, 'error', '<strong>Message failed.</strong><br>' + esc(error.message || 'Unable to send CRM message.') + '<br><small>Endpoint: ' + (threadId ? '/api/merchant/crm-messages.php' : '/api/merchant/crm-message.php') + '</small>');
    } finally {
      state.sending = false;
      busy(button, false);
    }
  }

  function renderThreads() {
    var list = host.querySelector('[data-crm-message-thread-list]');
    if (!list) return;
    list.innerHTML = state.threads.length ? state.threads.map(threadCard).join('') : '<div class="mg-empty-state"><strong>No CRM messages yet</strong><p>Merchant/customer conversations started from CRM contacts will appear here.</p></div>';
  }

  async function loadThreads() {
    var response = await Microgifter.get('/api/merchant/crm-messages.php?limit=100');
    var data = response.data || response;
    state.threads = data.threads || [];
    var badge = host.querySelector('[data-crm-message-unread]');
    if (badge) badge.textContent = Number(data.unread_count || 0).toLocaleString();
    renderThreads();
    if (window.Microgifter.setMessageCount) document.dispatchEvent(new CustomEvent('mg:messages:refresh'));
    return state.threads;
  }

  async function openThread(id) {
    state.current = id;
    var detail = host.querySelector('[data-crm-message-detail]');
    if (detail) detail.innerHTML = '<div class="mg-empty-state"><strong>Loading conversation…</strong></div>';
    var response = await Microgifter.get('/api/merchant/crm-messages.php?thread=' + encodeURIComponent(id));
    var thread = (response.data || response).thread || {};
    if (detail) {
      detail.innerHTML = '<div class="mg-thread-detail-head"><div><h2>' + esc(thread.contact_name || thread.contact_email || thread.subject || 'CRM conversation') + '</h2><p>' + esc(thread.campaign_title || 'Merchant CRM') + ' · ' + esc(thread.campaign_type || '') + '</p></div></div><div class="mg-message-stream">' + ((thread.messages || []).map(messageBubble).join('') || '<div class="mg-empty-state"><strong>No messages yet.</strong></div>') + '</div><form class="mg-message-composer" data-crm-thread-reply><textarea name="body" maxlength="4000" required placeholder="Reply as merchant"></textarea><button class="mg-btn mg-btn-primary" type="submit">Send</button></form>';
    }
    var stream = detail && detail.querySelector('.mg-message-stream');
    if (stream) stream.scrollTop = stream.scrollHeight;
    await loadThreads();
  }

  async function sendReply(event) {
    event.preventDefault();
    var form = event.target;
    if (!state.current) return;
    var body = form.elements.body.value.trim();
    if (!body) return;
    await Microgifter.post('/api/merchant/crm-messages.php', { thread_id: state.current, body: body });
    form.reset();
    await openThread(state.current);
    document.dispatchEvent(new CustomEvent('mg:messages:refresh'));
    document.dispatchEvent(new CustomEvent('mg:notifications:refresh'));
  }

  host.innerHTML = '<div class="mg-app-panel-head"><div><h2>CRM Messages</h2><p>Merchant-owned customer conversations. Customers see their side in normal Messages; merchants manage CRM threads here.</p></div><div class="mg-heading-actions"><span class="mg-status-badge"><strong data-crm-message-unread>0</strong>&nbsp;unread</span><button class="mg-btn mg-btn-soft" type="button" data-crm-messages-refresh>Refresh</button></div></div><div class="mg-app-panel-body"><div class="mg-communications-split"><div class="mg-thread-list" data-crm-message-thread-list></div><section class="mg-thread-detail" data-crm-message-detail><div class="mg-empty-state"><strong>Select a CRM conversation</strong><p>Open a customer thread to reply from the merchant workspace.</p></div></section></div></div>';

  host.addEventListener('click', function (event) {
    var row = event.target.closest('[data-crm-thread]');
    if (row) openThread(row.getAttribute('data-crm-thread'));
  });
  host.addEventListener('submit', function (event) {
    var form = event.target.closest('[data-crm-thread-reply]');
    if (form) sendReply(event);
  });

  document.addEventListener('click', function (event) {
    if (event.target.closest('[data-crm-message]')) rememberContactFromClick(event.target);
    if (event.target.closest('[data-crm-messages-refresh]')) loadThreads();

    var button = isMessageSendButton(event.target);
    if (!button) return;
    var form = formFromSendButton(button);
    if (form) sendDirectMessage(event, form).catch(function (error) { console.error(error); });
  }, true);

  document.addEventListener('submit', function (event) {
    var form = event.target && event.target.closest ? event.target.closest('[data-crm-message-form], [data-customer-message-form], [data-crm-customer-message-form]') : null;
    if (form) sendDirectMessage(event, form).catch(function (error) { console.error(error); });
  }, true);

  document.addEventListener('mg:crm-messages:refresh', function (event) {
    loadThreads().then(function () {
      var threadId = event.detail && event.detail.thread_id;
      if (threadId) openThread(threadId);
    }).catch(function () {});
  });

  var requested = new URLSearchParams(location.search).get('thread');
  loadThreads().then(function () { if (requested) openThread(requested); }).catch(function (error) {
    var list = host.querySelector('[data-crm-message-thread-list]');
    if (list) list.innerHTML = '<div class="mg-empty-state"><strong>Unable to load CRM messages</strong><p>' + esc(error.message || 'Try again.') + '</p></div>';
  });
});
