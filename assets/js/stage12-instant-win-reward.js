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
    if (!quick || quick.querySelector('[data-campaign-type-preset="instant_win_reward"]')) return;
    var link = document.createElement('a');
    link.href = '#campaign-create';
    link.setAttribute('data-campaign-tab-trigger', 'create');
    link.setAttribute('data-campaign-type-preset', 'instant_win_reward');
    link.textContent = 'Create Scratch / Spin Instant Win';
    quick.insertBefore(link, quick.firstChild);
  }

  function ensureFields() {
    if (root.querySelector('[data-campaign-type-fields="instant_win_reward"]')) return;
    var card = document.createElement('div');
    card.className = 'mg-campaign-rule-card';
    card.setAttribute('data-campaign-type-fields', 'instant_win_reward');
    card.hidden = true;
    card.innerHTML = '<span class="mg-eyebrow">Scratch / Spin Instant Win</span><h3>Run a mobile-friendly instant win experience.</h3><p>The public page uses the Watch/Listen style layout: interaction canvas on the left and merchant profile + form on the right. No-win plays do not create a merchant CRM contact until value is issued.</p><div class="mg-grid-2"><label>Instant win mode<select name="instant_win_mode"><option value="scratch_card">Scratch card</option><option value="spin_wheel">Spin wheel</option></select></label><label>Win odds percent<input name="instant_win_odds_percent" inputmode="numeric" placeholder="100"></label></div><div class="mg-grid-2"><label>No-win message<input name="instant_win_no_win_message" maxlength="240" placeholder="Not a winner this time — thanks for playing."></label><label class="mg-campaign-check"><input type="checkbox" name="instant_win_online_play" value="1" checked> <span>Online play, no cashier code</span></label></div><p class="mg-form-hint">Scratch-card artwork can be uploaded in the Media artwork panel. Spin Wheel mode uses the wheel canvas.</p>';
    var before = root.querySelector('[data-campaign-type-fields="watch_video_reward"]') || root.querySelector('[data-campaign-type-fields="customer_refund"]');
    if (before && before.parentNode) before.parentNode.insertBefore(card, before);
    else {
      var status = root.querySelector('[data-stage12-campaign-status]');
      if (status && status.parentNode) status.parentNode.insertBefore(card, status);
    }
  }

  function applyDefaults(force) {
    if (activeType() !== 'instant_win_reward') return;
    setIfEmpty('title', 'Scratch or spin to win a local reward');
    setIfEmpty('form_headline', 'Reveal your instant win');
    setIfEmpty('description', 'Enter your info, interact with the game, and see if you unlocked a Microgifter reward.');
    setIfEmpty('form_description', 'Complete the scratch or spin interaction before submitting. Winners receive a reward in their Microgifter Inbox.');
    setIfEmpty('success_message', 'Instant win result recorded.');
    setIfEmpty('quantity_limit', '100');
    setIfEmpty('per_user_limit', '1');
    setIfEmpty('instant_win_mode', 'scratch_card');
    setIfEmpty('instant_win_odds_percent', '100');
    setIfEmpty('instant_win_no_win_message', 'Not a winner this time — thanks for playing.');
    if (force) {
      var online = field('instant_win_online_play');
      if (online) online.checked = true;
    }
  }

  function syncVisibility() {
    root.querySelectorAll('[data-campaign-type-fields="instant_win_reward"]').forEach(function (panel) {
      panel.hidden = activeType() !== 'instant_win_reward';
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
    if (preset === 'instant_win_reward') window.setTimeout(function () { applyDefaults(true); syncVisibility(); }, 50);
  });
  syncVisibility();
});