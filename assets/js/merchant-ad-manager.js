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
  function status(message, error){var node=qs('[data-ads-status]'); if(node){node.textContent=message||''; node.style.color=error?'#b91c1c':'#64748b';}}
  async function api(path, options){
    var res = await fetch(path, Object.assign({credentials:'same-origin'}, options||{}));
    var out = await res.json().catch(function(){return {ok:false,message:'Invalid server response'};});
    if (!out.ok) throw new Error(out.message || 'Request failed');
    return out.data || {};
  }
  function activateTab(name){
    name = name || 'create';
    qsa('[data-ads-tab-button]').forEach(function(btn){btn.classList.toggle('is-active', btn.getAttribute('data-ads-tab-button') === name);});
    qsa('[data-ads-tab-panel]').forEach(function(panel){panel.classList.toggle('is-active', panel.getAttribute('data-ads-tab-panel') === name);});
    if (name === 'preview') preview();
  }
  function resetDraft(){
    selectedId='';
    qs('[data-ad-form]').reset();
    qsa('[name="placements[]"]').forEach(function(i){i.checked=i.value==='feed_sponsored_card'||i.value==='sidebar_sponsored_card';});
    var picker = qs('[data-product-picker]');
    var apply = qs('[data-apply-product]');
    var summary = qs('[data-product-summary]');
    if (picker) picker.value = '';
    if (apply) apply.disabled = true;
    if (summary) { summary.hidden = true; summary.innerHTML = ''; }
    status('New draft ready.'); preview(); activateTab('create');
  }
  function checkedPlacements(){return qsa('[name="placements[]"]:checked').map(function(input){return input.value;});}
  function formPayload(){
    return {
      csrf_token: csrf,
      title: qs('[name="title"]').value,
      headline: qs('[name="headline"]').value,
      description: qs('[name="description"]').value,
      objective: qs('[name="objective"]').value,
      budget_type: qs('[name="budget_type"]').value,
      budget_amount: qs('[name="budget_amount"]').value,
      claim_cap: qs('[name="claim_cap"]').value,
      redemption_cap: qs('[name="redemption_cap"]').value,
      starts_at: qs('[name="starts_at"]').value,
      ends_at: qs('[name="ends_at"]').value,
      image_url: qs('[name="image_url"]').value,
      cta_label: qs('[name="cta_label"]').value,
      destination_url: qs('[name="destination_url"]').value,
      target_zone_id: qs('[name="target_zone_id"]').value,
      placements: checkedPlacements(),
      targeting: {phase:'phase1', controlled:true}
    };
  }
  function preview(){
    var payload = formPayload();
    var item = {public_id:selectedId||'preview', title:payload.title, objective:payload.objective, placement_key:payload.placements[0]||'feed_sponsored_card', merchant:{merchant_name:root.getAttribute('data-merchant-name')||'Microgifter Merchant'}, creative:{headline:payload.headline||payload.title, description:payload.description, image_url:payload.image_url, cta_label:payload.cta_label||'View Offer', destination_url:payload.destination_url, sponsored_label:'Sponsored'}};
    qsa('[data-ads-preview],[data-ads-preview-secondary]').forEach(function(target){if (window.Microgifter.renderSponsoredCampaignCard) target.innerHTML = window.Microgifter.renderSponsoredCampaignCard(item,{compact:false});});
  }
  function productLabel(product){
    var source = product.source_label || product.source || 'Product';
    var value = product.value_label ? ' · ' + product.value_label : '';
    return source + ': ' + (product.title || product.headline || 'Untitled') + value;
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
      var data = await api('/api/ads/merchant-products.php?status=active');
      productsCache = Array.isArray(data.products) ? data.products : [];
      if (!productsCache.length) {
        picker.innerHTML = '<option value="">No active products or rewards found</option>';
        if (apply) apply.disabled = true;
        return;
      }
      picker.innerHTML = '<option value="">Select product / reward…</option>' + productsCache.map(function(product){return '<option value="'+esc(product.id || '')+'">'+esc(productLabel(product))+'</option>';}).join('');
      setProductPickerState();
    } catch (error) {
      productsCache = [];
      picker.innerHTML = '<option value="">Products unavailable — build manually</option>';
      if (apply) apply.disabled = true;
    }
  }
  function applySelectedProduct(){
    var product = selectedProduct();
    if (!product) return;
    qs('[name="title"]').value = product.title || product.headline || qs('[name="title"]').value;
    qs('[name="headline"]').value = product.headline || product.title || qs('[name="headline"]').value;
    qs('[name="description"]').value = product.ad_description || product.description || qs('[name="description"]').value;
    if (product.image_url) qs('[name="image_url"]').value = product.image_url;
    if (product.cta_label) qs('[name="cta_label"]').value = product.cta_label;
    if (product.destination_url) qs('[name="destination_url"]').value = product.destination_url;
    status('Applied '+(product.source_label || product.source || 'product')+' to campaign draft.');
    preview();
  }
  function placementText(c){return (c.placements||[]).join(', ').replace(/_/g,' ') || 'None selected';}
  function money(value){var n=Number(value||0); return n ? '$'+n.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}) : '—';}
  function dateShort(value){if(!value)return '—'; try{return new Intl.DateTimeFormat(undefined,{month:'short',day:'numeric',year:'numeric'}).format(new Date(String(value).replace(' ','T')));}catch(e){return String(value).slice(0,10);}}
  function campaignRow(c){
    var creative = c.creative || {};
    return '<article class="mg-ads-row mg-ads-campaign-table-row" data-campaign-id="'+esc(c.public_id)+'">'
      + '<div><strong>'+esc(c.title)+'</strong><span>'+esc(creative.headline||placementText(c))+'</span></div>'
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
    qs('[name="title"]').value = c.title || '';
    qs('[name="headline"]').value = creative.headline || c.title || '';
    qs('[name="description"]').value = creative.description || '';
    qs('[name="objective"]').value = c.objective || 'claim_growth';
    qs('[name="budget_type"]').value = c.budget_type || 'none';
    qs('[name="budget_amount"]').value = c.budget_amount || '';
    qs('[name="claim_cap"]').value = c.claim_cap || '';
    qs('[name="redemption_cap"]').value = c.redemption_cap || '';
    qs('[name="starts_at"]').value = c.starts_at ? String(c.starts_at).replace(' ','T').slice(0,16) : '';
    qs('[name="ends_at"]').value = c.ends_at ? String(c.ends_at).replace(' ','T').slice(0,16) : '';
    qs('[name="image_url"]').value = creative.image_url || '';
    qs('[name="cta_label"]').value = creative.cta_label || 'Claim Reward';
    qs('[name="destination_url"]').value = creative.destination_url || '';
    qs('[name="target_zone_id"]').value = c.target_zone_id || '';
    qsa('[name="placements[]"]').forEach(function(input){input.checked=(c.placements||[]).indexOf(input.value)!==-1;});
    var picker = qs('[data-product-picker]');
    if (picker) picker.value = '';
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
  async function saveDraft(){
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
  qsa('input,textarea,select').forEach(function(el){el.addEventListener('input',preview); el.addEventListener('change',preview);});
  qsa('[data-ads-tab-button]').forEach(function(btn){btn.addEventListener('click',function(){activateTab(btn.getAttribute('data-ads-tab-button'));});});
  qsa('[data-ads-tab-jump]').forEach(function(btn){btn.addEventListener('click',function(e){e.preventDefault(); if (btn.textContent.indexOf('New Campaign') !== -1) resetDraft(); else activateTab(btn.getAttribute('data-ads-tab-jump'));});});
  var search=qs('[data-ads-search]'); if(search) search.addEventListener('input', renderList);
  var picker=qs('[data-product-picker]'); if(picker) picker.addEventListener('change', setProductPickerState);
  var apply=qs('[data-apply-product]'); if(apply) apply.addEventListener('click', applySelectedProduct);
  qs('[data-save-draft]').addEventListener('click',function(){saveDraft().catch(function(e){status(e.message,true);});});
  qs('[data-submit-current]').addEventListener('click',function(){submitCampaign('').catch(function(e){status(e.message,true);});});
  qs('[data-new-draft]').addEventListener('click',function(){saveDraft().catch(function(e){status(e.message,true);});});
  preview(); loadProducts().catch(function(){}); loadList().catch(function(e){status(e.message,true);}); loadPerformance().catch(function(){});
})(window, document);
