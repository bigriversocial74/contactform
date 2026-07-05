document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  if (!window.Microgifter || !document.querySelector('[data-merchant-crm-app]')) return;

  var state = {
    contacts: [],
    threads: [],
    threadByContact: {},
    activeContact: null,
    activeThreadId: '',
    isOpen: false
  };

  function qs(selector, root) { return (root || document).querySelector(selector); }
  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char];
    });
  }
  function fmt(value) {
    if (!value) return '—';
    try { return new Date(String(value).replace(' ', 'T')).toLocaleString(); } catch (error) { return String(value); }
  }
  function contactSelector(id) {
    if (window.CSS && typeof CSS.escape === 'function') return '[data-contact-id="' + CSS.escape(String(id || '')) + '"]';
    return '[data-contact-id="' + String(id || '').replace(/"/g, '\\"') + '"]';
  }
  function toast(message) { if (Microgifter.toast) Microgifter.toast(message); else alert(message); }
  function setStatus(message, type) {
    var node = qs('[data-crm-contact-thread-status]');
    if (!node) return;
    node.textContent = message || '';
    node.dataset.statusType = type || '';
  }
  function setBusy(button, on, label) {
    if (!button) return;
    if (!button.dataset.originalText) button.dataset.originalText = button.textContent;
    button.disabled = !!on;
    button.textContent = on ? (label || 'Working...') : button.dataset.originalText;
  }
  function threadMessageCount(contact, thread) {
    return Number((thread && thread.message_count) || contact.message_count || (thread ? 1 : 0) || 0);
  }
  function threadActiveCount(contact, thread) {
    return Number((thread && thread.unread_count) || contact.active_message_count || (thread && thread.unread ? 1 : 0) || 0);
  }

  function ensureDrawer() {
    var drawer = qs('[data-crm-contact-thread-drawer]');
    if (drawer) return drawer;
    drawer = document.createElement('div');
    drawer.className = 'mg-crm-contact-thread-drawer';
    drawer.setAttribute('data-crm-contact-thread-drawer', '');
    drawer.hidden = true;
    drawer.innerHTML = '<button class="mg-crm-contact-thread-backdrop" type="button" data-crm-contact-thread-close aria-label="Close message history"></button>' +
      '<aside class="mg-crm-contact-thread-panel" role="dialog" aria-modal="true" aria-labelledby="crmContactThreadTitle">' +
      '<header class="mg-crm-contact-thread-head"><div><span class="mg-eyebrow">Customer messages</span><h2 id="crmContactThreadTitle" data-crm-contact-thread-title>Message history</h2><p data-crm-contact-thread-subtitle>Select a contact.</p></div><button class="mg-btn mg-btn-soft" type="button" data-crm-contact-thread-close>Close</button></header>' +
      '<div class="mg-crm-contact-thread-meta" data-crm-contact-thread-meta></div>' +
      '<div class="mg-crm-contact-thread-body" data-crm-contact-thread-body><div class="mg-empty-state"><strong>No contact selected</strong></div></div>' +
      '<form class="mg-crm-contact-thread-footer" data-crm-contact-thread-form><textarea name="body" maxlength="4000" required placeholder="Write a message to this customer..."></textarea><button class="mg-btn mg-btn-primary" type="submit" data-crm-contact-thread-send>Send message</button><p data-crm-contact-thread-status class="mg-form-status"></p></form>' +
      '</aside>';
    document.body.appendChild(drawer);
    return drawer;
  }

  function openDrawer() {
    var drawer = ensureDrawer();
    drawer.hidden = false;
    document.body.classList.add('mg-crm-contact-thread-open');
    state.isOpen = true;
  }

  function closeDrawer() {
    var drawer = ensureDrawer();
    drawer.hidden = true;
    document.body.classList.remove('mg-crm-contact-thread-open');
    state.isOpen = false;
    state.activeContact = null;
    state.activeThreadId = '';
  }

  function messageBubble(message) {
    var mine = message.mine ? ' is-mine' : '';
    return '<article class="mg-crm-contact-message' + mine + '"><strong>' + esc(message.sender_name || (message.mine ? 'Merchant' : 'Customer')) + '</strong><p>' + esc(message.body || '') + '</p><small>' + esc(fmt(message.created_at)) + '</small></article>';
  }

  function emptyThreadHtml(contact) {
    return '<div class="mg-empty-state"><strong>No messages yet</strong><p>Start a new CRM thread with ' + esc(contact.name || contact.email || 'this customer') + ' using the chat bar below.</p></div>';
  }

  function renderThreadHeader(contact, thread) {
    var title = contact.name || contact.email || 'Customer';
    var meta = thread ? ((thread.unread ? 'Unread activity' : 'Active thread') + ' · ' + fmt(thread.latest_at)) : 'No active thread yet';
    var account = contact.has_account ? 'Customer account linked' : 'Email fallback available';
    var titleNode = qs('[data-crm-contact-thread-title]');
    var subtitleNode = qs('[data-crm-contact-thread-subtitle]');
    var metaNode = qs('[data-crm-contact-thread-meta]');
    if (titleNode) titleNode.textContent = title;
    if (subtitleNode) subtitleNode.textContent = (contact.email || '') + ' · ' + (contact.campaign_title || 'Campaign contact');
    if (metaNode) metaNode.innerHTML = '<span><strong>' + esc(thread ? 1 : 0) + '</strong> active thread</span><span><strong>' + esc(threadMessageCount(contact, thread)) + '</strong> messages</span><span><strong>' + esc(threadActiveCount(contact, thread)) + '</strong> active messages</span><span>' + esc(account) + '</span><span>' + esc(meta) + '</span>';
  }

  async function loadThreads() {
    var response = await Microgifter.get('/api/merchant/crm-messages.php?limit=100');
    var data = response.data || response;
    state.threads = data.threads || [];
    state.threadByContact = {};
    state.threads.forEach(function (thread) {
      if (thread.contact_id) state.threadByContact[String(thread.contact_id)] = thread;
    });
    return state.threads;
  }

  async function openThread(contact) {
    state.activeContact = contact;
    openDrawer();
    setStatus('', '');
    await loadThreads().catch(function () {});
    var thread = state.threadByContact[String(contact.id || '')] || null;
    state.activeThreadId = thread ? String(thread.id || '') : '';
    renderThreadHeader(contact, thread);
    var body = qs('[data-crm-contact-thread-body]');
    if (body) body.innerHTML = '<div class="mg-empty-state"><strong>Loading message history...</strong></div>';
    if (!thread) {
      if (body) body.innerHTML = emptyThreadHtml(contact);
      return;
    }
    try {
      var response = await Microgifter.get('/api/merchant/crm-messages.php?thread=' + encodeURIComponent(thread.id));
      var data = response.data || response;
      var detail = data.thread || {};
      var messages = detail.messages || [];
      if (body) {
        body.innerHTML = messages.length ? '<div class="mg-crm-contact-message-stream">' + messages.map(messageBubble).join('') + '</div>' : emptyThreadHtml(contact);
        body.scrollTop = body.scrollHeight;
      }
      await loadThreads().catch(function () {});
      syncRows();
    } catch (error) {
      if (body) body.innerHTML = '<div class="mg-empty-state"><strong>Unable to load message history</strong><p>' + esc(error.message || 'Try again.') + '</p></div>';
    }
  }

  async function sendMessage(event) {
    event.preventDefault();
    var form = event.currentTarget;
    if (!state.activeContact) return;
    var textarea = form.elements.body;
    var text = textarea ? textarea.value.trim() : '';
    if (!text) return;
    var button = qs('[data-crm-contact-thread-send]', form);
    setBusy(button, true, 'Sending...');
    setStatus('Sending message...', 'loading');
    try {
      var response;
      if (state.activeThreadId) {
        response = await Microgifter.post('/api/merchant/crm-messages.php', { thread_id: state.activeThreadId, body: text });
      } else {
        response = await Microgifter.post('/api/merchant/crm-message.php', { contact_id: state.activeContact.id, message: text, idempotency_key: 'crm-contact-thread:' + state.activeContact.id + ':' + Date.now() });
        var data = response.data || response;
        state.activeThreadId = data.thread_id || (data.message && data.message.thread_id) || '';
      }
      if (textarea) textarea.value = '';
      setStatus('Message sent.', 'success');
      toast('Message sent.');
      document.dispatchEvent(new CustomEvent('mg:notifications:refresh'));
      document.dispatchEvent(new CustomEvent('mg:crm-messages:refresh', { detail: { thread_id: state.activeThreadId } }));
      await openThread(state.activeContact);
    } catch (error) {
      setStatus(error.message || 'Unable to send message.', 'error');
    } finally {
      setBusy(button, false);
    }
  }

  function buildCountChip(contact, thread) {
    var count = threadMessageCount(contact, thread);
    var active = threadActiveCount(contact, thread);
    var latest = thread && thread.latest_at ? fmt(thread.latest_at) : 'No thread yet';
    return '<button class="mg-crm-message-chip' + (active ? ' is-active' : '') + '" type="button" data-crm-contact-thread-open="' + esc(contact.id) + '"><strong>' + esc(count) + '</strong><span>messages</span><em>' + esc(active) + ' active</em><small>' + esc(latest) + '</small></button>';
  }

  function enhanceRow(contact) {
    var row = qs(contactSelector(contact.id));
    if (!row) return;
    var thread = state.threadByContact[String(contact.id || '')] || null;
    var actionCell = row.children[row.children.length - 1];
    if (!actionCell) return;
    var existing = qs('[data-crm-contact-thread-open]', row);
    if (existing) existing.remove();
    actionCell.insertAdjacentHTML('afterbegin', buildCountChip(contact, thread));
    var messageButton = qs('[data-crm-message]', row);
    if (messageButton) {
      messageButton.textContent = thread ? 'Open messages' : 'Send message';
      messageButton.classList.add('mg-crm-message-open-btn');
      messageButton.setAttribute('data-crm-contact-thread-open', String(contact.id || ''));
      messageButton.title = thread ? 'Open customer message history' : 'Start a customer message thread';
    }
  }

  function syncSummary() {
    var totalMessages = state.threads.reduce(function (sum, thread) { return sum + Number(thread.message_count || 0); }, 0);
    var activeMessages = state.threads.reduce(function (sum, thread) { return sum + Number(thread.unread_count || (thread.unread ? 1 : 0) || 0); }, 0);
    var strip = qs('.mg-crm-contact-stat-strip');
    if (!strip) return;
    var totalNode = qs('[data-crm-contact-message-total]', strip);
    var activeNode = qs('[data-crm-contact-active-message-total]', strip);
    if (!totalNode || !activeNode) {
      strip.insertAdjacentHTML('beforeend', '<article data-crm-contact-message-total><span>Messages</span><strong>' + esc(totalMessages) + '</strong></article><article data-crm-contact-active-message-total><span>Active Messages</span><strong>' + esc(activeMessages) + '</strong></article>');
      return;
    }
    var totalStrong = totalNode.querySelector('strong');
    var activeStrong = activeNode.querySelector('strong');
    if (totalStrong) totalStrong.textContent = String(totalMessages);
    if (activeStrong) activeStrong.textContent = String(activeMessages);
  }

  function syncRows() {
    state.contacts.forEach(enhanceRow);
    syncSummary();
  }

  document.addEventListener('mg:crm-contacts:rendered', function (event) {
    state.contacts = (event.detail && event.detail.visible) || [];
    loadThreads().then(syncRows).catch(syncRows);
  });

  document.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-crm-contact-thread-open], [data-crm-message]');
    if (trigger) {
      var row = trigger.closest('tr[data-contact-id]');
      if (row) {
        var id = row.getAttribute('data-contact-id') || trigger.getAttribute('data-crm-contact-thread-open') || '';
        var contact = state.contacts.find(function (item) { return String(item.id) === String(id); });
        if (contact) {
          event.preventDefault();
          event.stopImmediatePropagation();
          openThread(contact);
          return;
        }
      }
    }
    if (event.target.closest('[data-crm-contact-thread-close]')) {
      event.preventDefault();
      closeDrawer();
    }
  }, true);

  document.addEventListener('submit', function (event) {
    if (event.target && event.target.matches('[data-crm-contact-thread-form]')) sendMessage(event);
  });
});
