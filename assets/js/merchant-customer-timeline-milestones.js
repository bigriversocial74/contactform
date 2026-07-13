document.addEventListener('DOMContentLoaded',function(){
  'use strict';
  var root=document.querySelector('[data-customer-profile-page]');
  if(!root)return;

  var heartbeatTitles={
    'watch reward progress':true,
    'watch video reward progress':true,
    'listen reward progress':true,
    'listen music reward progress':true
  };

  function normalizedTitle(row){
    var title=row.querySelector('strong');
    return title?String(title.textContent||'').trim().toLowerCase().replace(/\s+/g,' '):'';
  }

  function emptyItem(){
    var item=document.createElement('li');
    item.dataset.mediaMilestoneEmpty='true';
    item.innerHTML='<span class="is-blue">•</span><div><strong>No reward milestones yet</strong><p>Playback heartbeat updates are not shown. Earned reward levels, claims, and redemptions will appear here.</p></div>';
    return item;
  }

  function compact(list){
    if(!list)return;
    list.querySelectorAll('li').forEach(function(row){
      if(heartbeatTitles[normalizedTitle(row)])row.remove();
    });
    if(!list.querySelector('li'))list.appendChild(emptyItem());
  }

  function compactAll(){
    compact(root.querySelector('[data-cp-timeline]'));
    compact(root.querySelector('[data-cp-timeline-full]'));
  }

  compactAll();
  if('MutationObserver' in window){
    var observer=new MutationObserver(compactAll);
    ['[data-cp-timeline]','[data-cp-timeline-full]'].forEach(function(selector){
      var list=root.querySelector(selector);
      if(list)observer.observe(list,{childList:true});
    });
  }
});
