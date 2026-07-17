document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var app = document.querySelector('[data-gift-center]');
  if (!app || !window.Microgifter) return;
  var modalBody = app.querySelector('[data-action-modal-body]');
  if (!modalBody) return;
  var activeGiftId = '';
  var confirmed = false;
  var confirmKey = '';
  var confirmIdempotencyKey = '';

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
    });
  }
  function toast(message) {
    if (Microgifter.toast) Microgifter.toast(message);
  }
  function busy(button, on, text) {
    if (!button) return;
    if (!button.dataset.originalText) button.dataset.originalText = button.textContent;
    button.disabled = !!on;
    button.textContent = on ? (text || 'Sending...') : button.dataset.originalText;
  }
  function status(form, message, type) {
    var node = form.querySelector('[data-regift-status]');
    if (!node) {
      node = document.createElement('p');
      node.className = 'mg-form-status';
      node.setAttribute('data-regift-status', '');
      var actions = form.querySelector('.mg-send-exact-actions,.mg-action-form-footer');
      if (actions) actions.parentNode.insertBefore(node, actions);
      else form.appendChild(node);
    }
    node.textContent = message || '';
    node.dataset.statusType = type || '';
  }
  function formatTimestamp(value) {
    var date = value ? new Date(value) : new Date();
    if (Number.isNaN(date.getTime())) date = new Date();
    return date.toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', second: '2-digit' });
  }
  function responseData(response) {
    return response && response.data ? response.data : (response || {});
  }
  function responseTimestamp(data) {
    return (data && data.delivery_event && data.delivery_event.occurred_at) ||
      (data && data.delivery_summary && data.delivery_summary.sent_at) ||
      (data && data.sent_at) ||
      (data && data.created_at) ||
      new Date().toISOString();
  }
  function installConfirm(form) {
    if (!form.querySelector('[data-regift-confirm]')) {
      var box = document.createElement('div');
      box.className = 'mg-send-confirm';
      box.setAttribute('data-regift-confirm', '');
      box.hidden = true;
      box.innerHTML = '<strong>Confirm regift</strong>';
      var actions = form.querySelector('.mg-send-exact-actions,.mg-action-form-footer');
      if (actions) actions.parentNode.insertBefore(box, actions);
      else form.appendChild(box);
    }
    var button = form.querySelector('button[type="submit"]');
    if (button && !button.dataset.originalText) button.dataset.originalText = 'Review Regift';
    if (button && button.textContent.trim() === 'Regift Microgift') button.textContent = 'Review Regift';
  }
  function reset(form) {
    confirmed = false;
    confirmKey = '';
    confirmIdempotencyKey = '';
    var box = form && form.querySelector('[data-regift-confirm]');
    if (box) box.hidden = true;
    var button = form && form.querySelector('button[type="submit"]');
    if (button) button.textContent = button.dataset.originalText || 'Review Regift';
  }
  function fieldValue(form, selector) {
    var node = form.querySelector(selector);
    return String((node && node.value) || '').trim();
  }
  function recipient(form) {
    var typed = form.querySelector('input[name="recipient"]');
    var selected = form.querySelector('[data-selected-recipient]');
    var userRef = fieldValue(form, 'input[name="recipient_user_id"]');
    var profileRef = fieldValue(form, 'input[name="recipient_profile_id"]');
    var slugRef = fieldValue(form, 'input[name="recipient_slug"]');
    var selectedRef = String((selected && selected.dataset && selected.dataset.recipientProfileId) || '').trim();
    var selectedSlug = String((selected && selected.dataset && selected.dataset.recipientSlug) || '').trim();
    var formRef = String((form.dataset && form.dataset.recipientProfileId) || '').trim();
    var formSlug = String((form.dataset && form.dataset.recipientSlug) || '').trim();
    var inputRef = String((typed && typed.dataset && typed.dataset.recipientProfileId) || '').trim();
    var inputSlug = String((typed && typed.dataset && typed.dataset.recipientSlug) || '').trim();
    var ref = userRef || profileRef || selectedRef || formRef || inputRef || slugRef || selectedSlug || formSlug || inputSlug;
    var slug = slugRef || selectedSlug || formSlug || inputSlug;
    var label = String(
      (selected && selected.dataset && selected.dataset.recipientLabel) ||
      (form.dataset && form.dataset.recipientLabel) ||
      (typed && typed.dataset && typed.dataset.recipientLabel) ||
      (typed && typed.value) ||
      slug ||
      'this recipient'
    ).trim();
    var hasSelectedPill = !!(selected && !selected.hidden && (selected.dataset.recipientProfileId || selected.dataset.recipientSlug || selected.textContent.trim()));
    return {
      ref: ref,
      slug: slug,
      label: label,
      selected: !!ref && (hasSelectedPill || !!profileRef || !!userRef || !!slugRef || !!formRef || !!inputRef)
    };
  }

  app.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-gift-action="send"]');
    if (!trigger) return;
    var row = trigger.closest('[data-gift-id]');
    activeGiftId = row ? String(row.getAttribute('data-gift-id') || '') : '';
    confirmed = false;
    confirmKey = '';
    confirmIdempotencyKey = '';
    window.setTimeout(function () {
      var form = modalBody.querySelector('[data-action-form="send"]');
      if (form) installConfirm(form);
    }, 80);
  }, true);

  modalBody.addEventListener('input', function (event) {
    var form = event.target.closest('[data-action-form="send"]');
    if (form) reset(form);
  }, true);

  modalBody.addEventListener('submit', async function (event) {
    var form = event.target.closest('[data-action-form="send"]');
    if (!form) return;
    event.preventDefault();
    event.stopPropagation();
    if (event.stopImmediatePropagation) event.stopImmediatePropagation();
    installConfirm(form);
    var rec = recipient(form);
    var message = String((form.querySelector('textarea[name="message"]') || {}).value || '').trim();
    var giftId = String((form.querySelector('input[name="action_item_id"]') || {}).value || activeGiftId || '').trim();
    var button = form.querySelector('button[type="submit"]');
    if (!giftId) return status(form, 'This gift is missing an Action Center item id.', 'error');
    if (!rec.ref) return status(form, 'Choose a recipient before sending.', 'error');
    if (!rec.selected) return status(form, 'Tap a search result to confirm the recipient before sending.', 'error');
    var key = giftId + '|' + rec.ref + '|' + message;
    if (!confirmed || confirmKey !== key) {
      confirmed = true;
      confirmKey = key;
      confirmIdempotencyKey = 'regift:' + giftId + ':' + rec.ref + ':' + Date.now();
      var box = form.querySelector('[data-regift-confirm]');
      if (box) box.hidden = false;
      if (button) button.textContent = 'Yes, Send Gift';
      return status(form, 'Review the recipient, then click Yes, Send Gift to confirm.', '');
    }
    busy(button, true, 'Sending...');
    status(form, 'Sending Microgift...', '');
    try {
      var response = await Microgifter.post('/api/account/action-center-send.php', {
        action_item_id: giftId,
        recipient_user_id: rec.ref,
        recipient_slug: rec.slug,
        recipient: rec.ref,
        message: message,
        idempotency_key: confirmIdempotencyKey || ('regift:' + giftId + ':' + rec.ref + ':' + Date.now())
      });
      var data = responseData(response);
      var sentAt = responseTimestamp(data);
      var timestampLabel = formatTimestamp(sentAt);
      toast('Microgift sent to ' + rec.label + '.');
      modalBody.innerHTML = '<div class="mg-action-success">' +
        '<strong>Gift sent successfully</strong>' +
        '<p>The Microgift was sent to ' + esc(rec.label) + ' and should now appear in their Inbox.</p>' +
        '<p><strong>Timestamp:</strong> ' + esc(timestampLabel) + '</p>' +
        '<button class="mg-btn mg-btn-primary" type="button" data-action-modal-close>Done</button>' +
        '</div>';
      document.dispatchEvent(new CustomEvent('mg:action-center:regift-sent', { detail: { gift_id: giftId, recipient: rec, sent_at: sentAt, response: data } }));
    } catch (error) {
      status(form, error && error.message ? error.message : 'Unable to regift this Microgift.', 'error');
      reset(form);
    } finally {
      busy(button, false);
    }
  }, true);
});
