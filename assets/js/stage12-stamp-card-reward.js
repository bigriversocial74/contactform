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
    if (!quick || quick.querySelector('[data-campaign-type-preset="stamp_card_reward"]')) return;
    var link = document.createElement('a');
    link.href = '#campaign-create';
    link.setAttribute('data-campaign-tab-trigger', 'create');
    link.setAttribute('data-campaign-type-preset', 'stamp_card_reward');
    link.textContent = 'Create Verified Stamp Card';
    quick.insertBefore(link, quick.firstChild);
  }

  function ensureFields() {
    if (root.querySelector('[data-campaign-type-fields="stamp_card_reward"]')) return;
    var card = document.createElement('div');
    card.className = 'mg-campaign-rule-card';
    card.setAttribute('data-campaign-type-fields', 'stamp_card_reward');
    card.hidden = true;
    card.innerHTML = '<span class="mg-eyebrow">Stamp Card / Visit Tracker</span><h3>Track verified repeat visits until a reward unlocks.</h3><p>The public page uses a large dynamic punch-card canvas. Each customer has unique progress per campaign. Cashier verification is required before a stamp becomes official.</p><div class="mg-grid-2"><label>Required stamps<input name="stamp_required_count" inputmode="numeric" placeholder="5"></label><label>Stamp label<input name="stamp_label" maxlength="40" placeholder="Visit"></label></div><div class="mg-grid-2"><label>Cooldown hours<input name="stamp_cooldown_hours" inputmode="numeric" placeholder="0"></label><label class="mg-campaign-check"><input type="checkbox" name="stamp_cashier_verification_required" value="1" checked> <span>Require cashier claim code</span></label></div><label class="mg-campaign-check"><input type="checkbox" name="stamp_card_reward_enabled" value="1" checked> <span>Issue reward when the verified card is full</span></label><p class="mg-form-hint">Partial verified stamps track campaign progress only. The merchant CRM contact is created/promoted when the first reward/value is issued.</p>';
    var before = root.querySelector('[data-campaign-type-fields="watch_video_reward"]') || root.querySelector('[data-campaign-type-fields="customer_refund"]');
    if (before && before.parentNode) before.parentNode.insertBefore(card, before);
    else {
      var status = root.querySelector('[data-stage12-campaign-status]');
      if (status && status.parentNode) status.parentNode.insertBefore(card, status);
    }
  }

  function applyDefaults(force) {
    if (activeType() !== 'stamp_card_reward') return;
    setIfEmpty('title', 'Collect verified stamps and unlock a reward');
    setIfEmpty('form_headline', 'Add a verified stamp to your card');
    setIfEmpty('description', 'Ask the cashier to verify your visit or purchase. When your stamp card is full, Microgifter sends the reward to your Inbox.');
    setIfEmpty('form_description', 'Enter your info and have the cashier enter the merchant claim code to make today’s stamp official.');
    setIfEmpty('success_message', 'Verified stamp recorded. Reward unlock checked.');
    setIfEmpty('per_user_limit', '1');
    setIfEmpty('stamp_required_count', '5');
    setIfEmpty('stamp_label', 'Visit');
    setIfEmpty('stamp_cooldown_hours', '0');
    if (force) {
      var enabled = field('stamp_card_reward_enabled');
      if (enabled) enabled.checked = true;
      var cashier = field('stamp_cashier_verification_required');
      if (cashier) cashier.checked = true;
    }
  }

  function syncVisibility() {
    root.querySelectorAll('[data-campaign-type-fields="stamp_card_reward"]').forEach(function (panel) {
      panel.hidden = activeType() !== 'stamp_card_reward';
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
    if (preset === 'stamp_card_reward') window.setTimeout(function () { applyDefaults(true); syncVisibility(); }, 50);
  });
  syncVisibility();
});