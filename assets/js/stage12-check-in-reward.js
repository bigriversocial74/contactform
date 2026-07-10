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
    if (!quick || quick.querySelector('[data-campaign-type-preset="check_in_reward"]')) return;
    var link = document.createElement('a');
    link.href = '#campaign-create';
    link.setAttribute('data-campaign-tab-trigger', 'create');
    link.setAttribute('data-campaign-type-preset', 'check_in_reward');
    link.textContent = 'Create Check-In Reward';
    quick.insertBefore(link, quick.firstChild);
  }

  function ensureFields() {
    if (root.querySelector('[data-campaign-type-fields="check_in_reward"]')) return;
    var card = document.createElement('div');
    card.className = 'mg-campaign-rule-card';
    card.setAttribute('data-campaign-type-fields', 'check_in_reward');
    card.hidden = true;
    card.innerHTML = '<span class="mg-eyebrow">Check-In Reward</span><h3>Configure the campaign check-in range.</h3><p>The public page can verify the customer against an active merchant location.</p><div class="mg-grid-2"><label>Campaign radius meters<input name="check_in_radius_meters" type="number" min="25" max="5000" inputmode="numeric" placeholder="150"></label><label class="mg-campaign-check"><input type="hidden" name="check_in_location_required" value="0"><input type="checkbox" name="check_in_location_required" value="1" checked> <span>Require a location match before reward issue</span></label></div><p class="mg-form-hint">Add coordinates to Merchant Locations before launching a required check-in campaign.</p>';
    var before = root.querySelector('[data-campaign-type-fields="watch_video_reward"]') || root.querySelector('[data-campaign-type-fields="customer_refund"]');
    if (before && before.parentNode) before.parentNode.insertBefore(card, before);
    else {
      var status = root.querySelector('[data-stage12-campaign-status]');
      if (status && status.parentNode) status.parentNode.insertBefore(card, status);
    }
  }

  function applyDefaults(force) {
    if (activeType() !== 'check_in_reward') return;
    setIfEmpty('title', 'Check in and get a reward');
    setIfEmpty('form_headline', 'Check in at this location');
    setIfEmpty('description', 'Use your browser location to verify you are near a registered merchant location and unlock a Microgifter reward.');
    setIfEmpty('form_description', 'Allow location access, enter your info, and Microgifter will match you to the nearest registered merchant location.');
    setIfEmpty('success_message', 'Check-in verified. Your reward has been sent.');
    setIfEmpty('per_user_limit', '1');
    setIfEmpty('check_in_radius_meters', '150');
    if (force) {
      var required = form.querySelector('input[type="checkbox"][name="check_in_location_required"]');
      if (required) required.checked = true;
    }
  }

  function syncVisibility() {
    root.querySelectorAll('[data-campaign-type-fields="check_in_reward"]').forEach(function (panel) {
      panel.hidden = activeType() !== 'check_in_reward';
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
    if (preset === 'check_in_reward') window.setTimeout(function () { applyDefaults(true); syncVisibility(); }, 50);
  });
  syncVisibility();
});
