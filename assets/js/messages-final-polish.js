document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-messages-center]');
  if (!root || !window.Microgifter) return;

  var MG = window.Microgifter;
  var timers = Object.create(null);

  function qs(selector, scope) { return (scope || root).querySelector(selector); }
  function qsa(selector, scope) { return Array.from((scope || root).querySelectorAll(selector)); }
  function csrf() { return MG.getCsrfToken ? MG.getCsrfToken() : ''; }
  function toast(message, type) { if (MG.toast) MG.toast(message, type || 'success'); }
  function threadId() { var form = qs('[data-thread-reply]'); return form ? String(form.dataset.threadId || '') : ''; }
  function composer() { return qs('[data-thread-reply] textarea[name="body"]'); }
  function setBusy(button, busy, label) {
    if (!button) return;
    if (busy && !button.dataset.originalText) button.dataset.originalText = button.textContent;
    button.disabled = !!busy;
    button.textContent = busy ? (label || 'Saving…') : (button.dataset.originalText || button.textContent);
  }
  function insertText(text) {
    var textarea = composer();
    if (!textarea) return;
    var current = textarea.value.trim();
    textarea.value = current ? current + '\n' + text : text;
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
    textarea.focus();
    textarea.selectionStart = textarea.selectionEnd = textarea.value.length;
  }
  function friendlyTime(value) {
    if (!value) return '';
    var normalized = String(value).replace(' ', 'T');
    var date = new Date(normalized);
    if (Number.isNaN(date.getTime())) return String(value);
    var now = new Date();
    var yesterday = new Date();
    yesterday.setDate(now.getDate() - 1);
    if (date.toDateString() === now.toDateString()) return date.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
    if (date.toDateString() === yesterday.toDateString()) return 'Yesterday';
    return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
  }
  function debounce(key, fn, wait) {
    clearTimeout(timers[key]);
    timers[key] = setTimeout(fn, wait || 120);
  }
  function currentMenuState() {
    var status = (qs('[data-profile-status]') && qs('[data-profile-status]').textContent || '').toLowerCase();
    var label = (qs('[data-profile-label]') && qs('[data-profile-label]').textContent || '').toLowerCase();
    var titleCard = qs('.mg-thread-card.is-active');
    if (titleCard) {
      status = titleCard.dataset.threadStatus || status;
      label = titleCard.textContent.toLowerCase().includes('high value') ? 'high value' : label;
    }
    return { status: status, label: label };
  }
  async function postCrm(payload) {
    var id = threadId();
    if (!id) throw new Error('No active thread.');
    return MG.post('/api/messages/crm-ops.php', Object.assign({ thread_id: id, action: 'update_state', csrf_token: csrf() }, payload || {}));
  }
  async function postThreadSetting(action) {
    var id = threadId();
    if (!id) throw new Error('No active thread.');
    return MG.post('/api/communications/thread-settings.php', { thread_id: id, action: action, csrf_token: csrf() });
  }
  function refreshMessages() {
    document.dispatchEvent(new CustomEvent('mg:messages:refresh'));
    var active = qs('.mg-thread-card.is-active');
    if (active) active.click();
  }
  async function handleThreadAction(event, button) {
    var action = button.dataset.threadAction || '';
    if (!action || action === 'profile') return false;
    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();
    setBusy(button, true);
    try {
      var state = currentMenuState();
      if (action === 'resolve') {
        await postCrm({ status: state.status === 'resolved' ? 'open' : 'resolved' });
        toast(state.status === 'resolved' ? 'Conversation reopened.' : 'Conversation resolved.');
      } else if (action === 'assign') {
        await postCrm({ assign_to_self: true });
        toast('Conversation assigned to you.');
      } else if (action === 'high-value') {
        await postCrm({ label: state.label === 'high value' ? 'Needs follow-up' : 'High value' });
        toast(state.label === 'high value' ? 'High value label removed.' : 'Marked high value.');
      } else if (action === 'archive') {
        await postThreadSetting('archive');
        toast('Conversation archived.');
      } else if (action === 'hide') {
        try { localStorage.setItem('mg:messages:hidden:' + threadId(), '1'); } catch (ignore) {}
        toast('Conversation removed from this view.');
      }
      refreshMessages();
    } catch (error) {
      toast(error && error.message ? error.message : 'Thread action failed.', 'error');
    } finally {
      setBusy(button, false);
    }
    return true;
  }
  function handleAttachmentButton(event, button) {
    var label = String(button.textContent || '').trim().toLowerCase();
    if (!label) return false;
    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();
    if (label.includes('reward')) {
      insertText('I can send a Microgifter reward for your next visit. Reply with the option you want and I will generate the reward link.');
      toast('Reward message inserted.');
      return true;
    }
    if (label.includes('claim')) {
      insertText('Here is your claim link: [claim link]. Show the claim code when you arrive.');
      toast('Claim-link message inserted.');
      return true;
    }
    if (label.includes('image')) {
      insertText('[Image attachment pending]');
      toast('Image attachment placeholder added.');
      return true;
    }
    if (label.includes('pdf')) {
      insertText('[PDF attachment pending]');
      toast('PDF attachment placeholder added.');
      return true;
    }
    return false;
  }
  function improveSidebar() {
    qsa('.mg-thread-card').forEach(function (card) {
      if (card.dataset.finalPolished === '1') return;
      card.dataset.finalPolished = '1';
      var smalls = qsa('small span:last-child', card);
      smalls.forEach(function (node) { node.textContent = friendlyTime(node.textContent); });
      var draft = qs('.mg-thread-draft-preview', card);
      if (draft && draft.textContent.length > 86) draft.textContent = draft.textContent.slice(0, 83) + '…';
    });
  }
  function improveMessages() {
    var stream = qs('.mg-message-stream');
    if (!stream) return;
    qsa('.mg-message-row', stream).forEach(function (row) {
      var bubble = qs('.mg-message-bubble', row);
      if (!bubble) return;
      var body = qs('p', bubble);
      if (body && body.textContent.trim().length < 44) row.classList.add('is-short-message');
      var meta = qs('.mg-message-meta small', bubble);
      if (meta) meta.textContent = friendlyTime(meta.textContent);
    });
  }
  function improveMenuLabels() {
    var menu = qs('[data-thread-menu]');
    if (!menu) return;
    var state = currentMenuState();
    var high = qs('[data-thread-action="high-value"]', menu);
    var resolve = qs('[data-thread-action="resolve"]', menu);
    if (high) high.textContent = state.label === 'high value' ? 'Remove high value' : 'Label high value';
    if (resolve) resolve.textContent = state.status === 'resolved' ? 'Reopen' : 'Mark resolved';
  }
  function polish() {
    improveSidebar();
    improveMessages();
    improveMenuLabels();
  }

  root.addEventListener('click', function (event) {
    var actionButton = event.target.closest('[data-thread-action]');
    if (actionButton && handleThreadAction(event, actionButton)) return;
    var attachmentButton = event.target.closest('[data-attachment-tray] button');
    if (attachmentButton && handleAttachmentButton(event, attachmentButton)) return;
  }, true);

  var observer = new MutationObserver(function () { debounce('polish', polish, 80); });
  observer.observe(root, { childList: true, subtree: true });
  polish();
});
