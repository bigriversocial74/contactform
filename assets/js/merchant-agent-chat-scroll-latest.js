document.addEventListener('DOMContentLoaded',function(){
  'use strict';
  var root=document.querySelector('[data-merchant-agent-chat]');
  if(!root)return;
  var feed=root.querySelector('[data-agent-chat-feed]');
  if(!feed)return;
  var didInitialScroll=false;

  function latestMessage(){
    var messages=feed.querySelectorAll('.mg-agent-chat-message');
    return messages.length?messages[messages.length-1]:null;
  }

  function scrollLatest(force){
    var message=latestMessage();
    if(!message)return;
    feed.scrollTop=feed.scrollHeight;
    if(force||!didInitialScroll){
      didInitialScroll=true;
      message.scrollIntoView({block:'end',inline:'nearest',behavior:'auto'});
      if(window.scrollY>0){
        window.scrollBy(0,64);
      }
    }
  }

  var timer=null;
  function schedule(force){
    window.clearTimeout(timer);
    timer=window.setTimeout(function(){scrollLatest(!!force);},80);
  }

  new MutationObserver(function(){schedule(false);}).observe(feed,{childList:true,subtree:false});
  schedule(true);
  window.setTimeout(function(){scrollLatest(true);},350);
  window.setTimeout(function(){scrollLatest(true);},900);
});
