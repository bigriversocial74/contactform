window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = document.querySelector('[data-social-feed]');
  if (!root || !MG.get || !MG.post) return;

  var authenticated = document.body.dataset.authenticated === 'true';
  var view = 'discover';
  var cursor = null;
  var loading = false;
  var ownerFilter = '';
  var ownerItems = new Map();
  var editingId = null;
  var requestController = null;

  function qs(selector, scope) { return (scope || root).querySelector(selector); }
  function qsa(selector, scope) { return Array.from((scope || root).querySelectorAll(selector)); }
  function hide(node, value) { if (node) node.classList.toggle('mg-hidden', Boolean(value)); }
  function clear(node) { if (node) node.replaceChildren(); }
  function payload(response) { return response && response.data ? response.data : response; }
  function hasOwn(object, key) { return Object.prototype.hasOwnProperty.call(object || {}, key); }
  function uuid(prefix) {
    var value = window.crypto && window.crypto.randomUUID ? window.crypto.randomUUID() : String(Date.now()) + '-' + Math.random().toString(16).slice(2);
    return String(prefix || 'request') + ':' + value;
  }
  function busy(button, value, label) {
    if (!button) return;
    if (MG.setBusy) return MG.setBusy(button, value, label);
    if (value) button.dataset.originalLabel = button.textContent;
    button.disabled = value;
    button.textContent = value ? (label || 'Working…') : (button.dataset.originalLabel || button.textContent);
    if (!value) delete button.dataset.originalLabel;
  }
  function toast(message, type) {
    if (MG.toast) MG.toast(message, type || 'info');
    else qs('[data-feed-status]').textContent = message || '';
  }
  function signIn() { window.location.href = '/signin.php?return=' + encodeURIComponent('/feed.php'); }

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

  function initials(name) {
    return String(name || 'M').split(/\s+/).filter(Boolean).slice(0, 2).map(function (part) { return part[0]; }).join('').toUpperCase() || 'M';
  }
  function formatDate(value) {
    if (!value) return '';
    var raw = String(value);
    var parsed = new Date(raw.replace(' ', 'T') + (raw.includes('T') ? '' : 'Z'));
    if (Number.isNaN(parsed.getTime())) return raw;
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(parsed);
  }
  function label(value) { return String(value || '').replace(/[_-]+/g, ' ').replace(/\b\w/g, function (letter) { return letter.toUpperCase(); }); }
  function setStatus(node, message, type) {
    if (!node) return;
    node.textContent = message || '';
    node.className = 'mg-feed-action-status' + (message ? ' is-visible' : '') + (type ? ' is-' + type : '');
  }

  function avatar(author) {
    var wrap = document.createElement('span');
    wrap.className = 'mg-feed-avatar';
    var src = safeUrl(author && author.avatar_url, true);
    if (src) {
      var image = document.createElement('img');
      image.src = src;
      image.alt = '';
      image.loading = 'lazy';
      image.addEventListener('error', function () { image.remove(); wrap.textContent = initials(author.display_name); }, { once: true });
      wrap.appendChild(image);
    } else wrap.textContent = initials(author && author.display_name);
    return wrap;
  }

  function metric(name, value) {
    var item = document.createElement('span');
    item.dataset.postStat = name;
    item.textContent = Number(value || 0).toLocaleString() + ' ' + name;
    return item;
  }

  function actionButton(text, action, className) {
    var button = document.createElement('button');
    button.type = 'button';
    button.className = className || 'mg-feed-action';
    button.dataset.postAction = action;
    button.textContent = text;
    return button;
  }

  function mediaNode(media) {
    var container = document.createElement('div');
    container.className = 'mg-feed-media';
    (Array.isArray(media) ? media : []).forEach(function (item) {
      var src = safeUrl(item && item.url, true);
      if (!src) return;
      var type = String(item.type || 'link');
      var figure = document.createElement('figure');
      if (type === 'image') {
        var image = document.createElement('img');
        image.src = src;
        image.alt = String(item.alt || item.caption || 'Post image');
        image.loading = 'lazy';
        figure.appendChild(image);
      } else if (type === 'audio') {
        var audio = document.createElement('audio');
        audio.src = src;
        audio.controls = true;
        audio.preload = 'metadata';
        figure.appendChild(audio);
      } else if (type === 'video') {
        var video = document.createElement('video');
        video.src = src;
        video.controls = true;
        video.preload = 'metadata';
        figure.appendChild(video);
      } else {
        var link = document.createElement('a');
        link.href = src;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.textContent = item.caption || 'Open attached link';
        figure.appendChild(link);
      }
      if (item.caption && type !== 'link') {
        var caption = document.createElement('figcaption');
        caption.textContent = String(item.caption);
        figure.appendChild(caption);
      }
      container.appendChild(figure);
    });
    return container;
  }

  function commentsPanel(item) {
    var panel = document.createElement('section');
    panel.className = 'mg-feed-comments mg-hidden';
    panel.dataset.commentsPanel = '1';
    panel.id = 'feed-comments-' + String(item.id || '').replace(/[^a-z0-9_-]/gi, '');
    var status = document.createElement('div');
    status.className = 'mg-feed-comments-status';
    status.dataset.commentsStatus = '1';
    status.setAttribute('role', 'status');
    var list = document.createElement('div');
    list.className = 'mg-feed-comment-list';
    list.dataset.commentList = '1';
    var more = actionButton('Load more comments', 'comments_more', 'mg-btn mg-btn-soft mg-hidden');
    more.dataset.commentsMore = '1';
    var form = document.createElement('form');
    form.className = 'mg-feed-comment-form';
    form.dataset.commentForm = '1';
    var textarea = document.createElement('textarea');
    textarea.name = 'comment_body';
    textarea.rows = 3;
    textarea.maxLength = 2000;
    textarea.required = true;
    textarea.placeholder = authenticated ? 'Write a comment…' : 'Sign in to comment';
    textarea.disabled = !authenticated;
    var submit = document.createElement('button');
    submit.type = 'submit';
    submit.className = 'mg-btn mg-btn-primary';
    submit.textContent = authenticated ? 'Post comment' : 'Sign in to comment';
    form.append(textarea, submit);
    panel.append(status, list, more, form);
    return panel;
  }

  function attachmentNode(item) {
    var attachments = item.attachments || {};
    var wrap = document.createElement('div');
    wrap.className = 'mg-feed-attachments-row';
    if (attachments.product && attachments.product.url) {
      var product = document.createElement('a');
      product.href = attachments.product.url;
      product.textContent = 'View attached product';
      wrap.appendChild(product);
    }
    if (attachments.microgift_id) {
      var gift = document.createElement('span');
      gift.textContent = 'Microgift attached';
      wrap.appendChild(gift);
    }
    if (attachments.subscription_plan_id) {
      var plan = document.createElement('span');
      plan.textContent = 'Member post';
      wrap.appendChild(plan);
    }
    return wrap;
  }

  function feedCard(item) {
    var card = document.createElement('article');
    card.className = 'mg-feed-card';
    card.dataset.postId = String(item.id || '');
    card.dataset.authorProfileId = String(item.author && item.author.id || '');
    card.dataset.viewerReaction = String(item.engagement && item.engagement.viewer_reaction || '');
    card.dataset.saved = item.engagement && item.engagement.saved ? '1' : '0';

    var header = document.createElement('header');
    header.className = 'mg-feed-card-header';
    header.appendChild(avatar(item.author || {}));
    var identity = document.createElement('div');
    var author = document.createElement('a');
    author.href = item.author && item.author.url || '#';
    author.textContent = String(item.author && item.author.display_name || 'Microgifter member');
    var meta = document.createElement('span');
    meta.textContent = label(item.author && item.author.profile_type || 'profile') + ' · ' + formatDate(item.published_at);
    identity.append(author, meta);
    var visibility = document.createElement('span');
    visibility.className = 'mg-feed-visibility';
    visibility.textContent = label(item.visibility || 'public');
    header.append(identity, visibility);
    card.appendChild(header);

    if (item.headline) {
      var heading = document.createElement('h3');
      heading.textContent = String(item.headline);
      card.appendChild(heading);
    }
    if (item.body) {
      var body = document.createElement('p');
      body.className = 'mg-feed-body';
      body.textContent = String(item.body);
      card.appendChild(body);
    }
    var media = mediaNode(item.media);
    if (media.children.length) card.appendChild(media);
    var attachments = attachmentNode(item);
    if (attachments.children.length) card.appendChild(attachments);

    var stats = document.createElement('div');
    stats.className = 'mg-feed-stats';
    stats.append(metric('comments', item.engagement && item.engagement.comments), metric('reactions', item.engagement && item.engagement.reactions), metric('shares', item.engagement && item.engagement.shares));
    if (item.engagement && Number(item.engagement.saves || 0) > 0) stats.append(metric('saves', item.engagement.saves));
    card.appendChild(stats);

    var actions = document.createElement('div');
    actions.className = 'mg-feed-actions';
    ['like','love','celebrate','support'].forEach(function (reaction) {
      var button = actionButton(label(reaction), 'reaction');
      button.dataset.reactionType = reaction;
      var active = reaction === card.dataset.viewerReaction;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
      actions.appendChild(button);
    });
    var comments = actionButton('Comments', 'comments');
    comments.setAttribute('aria-expanded', 'false');
    comments.setAttribute('aria-controls', 'feed-comments-' + String(item.id || '').replace(/[^a-z0-9_-]/gi, ''));
    actions.appendChild(comments);
    var save = actionButton(card.dataset.saved === '1' ? 'Saved' : 'Save', 'save');
    save.classList.toggle('is-active', card.dataset.saved === '1');
    save.setAttribute('aria-pressed', card.dataset.saved === '1' ? 'true' : 'false');
    actions.appendChild(save);
    actions.appendChild(actionButton('Share', 'share'));
    if (item.permissions && item.permissions.can_report) actions.appendChild(actionButton('Report', 'report'));
    if (authenticated && item.permissions && !item.permissions.is_owner) {
      actions.appendChild(actionButton('Mute', 'mute'));
      actions.appendChild(actionButton('Block', 'block'));
    }
    card.append(actions, commentsPanel(item));
    return card;
  }

  function ownerCard(item) {
    ownerItems.set(String(item.id), item);
    var card = document.createElement('article');
    card.className = 'mg-feed-card mg-owner-post-card';
    card.dataset.postId = String(item.id || '');
    var header = document.createElement('header');
    header.className = 'mg-feed-card-header';
    var identity = document.createElement('div');
    var title = document.createElement('strong');
    title.textContent = item.headline || 'Untitled post';
    var meta = document.createElement('span');
    meta.textContent = label(item.status) + ' · ' + label(item.visibility) + ' · Updated ' + formatDate(item.updated_at);
    identity.append(title, meta);
    var moderation = document.createElement('span');
    moderation.className = 'mg-feed-visibility' + (item.moderation_status !== 'clear' ? ' is-warning' : '');
    moderation.textContent = label(item.moderation_status);
    header.append(identity, moderation);
    card.appendChild(header);
    if (item.body) { var body = document.createElement('p'); body.className = 'mg-feed-body'; body.textContent = item.body; card.appendChild(body); }
    var media = mediaNode(item.media); if (media.children.length) card.appendChild(media);
    var stats = document.createElement('div'); stats.className = 'mg-feed-stats';
    stats.append(metric('comments', item.engagement.comments), metric('reactions', item.engagement.reactions), metric('shares', item.engagement.shares), metric('saves', item.engagement.saves));
    var actions = document.createElement('div'); actions.className = 'mg-feed-owner-actions';
    if (item.permissions.can_edit) actions.appendChild(actionButton('Edit', 'owner_edit', 'mg-btn mg-btn-soft'));
    if (item.permissions.can_publish && item.status !== 'published') actions.appendChild(actionButton('Publish', 'owner_publish', 'mg-btn mg-btn-primary'));
    if (item.permissions.can_archive) actions.appendChild(actionButton('Archive', 'owner_archive', 'mg-btn mg-btn-ghost'));
    if (item.permissions.can_delete) actions.appendChild(actionButton('Delete', 'owner_delete', 'mg-btn mg-btn-ghost'));
    card.append(stats, actions);
    return card;
  }

  function commentNode(comment) {
    var article = document.createElement('article');
    article.className = 'mg-feed-comment';
    article.dataset.commentId = String(comment.id || '');
    var header = document.createElement('header');
    var author = comment.author && comment.author.profile_slug ? document.createElement('a') : document.createElement('strong');
    author.textContent = String(comment.author && comment.author.display_name || 'Microgifter member');
    if (comment.author && comment.author.profile_slug) author.href = '/profile.php?slug=' + encodeURIComponent(comment.author.profile_slug);
    var time = document.createElement('time'); time.textContent = formatDate(comment.created_at);
    header.append(author, time);
    var body = document.createElement('p'); body.textContent = String(comment.body || '');
    article.append(header, body);
    var permissions = comment.permissions || {};
    if (permissions.can_delete || permissions.can_hide) {
      var actions = document.createElement('div'); actions.className = 'mg-feed-comment-actions';
      if (permissions.can_hide) actions.appendChild(actionButton('Hide', 'comment_hide'));
      if (permissions.can_delete) actions.appendChild(actionButton('Delete', 'comment_delete'));
      article.appendChild(actions);
    }
    return article;
  }

  function syncSaveButton(card, button, saved) {
    if (!card || !button) return;
    card.dataset.saved = saved ? '1' : '0';
    button.textContent = saved ? 'Saved' : 'Save';
    button.classList.toggle('is-active', Boolean(saved));
    button.setAttribute('aria-pressed', saved ? 'true' : 'false');
  }

  function syncCommentsButton(card, button, open) {
    if (!button) button = qs('[data-post-action="comments"]', card);
    if (!button) return;
    button.classList.toggle('is-active', Boolean(open));
    button.setAttribute('aria-expanded', open ? 'true' : 'false');
    button.textContent = open ? 'Hide comments' : 'Comments';
  }

  function updateEngagement(card, engagement) {
    if (!card || !engagement) return;
    ['comments','reactions','shares','saves'].forEach(function (name) {
      if (!hasOwn(engagement, name)) return;
      var node = qs('[data-post-stat="' + name + '"]', card);
      if (node) node.textContent = Number(engagement[name] || 0).toLocaleString() + ' ' + name;
    });
    if (hasOwn(engagement, 'viewer_reaction')) {
      card.dataset.viewerReaction = String(engagement.viewer_reaction || '');
      qsa('[data-post-action="reaction"]', card).forEach(function (button) {
        var active = button.dataset.reactionType === card.dataset.viewerReaction;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
      });
    }
    if (hasOwn(engagement, 'saved')) {
      syncSaveButton(card, qs('[data-post-action="save"]', card), Boolean(engagement.saved));
    }
  }

  function resetStates() {
    hide(qs('[data-feed-loading]'), true);
    hide(qs('[data-feed-empty]'), true);
    hide(qs('[data-feed-error]'), true);
    hide(qs('[data-feed-signin]'), true);
  }

  function configureView() {
    qsa('[data-feed-tab]').forEach(function (button) { button.classList.toggle('is-active', button.dataset.feedTab === view); });
    var copy = {
      discover: ['Public discovery','Discover posts','Public and unlisted posts from active profiles.'],
      following: ['Your network','Following feed','Posts from profiles you follow, filtered by your access.'],
      mine: ['Publishing workspace','My posts','Create, edit, publish, archive, and review your posts.'],
    }[view];
    qs('[data-feed-kicker]').textContent = copy[0];
    qs('[data-feed-title]').textContent = copy[1];
    qs('[data-feed-description]').textContent = copy[2];
    hide(qs('[data-owner-filter-wrap]'), view !== 'mine');
  }

  async function loadFeed(append) {
    if (loading) return;
    if ((view === 'following' || view === 'mine') && !authenticated) {
      resetStates(); clear(qs('[data-feed-list]')); hide(qs('[data-feed-list]'), true); hide(qs('[data-feed-pagination]'), true); hide(qs('[data-feed-signin]'), false); return;
    }
    loading = true;
    resetStates();
    if (!append) { cursor = null; clear(qs('[data-feed-list]')); ownerItems = new Map(); hide(qs('[data-feed-list]'), true); hide(qs('[data-feed-loading]'), false); }
    qs('[data-feed-status]').textContent = append ? 'Loading more posts…' : 'Loading posts…';
    requestController && requestController.abort();
    requestController = new AbortController();
    var path = view === 'mine'
      ? '/api/social/posts.php?scope=mine&limit=20&status=' + encodeURIComponent(ownerFilter)
      : '/api/public/feed.php?mode=' + encodeURIComponent(view) + '&limit=18';
    if (append && cursor) path += '&cursor=' + encodeURIComponent(cursor);
    try {
      var data = payload(await MG.get(path, { signal: requestController.signal }));
      var collection = view === 'mine' ? data.posts : data.feed;
      var items = collection && Array.isArray(collection.items) ? collection.items : [];
      var list = qs('[data-feed-list]');
      items.forEach(function (item) { list.appendChild(view === 'mine' ? ownerCard(item) : feedCard(item)); });
      cursor = collection && collection.has_more ? String(collection.next_cursor || '') : null;
      hide(qs('[data-feed-pagination]'), !cursor);
      hide(list, list.children.length === 0);
      hide(qs('[data-feed-empty]'), list.children.length > 0);
      qs('[data-feed-empty-message]').textContent = view === 'mine' ? 'Create a post or change the status filter.' : (view === 'following' ? 'Follow profiles to build your feed.' : 'Published posts will appear here.');
      qs('[data-feed-status]').textContent = list.children.length ? 'Posts loaded.' : '';
    } catch (error) {
      if (error.name === 'AbortError') return;
      hide(qs('[data-feed-error]'), false);
      qs('[data-feed-error-message]').textContent = error.message || 'Unable to load the feed.';
      qs('[data-feed-status]').textContent = '';
    } finally { loading = false; hide(qs('[data-feed-loading]'), true); }
  }

  function openComposer(item) {
    if (!authenticated) return signIn();
    var panel = qs('[data-post-composer]');
    var form = qs('[data-post-form]');
    form.reset();
    editingId = item ? String(item.id) : null;
    qs('[data-composer-title]').textContent = editingId ? 'Edit post' : 'Create a post';
    hide(qs('[data-post-cancel-edit]'), !editingId);
    if (item) {
      form.elements.post_id.value = item.id || '';
      form.elements.headline.value = item.headline || '';
      form.elements.body.value = item.body || '';
      form.elements.visibility.value = item.visibility || 'public';
      form.elements.post_type.value = item.type || 'simple';
      form.elements.product_id.value = item.attachments && item.attachments.product ? item.attachments.product.id : '';
      form.elements.microgift_id.value = item.attachments && item.attachments.microgift_id || '';
      form.elements.subscription_plan_id.value = item.attachments && item.attachments.subscription_plan_id || '';
      form.elements.media_urls.value = (item.media || []).map(function (media) { return media.url; }).join('\n');
      var link = (item.media || []).find(function (media) { return media.type === 'link'; });
      form.elements.link_url.value = link ? link.url : '';
    }
    setStatus(qs('[data-composer-status]'), '', '');
    hide(panel, false);
    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function closeComposer() {
    editingId = null;
    qs('[data-post-form]').reset();
    hide(qs('[data-post-composer]'), true);
    hide(qs('[data-post-cancel-edit]'), true);
  }

  function composerPayload(publish) {
    var form = qs('[data-post-form]');
    var media = String(form.elements.media_urls.value || '').split(/\r?\n/).map(function (value) { return value.trim(); }).filter(Boolean).slice(0, 8).map(function (url) {
      var lower = url.toLowerCase().split('?')[0];
      var type = /\.(png|jpe?g|gif|webp|avif)$/.test(lower) ? 'image' : (/\.(mp3|wav|ogg|m4a)$/.test(lower) ? 'audio' : (/\.(mp4|webm|mov)$/.test(lower) ? 'video' : 'link'));
      return { url: url, type: type };
    });
    return {
      action: editingId ? (publish ? 'publish' : 'update') : 'create',
      post_id: editingId || '',
      headline: form.elements.headline.value,
      body: form.elements.body.value,
      visibility: form.elements.visibility.value,
      post_type: form.elements.post_type.value,
      product_id: form.elements.product_id.value,
      microgift_id: form.elements.microgift_id.value,
      subscription_plan_id: form.elements.subscription_plan_id.value,
      link_url: form.elements.link_url.value,
      media: media,
      publish: Boolean(publish),
      idempotency_key: uuid('post-' + (editingId ? 'update' : 'create')),
    };
  }

  async function savePost(publish, button) {
    if (!authenticated) return signIn();
    busy(button, true, publish ? 'Publishing…' : 'Saving…');
    setStatus(qs('[data-composer-status]'), '', '');
    try {
      await MG.post('/api/social/posts.php', composerPayload(publish));
      setStatus(qs('[data-composer-status]'), publish ? 'Post published.' : 'Post saved.', 'success');
      closeComposer();
      view = 'mine'; configureView(); await loadFeed(false);
    } catch (error) { setStatus(qs('[data-composer-status]'), error.message || 'Unable to save post.', 'error'); }
    finally { busy(button, false); }
  }

  async function mutateOwner(card, action, button) {
    var item = ownerItems.get(card.dataset.postId);
    if (!item) return;
    if (action === 'owner_edit') return openComposer(item);
    if (action === 'owner_delete' && !window.confirm('Delete this post? The record will be retired and retained for audit history.')) return;
    busy(button, true, action === 'owner_publish' ? 'Publishing…' : 'Updating…');
    var body = {
      action: action.replace('owner_', ''), post_id: item.id,
      headline: item.headline || '', body: item.body || '', visibility: item.visibility,
      post_type: item.type, media: item.media || [],
      product_id: item.attachments && item.attachments.product ? item.attachments.product.id : '',
      microgift_id: item.attachments && item.attachments.microgift_id || '',
      subscription_plan_id: item.attachments && item.attachments.subscription_plan_id || '',
      idempotency_key: uuid('owner-' + action),
    };
    try { await MG.post('/api/social/posts.php', body); await loadFeed(false); }
    catch (error) { toast(error.message || 'Unable to update post.', 'error'); }
    finally { busy(button, false); }
  }

  async function react(card, button) {
    if (!authenticated) return signIn();
    var type = button.dataset.reactionType;
    var action = card.dataset.viewerReaction === type ? 'unreact' : 'react';
    busy(button, true, 'Saving…');
    try {
      var data = payload(await MG.post('/api/social/engage.php', { action: action, post_id: card.dataset.postId, reaction_type: type, idempotency_key: uuid('feed-reaction') }));
      updateEngagement(card, data.engagement || {});
    } catch (error) { toast(error.message || 'Unable to update reaction.', 'error'); }
    finally { busy(button, false); }
  }

  async function save(card, button) {
    if (!authenticated) return signIn();
    var action = card.dataset.saved === '1' ? 'unsave' : 'save';
    busy(button, true, 'Saving…');
    try {
      var data = payload(await MG.post('/api/social/engage.php', { action: action, post_id: card.dataset.postId, idempotency_key: uuid('feed-save') }));
      if (data.engagement) updateEngagement(card, data.engagement);
      else syncSaveButton(card, button, Boolean(data.saved));
    } catch (error) { toast(error.message || 'Unable to save post.', 'error'); }
    finally { busy(button, false); syncSaveButton(card, button, card.dataset.saved === '1'); }
  }

  async function share(card, button) {
    if (!authenticated) return signIn();
    busy(button, true, 'Sharing…');
    var url = window.location.origin + '/feed.php?post=' + encodeURIComponent(card.dataset.postId);
    try {
      if (navigator.clipboard) await navigator.clipboard.writeText(url);
      var data = payload(await MG.post('/api/social/engage.php', { action: 'share', post_id: card.dataset.postId, channel: 'copy_link', metadata: { source: 'feed' }, idempotency_key: uuid('feed-share') }));
      updateEngagement(card, data.engagement || {});
      toast('Post link copied.', 'success');
    } catch (error) { toast(error.message || 'Unable to share post.', 'error'); }
    finally { busy(button, false); }
  }

  async function report(card, button) {
    if (!authenticated) return signIn();
    var details = window.prompt('Briefly describe the issue with this post.');
    if (details === null) return;
    busy(button, true, 'Reporting…');
    try {
      await MG.post('/api/social/report.php', { subject_type: 'post', subject_reference: card.dataset.postId, reason_code: 'other', details: details });
      button.textContent = 'Reported'; button.disabled = true; toast('Report submitted.', 'success');
    } catch (error) { toast(error.message || 'Unable to report post.', 'error'); busy(button, false); }
  }

  async function relationship(card, action, button) {
    if (!authenticated) return signIn();
    if (action === 'block' && !window.confirm('Block this profile? Their posts will no longer appear.')) return;
    busy(button, true, action === 'block' ? 'Blocking…' : 'Muting…');
    try {
      await MG.post('/api/social/relationship.php', { action: action, profile_id: card.dataset.authorProfileId, idempotency_key: uuid('feed-' + action) });
      qsa('[data-author-profile-id="' + CSS.escape(card.dataset.authorProfileId) + '"]', qs('[data-feed-list]')).forEach(function (node) { node.remove(); });
      toast(action === 'block' ? 'Profile blocked.' : 'Profile muted.', 'success');
    } catch (error) { toast(error.message || 'Unable to update relationship.', 'error'); busy(button, false); }
  }

  function renderComments(card, collection, append) {
    var list = qs('[data-comment-list]', card);
    if (!append) clear(list);
    (collection && Array.isArray(collection.items) ? collection.items : []).forEach(function (comment) { list.appendChild(commentNode(comment)); });
    card.dataset.commentCursor = collection && collection.has_more ? String(collection.next_cursor || '') : '';
    card.dataset.commentsLoaded = '1';
    hide(qs('[data-comments-more]', card), !card.dataset.commentCursor);
  }

  async function loadComments(card, append) {
    if (card.dataset.commentsLoading === '1') return;
    card.dataset.commentsLoading = '1';
    var status = qs('[data-comments-status]', card);
    status.textContent = append ? 'Loading more comments…' : 'Loading comments…';
    var path = '/api/public/post-engagement.php?post_id=' + encodeURIComponent(card.dataset.postId) + '&limit=20';
    if (append && card.dataset.commentCursor) path += '&cursor=' + encodeURIComponent(card.dataset.commentCursor);
    try {
      var data = payload(await MG.get(path));
      updateEngagement(card, data.engagement || {});
      renderComments(card, data.comments || {}, append);
      status.textContent = '';
    } catch (error) { status.textContent = error.message || 'Unable to load comments.'; }
    finally { card.dataset.commentsLoading = '0'; }
  }

  async function submitComment(card, form) {
    if (!authenticated) return signIn();
    var input = form.elements.comment_body;
    var body = String(input.value || '').trim();
    if (!body) return;
    var button = qs('[type="submit"]', form); busy(button, true, 'Posting…');
    try {
      var data = payload(await MG.post('/api/social/engage.php', { action: 'comment', post_id: card.dataset.postId, body: body, idempotency_key: uuid('feed-comment') }));
      if (data.comment) qs('[data-comment-list]', card).appendChild(commentNode(data.comment));
      updateEngagement(card, data.engagement || {}); input.value = ''; card.dataset.commentsLoaded = '1';
    } catch (error) { toast(error.message || 'Unable to post comment.', 'error'); }
    finally { busy(button, false); }
  }

  async function moderateComment(card, comment, action, button) {
    if (!authenticated) return signIn();
    busy(button, true, 'Saving…');
    try {
      var data = payload(await MG.post('/api/social/engage.php', { action: action, comment_id: comment.dataset.commentId, idempotency_key: uuid('feed-comment-action') }));
      comment.remove(); updateEngagement(card, data.engagement || {});
    } catch (error) { toast(error.message || 'Unable to update comment.', 'error'); busy(button, false); }
  }

  root.addEventListener('click', function (event) {
    var tab = event.target.closest('[data-feed-tab]');
    if (tab) { view = tab.dataset.feedTab; cursor = null; configureView(); loadFeed(false); return; }
    if (event.target.closest('[data-composer-toggle]')) return openComposer(null);
    if (event.target.closest('[data-composer-close]')) return closeComposer();
    if (event.target.closest('[data-post-cancel-edit]')) return closeComposer();
    var draft = event.target.closest('[data-post-save-draft]');
    if (draft) return void savePost(false, draft);
    if (event.target.closest('[data-feed-retry]')) return void loadFeed(false);
    var more = event.target.closest('[data-feed-more]');
    if (more) return void loadFeed(true);

    var button = event.target.closest('[data-post-action]');
    if (!button) return;
    var card = button.closest('[data-post-id]');
    var action = button.dataset.postAction;
    if (action.indexOf('owner_') === 0) return void mutateOwner(card, action, button);
    if (action === 'reaction') return void react(card, button);
    if (action === 'save') return void save(card, button);
    if (action === 'share') return void share(card, button);
    if (action === 'report') return void report(card, button);
    if (action === 'mute' || action === 'block') return void relationship(card, action, button);
    if (action === 'comments') {
      var panel = qs('[data-comments-panel]', card);
      var opening = panel.classList.contains('mg-hidden');
      hide(panel, !opening);
      syncCommentsButton(card, button, opening);
      if (opening && card.dataset.commentsLoaded !== '1') loadComments(card, false);
      return;
    }
    if (action === 'comments_more') return void loadComments(card, true);
    if (action === 'comment_hide' || action === 'comment_delete') return void moderateComment(card, button.closest('[data-comment-id]'), action, button);
  });

  root.addEventListener('submit', function (event) {
    var postForm = event.target.closest('[data-post-form]');
    if (postForm) { event.preventDefault(); return void savePost(true, qs('[data-post-publish]', postForm)); }
    var commentForm = event.target.closest('[data-comment-form]');
    if (commentForm) { event.preventDefault(); return void submitComment(commentForm.closest('[data-post-id]'), commentForm); }
  });

  qs('[data-owner-filter]').addEventListener('change', function (event) { ownerFilter = event.target.value; cursor = null; loadFeed(false); });
  configureView();
  loadFeed(false);
})(window, document);
