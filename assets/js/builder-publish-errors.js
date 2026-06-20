document.addEventListener('DOMContentLoaded',function(){
  'use strict';
  var root=document.querySelector('[data-builder-app]');
  if(!root)return;
  var status=root.querySelector('[data-builder-status]');
  var toast=root.querySelector('[data-builder-toast]');
  if(!status||!toast)return;

  function preservePublishError(){
    var message=String(toast.textContent||'').trim();
    var statusText=String(status.textContent||'').trim().toLowerCase();
    if(!message||(!statusText.startsWith('publish failed')&&!statusText.startsWith('publish needs attention')))return;
    status.textContent='Publish failed: '+message;
    status.classList.remove('is-saving');
    status.classList.add('is-error');
  }

  var observer=new MutationObserver(preservePublishError);
  observer.observe(toast,{childList:true,characterData:true,subtree:true,attributes:true,attributeFilter:['class']});
});
