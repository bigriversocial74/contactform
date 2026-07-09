document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var root = document.querySelector('[data-campaign-command-center]');
  if (!root) return;
  var form = root.querySelector('[data-stage12-campaign-builder]');
  if (!form || form.elements.participation_policy) return;

  function typeValue() {
    return form.elements.campaign_type ? String(form.elements.campaign_type.value || '') : '';
  }
  function defaultPolicy(type) {
    return (type === 'watch_video_reward' || type === 'listen_music_reward') ? 'account_recommended' : 'email_only';
  }
  function csrfToken() {
    var field = form.querySelector('[name="csrf_token"]');
    if (field && field.value) return field.value;
    return window.Microgifter && typeof Microgifter.getCsrfToken === 'function' ? Microgifter.getCsrfToken() : '';
  }

  var card = document.createElement('section');
  card.className = 'mg-campaign-rule-card mg-campaign-participation-policy-card';
  card.innerHTML = '' +
    '<span class="mg-eyebrow">Participation policy</span>' +
    '<h3>Choose how customers join this campaign.</h3>' +
    '<p data-campaign-policy-help></p>' +
    '<label>Account mode<select name="participation_policy" data-campaign-participation-policy>' +
      '<option value="email_only">Email only</option>' +
      '<option value="account_recommended">Account recommended</option>' +
      '<option value="account_required">Account required</option>' +
    '</select></label>';

  var anchor = form.querySelector('[name="quantity_limit"]');
  var before = anchor ? anchor.closest('.mg-grid-2') : form.querySelector('[data-stage12-campaign-status]');
  if (before && before.parentNode) before.parentNode.insertBefore(card, before); else form.appendChild(card);

  var select = form.elements.participation_policy;
  var help = card.querySelector('[data-campaign-policy-help]');
  var touched = false;

  function syncHelp() {
    var mode = select.value || defaultPolicy(typeValue());
    var copy = {
      email_only: 'Customers can join with name, email, and optional phone. This is the fastest low-friction path.',
      account_recommended: 'Customers can still join by email, but the page recommends signing in so Inbox delivery, PPPM tracking, reward history, and follow-up stay connected.',
      account_required: 'Customers must be signed in, and the submitted email must match their Microgifter account before rewards can be issued.'
    };
    if (help) help.textContent = copy[mode] || copy.email_only;
  }
  function applyDefault(force) {
    if (force || !touched || !select.value) select.value = defaultPolicy(typeValue());
    syncHelp();
  }

  select.addEventListener('change', function () { touched = true; syncHelp(); });
  if (form.elements.campaign_type) {
    form.elements.campaign_type.addEventListener('change', function () {
      if (!form.elements.campaign_id || !form.elements.campaign_id.value) {
        touched = false;
        applyDefault(true);
      } else {
        syncHelp();
      }
    });
  }

  async function fetchPolicyForCampaign(campaignId) {
    if (!campaignId || !window.Microgifter || typeof Microgifter.get !== 'function') return;
    try {
      var response = await Microgifter.get('/api/merchant/campaigns.php');
      var campaigns = (response.data || response).campaigns || [];
      var item = campaigns.find(function (campaign) { return String(campaign.id || '') === String(campaignId); });
      var mode = item && item.rules ? item.rules.participation_policy : '';
      if (mode) { select.value = mode; touched = true; syncHelp(); }
    } catch (error) {}
  }

  root.addEventListener('click', function (event) {
    var target = event.target && event.target.closest ? event.target.closest('[data-campaign-type-preset],[data-stage12-campaign-new],[data-campaign-edit-id]') : null;
    if (!target) return;
    window.setTimeout(function () {
      if (target.hasAttribute('data-campaign-edit-id')) fetchPolicyForCampaign(target.getAttribute('data-campaign-edit-id'));
      else { touched = false; applyDefault(true); }
    }, 80);
  });

  async function syncPolicy(campaignId, mode) {
    if (!campaignId || !mode || !window.Microgifter || typeof Microgifter.post !== 'function') return;
    try {
      await window.Microgifter.post('/api/merchant/campaign-participation-policy.php', {
        csrf_token: csrfToken(),
        campaign_id: campaignId,
        participation_policy: mode
      });
    } catch (error) {
      var status = form.querySelector('[data-stage12-campaign-status]');
      if (status) status.textContent = error.message || 'Campaign saved, but participation policy could not be saved.';
    }
  }

  function installPostBridge() {
    if (!window.Microgifter || typeof window.Microgifter.post !== 'function' || window.Microgifter.__campaignPolicyBridge) return;
    var originalPost = window.Microgifter.post.bind(window.Microgifter);
    window.Microgifter.__campaignPolicyBridge = true;
    window.Microgifter.post = async function (url) {
      var response = await originalPost.apply(window.Microgifter, arguments);
      if (String(url || '').indexOf('/api/merchant/campaigns.php') !== -1) {
        var data = response.data || response || {};
        var campaign = data.campaign || (data.data && data.data.campaign) || {};
        await syncPolicy(campaign.id || campaign.public_id || (form.elements.campaign_id ? form.elements.campaign_id.value : ''), select.value || defaultPolicy(typeValue()));
      }
      return response;
    };
  }

  installPostBridge();
  applyDefault(true);
});
