(function(){
  'use strict';
  function ready(fn){if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',fn);}else{fn();}}
  ready(function(){
    var root=document.querySelector('[data-builder-app]');
    if(!root)return;
    function fieldValue(id,fallback){var node=root.querySelector('#'+id);var value=node?String(node.value||'').trim():'';return value||fallback;}
    function ensureCardText(){
      var headlineText=fieldValue('headline','HAPPY BIRTHDAY!');
      var messageText=fieldValue('message','Add the message the recipient will see inside the card.');
      root.querySelectorAll('.mg-card-message-copy').forEach(function(copy){
        var headline=copy.querySelector('[data-preview-card-headline]')||copy.querySelector('.mg-card-message-title')||copy.querySelector('h1,h2,h3');
        var message=copy.querySelector('[data-preview-card-message]')||copy.querySelector('.mg-card-inside-message')||copy.querySelector('p');
        if(!headline){headline=document.createElement('h3');copy.insertBefore(headline,copy.firstChild||null);}
        if(!message||message===headline){message=document.createElement('p');headline.insertAdjacentElement('afterend',message);}
        if(message.previousElementSibling!==headline){headline.insertAdjacentElement('afterend',message);}
        headline.classList.add('mg-card-message-title');
        headline.setAttribute('data-preview-card-headline','');
        headline.removeAttribute('data-preview-message');
        headline.removeAttribute('data-preview-card-message');
        message.classList.add('mg-card-inside-message');
        message.setAttribute('data-preview-card-message','');
        message.removeAttribute('data-preview-message');
        message.removeAttribute('data-preview-card-headline');
        headline.textContent=headlineText;
        message.textContent=messageText;
        headline.style.display='block';
        headline.style.fontWeight='950';
        headline.style.lineHeight='.96';
        headline.style.margin='0 0 12px';
        message.style.display='block';
        message.style.fontWeight='400';
        message.style.lineHeight='1.45';
        message.style.margin='0';
      });
    }
    ['headline','message'].forEach(function(id){var node=root.querySelector('#'+id);if(node){node.addEventListener('input',ensureCardText);node.addEventListener('change',ensureCardText);}});
    var observer=new MutationObserver(function(){window.requestAnimationFrame(ensureCardText);});
    root.querySelectorAll('.mg-card-message-copy').forEach(function(copy){observer.observe(copy,{childList:true,subtree:true});});
    ensureCardText();
    window.setTimeout(ensureCardText,50);
    window.setTimeout(ensureCardText,250);
    window.setTimeout(ensureCardText,750);
    var deadline=Date.now()+5000;
    (function watch(){ensureCardText();if(Date.now()<deadline)window.requestAnimationFrame(watch);})();
  });
})();
