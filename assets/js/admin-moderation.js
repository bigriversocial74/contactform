document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-admin-moderation]');
  if (!root || !window.Microgifter) return;
  var MG = window.Microgifter;
  var filters = root.querySelector('[data-moderation-filters]');
  var state = { page: 1, pages: 1, selected: '', loading: false, canManage: root.dataset.canManage === '1' };

  function node(tag, className, text) {
    var item = document.createElement(tag);
    if (className) item.className = className;
    if (text !== undefined && text !== null) item.textContent = String(text);
    return item;
  }

  function label(value) {
    return String(value || '').replace(/[_-]+/g, ' ').replace(/\b\w/g, function (letter) { return letter.toUpperCase(); });
  }

  function date(value) {
    if (!value) return '—';
    var raw = String(value);
    var parsed = new Date(raw.includes('T') ? raw : raw.replace(' ', 'T') + 'Z');
    return Number.isNaN(parsed.getTime()) ? raw : parsed.toLocaleString();
  }

  function safeUrl(value) {
    var raw = String(value || '').trim();
    if (!raw || !raw.startsWith('/') || raw.startsWith('//') || /[\u0000-\u001f\u007f]/.test(raw)) return null;
    return raw;
  }

  function clear(target) { if (target) target.replaceChildren(); }
  function hide(target, value) { if (target) target.classList.toggle('mg-hidden', Boolean(value)); }

  function pill(value, tone) {
    return node('span', 'mg-admin-review-pill' + (tone ? ' is-' + tone : ''), label(value));
  }

  function queryString() {
    var values = new FormData(filters);
    values.set('page', String(state.page));
    values.set('limit', '24');
    return new URLSearchParams(values).toString();
  }

  function renderMetrics(summary) {
    ['open','reviewing','urgent','unassigned','appealed'].forEach(function (key) {
      var target = root.querySelector('[data-moderation-metric="' + key + '"]');
      if (target) target.textContent = Number(summary && summary[key] || 0).toLocaleString();
    });
  }

  function reportButton(report) {
    var button = node('button', 'mg-admin-review-item' + (state.selected === report.id ? ' is-active' : ''));
    button.type = 'button';
    button.dataset.reportId = report.id;
    var head = node('div', 'mg-admin-review-item-head');
    var name = report.subject_user && report.subject_user.name ? report.subject_user.name : label(report.subject_type);
    head.append(node('strong', '', name), pill(report.severity, report.severity));
    var description = report.details || label(report.reason_code);
    var meta = node('div', 'mg-admin-review-item-meta');
    meta.append(pill(report.subject_type), pill(report.status, report.status));
    if (!report.assigned_to) meta.append(pill('Unassigned'));
    button.append(head, node('p', '', description), meta, node('small', '', date(report.created_at)));
    return button;
  }

  function renderQueue(payload) {
    renderMetrics(payload.summary || {});
    state.page = Number(payload.pagination && payload.pagination.page || 1);
    state.pages = Number(payload.pagination && payload.pagination.pages || 1);
    var list = root.querySelector('[data-moderation-list]');
    clear(list);
    (payload.reports || []).forEach(function (report) { list.appendChild(reportButton(report)); });
    if (!list.children.length) {
      var empty = node('div', 'mg-admin-moderation-loading');
      empty.append(node('strong', '', 'No matching reports'), node('span', '', 'Adjust the filters or wait for new reports.'));
      list.appendChild(empty);
    }
    var total = root.querySelector('[data-moderation-total]');
    if (total) total.textContent = Number(payload.pagination && payload.pagination.total || 0).toLocaleString();
    var pageLabel = root.querySelector('[data-moderation-page-label]');
    if (pageLabel) pageLabel.textContent = 'Page ' + state.page + ' of ' + state.pages;
    var previous = root.querySelector('[data-moderation-page="previous"]');
    var next = root.querySelector('[data-moderation-page="next"]');
    if (previous) previous.disabled = state.page <= 1;
    if (next) next.disabled = state.page >= state.pages;
    var updated = root.querySelector('[data-moderation-updated]');
    if (updated) updated.textContent = date(payload.generated_at);
  }

  async function loadQueue(selectFirst) {
    if (state.loading) return;
    state.loading = true;
    try {
      var response = await MG.get('/api/admin/content-review/queue.php?' + queryString());
      var payload = response.data || response;
      renderQueue(payload);
      if (selectFirst && !state.selected && payload.reports && payload.reports[0]) selectReport(payload.reports[0].id);
    } catch (error) {
      if (MG.toast) MG.toast(error.message || 'Unable to load reports.', 'error');
    } finally { state.loading = false; }
  }

  function factList(values) {
    var list = node('dl', 'mg-admin-review-facts');
    Object.keys(values || {}).forEach(function (key) {
      if (values[key] === null || values[key] === undefined || values[key] === '') return;
      var row = node('div');
      row.append(node('dt', '', label(key)), node('dd', '', typeof values[key] === 'boolean' ? (values[key] ? 'Yes' : 'No') : values[key]));
      list.appendChild(row);
    });
    return list;
  }

  function mediaPreview(url, type) {
    var safe = safeUrl(url);
    if (!safe) return null;
    if (type === 'image') {
      var image = document.createElement('img'); image.src = safe; image.alt = ''; image.loading = 'lazy'; return image;
    }
    if (type === 'video') {
      var video = document.createElement('video'); video.src = safe; video.controls = true; video.preload = 'metadata'; return video;
    }
    if (type === 'audio') {
      var audio = document.createElement('audio'); audio.src = safe; audio.controls = true; audio.preload = 'metadata'; return audio;
    }
    return null;
  }

  function renderSubject(payload) {
    var target = root.querySelector('[data-report-subject]');
    clear(target);
    target.append(node('h3', '', 'Reported content'));
    var subject = payload.subject || {};
    if (!subject.available) {
      target.append(node('p', '', 'The live subject is unavailable. The original report snapshot is shown below.'), factList(subject.snapshot || {}));
      return;
    }
    if (subject.profile) {
      var profile = subject.profile;
      target.append(node('h4', '', profile.profile_name || profile.display_name || profile.email), node('p', '', profile.biography || profile.headline || 'No profile biography.'));
      target.append(factList({profile_id:profile.profile_id,slug:profile.slug,profile_status:profile.profile_status,visibility:profile.visibility,user_status:profile.status,updated_at:date(profile.profile_updated_at)}));
      return;
    }
    if (subject.post) {
      var post = subject.post;
      target.append(node('h4', '', post.title || 'Feed post'), node('p', 'mg-admin-review-body', post.body || 'No post body.'));
      var gallery = node('div', 'mg-admin-review-media');
      (post.media || []).forEach(function (item) {
        var preview = mediaPreview(item.url, item.type);
        if (preview) gallery.appendChild(preview);
      });
      if (gallery.children.length) target.appendChild(gallery);
      target.append(factList({post_id:post.public_id,author:post.author_name,type:post.post_type,status:post.status,moderation_status:post.moderation_status,visibility:post.visibility,created_at:date(post.created_at)}));
      return;
    }
    if (subject.comment) {
      target.append(node('p', 'mg-admin-review-body', subject.comment.body), factList({comment_id:subject.comment.public_id,author:subject.comment.author_name,status:subject.comment.status,post_id:subject.comment.post_id,created_at:date(subject.comment.created_at)}));
      return;
    }
    if (subject.message) {
      target.append(node('p', 'mg-admin-review-body', subject.message.body), factList({message_id:subject.message.public_id,sender:subject.message.sender_name,status:subject.message.moderation_status,thread:subject.message.thread_subject || subject.message.thread_id,created_at:date(subject.message.created_at)}));
      return;
    }
    if (subject.media) {
      var preview = mediaPreview(subject.media.preview_url, subject.media.asset_type);
      if (preview) target.appendChild(preview);
      target.append(factList({asset_id:subject.media.public_id,owner:subject.media.owner_name,type:subject.media.asset_type,filename:subject.media.original_filename,mime_type:subject.media.mime_type,bytes:subject.media.byte_size,moderation_status:subject.media.moderation_status,post_id:subject.media.post_id}));
    }
  }

  function renderAccount(account) {
    var target = root.querySelector('[data-report-account]');
    clear(target);
    target.append(node('h3', '', 'Account context'));
    if (!account || !account.user) { target.append(node('p', '', 'No linked account is available.')); return; }
    var user = account.user;
    target.append(node('h4', '', user.display_name || user.full_name || user.email));
    target.append(factList({user_id:user.public_id,email:user.email,user_status:user.status,profile_status:user.profile_status,profile_slug:user.slug,created_at:date(user.created_at),posts:account.counts.posts,comments:account.counts.comments,messages:account.counts.messages,reports:account.counts.reports,active_reports:account.counts.active_reports}));
    if (account.restrictions && account.restrictions.length) {
      var restrictions = node('div', 'mg-admin-review-restrictions');
      account.restrictions.forEach(function (item) { restrictions.append(pill(item.restriction_type, 'high')); });
      target.append(node('h4', '', 'Active restrictions'), restrictions);
    }
  }

  function renderHistory(items) {
    var target = root.querySelector('[data-report-history]');
    clear(target);
    target.append(node('h3', '', 'Review history'));
    var list = node('ol', 'mg-admin-review-timeline');
    (items || []).forEach(function (item) {
      var row = node('li');
      row.append(node('strong', '', label(item.action) + ' · ' + item.actor_name), node('p', '', item.reason || 'No note supplied.'), node('small', '', date(item.created_at)));
      list.appendChild(row);
    });
    if (!list.children.length) list.append(node('li', '', 'No review history.'));
    target.appendChild(list);
  }

  function renderDetail(payload) {
    var report = payload.report;
    state.selected = report.id;
    root.querySelectorAll('[data-report-id]').forEach(function (item) { item.classList.toggle('is-active', item.dataset.reportId === state.selected); });
    hide(root.querySelector('[data-moderation-empty]'), true);
    hide(root.querySelector('[data-moderation-detail]'), false);
    var badges = root.querySelector('[data-report-badges]');
    clear(badges); badges.append(pill(report.subject_type), pill(report.status, report.status), pill(report.severity, report.severity));
    root.querySelector('[data-report-title]').textContent = label(report.reason_code) + ' report';
    root.querySelector('[data-report-description]').textContent = report.details || 'No additional reporter details.';
    var meta = root.querySelector('[data-report-meta]');
    clear(meta);
    var facts = {report_id:report.id,reported:date(report.created_at),reporter:report.reporter && report.reporter.name,assigned:report.assigned_to && report.assigned_to.name || 'Unassigned'};
    Object.keys(facts).forEach(function (key) { var row=node('div'); row.append(node('dt','',label(key)),node('dd','',facts[key])); meta.appendChild(row); });
    renderSubject(payload);
    renderAccount(payload.account);
    renderHistory(payload.history);
    var form = root.querySelector('[data-moderation-action-form]');
    if (form) {
      form.elements.report_id.value = report.id;
      hide(form, false);
      hide(root.querySelector('[data-moderation-action-empty]'), true);
      var submit = form.querySelector('[data-moderation-action-submit]');
      if (submit) submit.disabled = !state.canManage;
    }
  }

  async function selectReport(id) {
    state.selected = id;
    root.querySelectorAll('[data-report-id]').forEach(function (item) { item.classList.toggle('is-active', item.dataset.reportId === id); });
    try {
      var response = await MG.get('/api/admin/content-review/detail.php?id=' + encodeURIComponent(id));
      renderDetail(response.data || response);
    } catch (error) {
      if (MG.toast) MG.toast(error.message || 'Unable to load report.', 'error');
    }
  }

  filters.addEventListener('submit', function (event) { event.preventDefault(); state.page = 1; state.selected = ''; loadQueue(true); });
  root.addEventListener('click', function (event) {
    var report = event.target.closest('[data-report-id]');
    if (report) { selectReport(report.dataset.reportId); return; }
    var pager = event.target.closest('[data-moderation-page]');
    if (pager) {
      state.page += pager.dataset.moderationPage === 'next' ? 1 : -1;
      state.page = Math.max(1, Math.min(state.pages, state.page));
      loadQueue(false);
      return;
    }
    if (event.target.closest('[data-moderation-refresh]')) loadQueue(false);
  });

  loadQueue(true);
});
