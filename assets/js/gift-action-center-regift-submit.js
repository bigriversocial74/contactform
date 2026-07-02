document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var app = document.querySelector('[data-gift-center]');
  if (!app || !window.Microgifter) return;
  var modalBody = app.querySelector('[data-action-modal-body]');
  if (!modalBody) return;
  var activeGiftId = '';
  var confirmed = false;
  var confirmKey = '';

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
  function installConfirm(form) {
    if (!form.querySelector('[data-regift-confirm]')) {
      var box = document.createElement('div');
      box.className = 'mg-send-confirm';
      box.setAttribute('data-regift-confirm', '');
      box.hidden = true;
      box.innerHTML = '<strong>Confirm regift</strong><p data-regift-confirm-text></p>';
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
    var box = form && form.querySelector('[data-regift-confirm]');
    if (box) box.hidden = true;
    var button = form && form.querySelector('button[type="submit"]');
    if (button) button.textContent = button.dataset.originalText || 'Review Regift';
  }
  function recipient(form) {
    var id = form.querySelector('input[name="recipient_profile_id"]');
    var slug = form.querySelector('input[name="recipient_slug"]');
    var typed = form.querySelector('input[name="recipient"]');
    return {
      ref: String((id && id.value) || (slug && slug.value) || (typed && typed.value) || '').trim(),
      slug: String((slug && slug.value) || '').trim(),
      label: String((typed && typed.value) || (slug && slug.value) || 'this recipient').trim(),
      selected: !!((id && id.value) || (slug && slug.value))
    };
  }

  app.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-gift-action="send"]');
    if (!trigger) return;
    var row = trigger.closest('[data-gift-id]');
    activeGiftId = row ? String(row.getAttribute('data-gift-id') || '') : '';
    confirmed = false;
    confirmKey = '';
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
      var box = form.querySelector('[data-regift-confirm]');
      var text = form.querySelector('[data-regift-confirm-text]');
      if (text) text.textContent = 'Send this Microgift to ' + rec.label + '? This transfers ownership to the recipient.';
      if (box) box.hidden = false;
      if (button) button.textContent = 'Confirm & Regift';
      return status(form, 'Review and click Confirm & Regift to send.', '');
    }
    busy(button, true, 'Sending...');
    status(form, 'Sending Microgift...', '');
    try {
      await Microgifter.post('/api/account/action-center-send.php', {
        action_item_id: giftId,
        recipient_user_id: rec.ref,
        recipient_slug: rec.slug,
        message: message,
        idempotency_key: 'regift:' + giftId + ':' + rec.ref + ':' + Date.now()
      });
      toast('Microgift regifted.');
      modalBody.innerHTML = '<div class="mg-action-success"><strong>Microgift regifted</strong><p>The gift was sent to ' + esc(rec.label) + '.</p><button class="mg-btn mg-btn-primary" type="button" data-action-modal-close>Done</button></div>';
      var refresh = app.querySelector('[data-gift-refresh]');
      if (refresh) window.setTimeout(function () { refresh.click(); }, 350);
    } catch (error) {
      status(form, error && error.message ? error.message : 'Unable to regift this Microgift.', 'error');
      reset(form);
    } finally {
      busy(button, false);
    }
  }, true);
});
