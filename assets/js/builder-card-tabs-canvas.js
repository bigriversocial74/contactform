document.addEventListener('DOMContentLoaded',function(){
  'use strict';
  var root=document.querySelector('[data-builder-app]');
  if(!root)return;
  var typeLabels={simple_product:'Simple product',greeting_card:'Greeting card',multimedia_greeting_card:'Multimedia greeting card',simple_collab:'Simple collab'};
  function selectedType(){var input=root.querySelector('input[name="builder_type"]:checked');return input?input.value:'simple_product';}
  function isCardType(type){return type==='greeting_card'||type==='multimedia_greeting_card';}
  function stepAllowed(step,type){if(step==='product'||step==='publish')return true;if(step==='gift')return isCardType(type)||type==='simple_collab';if(step==='media')return isCardType(type);return true;}
  function labelStep(button,label){var span=button.querySelector('span');button.innerHTML=(span?span.outerHTML:'')+label;}
  function activateStep(step){
    var target=root.querySelector('[data-builder-step="'+step+'"]');
    if(!target||target.hidden)target=root.querySelector('[data-builder-step="product"]');
    if(!target)return;
    var panel=target.dataset.builderStep;
    root.querySelectorAll('[data-builder-step]').forEach(function(btn){btn.classList.toggle('is-active',btn===target);});
    root.querySelectorAll('[data-builder-panel]').forEach(function(item){item.classList.toggle('is-active',item.dataset.builderPanel===panel);});
  }
  function slugify(value){return String(value||'').toLowerCase().trim().replace(/&/g,' and ').replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'').slice(0,90)||'microgift-product';}
  function syncSlug(){var title=root.querySelector('#productTitle');var merchant=root.querySelector('#merchantName');var slug=root.querySelector('#slug');if(!slug)return;slug.value=slugify((merchant&&merchant.value?merchant.value+' ':'')+(title&&title.value?title.value:'microgift-product'));}
  function ensureExpirationSelect(){
    var current=root.querySelector('#expiration');
    if(!current||current.tagName==='SELECT')return;
    var select=document.createElement('select');
    select.id='expiration';
    select.innerHTML='<option value="No expiration until issued">No expiration until issued</option><option value="30 days after issue">30 days after issue</option><option value="60 days after issue">60 days after issue</option><option value="90 days after issue">90 days after issue</option><option value="End of campaign">End of campaign</option><option value="Merchant controlled expiration">Merchant controlled expiration</option>';
    select.value=current.value||'No expiration until issued';
    current.replaceWith(select);
    select.addEventListener('input',function(){select.dispatchEvent(new Event('change',{bubbles:true}));});
  }
  function hideSlugField(){var slug=root.querySelector('#slug');if(!slug)return;var field=slug.closest('.mg-builder-field');if(field){field.setAttribute('data-builder-auto-slug-field','true');field.hidden=true;}slug.readOnly=true;syncSlug();}
  function syncTypeUI(){
    var type=selectedType();
    root.dataset.activeTemplate=type;
    var label=root.querySelector('[data-preview-template-label]');if(label)label.textContent=typeLabels[type]||'Product';
    var giftStep=root.querySelector('[data-builder-step="gift"]');if(giftStep)labelStep(giftStep,'Info');
    var giftTitle=root.querySelector('[data-builder-panel="gift"] .mg-builder-section-title');if(giftTitle)giftTitle.textContent=isCardType(type)?'Card info':'Info';
    root.querySelectorAll('[data-builder-step]').forEach(function(button){button.hidden=!stepAllowed(button.dataset.builderStep,type);});
    root.querySelectorAll('[data-builder-panel]').forEach(function(panel){var step=panel.dataset.builderPanel;panel.hidden=!stepAllowed(step,type);});
    root.querySelectorAll('[data-builder-types]').forEach(function(node){var list=String(node.dataset.builderTypes||'').split(/\s+/).filter(Boolean);var visible=!list.length||list.indexOf(type)!==-1;node.hidden=!visible;node.setAttribute('aria-hidden',visible?'false':'true');node.querySelectorAll('input,textarea,select,button').forEach(function(control){control.disabled=!visible;});});
    var headline=root.querySelector('#headline');var message=root.querySelector('#message');if(headline)headline.required=isCardType(type);if(message)message.required=isCardType(type);
    var active=root.querySelector('[data-builder-step].is-active');if(active&&active.hidden)activateStep('product');
  }
  root.querySelectorAll('input[name="builder_type"]').forEach(function(input){input.addEventListener('change',function(){syncTypeUI();});});
  root.addEventListener('input',function(event){if(event.target&&/^(productTitle|merchantName)$/.test(event.target.id))syncSlug();});
  root.addEventListener('change',function(event){if(event.target&&/^(productTitle|merchantName)$/.test(event.target.id))syncSlug();});
  ensureExpirationSelect();
  hideSlugField();
  syncTypeUI();
  var deadline=Date.now()+5000;
  (function watch(){syncSlug();syncTypeUI();if(Date.now()<deadline)window.requestAnimationFrame(watch);})();
});
