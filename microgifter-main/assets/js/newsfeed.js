window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = document.querySelector('[data-newsfeed]');
  if (!root) return;

  var cursor = null;
  var loading = false;
  var requestController = null;

  function qs(selector, scope) { return (scope || root).querySelector(selector); }
  function qsa(selector, scope) { return Array.from((scope || root).querySelectorAll(selector)); }
  function hide(node, value) { if (node) node.classList.toggle('mg-hidden', Boolean(value)); }
  function clear(node) { if (node) node.replaceChildren(); }
  function payload(response) { return response && response.data ? response.data : response; }
  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
    });
  }
  function safeUrl(value, allowRelative) {
    var raw = String(value || '').trim();
    if (!raw || /[\u0000-\u001f\u007f]/.test(raw)) return null;
    try {
      var parsed = new URL(raw, window.location.origin);
      if (!['http:', 'https:'].includes(parsed.protocol) || parsed.username || parsed.password) return null;
      if (raw.startsWith('/')) {
        if (!allowRelative || raw.startsWith('//') || parsed.origin !== window.location.origin) return null;
        return parsed.pathname + parsed.search + parsed.hash;
      }
      return parsed.href;
    } catch (error) { return null; }
  }
  function formatDate(value) {
    if (!value) return '';
    var raw = String(value);
    var parsed = new Date(raw.replace(' ', 'T') + (raw.includes('T') ? '' : 'Z'));
    if (Number.isNaN(parsed.getTime())) return raw;
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(parsed);
  }
  function label(value) {
    return String(value || '').replace(/[_-]+/g, ' ').replace(/\b\w/g, function (letter) { return letter.toUpperCase(); });
  }
  function initials(name) {
    return String(name || 'M').split(/\s+/).filter(Boolean).slice(0, 2).map(function (part) { return part[0]; }).join('').toUpperCase() || 'M';
  }
  function busy(button, value, text) {
    if (!button) return;
    if (MG.setBusy) return MG.setBusy(button, value, text);
    if (value) button.dataset.originalLabel = button.textContent;
    button.disabled = value;
    button.textContent = value ? (text || 'Working…') : (button.dataset.originalLabel || button.textContent);
  }
  function toast(message, type) {
    if (MG.toast) MG.toast(message, type || 'info');
    else qs('[data-newsfeed-status]').textContent = message || '';
  }
  function uuid(prefix) {
    var value = window.crypto && window.crypto.randomUUID ? window.crypto.randomUUID() : String(Date.now()) + '-' + Math.random().toString(16).slice(2);
    return String(prefix || 'request') + ':' + value;
  }

  function avatarMarkup(author) {
    var src = safeUrl(author && author.avatar_url, true);
    if (src) return '<span class="mg-feed-avatar"><img src="' + escapeHtml(src) + '" alt="" loading="lazy"></span>';
    return '<span class="mg-feed-avatar">' + escapeHtml(initials(author && author.display_name)) + '</span>';
  }
  function mediaMarkup(media) {
    var items = Array.isArray(media) ? media : [];
    if (!items.length) return '';
    var figures = items.map(function (item) {
      var src = safeUrl(item && item.url, true);
      if (!src) return '';
      var type = String(item.type || 'link');
      var inner = '';
      if (type === 'image') inner = '<img src="' + escapeHtml(src) + '" alt="' + escapeHtml(item.alt || item.caption || 'Post image') + '" loading="lazy">';
      else if (type === 'video') inner = '<video src="' + escapeHtml(src) + '" controls preload="metadata"></video>';
      else if (type === 'audio') inner = '<audio src="' + escapeHtml(src) + '" controls preload="metadata"></audio>';
      else inner = '<a href="' + escapeHtml(src) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(item.caption || 'Open attached link') + '</a>';
      if (item.caption && type !== 'link') inner += '<figcaption>' + escapeHtml(item.caption) + '</figcaption>';
      return '<figure>' + inner + '</figure>';
    }).join('');
    return figures ? '<div class="mg-feed-media">' + figures + '</div>' : '';
  }
  function attachmentsMarkup(item) {
    var attachments = item.attachments || {};
    var html = '';
    if (attachments.product && attachments.product.url) html += '<a href="' + escapeHtml(attachments.product.url) + '">View attached product</a>';
    if (attachments.microgift_id) html += '<span>Microgift attached</span>';
    if (attachments.subscription_plan_id) html += '<span>Member post</span>';
    return html ? '<div class="mg-feed-attachments-row">' + html + '</div>' : '';
  }
  function metric(name, value) {
    return '<span data-post-stat="' + escapeHtml(name) + '">' + Number(value || 0).toLocaleString() + ' ' + escapeHtml(name) + '</span>';
  }
  function postCard(item) {
    var author = item.author || {};
    var authorUrl = safeUrl(author.url || '#', true) || '#';
    var engagement = item.engagement || {};
    var viewerReaction = String(engagement.viewer_reaction || '');
    var saved = Boolean(engagement.saved);
    var reactions = ['like', 'love', 'celebrate', 'support'].map(function (reaction) {
      var active = reaction === viewerReaction;
      return '<button class="mg-feed-action' + (active ? ' is-active' : '') + '" type="button" data-newsfeed-action="reaction" data-reaction-type="' + escapeHtml(reaction) + '" aria-pressed="' + (active ? 'true' : 'false') + '">' + escapeHtml(label(reaction)) + '</button>';
    }).join('');

    return '<article class="mg-feed-card" data-post-id="' + escapeHtml(item.id || '') + '" data-viewer-reaction="' + escapeHtml(viewerReaction) + '" data-saved="' + (saved ? '1' : '0') + '">' +
      '<header class="mg-feed-card-header">' + avatarMarkup(author) + '<div><a href="' + escapeHtml(authorUrl) + '">' + escapeHtml(author.display_name || 'Microgifter member') + '</a><span>' + escapeHtml(label(author.profile_type || 'profile')) + ' · ' + escapeHtml(formatDate(item.published_at)) + '</span></div><span class="mg-feed-visibility">' + escapeHtml(label(item.visibility || 'public')) + '</span></header>' +
      (item.headline ? '<h3>' + escapeHtml(item.headline) + '</h3>' : '') +
      (item.body ? '<p class="mg-feed-body">' + escapeHtml(item.body) + '</p>' : '') +
      mediaMarkup(item.media) + attachmentsMarkup(item) +
      '<div class="mg-feed-stats">' + metric('comments', engagement.comments) + metric('reactions', engagement.reactions) + metric('shares', engagement.shares) + '</div>' +
      '<div class="mg-feed-actions">' + reactions + '<button class="mg-feed-action' + (saved ? ' is-active' : '') + '" type="button" data-newsfeed-action="save">' + (saved ? 'Saved' : 'Save') + '</button><button class="mg-feed-action" type="button" data-newsfeed-action="share">Share</button></div>' +
      '</article>';
  }
  function updateEngagement(card, engagement) {
    if (!card || !engagement) return;
    ['comments', 'reactions', 'shares'].forEach(function (name) {
      var node = qs('[data-post-stat="' + name + '"]', card);
      if (node) node.textContent = Number(engagement[name] || 0).toLocaleString() + ' ' + name;
    });
    card.dataset.viewerReaction = String(engagement.viewer_reaction || '');
    qsa('[data-newsfeed-action="reaction"]', card).forEach(function (button) {
      var active = button.dataset.reactionType === card.dataset.viewerReaction;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
  }

  function resetStates() {
    hide(qs('[data-newsfeed-loading]'), true);
    hide(qs('[data-newsfeed-empty]'), true);
    hide(qs('[data-newsfeed-error]'), true);
  }
  async function loadFeed(append) {
    if (loading) return;
    loading = true;
    resetStates();
    if (!append) {
      cursor = null;
      clear(qs('[data-newsfeed-list]'));
      hide(qs('[data-newsfeed-list]'), true);
      hide(qs('[data-newsfeed-loading]'), false);
    }
    qs('[data-newsfeed-status]').textContent = append ? 'Loading more posts…' : 'Loading your feed…';
    if (requestController) requestController.abort();
    requestController = new AbortController();
    var path = '/api/public/newsfeed.php?limit=18';
    if (append && cursor) path += '&cursor=' + encodeURIComponent(cursor);
    try {
      var response = await fetch(path, { credentials: 'same-origin', signal: requestController.signal });
      var json = await response.json().catch(function () { return {}; });
      if (!response.ok || json.ok === false) throw new Error(json.message || 'Unable to load your feed.');
      var data = payload(json);
      var feed = data.feed || {};
      var items = Array.isArray(feed.items) ? feed.items : [];
      var list = qs('[data-newsfeed-list]');
      list.insertAdjacentHTML('beforeend', items.map(postCard).join(''));
      cursor = feed.has_more ? String(feed.next_cursor || '') : null;
      hide(qs('[data-newsfeed-pagination]'), !cursor);
      hide(list, list.children.length === 0);
      hide(qs('[data-newsfeed-empty]'), list.children.length > 0);
      qs('[data-newsfeed-status]').textContent = list.children.length ? 'Feed loaded.' : '';
    } catch (error) {
      if (error.name === 'AbortError') return;
      hide(qs('[data-newsfeed-error]'), false);
      qs('[data-newsfeed-error-message]').textContent = error.message || 'Unable to load your feed.';
      qs('[data-newsfeed-status]').textContent = '';
    } finally {
      loading = false;
      hide(qs('[data-newsfeed-loading]'), true);
    }
  }
  async function react(card, button) {
    if (!MG.post) return;
    var type = button.dataset.reactionType;
    var action = card.dataset.viewerReaction === type ? 'unreact' : 'react';
    busy(button, true, 'Saving…');
    try {
      var data = payload(await MG.post('/api/social/engage.php', { action: action, post_id: card.datasetPostId || card.dataset.postId, reaction_type: type, idempotency_key: uuid('newsfeed-reaction') }));
      updateEngagement(card, data.engagement || {});
    } catch (error) { toast(error.message || 'Unable to update reaction.', 'error'); }
    finally { busy(button, false); }
  }
  async function save(card, button) {
    if (!MG.post) return;
    var action = card.dataset.saved === '1' ? 'unsave' : 'save';
    busy(button, true, 'Saving…');
    try {
      var data = payload(await MG.post('/api/social/engage.php', { action: action, post_id: card.dataset.postId, idempotency_key: uuid('newsfeed-save') }));
      card.dataset.saved = data.saved ? '1' : '0';
      button.textContent = data.saved ? 'Saved' : 'Save';
      button.classList.toggle('is-active', Boolean(data.saved));
    } catch (error) { toast(error.message || 'Unable to save post.', 'error'); }
    finally { busy(button, false); }
  }
  async function share(card, button) {
    busy(button, true, 'Sharing…');
    var url = window.location.origin + '/feed.php?post=' + encodeURIComponent(card.dataset.postId);
    try {
      if (navigator.clipboard) await navigator.clipboard.writeText(url);
      if (MG.post) {
        var data = payload(await MG.post('/api/social/engage.php', { action: 'share', post_id: card.dataset.postId, channel: 'copy_link', metadata: { source: 'newsfeed' }, idempotency_key: uuid('newsfeed-share') }));
        updateEngagement(card, data.engagement || {});
      }
      toast('Post link copied.', 'success');
    } catch (error) { toast(error.message || 'Unable to share post.', 'error'); }
    finally { busy(button, false); }
  }

  root.addEventListener('click', function (event) {
    if (event.target.closest('[data-newsfeed-retry]')) return void loadFeed(false);
    if (event.target.closest('[data-newsfeed-more]')) return void loadFeed(true);
    var button = event.target.closest('[data-newsfeed-action]');
    if (!button) return;
    var card = button.closest('[data-post-id]');
    if (!card) return;
    var action = button.dataset.newsfeedAction;
    if (action === 'reaction') return void react(card, button);
    if (action === 'save') return void save(card, button);
    if (action === 'share') return void share(card, button);
  });

  loadFeed(false);
})(window, document);
