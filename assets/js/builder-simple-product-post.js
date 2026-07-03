(function(){
  'use strict';
  var root=document.querySelector('[data-builder-app]');
  if(!root)return;

  function selectedType(){var n=root.querySelector('input[name="builder_type"]:checked');return n?n.value:'simple_product';}
  function accountName(){var n=document.querySelector('.mg-account-name')||document.querySelector('.mg-account-head-name');return n&&n.textContent.trim()?n.textContent.trim():'Your business';}
  function initial(value){value=String(value||'').trim();return value?value.charAt(0).toUpperCase():'M';}
  function cssUrl(value){return 'url("'+String(value||'').replace(/"/g,'%22')+'")';}

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

  function fieldValue(id,fallback){var n=root.querySelector('#'+id);var v=n?String(n.value||'').trim():'';return v||fallback;}

  function syncCardText(){
    var headline=fieldValue('headline','HAPPY BIRTHDAY!');
    var message=fieldValue('message','Add the message the recipient will see inside the card.');
    root.querySelectorAll('.mg-card-message-copy').forEach(function(copy){
      var headlineNode=copy.querySelector('[data-preview-card-headline], .mg-card-message-title, h1, h2, h3');
      var messageNode=copy.querySelector('[data-preview-card-message]');
      if(!messageNode||messageNode===headlineNode){
        messageNode=Array.from(copy.querySelectorAll('[data-preview-message], p, .mg-card-inside-message')).find(function(node){return node!==headlineNode;})||null;
      }
      if(!headlineNode){
        headlineNode=document.createElement('h3');
        copy.insertBefore(headlineNode,copy.firstChild||null);
      }
      if(!messageNode||messageNode===headlineNode){
        messageNode=document.createElement('p');
        headlineNode.insertAdjacentElement('afterend',messageNode);
      }
      headlineNode.classList.add('mg-card-message-title');
      headlineNode.setAttribute('data-preview-card-headline','');
      headlineNode.removeAttribute('data-preview-message');
      headlineNode.removeAttribute('data-preview-card-message');
      headlineNode.textContent=headline;
      headlineNode.style.fontWeight='950';
      headlineNode.style.lineHeight='.96';
      headlineNode.style.margin='0 0 12px';
      messageNode.classList.add('mg-card-inside-message');
      messageNode.setAttribute('data-preview-card-message','');
      messageNode.removeAttribute('data-preview-message');
      messageNode.removeAttribute('data-preview-card-headline');
      messageNode.textContent=message;
      messageNode.style.fontWeight='400';
      messageNode.style.lineHeight='1.45';
      messageNode.style.margin='0';
    });
  }

  function sync(){
    var merchant=root.querySelector('#merchantName');
    if(merchant&&!merchant.value.trim())merchant.value=accountName();
    var name=merchant&&merchant.value?merchant.value:accountName();
    root.querySelectorAll('[data-preview-merchant-initial]').forEach(function(n){n.textContent=initial(name);});

    syncCardText();
    if(selectedType()!=='simple_product')return;
    var description=root.querySelector('#productDescription');
    var text=description&&description.value.trim()?description.value.trim():'Add product description.';
    root.querySelectorAll('[data-preview-template="simple_product"] [data-preview-headline]').forEach(function(n){n.textContent=text;});
    syncProductImage();
  }

  function bind(){
    root.querySelectorAll('input[name="builder_type"]').forEach(function(n){if(!n._mgSimplePostBound){n._mgSimplePostBound=true;n.addEventListener('change',sync);}});
    root.addEventListener('input',function(event){if(event.target&&/^(productDescription|merchantName|headline|message)$/.test(event.target.id))setTimeout(sync,0);});
    root.addEventListener('change',function(event){
      if(event.target&&(/^(productDescription|merchantName|headline|message)$/.test(event.target.id)||event.target.matches('[data-asset-role="thumbnail"]')))setTimeout(sync,0);
    });
    var preview=root.querySelector('[data-media-preview="thumbnail"]');
    if(preview)new MutationObserver(sync).observe(preview,{childList:true,subtree:true,attributes:true,attributeFilter:['src','class']});
    root.querySelectorAll('.mg-card-message-copy').forEach(function(copy){new MutationObserver(sync).observe(copy,{childList:true,subtree:true,attributes:true,attributeFilter:['data-preview-message','data-preview-card-headline','data-preview-card-message']});});
    sync();
  }

  bind();
  var deadline=Date.now()+8000;
  (function watch(){sync();if(Date.now()<deadline)window.requestAnimationFrame(watch);})();
})();
