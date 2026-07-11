document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var shell = document.querySelector('[data-merchant-crm-shell]');
  if (!shell) return;

  function qs(selector, root) {
    return (root || shell).querySelector(selector);
  }

  function qsa(selector, root) {
    return Array.prototype.slice.call((root || shell).querySelectorAll(selector));
  }

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character];
    });
  }

  var triggers = qsa('[data-crm-tab-target]');
  var panels = qsa('[data-crm-tab-panel]');

  function activate(tabId, updateHash) {
    var id = String(tabId || 'overview').trim();
    var exists = panels.some(function (panel) {
      return panel.getAttribute('data-crm-tab-panel') === id;
    });
    if (!exists) id = 'overview';

    triggers.forEach(function (trigger) {
      var active = trigger.getAttribute('data-crm-tab-target') === id;
      trigger.classList.toggle('is-active', active);
      if (trigger.getAttribute('role') === 'tab') {
        trigger.setAttribute('aria-selected', active ? 'true' : 'false');
        trigger.tabIndex = active ? 0 : -1;
      }
    });

    panels.forEach(function (panel) {
      panel.hidden = panel.getAttribute('data-crm-tab-panel') !== id;
    });

    shell.setAttribute('data-crm-active-tab', id);
    if (updateHash && window.history && history.replaceState) {
      history.replaceState(null, '', '#crm-' + id);
    }
    document.dispatchEvent(new CustomEvent('mg:crm-tab:changed', { detail: { tab: id } }));
  }

  triggers.forEach(function (trigger) {
    trigger.addEventListener('click', function (event) {
      event.preventDefault();
      activate(trigger.getAttribute('data-crm-tab-target'), true);
    });
  });

  var tablist = qs('.mg-crm-tabs');
  if (tablist) {
    tablist.addEventListener('keydown', function (event) {
      if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
      var tabs = qsa('[role="tab"]', tablist);
      var current = tabs.indexOf(document.activeElement);
      if (current < 0) return;
      event.preventDefault();
      var direction = event.key === 'ArrowRight' ? 1 : -1;
      var next = (current + direction + tabs.length) % tabs.length;
      tabs[next].focus();
      tabs[next].click();
    });
  }

  var activeContactId = '';

  document.addEventListener('click', function (event) {
    var row = event.target && event.target.closest && event.target.closest('tr[data-contact-id]');
    if (row) activeContactId = String(row.getAttribute('data-contact-id') || activeContactId || '').trim();
  }, true);

  document.addEventListener('submit', async function (event) {
    var form = event.target;
    if (!form || !form.matches('[data-crm-message-form]')) return;

    event.preventDefault();
    event.stopPropagation();
    if (event.stopImmediatePropagation) event.stopImmediatePropagation();

    var modal = qs('[data-crm-message-modal]');
    if (!modal || modal.hidden) return;

    var body = qs('[data-crm-message-body]', form);
    var status = qs('[data-crm-message-status]', form);
    var button = qs('[data-crm-message-submit]', form);
    var message = body ? String(body.value || '').trim() : '';

    function setStatus(type, html) {
      if (!status) return;
      status.dataset.statusType = type || '';
      status.style.display = 'block';
      status.style.visibility = 'visible';
      status.innerHTML = html;
    }

    if (!activeContactId) {
      setStatus('error', '<strong>Message not sent.</strong><br>Open the contact row again so the CRM can bind the contact ID.');
      return;
    }
    if (!message) {
      setStatus('error', '<strong>Message not sent.</strong><br>Write a message before sending.');
      if (body) body.focus();
      return;
    }

    if (button) {
      if (!button.dataset.originalText) button.dataset.originalText = button.textContent;
      button.disabled = true;
      button.textContent = 'Sending...';
    }
    setStatus('loading', '<strong>Sending message...</strong>');

    try {
      var response = await Microgifter.post('/api/merchant/crm-message.php', {
        contact_id: activeContactId,
        message: message,
        idempotency_key: 'crm-direct:' + activeContactId + ':' + Date.now()
      });
      var data = response.data || response;
      var proof = data.message || {};
      var threadId = data.thread_id || proof.thread_id || '';
      var messageId = data.message_id || proof.message_id || '';
      if (!threadId || !messageId) throw new Error('Message endpoint returned without thread/message proof.');
      if (body) body.value = '';
      setStatus('success', '<strong>Message sent.</strong><br><small>Thread: ' + esc(threadId) + ' · Message: ' + esc(messageId) + '</small>');
      document.dispatchEvent(new CustomEvent('mg:notifications:refresh'));
      if (window.Microgifter && Microgifter.toast) Microgifter.toast('Message sent.');
    } catch (error) {
      setStatus('error', '<strong>Message failed.</strong><br>' + esc(error.message || 'Unable to send CRM message.'));
    } finally {
      if (button) {
        button.disabled = false;
        button.textContent = button.dataset.originalText || 'Send message';
      }
    }
  }, true);

  var query = new URLSearchParams(location.search || '');
  var initial = query.get('tab') || query.get('crm_tab') || (location.hash || '').replace(/^#crm-/, '').replace(/^#/, '');
  activate(initial || 'overview', false);
});
