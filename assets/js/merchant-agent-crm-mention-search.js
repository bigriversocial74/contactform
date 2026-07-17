document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-merchant-agent-chat]');
  var form = document.querySelector('[data-agent-chat-form]');
  var textarea = form && form.querySelector('[data-agent-chat-textarea],textarea[name="message"]');
  var feed = root && root.querySelector('[data-agent-chat-feed]');
  var status = root && root.querySelector('[data-agent-chat-status]');
  var send = form && form.querySelector('[data-agent-chat-send]');
  if (!root || !form || !textarea || !feed || !window.Microgifter) return;

  var suggestionBox = document.createElement('div');
  suggestionBox.className = 'mg-agent-crm-mentions';
  suggestionBox.dataset.agentCrmMentions = '';
  suggestionBox.hidden = true;
  suggestionBox.setAttribute('role', 'listbox');
  suggestionBox.setAttribute('aria-label', 'Merchant CRM contact matches');
  form.appendChild(suggestionBox);

  var searchSequence = 0;
  var activeSuggestion = -1;
  var activeFragment = null;
  var suggestionContacts = [];
  var hydrationCache = new Map();
  var hydrating = false;

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char];
    });
  }

  function payload(response) { return response && response.data ? response.data : response; }
  function human(value) { return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, function (char) { return char.toUpperCase(); }); }
  function pureMention(value) { return /^@[a-z0-9][a-z0-9._-]{0,119}$/i.test(String(value || '').trim()); }
  function queryFromMention(value) { return String(value || '').trim().replace(/^@+/, ''); }
  function contactKey(contact) { return String(contact && contact.id || contact && contact.mention || ''); }

  function setStatus(message, type) {
    if (!status) return;
    status.textContent = message || '';
    status.className = 'mg-form-status' + (type ? ' is-' + type : '');
  }

  function setComposerBusy(on) {
    textarea.disabled = !!on;
    if (send) {
      send.disabled = !!on || !textarea.value.trim();
      send.classList.toggle('is-loading', !!on);
      send.textContent = on ? '…' : '↑';
    }
  }

  function currentFragment() {
    var caret = typeof textarea.selectionStart === 'number' ? textarea.selectionStart : textarea.value.length;
    var before = textarea.value.slice(0, caret);
    var match = before.match(/(^|\s)@([a-z0-9._-]{1,120})$/i);
    if (!match) return null;
    return {
      query: match[2],
      start: caret - match[2].length - 1,
      end: caret
    };
  }

  function closeSuggestions() {
    suggestionBox.hidden = true;
    suggestionBox.replaceChildren();
    textarea.removeAttribute('aria-activedescendant');
    activeSuggestion = -1;
    activeFragment = null;
    suggestionContacts = [];
  }

  function setActiveSuggestion(index) {
    var options = Array.prototype.slice.call(suggestionBox.querySelectorAll('[data-agent-crm-suggestion]'));
    if (!options.length) return;
    activeSuggestion = Math.max(0, Math.min(options.length - 1, index));
    options.forEach(function (option, optionIndex) {
      var active = optionIndex === activeSuggestion;
      option.classList.toggle('is-active', active);
      option.setAttribute('aria-selected', active ? 'true' : 'false');
      if (active) {
        textarea.setAttribute('aria-activedescendant', option.id);
        option.scrollIntoView({ block: 'nearest' });
      }
    });
  }

  function replaceMention(contact) {
    var fragment = activeFragment || currentFragment();
    if (!fragment || !contact || !contact.mention) return;
    var next = textarea.value.slice(0, fragment.start) + contact.mention + ' ' + textarea.value.slice(fragment.end);
    var caret = fragment.start + contact.mention.length + 1;
    textarea.value = next;
    textarea.focus();
    textarea.setSelectionRange(caret, caret);
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
    closeSuggestions();
  }

  function suggestionHtml(contact, index) {
    var identity = contact.email || contact.phone || (contact.has_account ? 'Linked account' : 'CRM contact');
    return '<button id="mg-agent-crm-suggestion-' + index + '" type="button" role="option" aria-selected="false" data-agent-crm-suggestion data-contact-index="' + index + '">' +
      '<span class="mg-agent-crm-suggestion-avatar" aria-hidden="true">' + esc((contact.name || contact.username || 'C').slice(0, 1).toUpperCase()) + '</span>' +
      '<span class="mg-agent-crm-suggestion-copy"><strong>' + esc(contact.name || 'Unnamed contact') + '</strong><small>' + esc(contact.mention || '') + ' · ' + esc(identity) + '</small></span>' +
      '<span class="mg-agent-crm-suggestion-meta">' + esc(human(contact.stage || 'lead')) + '</span>' +
      '</button>';
  }

  function renderSuggestions(result, fragment) {
    suggestionContacts = Array.isArray(result.contacts) ? result.contacts : [];
    activeFragment = fragment;
    if (!suggestionContacts.length) {
      suggestionBox.innerHTML = '<div class="mg-agent-crm-suggestion-empty">No CRM contacts match <strong>@' + esc(fragment.query) + '</strong>.</div>';
      suggestionBox.hidden = false;
      activeSuggestion = -1;
      return;
    }
    suggestionBox.innerHTML = '<div class="mg-agent-crm-suggestion-head"><span>CRM contacts</span><b>' + Number(result.total || suggestionContacts.length).toLocaleString() + ' matches</b></div>' +
      suggestionContacts.map(suggestionHtml).join('') +
      '<div class="mg-agent-crm-suggestion-foot">Press Enter to return every matching result, or choose one contact to insert the full @username.</div>';
    suggestionBox.hidden = false;
    activeSuggestion = -1;
  }

  async function searchSuggestions(fragment) {
    var sequence = ++searchSequence;
    try {
      var result = payload(await Microgifter.get('/api/merchant/crm-search.php?q=' + encodeURIComponent(fragment.query) + '&limit=10&offset=0'));
      if (sequence !== searchSequence) return;
      var latest = currentFragment();
      if (!latest || latest.query.toLowerCase() !== fragment.query.toLowerCase()) return closeSuggestions();
      renderSuggestions(result || {}, latest);
    } catch (error) {
      if (sequence !== searchSequence) return;
      closeSuggestions();
    }
  }

  function scheduleSuggestions() {
    var fragment = currentFragment();
    if (!fragment) return closeSuggestions();
    var sequence = ++searchSequence;
    window.setTimeout(function () {
      if (sequence !== searchSequence) return;
      searchSuggestions(fragment);
    }, 170);
  }

  function compactDate(value) {
    var parsed = Date.parse(value || '');
    if (!parsed) return 'No recent activity';
    return new Date(parsed).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
  }

  function resultRowHtml(contact) {
    var identity = [contact.email, contact.phone].filter(Boolean).join(' · ') || 'No email or phone';
    var source = contact.campaign_title || human(contact.campaign_type || contact.source || 'Direct CRM');
    var account = contact.has_account ? 'Account linked' : 'CRM only';
    if (contact.email_verified) account += ' · Verified';
    return '<tr data-agent-crm-contact-row data-contact-id="' + esc(contact.id) + '">' +
      '<td class="mg-agent-crm-result-contact"><strong>' + esc(contact.name || 'Unnamed contact') + '</strong><b>' + esc(contact.mention || '') + '</b><small>' + esc(identity) + '</small></td>' +
      '<td><strong>' + esc(human(contact.stage || 'lead')) + '</strong><small>' + esc(human(contact.status || 'active')) + ' · ' + esc(account) + '</small></td>' +
      '<td><strong>' + esc(source) + '</strong><small>' + esc(human(contact.source || contact.campaign_type || 'CRM')) + '</small></td>' +
      '<td><strong>' + Number(contact.score || 0) + ' · ' + esc(human(contact.score_label || 'score')) + '</strong><small>' + esc(compactDate(contact.last_activity_at)) + ' · ' + esc(contact.next_best_action || '') + '</small></td>' +
      '<td class="mg-agent-crm-result-actions"><button type="button" data-agent-crm-select-contact data-contact-id="' + esc(contact.id) + '" data-contact-mention="' + esc(contact.mention || '') + '">Select</button>' +
      '<a href="' + esc(contact.profile_url || contact.crm_url || '/merchant-crm.php') + '">Profile</a>' +
      '<a href="' + esc(contact.timeline_url || contact.crm_url || '/merchant-crm.php') + '">Timeline</a>' +
      '<a href="' + esc(contact.message_url || contact.crm_url || '/merchant-crm.php') + '">Message</a>' +
      '<a href="' + esc(contact.reward_url || contact.crm_url || '/merchant-crm.php') + '">Reward</a></td>' +
      '</tr>';
  }

  function resultPanelHtml(result) {
    var contacts = Array.isArray(result.contacts) ? result.contacts : [];
    var total = Number(result.total || contacts.length);
    var query = String(result.query || '');
    if (!result.schema_ready) {
      return '<section class="mg-agent-crm-results is-error" data-agent-crm-results><strong>CRM search unavailable</strong><p>The current Merchant CRM schema is not available.</p></section>';
    }
    if (!contacts.length) {
      return '<section class="mg-agent-crm-results is-empty" data-agent-crm-results><div class="mg-agent-crm-results-head"><div><span>CRM search</span><strong>No contacts matched @' + esc(query) + '</strong></div><a href="/merchant-crm.php?search=' + encodeURIComponent(query) + '">Open Merchant CRM</a></div></section>';
    }
    return '<section class="mg-agent-crm-results" data-agent-crm-results data-query="' + esc(query) + '">' +
      '<div class="mg-agent-crm-results-head"><div><span>Merchant CRM search</span><strong>' + total.toLocaleString() + ' contact' + (total === 1 ? '' : 's') + ' matched @' + esc(query) + '</strong><small>Results are limited to this authorized merchant workspace.</small></div><a href="/merchant-crm.php?search=' + encodeURIComponent(query) + '">Open in CRM</a></div>' +
      '<div class="mg-agent-crm-table-wrap"><table class="mg-agent-crm-table"><thead><tr><th>Contact</th><th>CRM</th><th>Source</th><th>Engagement</th><th>Actions</th></tr></thead><tbody>' + contacts.map(resultRowHtml).join('') + '</tbody></table></div>' +
      '<div class="mg-agent-crm-results-foot"><span>Showing all ' + contacts.length.toLocaleString() + ' loaded matches.</span></div>' +
      '</section>';
  }

  async function fetchAllResults(query, seed) {
    var result = seed && seed.query ? seed : payload(await Microgifter.get('/api/merchant/crm-search.php?q=' + encodeURIComponent(query) + '&limit=100&offset=0'));
    result = result || { query: query, contacts: [], total: 0 };
    result.query = result.query || query;
    result.contacts = Array.isArray(result.contacts) ? result.contacts.slice() : [];
    var seen = new Set(result.contacts.map(contactKey));
    var offset = Number(result.next_offset || result.contacts.length || 0);
    var safety = 0;
    while (result.has_more && safety < 100) {
      var page = payload(await Microgifter.get('/api/merchant/crm-search.php?q=' + encodeURIComponent(query) + '&limit=100&offset=' + offset));
      var items = page && Array.isArray(page.contacts) ? page.contacts : [];
      items.forEach(function (contact) {
        var key = contactKey(contact);
        if (!seen.has(key)) { seen.add(key); result.contacts.push(contact); }
      });
      result.has_more = !!(page && page.has_more);
      offset = Number(page && page.next_offset || offset + items.length);
      if (!items.length) break;
      safety += 1;
    }
    result.total = Number(result.total || result.contacts.length);
    return result;
  }

  function appendChatMessage(role, body, pending) {
    var article = document.createElement('article');
    article.className = 'mg-agent-chat-message ' + (role === 'user' ? 'is-user' : 'is-agent') + (pending ? ' is-pending' : '');
    article.innerHTML = '<div class="mg-agent-chat-bubble"><div class="mg-agent-chat-meta"><strong>' + (role === 'user' ? 'You' : 'Merchant Agent') + '</strong><time>' + esc(new Date().toLocaleString()) + '</time></div><p>' + esc(body) + '</p></div>';
    feed.appendChild(article);
    feed.scrollTop = feed.scrollHeight;
    return article;
  }

  async function runChatSearch(message) {
    var query = queryFromMention(message);
    closeSuggestions();
    textarea.value = '';
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
    setComposerBusy(true);
    setStatus('Searching every matching Merchant CRM contact…', '');
    var userArticle = appendChatMessage('user', message, false);
    var agentArticle = appendChatMessage('assistant', 'Searching Merchant CRM contacts…', true);
    try {
      var response = payload(await Microgifter.post('/api/ai/merchant-agent-chat.php', {
        action: 'crm_search',
        message: message,
        thread_id: (root.querySelector('[data-agent-thread-select]') || {}).value || ''
      }));
      var all = await fetchAllResults(query, response && response.crm_search);
      agentArticle.classList.remove('is-pending');
      var bubble = agentArticle.querySelector('.mg-agent-chat-bubble');
      bubble.querySelector('p').textContent = response && response.assistant_message ? response.assistant_message.body : ('Found ' + all.total + ' CRM contacts.');
      bubble.insertAdjacentHTML('beforeend', resultPanelHtml(all));
      agentArticle.dataset.agentCrmHydrated = 'true';
      userArticle.dataset.agentCrmQuery = query;
      setStatus(all.total ? 'CRM contact results loaded.' : 'No matching CRM contacts were found.', all.total ? 'success' : '');
      feed.scrollTop = feed.scrollHeight;
    } catch (error) {
      agentArticle.classList.remove('is-pending');
      agentArticle.classList.add('is-error');
      var paragraph = agentArticle.querySelector('p');
      if (paragraph) paragraph.textContent = String(error && error.message || 'Unable to search Merchant CRM contacts.');
      setStatus(String(error && error.message || 'Unable to search Merchant CRM contacts.'), 'error');
    } finally {
      setComposerBusy(false);
      textarea.focus();
    }
  }

  function queryForAgentArticle(article) {
    var previous = article.previousElementSibling;
    if (!previous || !previous.classList.contains('is-user')) return '';
    var paragraph = previous.querySelector('.mg-agent-chat-bubble > p');
    var value = paragraph ? paragraph.textContent.trim() : '';
    return pureMention(value) ? queryFromMention(value) : '';
  }

  async function hydrateArticle(article, query) {
    if (!article || article.dataset.agentCrmHydrated === 'true' || article.classList.contains('is-pending')) return;
    article.dataset.agentCrmHydrated = 'loading';
    try {
      var promise = hydrationCache.get(query.toLowerCase());
      if (!promise) {
        promise = fetchAllResults(query);
        hydrationCache.set(query.toLowerCase(), promise);
      }
      var result = await promise;
      if (!article.isConnected) return;
      var bubble = article.querySelector('.mg-agent-chat-bubble');
      if (bubble && !bubble.querySelector('[data-agent-crm-results]')) bubble.insertAdjacentHTML('beforeend', resultPanelHtml(result));
      article.dataset.agentCrmHydrated = 'true';
    } catch (error) {
      article.dataset.agentCrmHydrated = 'error';
    }
  }

  function hydrateHistory() {
    if (hydrating) return;
    hydrating = true;
    window.requestAnimationFrame(function () {
      Array.prototype.slice.call(feed.querySelectorAll('.mg-agent-chat-message.is-agent')).forEach(function (article) {
        var query = queryForAgentArticle(article);
        if (query) hydrateArticle(article, query);
      });
      hydrating = false;
    });
  }

  form.addEventListener('submit', function (event) {
    var value = textarea.value.trim();
    if (!pureMention(value)) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    runChatSearch(value);
  }, true);

  textarea.addEventListener('input', scheduleSuggestions, true);
  textarea.addEventListener('keydown', function (event) {
    if (suggestionBox.hidden) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      event.stopImmediatePropagation();
      closeSuggestions();
      return;
    }
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      event.stopImmediatePropagation();
      setActiveSuggestion(activeSuggestion + (event.key === 'ArrowDown' ? 1 : -1));
      return;
    }
    if ((event.key === 'Tab' || event.key === 'Enter') && activeSuggestion >= 0 && !pureMention(textarea.value)) {
      event.preventDefault();
      event.stopImmediatePropagation();
      replaceMention(suggestionContacts[activeSuggestion]);
    }
  }, true);

  suggestionBox.addEventListener('click', function (event) {
    var option = event.target.closest('[data-agent-crm-suggestion]');
    if (!option) return;
    var index = parseInt(option.getAttribute('data-contact-index') || '-1', 10);
    if (index >= 0 && suggestionContacts[index]) replaceMention(suggestionContacts[index]);
  });

  feed.addEventListener('click', function (event) {
    var select = event.target.closest('[data-agent-crm-select-contact]');
    if (!select) return;
    var mention = select.getAttribute('data-contact-mention') || '';
    if (!mention) return;
    textarea.value = (textarea.value.trim() ? textarea.value.trim() + ' ' : '') + mention + ' ';
    textarea.focus();
    textarea.setSelectionRange(textarea.value.length, textarea.value.length);
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
    setStatus(mention + ' added to the Merchant Agent prompt.', 'success');
  });

  document.addEventListener('click', function (event) {
    if (!suggestionBox.hidden && !event.target.closest('[data-agent-crm-mentions]') && event.target !== textarea) closeSuggestions();
  });

  if (window.MutationObserver) new MutationObserver(hydrateHistory).observe(feed, { childList: true, subtree: true });
  hydrateHistory();
});
