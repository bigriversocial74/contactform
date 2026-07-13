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
  function normalizeText(value){ return String(value == null ? '' : value).replace(/No Market Signal/gi, 'No Signal'); }
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
      node.textContent = normalizeText(value && typeof value === 'object' && 'display' in value ? value.display : value || '');
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
    var wrap = el('span', 'mg-invest-empty-icon', icon);
    var strong = el('strong', '', title);
    var small = el('small', '', body);
    var fragment = document.createDocumentFragment();
    fragment.append(wrap, strong, small);
    return fragment;
  }

  function setEmpty(selector, icon, title, body){
    var node = qs(selector);
    if (!node) return;
    node.replaceChildren(emptyMarkup(icon, title, body));
  }

  function emptyStates(){
    setEmpty('[data-profile-products-empty]', '🎁', 'No featured experiences', 'Published products and giftable offers will appear here.');
    setEmpty('[data-invest-campaigns-empty]', '✦', 'No active campaigns', 'Campaigns will appear here when this merchant launches one.');
    setEmpty('[data-invest-campaigns-empty-full]', '✦', 'No campaigns yet', 'Live reward and participation campaigns will appear here.');
    setEmpty('[data-profile-posts-empty]', '✦', 'No posts yet', 'Updates from this profile will appear here.');
    setEmpty('[data-profile-plans-empty]', '★', 'No memberships yet', 'Support plans and membership options will appear here.');
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
      empty.appendChild(emptyMarkup('🎁', 'No featured experiences', 'Published products and giftable offers will appear here.'));
      target.appendChild(empty);
    }
  }

  function campaignIcon(item){
    var value = String((item && (item.campaign_type || item.type || item.title)) || '').toLowerCase();
    if (value.includes('scratch') || value.includes('contest')) return '✦';
    if (value.includes('video') || value.includes('watch')) return '▶';
    if (value.includes('scan') || value.includes('qr')) return '⌗';
    if (value.includes('listen') || value.includes('audio') || value.includes('music')) return '♫';
    if (value.includes('stamp') || value.includes('check')) return '◎';
    if (value.includes('newsletter') || value.includes('email') || value.includes('signup')) return '✉';
    if (value.includes('referral')) return '↗';
    return '•';
  }

  function campaigns(selector, emptySelector, items){
    var box = qs(selector);
    var empty = qs(emptySelector);
    if (!box) return;
    box.replaceChildren();

    (Array.isArray(items) ? items : []).forEach(function(item){
      var card = el('article', 'mg-profile-campaign-card');
      var icon = el('span', 'mg-profile-campaign-icon', campaignIcon(item));
      icon.setAttribute('aria-hidden', 'true');

      var copy = el('div', 'mg-profile-campaign-copy');
      var title = item.url
        ? el('a', 'mg-profile-campaign-title', item.title || 'Campaign')
        : el('strong', 'mg-profile-campaign-title', item.title || 'Campaign');
      if (item.url) title.href = href(item.url, '/campaign.php');
      copy.append(title, el('p', '', item.description || 'Open this campaign to learn more.'));

      var chevron = item.url ? el('a', 'mg-profile-campaign-chevron', '›') : el('span', 'mg-profile-campaign-chevron', '›');
      if (item.url) {
        chevron.href = href(item.url, '/campaign.php');
        chevron.setAttribute('aria-label', 'Open ' + String(item.title || 'campaign'));
      } else {
        chevron.setAttribute('aria-hidden', 'true');
      }

      card.append(icon, copy, chevron);
      box.appendChild(card);
    });

    hide(empty, box.children.length > 0);
  }

  function render(raw){
    var data = payload(raw);
    invest = data;
    emptyStates();

    var profile = data.profile || {};
    if (profile.display_name) setField('display_name', profile.display_name);
    if (profile.tagline) setField('tagline', profile.tagline);

    campaigns('[data-invest-campaigns-list]', '[data-invest-campaigns-empty]', data.campaigns && data.campaigns.items);
    campaigns('[data-invest-campaigns-list-full]', '[data-invest-campaigns-empty-full]', data.campaigns && data.campaigns.items);
    syncProducts();
    moveLinks();
    ownerTools(Boolean(base && base.profile && base.profile.availability && base.profile.availability.is_owner));
  }

  function load(){
    if (!slug) return;
    fetch('/api/public/profile-investment.php?slug=' + encodeURIComponent(slug) + (preview ? '&preview=1' : ''), {
      credentials:'same-origin',
      headers:{Accept:'application/json'}
    })
      .then(function(response){ return response.ok ? response.json() : null; })
      .then(function(json){
        if (json) render(payload(json));
        else render({profile:{}, campaigns:{items:[]}});
      })
      .catch(function(){ render({profile:{}, campaigns:{items:[]}}); });
  }

  function tabs(){
    var tabs = qsa('[data-invest-tab]');
    var panels = qsa('[data-invest-panel]');
    if (!tabs.length) return;

    function show(name){
      if (name === 'products') syncProducts();
      tabs.forEach(function(tab){
        var active = tab.getAttribute('data-invest-tab') === name;
        tab.classList.toggle('is-active', active);
        tab.setAttribute('aria-selected', active ? 'true' : 'false');
      });
      panels.forEach(function(panel){
        var active = panel.getAttribute('data-invest-panel') === name;
        panel.hidden = !active;
        panel.classList.toggle('is-active', active);
        if (active) panel.classList.remove('mg-hidden');
      });
    }

    tabs.forEach(function(tab){
      tab.setAttribute('role', 'tab');
      tab.addEventListener('click', function(event){
        event.preventDefault();
        show(tab.getAttribute('data-invest-tab') || 'overview');
      });
    });

    show((qs('[data-invest-tab].is-active') || tabs[0]).getAttribute('data-invest-tab') || 'overview');
  }

  function ownerTools(isOwner){
    document.body.classList.toggle('mg-profile-owner', !!isOwner);
    var cover = qs('.mg-invest-cover-card');
    if (cover && !qs('[data-cover-adjust-tools]')) {
      var tools = el('div', 'mg-profile-owner-tools');
      tools.setAttribute('data-cover-adjust-tools', '1');

      var toggle = el('button', 'mg-profile-tool-btn', 'Adjust cover');
      toggle.type = 'button';
      toggle.setAttribute('data-cover-adjust-toggle', '');

      var replace = el('a', '', 'Replace cover');
      replace.href = '/account.php';
      var remove = el('a', '', 'Delete cover');
      remove.href = '/account.php';

      var panel = el('div', 'mg-cover-adjust-panel');
      panel.setAttribute('data-cover-adjust-panel', '');
      var xLabel = el('label', '');
      xLabel.append(el('span', '', 'X position'));
      var x = document.createElement('input');
      x.type = 'range'; x.min = '0'; x.max = '100'; x.value = '50'; x.setAttribute('data-cover-position-x', '');
      xLabel.append(x);
      var yLabel = el('label', '');
      yLabel.append(el('span', '', 'Y position'));
      var y = document.createElement('input');
      y.type = 'range'; y.min = '0'; y.max = '100'; y.value = '50'; y.setAttribute('data-cover-position-y', '');
      yLabel.append(y);
      var controls = el('div', '');
      var reset = el('button', 'mg-profile-tool-btn', 'Reset');
      reset.type = 'button'; reset.setAttribute('data-cover-adjust-reset', '');
      var save = el('button', 'mg-profile-tool-btn', 'Save view');
      save.type = 'button'; save.setAttribute('data-cover-adjust-save', '');
      controls.append(reset, save);
      panel.append(xLabel, yLabel, controls);
      tools.append(toggle, replace, remove, panel);
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

    function apply(){
      if (background) background.style.backgroundPosition = String(x ? x.value : 50) + '% ' + String(y ? y.value : 50) + '%';
    }

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
          body:JSON.stringify({
            slug:slug,
            x:x ? Number(x.value) : 50,
            y:y ? Number(y.value) : 50,
            csrf_token:token ? token.content : ''
          })
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
      var response = payload(await MG.post('/api/social/relationship.php', {
        action:'follow',
        profile_id:profileId,
        idempotency_key:uuid('profile-save')
      }));
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
  if (source && window.MutationObserver) new MutationObserver(syncProducts).observe(source, {childList:true, subtree:false});
  coverControls();
  load();
})();