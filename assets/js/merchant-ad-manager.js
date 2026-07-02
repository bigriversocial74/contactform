window.Microgifter = window.Microgifter || {};
(function(window, document){
  'use strict';
  var root = document.querySelector('[data-ads-manager]');
  if (!root) return;
  var csrf = root.getAttribute('data-csrf-token') || '';
  var selectedId = '';
  var campaignsCache = [];
  var productsCache = [];
  function esc(value){return String(value == null ? '' : value).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
  function qs(sel, scope){return (scope||root).querySelector(sel);}
  function qsa(sel, scope){return Array.prototype.slice.call((scope||root).querySelectorAll(sel));}
  function on(node, event, handler){ if (node) node.addEventListener(event, handler); }
  function field(name){var node=qs('[name="'+name+'"]'); return node ? node.value : '';}
  function setField(name, value){var node=qs('[name="'+name+'"]'); if (node) node.value = value == null ? '' : value;}
  function status(message, error){var node=qs('[data-ads-status]'); if(node){node.textContent=message||''; node.style.color=error?'#b91c1c':'#64748b';}}
  function uploadStatus(message, error){var node=qs('[data-creative-upload-status]'); if(node){node.textContent=message||''; node.style.color=error?'#b91c1c':'#64748b';}}
  function previewImageUrl(value){
    var raw = String(value || '').trim();
    if (!raw || /[\u0000-\u001f\u007f]/.test(raw)) return '';
    try {
      var parsed = new URL(raw, window.location.origin);
      if (['http:','https:'].indexOf(parsed.protocol) === -1 || parsed.username || parsed.password) return '';
      return raw.charAt(0) === '/' ? parsed.pathname + parsed.search + parsed.hash : parsed.href;
    } catch (error) {
      return '';
    }
  }
  function initials(value){return String(value || 'MG').split(/\s+/).filter(Boolean).slice(0,2).map(function(part){return part.charAt(0);}).join('').toUpperCase() || 'MG';}
  function setNodeText(selector, value, scope){var node=qs(selector, scope); if(node) node.textContent=value || '';}
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
    var form = qs('[data-ad-form]');
    if (!form) return;
    selectedId='';
    form.reset();
    qsa('[name="placements[]"]').forEach(function(i){i.checked=i.value==='feed_sponsored_card'||i.value==='sidebar_sponsored_card';});
    var picker = qs('[data-product-picker]');
    var apply = qs('[data-apply-product]');
    var summary = qs('[data-product-summary]');
    var uploadInput = qs('[data-creative-upload-input]');
    if (picker) picker.value = '';
    if (apply) apply.disabled = true;
    if (summary) { summary.hidden = true; summary.innerHTML = ''; }
    if (uploadInput) uploadInput.value = '';
    uploadStatus('');
    status('New draft ready.'); preview(); activateTab('create');
  }
  function checkedPlacements(){return qsa('[name="placements[]"]:checked').map(function(input){return input.value;});}
  function formPayload(){
    return {
      csrf_token: csrf,
      title: field('title'),
      headline: field('headline'),
      description: field('description'),
      objective: field('objective'),
      budget_type: field('budget_type'),
      budget_amount: field('budget_amount'),
      claim_cap: field('claim_cap'),
      redemption_cap: field('redemption_cap'),
      starts_at: field('starts_at'),
      ends_at: field('ends_at'),
      image_url: field('image_url'),
      cta_label: field('cta_label'),
      destination_url: field('destination_url'),
      target_zone_id: field('target_zone_id'),
      placements: checkedPlacements(),
      targeting: {phase:'phase1', controlled:true}
    };
  }
  function coverPreview(payload){
    var card = qs('[data-cover-preview]');
    if (!card) return;
    payload = payload || formPayload();
    var merchantName = root.getAttribute('data-merchant-name') || 'Microgifter Merchant';
    var media = qs('[data-cover-preview-media]', card);
    var headline = payload.headline || payload.title || 'Featured Local Reward';
    var description = payload.description || 'Claim this local reward, save it to your wallet, and redeem it with the merchant.';
    var cta = payload.cta_label || 'View Offer';
    var destination = payload.destination_url || '/feed.php';
    var image = previewImageUrl(payload.image_url);
    setNodeText('[data-cover-preview-merchant]', merchantName, card);
    setNodeText('[data-cover-preview-headline]', headline, card);
    setNodeText('[data-cover-preview-description]', description, card);
    setNodeText('[data-cover-preview-cta]', cta, card);
    setNodeText('[data-cover-preview-destination]', destination, card);
    if (!media) return;
    if (!image) {
      media.classList.add('is-empty');
      media.classList.remove('is-image-missing');
      media.innerHTML = '<span>'+esc(initials(headline || merchantName))+'</span>';
      return;
    }
    media.classList.remove('is-empty','is-image-missing');
    media.innerHTML = '<img src="'+esc(image)+'" alt="" loading="lazy" data-cover-preview-image>';
    var img = qs('[data-cover-preview-image]', media);
    if (img) {
      img.addEventListener('error', function(){
        media.classList.add('is-image-missing');
        media.innerHTML = '<span>Image unavailable</span>';
      }, {once:true});
    }
  }
  function ensurePlacementPreviewBoard(){
    var secondary = qs('[data-ads-preview-secondary]');
    if (!secondary) return null;
    var board = qs('[data-placement-preview-board]', secondary);
    if (board) return board;
    secondary.innerHTML = '<div class="mg-ads-placement-board mg-ads-live-preview-board" data-placement-preview-board>'
      + '<article class="mg-ads-placement-card"><span class="mg-ads-eyebrow">Feed Surface</span><h2>Feed sponsored card</h2><p class="mg-ads-muted">Main customer feed preview using the current campaign draft.</p><div class="mg-sponsored-placement" data-placement-preview="feed_sponsored_card"></div></article>'
      + '<article class="mg-ads-placement-card"><span class="mg-ads-eyebrow">Sidebar Surface</span><h2>Sidebar sponsored card</h2><p class="mg-ads-muted">Compact right-column preview using the same creative.</p><div class="mg-sponsored-placement mg-placement-preview-sidebar" data-placement-preview="sidebar_sponsored_card"></div></article>'
      + '</div>';
    return qs('[data-placement-preview-board]', secondary);
  }
  function renderPlacementPreviews(item){
    ensurePlacementPreviewBoard();
    qsa('[data-placement-preview]').forEach(function(target){
      var placement = target.getAttribute('data-placement-preview') || 'feed_sponsored_card';
      if (!window.Microgifter.renderSponsoredCampaignCard) {
        target.innerHTML = '<div class="mg-ads-empty">Sponsored preview renderer is unavailable.</div>';
        return;
      }
      var previewItem = Object.assign({}, item, {placement_key: placement});
      target.innerHTML = window.Microgifter.renderSponsoredCampaignCard(previewItem, {compact: placement === 'sidebar_sponsored_card'});
    });
  }
  function preview(){
    if (!qs('[data-ad-form]')) return;
    var payload = formPayload();
    var item = {public_id:selectedId||'preview', title:payload.title, objective:payload.objective, placement_key:payload.placements[0]||'feed_sponsored_card', merchant:{merchant_name:root.getAttribute('data-merchant-name')||'Microgifter Merchant'}, creative:{headline:payload.headline||payload.title, description:payload.description, image_url:payload.image_url, cta_label:payload.cta_label||'View Offer', destination_url:payload.destination_url, sponsored_label:'Sponsored'}};
    coverPreview(payload);
    qsa('[data-ads-preview]').forEach(function(target){if (window.Microgifter.renderSponsoredCampaignCard) target.innerHTML = window.Microgifter.renderSponsoredCampaignCard(item,{compact:false});});
    renderPlacementPreviews(item);
  }
  function productLabel(product){
    var source = product.source_label || product.source || 'Product';
    var value = product.value_label ? ' · ' + product.value_label : '';
    return source + ': ' + (product.title || product.headline || 'Untitled') + value;
  }
  function pickerSourceSummary(sources){
    if (!sources || typeof sources !== 'object') return '';
    var labels = [];
    Object.keys(sources).forEach(function(key){labels.push(key.replace(/_/g,' ') + ': ' + Number(sources[key] || 0));});
    return labels.length ? ' Sources checked — ' + labels.join(', ') + '.' : '';
  }
  function selectedProduct(){
    var picker = qs('[data-product-picker]');
    if (!picker || !picker.value) return null;
    return productsCache.find(function(product){return String(product.id || '') === String(picker.value);}) || null;
  }
  function renderProductSummary(product){
    var summary = qs('[data-product-summary]');
    if (!summary) return;
    if (!product) { summary.hidden = true; summary.innerHTML = ''; return; }
    summary.hidden = false;
    summary.innerHTML = '<strong>'+esc(product.title || product.headline || 'Selected product')+'</strong>'
      + '<span>'+esc(product.source_label || product.source || 'Product')+'</span>'
      + (product.ad_description || product.description ? '<p>'+esc(product.ad_description || product.description)+'</p>' : '')
      + (product.value_label ? '<small>'+esc(product.value_label)+'</small>' : '');
  }
  function setProductPickerState(){
    var apply = qs('[data-apply-product]');
    var product = selectedProduct();
    if (apply) apply.disabled = !product;
    renderProductSummary(product);
  }
  async function loadProducts(){
    var picker = qs('[data-product-picker]');
    var apply = qs('[data-apply-product]');
    if (!picker) return;
    try {
      var data = await api('/api/ads/merchant-products.php?status=all');
      productsCache = Array.isArray(data.products) ? data.products : [];
      if (!productsCache.length) {
        picker.innerHTML = '<option value="">No products, rewards, or campaigns found</option>';
        if (apply) apply.disabled = true;
        status('No picker items were returned.' + pickerSourceSummary(data.sources), true);
        return;
      }
      picker.innerHTML = '<option value="">Select product / reward / campaign…</option>' + productsCache.map(function(product){return '<option value="'+esc(product.id || '')+'">'+esc(productLabel(product))+'</option>';}).join('');
      status('Loaded '+productsCache.length+' picker item'+(productsCache.length === 1 ? '' : 's')+'.');
      setProductPickerState();
    } catch (error) {
      productsCache = [];
      picker.innerHTML = '<option value="">Products unavailable — build manually</option>';
      if (apply) apply.disabled = true;
      status('Product picker source failed: '+(error && error.message ? error.message : 'Unable to load merchant products.'), true);
    }
  }
  function applySelectedProduct(){
    var product = selectedProduct();
    if (!product) return;
    setField('title', product.title || product.headline || field('title'));
    setField('headline', product.headline || product.title || field('headline'));
    setField('description', product.ad_description || product.description || field('description'));
    if (product.image_url) setField('image_url', product.image_url);
    if (product.cta_label) setField('cta_label', product.cta_label);
    if (product.destination_url) setField('destination_url', product.destination_url);
    status('Applied '+(product.source_label || product.source || 'product')+' to campaign draft.');
    preview();
  }
  async function uploadCreativeImage(){
    var input = qs('[data-creative-upload-input]');
    var button = qs('[data-upload-creative-image]');
    if (!input || !input.files || !input.files.length) { uploadStatus('Choose an image before uploading.', true); return; }
    var file = input.files[0];
    var allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    if (file.type && allowed.indexOf(file.type) === -1) { uploadStatus('Use JPG, PNG, GIF, or WebP image files.', true); return; }
    if (file.size > 8 * 1024 * 1024) { uploadStatus('Creative image must be 8MB or smaller.', true); return; }
    var body = new FormData();
    body.append('csrf_token', csrf);
    body.append('creative_image', file);
    if (button) button.disabled = true;
    uploadStatus('Uploading creative image…');
    try {
      var data = await api('/api/ads/upload-creative.php', {method:'POST', headers:{'X-CSRF-TOKEN':csrf}, body:body});
      if (!data.url) throw new Error('Upload succeeded but no image URL was returned.');
      setField('image_url', data.url);
      uploadStatus('Image uploaded and added to campaign preview.');
      status('Creative image uploaded. Save the campaign to persist it.');
      preview();
    } catch (error) {
      uploadStatus(error && error.message ? error.message : 'Unable to upload creative image.', true);
    } finally {
      if (button) button.disabled = false;
    }
  }
  function placementText(c){return (c.placements||[]).join(', ').replace(/_/g,' ') || 'None selected';}
  function money(value){var n=Number(value||0); return n ? '$'+n.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}) : '—';}
  function dateShort(value){if(!value)return '—'; try{return new Intl.DateTimeFormat(undefined,{month:'short',day:'numeric',year:'numeric'}).format(new Date(String(value).replace(' ','T')));}catch(e){return String(value).slice(0,10);}}
  function campaignThumbHtml(c){
    var creative = c.creative || {};
    var label = initials(creative.headline || c.title || 'Ad');
    var image = previewImageUrl(creative.image_url || '');
    return '<span class="mg-ads-row-thumb">' + (image ? '<img src="'+esc(image)+'" alt="" loading="lazy">' : esc(label)) + '</span>';
  }
  function campaignRow(c){
    var creative = c.creative || {};
    return '<article class="mg-ads-row mg-ads-campaign-table-row" data-campaign-id="'+esc(c.public_id)+'">'
      + '<div class="mg-ads-campaign-name">'+campaignThumbHtml(c)+'<div><strong>'+esc(c.title)+'</strong><span>'+esc(creative.headline||placementText(c))+'</span></div></div>'
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
    setField('title', c.title || '');
    setField('headline', creative.headline || c.title || '');
    setField('description', creative.description || '');
    setField('objective', c.objective || 'claim_growth');
    setField('budget_type', c.budget_type || 'none');
    setField('budget_amount', c.budget_amount || '');
    setField('claim_cap', c.claim_cap || '');
    setField('redemption_cap', c.redemption_cap || '');
    setField('starts_at', c.starts_at ? String(c.starts_at).replace(' ','T').slice(0,16) : '');
    setField('ends_at', c.ends_at ? String(c.ends_at).replace(' ','T').slice(0,16) : '');
    setField('image_url', creative.image_url || '');
    setField('cta_label', creative.cta_label || 'Claim Reward');
    setField('destination_url', creative.destination_url || '');
    setField('target_zone_id', c.target_zone_id || '');
    qsa('[name="placements[]"]').forEach(function(input){input.checked=(c.placements||[]).indexOf(input.value)!==-1;});
    var picker = qs('[data-product-picker]');
    var uploadInput = qs('[data-creative-upload-input]');
    if (picker) picker.value = '';
    if (uploadInput) uploadInput.value = '';
    uploadStatus('');
    setProductPickerState();
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
    qsa('[data-submit]', list).forEach(function(btn){btn.onclick=function(){submitCampaign(btn.closest('[data-campaign-id]').getAttribute('data-campaign-id'));};});
  }
  async function loadList(){
    var list = qs('[data-ads-list]');
    if (!list) return;
    var data = await api('/api/ads/list.php?limit=60');
    if (!data.schema_ready) { list.innerHTML='<div class="mg-ads-alert">Campaign Ads Manager migration is required before campaigns can be saved.</div>'; return; }
    campaignsCache = data.campaigns || [];
    renderList();
  }
  async function loadPerformance(){
    var data = await api('/api/ads/performance.php');
    var p = data.performance || data.summary || {};
    ['impressions','clicks','claims','redemptions'].forEach(function(key){qsa('[data-kpi="'+key+'"]').forEach(function(node){node.textContent=Number(p[key]||0).toLocaleString();});});
  }
  async function saveDraft(){
    if (!qs('[data-ad-form]')) return;
    status('Saving campaign…');
    var path = selectedId ? '/api/ads/update.php' : '/api/ads/create.php';
    var body = formPayload(); if (selectedId) body.ad_campaign_id = selectedId;
    var data = await api(path,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify(body)});
    selectedId = data.campaign && data.campaign.public_id || selectedId;
    status('Campaign saved.'); await loadList(); await loadPerformance(); preview();
  }
  async function submitCampaign(id){
    var publicId = id || selectedId;
    if (!publicId) { status('Save the draft before submitting.', true); return; }
    status('Submitting for admin review…');
    await api('/api/ads/submit.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify({csrf_token:csrf,ad_campaign_id:publicId})});
    status('Submitted for admin review.'); await loadList(); activateTab('campaigns');
  }
  qsa('input,textarea,select').forEach(function(el){on(el,'input',preview); on(el,'change',preview);});
  qsa('[data-ads-tab-button]').forEach(function(btn){on(btn,'click',function(){activateTab(btn.getAttribute('data-ads-tab-button'));});});
  qsa('[data-ads-tab-jump]').forEach(function(btn){on(btn,'click',function(e){e.preventDefault(); if (btn.textContent.indexOf('New Campaign') !== -1) resetDraft(); else activateTab(btn.getAttribute('data-ads-tab-jump'));});});
  on(qs('[data-ads-search]'),'input', renderList);
  on(qs('[data-product-picker]'),'change', setProductPickerState);
  on(qs('[data-apply-product]'),'click', applySelectedProduct);
  var uploadInput=qs('[data-creative-upload-input]');
  on(uploadInput,'change',function(){uploadStatus(uploadInput.files && uploadInput.files.length ? 'Ready to upload '+uploadInput.files[0].name+'.' : '');});
  on(qs('[data-upload-creative-image]'),'click',function(){uploadCreativeImage().catch(function(e){uploadStatus(e.message,true);});});
  on(qs('[data-save-draft]'),'click',function(){saveDraft().catch(function(e){status(e.message,true);});});
  on(qs('[data-submit-current]'),'click',function(){submitCampaign('').catch(function(e){status(e.message,true);});});
  on(qs('[data-new-draft]'),'click',function(){saveDraft().catch(function(e){status(e.message,true);});});
  preview(); loadProducts().catch(function(){}); loadList().catch(function(e){status(e.message,true);}); loadPerformance().catch(function(){});
})(window, document);
