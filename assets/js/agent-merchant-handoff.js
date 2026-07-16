document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  function merchantCommand(value) {
    var match = String(value || '').trim().match(/^\/(?:m|merchant)(?:\s+([\s\S]*))?$/i);
    if (!match) return null;
    return String(match[1] || '').trim();
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
  if (!personalRoot || !personalForm) return;

  personalForm.addEventListener('submit', function (event) {
    var input = personalForm.querySelector('textarea,input');
    var prompt = merchantCommand(input && input.value);
    if (prompt === null) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    if (!routeToMerchant(personalRoot, prompt)) showMerchantAccessMessage(personalRoot, personalForm);
  }, true);
});
