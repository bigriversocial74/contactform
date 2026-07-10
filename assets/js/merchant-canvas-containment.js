window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = document.querySelector('[data-merchant-canvas]');
  if (!root || !MG || typeof MG.post !== 'function') return;

  var blockedRoutes = [
    '/api/merchant-canvas/auto-chat.php',
    '/api/merchant-canvas/campaign-trigger.php',
    '/api/merchant-canvas/campaign-trigger-automation.php'
  ];
  var basePost = MG.post.bind(MG);
  var rewardOptionsPromise = null;

  function payload(response) {
    return response && response.data ? response.data : response;
  }

  function isBlockedRoute(url) {
    var requestUrl = String(url || '').split('?')[0];
    return blockedRoutes.indexOf(requestUrl) !== -1;
  }

  function containmentError() {
    var error = new Error('Automatic Store Canvas messages and rewards are paused by production containment. Use an explicit manual customer action.');
    error.code = 'merchant_canvas_automatic_actions_disabled';
    error.status = 409;
    return error;
  }

  MG.post = function (url) {
    if (isBlockedRoute(url)) return Promise.reject(containmentError());
    return basePost.apply(MG, arguments);
  };
  MG.merchantCanvasContainment = {
    active: true,
    automaticActionsEnabled: false,
    blockedRoutes: blockedRoutes.slice()
  };

  function loadRewardOptions() {
    if (rewardOptionsPromise) return rewardOptionsPromise;
    if (typeof MG.get !== 'function') return Promise.resolve({ campaigns: [] });
    rewardOptionsPromise = MG.get('/api/merchant-canvas/reward-options.php').then(function (response) {
      var data = payload(response) || {};
      data.campaigns = Array.isArray(data.campaigns) ? data.campaigns : [];
      return data;
    }).catch(function () {
      return { campaigns: [] };
    });
    return rewardOptionsPromise;
  }

  function rewardSummary(form) {
    var node = form.querySelector('[data-containment-reward-summary]');
    if (node) return node;
    node = document.createElement('p');
    node.className = 'mg-canvas-containment-reward-summary';
    node.setAttribute('data-containment-reward-summary', '');
    var campaignSelect = form.querySelector('select[name="campaign_id"]');
    var campaignField = campaignSelect ? campaignSelect.closest('label') : null;
    if (campaignField) campaignField.insertAdjacentElement('afterend', node);
    else form.insertBefore(node, form.firstChild);
    return node;
  }

  function applyCampaignTemplate(form, options) {
    var campaignSelect = form.querySelector('select[name="campaign_id"]');
    var templateSelect = form.querySelector('select[name="reward_template_id"]');
    var submit = form.querySelector('[data-reward-submit]');
    var summary = rewardSummary(form);
    if (!campaignSelect || !templateSelect || !summary) return;

    var campaigns = Array.isArray(options.campaigns) ? options.campaigns : [];
    var campaign = campaigns.find(function (item) {
      return String(item.id || '') === String(campaignSelect.value || '');
    }) || null;
    var attachedTemplateId = campaign && campaign.reward_template_id ? String(campaign.reward_template_id) : '';

    templateSelect.value = attachedTemplateId;
    templateSelect.disabled = true;
    var templateLabel = templateSelect.closest('label');
    if (templateLabel) templateLabel.hidden = true;

    if (!campaign) {
      summary.textContent = 'Select an active campaign. Its configured reward will be used automatically.';
      summary.classList.remove('is-error');
      if (submit) submit.disabled = true;
      return;
    }

    if (!attachedTemplateId) {
      summary.textContent = 'This campaign does not have an active attached reward template. Update the campaign before sending a reward.';
      summary.classList.add('is-error');
      if (submit) submit.disabled = true;
      return;
    }

    summary.textContent = 'Campaign reward: ' + String(campaign.reward_template_title || 'Attached reward template') + '. The reward cannot be substituted from Store Canvas.';
    summary.classList.remove('is-error');
    if (submit) submit.disabled = false;
  }

  function bindRewardForm(form) {
    if (!form || form.dataset.containmentRewardBound === '1') return;
    form.dataset.containmentRewardBound = '1';
    loadRewardOptions().then(function (options) {
      applyCampaignTemplate(form, options);
      var campaignSelect = form.querySelector('select[name="campaign_id"]');
      if (campaignSelect) {
        campaignSelect.addEventListener('change', function () {
          applyCampaignTemplate(form, options);
        });
      }
    });
  }

  function enforceContainmentUi() {
    root.classList.add('is-production-contained');
    document.querySelectorAll('[data-reward-form]').forEach(bindRewardForm);
    document.querySelectorAll('[data-canvas-add-trigger], [data-persistent-trigger-button], .mg-canvas-trigger-add-btn').forEach(function (node) {
      node.remove();
    });
    document.querySelectorAll('.mg-canvas-trigger-zone, [data-canvas-persistent-zone]').forEach(function (node) {
      node.remove();
    });
  }

  enforceContainmentUi();
  new MutationObserver(function () {
    window.requestAnimationFrame(enforceContainmentUi);
  }).observe(document.body, { childList: true, subtree: true });

  document.dispatchEvent(new CustomEvent('mg:merchantCanvasContainmentReady', {
    detail: MG.merchantCanvasContainment
  }));
})(window, document);
