window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = document.querySelector('[data-social-feed]');
  if (!root) return;

  var authenticated = document.body.dataset.authenticated === 'true';

  function openSignIn(profileId) {
    var target = '/feed.php';
    if (profileId) target += '?chat=' + encodeURIComponent(profileId);
    window.location.href = '/signin.php?return=' + encodeURIComponent(target);
  }

  function openWithDeepLink(profileId, threadId) {
    var url = new URL(window.location.href);
    url.searchParams.set('chat', profileId);
    if (threadId) url.searchParams.set('thread', threadId);
    window.location.href = url.pathname + url.search + url.hash;
  }

  function openChat(profileId, threadId) {
    profileId = String(profileId || '').trim();
    if (!profileId) return false;
    if (!authenticated) {
      openSignIn(profileId);
      return true;
    }
    if (MG.feedOnlineChat && typeof MG.feedOnlineChat.open === 'function') {
      MG.feedOnlineChat.open(profileId, { markRead: true, threadId: threadId || '' });
      return true;
    }
    openWithDeepLink(profileId, threadId || '');
    return true;
  }

  function profileIdFromUrl(href) {
    if (!href || href === '#') return '';
    try {
      var url = new URL(href, window.location.origin);
      if (url.origin !== window.location.origin) return '';
      return url.searchParams.get('slug') || url.searchParams.get('profile') || url.searchParams.get('user') || '';
    } catch (error) {
      return '';
    }
  }

  function profileIdFromTrigger(trigger) {
    if (!trigger) return '';
    var explicit = trigger.dataset.feedChatProfile || trigger.dataset.chatProfileId || trigger.dataset.profileId || '';
    if (explicit) return explicit;
    var card = trigger.closest('[data-author-profile-id]');
    if (card && card.dataset.authorProfileId) return card.dataset.authorProfileId;
    var profileLink = trigger.closest('a[href]');
    return profileLink ? profileIdFromUrl(profileLink.getAttribute('href')) : '';
  }

  function isModifiedClick(event) {
    return event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey;
  }

  function shouldIgnore(target) {
    return Boolean(target.closest('[data-post-action], [data-story-create], [data-story-close], [data-story-next], [data-story-prev], [data-story-analytics], [data-story-highlight], [data-story-promote], [data-story-delete], form, input, textarea, select, button:not(.mg-story-viewer-profile):not(.mg-feed-avatar)'));
  }

  root.addEventListener('click', function (event) {
    if (isModifiedClick(event)) return;
    var target = event.target;
    var trigger = target.closest('[data-feed-chat-profile], [data-chat-profile-id], .mg-feed-avatar, .mg-feed-card-header a, .mg-story-viewer-profile');
    if (!trigger || !root.contains(trigger) || shouldIgnore(target)) return;

    var profileId = profileIdFromTrigger(trigger);
    if (!profileId) return;
    event.preventDefault();
    event.stopPropagation();
    openChat(profileId, '');
  });

  document.addEventListener('mg:feed-open-chat', function (event) {
    var detail = event.detail || {};
    openChat(detail.profileId || detail.profile_id || '', detail.threadId || detail.thread_id || '');
  });
})(window, document);
