document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  function debounce(callback, delay) {
    var timer = null;
    return function () {
      var args = arguments;
      clearTimeout(timer);
      timer = setTimeout(function () { callback.apply(null, args); }, delay);
    };
  }

  function setStatus(node, message, isError) {
    if (!node) return;
    node.textContent = message || '';
    node.classList.toggle('is-error', Boolean(isError));
  }

  function filterCards(input, selector, emptySelector) {
    if (!input) return;
    var cards = Array.from(document.querySelectorAll(selector));
    var empty = document.querySelector(emptySelector);
    var apply = function () {
      var query = input.value.trim().toLowerCase();
      var visible = 0;
      cards.forEach(function (card) {
        var match = !query || String(card.dataset.searchText || '').indexOf(query) !== -1;
        card.hidden = !match;
        if (match) visible += 1;
      });
      if (empty) empty.hidden = visible !== 0;
    };
    input.addEventListener('input', apply);
  }

  filterCards(document.querySelector('[data-user-list-filter]'), '[data-user-list-card]', '[data-user-list-no-results]');
  filterCards(document.querySelector('[data-user-contact-filter]'), '[data-user-contact-card]', '[data-user-contact-no-results]');

  var page = document.querySelector('[data-user-list-page]');
  var panel = document.querySelector('[data-contact-panel]');
  if (!page || !panel) return;

  var listId = String(page.dataset.listId || '');
  var searchInput = panel.querySelector('[data-contact-search]');
  var searchStatus = panel.querySelector('[data-contact-search-status]');
  var searchResults = panel.querySelector('[data-contact-search-results]');
  var privateForm = panel.querySelector('[data-private-contact-form]');
  var privateStatus = panel.querySelector('[data-private-contact-status]');

  function openPanel() {
    panel.hidden = false;
    panel.setAttribute('aria-hidden', 'false');
    document.body.classList.add('mg-contact-panel-open');
    window.requestAnimationFrame(function () {
      var activeInput = panel.querySelector('[data-contact-panel-view]:not([hidden]) input');
      if (activeInput) activeInput.focus();
    });
  }

  function closePanel() {
    panel.hidden = true;
    panel.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('mg-contact-panel-open');
  }

  document.querySelectorAll('[data-contact-panel-open]').forEach(function (button) {
    button.addEventListener('click', openPanel);
  });
  panel.querySelectorAll('[data-contact-panel-close]').forEach(function (button) {
    button.addEventListener('click', closePanel);
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && !panel.hidden) closePanel();
  });

  panel.querySelectorAll('[data-contact-panel-tab]').forEach(function (tab) {
    tab.addEventListener('click', function () {
      var key = tab.dataset.contactPanelTab;
      panel.querySelectorAll('[data-contact-panel-tab]').forEach(function (item) {
        item.classList.toggle('is-active', item === tab);
      });
      panel.querySelectorAll('[data-contact-panel-view]').forEach(function (view) {
        view.hidden = view.dataset.contactPanelView !== key;
      });
      var input = panel.querySelector('[data-contact-panel-view="' + key + '"] input');
      if (input) input.focus();
    });
  });

  function resultAvatar(contact) {
    var avatar = document.createElement('div');
    avatar.className = 'mg-contact-result-avatar';
    if (contact.avatar_url) {
      var image = document.createElement('img');
      image.src = contact.avatar_url;
      image.alt = '';
      avatar.appendChild(image);
    } else {
      avatar.textContent = String(contact.display_name || '?').slice(0, 1).toUpperCase();
    }
    return avatar;
  }

  function renderResults(contacts) {
    searchResults.replaceChildren();
    if (!contacts.length) {
      var empty = document.createElement('p');
      empty.className = 'mg-contact-search-help';
      empty.textContent = 'No matching contacts were found. Create a private contact instead.';
      searchResults.appendChild(empty);
      return;
    }
    contacts.forEach(function (contact) {
      var row = document.createElement('article');
      row.className = 'mg-contact-result';
      row.appendChild(resultAvatar(contact));

      var copy = document.createElement('div');
      var name = document.createElement('strong');
      name.textContent = contact.display_name || 'Contact';
      var detail = document.createElement('small');
      detail.textContent = contact.already_in_list ? 'Already in this list' : (contact.subtitle || 'Contact');
      copy.append(name, detail);
      row.appendChild(copy);

      var button = document.createElement('button');
      button.type = 'button';
      button.textContent = contact.already_in_list ? 'Added' : (contact.eligible ? 'Add' : 'Unavailable');
      button.disabled = Boolean(contact.already_in_list || !contact.eligible);
      if (!button.disabled) {
        button.addEventListener('click', async function () {
          button.disabled = true;
          button.textContent = 'Adding…';
          setStatus(searchStatus, '', false);
          try {
            await Microgifter.post('/api/user-lists/add-contact.php', {
              list_id: listId,
              contact_type: contact.type,
              contact_id: contact.id
            });
            button.textContent = 'Added';
            window.location.reload();
          } catch (error) {
            button.disabled = false;
            button.textContent = 'Add';
            setStatus(searchStatus, error.message || 'Unable to add contact.', true);
          }
        });
      }
      row.appendChild(button);
      searchResults.appendChild(row);
    });
  }

  var runSearch = debounce(async function () {
    var query = searchInput ? searchInput.value.trim() : '';
    if (query.length < 2) {
      searchResults.replaceChildren();
      setStatus(searchStatus, 'Enter at least two characters.', false);
      return;
    }
    setStatus(searchStatus, 'Searching relationships and private contacts…', false);
    try {
      var response = await Microgifter.get('/api/user-lists/search-contacts.php?q=' + encodeURIComponent(query) + '&list_id=' + encodeURIComponent(listId));
      var contacts = response && response.data && Array.isArray(response.data.contacts) ? response.data.contacts : [];
      renderResults(contacts);
      setStatus(searchStatus, contacts.length + (contacts.length === 1 ? ' result' : ' results'), false);
    } catch (error) {
      searchResults.replaceChildren();
      setStatus(searchStatus, error.message || 'Unable to search contacts.', true);
    }
  }, 280);
  if (searchInput) searchInput.addEventListener('input', runSearch);

  if (privateForm) {
    privateForm.addEventListener('submit', async function (event) {
      event.preventDefault();
      var submit = privateForm.querySelector('button[type="submit"]');
      var formData = new FormData(privateForm);
      var payload = {};
      formData.forEach(function (value, key) {
        if (key.endsWith('[]')) {
          var cleanKey = key.slice(0, -2);
          payload[cleanKey] = payload[cleanKey] || [];
          payload[cleanKey].push(value);
        } else {
          payload[key] = value;
        }
      });
      submit.disabled = true;
      submit.textContent = 'Creating…';
      setStatus(privateStatus, '', false);
      try {
        await Microgifter.post('/api/user-contacts/create.php', payload);
        setStatus(privateStatus, 'Contact created and added.', false);
        window.location.reload();
      } catch (error) {
        submit.disabled = false;
        submit.textContent = 'Create and add';
        setStatus(privateStatus, error.message || 'Unable to create contact.', true);
      }
    });
  }

  document.querySelectorAll('[data-remove-membership]').forEach(function (button) {
    button.addEventListener('click', async function () {
      if (!window.confirm('Remove this contact from this list? The contact record will not be deleted.')) return;
      button.disabled = true;
      button.textContent = 'Removing…';
      try {
        await Microgifter.post('/api/user-lists/remove-contact.php', { membership_id: button.dataset.removeMembership });
        var card = button.closest('[data-user-contact-card]');
        if (card) card.remove();
      } catch (error) {
        button.disabled = false;
        button.textContent = 'Remove';
        window.alert(error.message || 'Unable to remove contact.');
      }
    });
  });

  document.querySelectorAll('[data-agent-contact-prompt]').forEach(function (button) {
    button.addEventListener('click', function () {
      var name = String(button.dataset.agentContactPrompt || 'this contact');
      var composer = document.querySelector('[data-agent-composer] input');
      if (composer) {
        composer.value = 'Find gifts for ' + name + '.';
        composer.focus();
      } else {
        window.location.href = '/agent.php?prompt=' + encodeURIComponent('Find gifts for ' + name + '.');
      }
    });
  });
});
