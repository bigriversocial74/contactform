document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-campaign-command-center]');
  var form = root && root.querySelector('[data-stage12-campaign-builder]');
  if (!root || !form) return;

  function field(name) { return form.elements[name] || null; }
  function value(name) { var item = field(name); return item ? String(item.value || '').trim() : ''; }
  function setIfEmpty(name, next) { var item = field(name); if (item && !String(item.value || '').trim()) item.value = next; }
  function activeType() { return value('campaign_type') || 'newsletter_signup'; }

  function ensureQuickAction() {
    var quick = root.querySelector('.mg-campaign-actions .mg-app-panel-body');
    if (!quick || quick.querySelector('[data-campaign-type-preset="rsvp_event_reward"]')) return;
    var link = document.createElement('a');
    link.href = '#campaign-create';
    link.setAttribute('data-campaign-tab-trigger', 'create');
    link.setAttribute('data-campaign-type-preset', 'rsvp_event_reward');
    link.textContent = 'Create RSVP Event Reward';
    quick.insertBefore(link, quick.firstChild);
  }

  function ensureFields() {
    if (root.querySelector('[data-campaign-type-fields="rsvp_event_reward"]')) return;
    var card = document.createElement('div');
    card.className = 'mg-campaign-rule-card';
    card.setAttribute('data-campaign-type-fields', 'rsvp_event_reward');
    card.hidden = true;
    card.innerHTML = '<span class="mg-eyebrow">RSVP / Event Attendance Reward</span><h3>Capture RSVPs and issue rewards after attendance is confirmed.</h3><p>RSVP submissions create contacts, campaign events, and CRM timeline entries. Attendance-code confirmation uses the normal wallet, Inbox, PPPM, and notification flow.</p><div class="mg-grid-2"><label>Event name<input name="rsvp_event_name" maxlength="160" placeholder="Merchant event"></label><label>Event date/time<input name="rsvp_event_date" maxlength="80" placeholder="Friday at 7 PM"></label></div><label>Attendance code<input name="rsvp_attendance_code" maxlength="64" placeholder="EVENT-123"></label><p class="mg-form-hint">v1 reads attendance code from rules_json. If no code is configured, public submissions record RSVPs only and do not issue rewards.</p>';
    var before = root.querySelector('[data-campaign-type-fields="watch_video_reward"]') || root.querySelector('[data-campaign-type-fields="customer_refund"]');
    if (before && before.parentNode) before.parentNode.insertBefore(card, before);
    else {
      var status = root.querySelector('[data-stage12-campaign-status]');
      if (status && status.parentNode) status.parentNode.insertBefore(card, status);
    }
  }

  function applyDefaults(force) {
    if (activeType() !== 'rsvp_event_reward') return;
    setIfEmpty('title', 'RSVP and earn an attendance reward');
    setIfEmpty('form_headline', 'Reserve your spot');
    setIfEmpty('description', 'RSVP for this merchant event. When attendance is confirmed, Microgifter sends the reward to your Inbox.');
    setIfEmpty('form_description', 'Enter your info to RSVP. Rewards are issued after event attendance is confirmed.');
    setIfEmpty('success_message', 'RSVP recorded. Attendance reward eligibility will be checked at the event.');
    setIfEmpty('per_user_limit', '1');
    setIfEmpty('rsvp_event_name', 'Merchant event');
  }

  function syncVisibility() {
    root.querySelectorAll('[data-campaign-type-fields="rsvp_event_reward"]').forEach(function (panel) {
      panel.hidden = activeType() !== 'rsvp_event_reward';
    });
    applyDefaults(false);
  }

  ensureQuickAction();
  ensureFields();
  form.addEventListener('change', function (event) {
    if (event.target && event.target.name === 'campaign_type') syncVisibility();
  });
  root.addEventListener('click', function (event) {
    var preset = event.target && event.target.getAttribute && event.target.getAttribute('data-campaign-type-preset');
    if (preset === 'rsvp_event_reward') window.setTimeout(function () { applyDefaults(true); syncVisibility(); }, 50);
  });
  syncVisibility();
});
