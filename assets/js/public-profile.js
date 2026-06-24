window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = null;
  var slug = '';
  var preview = false;

  function node(selector, scope) { return (scope || document).querySelector(selector); }
  function setText(selector, value) { var target = node(selector, root); if (target) target.textContent = value === undefined || value === null ? '' : String(value); }
  function setHidden(target, hidden) { if (target) target.classList.toggle('mg-hidden', Boolean(hidden)); }
  function clear(target) { if (target) target.replaceChildren(); }

  function safeUrl(value, allowRelative) {
    var raw = String(value || '').trim();
    if (!raw || /[\u0000-\u001f\u007f]/.test(raw)) return null;
    try {
      var parsed = new URL(raw, window.location.origin);
      if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') return null;
      if (parsed.username || parsed.password) return null;
      if (raw.charAt(0) === '/') {
        if (!allowRelative || raw.indexOf('//') === 0 || parsed.origin !== window.location.origin) return null;
        return parsed.pathname + parsed.search + parsed.hash;
      }
      return parsed.href;
    } catch (error) { return null; }
  }

  function label(value) { return String(value || '').replace(/[_-]+/g, ' ').replace(/\b\w/g, function (letter) { return letter.toUpperCase(); }); }
  function number(value) { var parsed = Number(value || 0); return Number.isFinite(parsed) ? parsed.toLocaleString() : '0'; }

  function ensureNoIndex() {
    var robots = document.querySelector('meta[name="robots"]');
    if (!robots) { robots = document.createElement('meta'); robots.name = 'robots'; document.head.appendChild(robots); }
    robots.content = 'noindex,nofollow';
  }

  function ensureCanonical(profileSlug) {
    var canonical = document.querySelector('link[rel="canonical"]');
    if (!canonical) { canonical = document.createElement('link'); canonical.rel = 'canonical'; document.head.appendChild(canonical); }
    canonical.href = window.location.origin + '/profile.php?slug=' + encodeURIComponent(profileSlug);
  }

  function createPill(text, extraClass) {
    var pill = document.createElement('span');
    pill.className = 'mg-profile-pill' + (extraClass ? ' ' + extraClass : '');
    pill.textContent = text;
    return pill;
  }

  function createExternalLink(item) {
    var href = safeUrl(item && item.url, false);
    if (!href) return null;
    var anchor = document.createElement('a');
    anchor.href = href;
    anchor.target = '_blank';
    anchor.rel = 'noopener noreferrer';
    anchor.setAttribute('aria-label', String(item.label || item.type || 'Social link'));
    var copy = document.createElement('span');
    var title = document.createElement('strong');
    var type = document.createElement('span');
    title.textContent = String(item.label || 'External link');
    type.textContent = label(item.type || 'link');
    copy.append(title, type);
    var icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    icon.setAttribute('viewBox', '0 0 24 24');
    icon.setAttribute('aria-hidden', 'true');
    var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', 'M7 17 17 7M9 7h8v8');
    path.setAttribute('fill', 'none');
    path.setAttribute('stroke', 'currentColor');
    path.setAttribute('stroke-width', '2');
    path.setAttribute('stroke-linecap', 'round');
    path.setAttribute('stroke-linejoin', 'round');
    icon.appendChild(path);
    anchor.append(copy, icon);
    return anchor;
  }

  function renderLinks(items) {
    var section = node('[data-profile-links-section]', root);
    var container = node('[data-profile-links]', root);
    clear(container);
    (Array.isArray(items) ? items : []).forEach(function (item) {
      var link = createExternalLink(item);
      if (link) container.appendChild(link);
    });
    setHidden(section, !container || container.children.length === 0);
    document.dispatchEvent(new CustomEvent('mg:public-profile:links-rendered', { detail: { root: root } }));
  }

  function renderSections(items) {
    var container = node('[data-profile-sections]', root);
    clear(container);
    (Array.isArray(items) ? items : []).forEach(function (item, index) {
      var title = String(item && item.title || '').trim();
      var body = String(item && item.body || '').trim();
      if (!title && !body) return;
      var section = document.createElement('section');
      section.className = 'mg-profile-custom-section';
      section.setAttribute('data-profile-section', String(item.id || index));
      if (title) { var heading = document.createElement('h2'); heading.textContent = title; section.appendChild(heading); }
      if (body) { var paragraph = document.createElement('p'); paragraph.textContent = body; section.appendChild(paragraph); }
      container.appendChild(section);
    });
  }

  function renderAvatar(profile) {
    var image = node('[data-profile-avatar]', root);
    var fallback = node('[data-profile-avatar-fallback]', root);
    var avatarUrl = safeUrl(profile.avatar_url, true);
    var initial = String(profile.display_name || 'M').trim().charAt(0).toUpperCase() || 'M';
    fallback.textContent = initial;
    setHidden(fallback, false);
    setHidden(image, true);
    image.removeAttribute('src');
    if (!avatarUrl) return;
    image.alt = String(profile.display_name || 'Profile') + ' avatar';
    image.onload = function () { setHidden(image, false); setHidden(fallback, true); };
    image.onerror = function () { image.removeAttribute('src'); setHidden(image, true); setHidden(fallback, false); };
    image.src = avatarUrl;
  }

  function renderCover(profile) {
    var cover = node('[data-profile-cover]', root);
    var coverUrl = safeUrl(profile.cover_url, true);
    cover.style.backgroundImage = '';
    if (!coverUrl) return;
    cover.style.backgroundImage = 'url("' + coverUrl.replace(/["'\\\n\r]/g, '') + '")';
  }

  function renderStatus(profile) {
    var row = node('[data-profile-status-row]', root);
    clear(row);
    row.appendChild(createPill(label(profile.profile_type || 'profile')));
    if (profile.visibility === 'unlisted') { row.appendChild(createPill('Unlisted', 'is-unlisted')); ensureNoIndex(); }
    if (profile.availability && profile.availability.is_preview) { row.appendChild(createPill('Owner preview', 'is-preview')); ensureNoIndex(); }
  }

  function renderMeta(profile) {
    var meta = node('[data-profile-meta]', root);
    clear(meta);
    if (profile.location_label) meta.appendChild(createPill(String(profile.location_label), 'is-location'));
  }

  function renderWebsite(profile) {
    var website = node('[data-profile-website]', root);
    var href = safeUrl(profile.website_url, false);
    if (!href) { website.removeAttribute('href'); setHidden(website, true); return; }
    website.href = href;
    setHidden(website, false);
  }

  function publishProfileData(data) {
    MG.publicProfileData = data;
    document.dispatchEvent(new CustomEvent('mg:public-profile:data', { detail: data }));
  }

  function renderProfile(data) {
    var profile = data && data.profile ? data.profile : {};
    var counts = data && data.social_counts ? data.social_counts : {};
    var displayName = String(profile.display_name || 'Microgifter profile');
    renderCover(profile);
    renderAvatar(profile);
    renderStatus(profile);
    renderMeta(profile);
    renderWebsite(profile);
    renderLinks(data.links);
    renderSections(data.sections);
    setText('[data-profile-name]', displayName);
    setText('[data-profile-followers]', number(counts.followers));
    setText('[data-profile-supporters]', number(counts.supporters));
    setText('[data-profile-products]', number(counts.published_products));
    var headline = node('[data-profile-headline]', root);
    headline.textContent = String(profile.headline || '');
    setHidden(headline, !profile.headline);
    var biography = String(profile.biography || '').trim();
    setText('[data-profile-biography]', biography || 'This profile has not added a biography yet.');
    var isOwner = Boolean(profile.availability && profile.availability.is_owner);
    var isPreview = Boolean(profile.availability && profile.availability.is_preview);
    setHidden(node('[data-profile-edit]', root), !isOwner);
    setHidden(node('[data-profile-preview-banner]', root), !isPreview);
    document.title = displayName + ' | Microgifter';
    if (profile.slug) ensureCanonical(String(profile.slug));
    publishProfileData(data);
    setState('content');
  }

  function setState(state, error) {
    var loading = node('[data-profile-loading]', root);
    var content = node('[data-profile-content]', root);
    var errorBox = node('[data-profile-error]', root);
    setHidden(loading, state !== 'loading');
    setHidden(content, state !== 'content');
    setHidden(errorBox, state !== 'error');
    root.setAttribute('aria-busy', state === 'loading' ? 'true' : 'false');
    if (state === 'error') {
      var notFound = error && Number(error.status) === 404;
      setText('[data-profile-error-title]', notFound ? 'Profile not found' : 'Unable to load profile');
      setText('[data-profile-error-message]', notFound ? 'This profile may be private, still in draft, suspended, blocked, or using a different address.' : 'The profile could not be loaded. Check your connection and try again.');
    }
  }

  async function loadProfile() {
    if (!slug) { setState('error', { status: 404 }); return; }
    setState('loading');
    var path = '/api/public/profile.php?slug=' + encodeURIComponent(slug)
      + '&product_limit=6&post_limit=6&plan_limit=3'
      + (preview ? '&preview=1' : '');
    try {
      var response = await MG.get(path);
      renderProfile(response && response.data ? response.data : response);
    } catch (error) { setState('error', error || {}); }
  }

  function init() {
    root = document.querySelector('[data-public-profile-page]');
    if (!root) return;
    slug = String(root.getAttribute('data-profile-slug') || '').trim();
    preview = root.getAttribute('data-profile-preview') === '1';
    var retry = node('[data-profile-retry]', root);
    if (retry) retry.addEventListener('click', loadProfile);
    loadProfile();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})(window, document);
