document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  if (!window.Microgifter || !document.querySelector('[data-merchant-crm-app]')) return;

  function qs(selector, root) { return (root || document).querySelector(selector); }
  function qsa(selector, root) { return Array.prototype.slice.call((root || document).querySelectorAll(selector)); }
  function toast(message) { if (window.Microgifter.toast) window.Microgifter.toast(message); else alert(message); }
  function esc(value) { return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]; }); }
  function setStatus(selector, message, type) { var node = qs(selector); if (node) { node.textContent = message || ''; node.dataset.type = type || ''; node.dataset.statusType = type || ''; } }
  function selectedContactIds() { return qsa('tr[data-contact-id] [data-crm-contact-check]:checked').map(function (box) { var row = box.closest('tr[data-contact-id]'); return row && row.getAttribute('data-contact-id'); }).filter(Boolean); }
  function isFuture(value) { var ts = Date.parse(String(value || '').replace(' ', 'T')); return Number.isFinite(ts) && ts > Date.now() - 60000; }
  function actionCenterContactId() { var row = qs('tr[data-contact-id] [data-crm-gift]:focus'); row = row && row.closest('tr[data-contact-id]'); if (row) return row.getAttribute('data-contact-id'); var title = qs('[data-crm-action-subtitle]'); var email = title ? String(title.textContent || '').split(' · ')[1] || '' : ''; if (!email) return ''; var match = qsa('tr[data-contact-id]').find(function (tr) { return String(tr.getAttribute('data-contact-email') || '').toLowerCase() === String(email).toLowerCase(); }); return match ? match.getAttribute('data-contact-id') : ''; }
  function selectedCampaignId() { var selected = qs('[data-crm-campaign-select].is-selected, .mg-crm-campaign-card.is-selected'); return selected ? selected.getAttribute('data-crm-campaign-select') : ''; }
  function normalActionLabel() {
    var summary = qs('[data-crm-action-footer-summary]');
    var text = summary ? String(summary.textContent || '').toLowerCase() : '';
    return text.indexOf('invite fallback') >= 0 ? 'Send wallet invite' : 'Send to customer';
  }
  function syncActionScheduleButton() {
    var send = qs('[data-crm-action-send]');
    var schedule = qs('[data-crm-action-schedule]');
    var scheduledAt = (qs('[data-crm-action-scheduled-at]') || {}).value || '';
    var help = qs('[data-crm-action-schedule-help]');
    if (!send || !schedule) return;
    if (schedule.checked) {
      send.textContent = 'Schedule Send Gift';
      send.setAttribute('aria-label', 'Schedule send gift');
      if (help) help.textContent = isFuture(scheduledAt) ? 'Schedule settings are applied to this action. Click Schedule Send Gift to save it.' : 'Choose a future date and time, then click Schedule Send Gift.';
    } else if (/schedule/i.test(send.textContent || '')) {
      send.textContent = normalActionLabel();
      send.removeAttribute('aria-label');
      if (help) help.textContent = 'No separate save needed. These settings apply to this send and are saved when you send or schedule it.';
    } else if (help) {
      help.textContent = 'No separate save needed. These settings apply to this send and are saved when you send or schedule it.';
    }
  }

  function ensureActionScheduleControls() {
    var notes = qs('[data-crm-action-section="notes"] .mg-crm-action-note');
    if (!notes || qs('[data-crm-action-schedule]', notes)) return;
    notes.insertAdjacentHTML('beforeend', '<label class="mg-crm-action-toggle"><input type="checkbox" data-crm-action-schedule> Schedule this send</label><label data-crm-action-schedule-wrap hidden>Scheduled send time<input class="mg-input" type="datetime-local" data-crm-action-scheduled-at></label><p class="mg-crm-action-helper" data-crm-action-schedule-help>No separate save needed. These settings apply to this send and are saved when you send or schedule it.</p>');
    syncActionScheduleButton();
  }

  function ensureBulkScheduleControls() {
    var form = qs('[data-crm-bulk-form]');
    if (!form || qs('[data-crm-bulk-schedule]', form)) return;
    var results = qs('[data-crm-bulk-results]', form);
    var html = '<label class="mg-crm-field" data-crm-bulk-schedule-field><span>Delivery timing</span><label class="mg-crm-inline-check"><input type="checkbox" data-crm-bulk-schedule> Schedule this bulk action</label><input class="mg-input" type="datetime-local" data-crm-bulk-scheduled-at hidden><small data-crm-bulk-schedule-help>No separate save needed. The batch is saved when you run or schedule the action.</small></label>';
    if (results) results.insertAdjacentHTML('beforebegin', html); else form.insertAdjacentHTML('beforeend', html);
  }

  var patchTimer = null;
  function patchControls() { ensureActionScheduleControls(); ensureBulkScheduleControls(); syncActionScheduleButton(); }
  function schedulePatch(delay) {
    window.clearTimeout(patchTimer);
    patchTimer = window.setTimeout(patchControls, delay || 0);
  }
  patchControls();

  document.addEventListener('click', function (event) {
    if (!event.target || !event.target.closest) return;
    if (event.target.closest('[data-crm-gift],[data-crm-reward],[data-crm-action-tab],[data-crm-campaign-select],[data-crm-bulk-action]')) schedulePatch(150);
  }, false);

  document.addEventListener('change', function (event) {
    var target = event.target;
    if (!target) return;
    if (target.matches('[data-crm-action-schedule]')) {
      var wrap = qs('[data-crm-action-schedule-wrap]');
      if (wrap) wrap.hidden = !target.checked;
      syncActionScheduleButton();
    }
    if (target.matches('[data-crm-action-scheduled-at]')) syncActionScheduleButton();
    if (target.matches('[data-crm-bulk-schedule]')) {
      var input = qs('[data-crm-bulk-scheduled-at]');
      if (input) input.hidden = !target.checked;
    }
  });
  document.addEventListener('input', function (event) {
    if (event.target && event.target.matches('[data-crm-action-scheduled-at]')) syncActionScheduleButton();
  });

  window.addEventListener('click', async function (event) {
    var send = event.target && event.target.closest && event.target.closest('[data-crm-action-send]');
    if (!send) return;
    var schedule = qs('[data-crm-action-schedule]');
    if (!schedule || !schedule.checked) return;
    var scheduledAt = (qs('[data-crm-action-scheduled-at]') || {}).value || '';
    event.preventDefault();
    event.stopImmediatePropagation();
    if (!isFuture(scheduledAt)) { setStatus('[data-crm-action-status]', 'Choose a valid future scheduled send time.', 'error'); syncActionScheduleButton(); return; }
    var contactId = actionCenterContactId();
    var campaignId = selectedCampaignId();
    if (!contactId || !campaignId) { setStatus('[data-crm-action-status]', 'Choose a contact and campaign before scheduling.', 'error'); syncActionScheduleButton(); return; }
    var note = ((qs('[data-crm-action-note]') || {}).value || '').trim();
    var reason = ((qs('[data-crm-action-reason]') || {}).value || 'manual_promo').replace(/_/g, ' ');
    var sendMessage = !!((qs('[data-crm-action-send-message]') || {}).checked);
    var message = ((qs('[data-crm-action-message]') || {}).value || '').trim();
    var allowDuplicate = !!((qs('[data-crm-action-allow-duplicate]') || {}).checked);
    send.disabled = true;
    var original = send.textContent;
    send.textContent = 'Scheduling...';
    setStatus('[data-crm-action-status]', 'Scheduling CRM action...', '');
    try {
      var response = await Microgifter.post('/api/merchant/crm-scheduled-actions.php', {
        action_type: 'campaign_reward',
        contact_ids: [contactId],
        campaign_id: campaignId,
        note: 'Reason: ' + reason + (note ? '\n' + note : ''),
        send_message: sendMessage,
        message: message,
        allow_duplicate: allowDuplicate,
        scheduled_at: scheduledAt,
        idempotency_key: 'crm-action-schedule-ui:' + contactId + ':' + campaignId + ':' + Date.now()
      });
      var data = response.data || response;
      setStatus('[data-crm-action-status]', 'Scheduled for ' + scheduledAt + '. Batch: ' + (data.batch_id || ''), 'success');
      send.textContent = 'Scheduled Send Gift';
      toast('CRM action scheduled.');
    } catch (error) {
      setStatus('[data-crm-action-status]', error.message || 'Unable to schedule CRM action.', 'error');
      send.textContent = original;
    } finally {
      send.disabled = false;
      if (schedule.checked && !/^Scheduled Send Gift$/i.test(send.textContent || '')) syncActionScheduleButton();
    }
  }, true);

  window.addEventListener('submit', async function (event) {
    var form = event.target;
    if (!form || !form.matches || !form.matches('[data-crm-bulk-form]')) return;
    var schedule = qs('[data-crm-bulk-schedule]', form);
    if (!schedule || !schedule.checked) return;
    var scheduledAt = (qs('[data-crm-bulk-scheduled-at]', form) || {}).value || '';
    event.preventDefault();
    event.stopImmediatePropagation();
    if (!isFuture(scheduledAt)) { setStatus('[data-crm-bulk-status]', 'Choose a valid future scheduled send time.', 'error'); return; }
    var ids = selectedContactIds();
    if (!ids.length) { setStatus('[data-crm-bulk-status]', 'Select contacts before scheduling.', 'error'); return; }
    var title = qs('[data-crm-bulk-title]', form);
    var isMessage = title && /message/i.test(title.textContent || '');
    var isReward = title && /reward|invite/i.test(title.textContent || '');
    var payload = { contact_ids: ids, scheduled_at: scheduledAt, idempotency_key: 'crm-bulk-schedule-ui:' + Date.now() };
    if (isMessage) {
      payload.action_type = 'message';
      payload.message = ((qs('[data-crm-bulk-message]', form) || {}).value || '').trim();
    } else if (isReward) {
      payload.action_type = 'reward_template';
      payload.reward_template_id = (qs('[data-crm-bulk-template]', form) || {}).value || '';
      payload.note = ((qs('[data-crm-bulk-note]', form) || {}).value || '').trim();
    } else {
      setStatus('[data-crm-bulk-status]', 'Scheduling is available for bulk messages and reward/invite actions.', 'error');
      return;
    }
    var btn = qs('[data-crm-bulk-submit]', form);
    var original = btn ? btn.textContent : '';
    if (btn) { btn.disabled = true; btn.textContent = 'Scheduling...'; }
    setStatus('[data-crm-bulk-status]', 'Scheduling bulk CRM action...', '');
    try {
      var response = await Microgifter.post('/api/merchant/crm-scheduled-actions.php', payload);
      var data = response.data || response;
      var box = qs('[data-crm-bulk-results]', form);
      if (box) {
        box.hidden = false;
        box.innerHTML = '<strong>Scheduled batch</strong><p>' + esc((data.summary && data.summary.scheduled) || ids.length) + ' actions scheduled for ' + esc(scheduledAt) + '. Batch: ' + esc(data.batch_id || '') + '</p>';
      }
      setStatus('[data-crm-bulk-status]', 'Bulk action scheduled.', 'success');
      toast('Bulk CRM action scheduled.');
    } catch (error) {
      setStatus('[data-crm-bulk-status]', error.message || 'Unable to schedule bulk CRM action.', 'error');
    } finally {
      if (btn) { btn.disabled = false; btn.textContent = original; }
    }
  }, true);
});
