(() => {
  'use strict';

  if (window.__mgActionCenterUserSearchV2Booted) return;
  window.__mgActionCenterUserSearchV2Booted = true;

  function boot() {
    const app = document.querySelector('[data-gift-center]');
    const input = app && app.querySelector('[data-user-profile-search]');
    const results = app && app.querySelector('[data-gift-search-results]');
    const clearButton = app && app.querySelector('[data-gift-search-clear]');
    if (!app || !input || !results || !window.Microgifter) return;

    let timer = 0;
    let requestSequence = 0;
    let recipients = [];

    const text = (value) => String(value == null ? '' : value).trim();
    const payload = (response) => response && response.data ? response.data : (response || {});
    const profileId = (profile) => text(profile.profile_id || profile.recipient_profile_id || profile.profile_slug || profile.recipient_slug);
    const userId = (profile) => text(profile.recipient_user_id || profile.user_id);
    const targetId = (profile) => profileId(profile) || userId(profile);

    function safeAvatar(profile) {
      const value = text(profile.avatar_url);
      if (!value) return '';
      if (value.startsWith('/') && !value.startsWith('//')) return value;
      return /^https:\/\//i.test(value) ? value : '';
    }

    function initials(name) {
      return text(name || 'M').split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase() || 'M';
    }

    function profileHref(profile) {
      const slug = text(profile.profile_slug || profile.recipient_slug);
      if (slug) return '/profile.php?slug=' + encodeURIComponent(slug.toLowerCase());
      const id = userId(profile);
      return id ? '/user-profile.php?user=' + encodeURIComponent(id) : '';
    }

    function setClearVisibility() {
      if (clearButton) clearButton.hidden = !text(input.value);
    }

    function closeResults() {
      results.replaceChildren();
      results.hidden = true;
      input.setAttribute('aria-expanded', 'false');
    }

    function avatarNode(profile) {
      const node = document.createElement('span');
      node.className = 'mg-user-search-avatar';
      const url = safeAvatar(profile);
      if (!url) {
        node.textContent = initials(profile.display_name);
        return node;
      }
      const image = document.createElement('img');
      image.src = url;
      image.alt = '';
      image.loading = 'lazy';
      image.addEventListener('error', () => {
        node.replaceChildren();
        node.textContent = initials(profile.display_name);
      }, { once: true });
      node.appendChild(image);
      return node;
    }

    function setFollowState(button, following) {
      button.dataset.following = following ? 'true' : 'false';
      button.setAttribute('aria-pressed', following ? 'true' : 'false');
      button.classList.toggle('is-following', following);
      button.textContent = following ? 'Following' : 'Follow';
    }

    function renderProfile(profile) {
      const row = document.createElement('div');
      const pId = profileId(profile);
      const uId = userId(profile);
      const target = pId || uId;
      const name = text(profile.display_name || 'Microgifter member');
      const slug = text(profile.profile_slug || profile.recipient_slug);
      const type = text(profile.profile_type || profile.source || 'profile');

      row.className = 'mg-user-search-result';
      row.setAttribute('role', 'option');
      row.dataset.userSearchResult = target;
      row.dataset.recipientUserId = uId;
      row.dataset.profileId = pId;
      row.dataset.profileName = name;
      row.appendChild(avatarNode(profile));

      const body = document.createElement('div');
      body.className = 'mg-user-search-body';
      const href = profileHref(profile);
      if (href) {
        const link = document.createElement('a');
        link.className = 'mg-user-search-profile-link';
        link.href = href;
        link.dataset.userSearchProfileLink = '';
        link.textContent = name;
        link.setAttribute('aria-label', 'Open ' + name + ' profile');
        body.appendChild(link);
      } else {
        const strong = document.createElement('strong');
        strong.textContent = name;
        body.appendChild(strong);
      }
      const meta = document.createElement('small');
      meta.textContent = slug ? '@' + slug + ' · ' + type : text(profile.email_hint || type || 'Microgifter profile');
      body.appendChild(meta);
      row.appendChild(body);

      const actions = document.createElement('div');
      actions.className = 'mg-user-search-actions';
      const follow = document.createElement('button');
      follow.type = 'button';
      follow.className = 'mg-user-search-action';
      follow.dataset.userSearchFollow = target;
      follow.dataset.userSearchProfile = pId;
      follow.dataset.userSearchUser = uId;
      follow.disabled = !target;
      setFollowState(follow, Boolean(profile.is_following));

      const message = document.createElement('button');
      message.type = 'button';
      message.className = 'mg-user-search-action is-primary';
      message.textContent = 'Message';
      message.dataset.userSearchMessage = target;
      message.dataset.userSearchProfile = pId;
      message.dataset.userSearchUser = uId;
      message.disabled = !target;
      actions.append(follow, message);
      row.appendChild(actions);
      return row;
    }

    function showMessage(message) {
      results.replaceChildren();
      results.hidden = false;
      input.setAttribute('aria-expanded', 'true');
      const node = document.createElement('div');
      node.className = 'mg-gift-search-empty';
      node.textContent = message;
      results.appendChild(node);
    }

    function renderResults() {
      results.replaceChildren();
      results.hidden = false;
      input.setAttribute('aria-expanded', 'true');
      if (!recipients.length) {
        showMessage('No matching users found.');
        return;
      }
      recipients.forEach((profile) => results.appendChild(renderProfile(profile)));
    }

    async function searchUsers() {
      const query = text(input.value);
      setClearVisibility();
      if (query.length < 2) {
        recipients = [];
        closeResults();
        return;
      }
      const sequence = ++requestSequence;
      showMessage('Searching users…');
      try {
        const response = await Microgifter.get('/api/account/action-center-recipient-search.php?q=' + encodeURIComponent(query));
        if (sequence !== requestSequence || query !== text(input.value)) return;
        const data = payload(response);
        recipients = Array.isArray(data.recipients) ? data.recipients : [];
        renderResults();
      } catch (error) {
        if (sequence !== requestSequence) return;
        recipients = [];
        showMessage('Unable to search users right now.');
      }
    }

    function scheduleSearch() {
      window.clearTimeout(timer);
      timer = window.setTimeout(searchUsers, 180);
    }

    function matchingFollowButtons(button) {
      const pId = text(button.dataset.userSearchProfile);
      const uId = text(button.dataset.userSearchUser);
      return Array.from(results.querySelectorAll('[data-user-search-follow]')).filter((candidate) => {
        return (pId && text(candidate.dataset.userSearchProfile) === pId)
          || (uId && text(candidate.dataset.userSearchUser) === uId);
      });
    }

    async function toggleFollow(button) {
      if (button.dataset.followRequestPending === 'true') return;
      const pId = text(button.dataset.userSearchProfile || button.dataset.userSearchFollow);
      const uId = text(button.dataset.userSearchUser);
      const target = pId || uId;
      if (!target) return;
      const wasFollowing = button.dataset.following === 'true';
      const action = wasFollowing ? 'unfollow' : 'follow';
      const buttons = matchingFollowButtons(button);
      buttons.forEach((candidate) => {
        candidate.dataset.followRequestPending = 'true';
        candidate.disabled = true;
        candidate.textContent = wasFollowing ? 'Unfollowing…' : 'Following…';
      });
      try {
        const request = {
          action,
          idempotency_key: 'action-center-user-search:' + action + ':' + target + ':' + Date.now()
        };
        if (pId) request.profile_id = pId;
        else request.user_id = uId;
        const response = await Microgifter.post('/api/social/relationship.php', request);
        const data = payload(response);
        const following = Boolean(data.relationship && data.relationship.following);
        recipients.forEach((profile) => {
          if ((pId && profileId(profile) === pId) || (uId && userId(profile) === uId)) profile.is_following = following;
        });
        buttons.forEach((candidate) => setFollowState(candidate, following));
        if (Microgifter.toast) Microgifter.toast(following ? 'Profile followed.' : 'Profile unfollowed.');
        window.setTimeout(searchUsers, 120);
      } catch (error) {
        buttons.forEach((candidate) => setFollowState(candidate, wasFollowing));
        if (Microgifter.toast) Microgifter.toast(error && error.message ? error.message : 'Unable to update follow status.');
      } finally {
        buttons.forEach((candidate) => {
          delete candidate.dataset.followRequestPending;
          candidate.disabled = false;
        });
      }
    }

    app.addEventListener('input', (event) => {
      if (!event.target.closest('[data-user-profile-search]')) return;
      scheduleSearch();
    });
    app.addEventListener('focusin', (event) => {
      if (event.target.closest('[data-user-profile-search]') && text(input.value).length >= 2) scheduleSearch();
    });
    app.addEventListener('keydown', (event) => {
      if (!event.target.closest('[data-user-profile-search]')) return;
      if (event.key === 'Escape') closeResults();
      if (event.key === 'Enter') event.preventDefault();
    });
    app.addEventListener('click', (event) => {
      const clear = event.target.closest('[data-gift-search-clear]');
      if (clear) {
        event.preventDefault();
        input.value = '';
        recipients = [];
        closeResults();
        setClearVisibility();
        input.focus();
        return;
      }
      const profileLink = event.target.closest('[data-user-search-profile-link]');
      if (profileLink && results.contains(profileLink)) return;
      const follow = event.target.closest('[data-user-search-follow]');
      if (follow) {
        event.preventDefault();
        toggleFollow(follow);
        return;
      }
      const message = event.target.closest('[data-user-search-message]');
      if (message) {
        event.preventDefault();
        const target = text(message.dataset.userSearchProfile || message.dataset.userSearchUser || message.dataset.userSearchMessage);
        if (target) window.location.href = '/feed.php?chat=' + encodeURIComponent(target);
        return;
      }
      if (!event.target.closest('.mg-gift-search-shell')) closeResults();
    });

    setClearVisibility();
    window.MicrogifterActionCenterUserSearch = Object.freeze({ version: 2, refresh: searchUsers, close: closeResults });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
  else boot();
})();