document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-campaign-command-center]');
  var form = root && root.querySelector('[data-stage12-campaign-builder]');
  if (!root || !form) return;

  function field(name) { return form.elements[name] || null; }
  function value(name) { var item = field(name); return item ? String(item.value || '').trim() : ''; }
  function setIfEmpty(name, next) { var item = field(name); if (item && !String(item.value || '').trim()) item.value = next; }
  function activeType() { return value('campaign_type') || 'newsletter_signup'; }

  function ensureSurveyFields() {
    if (root.querySelector('[data-campaign-type-fields="survey_feedback_reward"]')) return;
    var card = document.createElement('div');
    card.className = 'mg-campaign-rule-card';
    card.setAttribute('data-campaign-type-fields', 'survey_feedback_reward');
    card.hidden = true;
    card.innerHTML = '<span class="mg-eyebrow">Survey / Feedback Reward</span><h3>Collect structured feedback before issuing the reward.</h3><p>Customers rate their experience, answer the prompt, and the response is attached to the campaign contact, CRM timeline, and reward issue trail.</p><label>Survey prompt<textarea name="survey_prompt" placeholder="Example: How was your experience?"></textarea></label><div class="mg-grid-2"><label class="mg-campaign-check"><input type="hidden" name="survey_rating_required" value="0"><input type="checkbox" name="survey_rating_required" value="1" checked> <span>Require a 1-5 rating</span></label><label class="mg-campaign-check"><input type="hidden" name="survey_feedback_required" value="0"><input type="checkbox" name="survey_feedback_required" value="1" checked> <span>Require written feedback</span></label></div>';
    var before = root.querySelector('[data-campaign-type-fields="watch_video_reward"]') || root.querySelector('[data-campaign-type-fields="customer_refund"]');
    if (before && before.parentNode) before.parentNode.insertBefore(card, before);
    else {
      var status = root.querySelector('[data-stage12-campaign-status]');
      if (status && status.parentNode) status.parentNode.insertBefore(card, status);
    }
  }

  function ensureQuickAction() {
    var quick = root.querySelector('.mg-campaign-actions .mg-app-panel-body');
    if (!quick || quick.querySelector('[data-campaign-type-preset="survey_feedback_reward"]')) return;
    var link = document.createElement('a');
    link.href = '#campaign-create';
    link.setAttribute('data-campaign-tab-trigger', 'create');
    link.setAttribute('data-campaign-type-preset', 'survey_feedback_reward');
    link.textContent = 'Create Survey Feedback Reward';
    quick.insertBefore(link, quick.firstChild);
  }

  function syncPromptToCampaignCopy() {
    if (activeType() !== 'survey_feedback_reward') return;
    var prompt = value('survey_prompt');
    var desc = field('form_description');
    if (prompt && desc) desc.value = prompt;
  }

  function applySurveyDefaults(force) {
    if (activeType() !== 'survey_feedback_reward') return;
    setIfEmpty('title', 'Share feedback and get a reward');
    setIfEmpty('form_headline', 'Tell us how we did');
    setIfEmpty('description', 'Answer a quick feedback question and receive a Microgifter reward.');
    setIfEmpty('form_description', 'How was your experience?');
    setIfEmpty('success_message', 'Feedback received. Your reward has been sent.');
    setIfEmpty('per_user_limit', '1');
    setIfEmpty('survey_prompt', value('form_description') || 'How was your experience?');
    if (force) {
      var rating = form.querySelector('input[type="checkbox"][name="survey_rating_required"]');
      var feedback = form.querySelector('input[type="checkbox"][name="survey_feedback_required"]');
      if (rating) rating.checked = true;
      if (feedback) feedback.checked = true;
    }
  }

  function syncVisibility() {
    root.querySelectorAll('[data-campaign-type-fields="survey_feedback_reward"]').forEach(function (panel) {
      panel.hidden = activeType() !== 'survey_feedback_reward';
    });
    applySurveyDefaults(false);
  }

  ensureSurveyFields();
  ensureQuickAction();
  form.addEventListener('change', function (event) {
    if (event.target && event.target.name === 'campaign_type') syncVisibility();
  });
  form.addEventListener('submit', syncPromptToCampaignCopy, true);
  root.addEventListener('input', function (event) {
    if (event.target && event.target.name === 'survey_prompt') syncPromptToCampaignCopy();
  });
  root.addEventListener('click', function (event) {
    var preset = event.target && event.target.getAttribute && event.target.getAttribute('data-campaign-type-preset');
    if (preset === 'survey_feedback_reward') window.setTimeout(function () { applySurveyDefaults(true); syncVisibility(); }, 50);
  });
  syncVisibility();
});
