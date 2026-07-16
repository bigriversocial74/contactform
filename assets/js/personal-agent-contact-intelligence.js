document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var app = window.MicrogifterPersonalAgent;
  if (!app || !app.root) return;

  var root = app.root;
  var feed = app.ui && app.ui.feed;
  var summary = app.ui && app.ui.summary;
  var esc = app.esc;
  var dataOf = app.dataOf;
  var setStatus = app.setStatus;
  var loadDashboard = app.loadDashboard;

  function cardsFor(grid) {
    return grid && Array.isArray(grid._agentCards) ? grid._agentCards : [];
  }

  function enhanceGrid(grid) {
    if (!grid || grid.dataset.contactIntelligenceReady === 'true') return;
    var cards = cardsFor(grid);
    if (!cards.length) return;

    Array.prototype.forEach.call(grid.querySelectorAll('.mg-personal-agent-chat-card'), function (article, index) {
      var card = cards[index] || {};
      article.dataset.agentCardType = String(card.type || '');

      if (Array.isArray(card.fields) && card.fields.length && !article.querySelector('.mg-agent-action-review-fields')) {
        var fields = document.createElement('dl');
        fields.className = 'mg-agent-action-review-fields';
        fields.innerHTML = card.fields.map(function (field) {
          return '<div><dt>' + esc(field.label || 'Detail') + '</dt><dd>' + esc(field.value == null ? '' : field.value) + '</dd></div>';
        }).join('');
        var firstButton = article.querySelector('[data-agent-card-index]');
        article.insertBefore(fields, firstButton || null);
      }

      if (card.action === 'confirm_contact_action') {
        article.classList.add('is-action-review');
        var confirm = article.querySelector('[data-agent-card-index]');
        if (confirm) {
          confirm.dataset.agentActionDraft = String(card.action_draft_id || '');
          confirm.dataset.decision = 'confirm';
          confirm.classList.add('is-primary');
        }
        if (!article.querySelector('[data-agent-action-cancel]')) {
          var actions = document.createElement('div');
          actions.className = 'mg-agent-action-review-actions';
          if (confirm) actions.appendChild(confirm);
          var cancel = document.createElement('button');
          cancel.type = 'button';
          cancel.textContent = card.cancel_label || 'Cancel';
          cancel.dataset.agentActionCancel = 'true';
          cancel.dataset.agentActionDraft = String(card.action_draft_id || '');
          cancel.dataset.decision = 'cancel';
          actions.appendChild(cancel);
          article.appendChild(actions);
        }
      } else if (card.url) {
        var open = article.querySelector('[data-agent-card-index]');
        if (open) open.dataset.agentResultUrl = String(card.url);
      }
    });

    grid.dataset.contactIntelligenceReady = 'true';
  }

  function enhanceAll() {
    if (!feed) return;
    feed.querySelectorAll('.mg-personal-agent-card-grid').forEach(enhanceGrid);
  }

  function enhanceSummary() {
    if (!summary || !app.state || !app.state.dashboard) return;
    var count = Number(app.state.dashboard.summary && app.state.dashboard.summary.signals || 0);
    var card = summary.querySelector('[data-agent-signal-count]');
    if (!card) {
      card = document.createElement('article');
      card.className = 'mg-personal-agent-stat';
      card.dataset.agentSignalCount = 'true';
      card.innerHTML = '<span>Signals</span><strong>0</strong><small>Relationship and occasion cues</small>';
      summary.appendChild(card);
    }
    var value = card.querySelector('strong');
    if (value) value.textContent = String(count);
  }

  async function executeDraft(button) {
    var draftId = String(button.dataset.agentActionDraft || '');
    var decision = String(button.dataset.decision || 'confirm');
    if (!draftId) return;

    var article = button.closest('.mg-personal-agent-chat-card');
    var actions = article && article.querySelector('.mg-agent-action-review-actions');
    var buttons = actions ? actions.querySelectorAll('button') : [button];
    buttons.forEach(function (node) { node.disabled = true; });
    var original = button.textContent;
    button.textContent = decision === 'cancel' ? 'Cancelling…' : 'Saving…';
    setStatus(decision === 'cancel' ? 'Cancelling the prepared action…' : 'Saving the confirmed account change…');

    try {
      var response = await Microgifter.post('/api/user-agent/action-confirm.php', { draft_id: draftId, decision: decision });
      var data = dataOf(response);
      if (decision === 'cancel' || data.status === 'cancelled') {
        if (article) {
          article.classList.remove('is-action-review');
          article.classList.add('is-action-cancelled');
          if (actions) actions.innerHTML = '<span class="mg-agent-action-result">Action cancelled. No account data was changed.</span>';
        }
        setStatus('Prepared action cancelled. No account data was changed.', 'success');
        return;
      }

      var receipt = data.receipt || {};
      if (article) {
        article.classList.remove('is-action-review');
        article.classList.add('is-action-complete');
        if (actions) actions.innerHTML = '<span class="mg-agent-action-result"><b>Saved.</b> ' + esc(receipt.summary || 'The account change was completed.') + '</span>';
      }
      setStatus(receipt.summary || 'Account change completed.', 'success');
      if (typeof loadDashboard === 'function') await loadDashboard(false);
      enhanceSummary();
    } catch (error) {
      buttons.forEach(function (node) { node.disabled = false; });
      button.textContent = original;
      setStatus(error.message || 'Unable to complete the confirmed action.', 'error');
    }
  }

  root.addEventListener('click', function (event) {
    var action = event.target.closest('[data-agent-action-draft]');
    if (action) {
      event.preventDefault();
      event.stopImmediatePropagation();
      executeDraft(action);
      return;
    }

    var result = event.target.closest('[data-agent-result-url]');
    if (result) {
      event.preventDefault();
      event.stopImmediatePropagation();
      var url = String(result.dataset.agentResultUrl || '');
      if (url && url.charAt(0) === '/') window.location.href = url;
    }
  }, true);

  if (feed && window.MutationObserver) new MutationObserver(enhanceAll).observe(feed, { childList: true, subtree: true });
  if (summary && window.MutationObserver) new MutationObserver(enhanceSummary).observe(summary, { childList: true, subtree: true });
  enhanceAll();
  window.setTimeout(enhanceSummary, 100);
});
