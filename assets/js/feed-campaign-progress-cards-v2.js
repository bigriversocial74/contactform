(function (window, document) {
  'use strict';

  var root = document.querySelector('[data-social-feed]');
  var list = root && root.querySelector('[data-campaign-feed-list]');
  var warning = root && root.querySelector('[data-feed-source-warning]');
  if (!root || !list) return;

  var emptyNode = root.querySelector('[data-feed-empty]');
  var errorNode = root.querySelector('[data-feed-error]');
  var errorMessage = root.querySelector('[data-feed-error-message]');

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
    if (Number.isNaN(parsed.getTime())) return '';
    return new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric' }).format(parsed);
  }

  function node(tag, className, text) {
    var element = document.createElement(tag);
    if (className) element.className = className;
    if (text !== undefined) element.textContent = String(text || '');
    return element;
  }

  function avatar(author) {
    var wrap = node('span', 'mg-feed-avatar mg-campaign-feed-avatar', '');
    var src = safeUrl(author && author.avatar_url, true);
    if (!src) {
      wrap.textContent = initials(author && author.display_name);
      return wrap;
    }
    var image = document.createElement('img');
    image.src = src;
    image.alt = '';
    image.loading = 'lazy';
    image.addEventListener('error', function () {
      image.remove();
      wrap.textContent = initials(author && author.display_name);
    }, { once: true });
    wrap.appendChild(image);
    return wrap;
  }

  function media(campaign) {
    var link = document.createElement('a');
    link.className = 'mg-campaign-feed-media is-' + (campaign.kind === 'listen' ? 'listen' : 'watch');
    link.href = safeUrl(campaign.url, true) || '#';
    link.setAttribute('aria-label', (campaign.kind === 'listen' ? 'Open listen reward: ' : 'Open watch reward: ') + String(campaign.title || 'Campaign'));
    var src = safeUrl(campaign.image_url, true);
    if (src) {
      var image = document.createElement('img');
      image.src = src;
      image.alt = '';
      image.loading = 'lazy';
      image.addEventListener('error', function () { image.remove(); link.classList.add('is-fallback'); }, { once: true });
      link.appendChild(image);
    } else link.classList.add('is-fallback');
    link.appendChild(node('span', 'mg-campaign-feed-media-icon', campaign.kind === 'listen' ? '♪' : '▶'));
    return link;
  }

  function levels(campaign) {
    var rail = node('div', 'mg-campaign-feed-levels', '');
    rail.setAttribute('aria-label', 'Reward levels');
    (Array.isArray(campaign.levels) ? campaign.levels : []).forEach(function (level) {
      var item = node('span', 'mg-campaign-feed-level', String(level.percent || 0) + '%');
      if (level.complete) item.classList.add('is-complete');
      if (level.shipped) item.classList.add('is-shipped');
      item.title = String(level.label || (level.percent + '% reward')) + (level.shipped ? ' — reward in Action Center' : '');
      rail.appendChild(item);
    });
    return rail;
  }

  function card(item) {
    var campaign = item.campaign || {};
    var authorData = item.author || {};
    var article = node('article', 'mg-feed-card mg-feed-campaign-card', '');
    article.dataset.campaignId = String(campaign.id || '');
    article.dataset.campaignKind = String(campaign.kind || '');

    var header = node('header', 'mg-feed-card-header mg-campaign-feed-header', '');
    header.appendChild(avatar(authorData));
    var identity = document.createElement('div');
    var author = document.createElement('a');
    author.href = safeUrl(authorData.url, true) || '#';
    author.textContent = String(authorData.display_name || 'Microgifter merchant');
    identity.append(author, node('span', '', String(campaign.label || 'Campaign') + ' · ' + formatDate(item.published_at)));
    var badge = node('span', 'mg-campaign-feed-state' + (campaign.reward_shipped ? ' is-shipped' : ''), campaign.reward_shipped ? 'In Action Center' : String(campaign.status || 'Ready'));
    header.append(identity, badge);

    var main = node('div', 'mg-campaign-feed-main', '');
    main.appendChild(media(campaign));
    var content = node('div', 'mg-campaign-feed-content', '');
    var title = document.createElement('a');
    title.className = 'mg-campaign-feed-title';
    title.href = safeUrl(campaign.url, true) || '#';
    title.textContent = String(campaign.title || 'Reward campaign');
    content.appendChild(title);
    if (campaign.subtitle) content.appendChild(node('p', 'mg-campaign-feed-subtitle', campaign.subtitle));

    var progressTop = node('div', 'mg-campaign-feed-progress-top', '');
    progressTop.append(node('span', '', 'Progress'), node('strong', '', Math.round(Number(campaign.progress_percent || 0)) + '%'));
    var track = node('div', 'mg-campaign-feed-progress', '');
    var fill = document.createElement('span');
    fill.style.width = Math.max(0, Math.min(100, Number(campaign.progress_percent || 0))) + '%';
    track.appendChild(fill);
    content.append(progressTop, track, levels(campaign));

    var footer = node('footer', 'mg-campaign-feed-footer', '');
    var statusText = campaign.reward_shipped
      ? String(campaign.reward_shipped_count || 1) + ' reward' + (Number(campaign.reward_shipped_count || 1) === 1 ? '' : 's') + ' in Action Center' + (campaign.reward_shipped_at ? ' · ' + formatDate(campaign.reward_shipped_at) : '')
      : (campaign.next_level_percent ? 'Next reward at ' + campaign.next_level_percent + '%' : String(campaign.reward_title || 'Campaign reward'));
    footer.appendChild(node('span', campaign.reward_shipped ? 'is-shipped' : '', statusText));

    var actionCenter = campaign.action_center || {};
    var cta = document.createElement('a');
    cta.className = 'mg-campaign-feed-cta';
    cta.href = safeUrl(campaign.reward_shipped && actionCenter.url ? actionCenter.url : campaign.url, true) || '#';
    cta.textContent = campaign.reward_shipped ? 'Open Inbox' : (campaign.kind === 'listen' ? 'Listen & earn' : 'Watch & earn');
    footer.appendChild(cta);
    content.appendChild(footer);
    main.appendChild(content);
    article.append(header, main);
    return article;
  }

  function render(items) {
    list.replaceChildren();
    (Array.isArray(items) ? items : []).forEach(function (item) { list.appendChild(card(item)); });
    list.classList.toggle('mg-hidden', list.children.length === 0);
    if (emptyNode && list.children.length > 0) emptyNode.classList.add('mg-hidden');
  }

  function sourceState(detail) {
    var sources = detail && detail.sources || {};
    var postsOk = !sources.posts || sources.posts.ok !== false;
    var campaignsOk = !sources.campaigns || sources.campaigns.ok !== false;
    var messages = Array.isArray(detail && detail.warnings) ? detail.warnings.filter(Boolean) : [];

    if (warning) {
      warning.textContent = messages.join(' ');
      warning.classList.toggle('mg-hidden', messages.length === 0);
    }
    if (!postsOk && !campaignsOk && errorNode) {
      errorNode.classList.remove('mg-hidden');
      if (errorMessage) errorMessage.textContent = 'Social posts and campaign opportunities are temporarily unavailable. Please try again.';
    }
  }

  function consume(detail) {
    detail = detail || {};
    var campaigns = detail.campaigns || {};
    render(campaigns.items || []);
    sourceState(detail);
  }

  root.addEventListener('click', function (event) {
    var tab = event.target.closest('[data-feed-tab]');
    if (!tab) return;
    var mode = String(tab.dataset.feedTab || 'discover');
    if (mode === 'mine') {
      render([]);
      if (warning) warning.classList.add('mg-hidden');
    } else {
      list.replaceChildren(node('div', 'mg-campaign-feed-loading', 'Loading campaign opportunities…'));
      list.classList.remove('mg-hidden');
    }
  });

  document.addEventListener('mg:feed-contract-v2', function (event) { consume(event.detail); });
  if (window.MicrogifterFeedContractV2Latest) consume(window.MicrogifterFeedContractV2Latest);
})(window, document);
