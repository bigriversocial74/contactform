document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var selectedNode = document.getElementById('mg-selected-agent-id');
  var agentId = selectedNode ? JSON.parse(selectedNode.textContent || '""') : '';
  if (!agentId) return;

  var root = document.querySelector('[data-agent-instance-canvas]');
  if (!root) return;

  var messages = root.querySelector('[data-agent-runtime-messages]');
  var composer = root.querySelector('[data-agent-runtime-composer]');
  var status = root.querySelector('[data-agent-runtime-status]');
  var memoryList = root.querySelector('[data-agent-memory-list]');
  var onboardingForm = document.querySelector('[data-agent-onboarding-form]');
  var currentThread = '';

  function csrf() {
    var node = document.querySelector('meta[name="csrf-token"]');
    return node ? node.content : '';
  }

  function esc(value) {
    return String(value || '').replace(/[&<>"']/g, function (ch) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch];
    });
  }

  async function request(method, url, payload) {
    var options = { method: method, credentials: 'same-origin', headers: { Accept: 'application/json' } };
    if (payload) {
      options.headers['Content-Type'] = 'application/json';
      options.headers['X-CSRF-Token'] = csrf();
      options.body = JSON.stringify(payload);
    }
    var response = await fetch(url, options);
    var json = await response.json();
    if (!response.ok || !json.ok) throw new Error(json.message || 'Unable to complete the agent request.');
    return json.data || json;
  }

  function renderCards(cards) {
    if (!Array.isArray(cards) || !cards.length) return '';
    return '<div class="mg-agent-runtime-cards">' + cards.map(function (card) {
      return '<article><span>' + esc(card.type || 'Agent draft') + '</span><h4>' + esc(card.title || 'Next step') + '</h4><p>' + esc(card.body || '') + '</p>'
        + (card.action === 'save_draft' ? '<button type="button" data-save-agent-draft data-draft-title="' + esc(card.title || 'Agent draft') + '" data-draft-payload="' + esc(JSON.stringify(card.review_payload || {})) + '">Save reviewable draft</button>' : '')
        + '</article>';
    }).join('') + '</div>';
  }

  function renderMessages(items) {
    messages.innerHTML = (items || []).map(function (item) {
      return '<article class="mg-agent-runtime-message is-' + esc(item.role) + '"><div><strong>' + esc(item.role === 'assistant' ? 'Agent' : 'You') + '</strong><time>' + esc(item.created_at || '') + '</time></div><p>' + esc(item.body || '') + '</p>' + renderCards(item.cards || []) + '</article>';
    }).join('');
    messages.scrollTop = messages.scrollHeight;
  }

  function renderMemory(items) {
    if (!memoryList) return;
    memoryList.innerHTML = (items && items.length) ? items.map(function (item) {
      var value = item.value;
      return '<article><span>' + esc(item.category || 'memory') + '</span><strong>' + esc(item.title || item.memory_key) + '</strong><p>' + esc(typeof value === 'string' ? value : JSON.stringify(value)) + '</p></article>';
    }).join('') : '<p>No saved memory yet.</p>';
  }

  async function load() {
    status.textContent = 'Loading…';
    try {
      var data = await request('GET', '/api/agents/runtime.php?id=' + encodeURIComponent(agentId));
      currentThread = data.thread && data.thread.id ? data.thread.id : '';
      renderMessages(data.messages || []);
      renderMemory(data.memory || []);
      var answers = (data.onboarding && data.onboarding.answers) || {};
      Object.keys(answers).forEach(function (key) {
        if (onboardingForm && onboardingForm.elements[key]) onboardingForm.elements[key].value = answers[key] || '';
      });
      status.textContent = '';
    } catch (error) {
      status.textContent = error.message;
    }
  }

  async function send(message) {
    status.textContent = 'Thinking…';
    var sendButton = composer.querySelector('button');
    sendButton.disabled = true;
    try {
      var data = await request('POST', '/api/agents/runtime.php', { id: agentId, action: 'chat', thread_id: currentThread, message: message });
      currentThread = data.thread && data.thread.id ? data.thread.id : currentThread;
      await load();
    } catch (error) {
      status.textContent = error.message;
    } finally {
      sendButton.disabled = false;
    }
  }

  composer.hidden = false;
  composer.style.display = '';
  composer.addEventListener('submit', function (event) {
    event.preventDefault();
    var field = composer.elements.message;
    var value = field.value.trim();
    if (!value) return;
    field.value = '';
    send(value);
  });

  document.addEventListener('click', function (event) {
    var prompt = event.target.closest('[data-agent-seed-prompt]');
    if (prompt) {
      event.preventDefault();
      event.stopImmediatePropagation();
      composer.elements.message.value = prompt.getAttribute('data-agent-seed-prompt') || '';
      composer.elements.message.focus();
      return;
    }

    var draft = event.target.closest('[data-save-agent-draft]');
    if (draft) {
      event.preventDefault();
      event.stopImmediatePropagation();
      var payload = {};
      try { payload = JSON.parse(draft.getAttribute('data-draft-payload') || '{}'); } catch (e) {}
      request('POST', '/api/agents/runtime.php', {
        id: agentId,
        action: 'save_draft',
        thread_id: currentThread,
        title: draft.getAttribute('data-draft-title') || 'Agent draft',
        draft_type: 'plan',
        payload: payload
      }).then(function () {
        status.textContent = 'Reviewable draft saved.';
      }).catch(function (error) {
        status.textContent = error.message;
      });
    }
  }, true);

  if (onboardingForm) {
    onboardingForm.addEventListener('submit', function (event) {
      event.preventDefault();
      var form = new FormData(onboardingForm);
      var answers = {};
      form.forEach(function (value, key) {
        if (key !== 'settings_action') answers[key] = String(value).trim();
      });
      var action = (event.submitter && event.submitter.value) || 'save';
      var note = onboardingForm.querySelector('[data-agent-onboarding-status]');
      note.textContent = action === 'apply' ? 'Saving and applying…' : 'Saving…';
      request('POST', '/api/agents/runtime.php', { id: agentId, action: 'onboarding', status: 'completed', current_step: 'complete', answers: answers }).then(function (data) {
        renderMemory(data.memory || []);
        note.textContent = action === 'apply' ? 'Settings saved and applied.' : 'Settings saved.';
        if (action === 'apply') load();
      }).catch(function (error) {
        note.textContent = error.message;
      });
    });
  }

  load();
});