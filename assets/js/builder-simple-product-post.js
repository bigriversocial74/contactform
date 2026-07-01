(function(){
  'use strict';
  var root=document.querySelector('[data-builder-app]');
  if(!root)return;
  var syncing=false;
  function selectedType(){var n=root.querySelector('input[name="builder_type"]:checked');return n?n.value:'simple_product';}
  function field(selector){var n=root.querySelector(selector);return n?n.closest('.mg-builder-field'):null;}
  function after(target,node){if(target&&target.parentNode)target.parentNode.insertBefore(node,target.nextSibling);}
  function hide(selector){var f=field(selector);if(f)f.setAttribute('data-simple-product-hidden','true');}
  function accountName(){var n=document.querySelector('.mg-account-name')||document.querySelector('.mg-account-head-name');return n&&n.textContent.trim()?n.textContent.trim():'Your business';}
  function initial(value){value=String(value||'').trim();return value?value.charAt(0).toUpperCase():'M';}
  function ensure(){
    hide('#merchantName');hide('#productCategory');hide('#discount');
    var merchant=root.querySelector('#merchantName');
    if(merchant&&!merchant.value.trim())merchant.value=accountName();
    if(!root.querySelector('#simpleProductInfo')){
      var box=document.createElement('div');
      box.className='mg-builder-field';box.setAttribute('data-simple-product-control','info');
      box.innerHTML='<label for="simpleProductInfo">Product info</label><textarea id="simpleProductInfo" maxlength="4000" placeholder="Describe what the customer receives, where it can be used, or why it is valuable."></textarea>';
      after(field('#productTitle'),box);
    }
    var valueGrid=root.querySelector('.mg-builder-grid-2');
    if(valueGrid&&!root.querySelector('[data-simple-product-cover-slot]')){
      var simpleSlot=document.createElement('span');simpleSlot.setAttribute('data-simple-product-cover-slot','true');simpleSlot.hidden=true;after(valueGrid,simpleSlot);
    }
    var coverField=field('#coverImage');
    if(coverField&&!root.querySelector('[data-card-cover-slot]')){
      var cardSlot=document.createElement('span');cardSlot.setAttribute('data-card-cover-slot','true');cardSlot.hidden=true;coverField.parentNode.insertBefore(cardSlot,coverField);
    }
  }
  function moveCoverUpload(simple){
    var coverField=field('#coverImage');
    if(!coverField)return;
    var simpleSlot=root.querySelector('[data-simple-product-cover-slot]');
    var cardSlot=root.querySelector('[data-card-cover-slot]');
    if(simple&&simpleSlot){simpleSlot.parentNode.insertBefore(coverField,simpleSlot.nextSibling);coverField.setAttribute('data-simple-product-control','image');}
    if(!simple&&cardSlot){cardSlot.parentNode.insertBefore(coverField,cardSlot.nextSibling);coverField.removeAttribute('data-simple-product-control');}
    var label=coverField.querySelector('label[for="coverImage"]');
    var help=coverField.querySelector('.mg-builder-help');
    if(label)label.textContent=simple?'Upload product image':'Upload cover image';
    if(help)help.textContent=simple?'Social post image · JPG, PNG, WebP, GIF':'JPG, PNG, WebP, GIF';
  }
  function sync(){
    if(syncing)return;syncing=true;
    ensure();
    var simple=selectedType()==='simple_product';
    moveCoverUpload(simple);
    root.querySelectorAll('[data-simple-product-control]').forEach(function(n){n.hidden=!simple;n.querySelectorAll('input,textarea,select,button').forEach(function(c){c.disabled=!simple;});});
    var merchant=root.querySelector('#merchantName');
    var name=merchant&&merchant.value?merchant.value:accountName();
    root.querySelectorAll('[data-preview-merchant-initial]').forEach(function(n){n.textContent=initial(name);});
    var info=root.querySelector('#simpleProductInfo');
    var headline=root.querySelector('#headline');
    if(info&&headline&&simple)headline.value=info.value;
    var text=info&&info.value.trim()?info.value.trim():'Add product description.';
    root.querySelectorAll('[data-preview-template="simple_product"] [data-preview-headline]').forEach(function(n){n.textContent=text;});
    syncing=false;
  }
  function bind(){
    ensure();
    var info=root.querySelector('#simpleProductInfo');
    if(info&&!info._mgSimpleBound){info._mgSimpleBound=true;info.addEventListener('input',sync);info.addEventListener('change',sync);}
    root.querySelectorAll('input[name="builder_type"]').forEach(function(n){if(!n._mgSimpleBound){n._mgSimpleBound=true;n.addEventListener('change',sync);}});
    root.addEventListener('input',function(){setTimeout(sync,0);});
    root.addEventListener('change',function(){setTimeout(sync,0);});
    sync();
  }
  bind();
  var observer=new MutationObserver(function(){setTimeout(sync,0);});
  observer.observe(root,{childList:true,subtree:true,characterData:true});
  document.addEventListener('DOMContentLoaded',function(){bind();var end=Date.now()+5000;(function watch(){sync();if(Date.now()<end)requestAnimationFrame(watch);})();});
})();
