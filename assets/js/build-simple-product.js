(function(){
  'use strict';

  var root=document.querySelector('[data-simple-product-builder]');
  if(!root)return;

  var csrfMeta=document.querySelector('meta[name="csrf-token"]');
  var csrfToken=csrfMeta?csrfMeta.content:'';
  var authenticated=document.body.dataset.authenticated==='true';
  var productId=root.dataset.productId||new URLSearchParams(window.location.search).get('id')||'';
  var lockVersion=0;
  var assets={cover:''};
  var imageUrl='';
  var isSaving=false;
  var isPublishing=false;

  var statusNode=root.querySelector('[data-sp-status]');
  var toastNode=root.querySelector('[data-sp-toast]');
  var imageInput=root.querySelector('[data-sp-image-input]');
  var imagePreview=root.querySelector('[data-sp-image-preview]');
  var previewImage=root.querySelector('[data-sp-preview-image]');
  var previewEmpty=root.querySelector('[data-sp-preview-empty]');
  var locationSelect=root.querySelector('[data-sp-locations]');
  var allLocations=root.querySelector('[data-sp-all-locations]');
  var saveButton=root.querySelector('[data-sp-save]');
  var publishButton=root.querySelector('[data-sp-publish]');
  var productLink=root.querySelector('[data-sp-product-link]');

  function qs(sel){return root.querySelector(sel);} 
  function field(id){return qs('#'+id);} 
  function value(id){var node=field(id);return node?node.value:'';} 
  function setValue(id,next){var node=field(id);if(node&&next!==undefined&&next!==null)node.value=next;} 
  function clean(v){return String(v==null?'':v).trim();}
  function money(raw){var n=Number(String(raw||'').replace(/[^0-9.-]/g,''));return Number.isFinite(n)?Math.max(0,Math.round(n*100)):0;}
  function amountText(){var currency=value('currency')||'USD',amount=value('price')||'25.00';return currency==='USD'?'$'+amount.replace(/^\$/,''):currency+' '+amount.replace(/^\$/,'');}
  function initial(name){name=clean(name);return name?name.charAt(0).toUpperCase():'M';}

  function setStatus(message,state){
    if(!statusNode)return;
    statusNode.textContent=message||'';
    statusNode.classList.remove('is-saving','is-error');
    if(state)statusNode.classList.add(state);
  }

  function toast(message){
    if(!toastNode)return;
    toastNode.textContent=message||'';
    toastNode.classList.add('is-visible');
    clearTimeout(toastNode._timer);
    toastNode._timer=setTimeout(function(){toastNode.classList.remove('is-visible');},3200);
  }

  async function api(url,options){
    var response=await fetch(url,options||{});
    var payload=await response.json().catch(function(){return{};});
    if(!response.ok||payload.ok===false)throw new Error(payload.message||'Request failed.');
    return payload.data||payload;
  }

  function selectedLocationIds(){
    if(!locationSelect||locationSelect.disabled)return[];
    return Array.from(locationSelect.selectedOptions||[]).map(function(option){return option.value;}).filter(Boolean);
  }

  function renderLocations(locations,selected){
    if(!locationSelect)return;
    locationSelect.innerHTML='';
    selected=(selected||[]).map(String);
    (locations||[]).forEach(function(location){
      var option=document.createElement('option');
      option.value=String(location.public_id||'');
      var place=[location.city,location.region].filter(Boolean).join(', ');
      option.textContent=clean(location.name||'Location')+(place?' · '+place:'')+(location.is_primary?' · Primary':'');
      option.selected=selected.length?selected.indexOf(option.value)>-1:Boolean(location.is_primary);
      locationSelect.appendChild(option);
    });
    if(!locationSelect.options.length){
      var empty=document.createElement('option');
      empty.disabled=true;
      empty.textContent='Add an active merchant location before publishing';
      locationSelect.appendChild(empty);
    }
    locationSelect.disabled=Boolean(allLocations&&allLocations.checked);
  }

  function renderImage(){
    var targets=[imagePreview,previewImage];
    targets.forEach(function(img){
      if(!img)return;
      if(imageUrl){img.src=imageUrl;img.hidden=false;}else{img.removeAttribute('src');img.hidden=true;}
    });
    if(previewEmpty)previewEmpty.hidden=!!imageUrl;
  }

  function renderPreview(){
    var merchant=clean(value('merchantName'))||'Your business';
    var title=clean(value('productTitle'))||'Product title';
    var description=clean(value('productDescription'))||'Add a short product description.';
    var valueNode=root.querySelector('[data-sp-preview-value]');
    root.querySelectorAll('[data-sp-preview-merchant]').forEach(function(node){node.textContent=merchant;});
    root.querySelectorAll('[data-sp-preview-initial]').forEach(function(node){node.textContent=initial(merchant);});
    root.querySelectorAll('[data-sp-preview-title]').forEach(function(node){node.textContent=title;});
    root.querySelectorAll('[data-sp-preview-description]').forEach(function(node){node.textContent=description;});
    if(valueNode)valueNode.textContent=amountText();
    renderImage();
  }

  function gatherPayload(){
    return{
      title:clean(value('productTitle')),
      merchant_name:clean(value('merchantName'))||'Your business',
      product_category:clean(value('productCategory'))||'Voucher',
      description:clean(value('productDescription')),
      value_cents:money(value('price')),
      currency:clean(value('currency'))||'USD',
      offer:'',
      location_ids:selectedLocationIds(),
      all_locations:Boolean(allLocations&&allLocations.checked),
      headline:clean(value('productDescription')),
      message:'',
      recipient_note:'',
      collaboration_prompt:'',
      audio_label:'',
      video_label:'',
      claim_code_label:clean(value('claimCode')),
      slug:clean(value('slug')),
      visibility:'public',
      demo:false,
      terms:{note:clean(value('terms'))},
      expiration_policy:{label:clean(value('expiration'))}
    };
  }

  function fillPayload(payload){
    payload=payload||{};
    setValue('productTitle',payload.title||'');
    setValue('merchantName',payload.merchant_name||'');
    setValue('productCategory',payload.product_category||'Voucher');
    setValue('productDescription',payload.description||payload.headline||'');
    if(payload.value_cents!==undefined&&payload.value_cents!==null)setValue('price',Number(payload.value_cents)>0?(Number(payload.value_cents)/100).toFixed(2):'');
    setValue('currency',payload.currency||'USD');
    setValue('claimCode',payload.claim_code_label||'');
    setValue('slug',payload.slug||'');
    setValue('terms',payload.terms&&payload.terms.note||'');
    setValue('expiration',payload.expiration_policy&&payload.expiration_policy.label||'');
    if(allLocations)allLocations.checked=Boolean(payload.all_locations);
  }

  function validate(canPublish){
    if(!authenticated)return'Sign in to save or publish.';
    if(!clean(value('productTitle')))return'Enter a product title.';
    if(canPublish&&money(value('price'))<1)return'Enter a product value.';
    if(canPublish&&!(allLocations&&allLocations.checked)&&selectedLocationIds().length<1)return'Choose at least one active merchant location or use all locations.';
    return'';
  }

  async function uploadImage(){
    if(!imageInput||!imageInput.files||!imageInput.files[0])return;
    var file=imageInput.files[0];
    imageUrl=URL.createObjectURL(file);
    renderPreview();
    if(!authenticated){toast('Sign in to save uploaded media.');return;}
    setStatus('Uploading image…','is-saving');
    var body=new FormData();
    body.append('file',file);
    body.append('role','cover');
    body.append('csrf_token',csrfToken);
    try{
      var data=await api('/api/catalog/upload.php',{method:'POST',credentials:'same-origin',headers:{'X-CSRF-Token':csrfToken},body:body});
      assets.cover=data.asset_id||'';
      imageUrl=data.preview_url||imageUrl;
      renderPreview();
      setStatus('Image uploaded');
    }catch(error){
      setStatus('Image upload failed','is-error');
      toast(error.message);
    }
  }

  async function saveDraft(quiet){
    var problem=validate(false);
    if(problem){if(!quiet)toast(problem);setStatus(problem,'is-error');return false;}
    if(isSaving)return false;
    isSaving=true;
    if(saveButton)saveButton.disabled=true;
    setStatus('Saving draft…','is-saving');
    try{
      var data=await api('/api/catalog/builder-draft.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':csrfToken},body:JSON.stringify({action:'save',product_id:productId,builder_type:'simple_product',payload:gatherPayload(),assets:assets,lock_version:lockVersion,csrf_token:csrfToken})});
      productId=data.product_id||productId;
      lockVersion=Number(data.lock_version||lockVersion||0);
      root.dataset.productId=productId;
      if(productId){var url=new URL(window.location.href);url.searchParams.set('id',productId);window.history.replaceState({},'',url.toString());}
      setStatus('Draft saved');
      if(!quiet)toast('Product draft saved.');
      return true;
    }catch(error){
      setStatus('Save failed','is-error');
      if(!quiet)toast(error.message);
      return false;
    }finally{
      isSaving=false;
      if(saveButton)saveButton.disabled=false;
    }
  }

  async function publish(){
    var problem=validate(true);
    if(problem){toast(problem);setStatus(problem,'is-error');return;}
    if(isPublishing)return;
    isPublishing=true;
    if(publishButton)publishButton.disabled=true;
    setStatus('Publishing product…','is-saving');
    try{
      if(!productId){var saved=await saveDraft(true);if(!saved)throw new Error('Save the product before publishing.');}
      var data=await api('/api/catalog/builder-draft.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':csrfToken},body:JSON.stringify({action:'publish',product_id:productId,builder_type:'simple_product',payload:gatherPayload(),assets:assets,lock_version:lockVersion,csrf_token:csrfToken})});
      lockVersion=Number(data.lock_version||lockVersion||0);
      setStatus('Published');
      toast('Product published.');
      if(productLink&&data.product_url){productLink.href=data.product_url;productLink.hidden=false;}
    }catch(error){
      setStatus('Publish failed','is-error');
      toast(error.message);
    }finally{
      isPublishing=false;
      if(publishButton)publishButton.disabled=false;
    }
  }

  async function load(){
    renderPreview();
    if(!authenticated){setStatus('Sign in to save products.');return;}
    setStatus('Loading…','is-saving');
    try{
      var endpoint='/api/catalog/builder-draft.php'+(productId?'?id='+encodeURIComponent(productId):'');
      var data=await api(endpoint,{credentials:'same-origin'});
      if(data.merchant&&data.merchant.display_name&&!clean(value('merchantName')))setValue('merchantName',data.merchant.display_name);
      var draft=data.draft;
      var selected=[];
      if(draft){
        fillPayload(draft.payload||{});
        lockVersion=Number(draft.lock_version||0);
        productId=draft.product_id||productId;
        var draftAssets=draft.assets||{};
        assets.cover=draftAssets.cover||draftAssets.thumbnail||'';
        if(assets.cover)imageUrl='/api/catalog/asset-file.php?id='+encodeURIComponent(assets.cover);
        selected=Array.isArray((draft.payload||{}).location_ids)?draft.payload.location_ids:[];
      }
      renderLocations(data.locations||[],selected);
      renderPreview();
      setStatus(draft?'Draft loaded':'New simple product');
    }catch(error){
      setStatus('Load failed','is-error');
      toast(error.message);
    }
  }

  root.addEventListener('input',function(event){
    if(event.target&&event.target.matches('input,textarea,select'))renderPreview();
  });
  root.addEventListener('change',function(event){
    if(event.target===imageInput)uploadImage();
    if(event.target===allLocations&&locationSelect)locationSelect.disabled=allLocations.checked;
    renderPreview();
  });
  if(saveButton)saveButton.addEventListener('click',function(){saveDraft(false);});
  if(publishButton)publishButton.addEventListener('click',publish);

  load();
})();
