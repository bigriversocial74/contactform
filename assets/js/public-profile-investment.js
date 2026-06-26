(function(){
  'use strict';

  var root = document.querySelector('[data-public-profile-page]');
  if (!root) return;

  var MG = window.Microgifter || {};
  var slug = root.getAttribute('data-profile-slug') || new URLSearchParams(location.search).get('slug') || '';
  var preview = root.getAttribute('data-profile-preview') === '1';
  var base = MG.publicProfileData || null;
  var invest = null;

  function qs(selector, context){ return (context || root).querySelector(selector); }
  function qsa(selector, context){ return Array.prototype.slice.call((context || root).querySelectorAll(selector)); }
  function el(tag, className, text){
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = String(text);
    return node;
  }
  function payload(response){ return response && response.data ? response.data : response || {}; }
  function normalizeText(value){
    return String(value == null ? '' : value).replace(/No Market Signal/gi, 'No Signal');
  }
  function m(value, fallback){
    var text = value && typeof value === 'object' && 'display' in value
      ? String(value.display)
      : (value == null ? String(fallback || '0') : String(value));
    return normalizeText(text);
  }
  function has(value){ return !!(value && typeof value === 'object' && value.has_data); }
  function hide(node, hidden){ if (node) node.classList.toggle('mg-hidden', !!hidden); }
  function href(value, fallback){
    try {
      if (!value) return fallback || '/profile.php';
      var url = new URL(String(value), location.origin);
      if (url.username || url.password || !['http:', 'https:'].includes(url.protocol)) return fallback || '/profile.php';
      return url.origin === location.origin ? url.pathname + url.search + url.hash : url.href;
    } catch (error) {
      return fallback || '/profile.php';
    }
  }
  function profileUrl(){ return slug ? '/profile.php?slug=' + encodeURIComponent(slug) : '/profile.php'; }
  function setField(key, value){
    qsa('[data-invest-field="' + key + '"]').forEach(function(node){
      node.textContent = m(value, '0');
      node.classList.toggle('mg-invest-no-data', value && typeof value === 'object' && !value.has_data);
    });
  }
  function status(text, type){
    var node = qs('[data-profile-button-status]');
    if (!node) return;
    node.textContent = text || '';
    node.className = 'mg-profile-action-status' + (text ? ' is-visible' : '') + (type ? ' is-' + type : '');
  }
  function uuid(prefix){
    return (prefix || 'profile-action') + ':' + (window.crypto && crypto.randomUUID ? crypto.randomUUID() : Date.now() + '-' + Math.random().toString(16).slice(2));
  }
  function signin(){ location.href = '/signin.php?return=' + encodeURIComponent(location.pathname + location.search); }
  function authed(){ return !!(MG.isAuthenticated && MG.isAuthenticated()); }

  function emptyMarkup(icon, title, body){
    return '<span class="mg-invest-empty-icon" aria-hidden="true">' + icon + '</span><strong>' + title + '</strong><small>' + body + '</small>';
  }
  function setEmpty(selector, icon, title, body){
    var node = qs(selector);
    if (node) node.innerHTML = emptyMarkup(icon, title, body);
  }
  function emptyStates(){
    setEmpty('[data-invest-chart-empty]', '↗', 'No recent pricing data', 'Ticker value updates when market activity is available.');
    setEmpty('[data-invest-demand-empty]', '〽', 'No signal yet', 'Merchant score will update after products, campaigns, followers, or redemption activity.');
    setEmpty('[data-profile-products-empty]', '🎁', 'No featured experiences', 'Published products and giftable offers will appear here.');
    setEmpty('[data-invest-campaigns-empty]', '📣', 'No active campaigns', 'Create a campaign to start reaching new customers.');
    setEmpty('[data-invest-campaigns-empty-full]', '📣', 'No campaigns yet', 'Live reward and CRM campaigns will appear here.');
    setEmpty('[data-profile-posts-empty]', '✦', 'No posts yet', 'Updates from this profile will appear here.');
    setEmpty('[data-invest-analytics-empty]', '◎', 'No analytics yet', 'Analytics will populate after products, campaigns, wallet, distribution, and profile activity.');
    setEmpty('[data-profile-plans-empty]', '★', 'No memberships yet', 'Support plans and membership options will appear here.');
    setEmpty('[data-invest-portfolio-empty]', '◌', 'No portfolio activity', 'Ticker value and ownership activity will appear as the market develops.');
    setEmpty('[data-invest-activity-empty]', '◷', 'No recent activity', 'Activity will appear here when there is movement.');
    setEmpty('[data-invest-trending-empty]', '✧', 'No trending experiences', 'Trending experiences will appear here soon.');
  }

  function moveLinks(){
    var links = qs('[data-profile-links-section]');
    var actions = qs('.mg-invest-actions');
    if (links && actions && !actions.contains(links)) {
      links.classList.add('mg-invest-social-actions');
      actions.appendChild(links);
    }
  }

  function syncProducts(){
    var source = qs('[data-profile-products-grid]');
    var target = qs('[data-profile-products-grid-clone]');
    if (!source || !target) return;
    target.replaceChildren();
    qsa(':scope > *', source).forEach(function(card){ target.appendChild(card.cloneNode(true)); });
    if (!target.children.length) {
      var empty = el('div', 'mg-invest-empty-state');
      empty.innerHTML = emptyMarkup('🎁', 'No featured experiences', 'Published products and giftable offers will appear here.');
      target.appendChild(empty);
    }
  }

  function campaigns(selector, emptySelector, items){
    var box = qs(selector);
    var empty = qs(emptySelector);
    if (!box) return;
    box.replaceChildren();
    (Array.isArray(items) ? items : []).forEach(function(item){
      var card = el('article', '');
      var title = item.url ? el('a', 'mg-invest-campaign-title', item.title || 'Campaign') : el('b', '', item.title || 'Campaign');
      if (item.url) title.href = href(item.url, '/campaign.php');
      card.append(title, el('span', '', String(item.status || 'active').toUpperCase()), el('p', '', item.description || 'No campaign description provided.'));
      if (item.progress !== null && item.progress !== undefined) {
        var progress = document.createElement('progress');
        progress.value = Math.max(0, Math.min(100, Number(item.progress) || 0));
        progress.max = 100;
        card.appendChild(progress);
      }
      card.appendChild(el('small', 'mg-invest-campaign-meta', Number(item.issued_count || 0).toLocaleString() + ' issued' + (item.quantity_limit ? ' of ' + Number(item.quantity_limit).toLocaleString() : '')));
      box.appendChild(card);
    });
    hide(empty, box.children.length > 0);
  }

  function analytics(data){
    var grid = qs('[data-invest-analytics-grid]');
    var empty = qs('[data-invest-analytics-empty]');
    var formulas = qs('[data-invest-formula-list]');
    if (grid) {
      grid.replaceChildren();
      ((data.analytics && data.analytics.items) || []).forEach(function(item){
        var card = el('div', '');
        card.classList.toggle('mg-invest-no-data', !item.has_data);
        card.append(el('strong', '', normalizeText(item.value || '0')), el('span', '', normalizeText(item.label || 'Metric')));
        if (item.detail) card.appendChild(el('small', '', normalizeText(item.detail)));
        grid.appendChild(card);
      });
      hide(empty, grid.children.length > 0 && data.analytics && data.analytics.has_data);
    }
    if (formulas) {
      formulas.replaceChildren();
      ((data.analytics && data.analytics.formulas) || []).forEach(function(text){ formulas.appendChild(el('p', '', normalizeText(text))); });
    }
  }

  function sidebar(data){
    var portfolio = data.portfolio || {};
    var portfolioValue = qs('[data-invest-portfolio-value]');
    var portfolioSubtitle = qs('[data-invest-portfolio-subtitle]');
    if (portfolioValue) portfolioValue.textContent = portfolio.has_data ? normalizeText(portfolio.value || '$0') : 'No data';
    if (portfolioSubtitle) portfolioSubtitle.textContent = portfolio.has_data ? normalizeText(portfolio.subtitle || 'Real profile value') : 'Ticker value • No Signal';
    hide(qs('[data-invest-portfolio-empty]'), !!portfolio.has_data);

    var activity = qs('[data-invest-activity-list]');
    var activityEmpty = qs('[data-invest-activity-empty]');
    if (activity) {
      activity.replaceChildren();
      ((data.activity && data.activity.items) || []).forEach(function(item){
        var row = el('p', '');
        row.append(document.createTextNode(normalizeText(item.title || 'Reward')), el('b', '', normalizeText(String(item.value || '$0') + ' ' + String(item.status || ''))));
        activity.appendChild(row);
      });
      hide(activityEmpty, activity.children.length > 0);
    }

    var trending = qs('[data-invest-trending-list]');
    var trendingEmpty = qs('[data-invest-trending-empty]');
    if (trending) {
      trending.replaceChildren();
      ((data.trending && data.trending.items) || []).forEach(function(item){
        var li = el('li', '');
        var title = item.url ? el('a', '', item.title || 'Experience') : document.createTextNode(item.title || 'Experience');
        if (title.href) title.href = href(item.url, '/discover.php');
        li.append(title, el('b', '', Number(item.score || 0).toLocaleString() + ' score'));
        trending.appendChild(li);
      });
      hide(trendingEmpty, trending.children.length > 0);
    }
  }

  function chartValue(point){
    return Number(point.ticker_value_cents !== undefined ? point.ticker_value_cents : (point.value_cents !== undefined ? point.value_cents : 0));
  }
  function chart(data){
    var svg = qs('[data-invest-market-chart]');
    var line = qs('[data-invest-chart-line]');
    var fill = qs('[data-invest-chart-fill]');
    var labels = qs('[data-invest-chart-labels]');
    var empty = qs('[data-invest-chart-empty]');
    var series = data.series || {};
    var points = Array.isArray(series.market_snapshots) && series.market_snapshots.length >= 2 ? series.market_snapshots : (Array.isArray(series.volume_30d) ? series.volume_30d : []);
    if (!svg || !line || !fill) return;
    if (points.length < 2) {
      line.setAttribute('d', '');
      fill.setAttribute('d', '');
      if (labels) labels.replaceChildren();
      hide(svg, true);
      hide(empty, false);
      return;
    }
    var max = Math.max.apply(null, points.map(chartValue));
    if (max <= 0) {
      hide(svg, true);
      hide(empty, false);
      return;
    }
    var width = 305, height = 120, left = 10, top = 18, bottom = 140;
    var path = points.map(function(point, index){
      var x = left + (index / (points.length - 1)) * width;
      var y = top + (1 - (chartValue(point) / max)) * height;
      return (index ? 'L' : 'M') + x.toFixed(1) + ' ' + y.toFixed(1);
    }).join(' ');
    line.setAttribute('d', path);
    fill.setAttribute('d', path + ' L ' + (left + width).toFixed(1) + ' ' + bottom + ' L ' + left + ' ' + bottom + ' Z');
    hide(svg, false);
    hide(empty, true);
    if (labels) {
      labels.replaceChildren();
      [points[0], points[Math.floor(points.length / 2)], points[points.length - 1]].forEach(function(point){ labels.appendChild(el('span', '', point.date || '')); });
    }
  }

  function demand(data){
    var metrics = data.metrics || {};
    var ok = has(metrics.demand_score);
    var score = Number(metrics.demand_score && metrics.demand_score.raw !== undefined ? metrics.demand_score.raw : 0);
    var ring = qs('[data-invest-demand-ring]');
    if (ring) {
      var circumference = 282.743;
      var filled = Math.max(0, Math.min(100, score)) / 100 * circumference;
      ring.style.strokeDasharray = filled.toFixed(2) + ' ' + circumference.toFixed(2);
    }
    hide(qs('[data-invest-demand-meter]'), !ok);
    hide(qs('[data-invest-demand-empty]'), ok);
    Object.keys(data.factors || {}).forEach(function(key){
      var node = qs('[data-invest-factor="' + key + '"]');
      if (node) node.textContent = String(data.factors[key]);
    });
  }

  function render(raw){
    var data = payload(raw);
    invest = data;
    emptyStates();
    var metrics = data.metrics || {};
    var profile = data.profile || {};
    setField('display_name', profile.display_name || 'Microgifter Merchant');
    setField('tagline', profile.tagline || '');
    Object.keys(metrics).forEach(function(key){ setField(key, metrics[key]); });
    campaigns('[data-invest-campaigns-list]', '[data-invest-campaigns-empty]', data.campaigns && data.campaigns.items);
    campaigns('[data-invest-campaigns-list-full]', '[data-invest-campaigns-empty-full]', data.campaigns && data.campaigns.items);
    analytics(data);
    sidebar(data);
    chart(data);
    demand(data);
    syncProducts();
    moveLinks();
    ownerTools(Boolean(base && base.profile && base.profile.availability && base.profile.availability.is_owner));
  }

  function load(){
    if (!slug) return;
    fetch('/api/public/profile-investment.php?slug=' + encodeURIComponent(slug) + (preview ? '&preview=1' : ''), {credentials:'same-origin', headers:{Accept:'application/json'}})
      .then(function(response){ return response.ok ? response.json() : null; })
      .then(function(json){
        if (!json) return;
        var data = payload(json);
        return fetch('/api/public/profile-market-series.php?slug=' + encodeURIComponent(slug) + '&days=30', {credentials:'same-origin', headers:{Accept:'application/json'}})
          .then(function(response){ return response.ok ? response.json() : null; })
          .then(function(seriesJson){
            var seriesData = payload(seriesJson);
            if (seriesData && seriesData.series && Array.isArray(seriesData.series.market_snapshots)) {
              data.series = data.series || {};
              data.series.market_snapshots = seriesData.series.market_snapshots;
            }
            render(data);
          })
          .catch(function(){ render(data); });
      })
      .catch(function(){ render({metrics:{}, campaigns:{items:[]}, analytics:{items:[]}, activity:{items:[]}, trending:{items:[]}, series:{volume_30d:[]}}); });
  }

  function tabs(){
    var tabs = qsa('[data-invest-tab]');
    var panels = qsa('[data-invest-panel]');
    if (!tabs.length) return;
    function show(name){
      if (name === 'products') syncProducts();
      tabs.forEach(function(tab){ tab.classList.toggle('is-active', tab.getAttribute('data-invest-tab') === name); });
      panels.forEach(function(panel){
        var active = panel.getAttribute('data-invest-panel') === name;
        panel.hidden = !active;
        panel.classList.toggle('is-active', active);
        if (active) panel.classList.remove('mg-hidden');
      });
    }
    tabs.forEach(function(tab){ tab.addEventListener('click', function(event){ event.preventDefault(); show(tab.getAttribute('data-invest-tab') || 'overview'); }); });
    show((qs('[data-invest-tab].is-active') || tabs[0]).getAttribute('data-invest-tab') || 'overview');
  }

  function ownerTools(isOwner){
    document.body.classList.toggle('mg-profile-owner', !!isOwner);
    var cover = qs('.mg-invest-cover-card');
    if (cover && !qs('[data-cover-adjust-tools]')) {
      var tools = el('div', 'mg-profile-owner-tools');
      tools.setAttribute('data-cover-adjust-tools', '1');
      tools.innerHTML = '<button class="mg-profile-tool-btn" type="button" data-cover-adjust-toggle>Adjust cover</button><a href="/account.php">Replace cover</a><a href="/account.php">Delete cover</a><div class="mg-cover-adjust-panel" data-cover-adjust-panel><label><span>X position</span><input type="range" min="0" max="100" value="50" data-cover-position-x></label><label><span>Y position</span><input type="range" min="0" max="100" value="50" data-cover-position-y></label><div><button class="mg-profile-tool-btn" type="button" data-cover-adjust-reset>Reset</button><button class="mg-profile-tool-btn" type="button" data-cover-adjust-save>Save view</button></div></div>';
      cover.appendChild(tools);
    }
    if (isOwner) coverControls();
  }

  function coverControls(){
    var panel = qs('[data-cover-adjust-panel]');
    if (!panel) return;
    var toggle = qs('[data-cover-adjust-toggle]');
    var reset = qs('[data-cover-adjust-reset]');
    var saveButton = qs('[data-cover-adjust-save]');
    var x = qs('[data-cover-position-x]');
    var y = qs('[data-cover-position-y]');
    var background = qs('[data-profile-cover]');
    function apply(){ if (background) background.style.backgroundPosition = String(x ? x.value : 50) + '% ' + String(y ? y.value : 50) + '%'; }
    if (toggle && !toggle.dataset.bound) {
      toggle.dataset.bound = '1';
      toggle.addEventListener('click', function(){ panel.classList.toggle('is-open'); });
    }
    if (x && !x.dataset.bound) { x.dataset.bound = '1'; x.addEventListener('input', apply); }
    if (y && !y.dataset.bound) { y.dataset.bound = '1'; y.addEventListener('input', apply); }
    if (reset && !reset.dataset.bound) {
      reset.dataset.bound = '1';
      reset.addEventListener('click', function(){ if (x) x.value = 50; if (y) y.value = 50; apply(); });
    }
    if (saveButton && !saveButton.dataset.bound) {
      saveButton.dataset.bound = '1';
      saveButton.addEventListener('click', function(){
        var token = document.querySelector('meta[name="csrf-token"]');
        fetch('/api/profiles/cover-position.php', {
          method:'POST',
          credentials:'same-origin',
          headers:{'Content-Type':'application/json','Accept':'application/json'},
          body:JSON.stringify({slug:slug, x:x ? Number(x.value) : 50, y:y ? Number(y.value) : 50, csrf_token:token ? token.content : ''})
        }).catch(function(){});
      });
    }
    apply();
  }

  function share(){
    var url = location.origin + href(invest && invest.actions && invest.actions.share_url, profileUrl());
    var title = (base && base.profile && base.profile.display_name) || (invest && invest.profile && invest.profile.display_name) || 'Microgifter profile';
    if (navigator.share) {
      navigator.share({title:title, url:url}).then(function(){ status('Profile shared.', 'success'); }).catch(function(){});
      return;
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(url).then(function(){ status('Profile link copied.', 'success'); }).catch(function(){ status(url, 'success'); });
    } else {
      status(url, 'success');
    }
  }

  function message(){
    var actions = invest && invest.actions || {};
    if (actions.message_enabled && actions.message_url) {
      location.href = href(actions.message_url, profileUrl());
      return;
    }
    status('Messaging is not available for this profile yet.', 'error');
  }

  async function save(button){
    var data = base || MG.publicProfileData || {};
    var profileId = String(data && data.profile && data.profile.id || '');
    var owner = !!(data && data.profile && data.profile.availability && data.profile.availability.is_owner);
    if (owner) { status('This is your profile.', 'success'); return; }
    if (!profileId) { status('Profile is still loading.', 'error'); return; }
    if (!authed()) return void signin();
    if (MG.setBusy) MG.setBusy(button, true, 'Saving…');
    try {
      var response = payload(await MG.post('/api/social/relationship.php', {action:'follow', profile_id:profileId, idempotency_key:uuid('profile-save')}));
      status(response && response.relationship && response.relationship.following ? 'Profile saved to your followed merchants.' : 'Profile saved.', 'success');
    } catch (error) {
      status(error && error.message ? error.message : 'Unable to save this profile.', 'error');
    } finally {
      if (MG.setBusy) MG.setBusy(button, false);
    }
  }

  document.addEventListener('mg:public-profile:links-rendered', moveLinks);
  document.addEventListener('mg:public-profile:data', function(event){
    base = event.detail || {};
    ownerTools(Boolean(base.profile && base.profile.availability && base.profile.availability.is_owner));
  });
  root.addEventListener('click', function(event){
    var deleteButton = event.target.closest('[data-profile-avatar-delete]');
    if (deleteButton) {
      event.preventDefault();
      var avatar = qs('[data-profile-avatar]');
      var fallback = qs('[data-profile-avatar-fallback]');
      if (avatar) { avatar.removeAttribute('src'); avatar.classList.add('mg-hidden'); }
      if (fallback) fallback.classList.remove('mg-hidden');
      deleteButton.textContent = 'Open editor to save';
      setTimeout(function(){ location.href = '/account.php'; }, 450);
      return;
    }
    var shareButton = event.target.closest('[data-profile-share]');
    if (shareButton) { event.preventDefault(); share(); return; }
    var messageButton = event.target.closest('[data-profile-message]');
    if (messageButton) { event.preventDefault(); message(); return; }
    var saveButton = event.target.closest('[data-profile-save]');
    if (saveButton) { event.preventDefault(); save(saveButton); }
  });

  emptyStates();
  moveLinks();
  tabs();
  var source = qs('[data-profile-products-grid]');
  if (source && MutationObserver) new MutationObserver(syncProducts).observe(source, {childList:true, subtree:false});
  coverControls();
  load();
})();
