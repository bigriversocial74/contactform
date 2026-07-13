(function(){
  'use strict';

  window.MicrogifterCampaignTypes = Array.isArray(window.MicrogifterCampaignTypes) ? window.MicrogifterCampaignTypes : [];
  if (!window.MicrogifterCampaignTypes.some(function(item){ return item && item.key === 'customer_review'; })) {
    window.MicrogifterCampaignTypes.push({
      key: 'customer_review',
      label: 'CUSTOMER REVIEW',
      category: 'loyalty_retention',
      description: 'Collect a five-star customer review from the merchant profile and issue the attached reward through Wallet → Inbox PPPM.',
      public_path: '/profile.php',
      source_type: 'customer_review',
      wallet_issue_mode: 'review_reward',
      internal_only: false,
      public_enabled: true,
      default_copy: {
        title: 'Share a review and receive a reward',
        form_headline: 'How was your experience?',
        description: 'Invite customers to leave a verified five-star review from your Microgifter profile.',
        form_description: 'Rate your experience, write a review, and receive the attached reward in your wallet and Microgifter Inbox.',
        success_message: 'Review submitted. Your reward is in your wallet and Microgifter Inbox.',
        quantity_limit: '',
        per_user_limit: '1',
        review_prompt: 'Tell us about your experience.',
        review_max_per_period: '1',
        review_limit_period: 'month'
      }
    });
  }

  function init(){
    var root = document.querySelector('[data-campaign-command-center]');
    var form = root && root.querySelector('[data-stage12-campaign-builder]');
    if (!root || !form) return;

    var typeSelect = form.elements.campaign_type;
    var status = form.querySelector('[data-stage12-campaign-status]');
    var MG = window.Microgifter || {};

    function setStatus(message, type){
      if (MG.setStatus) {
        MG.setStatus(status, message, type);
      } else if (status) {
        status.textContent = message || '';
        status.classList.toggle('is-error', type === 'error');
        status.classList.toggle('is-success', type === 'success');
      }
    }

    function ensureOption(){
      if (!typeSelect || Array.prototype.some.call(typeSelect.options, function(option){ return option.value === 'customer_review'; })) return;
      var option = document.createElement('option');
      option.value = 'customer_review';
      option.textContent = 'CUSTOMER REVIEW';
      var refund = Array.prototype.find.call(typeSelect.options, function(item){ return item.value === 'customer_refund'; });
      if (refund) typeSelect.insertBefore(option, refund);
      else typeSelect.appendChild(option);
    }

    function ensureFields(){
      if (root.querySelector('[data-campaign-type-fields="customer_review"]')) return;
      var card = document.createElement('div');
      card.className = 'mg-campaign-rule-card';
      card.setAttribute('data-campaign-type-fields', 'customer_review');
      card.hidden = true;
      card.innerHTML =
        '<span class="mg-eyebrow">CUSTOMER REVIEW</span>' +
        '<h3>Collect five-star profile reviews and reward verified customers.</h3>' +
        '<p>The customer submits the review from the Reviews tab on your public profile. Each accepted review creates a wallet reward, then the existing PPPM bridge delivers it into the customer Inbox.</p>' +
        '<label>Review prompt<textarea name="review_prompt" maxlength="500" placeholder="Tell us about your experience."></textarea></label>' +
        '<div class="mg-grid-2">' +
          '<label>Maximum reviews per customer<input name="review_max_per_period" type="number" min="1" max="1000" value="1" required></label>' +
          '<label>Limit period<select name="review_limit_period">' +
            '<option value="day">Day</option>' +
            '<option value="week">Week</option>' +
            '<option value="month" selected>Month</option>' +
            '<option value="quarter">Quarter</option>' +
            '<option value="year">Year</option>' +
          '</select></label>' +
        '</div>' +
        '<div class="mg-form-status">Reward destination: Wallet → Inbox PPPM.</div>';

      var before = root.querySelector('[data-campaign-type-fields="customer_refund"]');
      if (before && before.parentNode) before.parentNode.insertBefore(card, before);
      else if (status && status.parentNode) status.parentNode.insertBefore(card, status);
    }

    function ensureQuickAction(){
      var quick = root.querySelector('.mg-campaign-actions .mg-app-panel-body');
      if (!quick || quick.querySelector('[data-campaign-type-preset="customer_review"]')) return;
      var link = document.createElement('a');
      link.href = '#campaign-create';
      link.setAttribute('data-campaign-tab-trigger', 'create');
      link.setAttribute('data-campaign-type-preset', 'customer_review');
      link.textContent = 'Create Customer Review';
      quick.insertBefore(link, quick.firstChild);
    }

    function activeType(){
      return typeSelect ? String(typeSelect.value || '') : '';
    }

    function syncDefaults(force){
      if (activeType() !== 'customer_review') return;
      var defaults = {
        title: 'Share a review and receive a reward',
        form_headline: 'How was your experience?',
        description: 'Invite customers to leave a verified five-star review from your Microgifter profile.',
        form_description: 'Rate your experience, write a review, and receive the attached reward in your wallet and Microgifter Inbox.',
        success_message: 'Review submitted. Your reward is in your wallet and Microgifter Inbox.',
        review_prompt: 'Tell us about your experience.',
        review_max_per_period: '1',
        review_limit_period: 'month',
        per_user_limit: '1'
      };
      Object.keys(defaults).forEach(function(name){
        var field = form.elements[name];
        if (field && (force || !String(field.value || '').trim())) field.value = defaults[name];
      });
    }

    async function restoreRulesForEdit(){
      var id = form.elements.campaign_id ? String(form.elements.campaign_id.value || '') : '';
      if (!id || activeType() !== 'customer_review' || !MG.get) return;
      try {
        var response = await MG.get('/api/merchant/campaigns.php');
        var campaigns = ((response && response.data) || response || {}).campaigns || [];
        var item = campaigns.find(function(campaign){ return String(campaign.id) === id; });
        var rules = item && item.rules || {};
        if (form.elements.review_prompt) form.elements.review_prompt.value = rules.prompt || 'Tell us about your experience.';
        if (form.elements.review_max_per_period) form.elements.review_max_per_period.value = rules.max_reviews_per_period || item.per_user_limit || 1;
        if (form.elements.review_limit_period) form.elements.review_limit_period.value = rules.limit_period || 'month';
      } catch (error) {}
    }

    ensureOption();
    ensureFields();
    ensureQuickAction();

    if (typeSelect) {
      typeSelect.addEventListener('change', function(){
        if (activeType() === 'customer_review') syncDefaults(!form.elements.campaign_id || !form.elements.campaign_id.value);
      });
    }

    root.addEventListener('click', function(event){
      var preset = event.target.closest('[data-campaign-type-preset="customer_review"]');
      if (preset) window.setTimeout(function(){ syncDefaults(true); }, 40);
      var edit = event.target.closest('[data-campaign-edit-id]');
      if (edit) window.setTimeout(restoreRulesForEdit, 350);
    });

    form.addEventListener('submit', function(event){
      if (activeType() !== 'customer_review') return;
      event.preventDefault();
      event.stopImmediatePropagation();

      var data = Object.fromEntries(new FormData(form).entries());
      data.campaign_type = 'customer_review';
      data.per_user_limit = data.review_max_per_period || '1';

      if (!String(data.reward_template_id || '').trim() && String(data.status || '') === 'active') {
        setStatus('Choose an active reward template before activating this Customer Review campaign.', 'error');
        if (form.elements.reward_template_id) form.elements.reward_template_id.focus();
        return;
      }

      if (!MG.post) {
        setStatus('Campaign API is unavailable.', 'error');
        return;
      }

      setStatus('Saving Customer Review campaign…');
      MG.post('/api/merchant/customer-review-campaign.php', data).then(function(response){
        setStatus(response && response.message ? response.message : 'Customer Review campaign saved.', 'success');
        window.setTimeout(function(){
          window.location.hash = '#campaign-overview';
          window.location.reload();
        }, 450);
      }).catch(function(error){
        setStatus(error && error.message ? error.message : 'Unable to save Customer Review campaign.', 'error');
      });
    }, true);

    syncDefaults(false);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();