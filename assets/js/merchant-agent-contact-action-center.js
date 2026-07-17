document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-merchant-agent-chat]');
  var form = document.querySelector('[data-agent-chat-form]');
  var center = form && form.querySelector('[data-merchant-contact-action-center]');
  if (!root || !form || !center || !window.Microgifter) return;

  var toggle = center.querySelector('[data-contact-center-toggle]');
  var body = center.querySelector('[data-contact-center-body]');
  var clearButton = center.querySelector('[data-contact-center-clear]');
  var idInput = center.querySelector('[data-contact-center-id]');
  var mentionInput = center.querySelector('[data-contact-center-mention]');
  var nameNode = center.querySelector('[data-contact-center-name]');
  var metaNode = center.querySelector('[data-contact-center-meta]');
  var avatarNode = center.querySelector('[data-contact-center-avatar]');
  var scoreNode = center.querySelector('[data-contact-center-score]');
  var metricsNode = center.querySelector('[data-contact-center-metrics]');
  var actionsNode = center.querySelector('[data-contact-center-actions]');
  var activityNode = center.querySelector('[data-contact-center-activity]');
  var followupsNode = center.querySelector('[data-contact-center-followups]');
  var activityCountNode = center.querySelector('[data-contact-center-activity-count]');
  var followupCountNode = center.querySelector('[data-contact-center-followup-count]');
  var profileLink = center.querySelector('[data-contact-center-profile]');
  var timelineLink = center.querySelector('[data-contact-center-timeline]');
  var boundaryNode = center.querySelector('[data-contact-center-boundary]');
  var statusNode = document.querySelector('[data-agent-chat-status]');
  var textarea = form.querySelector('[data-agent-chat-textarea],textarea[name="message"]');
  var current = null;
  var busy = false;

  function payload(response) { return response && response.data ? response.data : response; }
  function esc(value) { return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]; }); }
  function human(value) { return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, function (char) { return char.toUpperCase(); }); }
  function compactDate(value) { var stamp = Date.parse(value || ''); return stamp ? new Date(stamp).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) : 'No date'; }
  function money(cents) { return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(Number(cents || 0) / 100); }
  function threadId() { var select = document.querySelector('[data-agent-thread-select]'); return select && select.value ? select.value : ''; }
  function days() { var select = document.querySelector('[data-agent-chat-days]'); return select && select.value ? Number(select.value) : 90; }

  function setStatus(message, type) {
    if (!statusNode) return;
    statusNode.textContent = message || '';
    statusNode.className = 'mg-form-status' + (type ? ' is-' + type : '');
  }

  function setExpanded(open) {
    if (!toggle || !body) return;
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    body.hidden = !open;
  }

  function initials(name) {
    var parts = String(name || 'C').trim().split(/\s+/).filter(Boolean);
    return (parts.slice(0, 2).map(function (part) { return part.charAt(0); }).join('') || 'C').toUpperCase();
  }

  function metric(label, value) {
    return '<article><strong>' + esc(value) + '</strong><span>' + esc(label) + '</span></article>';
  }

  function activityHtml(items) {
    items = Array.isArray(items) ? items : [];
    if (!items.length) return '<span class="is-empty">No recent CRM events are available for this contact.</span>';
    return items.slice(0, 6).map(function (item) {
      var title = human(item.event_type || item.type || 'CRM activity');
      var detail = [human(item.campaign_type || ''), human(item.source_type || '')].filter(Boolean).join(' · ');
      if (item.value_cents != null) detail += (detail ? ' · ' : '') + money(item.value_cents);
      return '<article><strong>' + esc(title) + '</strong><span>' + esc(detail || 'Merchant CRM activity') + '</span><small>' + esc(compactDate(item.created_at || item.at)) + '</small></article>';
    }).join('');
  }

  function followupsHtml(data) {
    var tasks = Array.isArray(data.followup_tasks) ? data.followup_tasks : [];
    var campaigns = Array.isArray(data.campaign_history) ? data.campaign_history : [];
    var rows = [];
    tasks.slice(0, 3).forEach(function (task) {
      rows.push('<article><strong>' + esc(task.note || 'CRM follow-up') + '</strong><span>' + esc(human(task.status || 'open')) + (task.due_at ? ' · due ' + esc(compactDate(task.due_at)) : '') + '</span></article>');
    });
    campaigns.slice(0, Math.max(0, 4 - rows.length)).forEach(function (campaign) {
      rows.push('<article><strong>' + esc(campaign.title || human(campaign.campaign_type || 'Campaign')) + '</strong><span>' + Number(campaign.event_count || 0).toLocaleString() + ' interactions · ' + esc(compactDate(campaign.last_event_at)) + '</span></article>');
    });
    return rows.length ? rows.join('') : '<span class="is-empty">No campaign or follow-up history is available yet.</span>';
  }

  function actionButtons(items) {
    items = Array.isArray(items) ? items : [];
    return items.map(function (item) {
      return '<button type="button" data-contact-center-action="' + esc(item.key) + '">' + esc(item.label || human(item.key)) + '</button>';
    }).join('');
  }

  function render(data) {
    current = data && data.selected ? data : null;
    if (!current || !current.contact) {
      center.hidden = true;
      if (idInput) idInput.value = '';
      if (mentionInput) mentionInput.value = '';
      setExpanded(false);
      return;
    }

    var contact = current.contact || {};
    var metrics = current.metrics || {};
    center.hidden = false;
    if (idInput) idInput.value = String(contact.id || '');
    if (mentionInput) mentionInput.value = String(contact.mention || '');
    if (nameNode) nameNode.textContent = contact.name || 'CRM contact';
    if (metaNode) metaNode.textContent = [contact.mention, human(contact.lifecycle_stage), human(contact.crm_status)].filter(Boolean).join(' · ');
    if (avatarNode) avatarNode.textContent = initials(contact.name);
    if (scoreNode) scoreNode.textContent = Number(contact.engagement_score || 0) + ' · ' + human(contact.engagement_label || 'CRM');
    if (metricsNode) metricsNode.innerHTML = [
      metric('Purchases', money(metrics.purchase_value_cents)),
      metric('Rewards sent', Number(metrics.rewards_issued || 0).toLocaleString()),
      metric('Claimed', Number(metrics.rewards_claimed || 0).toLocaleString()),
      metric('Redeemed', Number(metrics.rewards_redeemed || 0).toLocaleString()),
      metric('Messages', Number(metrics.messages || 0).toLocaleString()),
      metric('Open tasks', Number(metrics.open_followups || 0).toLocaleString())
    ].join('');
    if (actionsNode) actionsNode.innerHTML = actionButtons(current.quick_actions);
    if (activityNode) activityNode.innerHTML = activityHtml(current.recent_activity);
    if (followupsNode) followupsNode.innerHTML = followupsHtml(current);
    if (activityCountNode) activityCountNode.textContent = Number(metrics.recent_events || 0).toLocaleString() + ' recent';
    if (followupCountNode) followupCountNode.textContent = Number(metrics.campaigns || 0).toLocaleString() + ' campaigns · ' + Number(metrics.open_followups || 0).toLocaleString() + ' open';
    if (profileLink && current.links && current.links.profile) profileLink.href = current.links.profile;
    if (timelineLink && current.links && current.links.timeline) timelineLink.href = current.links.timeline;
    if (boundaryNode) boundaryNode.textContent = current.boundary || 'Selected contact remains scoped to this merchant workspace. Actions require review.';
  }

  function applyResponse(response) {
    var data = payload(response) || {};
    var state = data.state || data;
    if (state && state.contact_action_center) render(state.contact_action_center);
    else if (data.contact_action_center) render(data.contact_action_center);
  }

  function installTransportBridge() {
    if (Microgifter.get && !Microgifter.get.__mgContactActionCenter) {
      var originalGet = Microgifter.get;
      var wrappedGet = function () {
        var args = arguments;
        var url = String(args[0] || '');
        return Promise.resolve(originalGet.apply(this, args)).then(function (response) {
          if (url.indexOf('/api/ai/merchant-agent-chat.php') !== -1) applyResponse(response);
          return response;
        });
      };
      wrappedGet.__mgContactActionCenter = true;
      wrappedGet.__mgContactActionCenterOriginal = originalGet;
      Microgifter.get = wrappedGet;
    }

    if (Microgifter.post && !Microgifter.post.__mgContactActionCenter) {
      var originalPost = Microgifter.post;
      var wrappedPost = function (url, data) {
        var request = data && typeof data === 'object' ? Object.assign({}, data) : data;
        if (String(url || '').indexOf('/api/ai/merchant-agent-chat.php') !== -1 && request && current && current.contact) {
          if (request.action === 'send_message' || request.action === 'contact_action') {
            request.selected_contact_id = request.selected_contact_id || current.contact.id;
            request.selected_contact_mention = request.selected_contact_mention || current.contact.mention || '';
          }
        }
        return Promise.resolve(originalPost.call(this, url, request)).then(function (response) {
          if (String(url || '').indexOf('/api/ai/merchant-agent-chat.php') !== -1 && (!request || request.action !== 'crm_search')) applyResponse(response);
          return response;
        });
      };
      wrappedPost.__mgContactActionCenter = true;
      wrappedPost.__mgContactActionCenterOriginal = originalPost;
      Microgifter.post = wrappedPost;
    }
  }

  async function selectContact(contact) {
    if (busy || !contact || !contact.id) return;
    busy = true;
    setStatus('Selecting ' + (contact.name || contact.mention || 'CRM contact') + '…', '');
    try {
      var response = await Microgifter.post('/api/ai/merchant-agent-chat.php', {
        action: 'select_contact',
        contact_id: contact.id,
        contact_mention: contact.mention || '',
        thread_id: threadId(),
        days: days()
      });
      applyResponse(response);
      setExpanded(true);
      setStatus((contact.mention || contact.name || 'Contact') + ' is now active for this Merchant Agent chat.', 'success');
    } catch (error) {
      setStatus(String(error && error.message || 'Unable to select this CRM contact.'), 'error');
    } finally {
      busy = false;
    }
  }

  async function clearContact() {
    if (busy) return;
    busy = true;
    setStatus('Clearing selected CRM contact…', '');
    try {
      var response = await Microgifter.post('/api/ai/merchant-agent-chat.php', { action: 'clear_contact', thread_id: threadId() });
      applyResponse(response);
      render(null);
      setStatus('Selected CRM contact cleared from this chat.', 'success');
    } catch (error) {
      setStatus(String(error && error.message || 'Unable to clear the selected contact.'), 'error');
    } finally {
      busy = false;
    }
  }

  function runAction(button, action) {
    if (busy || !current || !current.contact || !action || !textarea) return;
    var mention = current.contact.mention || 'The selected contact';
    var prompts = {
      summarize_activity: { message: mention + ' summarize recent activity and explain the next best action.', output: 'action_plan', approval: 'advisory' },
      draft_followup: { message: mention + ' draft a personalized follow-up message for review. Do not send it.', output: 'message_draft', approval: 'review_queue' },
      recommend_reward: { message: mention + ' recommend the most appropriate reward and prepare it for review. Do not issue it.', output: 'admin_recommendation', approval: 'review_queue' },
      draft_campaign_invite: { message: mention + ' draft a personalized campaign invitation for review. Do not send it.', output: 'message_draft', approval: 'review_queue' },
      create_followup_task: { message: mention + ' create a review-ready CRM follow-up task with an objective, reason, and due-date recommendation.', output: 'admin_recommendation', approval: 'review_queue' }
    };
    var prompt = prompts[action];
    if (!prompt) return;
    var output = document.querySelector('[data-agent-chat-output]');
    var approval = document.querySelector('[data-agent-chat-approval]');
    var scope = document.querySelector('[data-agent-chat-scope]');
    if (output) output.value = prompt.output;
    if (approval) approval.value = prompt.approval;
    if (scope) scope.value = 'crm';
    textarea.value = prompt.message;
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
    if (button) button.classList.add('is-loading');
    window.setTimeout(function () {
      if (typeof form.requestSubmit === 'function') form.requestSubmit();
      else form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
      if (button) button.classList.remove('is-loading');
    }, 20);
  }

  installTransportBridge();

  if (toggle) toggle.addEventListener('click', function () { setExpanded(toggle.getAttribute('aria-expanded') !== 'true'); });
  if (clearButton) clearButton.addEventListener('click', clearContact);
  if (actionsNode) actionsNode.addEventListener('click', function (event) {
    var button = event.target.closest('[data-contact-center-action]');
    if (button) runAction(button, button.getAttribute('data-contact-center-action') || '');
  });

  document.addEventListener('mg:merchant-agent:select-contact', function (event) {
    selectContact(event.detail && event.detail.contact);
  });

  window.MicrogifterMerchantContactActionCenter = Object.freeze({
    select: selectContact,
    clear: clearContact,
    render: render,
    getSelectedContact: function () { return current && current.contact ? Object.assign({}, current.contact) : null; }
  });
});
