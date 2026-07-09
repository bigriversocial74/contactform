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
    link.textContent = 'Create Instant Win Reward';
    quick.insertBefore(link, quick.firstChild);
  }

  function ensureFields() {
    if (root.querySelector('[data-campaign-type-fields="instant_win_reward"]')) return;
    var card = document.createElement('div');
    card.className = 'mg-campaign-rule-card';
    card.setAttribute('data-campaign-type-fields', 'instant_win_reward');
    card.hidden = true;
    card.innerHTML = '<span class="mg-eyebrow">Spin / Scratch Instant Win</span><h3>Run a scratch-reveal game that can issue a reward instantly.</h3><p>v1 uses a scratch-card reveal. Winners reuse the standard wallet, Inbox, PPPM, CRM, and merchant notification flow. No-win plays are still recorded as CRM events.</p><div class="mg-grid-2"><label>Win odds percent<input name="instant_win_odds_percent" inputmode="numeric" placeholder="100"></label><label>No-win message<input name="instant_win_no_win_message" maxlength="240" placeholder="Not a winner this time — thanks for playing."></label></div><p class="mg-form-hint">Until custom rules persistence is expanded, v1 defaults to 100% win unless rules_json is populated by an integration.</p>';
    var before = root.querySelector('[data-campaign-type-fields="watch_video_reward"]') || root.querySelector('[data-campaign-type-fields="customer_refund"]');
    if (before && before.parentNode) before.parentNode.insertBefore(card, before);
    else {
      var status = root.querySelector('[data-stage12-campaign-status]');
      if (status && status.parentNode) status.parentNode.insertBefore(card, status);
    }
  }

  function applyDefaults(force) {
    if (activeType() !== 'instant_win_reward') return;
    setIfEmpty('title', 'Scratch to win a local reward');
    setIfEmpty('form_headline', 'Scratch and reveal your instant win');
    setIfEmpty('description', 'Enter your info, reveal the scratch card, and see if you unlocked a Microgifter reward.');
    setIfEmpty('form_description', 'Every play is tracked in the merchant CRM. Winners receive a reward in their Microgifter Inbox.');
    setIfEmpty('success_message', 'Instant win result recorded.');
    setIfEmpty('quantity_limit', '100');
    setIfEmpty('per_user_limit', '1');
    setIfEmpty('instant_win_odds_percent', '100');
    setIfEmpty('instant_win_no_win_message', 'Not a winner this time — thanks for playing.');
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
