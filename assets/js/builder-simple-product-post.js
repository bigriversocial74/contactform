(function(){
  'use strict';
  var root=document.querySelector('[data-builder-app]');
  if(!root)return;

  function selectedType(){var n=root.querySelector('input[name="builder_type"]:checked');return n?n.value:'simple_product';}
  function accountName(){var n=document.querySelector('.mg-account-name')||document.querySelector('.mg-account-head-name');return n&&n.textContent.trim()?n.textContent.trim():'Your business';}
  function initial(value){value=String(value||'').trim();return value?value.charAt(0).toUpperCase():'M';}

  function sync(){
    var merchant=root.querySelector('#merchantName');
    if(merchant&&!merchant.value.trim())merchant.value=accountName();
    var name=merchant&&merchant.value?merchant.value:accountName();
    root.querySelectorAll('[data-preview-merchant-initial]').forEach(function(n){n.textContent=initial(name);});

    if(selectedType()!=='simple_product')return;
    var description=root.querySelector('#productDescription');
    var text=description&&description.value.trim()?description.value.trim():'Add product description.';
    root.querySelectorAll('[data-preview-template="simple_product"] [data-preview-headline]').forEach(function(n){n.textContent=text;});
  }

  function bind(){
    root.querySelectorAll('input[name="builder_type"]').forEach(function(n){if(!n._mgSimplePostBound){n._mgSimplePostBound=true;n.addEventListener('change',sync);}});
    root.addEventListener('input',function(event){if(event.target&&/^(productDescription|merchantName)$/.test(event.target.id))setTimeout(sync,0);});
    root.addEventListener('change',function(event){if(event.target&&/^(productDescription|merchantName)$/.test(event.target.id))setTimeout(sync,0);});
    sync();
  }

  bind();
  var deadline=Date.now()+5000;
  (function watch(){sync();if(Date.now()<deadline)window.requestAnimationFrame(watch);})();
})();
