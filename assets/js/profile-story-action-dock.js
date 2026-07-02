// Safe story layers:
// - prevent the static Stories empty state from showing on rendered highlights
// - launch highlighted stories in an isolated viewer instead of navigating to the attached product
// - show story viewer actions as a compact circular dock
(function(){
  'use strict';

  var root = document.querySelector('[data-public-profile-page]');
  var slug = root ? (root.getAttribute('data-profile-slug') || '') : '';
  if(!slug){
    try{slug = new URLSearchParams(window.location.search).get('slug') || '';}catch(error){}
  }
  var storiesLoaded = false;
  var storiesLoading = null;
  var stories = [];
  var storyMap = new Map();
  var activeIndex = -1;
  var viewer = null;
  var canManageHighlights = false;

  function storyPanel(){
    return document.querySelector('[data-invest-panel="stories"]');
  }

  function payload(response){
    return response && response.data ? response.data : response;
  }

  function safeUrl(value){
    try{
      if(!value)return null;
      var url = new URL(String(value), window.location.origin);
      if(url.protocol !== 'http:' && url.protocol !== 'https:')return null;
      if(url.username || url.password)return null;
      return url.origin === window.location.origin ? url.pathname + url.search + url.hash : url.href;
    }catch(error){
      return null;
    }
  }

  function text(value, fallback){
    var next = String(value || '').replace(/\s+/g, ' ').trim();
    return next || fallback || '';
  }

  function label(value){
    return String(value || 'story').replace(/[_-]+/g, ' ').replace(/\b\w/g, function(letter){return letter.toUpperCase();});
  }

  function formatDate(value){
    if(!value)return '';
    var raw = String(value);
    var parsed = new Date(raw.replace(' ', 'T') + (raw.indexOf('T') === -1 ? 'Z' : ''));
    if(Number.isNaN(parsed.getTime()))return raw;
    return new Intl.DateTimeFormat(undefined, {dateStyle:'medium'}).format(parsed);
  }

  function isStaticStoriesEmpty(node){
    if(!node || !node.classList || !node.classList.contains('mg-invest-empty-state'))return false;
    if(node.hasAttribute('data-profile-story-highlights-empty'))return false;
    return String(node.textContent || '').replace(/\s+/g,' ').trim().toLowerCase() === 'no stories available yet.';
  }

  function syncStoriesEmptyState(){
    var panel = storyPanel();
    if(!panel)return;
    var renderedHighlights = panel.querySelector('[data-profile-story-highlights]');
    var renderedEmpty = panel.querySelector('[data-profile-story-highlights-empty]');
    var hasRenderedStoryUi = !!(renderedHighlights || renderedEmpty);
    var hasHighlights = !!(renderedHighlights && renderedHighlights.children && renderedHighlights.children.length);
    var empties = Array.prototype.slice.call(panel.querySelectorAll('.mg-invest-empty-state')).filter(isStaticStoriesEmpty);
    empties.forEach(function(empty){
      empty.classList.toggle('mg-hidden', hasRenderedStoryUi || hasHighlights);
      empty.setAttribute('aria-hidden', (hasRenderedStoryUi || hasHighlights) ? 'true' : 'false');
    });
  }

  function installViewerStyles(){
    if(document.getElementById('mg-profile-story-viewer-style'))return;
    var style = document.createElement('style');
    style.id = 'mg-profile-story-viewer-style';
    style.textContent = '.mg-profile-story-viewer{position:fixed;inset:0;z-index:10050;display:flex;align-items:center;justify-content:center;padding:22px;background:rgba(2,6,23,.74);backdrop-filter:blur(12px)}.mg-profile-story-viewer[hidden]{display:none}.mg-profile-story-viewer-shell{position:relative;width:min(440px,94vw);max-height:92vh;border-radius:30px;overflow:hidden;background:#0f172a;color:#fff;box-shadow:0 30px 90px rgba(2,6,23,.42)}.mg-profile-story-viewer-media{position:relative;min-height:560px;background:#020617}.mg-profile-story-viewer-media img,.mg-profile-story-viewer-media video{display:block;width:100%;height:min(72vh,640px);min-height:440px;object-fit:cover;background:#020617}.mg-profile-story-viewer-close,.mg-profile-story-viewer-nav{position:absolute;z-index:4;border:0;border-radius:999px;background:rgba(15,23,42,.68);color:#fff;box-shadow:0 10px 24px rgba(2,6,23,.24);cursor:pointer}.mg-profile-story-viewer-close{top:14px;right:14px;width:42px;height:42px;font-size:24px;line-height:1}.mg-profile-story-viewer-nav{top:48%;width:42px;height:42px;font-size:26px}.mg-profile-story-viewer-prev{left:12px}.mg-profile-story-viewer-next{right:12px}.mg-profile-story-viewer-copy{position:absolute;z-index:3;left:0;right:0;bottom:0;padding:80px 20px 92px;background:linear-gradient(180deg,rgba(15,23,42,0),rgba(15,23,42,.92) 58%,#0f172a)}.mg-profile-story-viewer-copy span{display:inline-flex;margin-bottom:8px;padding:5px 10px;border-radius:999px;background:rgba(255,255,255,.16);font-size:.72rem;font-weight:900;text-transform:uppercase;letter-spacing:.08em}.mg-profile-story-viewer-copy h3{margin:0;font-size:1.35rem;line-height:1.08}.mg-profile-story-viewer-copy p{margin:8px 0 0;color:rgba(255,255,255,.82);font-weight:750;line-height:1.38}.mg-profile-story-viewer-copy small{display:block;margin-top:8px;color:rgba(255,255,255,.6);font-weight:800}.mg-profile-story-viewer-actions{position:absolute;z-index:6;left:0;right:0;bottom:20px;display:flex;align-items:center;justify-content:center;gap:12px;padding:0 18px;pointer-events:none}.mg-profile-story-action{display:inline-flex;align-items:center;justify-content:center;width:50px;height:50px;border:1px solid rgba(255,255,255,.24);border-radius:999px;background:rgba(255,255,255,.92);color:#0f172a;text-decoration:none;font-size:20px;font-weight:950;box-shadow:0 16px 34px rgba(2,6,23,.3);cursor:pointer;pointer-events:auto}.mg-profile-story-action:hover,.mg-profile-story-action:focus-visible{background:#fff;transform:translateY(-1px);outline:2px solid rgba(255,255,255,.72);outline-offset:2px}.mg-profile-story-action.is-muted{background:rgba(15,23,42,.68);color:#fff}.mg-profile-story-action span{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}.mg-profile-story-highlight-card{cursor:pointer}@media(max-width:560px){.mg-profile-story-viewer{padding:0}.mg-profile-story-viewer-shell{width:100vw;max-height:100vh;height:100vh;border-radius:0}.mg-profile-story-viewer-media img,.mg-profile-story-viewer-media video{height:100vh;min-height:100vh}.mg-profile-story-viewer-nav{display:none}.mg-profile-story-viewer-actions{bottom:24px}.mg-profile-story-viewer-copy{padding-bottom:104px}}';
    document.head.appendChild(style);
  }

  function ensureViewer(){
    if(viewer)return viewer;
    installViewerStyles();
    viewer = document.createElement('div');
    viewer.className = 'mg-profile-story-viewer';
    viewer.hidden = true;
    viewer.setAttribute('role', 'dialog');
    viewer.setAttribute('aria-modal', 'true');
    viewer.setAttribute('aria-label', 'Story viewer');
    viewer.innerHTML = '<div class="mg-profile-story-viewer-shell"><div class="mg-profile-story-viewer-media" data-story-viewer-media></div><button type="button" class="mg-profile-story-viewer-close" data-story-viewer-close aria-label="Close story">×</button><button type="button" class="mg-profile-story-viewer-nav mg-profile-story-viewer-prev" data-story-viewer-prev aria-label="Previous story">‹</button><button type="button" class="mg-profile-story-viewer-nav mg-profile-story-viewer-next" data-story-viewer-next aria-label="Next story">›</button><div class="mg-profile-story-viewer-copy"><span data-story-viewer-type></span><h3 data-story-viewer-title></h3><p data-story-viewer-caption></p><small data-story-viewer-date></small></div><div class="mg-profile-story-viewer-actions" data-story-viewer-actions></div></div>';
    document.body.appendChild(viewer);
    viewer.addEventListener('click', function(event){
      if(event.target === viewer || event.target.closest('[data-story-viewer-close]'))closeViewer();
      if(event.target.closest('[data-story-viewer-prev]'))showStory(activeIndex - 1);
      if(event.target.closest('[data-story-viewer-next]'))showStory(activeIndex + 1);
      if(event.target.closest('[data-story-viewer-analytics]'))openProfileTab('analytics');
      if(event.target.closest('[data-story-viewer-stories]'))openProfileTab('stories');
    });
    document.addEventListener('keydown', function(event){
      if(!viewer || viewer.hidden)return;
      if(event.key === 'Escape')closeViewer();
      if(event.key === 'ArrowLeft')showStory(activeIndex - 1);
      if(event.key === 'ArrowRight')showStory(activeIndex + 1);
    });
    return viewer;
  }

  function makeAction(tag, icon, labelText, attrs){
    var el = document.createElement(tag || 'button');
    el.className = 'mg-profile-story-action';
    if(tag !== 'a')el.type = 'button';
    el.setAttribute('aria-label', labelText);
    el.title = labelText;
    el.innerHTML = icon + '<span>' + labelText + '</span>';
    Object.keys(attrs || {}).forEach(function(name){
      if(attrs[name] === false || attrs[name] === null || typeof attrs[name] === 'undefined')return;
      el.setAttribute(name, String(attrs[name]));
    });
    return el;
  }

  function openProfileTab(tab){
    closeViewer();
    var button = document.querySelector('[data-invest-tab="' + tab + '"]');
    if(button)button.click();
    var panel = document.querySelector('[data-invest-panel="' + tab + '"]');
    if(panel)panel.scrollIntoView({behavior:'smooth', block:'start'});
  }

  function closeViewer(){
    if(!viewer)return;
    var media = viewer.querySelector('[data-story-viewer-media]');
    if(media)media.replaceChildren();
    viewer.hidden = true;
    document.body.style.overflow = '';
  }

  function renderViewer(item){
    if(!item)return;
    var modal = ensureViewer();
    var media = modal.querySelector('[data-story-viewer-media]');
    var mediaUrl = safeUrl(item.media_url);
    var thumb = safeUrl(item.thumbnail_url) || mediaUrl;
    media.replaceChildren();
    if(String(item.media_type || '').toLowerCase() === 'video'){
      var video = document.createElement('video');
      video.src = mediaUrl || '';
      video.poster = thumb || '';
      video.controls = true;
      video.autoplay = true;
      video.playsInline = true;
      media.appendChild(video);
    }else{
      var image = document.createElement('img');
      image.src = thumb || mediaUrl || '';
      image.alt = text(item.title || item.caption, 'Story');
      media.appendChild(image);
    }
    modal.querySelector('[data-story-viewer-type]').textContent = label(item.story_type || 'story');
    modal.querySelector('[data-story-viewer-title]').textContent = text(item.title || item.caption, 'Story Highlight');
    var caption = modal.querySelector('[data-story-viewer-caption]');
    caption.textContent = text(item.caption, '');
    caption.hidden = !caption.textContent;
    modal.querySelector('[data-story-viewer-date]').textContent = formatDate(item.highlighted_at || item.created_at);
    var actions = modal.querySelector('[data-story-viewer-actions]');
    actions.replaceChildren();
    var cta = safeUrl(item.cta_url);
    if(cta){
      actions.appendChild(makeAction('a', '🛍️', text(item.cta_label, 'View Product'), {href:cta}));
    }
    actions.appendChild(makeAction('button', '📊', 'Analytics', {'data-story-viewer-analytics':'1', 'class':'mg-profile-story-action is-muted'}));
    actions.appendChild(makeAction('button', '◎', 'Stories', {'data-story-viewer-stories':'1', 'class':'mg-profile-story-action is-muted'}));
    if(canManageHighlights && item.story_id){
      actions.appendChild(makeAction('a', '📣', 'Promote Story', {href:'/merchant-ad-manager.php?story_id=' + encodeURIComponent(String(item.story_id))}));
    }
    modal.querySelector('[data-story-viewer-prev]').hidden = stories.length < 2;
    modal.querySelector('[data-story-viewer-next]').hidden = stories.length < 2;
    modal.hidden = false;
    document.body.style.overflow = 'hidden';
  }

  function fallbackItemFromCard(card){
    var media = card.querySelector('video,img');
    var copy = card.querySelector('.mg-profile-story-highlight-copy');
    return {
      id: card.dataset.highlightId || '',
      story_id: '',
      title: text(copy && copy.querySelector('strong') && copy.querySelector('strong').textContent, 'Story Highlight'),
      caption: '',
      story_type: text(copy && copy.querySelector('span') && copy.querySelector('span').textContent, 'story'),
      media_type: media && media.tagName === 'VIDEO' ? 'video' : 'image',
      media_url: media ? (media.currentSrc || media.src) : '',
      thumbnail_url: media && media.tagName === 'IMG' ? media.src : (media ? media.poster : ''),
      highlighted_at: '',
      created_at: '',
      cta_label: '',
      cta_url: ''
    };
  }

  function indexStories(items){
    stories = Array.isArray(items) ? items : [];
    storyMap = new Map();
    stories.forEach(function(item, index){
      item.__index = index;
      storyMap.set(String(item.id || ''), item);
    });
  }

  function loadStories(force){
    if(!slug)return Promise.resolve([]);
    if(storiesLoaded && !force)return Promise.resolve(stories);
    if(storiesLoading && !force)return storiesLoading;
    storiesLoading = fetch('/api/public/profile-stories.php?slug=' + encodeURIComponent(slug), {credentials:'same-origin', headers:{Accept:'application/json'}})
      .then(function(response){return response.ok ? response.json() : null;})
      .then(function(response){
        var data = payload(response) || {};
        canManageHighlights = !!(data.permissions && data.permissions.can_manage);
        indexStories(Array.isArray(data.highlights) ? data.highlights : []);
        storiesLoaded = true;
        return stories;
      })
      .catch(function(){return stories;})
      .finally(function(){storiesLoading = null;});
    return storiesLoading;
  }

  function showStory(index){
    if(!stories.length)return;
    if(index < 0)index = stories.length - 1;
    if(index >= stories.length)index = 0;
    activeIndex = index;
    renderViewer(stories[activeIndex]);
  }

  function openStoryFromCard(card){
    var highlightId = String(card.dataset.highlightId || '');
    var fromMap = storyMap.get(highlightId);
    if(fromMap){
      activeIndex = Number.isFinite(fromMap.__index) ? fromMap.__index : Math.max(0, Number(card.dataset.highlightIndex || 0));
      renderViewer(fromMap);
      return;
    }
    loadStories(false).then(function(){
      var item = storyMap.get(highlightId);
      if(item){
        activeIndex = Number.isFinite(item.__index) ? item.__index : Math.max(0, Number(card.dataset.highlightIndex || 0));
        renderViewer(item);
      }else{
        activeIndex = Math.max(0, Number(card.dataset.highlightIndex || 0));
        renderViewer(fallbackItemFromCard(card));
      }
    });
  }

  function scheduleSync(){
    if(scheduleSync.pending)return;
    scheduleSync.pending = true;
    window.requestAnimationFrame(function(){
      scheduleSync.pending = false;
      syncStoriesEmptyState();
      loadStories(false);
    });
  }

  document.addEventListener('click', function(event){
    var card = event.target && event.target.closest ? event.target.closest('.mg-profile-story-highlight-card[data-highlight-id]') : null;
    var panel = storyPanel();
    if(!card || !panel || !panel.contains(card))return;
    if(event.target.closest('[data-highlight-move],[data-highlight-rename],[data-highlight-remove],[data-highlight-manage],button,a'))return;
    if(event.target.closest('video[controls]'))return;
    event.preventDefault();
    event.stopImmediatePropagation();
    openStoryFromCard(card);
  }, true);

  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', scheduleSync, {once:true});
  }else{
    scheduleSync();
  }

  document.addEventListener('mg:public-profile:data', scheduleSync);
  document.addEventListener('mg:public-profile:links-rendered', scheduleSync);

  var observer = new MutationObserver(scheduleSync);
  observer.observe(document.documentElement, {subtree:true, childList:true});
})();
