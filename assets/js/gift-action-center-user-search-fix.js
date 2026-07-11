document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var app = document.querySelector('[data-gift-center]');
  var results = app && app.querySelector('[data-gift-search-results]');
  var searchInput = app && app.querySelector('[data-user-profile-search]');
  if (!app || !results || !searchInput || !window.Microgifter) return;

  function text(value) {
    return String(value == null ? '' : value).trim();
  }

  function responsePayload(response) {
    var payload = response && response.data ? response.data : (response || {});
    return payload && payload.data && typeof payload.data === 'object' ? payload.data : payload;
  }

  function booleanValue(value, fallback) {
    if (value === true || value === false) return value;
    if (value === 1 || value === '1' || value === 'true') return true;
    if (value === 0 || value === '0' || value === 'false') return false;
    return fallback;
  }

  function followingFromResponse(response, fallback) {
    var payload = responsePayload(response);
    var relationship = payload && payload.relationship && typeof payload.relationship === 'object'
      ? payload.relationship
      : {};
    if (Object.prototype.hasOwnProperty.call(relationship, 'following')) {
      return booleanValue(relationship.following, fallback);
    }
    if (payload && Object.prototype.hasOwnProperty.call(payload, 'following')) {
      return booleanValue(payload.following, fallback);
    }
    return fallback;
  }

  function rowProfileUrl(row) {
    if (!row) return '';
    var meta = row.querySelector('.mg-user-search-body small');
    var match = text(meta && meta.textContent).match(/^@([a-z0-9](?:[a-z0-9-]{0,118}[a-z0-9])?)(?:\s|·|$)/i);
    if (match) return '/profile.php?slug=' + encodeURIComponent(match[1].toLowerCase());
    var userId = text(row.dataset.recipientUserId);
    return userId ? '/user-profile.php?user=' + encodeURIComponent(userId) : '';
  }

  function setButtonState(button, following) {
    if (!button) return;
    button.dataset.following = following ? 'true' : 'false';
    button.textContent = following ? 'Following' : 'Follow';
    button.setAttribute('aria-pressed', following ? 'true' : 'false');
    button.classList.toggle('is-following', following);
  }

  function enhanceRow(row) {
    if (!row || !row.matches('[data-user-search-result]')) return;

    var follow = row.querySelector('[data-user-search-follow]');
    if (follow) setButtonState(follow, follow.dataset.following === 'true');

    var body = row.querySelector('.mg-user-search-body');
    var currentLink = body && body.querySelector('[data-user-search-profile-link]');
    var name = body && body.querySelector(':scope > strong');
    var href = rowProfileUrl(row);
    if (!body || currentLink || !name || !href) return;

    var link = document.createElement('a');
    link.className = 'mg-user-search-profile-link';
    link.href = href;
    link.dataset.userSearchProfileLink = '';
    link.textContent = name.textContent || 'Microgifter member';
    link.setAttribute('aria-label', 'Open ' + link.textContent + ' profile');
    name.replaceWith(link);
  }

  function enhanceRows() {
    results.querySelectorAll('[data-user-search-result]').forEach(enhanceRow);
  }

  function matchingButtons(button) {
    var profileId = text(button.dataset.userSearchProfile || button.dataset.userSearchFollow);
    var userId = text(button.dataset.userSearchUser);
    return Array.prototype.filter.call(results.querySelectorAll('[data-user-search-follow]'), function (candidate) {
      var candidateProfile = text(candidate.dataset.userSearchProfile || candidate.dataset.userSearchFollow);
      var candidateUser = text(candidate.dataset.userSearchUser);
      return (profileId && candidateProfile === profileId) || (userId && candidateUser === userId);
    });
  }

  async function toggleFollow(button) {
    var profileId = text(button.dataset.userSearchProfile || button.dataset.userSearchFollow);
    var userId = text(button.dataset.userSearchUser);
    var targetId = profileId || userId;
    if (!targetId || button.dataset.followRequestPending === 'true') return;

    var wasFollowing = button.dataset.following === 'true';
    var requestedFollowing = !wasFollowing;
    var action = requestedFollowing ? 'follow' : 'unfollow';
    var payload = {
      action: action,
      idempotency_key: 'inbox-user-search:' + action + ':' + targetId + ':' + Date.now()
    };
    if (profileId) payload.profile_id = profileId;
    else payload.user_id = userId;

    var buttons = matchingButtons(button);
    buttons.forEach(function (candidate) {
      candidate.dataset.followRequestPending = 'true';
      candidate.disabled = true;
      candidate.textContent = requestedFollowing ? 'Following…' : 'Unfollowing…';
    });

    try {
      var response = await Microgifter.post('/api/social/relationship.php', payload);
      var following = followingFromResponse(response, requestedFollowing);
      buttons.forEach(function (candidate) { setButtonState(candidate, following); });
      if (Microgifter.toast) Microgifter.toast(following ? 'Profile followed.' : 'Profile unfollowed.');

      window.setTimeout(function () {
        searchInput.dispatchEvent(new Event('input', { bubbles: true }));
      }, 120);
    } catch (error) {
      buttons.forEach(function (candidate) { setButtonState(candidate, wasFollowing); });
      if (Microgifter.toast) Microgifter.toast(error && error.message ? error.message : 'Unable to update follow status.');
    } finally {
      buttons.forEach(function (candidate) {
        delete candidate.dataset.followRequestPending;
        candidate.disabled = false;
      });
    }
  }

  var observer = new MutationObserver(enhanceRows);
  observer.observe(results, { childList: true, subtree: true });
  enhanceRows();

  document.addEventListener('click', function (event) {
    var profileLink = event.target.closest && event.target.closest('[data-user-search-profile-link]');
    if (profileLink && results.contains(profileLink)) {
      event.preventDefault();
      event.stopImmediatePropagation();
      window.location.href = profileLink.href;
      return;
    }

    var follow = event.target.closest && event.target.closest('[data-user-search-follow]');
    if (follow && results.contains(follow)) {
      event.preventDefault();
      event.stopImmediatePropagation();
      toggleFollow(follow);
    }
  }, true);
});
