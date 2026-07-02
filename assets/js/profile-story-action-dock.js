// PR #716 action dock remains intentionally disabled.
// This file now carries only the safe PR #715 layer: prevent the static Stories
// empty state from showing on top of rendered story highlights.
(function(){
  'use strict';

  function storyPanel(){
    return document.querySelector('[data-invest-panel="stories"]');
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

  function scheduleSync(){
    if(scheduleSync.pending)return;
    scheduleSync.pending = true;
    window.requestAnimationFrame(function(){
      scheduleSync.pending = false;
      syncStoriesEmptyState();
    });
  }

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
