document.addEventListener('DOMContentLoaded',function(){
  'use strict';
  var root=document.querySelector('[data-builder-app]');
  if(!root)return;

  function setCardState(card,state){
    if(!card)return;
    card.dataset.cardState=state;
    card.classList.toggle('is-open',state==='open');
    card.classList.toggle('is-back',state==='back');
  }

  function hasImage(node){
    return !!(node&&node.style&&node.style.backgroundImage&&node.style.backgroundImage!=='none');
  }

  function syncPlaceholders(scope){
    (scope||root).querySelectorAll('.mg-card-cover-media,.mg-card-inside-image').forEach(function(node){
      node.classList.toggle('is-empty',!hasImage(node));
    });
  }

  root.addEventListener('click',function(event){
    var button=event.target.closest('[data-card-action]');
    if(!button)return;
    var card=button.closest('[data-card-presenter]');
    if(!card)return;
    event.preventDefault();
    var action=button.dataset.cardAction;
    if(action==='open')setCardState(card,'open');
    if(action==='close')setCardState(card,'closed');
    if(action==='flip')setCardState(card,card.dataset.cardState==='back'?'open':'back');
  });

  root.querySelectorAll('input[name="builder_type"]').forEach(function(input){
    input.addEventListener('change',function(){
      root.querySelectorAll('[data-card-presenter]').forEach(function(card){setCardState(card,'closed');});
      window.setTimeout(function(){syncPlaceholders(root);},30);
    });
  });

  var observer=new MutationObserver(function(records){
    records.forEach(function(record){
      if(record.type==='attributes')syncPlaceholders(record.target.closest('[data-card-presenter]')||root);
    });
  });
  root.querySelectorAll('.mg-card-cover-media,.mg-card-inside-image').forEach(function(node){
    observer.observe(node,{attributes:true,attributeFilter:['style']});
  });

  syncPlaceholders(root);
  var deadline=Date.now()+5000;
  (function watch(){syncPlaceholders(root);if(Date.now()<deadline)window.requestAnimationFrame(watch);})();
});
