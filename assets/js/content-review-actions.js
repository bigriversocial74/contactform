document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-admin-moderation]');
  if (!root || !window.Microgifter) return;
  var MG = window.Microgifter;
  var refresh = root.querySelector('[data-moderation-refresh]');
  var form = root.querySelector('[data-moderation-action-form]');
  var status = root.querySelector('[data-moderation-action-status]');

  if (refresh) refresh.disabled = false;
  if (!form) return;

  function message(value, kind) {
    if (!status) return;
    status.textContent = value || '';
    status.className = 'mg-admin-moderation-action-status' + (kind ? ' is-' + kind : '');
  }

  function confirmation(action) {
    var prompts = {
      dismiss: 'Dismiss this report without taking action?',
      resolve: 'Resolve this report and record the decision?',
      hide_content: 'Hide the reported content from normal viewing?',
      restore_content: 'Restore the reported content?',
      quarantine_media: 'Quarantine this uploaded media?',
      warn_user: 'Send an account warning to this user?',
      restrict_posting: 'Restrict this account from publishing posts?',
      suspend_user: 'Suspend this account and its public profile?',
      reactivate_user: 'Reactivate this account and lift active restrictions?'
    };
    return prompts[action] || 'Apply this review action?';
  }

  form.addEventListener('submit', async function (event) {
    event.preventDefault();
    var payload = Object.fromEntries(new FormData(form).entries());
    var action = String(payload.action || '');
    var reason = String(payload.reason || '').trim();
    if (action !== 'claim' && !reason) {
      message('Document a reason before applying this action.', 'error');
      form.elements.reason.focus();
      return;
    }
    if (!window.confirm(confirmation(action))) return;

    var button = form.querySelector('[data-moderation-action-submit]');
    var original = button.textContent;
    button.disabled = true;
    button.textContent = 'Applying…';
    message('Applying the review action…');
    try {
      var response = await MG.post('/api/admin/content-review/action.php', payload);
      var data = response.data || response;
      message('Action completed: ' + String(data.resulting_state || data.action || 'updated') + '.', 'success');
      if (MG.toast) MG.toast(response.message || 'Review action completed.', 'success');
      window.setTimeout(function () { window.location.reload(); }, 650);
    } catch (error) {
      message(error.message || 'Unable to complete the review action.', 'error');
      if (MG.toast) MG.toast(error.message || 'Unable to complete the review action.', 'error');
      button.disabled = false;
      button.textContent = original;
    }
  });
});
