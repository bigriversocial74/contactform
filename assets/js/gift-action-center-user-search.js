document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var app = document.querySelector('[data-gift-center]');
  if (!app || !window.Microgifter) return;

  var searchInput = app.querySelector('[data-user-profile-search]');
  var searchResults = app.querySelector('[data-gift-search-results]');
  var searchClear = app.querySelector('[data-gift-search-clear]');
  if (!searchInput || !searchResults) return;

  var activeQuery = '';
  var searchTimer = null;
  var recipients = [];
  var loading = false;

  function responseData(response) {
    return response && response.data ? response.data : (response || {});
  }

  function text(value) {
    return String(value == null ? '' : value).trim();
  }

  function initials(name) {
    return text(name || 'M').split(/\s+/).filter(Boolean).slice(0, 2).map(function (part) {
      return part.charAt(0);
    }).join('').toUpperCase() || 'M';
  }

  function clear(node) {
    if (node) node.replaceChildren();
  }

  function safeProfileId(profile) {
    return text(profile.profile_id || profile.recipient_profile_id || profile.profile_slug || profile.recipient_slug);
  }

  function safeUserId(profile) {
    return text(profile.recipient_user_id || profile.user_id);
  }

  function safeTargetId(profile) {
    return safeProfileId(profile) || safeUserId(profile);
  }

  function safeAvatarUrl(profile) {
    var url = text(profile.avatar_url);
    if (!url) return '';
    if (url.charAt(0) === '/' && url.charAt(1) !== '/') return url;
    return /^https:\/\//i.test(url) ? url : '';
  }

  function profileMeta(profile) {
    var handle = text(profile.profile_slug || profile.recipient_slug);
    var type = text(profile.profile_type || profile.source || 'profile');
    if (handle) return '@' + handle + ' · ' + type;
    return text(profile.email_hint || type || 'Microgifter profile');
  }

  function closeResults() {
    clear(searchResults);
    searchResults.hidden = true;
    searchInput.setAttribute('aria-expanded', 'false');
  }

  function setClearVisible() {
    if (searchClear) searchClear.hidden = !text(searchInput.value);
  }

  function avatarNode(profile) {
    var wrap = document.createElement('span');
    wrap.className = 'mg-user-search-avatar';
    var url = safeAvatarUrl(profile);
    if (url) {
      var img = document.createElement('img');
      img.src = url;
      img.alt = '';
      img.loading = 'lazy';
      img.addEventListener('error', function () {
        wrap.textContent = initials(profile.display_name);
      }, { once: true });
      wrap.appendChild(img);
    } else {
      wrap.textContent = initials(profile.display_name);
    }
    return wrap;
  }

  function button(label, className, disabled) {
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = className;
    btn.textContent = label;
    btn.disabled = !!disabled;
    return btn;
  }

  function renderProfile(profile) {
    var row = document.createElement('div');
    var profileId = safeProfileId(profile);
    var userId = safeUserId(profile);
    var targetId = profileId || userId;

    row.className = 'mg-user-search-result';
    row.setAttribute('role', 'option');
    row.dataset.userSearchResult = targetId;
    row.dataset.recipientUserId = userId;
    row.dataset.profileId = profileId;
    row.dataset.profileName = text(profile.display_name || 'Microgifter member');

    row.appendChild(avatarNode(profile));

    var body = document.createElement('div');
    body.className = 'mg-user-search-body';
    var name = document.createElement('strong');
    name.textContent = text(profile.display_name || 'Microgifter member');
    var meta = document.createElement('small');
    meta.textContent = profileMeta(profile);
    body.append(name, meta);
    row.appendChild(body);

    var actions = document.createElement('div');
    actions.className = 'mg-user-search-actions';
    var follow = button(profile.is_following ? 'Following' : 'Follow', 'mg-user-search-action', !targetId);
    follow.dataset.userSearchFollow = targetId;
    follow.dataset.userSearchProfile = profileId;
    follow.dataset.userSearchUser = userId;
    follow.dataset.following = profile.is_following ? 'true' : 'false';
    var message = button('Message', 'mg-user-search-action is-primary', !targetId);
    message.dataset.userSearchMessage = targetId;
    message.dataset.userSearchProfile = profileId;
    message.dataset.userSearchUser = userId;
    actions.append(follow, message);
    row.appendChild(actions);

    return row;
  }

  function renderResults(message) {
    setClearVisible();
    clear(searchResults);
    searchInput.setAttribute('aria-expanded', 'true');
    searchResults.hidden = false;

    if (message) {
      var empty = document.createElement('div');
      empty.className = 'mg-gift-search-empty';
      empty.textContent = message;
      searchResults.appendChild(empty);
      return;
    }

    if (!recipients.length) {
      var none = document.createElement('div');
      none.className = 'mg-gift-search-empty';
      none.textContent = 'No matching users found.';
      searchResults.appendChild(none);
      return;
    }

    recipients.forEach(function (profile) {
      searchResults.appendChild(renderProfile(profile));
    });
  }

  async function searchUsers() {
    var query = text(searchInput.value);
    setClearVisible();
    if (query.length < 2) {
      recipients = [];
      closeResults();
      return;
    }
    activeQuery = query;
    loading = true;
    renderResults('Searching users…');
    try {
      var response = await Microgifter.get('/api/account/action-center-recipient-search.php?q=' + encodeURIComponent(query));
      if (activeQuery !== query) return;
      var data = responseData(response);
      recipients = Array.isArray(data.recipients) ? data.recipients : [];
      renderResults('');
    } catch (error) {
      recipients = [];
      renderResults('Unable to search users right now.');
    } finally {
      loading = false;
    }
  }

  function scheduleSearch() {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(searchUsers, 180);
  }

  async function toggleFollow(buttonNode) {
    var profileId = text(buttonNode.dataset.userSearchProfile || buttonNode.dataset.userSearchFollow);
    var userId = text(buttonNode.dataset.userSearchUser);
    var targetId = profileId || userId;
    if (!targetId) return;
    var following = buttonNode.dataset.following === 'true';
    var nextAction = following ? 'unfollow' : 'follow';
    var oldText = buttonNode.textContent;
    var payload = {
      action: nextAction,
      idempotency_key: 'action-center-user-search:' + nextAction + ':' + targetId + ':' + Date.now()
    };
    if (profileId) payload.profile_id = profileId;
    else payload.user_id = userId;
    buttonNode.disabled = true;
    buttonNode.textContent = following ? 'Unfollowing…' : 'Following…';
    try {
      var response = await Microgifter.post('/api/social/relationship.php', payload);
      var data = responseData(response);
      var relationship = data.relationship || {};
      var isFollowing = !!relationship.following;
      buttonNode.dataset.following = isFollowing ? 'true' : 'false';
      buttonNode.textContent = isFollowing ? 'Following' : 'Follow';
      recipients.forEach(function (profile) {
        if (safeProfileId(profile) === profileId || safeUserId(profile) === userId || safeTargetId(profile) === targetId) profile.is_following = isFollowing;
      });
      if (window.Microgifter.toast) window.Microgifter.toast(isFollowing ? 'Profile followed.' : 'Profile unfollowed.');
    } catch (error) {
      buttonNode.textContent = oldText;
      if (window.Microgifter.toast) window.Microgifter.toast(error && error.message ? error.message : 'Unable to update follow status.');
    } finally {
      buttonNode.disabled = false;
    }
  }

  function openMessage(buttonNode) {
    var profileId = text(buttonNode.dataset.userSearchProfile || buttonNode.dataset.userSearchMessage);
    var userId = text(buttonNode.dataset.userSearchUser);
    var targetId = profileId || userId;
    if (!targetId) return;
    window.location.href = '/feed.php?chat=' + encodeURIComponent(targetId);
  }

  function blockOriginalGiftSearch(event) {
    if (event.target && event.target.closest && event.target.closest('[data-user-profile-search]')) {
      event.stopImmediatePropagation();
      event.stopPropagation();
    }
  }

  app.addEventListener('input', function (event) {
    if (!event.target.closest('[data-user-profile-search]')) return;
    event.stopImmediatePropagation();
    event.stopPropagation();
    scheduleSearch();
  }, true);

  app.addEventListener('focusin', function (event) {
    if (!event.target.closest('[data-user-profile-search]')) return;
    event.stopImmediatePropagation();
    event.stopPropagation();
    if (text(searchInput.value).length >= 2) scheduleSearch();
  }, true);

  app.addEventListener('keydown', function (event) {
    if (!event.target.closest('[data-user-profile-search]')) return;
    event.stopImmediatePropagation();
    event.stopPropagation();
    if (event.key === 'Escape') closeResults();
    if (event.key === 'Enter') event.preventDefault();
  }, true);

  app.addEventListener('click', function (event) {
    var clearButton = event.target.closest('[data-gift-search-clear]');
    if (clearButton) {
      event.preventDefault();
      event.stopImmediatePropagation();
      searchInput.value = '';
      recipients = [];
      closeResults();
      setClearVisible();
      searchInput.focus();
      return;
    }

    var followButton = event.target.closest('[data-user-search-follow]');
    if (followButton) {
      event.preventDefault();
      event.stopImmediatePropagation();
      toggleFollow(followButton);
      return;
    }

    var messageButton = event.target.closest('[data-user-search-message]');
    if (messageButton) {
      event.preventDefault();
      event.stopImmediatePropagation();
      openMessage(messageButton);
      return;
    }

    var result = event.target.closest('[data-user-search-result]');
    if (result) {
      event.preventDefault();
      event.stopImmediatePropagation();
      searchInput.value = result.dataset.profileName || searchInput.value;
      closeResults();
      setClearVisible();
      return;
    }

    if (!event.target.closest('.mg-gift-search-shell')) closeResults();
  }, true);

  ['input', 'focus', 'keydown'].forEach(function (eventName) {
    searchInput.addEventListener(eventName, blockOriginalGiftSearch, true);
  });

  setClearVisible();
});
