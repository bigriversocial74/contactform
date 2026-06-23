document.addEventListener('DOMContentLoaded',function(){
  var root=document.querySelector('[data-stage12-agent-offers]');
  if(!root||!window.Microgifter){return;}
  var form=root.querySelector('[data-offer-search-form]');
  var list=root.querySelector('[data-offer-list]');
  var detail=root.querySelector('[data-offer-detail]');
  var status=root.querySelector('[data-offer-status]');
  function safe(v){return String(v==null?'':v).replace(/[&<>'"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[c];});}
  function note(message){if(status){status.textContent=message||'';}}
  function value(o){if(o.value_type==='percent'){return String(o.value_percent||0)+'%';}return (o.currency||'USD')+' '+(Number(o.value_amount_cents||0)/100).toFixed(2);}
  function offerCard(o){return '<div class="mg-product-card"><span><strong>'+safe(o.title)+'</strong><span>'+safe(o.reward_type)+' · '+safe(value(o))+'</span><small>'+safe(o.agent_summary||o.description||'Agent-discoverable local reward.')+'</small></span><span class="mg-card-meta"><button class="mg-btn mg-btn-soft" type="button" data-offer-detail-id="'+safe(o.id)+'">Details</button></span></div>';}
  function renderDetail(o){
    var cats=(o.agent_categories||[]).map(function(x){return '<span class="mg-chip">'+safe(x)+'</span>';}).join('');
    var useCases=(o.agent_use_cases||[]).map(function(x){return '<span class="mg-chip">'+safe(x)+'</span>';}).join('');
    var add=o.can_add_to_wallet?'<button class="mg-btn mg-btn-primary" type="button" data-add-wallet="'+safe(o.id)+'">Add to wallet</button>':'<span class="mg-muted">Wallet add unavailable</span>';
    detail.innerHTML='<div class="mg-product-card"><span><strong>'+safe(o.title)+'</strong><span>'+safe(o.merchant_label||'Local merchant')+' · '+safe(o.display_value||value(o))+'</span><small>'+safe(o.agent_summary||o.description||'')+'</small></span><span class="mg-card-meta">'+add+'</span></div><div class="mg-account-section"><h3>Categories</h3><div class="mg-chip-list">'+cats+'</div></div><div class="mg-account-section"><h3>Use cases</h3><div class="mg-chip-list">'+useCases+'</div></div><div class="mg-empty-state"><p>'+safe(o.redemption_instructions||'Add this reward to your wallet, claim it, then show it to the merchant when ready.')+'</p></div>';
    detail.querySelectorAll('[data-add-wallet]').forEach(function(button){button.addEventListener('click',async function(){
      try{note('Adding offer to wallet...');var r=await Microgifter.post('/api/public/wallet/add.php',{offer_id:button.getAttribute('data-add-wallet')});note(r.message||'Added to wallet.');}
      catch(error){note(error.message||'Unable to add offer. Sign in may be required.');}
    });});
  }
  async function loadDetail(id){var r=await Microgifter.get('/api/public/offers/detail.php?offer_id='+encodeURIComponent(id));var o=(r.data||r).offer;if(o){renderDetail(o);}}
  async function search(){
    var data=Object.fromEntries(new FormData(form).entries());
    var q=new URLSearchParams();
    if(data.q){q.set('q',data.q);}if(data.reward_type){q.set('reward_type',data.reward_type);}q.set('limit','24');
    note('Searching offers...');
    var r=await Microgifter.get('/api/public/offers/search.php?'+q.toString());
    var offers=(r.data||r).offers||[];
    list.innerHTML=offers.length?offers.map(offerCard).join(''):'<div class="mg-empty-state"><p>No agent-discoverable offers matched.</p></div>';
    note(offers.length+' offers loaded.');
    list.querySelectorAll('[data-offer-detail-id]').forEach(function(button){button.addEventListener('click',function(){loadDetail(button.getAttribute('data-offer-detail-id')).catch(function(error){note(error.message||'Unable to load offer.');});});});
  }
  form&&form.addEventListener('submit',function(event){event.preventDefault();search().catch(function(error){note(error.message||'Unable to search offers.');});});
  search().catch(function(error){note(error.message||'Unable to load offers.');});
});
