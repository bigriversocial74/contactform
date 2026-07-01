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
    if(!root.querySelector('#simpleProductImageProxy')){
      var upload=document.createElement('div');
      upload.className='mg-builder-upload';upload.setAttribute('data-simple-product-control','image');
      upload.innerHTML='<div class="mg-builder-upload-head"><label class="mg-builder-upload-label" for="simpleProductImageProxy">Upload product image</label><span class="mg-builder-help">Social post image</span></div><input id="simpleProductImageProxy" type="file" accept="image/jpeg,image/png,image/webp,image/gif"><div class="mg-simple-product-proxy-preview" data-simple-product-image-preview></div><div class="mg-builder-upload-meta" data-simple-product-image-meta></div>';
      after(root.querySelector('.mg-builder-grid-2'),upload);
    }
  }
  function sync(){
    if(syncing)return;syncing=true;
    ensure();
    var simple=selectedType()==='simple_product';
    root.querySelectorAll('[data-simple-product-control]').forEach(function(n){n.hidden=!simple;});
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
    var proxy=root.querySelector('#simpleProductImageProxy');
    if(proxy&&!proxy._mgSimpleBound){
      proxy._mgSimpleBound=true;
      proxy.addEventListener('change',function(){
        var file=proxy.files&&proxy.files[0];if(!file)return;
        var url=URL.createObjectURL(file);
        var preview=root.querySelector('[data-simple-product-image-preview]');
        var meta=root.querySelector('[data-simple-product-image-meta]');
        if(preview){preview.style.backgroundImage='url("'+url.replace(/"/g,'%22')+'")';preview.classList.add('is-visible');}
        if(meta)meta.textContent=file.name;
        root.querySelectorAll('[data-preview-template="simple_product"] [data-cover-media]').forEach(function(n){n.style.backgroundImage='url("'+url.replace(/"/g,'%22')+'")';});
      });
    }
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
