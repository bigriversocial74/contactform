document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-messages-center]');
  if (!root || !window.Microgifter) return;

  var detail = root.querySelector('[data-thread-detail]');
  var activeThreadId = '';
  var activeController = 0;

  var templates = [
    {
      key: 'thanks',
      label: 'Thanks',
      text: 'Thanks for reaching out. I am reviewing this now and will follow up with the best next step.'
    },
    {
      key: 'reward',
      label: 'Reward follow-up',
      text: 'Thanks for connecting with us. I can send over a reward for your next visit. Let me know the best option for you.'
    },
    {
      key: 'claim',
      label: 'Claim link',
      text: 'Here is your claim link. Please open it before your visit and show the claim code when you arrive.'
    },
    {
      key: 'visit',
      label: 'Visit follow-up',
      text: 'Thanks for visiting. I wanted to follow up and make sure everything went smoothly with your Microgifter reward.'
    },
    {
      key: 'support',
      label: 'Support issue',
      text: 'I am sorry about that. I am checking the claim and reward details now so we can get this corrected.'
    },
    {
      key: 'close',
      label: 'Close loop',
      text: 'I am going to mark this as resolved for now. Reply here anytime if you need more help.'
    }
  ];

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character];
    });
  }

  function text(value, fallback) {
    value = String(value == null ? '' : value).trim();
    return value || fallback || 'Not available';
  }

  function initials(value) {
    return text(value, 'Customer').split(/\s+/).filter(Boolean).slice(0, 2).map(function (part) {
      return part.charAt(0).toUpperCase();
    }).join('') || 'CU';
  }

  function draftKey(threadId) {
    return 'mg:messages:draft:' + threadId;
  }

  function noteKey(threadId) {
    return 'mg:messages:note:' + threadId;
  }

  function stateKey(threadId) {
    return 'mg:messages:ops:' + threadId;
  }

  function safeGet(key) {
    try { return localStorage.getItem(key) || ''; } catch (error) { return ''; }
  }

  function safeSet(key, value) {
    try {
      if (value) localStorage.setItem(key, value);
      else localStorage.removeItem(key);
    } catch (error) {}
  }

  function safeJsonGet(key) {
    try { return JSON.parse(localStorage.getItem(key) || '{}') || {}; } catch (error) { return {}; }
  }

  function safeJsonSet(key, value) {
    try { localStorage.setItem(key, JSON.stringify(value || {})); } catch (error) {}
  }

  function setTextareaValue(textarea, value) {
    if (!textarea) return;
    var current = textarea.value || '';
    var next = current ? current + (current.endsWith('\n') ? '' : '\n') + value : value;
    textarea.value = next;
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
    textarea.focus();
    textarea.selectionStart = textarea.selectionEnd = textarea.value.length;
  }

  function profileFromThread(thread) {
    var source = thread.source || {};
    var context = source.context || {};
    var messages = thread.messages || [];
    var latest = messages.length ? messages[messages.length - 1] : {};
    var customerName = context.customer_name || context.name || thread.customer_name || thread.recipient_name || thread.subject || 'Customer';
    var customerEmail = context.customer_email || context.email || thread.customer_email || thread.recipient_email || '';
    var claimed = Number(context.rewards_claimed_count || context.claimed_count || 0);
    var sent = Number(context.rewards_sent_count || context.rewards_received_count || 0);
    var visits = Number(context.visit_count || context.visits || context.store_visits || 0);
    var score = Math.min(100, 42 + Math.min(messages.length * 4, 24) + Math.min(claimed * 10, 20) + Math.min(visits * 4, 14));
    return {
      source: source,
      context: context,
      customerName: customerName,
      customerEmail: customerEmail,
      merchant: context.merchant_name || 'Merchant workspace',
      campaign: context.campaign_title || context.campaign_type || 'Direct conversation',
      campaignType: context.campaign_type || 'Message thread',
      contactId: context.contact_id || source.reference || thread.conversation_key || '',
      contactSource: context.contact_source || source.system || source.label || 'Messages',
      storeSession: context.store_session_id || '',
      messagesCount: messages.length,
      lastMessageAt: latest.created_at || thread.updated_at || thread.latest_at || '',
      rewardsSent: sent,
      rewardsClaimed: claimed,
      visits: visits,
      score: score,
      nextAction: claimed > 0 ? 'Send visit follow-up' : (source.label === 'Merchant CRM' ? 'Send claim link or reward' : 'Ask customer what they need next')
    };
  }

  function renderProfilePanel(thread, threadId) {
    var profile = profileFromThread(thread);
    var ops = safeJsonGet(stateKey(threadId));
    var note = safeGet(noteKey(threadId));
    var status = ops.resolved ? 'Resolved' : 'Open';
    var label = ops.label || 'Needs follow-up';
    var assignee = ops.assignee || 'Unassigned';

    return '' +
      '<aside class="mg-customer-profile-panel" data-profile-panel>' +
        '<div class="mg-profile-card mg-profile-hero">' +
          '<div class="mg-profile-avatar">' + esc(initials(profile.customerName)) + '</div>' +
          '<div><span>Customer profile</span><strong>' + esc(profile.customerName) + '</strong><small>' + esc(text(profile.customerEmail, profile.contactSource)) + '</small></div>' +
        '</div>' +
        '<div class="mg-profile-score"><div><span>CRM score</span><strong>' + profile.score + '/100</strong></div><i style="width:' + profile.score + '%"></i></div>' +
        '<div class="mg-profile-grid">' +
          '<div><span>Status</span><strong data-profile-status>' + esc(status) + '</strong></div>' +
          '<div><span>Label</span><strong data-profile-label>' + esc(label) + '</strong></div>' +
          '<div><span>Owner</span><strong data-profile-assignee>' + esc(assignee) + '</strong></div>' +
          '<div><span>Messages</span><strong>' + profile.messagesCount + '</strong></div>' +
          '<div><span>Rewards sent</span><strong>' + profile.rewardsSent + '</strong></div>' +
          '<div><span>Claimed</span><strong>' + profile.rewardsClaimed + '</strong></div>' +
        '</div>' +
        '<div class="mg-profile-card"><span>Campaign</span><strong>' + esc(profile.campaign) + '</strong><small>' + esc(profile.campaignType) + '</small></div>' +
        '<div class="mg-profile-card"><span>Source</span><strong>' + esc(profile.contactSource) + '</strong><small>' + esc(text(profile.contactId, 'No contact reference')) + '</small></div>' +
        (profile.storeSession ? '<div class="mg-profile-card"><span>Store session</span><strong>' + esc(profile.storeSession) + '</strong><small>Active Store Canvas context</small></div>' : '') +
        '<div class="mg-profile-next"><span>Suggested next action</span><strong>' + esc(profile.nextAction) + '</strong><button type="button" data-insert-suggestion>Use as message</button></div>' +
        '<div class="mg-profile-actions"><button type="button" data-profile-resolve>' + (ops.resolved ? 'Reopen' : 'Resolve') + '</button><button type="button" data-profile-assign>Assign me</button><button type="button" data-profile-label-action>High value</button></div>' +
        '<label class="mg-profile-note"><span>Internal note</span><textarea data-profile-note placeholder="Private merchant note...">' + esc(note) + '</textarea></label>' +
      '</aside>';
  }

  function renderTemplateTray() {
    return '<div class="mg-template-tray" data-template-tray>' +
      '<div><span>Reply templates</span><strong>Insert a merchant-ready response</strong></div>' +
      '<div class="mg-template-list">' + templates.map(function (template) {
        return '<button type="button" data-template-key="' + esc(template.key) + '">' + esc(template.label) + '</button>';
      }).join('') + '</div>' +
    '</div>';
  }

  function enhanceThread(threadId) {
    if (!threadId || threadId === activeThreadId) return;
    activeThreadId = threadId;
    var controller = ++activeController;

    Microgifter.get('/api/messages/thread.php?id=' + encodeURIComponent(threadId)).then(function (response) {
      if (controller !== activeController) return;
      var thread = (response.data || response).thread || {};
      var shell = detail && detail.querySelector('.mg-thread-detail-shell');
      var streamWrap = detail && detail.querySelector('.mg-message-stream-wrap');
      var composer = detail && detail.querySelector('[data-thread-reply]');
      if (!shell || !streamWrap || !composer || shell.dataset.crmEnhanced === threadId) return;

      shell.dataset.crmEnhanced = threadId;
      shell.classList.add('has-crm-profile');

      var grid = document.createElement('div');
      grid.className = 'mg-thread-main-grid';
      streamWrap.parentNode.insertBefore(grid, streamWrap);
      grid.appendChild(streamWrap);
      grid.insertAdjacentHTML('beforeend', renderProfilePanel(thread, threadId));

      var titleActions = detail.querySelector('.mg-thread-title-actions');
      if (titleActions && !titleActions.querySelector('[data-profile-toggle]')) {
        titleActions.insertAdjacentHTML('afterbegin', '<button type="button" data-profile-toggle aria-expanded="true" title="Customer profile">Profile</button>');
      }

      var quickActions = composer.querySelector('.mg-message-quick-actions');
      if (quickActions && !composer.querySelector('[data-template-tray]')) {
        quickActions.insertAdjacentHTML('afterend', renderTemplateTray());
      }

      var textarea = composer.querySelector('textarea[name="body"]');
      var savedDraft = safeGet(draftKey(threadId));
      if (textarea && savedDraft && !textarea.value.trim()) {
        textarea.value = savedDraft;
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
      }
    }).catch(function (error) {
      console.error(error);
    });
  }

  function scan() {
    var form = detail && detail.querySelector('[data-thread-reply]');
    var threadId = form && form.dataset.threadId ? form.dataset.threadId : '';
    if (threadId) enhanceThread(threadId);
  }

  if (detail) {
    var observer = new MutationObserver(scan);
    observer.observe(detail, { childList: true, subtree: true });
    scan();

    detail.addEventListener('input', function (event) {
      var form = event.target.closest('[data-thread-reply]');
      if (form && event.target.matches('textarea[name="body"]')) {
        safeSet(draftKey(form.dataset.threadId || ''), event.target.value);
      }
      if (event.target.matches('[data-profile-note]')) {
        var activeForm = detail.querySelector('[data-thread-reply]');
        safeSet(noteKey(activeForm && activeForm.dataset.threadId || ''), event.target.value);
      }
    });

    detail.addEventListener('submit', function (event) {
      var form = event.target.closest('[data-thread-reply]');
      if (!form) return;
      var threadId = form.dataset.threadId || '';
      setTimeout(function () {
        var textarea = form.querySelector('textarea[name="body"]');
        if (!textarea || !textarea.value.trim()) safeSet(draftKey(threadId), '');
      }, 1400);
    }, true);

    detail.addEventListener('click', function (event) {
      var form = detail.querySelector('[data-thread-reply]');
      var threadId = form && form.dataset.threadId || activeThreadId;
      var textarea = form && form.querySelector('textarea[name="body"]');

      var templateButton = event.target.closest('[data-template-key]');
      if (templateButton) {
        var selected = templates.find(function (template) { return template.key === templateButton.dataset.templateKey; });
        if (selected) setTextareaValue(textarea, selected.text);
        return;
      }

      if (event.target.closest('[data-insert-suggestion]')) {
        var action = detail.querySelector('.mg-profile-next strong');
        setTextareaValue(textarea, action ? action.textContent + ': ' : 'Following up: ');
        return;
      }

      if (event.target.closest('[data-profile-toggle]')) {
        var shell = detail.querySelector('.mg-thread-detail-shell');
        var open = shell && !shell.classList.contains('is-profile-collapsed');
        if (shell) shell.classList.toggle('is-profile-collapsed', open);
        event.target.setAttribute('aria-expanded', open ? 'false' : 'true');
        return;
      }

      if (event.target.closest('[data-profile-resolve]')) {
        var ops = safeJsonGet(stateKey(threadId));
        ops.resolved = !ops.resolved;
        safeJsonSet(stateKey(threadId), ops);
        var statusNode = detail.querySelector('[data-profile-status]');
        if (statusNode) statusNode.textContent = ops.resolved ? 'Resolved' : 'Open';
        event.target.textContent = ops.resolved ? 'Reopen' : 'Resolve';
        return;
      }

      if (event.target.closest('[data-profile-assign]')) {
        var assignOps = safeJsonGet(stateKey(threadId));
        assignOps.assignee = 'Me';
        safeJsonSet(stateKey(threadId), assignOps);
        var assigneeNode = detail.querySelector('[data-profile-assignee]');
        if (assigneeNode) assigneeNode.textContent = 'Me';
        return;
      }

      if (event.target.closest('[data-profile-label-action]')) {
        var labelOps = safeJsonGet(stateKey(threadId));
        labelOps.label = labelOps.label === 'High value' ? 'Needs follow-up' : 'High value';
        safeJsonSet(stateKey(threadId), labelOps);
        var labelNode = detail.querySelector('[data-profile-label]');
        if (labelNode) labelNode.textContent = labelOps.label;
      }
    });
  }
});
