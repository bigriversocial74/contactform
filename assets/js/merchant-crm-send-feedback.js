document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  if (!window.Microgifter) return;

  var activeContactId = '';
  var activeContactLabel = '';

  function qs(selector, root) { return (root || document).querySelector(selector); }
  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[c];
    });
  }
  function setBusy(button, busy, label) {
    if (!button) return;
    if (!button.dataset.originalText) button.dataset.originalText = button.textContent;
    button.disabled = !!busy;
    button.setAttribute('aria-busy', busy ? 'true' : 'false');
    button.textContent = busy ? (label || 'Sending...') : button.dataset.originalText;
  }
  function ensureStatus(form) {
    var status = qs('[data-crm-message-status]', form) || qs('[data-crm-message-status]');
    if (!status) {
      status = document.createElement('div');
      status.setAttribute('data-crm-message-status', '');
      status.className = 'mg-form-status';
      var actions = qs('.mg-heading-actions', form);
      form.insertBefore(status, actions || null);
    }
    status.style.display = 'block';
    status.style.visibility = 'visible';
    return status;
  }
  function setStatus(form, type, html) {
    var status = ensureStatus(form);
    status.dataset.statusType = type;
    status.classList.remove('is-success', 'is-error', 'is-loading');
    status.classList.add(type === 'success' ? 'is-success' : (type === 'error' ? 'is-error' : 'is-loading'));
    status.innerHTML = html;
  }
  function proofHtml(data) {
    var payload = data && data.data ? data.data : data;
    var message = payload && payload.message ? payload.message : {};
    var delivered = message.delivered_via === 'microgifter_thread';
    var title = delivered ? 'Message delivered to customer Messages.' : 'Message queued for email fallback.';
    var chips = [];
    if (payload.thread_id || message.thread_id) chips.push('Thread ' + (payload.thread_id || message.thread_id));
    if (payload.message_id || message.message_id) chips.push('Message ' + (payload.message_id || message.message_id));
    if (message.notification_id) chips.push('Notification ' + message.notification_id);
    if (message.recipient_user_id) chips.push('Customer user ' + message.recipient_user_id);
    if (message.duplicate) chips.push('Duplicate protected');
    if (message.email_delivery) chips.push('Email job queued');
    var open = payload.thread_id || message.thread_id ? '<a class="mg-btn mg-btn-soft" href="/merchant-crm.php?tab=messages&thread=' + encodeURIComponent(payload.thread_id || message.thread_id) + '">Open CRM thread</a>' : '';
    var customer = payload.thread_id || message.thread_id ? '<a class="mg-btn mg-btn-soft" href="/messages.php?thread=' + encodeURIComponent(payload.thread_id || message.thread_id) + '">Customer message URL</a>' : '';
    return '<div class="mg-crm-delivery-proof"><strong>' + esc(title) + '</strong><p>' + esc(activeContactLabel || 'CRM contact') + '</p><div>' + chips.map(function (chip) { return '<span>' + esc(chip) + '</span>'; }).join('') + '</div><div class="mg-crm-proof-actions">' + open + customer + '</div></div>';
  }
  function findContactFromClick(target) {
    var row = target.closest && target.closest('tr[data-contact-id]');
    if (!row) return;
    activeContactId = row.getAttribute('data-contact-id') || '';
    var name = row.querySelector('td:nth-child(2) strong');
    var email = row.getAttribute('data-contact-email') || '';
    activeContactLabel = (name && name.textContent ? name.textContent.trim() : '') || email || activeContactId;
    var modal = qs('[data-crm-message-modal]');
    if (modal) {
      modal.dataset.activeContactId = activeContactId;
      modal.dataset.activeContactLabel = activeContactLabel;
    }
  }
  async function sendMessage(event) {
    var form = event.target && event.target.closest ? event.target.closest('[data-crm-message-form]') : null;
    if (!form) return;
    event.preventDefault();
    event.stopPropagation();
    if (event.stopImmediatePropagation) event.stopImmediatePropagation();

    var modal = qs('[data-crm-message-modal]');
    var contactId = activeContactId || (modal && modal.dataset.activeContactId) || '';
    var bodyField = qs('[data-crm-message-body]', form) || qs('[data-crm-message-body]');
    var body = bodyField ? bodyField.value.trim() : '';
    var button = qs('[data-crm-message-submit]', form);

    if (!contactId) {
      setStatus(form, 'error', '<strong>Message not sent.</strong><br>Missing CRM contact ID. Close this modal, reopen the contact row, and try again.');
      return;
    }
    if (!body) {
      setStatus(form, 'error', '<strong>Message not sent.</strong><br>Write a message before sending.');
      return;
    }

    setStatus(form, 'loading', '<strong>Sending message...</strong><br>Creating the CRM thread, customer participant, message row, and notification.');
    setBusy(button, true, 'Sending...');
    try {
      var response = await Microgifter.post('/api/merchant/crm-message.php', {
        contact_id: contactId,
        message: body,
        idempotency_key: 'crm-message-ui:' + contactId + ':' + Date.now()
      });
      var payload = response && response.data ? response.data : response;
      var msg = payload && payload.message ? payload.message : {};
      if (!payload || !payload.thread_id || !payload.message_id) {
        throw new Error('Message endpoint returned without thread/message proof.');
      }
      if (bodyField) bodyField.value = '';
      setStatus(form, 'success', proofHtml(response));
      document.dispatchEvent(new CustomEvent('mg:crm-messages:refresh', { detail: { thread_id: payload.thread_id || msg.thread_id || '' } }));
      document.dispatchEvent(new CustomEvent('mg:notifications:refresh'));
      if (Microgifter.toast) Microgifter.toast(msg.delivered_via === 'microgifter_thread' ? 'Message delivered to customer Messages.' : 'Message queued for email fallback.');
    } catch (error) {
      setStatus(form, 'error', '<strong>Message failed.</strong><br>' + esc(error && error.message ? error.message : 'Unable to send CRM message.') + '<br><small>Check the Network response for /api/merchant/crm-message.php if this repeats.</small>');
    } finally {
      setBusy(button, false);
    }
  }

  document.addEventListener('click', function (event) {
    if (event.target && event.target.closest && event.target.closest('[data-crm-message]')) findContactFromClick(event.target);
  }, true);
  document.addEventListener('submit', sendMessage, true);
});
