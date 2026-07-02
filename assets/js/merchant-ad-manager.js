window.Microgifter = window.Microgifter || {};
(function(window, document){
  'use strict';

  var root = document.querySelector('[data-ads-manager]');
  if (!root) return;

  var csrf = root.getAttribute('data-csrf-token') || '';
  var selectedId = '';
  var campaignsCache = [];
  var productsCache = [];
  var selectedProduct = null;
  var imageSource = 'manual';
  var lastProductImageUrl = '';

  function esc(value){return String(value == null ? '' : value).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
  function qs(sel, scope){return (scope||root).querySelector(sel);}
  function qsa(sel, scope){return Array.prototype.slice.call((scope||root).querySelectorAll(sel));}
  function field(name){return qs('[name="'+name+'"]');}
  function fieldValue(name){var node=field(name); return node ? node.value : '';}
  function setFieldValue(name, value){var node=field(name); if(node) node.value = value == null ? '' : String(value);}
  function status(message, error){var node=qs('[data-ads-status]'); if(node){node.textContent=message||''; node.style.color=error?'#b91c1c':'#64748b';}}
  function uploadStatus(message, error){var node=qs('[data-creative-upload-status]'); if(node){node.textContent=message||''; node.style.color=error?'#b91c1c':'#64748b';}}
  function updateImageSourceLabel(){var node=qs('[data-image-source-label]'); if(!node)return; node.textContent = imageSource === 'upload' ? 'Uploaded creative applied' : (imageSource === 'product' ? 'Product image applied' : 'Manual URL fallback');}
  function looksUploadedImage(url){return /^\/uploads\/ad-creatives\//.test(String(url||''));}
  function setImageUrl(url, source){setFieldValue('image_url', url || ''); imageSource = source || (looksUploadedImage(url) ? 'upload' : 'manual'); updateImageSourceLabel();}
  function fileSizeLabel(bytes){var size=Number(bytes||0); if(!size)return ''; if(size >= 1024*1024)return (size/(1024*1024)).toFixed(size >= 10*1024*1024 ? 0 : 1)+'MB'; if(size >= 1024)return Math.round(size/1024)+'KB'; return size+'B';}
  function setButtonBusy(button, busy, text){if(!button)return; if(busy){button.dataset.originalText=button.textContent;button.disabled=true;button.textContent=text||'Working…';}else{button.disabled=false;button.textContent=button.dataset.originalText||text||button.textContent;delete button.dataset.originalText;}}

  async function api(path, options){
    var res = await fetch(path, Object.assign({credentials:'same-origin'}, options||{}));
    var out = await res.json().catch(function(){return {ok:false,message:'Invalid server response'};});
    if (!res.ok || !out.ok) throw new Error(out.message || 'Request failed');
    return out.data || {};
  }

  function activateTab(name){
    name = name || 'create';
    qsa('[data-ads-tab-button]').forEach(function(btn){btn.classList.toggle('is-active', btn.getAttribute('data-ads-tab-button') === name);});
    qsa('[data-ads-tab-panel]').forEach(function(panel){panel.classList.toggle('is-active', panel.getAttribute('data-ads-tab-panel') === name);});
    if (name === 'preview') preview();
  }

  function resetDraft(){
    selectedId=''; selectedProduct=null; imageSource='manual'; lastProductImageUrl='';
    var form=qs('[data-ad-form]'); if(form) form.reset();
    qsa('[name="placements[]"]').forEach(function(i){i.checked=i.value==='feed_sponsored_card'||i.value==='sidebar_sponsored_card';});
    var picker=qs('[data-product-picker]'); if(picker) picker.value='';
    var file=qs('[data-creative-image-file]'); if(file) file.value='';
    renderProductSummary(null);
    updateImageSourceLabel();
    uploadStatus('');
    status('New draft ready.'); preview(); activateTab('create');
  }

  function productById(id){
    id = String(id || '');
    return productsCache.find(function(product){return String(product.id)===id;}) || null;
  }

  function targetingValue(targeting, key){
    var value = targeting && Object.prototype.hasOwnProperty.call(targeting, key) ? targeting[key] : null;
    if (value == null) return '';
    if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') return String(value);
    if (Array.isArray(value)) return '';
    if (typeof value === 'object') {
      if (value.value != null) return String(value.value);
      if (value.id != null) return String(value.id);
      if (value.public_id != null) return String(value.public_id);
    }
    return '';
  }

  function targetingMeta(value, label){
    return {value:String(value || ''), label:String(label || '')};
  }

  function renderProductSummary(product){
    var node=qs('[data-product-summary]');
    var apply=qs('[data-apply-product]');
    if(apply) apply.disabled=!product;
    if(!node)return;
    if(!product){node.hidden=true; node.innerHTML=''; return;}
    node.hidden=false;
    node.innerHTML='<div class="mg-ads-product-thumb">'+(product.image_url?'<img src="'+esc(product.image_url)+'" alt="">':'<span>MG</span>')+'</div><div><strong>'+esc(product.title)+'</strong><p>'+esc(product.description||product.value_label||'Merchant product')+'</p><small>'+esc([product.reward_type,product.value_label,product.status].filter(Boolean).join(' · '))+'</small></div>';
  }

  function resolveImageSourceFromField(){
    var current = fieldValue('image_url');
    if (looksUploadedImage(current)) return 'upload';
    if (selectedProduct && current && current === selectedProduct.image_url) return 'product';
    return 'manual';
  }

  function applyProduct(product){
    if(!product)return;
    selectedProduct=product;
    setFieldValue('title', 'Promote ' + (product.title || 'Local Reward'));
    setFieldValue('headline', product.headline || product.title || 'Featured Local Reward');
    setFieldValue('description', product.ad_description || product.description || 'Claim this local reward, save it to your wallet, and redeem it with the merchant.');
    var currentImage = fieldValue('image_url');
    var keepUploadedImage = imageSource === 'upload' || looksUploadedImage(currentImage);
    var shouldUseProductImage = product.image_url && (!currentImage || imageSource === 'product' || currentImage === lastProductImageUrl);
    if (keepUploadedImage) {
      imageSource = 'upload';
      uploadStatus('Product applied. Uploaded creative image was preserved.');
    } else if (shouldUseProductImage) {
      setImageUrl(product.image_url, 'product');
      lastProductImageUrl = product.image_url;
      uploadStatus('Product image applied. Upload a creative image to override it.');
    }
    setFieldValue('cta_label', product.cta_label || 'Claim Reward');
    setFieldValue('destination_url', product.destination_url || '/feed.php');
    setFieldValue('objective', product.agent_add_to_wallet_allowed ? 'claim_growth' : 'local_awareness');
    renderProductSummary(product);
    updateImageSourceLabel();
    status('Product applied to ad draft.');
    preview();
  }

  async function loadProducts(){
    var picker=qs('[data-product-picker]');
    if(!picker)return;
    picker.innerHTML='<option value="">Loading products…</option>';
    try{
      var data=await api('/api/ads/merchant-products.php?status=active');
      productsCache=data.products||[];
      if(!data.schema_ready){picker.innerHTML='<option value="">Product catalog unavailable</option>';return;}
      if(!productsCache.length){picker.innerHTML='<option value="">No active products found</option>';return;}
      picker.innerHTML='<option value="">Choose an existing product…</option>'+productsCache.map(function(product){return '<option value="'+esc(product.id)+'">'+esc(product.title+(product.value_label?' · '+product.value_label:''))+'</option>';}).join('');
      if (picker.value) {
        selectedProduct = productById(picker.value);
        renderProductSummary(selectedProduct);
      }
    }catch(error){picker.innerHTML='<option value="">Unable to load products</option>';}
  }

  async function uploadCreative(){
    var input=qs('[data-creative-image-file]');
    var button=qs('[data-upload-creative]');
    if(!input || !input.files || !input.files[0]){uploadStatus('Choose an image file first.', true);return;}
    var file=input.files[0];
    var allowed=/\.(jpe?g|png|gif|webp)$/i;
    if(!allowed.test(file.name || '')){uploadStatus('Use JPG, PNG, GIF, or WebP for campaign images.', true);return;}
    if(file.size > 8 * 1024 * 1024){uploadStatus('Image must be 8MB or smaller.', true);return;}
    var form=new FormData();
    form.append('csrf_token', csrf);
    form.append('creative_image', file);
    setButtonBusy(button, true, 'Uploading…');
    uploadStatus('Uploading creative image…');
    try{
      var res=await fetch('/api/ads/upload-creative.php',{method:'POST',credentials:'same-origin',headers:{'X-CSRF-TOKEN':csrf},body:form});
      var out=await res.json().catch(function(){return {ok:false,message:'Invalid upload response'};});
      if(!res.ok || !out.ok)throw new Error(out.message||'Unable to upload creative image.');
      var data=out.data||{};
      if(!data.url)throw new Error('Upload did not return an image URL.');
      setImageUrl(data.url, 'upload');
      var details=[data.width && data.height ? data.width+'×'+data.height : '', fileSizeLabel(data.size_bytes || file.size)].filter(Boolean).join(' · ');
      uploadStatus('Image uploaded and applied to preview'+(details ? ' ('+details+').' : '.'));
      preview();
    }catch(error){uploadStatus(error.message||'Upload failed.', true);}
    finally{setButtonBusy(button, false, 'Upload Image');}
  }

  function checkedPlacements(){return qsa('[name="placements[]"]:checked').map(function(input){return input.value;});}

  function formPayload(){
    var productId = qs('[name="source_product_id"]') ? qs('[name="source_product_id"]').value : '';
    var product = productById(productId);
    var currentImage = fieldValue('image_url');
    imageSource = resolveImageSourceFromField();
    var targeting = {phase:'phase1', controlled:true};
    if (productId) {
      targeting.source_product_id = targetingMeta(productId, product && product.title || '');
      targeting.source_product_type = targetingMeta(product && product.source || 'reward_template', 'Product source');
      targeting.source_product_title = targetingMeta(product && product.title || '', 'Product title');
    }
    if (currentImage) {
      targeting.creative_image_source = targetingMeta(imageSource, 'Creative image source');
      targeting.creative_image_url = targetingMeta(currentImage, 'Creative image URL');
    }
    return {
      csrf_token: csrf,
      title: fieldValue('title'),
      headline: fieldValue('headline'),
      description: fieldValue('description'),
      objective: fieldValue('objective'),
      budget_type: fieldValue('budget_type'),
      budget_amount: fieldValue('budget_amount'),
      claim_cap: fieldValue('claim_cap'),
      redemption_cap: fieldValue('redemption_cap'),
      starts_at: fieldValue('starts_at'),
      ends_at: fieldValue('ends_at'),
      image_url: currentImage,
      cta_label: fieldValue('cta_label'),
      destination_url: fieldValue('destination_url'),
      destination_type: productId ? 'reward_template' : '',
      target_zone_id: fieldValue('target_zone_id'),
      placements: checkedPlacements(),
      targeting: targeting
    };
  }

  function previewFallback(item){
    var creative = item.creative || {};
    return '<article class="mg-sponsored-card mg-sponsored-card-preview"><div class="mg-sponsored-card-media">'+(creative.image_url?'<img src="'+esc(creative.image_url)+'" alt="">':'<span>Sponsored</span>')+'</div><div class="mg-sponsored-card-body"><small>Sponsored</small><h3>'+esc(creative.headline||item.title||'Sponsored Campaign')+'</h3><p>'+esc(creative.description||'Claim this local reward, save it to your wallet, and redeem it with the merchant.')+'</p><strong>'+esc(creative.cta_label||'View Offer')+'</strong></div></article>';
  }

  function preview(){
    var payload = formPayload();
    var item = {public_id:selectedId||'preview', title:payload.title, objective:payload.objective, placement_key:payload.placements[0]||'feed_sponsored_card', merchant:{merchant_name:root.getAttribute('data-merchant-name')||'Microgifter Merchant'}, creative:{headline:payload.headline||payload.title, description:payload.description, image_url:payload.image_url, cta_label:payload.cta_label||'View Offer', destination_url:payload.destination_url, sponsored_label:'Sponsored'}};
    qsa('[data-ads-preview],[data-ads-preview-secondary]').forEach(function(target){target.innerHTML = window.Microgifter.renderSponsoredCampaignCard ? window.Microgifter.renderSponsoredCampaignCard(item,{compact:false}) : previewFallback(item);});
    updateImageSourceLabel();
  }

  function placementText(c){return (c.placements||[]).join(', ').replace(/_/g,' ') || 'None selected';}
  function money(value){var n=Number(value||0); return n ? '$'+n.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}) : '—';}
  function dateShort(value){if(!value)return '—'; try{return new Intl.DateTimeFormat(undefined,{month:'short',day:'numeric',year:'numeric'}).format(new Date(String(value).replace(' ','T')));}catch(e){return String(value).slice(0,10);}}

  function campaignRow(c){
    var creative = c.creative || {};
    var image = creative.image_url ? '<span class="mg-ads-row-thumb"><img src="'+esc(creative.image_url)+'" alt=""></span>' : '<span class="mg-ads-row-thumb">AD</span>';
    return '<article class="mg-ads-row mg-ads-campaign-table-row" data-campaign-id="'+esc(c.public_id)+'">'
      + '<div class="mg-ads-campaign-name">'+image+'<div><strong>'+esc(c.title)+'</strong><span>'+esc(creative.headline||placementText(c))+'</span></div></div>'
      + '<span class="mg-ads-pill is-'+esc(c.status)+'">'+esc(c.status).replace(/_/g,' ')+'</span>'
      + '<span>'+esc(money(c.budget_amount))+'</span>'
      + '<span>'+esc((c.objective||'').replace(/_/g,' '))+'</span>'
      + '<span>'+esc(dateShort(c.created_at||c.updated_at))+'</span>'
      + '<div class="mg-ads-row-actions"><button class="mg-btn mg-btn-soft" type="button" data-edit>Load</button><button class="mg-btn mg-btn-primary" type="button" data-submit>Submit</button></div>'
      + '</article>';
  }

  function loadCampaignIntoForm(c){
    selectedId = c.public_id || '';
    var creative = c.creative || {};
    setFieldValue('title', c.title || '');
    setFieldValue('headline', creative.headline || c.title || '');
    setFieldValue('description', creative.description || '');
    setFieldValue('objective', c.objective || 'claim_growth');
    setFieldValue('budget_type', c.budget_type || 'none');
    setFieldValue('budget_amount', c.budget_amount || '');
    setFieldValue('claim_cap', c.claim_cap || '');
    setFieldValue('redemption_cap', c.redemption_cap || '');
    setFieldValue('starts_at', c.starts_at ? String(c.starts_at).replace(' ','T').slice(0,16) : '');
    setFieldValue('ends_at', c.ends_at ? String(c.ends_at).replace(' ','T').slice(0,16) : '');
    setImageUrl(creative.image_url || '', looksUploadedImage(creative.image_url || '') ? 'upload' : 'manual');
    setFieldValue('cta_label', creative.cta_label || 'Claim Reward');
    setFieldValue('destination_url', creative.destination_url || '');
    setFieldValue('target_zone_id', c.target_zone_id || '');
    qsa('[name="placements[]"]').forEach(function(input){input.checked=(c.placements||[]).indexOf(input.value)!==-1;});
    var sourceProductId = targetingValue(c.targeting, 'source_product_id');
    var picker=qs('[data-product-picker]');
    if(picker) picker.value=sourceProductId;
    selectedProduct=productById(sourceProductId);
    if (selectedProduct && creative.image_url === selectedProduct.image_url) imageSource='product';
    renderProductSummary(selectedProduct);
    var file=qs('[data-creative-image-file]'); if(file) file.value='';
    updateImageSourceLabel();
    uploadStatus('');
    status('Loaded campaign '+selectedId+'.'); preview(); activateTab('create');
  }

  function filterCampaigns(){
    var q=(qs('[data-ads-search]') && qs('[data-ads-search]').value || '').toLowerCase().trim();
    return campaignsCache.filter(function(c){return !q || String(c.title+' '+(c.status||'')+' '+placementText(c)).toLowerCase().indexOf(q)!==-1;});
  }

  function renderList(){
    var list = qs('[data-ads-list]');
    if (!list) return;
    var campaigns = filterCampaigns();
    if (!campaigns.length) { list.innerHTML = '<div class="mg-ads-empty">No matching ad campaigns yet.</div>'; return; }
    list.innerHTML = '<div class="mg-ads-campaign-table-head"><span>Campaign name</span><span>Status</span><span>Budget</span><span>Objective</span><span>Created</span><span>Actions</span></div>' + campaigns.map(campaignRow).join('') + '<p class="mg-ads-table-foot">Showing '+campaigns.length+' of '+campaignsCache.length+' campaigns</p>';
    qsa('[data-edit]', list).forEach(function(btn){btn.onclick=function(){var id=btn.closest('[data-campaign-id]').getAttribute('data-campaign-id'); var c=campaignsCache.find(function(item){return item.public_id===id;}); if(c) loadCampaignIntoForm(c);};});
    qsa('[data-submit]', list).forEach(function(btn){btn.onclick=function(){submitCampaign(btn.closest('[data-campaign-id]').getAttribute('data-campaign-id'), btn);};});
  }

  async function loadList(){
    var data = await api('/api/ads/list.php?limit=60');
    var list = qs('[data-ads-list]');
    if (!data.schema_ready) { list.innerHTML='<div class="mg-ads-alert">Campaign Ads Manager migration is required before campaigns can be saved.</div>'; return; }
    campaignsCache = data.campaigns || [];
    renderList();
  }

  async function loadPerformance(){
    var data = await api('/api/ads/performance.php');
    var p = data.performance || data.summary || {};
    ['impressions','clicks','claims','redemptions'].forEach(function(key){qsa('[data-kpi="'+key+'"]').forEach(function(node){node.textContent=Number(p[key]||0).toLocaleString();});});
  }

  async function saveDraft(button){
    status('Saving campaign…');
    setButtonBusy(button, true, 'Saving…');
    try{
      var path = selectedId ? '/api/ads/update.php' : '/api/ads/create.php';
      var body = formPayload(); if (selectedId) body.ad_campaign_id = selectedId;
      var data = await api(path,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify(body)});
      selectedId = data.campaign && data.campaign.public_id || selectedId;
      status('Campaign saved.'); await loadList(); await loadPerformance(); preview();
    } finally {
      setButtonBusy(button, false, 'Save Campaign');
    }
  }

  async function submitCampaign(id, button){
    var publicId = id || selectedId;
    if (!publicId) { status('Save the draft before submitting.', true); return; }
    status('Submitting for admin review…');
    setButtonBusy(button, true, 'Submitting…');
    try{
      await api('/api/ads/submit.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify({csrf_token:csrf,ad_campaign_id:publicId})});
      status('Submitted for admin review.'); await loadList(); activateTab('campaigns');
    } finally {
      setButtonBusy(button, false, 'Submit');
    }
  }

  qsa('input,textarea,select').forEach(function(el){el.addEventListener('input',preview); el.addEventListener('change',preview);});
  qsa('[data-ads-tab-button]').forEach(function(btn){btn.addEventListener('click',function(){activateTab(btn.getAttribute('data-ads-tab-button'));});});
  qsa('[data-ads-tab-jump]').forEach(function(btn){btn.addEventListener('click',function(e){e.preventDefault(); if (btn.textContent.indexOf('New Campaign') !== -1) resetDraft(); else activateTab(btn.getAttribute('data-ads-tab-jump'));});});
  var picker=qs('[data-product-picker]');
  if(picker) picker.addEventListener('change',function(){selectedProduct=productById(picker.value); renderProductSummary(selectedProduct); preview();});
  var apply=qs('[data-apply-product]');
  if(apply) apply.addEventListener('click',function(){applyProduct(selectedProduct || productById(picker && picker.value || ''));});
  var uploadButton=qs('[data-upload-creative]');
  if(uploadButton) uploadButton.addEventListener('click',function(){uploadCreative();});
  var fileInput=qs('[data-creative-image-file]');
  if(fileInput) fileInput.addEventListener('change',function(){uploadStatus(fileInput.files && fileInput.files[0] ? 'Ready to upload: '+fileInput.files[0].name+' ('+fileSizeLabel(fileInput.files[0].size)+')' : '');});
  var imageInput=field('image_url');
  if(imageInput) imageInput.addEventListener('input',function(){imageSource=resolveImageSourceFromField(); updateImageSourceLabel();});
  var search=qs('[data-ads-search]'); if(search) search.addEventListener('input', renderList);
  var saveButton=qs('[data-save-draft]'); if(saveButton) saveButton.addEventListener('click',function(){saveDraft(saveButton).catch(function(e){status(e.message,true);});});
  var submitButton=qs('[data-submit-current]'); if(submitButton) submitButton.addEventListener('click',function(){submitCampaign('', submitButton).catch(function(e){status(e.message,true);});});
  var newDraftButton=qs('[data-new-draft]'); if(newDraftButton) newDraftButton.addEventListener('click',function(){saveDraft(newDraftButton).catch(function(e){status(e.message,true);});});

  updateImageSourceLabel();
  preview();
  loadProducts().catch(function(){});
  loadList().catch(function(e){status(e.message,true);});
  loadPerformance().catch(function(){});
})(window, document);
