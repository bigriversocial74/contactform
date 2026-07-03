(function(){
  'use strict';

  var root=document.querySelector('[data-builder-app]');
  if(!root)return;

  var lastUrl='';

  function clean(value){return String(value||'').trim();}
  function cssUrl(value){return 'url("'+clean(value).replace(/"/g,'%22')+'")';}
  function selectedType(){var node=root.querySelector('input[name="builder_type"]:checked');return node?node.value:'simple_product';}
  function thumbnailPreviewUrl(){
    var img=root.querySelector('[data-media-preview="thumbnail"] img');
    var url=img&&!img.hidden?clean(img.currentSrc||img.getAttribute('src')):'';
    if(url)lastUrl=url;
    return url||lastUrl;
  }

  function render(){
    if(selectedType()!=='simple_product')return;
    var url=thumbnailPreviewUrl();
    root.querySelectorAll('[data-product-media]').forEach(function(node){
      var image=node.querySelector('[data-product-media-image]');
      node.classList.toggle('has-product-image',!!url);
      node.style.position='relative';
      node.style.overflow='hidden';
      if(url){
        node.style.setProperty('background-image',cssUrl(url));
        if(!image){
          image=document.createElement('img');
          image.dataset.productMediaImage='1';
          image.alt='Product image preview';
          image.decoding='async';
          image.style.cssText='position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block;z-index:1;';
          node.appendChild(image);
        }
        if(image.getAttribute('src')!==url)image.src=url;
        image.hidden=false;
      }else{
        node.style.removeProperty('background-image');
        if(image){image.removeAttribute('src');image.hidden=true;}
      }
    });
  }

  function schedule(){window.requestAnimationFrame(render);}

  root.addEventListener('change',function(event){
    if(event.target&&event.target.matches('[data-asset-role="thumbnail"], input[name="builder_type"]'))schedule();
  });

  var preview=root.querySelector('[data-media-preview="thumbnail"]');
  if(preview)new MutationObserver(schedule).observe(preview,{childList:true,subtree:true,attributes:true,attributeFilter:['src','hidden','class']});

  document.addEventListener('mg:builder-preview-rendered',schedule);
  schedule();
})();
