document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  function merchantCommand(value) {
    var match = String(value || '').trim().match(/^\/(?:m|merchant)(?:\s+([\s\S]*))?$/i);
    if (!match) return null;
    return String(match[1] || '').trim();
  }

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char];
    });
  }

  function routeToMerchant(root, prompt) {
    if (!root || root.getAttribute('data-merchant-agent-access') !== 'true') return false;
    var url = new URL('/merchant-agent-chat.php', window.location.origin);
    url.searchParams.set('source', 'personal-agent');
    if (prompt) url.searchParams.set('prompt', prompt);
    window.location.href = url.pathname + url.search;
    return true;
  }

  function showMerchantAccessMessage(root, form) {
    var feed = root && root.querySelector('[data-personal-agent-feed]');
    var status = root && root.querySelector('[data-personal-agent-status]');
    if (feed) {
      var message = document.createElement('div');
      message.className = 'mg-personal-agent-message is-assistant';
      message.innerHTML = '<div><strong>Merchant Agent access is not enabled.</strong><br>Use a merchant package or an assigned merchant workspace to open business data, campaign, CRM, product, and analytics tools. <a href="/account-subscriptions.php">Review merchant access</a>.</div>';
      feed.appendChild(message);
      message.scrollIntoView({ block: 'end', behavior: 'smooth' });
    }
    if (status) {
      status.textContent = 'Merchant Agent requires merchant access and merchant AI permissions.';
      status.classList.add('is-error');
    }
    var input = form && form.querySelector('textarea,input');
    if (input) {
      input.value = '';
      input.focus();
    }
  }

  var personalRoot = document.querySelector('[data-personal-gifting-agent]');
  var personalForm = personalRoot && personalRoot.querySelector('[data-personal-agent-composer]');
  if (personalRoot && personalForm) {
    personalForm.addEventListener('submit', function (event) {
      var input = personalForm.querySelector('textarea,input');
      var prompt = merchantCommand(input && input.value);
      if (prompt === null) return;
      event.preventDefault();
      event.stopImmediatePropagation();
      if (!routeToMerchant(personalRoot, prompt)) showMerchantAccessMessage(personalRoot, personalForm);
    }, true);
  }

  var merchantRoot = document.querySelector('[data-merchant-agent-chat]');
  if (!merchantRoot) return;

  var params = new URLSearchParams(window.location.search);
  var handoffPrompt = String(params.get('prompt') || '').trim();
  var source = String(params.get('source') || '').trim();
  var form = merchantRoot.querySelector('[data-agent-chat-form]');
  var textarea = form && form.querySelector('[data-agent-chat-textarea],textarea[name="message"]');
  if (!form || !textarea || (!handoffPrompt && source !== 'personal-agent')) return;

  if (source === 'personal-agent') {
    var note = merchantRoot.querySelector('[data-merchant-agent-handoff-note]');
    if (note) {
      note.hidden = false;
      note.textContent = handoffPrompt ? 'Continued from Personal Agent using /m.' : 'Merchant mode opened from Personal Agent.';
    }
  }

  if (handoffPrompt) {
    textarea.value = handoffPrompt;
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
  }

  window.setTimeout(function () {
    if (handoffPrompt) form.requestSubmit();
    else textarea.focus({ preventScroll: true });
    var clean = new URL(window.location.href);
    clean.searchParams.delete('prompt');
    clean.searchParams.delete('source');
    window.history.replaceState({}, '', clean.pathname + clean.search + clean.hash);
  }, 350);
});
