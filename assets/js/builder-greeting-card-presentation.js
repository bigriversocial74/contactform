document.addEventListener('DOMContentLoaded',function(){
  'use strict';
  var root=document.querySelector('[data-builder-app]');
  if(!root)return;
  var mobileQuery=window.matchMedia('(max-width: 900px)');

  function isMobileCard(){return mobileQuery.matches;}

  function states(){return isMobileCard()?['closed','inside-image','open','back']:['closed','open','back'];}

  function setCardState(card,state){
    if(!card)return;
    card.dataset.cardState=state;
    card.classList.toggle('is-open',state==='open'||state==='inside-image');
    card.classList.toggle('is-back',state==='back');
    syncButtonLabels(card);
  }

  function syncButtonLabels(card){
    if(!card)return;
    var flip=card.querySelector('[data-card-action="flip"]');
    if(!flip)return;
    if(!isMobileCard()){
      flip.textContent=card.dataset.cardState==='back'?'Inside Card':'Flip Card';
      return;
    }
    if(card.dataset.cardState==='inside-image')flip.textContent='Message Page';
    else if(card.dataset.cardState==='open')flip.textContent='Product Info';
    else if(card.dataset.cardState==='back')flip.textContent='Inside Image';
    else flip.textContent='Next Page';
  }

  function stepCard(card,direction){
    var list=states();
    var state=card.dataset.cardState||'closed';
    var index=list.indexOf(state);
    if(index<0)index=0;
    if(state==='closed'&&direction<0){setCardState(card,list[1]);return;}
    var next=(index+direction+list.length)%list.length;
    setCardState(card,list[next]);
  }

  function nextMobileState(card){
    var state=card.dataset.cardState||'closed';
    if(state==='inside-image')return 'open';
    if(state==='open')return 'back';
    if(state==='back')return 'inside-image';
    return 'inside-image';
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
    if(action==='open')setCardState(card,isMobileCard()?'inside-image':'open');
    if(action==='close')setCardState(card,'closed');
    if(action==='flip'){
      if(isMobileCard())setCardState(card,nextMobileState(card));
      else setCardState(card,card.dataset.cardState==='back'?'open':'back');
    }
  });

  root.addEventListener('click',function(event){
    if(event.target.closest('button,a,input,textarea,select,audio,video'))return;
    var card=event.target.closest('[data-card-presenter]');
    if(!card||!root.contains(card))return;
    var rect=card.getBoundingClientRect();
    var x=event.clientX-rect.left;
    stepCard(card,x<rect.width/2?-1:1);
  });

  root.querySelectorAll('input[name="builder_type"]').forEach(function(input){
    input.addEventListener('change',function(){
      root.querySelectorAll('[data-card-presenter]').forEach(function(card){setCardState(card,'closed');});
      window.setTimeout(function(){syncPlaceholders(root);},30);
    });
  });

  if(mobileQuery.addEventListener){
    mobileQuery.addEventListener('change',function(){
      root.querySelectorAll('[data-card-presenter]').forEach(function(card){
        if(card.dataset.cardState==='inside-image'&&!isMobileCard())setCardState(card,'open');
        else syncButtonLabels(card);
      });
    });
  }

  var observer=new MutationObserver(function(records){
    records.forEach(function(record){
      if(record.type==='attributes')syncPlaceholders(record.target.closest('[data-card-presenter]')||root);
    });
  });
  root.querySelectorAll('.mg-card-cover-media,.mg-card-inside-image').forEach(function(node){
    observer.observe(node,{attributes:true,attributeFilter:['style']});
  });

  syncPlaceholders(root);
  root.querySelectorAll('[data-card-presenter]').forEach(function(card){syncButtonLabels(card);});
  var deadline=Date.now()+5000;
  (function watch(){syncPlaceholders(root);if(Date.now()<deadline)window.requestAnimationFrame(watch);})();
});
