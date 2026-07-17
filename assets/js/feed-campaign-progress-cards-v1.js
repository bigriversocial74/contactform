(function (window, document) {
  'use strict';

  var root = document.querySelector('[data-social-feed], [data-newsfeed]');
  var list = root && root.querySelector('[data-campaign-feed-list]');
  if (!root || !list) return;

  var requestController = null;
  var currentMode = root.hasAttribute('data-newsfeed')
    ? 'following'
    : String(root.getAttribute('data-initial-feed-view') || 'discover');

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
  function setHidden(hidden) { list.classList.toggle('mg-hidden', Boolean(hidden)); }
  function textNode(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    node.textContent = String(text || '');
    return node;
  }
  function avatar(author) {
    var wrap = textNode('span', 'mg-feed-avatar mg-campaign-feed-avatar', '');
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
  function campaignMedia(campaign) {
    var media = document.createElement('a');
    media.className = 'mg-campaign-feed-media is-' + (campaign.kind === 'listen' ? 'listen' : 'watch');
    media.href = safeUrl(campaign.url, true) || '#';
    media.setAttribute('aria-label', (campaign.kind === 'listen' ? 'Open listen reward: ' : 'Open watch reward: ') + String(campaign.title || 'Campaign'));
    var src = safeUrl(campaign.image_url, true);
    if (src) {
      var image = document.createElement('img');
      image.src = src;
      image.alt = '';
      image.loading = 'lazy';
      image.addEventListener('error', function () { image.remove(); media.classList.add('is-fallback'); }, { once: true });
      media.appendChild(image);
    } else media.classList.add('is-fallback');
    media.appendChild(textNode('span', 'mg-campaign-feed-media-icon', campaign.kind === 'listen' ? '♪' : '▶'));
    return media;
  }
  function levelRail(campaign) {
    var levels = document.createElement('div');
    levels.className = 'mg-campaign-feed-levels';
    levels.setAttribute('aria-label', 'Reward levels');
    (Array.isArray(campaign.levels) ? campaign.levels : []).forEach(function (level) {
      var node = textNode('span', 'mg-campaign-feed-level', String(level.percent || 0) + '%');
      if (level.complete) node.classList.add('is-complete');
      if (level.shipped) node.classList.add('is-shipped');
      node.title = String(level.label || (level.percent + '% reward')) + (level.shipped ? ' — reward shipped' : '');
      levels.appendChild(node);
    });
    return levels;
  }
  function campaignCard(item) {
    var campaign = item.campaign || {};
    var authorData = item.author || {};
    var card = document.createElement('article');
    card.className = 'mg-feed-card mg-feed-campaign-card';
    card.dataset.campaignId = String(campaign.id || '');
    card.dataset.campaignKind = String(campaign.kind || '');

    var header = document.createElement('header');
    header.className = 'mg-feed-card-header mg-campaign-feed-header';
    header.appendChild(avatar(authorData));
    var identity = document.createElement('div');
    var author = document.createElement('a');
    author.href = safeUrl(authorData.url, true) || '#';
    author.textContent = String(authorData.display_name || 'Microgifter merchant');
    var meta = textNode('span', '', String(campaign.label || 'Campaign') + ' · ' + formatDate(item.published_at));
    identity.append(author, meta);
    var badge = textNode('span', 'mg-campaign-feed-state' + (campaign.reward_shipped ? ' is-shipped' : ''), campaign.reward_shipped ? 'Reward shipped' : String(campaign.status || 'Ready'));
    header.append(identity, badge);

    var main = document.createElement('div');
    main.className = 'mg-campaign-feed-main';
    main.appendChild(campaignMedia(campaign));
    var content = document.createElement('div');
    content.className = 'mg-campaign-feed-content';
    var titleLink = document.createElement('a');
    titleLink.className = 'mg-campaign-feed-title';
    titleLink.href = safeUrl(campaign.url, true) || '#';
    titleLink.textContent = String(campaign.title || 'Reward campaign');
    content.appendChild(titleLink);
    if (campaign.subtitle) content.appendChild(textNode('p', 'mg-campaign-feed-subtitle', campaign.subtitle));

    var progressTop = document.createElement('div');
    progressTop.className = 'mg-campaign-feed-progress-top';
    progressTop.append(textNode('span', '', 'Progress'), textNode('strong', '', Math.round(Number(campaign.progress_percent || 0)) + '%'));
    var track = document.createElement('div');
    track.className = 'mg-campaign-feed-progress';
    var fill = document.createElement('span');
    fill.style.width = Math.max(0, Math.min(100, Number(campaign.progress_percent || 0))) + '%';
    track.appendChild(fill);
    content.append(progressTop, track, levelRail(campaign));

    var footer = document.createElement('footer');
    footer.className = 'mg-campaign-feed-footer';
    var statusText = campaign.reward_shipped
      ? String(campaign.reward_shipped_count || 1) + ' reward' + (Number(campaign.reward_shipped_count || 1) === 1 ? '' : 's') + ' shipped' + (campaign.reward_shipped_at ? ' · ' + formatDate(campaign.reward_shipped_at) : '')
      : (campaign.next_level_percent ? 'Next reward at ' + campaign.next_level_percent + '%' : String(campaign.reward_title || 'Campaign reward'));
    footer.appendChild(textNode('span', campaign.reward_shipped ? 'is-shipped' : '', statusText));
    var cta = document.createElement('a');
    cta.className = 'mg-campaign-feed-cta';
    cta.href = safeUrl(campaign.url, true) || '#';
    cta.textContent = campaign.kind === 'listen' ? 'Listen & earn' : 'Watch & earn';
    footer.appendChild(cta);
    content.appendChild(footer);
    main.appendChild(content);
    card.append(header, main);
    return card;
  }
  function render(items) {
    list.replaceChildren();
    (Array.isArray(items) ? items : []).forEach(function (item) { list.appendChild(campaignCard(item)); });
    setHidden(list.children.length === 0);
  }
  async function load(mode) {
    currentMode = String(mode || 'discover');
    if (!['discover', 'following'].includes(currentMode)) {
      render([]);
      return;
    }
    if (requestController) requestController.abort();
    requestController = new AbortController();
    try {
      var response = await fetch('/api/public/campaign-feed.php?mode=' + encodeURIComponent(currentMode) + '&limit=4', {
        credentials: 'same-origin',
        signal: requestController.signal,
        headers: { Accept: 'application/json' }
      });
      var json = await response.json().catch(function () { return {}; });
      if (!response.ok || json.ok === false) {
        if (response.status === 401) return render([]);
        throw new Error(json.message || 'Campaign feed unavailable.');
      }
      var data = payload(json);
      render(data.campaigns && data.campaigns.items || []);
    } catch (error) {
      if (error.name !== 'AbortError') render([]);
    }
  }

  root.addEventListener('click', function (event) {
    var tab = event.target.closest('[data-feed-tab]');
    if (!tab) return;
    window.setTimeout(function () { load(tab.dataset.feedTab || 'discover'); }, 0);
  });
  window.addEventListener('popstate', function () {
    if (root.hasAttribute('data-newsfeed')) return;
    var mode = new URLSearchParams(window.location.search).get('view') || 'discover';
    load(mode);
  });

  load(currentMode);
})(window, document);
