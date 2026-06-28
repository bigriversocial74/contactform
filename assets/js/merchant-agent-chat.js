document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-merchant-agent-chat]');
  if (!root || !window.Microgifter) return;

  var feed = root.querySelector('[data-agent-chat-feed]');
  var form = root.querySelector('[data-agent-chat-form]');
  var status = root.querySelector('[data-agent-chat-status]');
  var send = root.querySelector('[data-agent-chat-send]');
  var textarea = form ? form.querySelector('[data-agent-chat-textarea],textarea[name="message"]') : null;
  var menu = root.querySelector('[data-agent-context-menu]');
  var menuToggle = root.querySelector('[data-agent-context-toggle]');
  var state = { messages: [], quick_prompts: [], overview: null };
  var fallbackPrompts = [
    'Review my campaigns and tell me what needs attention.',
    'Draft a customer follow-up message.',
    'Find reward or claim issues.',
    'Create a weekly merchant action plan.',
    'Summarize my CRM activity.'
  ];

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char];
    });
  }

  function payload(response) {
    return response && response.data ? response.data : response;
  }

  function time(value) {
    var parsed = Date.parse(value || '');
    return parsed ? new Date(parsed).toLocaleString() : '';
  }

  function nowIso() {
    return new Date().toISOString();
  }

  function human(value) {
    return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, function (char) { return char.toUpperCase(); });
  }

  function cleanErrorMessage(error) {
    var message = String((error && error.message) || error || '').trim();
    if (!message) return 'Unable to run merchant agent chat.';
    if (/invalid\s*x-api-key|x-api-key|invalid api key|authentication_error/i.test(message)) return 'Anthropic rejected the server API key. Replace the value in api/config.local.php with a valid Anthropic Console API key, save the file, then retry.';
    if (/csrf/i.test(message)) return 'The security token expired. Refresh the page and send the message again.';
    if (/permission|forbidden|unauthorized|merchant\.ai\.plan|merchant\.ai\.review/i.test(message)) return 'Your account needs the merchant AI permission for this action. Re-run the Stage 19C Claude planner migration or update this user role.';
    if (/not configured|MG_ANTHROPIC_API_KEY|Anthropic API key/i.test(message)) return 'Anthropic is not configured on the server. Check api/config.local.php and reload the page.';
    if (/Claude Sonnet is not enabled|AI provider is not enabled|model catalog/i.test(message)) return 'Claude is not enabled in the AI provider settings. Open admin-ai.php, enable Anthropic, choose a default Claude model, and save.';
    if (/cURL|curl|timeout|timed out|empty response|HTTP 5|overloaded|rate/i.test(message)) return 'Claude did not return a usable response yet. The request may have timed out, hit a provider limit, or been blocked by hosting/network settings. Server detail: ' + message;
    return message;
  }

  function setStatus(message, type) {
    if (!status) return;
    status.textContent = message || '';
    status.className = 'mg-form-status' + (type ? ' is-' + type : '');
  }

  function updateSendState() {
    if (!send || !textarea) return;
    send.disabled = !textarea.value.trim() || textarea.disabled;
  }

  function growTextarea() {
    if (!textarea) return;
    textarea.style.height = 'auto';
    textarea.style.height = Math.min(textarea.scrollHeight, 170) + 'px';
  }

  function resetComposer() {
    if (textarea) {
      textarea.value = '';
      textarea.style.height = '';
    }
    updateSendState();
  }

  function busy(on) {
    if (send) {
      send.disabled = !!on || !(textarea && textarea.value.trim());
      send.textContent = on ? '…' : '↑';
      send.classList.toggle('is-loading', !!on);
    }
    if (textarea) textarea.disabled = !!on;
  }

  function contextValue(selector, fallback) {
    var element = root.querySelector(selector);
    return element && element.value ? element.value : fallback;
  }

  function updateContextSummary() {
    var box = root.querySelector('[data-agent-chat-summary]');
    if (!box) return;
    var mode = contextValue('[data-agent-chat-mode]', 'advisor');
    var scope = contextValue('[data-agent-chat-scope]', 'overview');
    var days = contextValue('[data-agent-chat-days]', '90');
    var output = contextValue('[data-agent-chat-output]', 'action_plan');
    var approval = contextValue('[data-agent-chat-approval]', 'advisory');
    box.textContent = [human(mode) + ' mode', human(scope), 'Last ' + days + ' days', human(output), human(approval)].join(' · ');
  }

  function closeMenu() {
    if (!menu || !menuToggle) return;
    menu.hidden = true;
    menuToggle.setAttribute('aria-expanded', 'false');
  }

  function toggleMenu() {
    if (!menu || !menuToggle) return;
    var open = menu.hidden;
    menu.hidden = !open;
    menuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  function promptButtons() {
    var prompts = state.quick_prompts && state.quick_prompts.length ? state.quick_prompts : fallbackPrompts;
    return '<div class="mg-agent-chat-prompts" data-agent-chat-prompts>' + prompts.map(function (prompt) {
      return '<button type="button">' + esc(prompt) + '</button>';
    }).join('') + '</div>';
  }

  function renderOverview() {
    var overview = state.overview || {};
    var box = root.querySelector('[data-agent-chat-overview]');
    if (box) {
      var nums = [overview.pending_reviews, overview.review_ready_plans, overview.executed_items, overview.chat_messages];
      box.querySelectorAll('strong').forEach(function (node, index) {
        node.textContent = String(nums[index] == null ? '—' : nums[index]);
      });
    }
    var list = root.querySelector('[data-agent-chat-overview-list]');
    if (!list) return;
    var latest = Array.isArray(overview.latest) ? overview.latest : [];
    if (!latest.length) {
      list.innerHTML = '<div class="mg-empty-state"><strong>No review items yet.</strong><p>Send a useful chat card to the review queue to start the approval workflow.</p></div>';
      return;
    }
    list.innerHTML = latest.map(function (item) {
      return '<article><div><strong>' + esc(item.title || 'Review item') + '</strong><span>' + esc(item.action_key || 'action') + '</span></div><b class="is-' + esc(item.status || 'recommended') + '">' + esc(item.status || 'recommended') + '</b></article>';
    }).join('');
  }

  function cardHtml(card, message, index) {
    var link = card.action_url ? '<a class="mg-btn mg-btn-soft" href="' + esc(card.action_url) + '">' + esc(card.action_label || 'Open') + '</a>' : '';
    var bridge = '';
    if (message.role !== 'user') {
      if (card.review_item_id) {
        bridge = '<a class="mg-btn mg-btn-soft" href="/merchant-agent-approvals.php">In Review Queue</a>';
      } else {
        bridge = '<button class="mg-btn mg-btn-secondary" type="button" data-agent-chat-review data-message-id="' + esc(message.id) + '" data-card-index="' + esc(index) + '">Send to Review Queue</button>';
      }
    }
    var save = '<button class="mg-btn mg-btn-soft" type="button" data-agent-card-status>Save draft</button>';
    var dismiss = '<button class="mg-btn mg-btn-soft" type="button" data-agent-card-status>Dismiss</button>';
    return '<article class="mg-agent-chat-card"><span>' + esc(card.type || 'recommendation') + '</span><strong>' + esc(card.title || 'Agent note') + '</strong><p>' + esc(card.body || '') + '</p><div class="mg-agent-chat-card-actions">' + link + bridge + save + dismiss + '</div></article>';
  }

  function messageHtml(message) {
    var mine = message.role === 'user';
    var cards = Array.isArray(message.cards) ? message.cards : [];
    var classes = ['mg-agent-chat-message', mine ? 'is-user' : 'is-agent'];
    if (message.pending) classes.push('is-pending');
    if (message.error) classes.push('is-error');
    return '<article class="' + classes.join(' ') + '"><div class="mg-agent-chat-bubble"><div class="mg-agent-chat-meta"><strong>' + esc(mine ? 'You' : 'Merchant Agent') + '</strong><time>' + esc(time(message.created_at)) + '</time></div><p>' + esc(message.body || '') + '</p>' + (cards.length ? '<div class="mg-agent-chat-cards">' + cards.map(function (card, index) { return cardHtml(card, message, index); }).join('') + '</div>' : '') + '</div></article>';
  }

  function render() {
    renderOverview();
    updateContextSummary();
    if (!feed) return;
    if (!state.messages.length) {
      feed.innerHTML = '<div class="mg-agent-chat-empty"><div class="mg-agent-chat-empty-icon" aria-hidden="true">✦</div><strong>How can I help you today?</strong><p>Ask the merchant agent to review, fix, draft, or prioritize anything for your business.</p>' + promptButtons() + '<small>Your conversations stay advisory until you choose to act.</small></div>';
      return;
    }
    feed.innerHTML = state.messages.map(messageHtml).join('');
    feed.scrollTop = feed.scrollHeight;
  }

  function renderPrompts() {
    root.querySelectorAll('[data-agent-chat-prompts]').forEach(function (box) {
      var prompts = state.quick_prompts && state.quick_prompts.length ? state.quick_prompts : fallbackPrompts;
      box.innerHTML = prompts.map(function (prompt) {
        return '<button type="button">' + esc(prompt) + '</button>';
      }).join('');
    });
  }

  async function load() {
    try {
      setStatus('Loading agent chat…', '');
      var data = payload(await Microgifter.get('/api/ai/merchant-agent-chat.php'));
      state.messages = data.messages || [];
      state.quick_prompts = data.quick_prompts || [];
      state.overview = data.overview || null;
      render();
      renderPrompts();
      setStatus('', '');
    } catch (error) {
      setStatus(cleanErrorMessage(error), 'error');
      if (!state.messages.length) {
        state.messages = [{
          id: 'load-error-' + Date.now(),
          role: 'assistant',
          body: cleanErrorMessage(error),
          cards: [],
          created_at: nowIso(),
          error: true
        }];
        render();
      }
    }
  }

  async function submit(message) {
    message = String(message || '').trim();
    if (!message) return;

    var scope = contextValue('[data-agent-chat-scope]', 'overview');
    var days = parseInt(contextValue('[data-agent-chat-days]', '90'), 10) || 90;
    var mode = contextValue('[data-agent-chat-mode]', 'advisor');
    var output = contextValue('[data-agent-chat-output]', 'action_plan');
    var approval = contextValue('[data-agent-chat-approval]', 'advisory');
    var stamp = Date.now();
    var tempUserId = 'local-user-' + stamp;
    var tempAssistantId = 'local-agent-' + stamp;
    var tempUser = {
      id: tempUserId,
      role: 'user',
      body: message,
      cards: [],
      scope: scope,
      mode: mode,
      output_type: output,
      approval_mode: approval,
      created_at: nowIso()
    };
    var tempAssistant = {
      id: tempAssistantId,
      role: 'assistant',
      body: 'Reviewing your workspace with Claude…',
      cards: [],
      scope: scope,
      mode: mode,
      output_type: output,
      approval_mode: approval,
      created_at: nowIso(),
      pending: true
    };

    state.messages = state.messages.concat([tempUser, tempAssistant]);
    render();
    resetComposer();
    busy(true);
    closeMenu();
    setStatus('Merchant agent is reviewing your workspace…', '');

    try {
      var data = payload(await Microgifter.post('/api/ai/merchant-agent-chat.php', {
        message: message,
        scope: scope,
        days: days,
        mode: mode,
        output_type: output,
        approval_mode: approval
      }));

      if (data.state) {
        state.messages = data.state.messages || state.messages;
        state.overview = data.state.overview || state.overview;
      } else {
        state.messages = state.messages.filter(function (item) {
          return item.id !== tempUserId && item.id !== tempAssistantId;
        }).concat([data.user_message || tempUser, data.assistant_message || {
          id: 'agent-' + Date.now(),
          role: 'assistant',
          body: 'Claude returned a response, but the chat payload was incomplete.',
          cards: [],
          created_at: nowIso(),
          error: true
        }]);
      }

      render();
      setStatus('Agent reply created.', 'success');
    } catch (error) {
      var clean = cleanErrorMessage(error);
      state.messages = state.messages.map(function (item) {
        if (item.id !== tempAssistantId) return item;
        return {
          id: tempAssistantId,
          role: 'assistant',
          body: clean,
          cards: [{
            type: 'diagnostic',
            title: 'Claude response did not complete',
            body: 'The message was submitted, but the server did not return a usable agent reply. Check the note above, then retry after fixing the server/API issue.',
            action_label: 'AI settings',
            action_url: '/admin-ai.php'
          }],
          scope: scope,
          mode: mode,
          output_type: output,
          approval_mode: approval,
          created_at: nowIso(),
          error: true
        };
      });
      render();
      setStatus(clean, 'error');
    } finally {
      busy(false);
      updateSendState();
    }
  }

  async function sendCardToReview(button) {
    var messageId = button.getAttribute('data-message-id') || '';
    var cardIndex = parseInt(button.getAttribute('data-card-index') || '-1', 10);
    if (!messageId || cardIndex < 0) return;
    button.disabled = true;
    button.textContent = 'Sending…';
    setStatus('Adding card to review queue…', '');
    try {
      var data = payload(await Microgifter.post('/api/ai/merchant-agent-chat-review.php', { message_id: messageId, card_index: cardIndex }));
      if (data.state) {
        state.messages = data.state.messages || state.messages;
        state.overview = data.state.overview || state.overview;
      }
      render();
      setStatus('Card added to the Agent Review queue.', 'success');
    } catch (error) {
      button.disabled = false;
      button.textContent = 'Send to Review Queue';
      setStatus(cleanErrorMessage(error), 'error');
    }
  }

  if (form && textarea) {
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      submit(textarea.value);
    });
    textarea.addEventListener('input', function () {
      growTextarea();
      updateSendState();
    });
    textarea.addEventListener('keydown', function (event) {
      if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        form.requestSubmit();
      }
    });
    growTextarea();
    updateSendState();
  }

  root.querySelectorAll('[data-agent-chat-mode],[data-agent-chat-scope],[data-agent-chat-days],[data-agent-chat-output],[data-agent-chat-approval]').forEach(function (element) {
    element.addEventListener('change', updateContextSummary);
  });

  if (menuToggle) {
    menuToggle.addEventListener('click', function (event) {
      event.preventDefault();
      toggleMenu();
    });
  }

  document.addEventListener('click', function (event) {
    if (menu && !menu.hidden && !event.target.closest('[data-agent-context-menu]') && !event.target.closest('[data-agent-context-toggle]')) closeMenu();
  });

  root.addEventListener('click', function (event) {
    var prompt = event.target.closest && event.target.closest('[data-agent-chat-prompts] button');
    if (prompt && textarea) {
      textarea.value = prompt.textContent.trim();
      textarea.focus();
      growTextarea();
      updateSendState();
    }

    var insert = event.target.closest && event.target.closest('[data-agent-context-insert]');
    if (insert && textarea) {
      var text = insert.getAttribute('data-agent-context-insert') || insert.textContent.trim();
      textarea.value = (textarea.value.trim() ? textarea.value.trim() + '\n\n' : '') + text;
      textarea.focus();
      growTextarea();
      updateSendState();
      closeMenu();
    }

    var refresh = event.target.closest && event.target.closest('[data-agent-chat-refresh]');
    if (refresh) load();

    var review = event.target.closest && event.target.closest('[data-agent-chat-review]');
    if (review) sendCardToReview(review);

    var cardStatus = event.target.closest && event.target.closest('[data-agent-card-status]');
    if (cardStatus) {
      cardStatus.textContent = 'Saved';
      cardStatus.disabled = true;
      setStatus('Card action saved locally for this session.', 'success');
    }
  });

  updateContextSummary();
  load();
});