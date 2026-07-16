document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var app = window.MicrogifterPersonalAgent;
  if (!app || !app.root || !window.Microgifter) return;
  var root = app.root;
  var feed = app.ui && app.ui.feed;
  if (!feed) return;

  function text(value) { return String(value == null ? '' : value).trim(); }
  function storageKey() { return 'mg:agent-attribution:v1'; }
  function remember(card, action) {
    if (!card || !card.attribution_token) return;
    var payload = {
      token: text(card.attribution_token),
      opportunity_id: text(card.opportunity_id),
      action: text(action),
      entity_type: text(card.result_kind),
      entity_id: text(card.id),
      saved_at: new Date().toISOString()
    };
    try { window.sessionStorage.setItem(storageKey(), JSON.stringify(payload)); } catch (_) { /* optional */ }
  }
  function post(card, action, extra) {
    return window.Microgifter.post('/api/user-agent/opportunity-action.php', Object.assign({
      opportunity_id: text(card.opportunity_id),
      attribution_token: text(card.attribution_token),
      action: action,
      page_path: window.location.pathname + window.location.search,
      referrer_path: document.referrer || '',
      idempotency_key: action + ':' + text(card.opportunity_id) + ':' + Date.now()
    }, extra || {}));
  }
  function scheduleReminder(card, hours) {
    return window.Microgifter.post('/api/user-agent/opportunity-recovery.php', {
      action:'schedule',
      opportunity_id:text(card.opportunity_id),
      attribution_token:text(card.attribution_token),
      delay_hours:Number(hours || 24),
      page_path:window.location.pathname + window.location.search
    });
  }
  function safeInternal(url) {
    try {
      var parsed = new URL(text(url), window.location.origin);
      return parsed.origin === window.location.origin ? parsed.pathname + parsed.search + parsed.hash : '';
    } catch (_) { return ''; }
  }
  function status(article, message, type) {
    var node = article.querySelector('.mg-agent-opportunity-status');
    if (!node) {
      node = document.createElement('div');
      node.className = 'mg-agent-opportunity-status';
      var footer = article.querySelector('.mg-agent-marketplace-actions');
      if (footer) footer.appendChild(node);
    }
    if (!node) return;
    node.textContent = message || '';
    node.classList.toggle('is-error', type === 'error');
    node.classList.toggle('is-success', type === 'success');
  }
  function actionButton(action, card) {
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'mg-agent-opportunity-button' + (action.primary ? ' is-primary' : '') + (action.key === 'hide' ? ' is-muted' : '') + (action.key === 'remind' ? ' is-reminder' : '');
    button.textContent = action.label || action.key;
    button.setAttribute('data-opportunity-action', action.key);
    button.setAttribute('data-opportunity-url', safeInternal(action.url || ''));
    button.setAttribute('aria-label', (action.label || action.key) + ' ' + text(card.title));
    return button;
  }
  function enhanceArticle(article, card) {
    if (!article || !card || !card.opportunity_id || article.getAttribute('data-opportunity-actions') === '1') return;
    article.setAttribute('data-opportunity-actions', '1');
    article.setAttribute('data-opportunity-id', text(card.opportunity_id));
    var content = article.querySelector('.mg-agent-marketplace-content');
    if (!content) return;
    var existing = article.querySelector('.mg-agent-marketplace-actions');
    if (existing) existing.remove();
    var footer = document.createElement('footer');
    footer.className = 'mg-agent-marketplace-actions is-opportunity-actions';
    (Array.isArray(card.actions) ? card.actions : []).forEach(function (action) {
      if (!action || !action.key) return;
      var button = actionButton(action, card);
      if (action.key === 'save' && card.opportunity_state === 'saved') {
        button.textContent = 'Saved';
        button.classList.add('is-saved');
      }
      footer.appendChild(button);
    });
    footer.appendChild(document.createElement('div')).className = 'mg-agent-opportunity-status';
    content.appendChild(footer);
    if (article.getAttribute('data-opportunity-viewed') !== '1') {
      article.setAttribute('data-opportunity-viewed', '1');
      post(card, 'view', { idempotency_key: 'view:' + text(card.opportunity_id) }).catch(function () {});
    }
  }
  function enhanceGrid(grid) {
    if (!grid) return;
    var cards = Array.isArray(grid._agentCards) ? grid._agentCards : [];
    Array.prototype.slice.call(grid.children).forEach(function (article, index) {
      enhanceArticle(article, cards[index]);
    });
  }
  function scan(node) {
    if (!node || node.nodeType !== 1) return;
    if (node.matches && node.matches('.mg-personal-agent-card-grid')) enhanceGrid(node);
    if (node.querySelectorAll) node.querySelectorAll('.mg-personal-agent-card-grid').forEach(enhanceGrid);
  }

  feed.querySelectorAll('.mg-personal-agent-card-grid').forEach(enhanceGrid);
  if (window.MutationObserver) {
    new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        Array.prototype.forEach.call(mutation.addedNodes || [], scan);
      });
    }).observe(feed, { childList:true, subtree:true });
  }

  root.addEventListener('click', function (event) {
    var button = event.target.closest('[data-opportunity-action]');
    if (!button) return;
    var article = button.closest('[data-opportunity-id]');
    var grid = button.closest('.mg-personal-agent-card-grid');
    var cards = grid && Array.isArray(grid._agentCards) ? grid._agentCards : [];
    var index = article && grid ? Array.prototype.indexOf.call(grid.children, article) : -1;
    var card = index >= 0 ? cards[index] : null;
    if (!card) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    var action = button.getAttribute('data-opportunity-action');
    var destination = safeInternal(button.getAttribute('data-opportunity-url'));
    button.disabled = true;
    status(article, action === 'save' ? 'Saving…' : (action === 'remind' ? 'Scheduling reminder…' : 'Recording action…'), '');

    if (action === 'save') {
      post(card, card.opportunity_state === 'saved' ? 'unsave' : 'save').then(function (response) {
        var data = response && response.data ? response.data : response || {};
        card.opportunity_state = data.opportunity && data.opportunity.state || (card.opportunity_state === 'saved' ? 'active' : 'saved');
        button.textContent = card.opportunity_state === 'saved' ? 'Saved' : 'Save';
        button.classList.toggle('is-saved', card.opportunity_state === 'saved');
        status(article, card.opportunity_state === 'saved' ? 'Saved to My Lists.' : 'Removed from saved opportunities.', 'success');
      }).catch(function (error) {
        status(article, error.message || 'Unable to update this opportunity.', 'error');
      }).finally(function () { button.disabled = false; });
      return;
    }
    if (action === 'remind') {
      scheduleReminder(card,24).then(function () {
        button.textContent = 'Reminder set';
        button.classList.add('is-saved');
        status(article,'Reminder scheduled for about 24 hours from now. Manage it under Reminders.','success');
      }).catch(function (error) {
        status(article,error.message || 'Unable to schedule this reminder.','error');
      }).finally(function () { button.disabled = false; });
      return;
    }
    if (action === 'hide') {
      post(card, 'hide').then(function () {
        article.classList.add('is-opportunity-hidden');
      }).catch(function (error) {
        button.disabled = false;
        status(article, error.message || 'Unable to hide this recommendation.', 'error');
      });
      return;
    }

    remember(card, action);
    post(card, action).catch(function () {}).finally(function () {
      if (destination) window.location.href = destination;
      else {
        button.disabled = false;
        status(article, 'Action recorded.', 'success');
      }
    });
  }, true);
});
