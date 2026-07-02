(function(){
  'use strict';
  var root=document.querySelector('[data-builder-app]');
  if(!root)return;

  function selectedType(){var n=root.querySelector('input[name="builder_type"]:checked');return n?n.value:'simple_product';}
  function accountName(){var n=document.querySelector('.mg-account-name')||document.querySelector('.mg-account-head-name');return n&&n.textContent.trim()?n.textContent.trim():'Your business';}
  function initial(value){value=String(value||'').trim();return value?value.charAt(0).toUpperCase():'M';}
  function cssUrl(value){return 'url("'+String(value||'').replace(/"/g,'%22')+'")';}

  function injectSimpleProductFixes(){
    if(document.getElementById('mg-simple-product-preview-fixes'))return;
    var style=document.createElement('style');
    style.id='mg-simple-product-preview-fixes';
    style.textContent='html body.mg-section-builder .mg-builder-shell[data-active-template="simple_product"] .mg-builder-simple-media.has-product-image::after{content:none!important;display:none!important}'+
      'html body.mg-section-builder .mg-builder-shell[data-active-template="simple_product"] .mg-builder-simple-media.has-product-image{background-position:center!important;background-size:cover!important;background-repeat:no-repeat!important}'+
      'html body.mg-section-builder .mg-builder-card.is-mobile .mg-builder-simple{display:grid!important;grid-template-rows:minmax(0,1fr) minmax(0,1fr)!important;min-height:680px!important;height:680px!important}'+
      'html body.mg-section-builder .mg-builder-card.is-mobile .mg-builder-simple-copy{display:flex!important;flex-direction:column!important;justify-content:center!important;min-height:0!important;padding:28px!important;order:1!important;border-bottom:1px solid rgba(226,232,240,.8)!important}'+
      'html body.mg-section-builder .mg-builder-card.is-mobile .mg-builder-simple-media{order:2!important;width:100%!important;height:100%!important;min-height:0!important;aspect-ratio:auto!important;border-radius:0!important;background-position:center!important;background-size:cover!important;background-repeat:no-repeat!important}'+
      'html body.mg-section-builder .mg-builder-card.is-mobile .mg-builder-simple .mg-product-profile{order:0!important;margin:0 0 18px!important;padding:0!important;border-bottom:0!important}'+
      'html body.mg-section-builder .mg-builder-card.is-mobile .mg-builder-simple h1{order:0!important;margin:0!important;padding:0!important;font-size:34px!important;line-height:1.04!important}'+
      'html body.mg-section-builder .mg-builder-card.is-mobile .mg-builder-simple p{order:0!important;margin:12px 0 0!important;padding:0!important;font-size:15px!important;line-height:1.45!important}'+
      'html body.mg-section-builder .mg-builder-card.is-mobile .mg-builder-simple-value{order:0!important;margin:22px 0 0!important;padding:0!important;font-size:52px!important;line-height:1!important}'+
      '@media(max-width:900px){html body.mg-section-builder .mg-builder-shell[data-active-template="simple_product"] .mg-builder-simple{display:grid!important;grid-template-rows:minmax(0,1fr) minmax(0,1fr)!important;min-height:620px!important}html body.mg-section-builder .mg-builder-shell[data-active-template="simple_product"] .mg-builder-simple-copy{display:flex!important;flex-direction:column!important;justify-content:center!important;min-height:0!important;padding:28px!important;order:1!important;border-bottom:1px solid rgba(226,232,240,.8)!important}html body.mg-section-builder .mg-builder-shell[data-active-template="simple_product"] .mg-builder-simple-media{order:2!important;width:100%!important;height:100%!important;min-height:0!important;aspect-ratio:auto!important;border-radius:0!important;background-position:center!important;background-size:cover!important;background-repeat:no-repeat!important}html body.mg-section-builder .mg-builder-shell[data-active-template="simple_product"] .mg-builder-simple .mg-product-profile{order:0!important;margin:0 0 18px!important;padding:0!important;border-bottom:0!important}html body.mg-section-builder .mg-builder-shell[data-active-template="simple_product"] .mg-builder-simple h1{order:0!important;margin:0!important;padding:0!important;font-size:34px!important;line-height:1.04!important}html body.mg-section-builder .mg-builder-shell[data-active-template="simple_product"] .mg-builder-simple p{order:0!important;margin:12px 0 0!important;padding:0!important;font-size:15px!important;line-height:1.45!important}html body.mg-section-builder .mg-builder-shell[data-active-template="simple_product"] .mg-builder-simple-value{order:0!important;margin:22px 0 0!important;padding:0!important;font-size:52px!important;line-height:1!important}}';
    document.head.appendChild(style);
  }

  function thumbnailUrl(){
    var img=root.querySelector('[data-media-preview="thumbnail"] img');
    var src=img&&(img.currentSrc||img.getAttribute('src'));
    if(src)return src;
    var media=root.querySelector('[data-product-media]');
    var inline=media&&media.style&&media.style.backgroundImage;
    if(inline&&inline!=='none')return inline.replace(/^url\(["']?/,'').replace(/["']?\)$/,'');
    return '';
  }

  function syncProductImage(){
    var url=thumbnailUrl();
    root.querySelectorAll('[data-product-media]').forEach(function(node){
      node.classList.toggle('has-product-image',!!url);
      if(url){
        node.style.setProperty('background-image',cssUrl(url),'important');
      }else{
        node.style.removeProperty('background-image');
      }
    });
  }

  function sync(){
    injectSimpleProductFixes();
    var merchant=root.querySelector('#merchantName');
    if(merchant&&!merchant.value.trim())merchant.value=accountName();
    var name=merchant&&merchant.value?merchant.value:accountName();
    root.querySelectorAll('[data-preview-merchant-initial]').forEach(function(n){n.textContent=initial(name);});

    if(selectedType()!=='simple_product')return;
    var description=root.querySelector('#productDescription');
    var text=description&&description.value.trim()?description.value.trim():'Add product description.';
    root.querySelectorAll('[data-preview-template="simple_product"] [data-preview-headline]').forEach(function(n){n.textContent=text;});
    syncProductImage();
  }

  function bind(){
    root.querySelectorAll('input[name="builder_type"]').forEach(function(n){if(!n._mgSimplePostBound){n._mgSimplePostBound=true;n.addEventListener('change',sync);}});
    root.addEventListener('input',function(event){if(event.target&&/^(productDescription|merchantName)$/.test(event.target.id))setTimeout(sync,0);});
    root.addEventListener('change',function(event){
      if(event.target&&(/^(productDescription|merchantName)$/.test(event.target.id)||event.target.matches('[data-asset-role="thumbnail"]')))setTimeout(sync,0);
    });
    var preview=root.querySelector('[data-media-preview="thumbnail"]');
    if(preview)new MutationObserver(sync).observe(preview,{childList:true,subtree:true,attributes:true,attributeFilter:['src','class']});
    sync();
  }

  bind();
  var deadline=Date.now()+8000;
  (function watch(){sync();if(Date.now()<deadline)window.requestAnimationFrame(watch);})();
})();
