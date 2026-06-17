window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root;
  var current = null;

  function qs(selector, scope) { return (scope || document).querySelector(selector); }
  function clear(node) { if (node) node.replaceChildren(); }
  function hide(node, value) { if (node) node.classList.toggle('mg-hidden', Boolean(value)); }
  function data(response) { return response && response.data ? response.data : response; }
  function label(value) { return String(value || '').replace(/[_-]+/g, ' ').replace(/\b\w/g, function (letter) { return letter.toUpperCase(); }); }

  function pill(value) {
    var node = document.createElement('span');
    node.textContent = label(value);
    return node;
  }

  function setStatus(node, message, type) {
    if (!node) return;
    node.textContent = message || '';
    node.className = 'mg-profile-action-status' + (message ? ' is-visible' : '') + (type ? ' is-' + type : '');
  }

  function render(payload) {
    current = payload || {};
    var caseData = current.case;
    var restricted = ['hidden', 'suspended'].includes(String(current.profile_status || ''));
    var activeAppeal = current.appeal && ['submitted', 'in_review'].includes(current.appeal.status);
    var deniedAppeal = current.appeal && current.appeal.status === 'denied';
    if (!caseData || (!restricted && !activeAppeal && !deniedAppeal)) {
      hide(root, true);
      return;
    }

    hide(root, false);
    var title = qs('[data-owner-moderation-title]', root);
    title.textContent = current.profile_status === 'suspended'
      ? 'Your profile is suspended'
      : current.profile_status === 'hidden'
        ? 'Your profile is hidden by moderation'
        : 'Your moderation appeal was reviewed';
    qs('[data-owner-moderation-summary]', root).textContent = caseData.summary || 'A moderation restriction is active on this profile.';

    var meta = qs('[data-owner-moderation-meta]', root);
    clear(meta);
    meta.append(pill(caseData.category), pill(caseData.status), pill(current.profile_status));

    var reason = qs('[data-owner-moderation-reason]', root);
    reason.textContent = caseData.reason ? 'Moderation reason: ' + caseData.reason : '';
    hide(reason, !caseData.reason);

    var appealState = qs('[data-owner-appeal-state]', root);
    if (current.appeal) {
      var copy = 'Appeal status: ' + label(current.appeal.status) + '.';
      if (current.appeal.decision_reason) copy += '\nDecision: ' + current.appeal.decision_reason;
      appealState.textContent = copy;
      hide(appealState, false);
    } else {
      appealState.textContent = '';
      hide(appealState, true);
    }

    var button = qs('[data-owner-appeal-open]', root);
    hide(button, !current.can_appeal);
    var form = qs('[data-owner-appeal-form]', root);
    if (form && caseData.id) form.elements.case_id.value = caseData.id;
  }

  async function load() {
    try {
      var response = await MG.get('/api/profiles/moderation.php');
      render(data(response));
    } catch (error) {
      hide(root, true);
    }
  }

  async function submit(form) {
    var button = qs('button[type="submit"]', form);
    var status = qs('[data-owner-appeal-status]', form);
    MG.setBusy(button, true, 'Submitting…');
    setStatus(status, '', '');
    try {
      var payload = Object.fromEntries(new FormData(form).entries());
      var response = await MG.post('/api/profiles/moderation-appeal.php', payload);
      render(data(response));
      qs('[data-owner-appeal-dialog]', root).close();
      form.reset();
      setStatus(status, '', '');
      MG.toast(response.message || 'Appeal submitted.', 'success');
    } catch (error) {
      setStatus(status, error.message || 'Unable to submit appeal.', 'error');
    } finally { MG.setBusy(button, false); }
  }

  function init() {
    root = qs('[data-profile-moderation-owner]');
    if (!root) return;
    var dialog = qs('[data-owner-appeal-dialog]', root);
    var open = qs('[data-owner-appeal-open]', root);
    var cancel = qs('[data-owner-appeal-cancel]', root);
    var form = qs('[data-owner-appeal-form]', root);
    open.addEventListener('click', function () { dialog.showModal(); });
    cancel.addEventListener('click', function () { dialog.close(); });
    form.addEventListener('submit', function (event) { event.preventDefault(); submit(form); });
    load();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})(window, document);
