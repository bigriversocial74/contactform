window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root;
  var slug = '';
  var preview = false;
  var profileId = '';
  var postCursor = null;
  var planCursor = null;
  var postLoading = false;
  var planLoading = false;
  var postIds = new Set();
  var planIds = new Set();
  var viewerIsOwner = false;
  var relationship = null;
  var tipCapability = null;
  var pendingCardTip = null;

  function qs(selector, scope) { return (scope || document).querySelector(selector); }
  function qsa(selector, scope) { return Array.from((scope || document).querySelectorAll(selector)); }
  function hide(target, value) { if (target) target.classList.toggle('mg-hidden', Boolean(value)); }
  function clear(target) { if (target) target.replaceChildren(); }
  function payload(response) { return response && response.data ? response.data : response; }

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

  function money(cents, currency) {
    try {
      return new Intl.NumberFormat(undefined, {
        style: 'currency', currency: String(currency || 'USD').toUpperCase(),
      }).format(Number(cents || 0) / 100);
    } catch (error) { return '$' + (Number(cents || 0) / 100).toFixed(2); }
  }

  function date(value) {
    if (!value) return '';
    var raw = String(value);
    var parsed = new Date(raw.replace(' ', 'T') + (raw.includes('T') ? '' : 'Z'));
    return Number.isNaN(parsed.getTime()) ? raw : new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(parsed);
  }

  function label(value) {
    return String(value || '').replace(/[_-]+/g, ' ').replace(/\b\w/g, function (letter) { return letter.toUpperCase(); });
  }

  function uuid(prefix) {
    var value = window.crypto && window.crypto.randomUUID
      ? window.crypto.randomUUID()
      : String(Date.now()) + '-' + Math.random().toString(16).slice(2);
    return String(prefix || 'request') + ':' + value;
  }

  function signIn() {
    window.location.href = '/signin.php?return=' + encodeURIComponent(window.location.pathname + window.location.search);
  }

  function setStatus(target, message, type) {
    if (!target) return;
    target.textContent = message || '';
    target.className = 'mg-profile-action-status' + (message ? ' is-visible' : '') + (type ? ' is-' + type : '');
  }

  function postMedia(items, title) {
    var media = document.createElement('div');
    media.className = 'mg-profile-post-media';
    (Array.isArray(items) ? items : []).slice(0, 6).forEach(function (item) {
      var src = safeUrl(item && item.url, true);
      if (!src) return;
      var figure = document.createElement('figure');
      var image = document.createElement('img');
      image.src = src;
      image.alt = String(item.alt || title || 'Post image');
      image.loading = 'lazy';
      figure.appendChild(image);
      if (item.caption) {
        var caption = document.createElement('figcaption');
        caption.textContent = String(item.caption);
        figure.appendChild(caption);
      }
      media.appendChild(figure);
    });
    return media;
  }

  function stat(name, value) {
    var item = document.createElement('span');
    item.setAttribute('data-post-stat', name);
    item.textContent = Number(value || 0).toLocaleString() + ' ' + name;
    return item;
  }

  function reactionButton(type) {
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'mg-profile-reaction-button';
    button.dataset.postReaction = type;
    button.textContent = label(type);
    button.setAttribute('aria-pressed', 'false');
    return button;
  }

  function commentsPanel() {
    var panel = document.createElement('div');
    panel.className = 'mg-profile-comments mg-hidden';
    panel.dataset.commentsPanel = '1';

    var status = document.createElement('div');
    status.className = 'mg-profile-comments-status';
    status.dataset.commentsStatus = '1';
    status.setAttribute('role', 'status');

    var list = document.createElement('div');
    list.className = 'mg-profile-comment-list';
    list.dataset.commentList = '1';

    var more = document.createElement('button');
    more.className = 'mg-btn mg-btn-soft mg-hidden';
    more.type = 'button';
    more.dataset.commentsMore = '1';
    more.textContent = 'Load more comments';

    var form = document.createElement('form');
    form.className = 'mg-profile-comment-form';
    form.dataset.commentForm = '1';

    var fieldLabel = document.createElement('label');
    var fieldTitle = document.createElement('span');
    fieldTitle.textContent = 'Join the conversation';
    var textarea = document.createElement('textarea');
    textarea.name = 'comment_body';
    textarea.maxLength = 2000;
    textarea.rows = 3;
    textarea.required = true;
    fieldLabel.append(fieldTitle, textarea);

    var submit = document.createElement('button');
    submit.className = 'mg-btn mg-btn-primary';
    submit.type = 'submit';
    submit.textContent = 'Post comment';
    form.append(fieldLabel, submit);

    panel.append(status, list, more, form);
    return panel;
  }

  function postCard(post) {
    var id = String(post && post.id || '');
    if (!id || postIds.has(id)) return null;
    postIds.add(id);

    var card = document.createElement('article');
    card.className = 'mg-profile-post-card';
    card.dataset.postId = id;

    var meta = document.createElement('div');
    meta.className = 'mg-profile-post-meta';
    var type = document.createElement('span');
    type.textContent = label(post.type || 'update');
    var published = document.createElement('time');
    published.textContent = date(post.published_at);
    if (post.published_at) published.dateTime = String(post.published_at);
    meta.append(type, published);
    card.appendChild(meta);

    if (post.headline) {
      var heading = document.createElement('h3');
      heading.textContent = String(post.headline);
      card.appendChild(heading);
    }
    if (post.body) {
      var body = document.createElement('p');
      body.textContent = String(post.body);
      card.appendChild(body);
    }

    var media = postMedia(post.media, post.headline);
    if (media.children.length) card.appendChild(media);

    var engagement = post.engagement || {};
    card.dataset.viewerReaction = String(engagement.viewer_reaction || '');
    var stats = document.createElement('div');
    stats.className = 'mg-profile-post-stats';
    stats.append(stat('comments', engagement.comments), stat('reactions', engagement.reactions), stat('shares', engagement.shares));

    var actions = document.createElement('div');
    actions.className = 'mg-profile-post-actions';
    ['like', 'love', 'celebrate', 'support'].forEach(function (reaction) {
      var button = reactionButton(reaction);
      var active = reaction === card.dataset.viewerReaction;
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
      button.classList.toggle('is-active', active);
      actions.appendChild(button);
    });
    var comments = document.createElement('button');
    comments.type = 'button';
    comments.className = 'mg-profile-comment-toggle';
    comments.dataset.postComments = '1';
    comments.textContent = 'Comments';
    actions.appendChild(comments);

    card.append(stats, actions, commentsPanel());
    return card;
  }

  function renderPosts(collection, append) {
    var section = qs('[data-profile-posts-section]', root);
    var list = qs('[data-profile-posts-list]', root);
    var empty = qs('[data-profile-posts-empty]', root);
    var pagination = qs('[data-post-pagination]', root);
    if (!append) { clear(list); postIds = new Set(); }
    var items = collection && Array.isArray(collection.items) ? collection.items : [];
    items.forEach(function (post) { var card = postCard(post); if (card) list.appendChild(card); });
    postCursor = collection && collection.has_more ? String(collection.next_cursor || '') : null;
    hide(section, list.children.length === 0 && !postCursor);
    hide(empty, list.children.length > 0);
    hide(pagination, !postCursor);
  }

  function updatePostState(card, engagement) {
    if (!card || !engagement) return;
    ['comments', 'reactions', 'shares'].forEach(function (name) {
      var target = qs('[data-post-stat="' + name + '"]', card);
      if (target) target.textContent = Number(engagement[name] || 0).toLocaleString() + ' ' + name;
    });
    card.dataset.viewerReaction = String(engagement.viewer_reaction || '');
    qsa('[data-post-reaction]', card).forEach(function (button) {
      var active = button.dataset.postReaction === card.dataset.viewerReaction;
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
      button.classList.toggle('is-active', active);
    });
  }

  function commentNode(comment) {
    var item = document.createElement('article');
    item.className = 'mg-profile-comment';
    item.dataset.commentId = String(comment.id || '');

    var header = document.createElement('div');
    header.className = 'mg-profile-comment-header';
    var author = document.createElement(comment.author && comment.author.profile_slug ? 'a' : 'strong');
    author.textContent = String(comment.author && comment.author.display_name || 'Microgifter member');
    if (comment.author && comment.author.profile_slug) author.href = '/profile.php?slug=' + encodeURIComponent(comment.author.profile_slug);
    var time = document.createElement('time');
    time.textContent = date(comment.created_at);
    header.append(author, time);

    var body = document.createElement('p');
    body.textContent = String(comment.body || '');
    item.append(header, body);

    var permissions = comment.permissions || {};
    if (permissions.can_delete || permissions.can_hide) {
      var actions = document.createElement('div');
      actions.className = 'mg-profile-comment-actions';
      if (permissions.can_hide) {
        var hideButton = document.createElement('button');
        hideButton.type = 'button';
        hideButton.dataset.commentAction = 'comment_hide';
        hideButton.textContent = 'Hide';
        actions.appendChild(hideButton);
      }
      if (permissions.can_delete) {
        var deleteButton = document.createElement('button');
        deleteButton.type = 'button';
        deleteButton.dataset.commentAction = 'comment_delete';
        deleteButton.textContent = 'Delete';
        actions.appendChild(deleteButton);
      }
      item.appendChild(actions);
    }
    return item;
  }

  function renderComments(card, collection, append) {
    var list = qs('[data-comment-list]', card);
    if (!append) clear(list);
    (collection && Array.isArray(collection.items) ? collection.items : []).forEach(function (comment) {
      list.appendChild(commentNode(comment));
    });
    card.dataset.commentCursor = collection && collection.has_more ? String(collection.next_cursor || '') : '';
    card.dataset.commentsLoaded = '1';
    hide(qs('[data-comments-more]', card), !card.dataset.commentCursor);
  }

  async function loadComments(card, append) {
    if (!card || card.dataset.commentsLoading === '1') return;
    card.dataset.commentsLoading = '1';
    var status = qs('[data-comments-status]', card);
    status.textContent = append ? 'Loading more comments…' : 'Loading comments…';
    var path = '/api/public/post-engagement.php?post_id=' + encodeURIComponent(card.dataset.postId) + '&limit=20';
    if (append && card.dataset.commentCursor) path += '&cursor=' + encodeURIComponent(card.dataset.commentCursor);
    try {
      var data = payload(await MG.get(path));
      updatePostState(card, data.engagement || {});
      renderComments(card, data.comments || {}, append);
      status.textContent = '';
    } catch (error) {
      status.textContent = error.message || 'Unable to load comments.';
    } finally { card.dataset.commentsLoading = '0'; }
  }

  async function react(card, type, button) {
    if (!MG.isAuthenticated || !MG.isAuthenticated()) return void signIn();
    var current = String(card.dataset.viewerReaction || '');
    var action = current === type ? 'unreact' : 'react';
    MG.setBusy(button, true, 'Saving…');
    try {
      var data = payload(await MG.post('/api/social/engage.php', {
        action: action,
        post_id: card.dataset.postId,
        reaction_type: type,
        idempotency_key: uuid('profile-reaction'),
      }));
      updatePostState(card, data.engagement || {});
    } catch (error) { MG.toast(error.message || 'Unable to update reaction.', 'error'); }
    finally { MG.setBusy(button, false); }
  }

  async function submitComment(card, form) {
    if (!MG.isAuthenticated || !MG.isAuthenticated()) return void signIn();
    var input = qs('[name="comment_body"]', form);
    var button = qs('[type="submit"]', form);
    var body = String(input && input.value || '').trim();
    if (!body) return;
    MG.setBusy(button, true, 'Posting…');
    try {
      var data = payload(await MG.post('/api/social/engage.php', {
        action: 'comment', post_id: card.dataset.postId, body: body,
        idempotency_key: uuid('profile-comment'),
      }));
      var list = qs('[data-comment-list]', card);
      if (data.comment) list.appendChild(commentNode(data.comment));
      updatePostState(card, data.engagement || {});
      input.value = '';
      card.dataset.commentsLoaded = '1';
    } catch (error) { MG.toast(error.message || 'Unable to post comment.', 'error'); }
    finally { MG.setBusy(button, false); }
  }

  async function moderateComment(card, comment, action, button) {
    if (!MG.isAuthenticated || !MG.isAuthenticated()) return void signIn();
    MG.setBusy(button, true, 'Saving…');
    try {
      var data = payload(await MG.post('/api/social/engage.php', {
        action: action, comment_id: comment.dataset.commentId,
        idempotency_key: uuid('profile-comment-action'),
      }));
      comment.remove();
      updatePostState(card, data.engagement || {});
    } catch (error) { MG.toast(error.message || 'Unable to update comment.', 'error'); }
    finally { MG.setBusy(button, false); }
  }

  function planStatus(id) {
    return qsa('[data-plan-status]', root).find(function (item) {
      return item.getAttribute('data-plan-status') === id;
    }) || null;
  }

  function planCard(plan) {
    var id = String(plan && plan.id || '');
    if (!id || planIds.has(id)) return null;
    planIds.add(id);

    var card = document.createElement('article');
    card.className = 'mg-profile-plan-card';
    card.dataset.planId = id;
    var heading = document.createElement('div');
    heading.className = 'mg-profile-plan-heading';
    var title = document.createElement('h3');
    title.textContent = String(plan.name || 'Membership');
    var price = document.createElement('strong');
    price.textContent = money(plan.amount_cents, plan.currency);
    heading.append(title, price);

    var details = document.createElement('div');
    details.className = 'mg-profile-plan-details';
    var count = Math.max(1, Number(plan.interval && plan.interval.count || 1));
    var unit = String(plan.interval && plan.interval.unit || 'month');
    var interval = document.createElement('span');
    interval.textContent = count === 1 ? 'Every ' + unit : 'Every ' + count + ' ' + unit + 's';
    details.appendChild(interval);
    if (Number(plan.trial && plan.trial.days || 0) > 0) {
      var trial = document.createElement('span');
      trial.textContent = Number(plan.trial.days) + '-day trial';
      details.appendChild(trial);
    }

    var description = document.createElement('p');
    description.textContent = String(plan.description || 'Support this profile with a recurring subscription.');
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'mg-btn mg-btn-primary';
    button.dataset.subscribePlan = id;
    button.textContent = viewerIsOwner ? 'Your plan' : (MG.isAuthenticated && MG.isAuthenticated() ? 'Subscribe' : 'Sign in to subscribe');
    button.disabled = viewerIsOwner;
    var status = document.createElement('div');
    status.className = 'mg-profile-action-status';
    status.setAttribute('data-plan-status', id);
    card.append(heading, details, description, button, status);
    return card;
  }

  function renderPlans(collection, append) {
    var section = qs('[data-profile-support-section]', root);
    var grid = qs('[data-profile-plans-grid]', root);
    var empty = qs('[data-profile-plans-empty]', root);
    var pagination = qs('[data-plan-pagination]', root);
    if (!append) { clear(grid); planIds = new Set(); }
    var items = collection && Array.isArray(collection.items) ? collection.items : [];
    items.forEach(function (plan) { var card = planCard(plan); if (card) grid.appendChild(card); });
    planCursor = collection && collection.has_more ? String(collection.next_cursor || '') : null;
    hide(empty, grid.children.length > 0);
    hide(pagination, !planCursor);
    hide(section, grid.children.length === 0 && !planCursor && !(tipCapability && tipCapability.available));
  }

  function renderFollow(data) {
    relationship = data && data.relationship ? data.relationship : null;
    profileId = String(data && data.profile && data.profile.id || '');
    var button = qs('[data-profile-follow]', root);
    if (!button || viewerIsOwner || !profileId) { hide(button, true); return; }
    hide(button, false);
    var authenticated = MG.isAuthenticated && MG.isAuthenticated();
    button.textContent = !authenticated ? 'Sign in to follow' : (relationship && relationship.following ? 'Following' : 'Follow');
    button.classList.toggle('mg-btn-primary', !(relationship && relationship.following));
    button.classList.toggle('mg-btn-soft', Boolean(relationship && relationship.following));
  }

  async function follow(button) {
    if (!MG.isAuthenticated || !MG.isAuthenticated()) return void signIn();
    var action = relationship && relationship.following ? 'unfollow' : 'follow';
    var status = qs('[data-profile-follow-status]', root);
    MG.setBusy(button, true, action === 'follow' ? 'Following…' : 'Updating…');
    setStatus(status, '', '');
    try {
      var data = payload(await MG.post('/api/social/relationship.php', {
        action: action, profile_id: profileId, idempotency_key: uuid('profile-follow'),
      }));
      relationship = data.relationship || relationship;
      var followers = qs('[data-profile-followers]', root);
      if (followers && relationship) followers.textContent = Number(relationship.followers || 0).toLocaleString();
      setStatus(status, relationship && relationship.following ? 'You are following this profile.' : 'You are no longer following this profile.', 'success');
    } catch (error) { setStatus(status, error.message || 'Unable to update follow status.', 'error'); }
    finally {
      MG.setBusy(button, false);
      renderFollow({ profile: { id: profileId }, relationship: relationship });
    }
  }

  function renderTip(capability) {
    tipCapability = capability && capability.available ? capability : null;
    var card = qs('[data-profile-tip-card]', root);
    if (!tipCapability || viewerIsOwner) { hide(card, true); return; }
    hide(card, false);
    var button = qs('[data-profile-tip-submit]', root);
    if (button) button.textContent = MG.isAuthenticated && MG.isAuthenticated() ? 'Send tip' : 'Sign in to tip';
  }

  function render(data) {
    root = root || qs('[data-public-profile-page]');
    if (!root) return;
    viewerIsOwner = Boolean(data && data.profile && data.profile.availability && data.profile.availability.is_owner);
    renderFollow(data || {});
    renderTip(data && data.tip);
    renderPlans(data && data.subscription_plans || {}, false);
    renderPosts(data && data.posts || {}, false);
  }

  async function loadMorePosts(button) {
    if (!postCursor || postLoading) return;
    postLoading = true;
    MG.setBusy(button, true, 'Loading…');
    var path = '/api/public/profile.php?slug=' + encodeURIComponent(slug)
      + '&product_limit=1&post_limit=6&plan_limit=1&post_cursor=' + encodeURIComponent(postCursor)
      + (preview ? '&preview=1' : '');
    try { renderPosts(payload(await MG.get(path)).posts || {}, true); }
    catch (error) { MG.toast(error.message || 'Unable to load more updates.', 'error'); }
    finally { postLoading = false; MG.setBusy(button, false); }
  }

  async function loadMorePlans(button) {
    if (!planCursor || planLoading) return;
    planLoading = true;
    MG.setBusy(button, true, 'Loading…');
    var path = '/api/public/profile.php?slug=' + encodeURIComponent(slug)
      + '&product_limit=1&post_limit=1&plan_limit=6&plan_cursor=' + encodeURIComponent(planCursor)
      + (preview ? '&preview=1' : '');
    try { renderPlans(payload(await MG.get(path)).subscription_plans || {}, true); }
    catch (error) { MG.toast(error.message || 'Unable to load more memberships.', 'error'); }
    finally { planLoading = false; MG.setBusy(button, false); }
  }

  async function subscribe(button) {
    if (viewerIsOwner || !button.dataset.subscribePlan) return;
    if (!MG.isAuthenticated || !MG.isAuthenticated()) return void signIn();
    var id = button.dataset.subscribePlan;
    var status = planStatus(id);
    var completed = false;
    MG.setBusy(button, true, 'Starting…');
    setStatus(status, '', '');
    try {
      var data = payload(await MG.post('/api/subscriptions/create.php', {
        plan_id: id, idempotency_key: uuid('profile-subscription'),
        metadata: { source: 'public_profile', profile_slug: slug },
      }));
      var subscription = data.subscription || data;
      var payment = subscription.initial_attempt && subscription.initial_attempt.tip;
      if (payment && payment.client_secret) {
        document.dispatchEvent(new CustomEvent('mg:payment:requires-confirmation', { detail: payment }));
        setStatus(status, 'Subscription created. Payment authorization is ready.', 'success');
      } else setStatus(status, 'Subscription started successfully.', 'success');
      completed = true;
    } catch (error) { setStatus(status, error.message || 'Unable to start this subscription.', 'error'); }
    finally {
      MG.setBusy(button, false);
      if (completed) { button.textContent = 'Subscribed'; button.disabled = true; }
    }
  }

  function showCardConfirmation(data) {
    pendingCardTip = {
      tip_id: String(data.tip_id || ''),
      client_secret: data.client_secret || null,
      provider_payment_id: data.provider_payment_id || null,
    };
    hide(qs('[data-profile-tip-confirmation]', root), false);
    document.dispatchEvent(new CustomEvent('mg:payment:requires-confirmation', {
      detail: {
        kind: 'tip', tip_id: pendingCardTip.tip_id,
        client_secret: pendingCardTip.client_secret,
        provider_payment_id: pendingCardTip.provider_payment_id,
        confirm_url: '/api/tips/confirm.php',
      },
    }));
  }

  async function confirmCardTip(button) {
    if (!pendingCardTip || !pendingCardTip.tip_id) return;
    var status = qs('[data-profile-tip-status]', root);
    MG.setBusy(button, true, 'Confirming…');
    try {
      var data = payload(await MG.post('/api/tips/confirm.php', {
        tip_id: pendingCardTip.tip_id,
        idempotency_key: uuid('profile-tip-confirm'),
      }));
      if (data.posted || data.status === 'posted') {
        setStatus(status, 'Card-funded tip confirmed and posted.', 'success');
        hide(qs('[data-profile-tip-confirmation]', root), true);
        pendingCardTip = null;
      } else {
        if (data.client_secret) pendingCardTip.client_secret = data.client_secret;
        setStatus(status, data.status === 'processing' ? 'Card payment is processing.' : 'Complete card authorization, then confirm again.', 'success');
      }
    } catch (error) { setStatus(status, error.message || 'Unable to confirm this card tip.', 'error'); }
    finally { MG.setBusy(button, false); }
  }

  async function tip(form) {
    if (!tipCapability || !tipCapability.target) return;
    if (!MG.isAuthenticated || !MG.isAuthenticated()) return void signIn();
    var amountInput = qs('[name="tip_amount"]', form);
    var fundingInput = qs('[name="tip_funding"]', form);
    var submit = qs('[data-profile-tip-submit]', form);
    var status = qs('[data-profile-tip-status]', form);
    var cents = Math.round(Number(amountInput && amountInput.value || 0) * 100);
    var funding = String(fundingInput && fundingInput.value || 'wallet');
    if (!Number.isFinite(cents) || cents < 100 || cents > 100000) {
      setStatus(status, 'Enter an amount between $1 and $1,000.', 'error');
      return;
    }

    MG.setBusy(submit, true, 'Creating…');
    setStatus(status, '', '');
    try {
      var data = payload(await MG.post('/api/tips/create.php', {
        target_type: String(tipCapability.target.type || 'profile'),
        target_reference: String(tipCapability.target.id || ''),
        amount_cents: cents, currency: 'USD', funding_type: funding,
        idempotency_key: uuid('profile-tip'),
        metadata: { source: 'public_profile', profile_slug: slug },
      }));
      if (data.status === 'posted') {
        setStatus(status, 'Tip sent successfully.', 'success');
        form.reset();
      } else if (funding === 'stripe' && data.client_secret) {
        showCardConfirmation(data);
        setStatus(status, 'Card tip created. Complete authorization to post the tip.', 'success');
      } else setStatus(status, 'Tip request created.', 'success');
    } catch (error) { setStatus(status, error.message || 'Unable to send this tip.', 'error'); }
    finally { MG.setBusy(submit, false); }
  }

  function init() {
    root = qs('[data-public-profile-page]');
    if (!root) return;
    slug = String(root.dataset.profileSlug || '');
    preview = root.dataset.profilePreview === '1';
    document.addEventListener('mg:public-profile:data', function (event) { render(event.detail || {}); });
    document.addEventListener('mg:payment:confirmed', function (event) {
      var detail = event.detail || {};
      if (pendingCardTip && String(detail.tip_id || '') === pendingCardTip.tip_id) {
        var button = qs('[data-profile-tip-confirm]', root);
        if (button) confirmCardTip(button);
      }
    });
    root.addEventListener('click', function (event) {
      var followButton = event.target.closest('[data-profile-follow]');
      if (followButton) return void follow(followButton);
      var postMore = event.target.closest('[data-posts-load-more]');
      if (postMore) return void loadMorePosts(postMore);
      var planMore = event.target.closest('[data-plans-load-more]');
      if (planMore) return void loadMorePlans(planMore);
      var plan = event.target.closest('[data-subscribe-plan]');
      if (plan) return void subscribe(plan);
      var quickTip = event.target.closest('[data-tip-amount]');
      if (quickTip) {
        var input = qs('[name="tip_amount"]', root);
        if (input) input.value = quickTip.dataset.tipAmount;
        return;
      }
      var confirmTip = event.target.closest('[data-profile-tip-confirm]');
      if (confirmTip) return void confirmCardTip(confirmTip);
      var reaction = event.target.closest('[data-post-reaction]');
      if (reaction) return void react(reaction.closest('[data-post-id]'), reaction.dataset.postReaction, reaction);
      var comments = event.target.closest('[data-post-comments]');
      if (comments) {
        var card = comments.closest('[data-post-id]');
        var panel = qs('[data-comments-panel]', card);
        var opening = panel.classList.contains('mg-hidden');
        hide(panel, !opening);
        if (opening && card.dataset.commentsLoaded !== '1') loadComments(card, false);
        return;
      }
      var moreComments = event.target.closest('[data-comments-more]');
      if (moreComments) return void loadComments(moreComments.closest('[data-post-id]'), true);
      var commentAction = event.target.closest('[data-comment-action]');
      if (commentAction) {
        var comment = commentAction.closest('[data-comment-id]');
        return void moderateComment(commentAction.closest('[data-post-id]'), comment, commentAction.dataset.commentAction, commentAction);
      }
    });
    root.addEventListener('submit', function (event) {
      var tipForm = event.target.closest('[data-profile-tip-form]');
      if (tipForm) { event.preventDefault(); return void tip(tipForm); }
      var commentForm = event.target.closest('[data-comment-form]');
      if (commentForm) { event.preventDefault(); return void submitComment(commentForm.closest('[data-post-id]'), commentForm); }
    });
    if (MG.publicProfileData) render(MG.publicProfileData);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})(window, document);
