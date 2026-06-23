document.addEventListener('DOMContentLoaded',function(){
  if(!window.Microgifter){return;}
  var campaignList=document.querySelector('[data-stage12-campaign-list]');
  var contactStatus=document.querySelector('[data-stage12-contact-status]');
  if(!campaignList||!contactStatus){return;}
  var toolBox=document.createElement('div');
  toolBox.className='mg-empty-state';
  toolBox.setAttribute('data-stage12-campaign-tools','');
  toolBox.innerHTML='<p>Select a campaign to view public links and operations.</p>';
  contactStatus.parentNode.insertBefore(toolBox,contactStatus);
  var detailBox=document.createElement('div');
  detailBox.className='mg-product-list';
  detailBox.setAttribute('data-stage12-campaign-detail','');
  contactStatus.parentNode.insertBefore(detailBox,contactStatus.nextSibling);
  function html(v){return String(v==null?'':v).replace(/[&<>'"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[c];});}
  function counts(c){var w=c.wallet_status||{};return '<small>'+Number(c.contacts.length||0)+' contacts · '+Number(w.issued||0)+' issued · '+Number(w.claimed||0)+' claimed · '+Number(w.redeemed||0)+' completed</small>';}
  function renderDetail(c){
    var tools=c.public_tools||{};
    toolBox.innerHTML='<strong>Public campaign links</strong><p><a href="'+html(tools.public_url||'')+'" target="_blank" rel="noopener">'+html(tools.public_url||'')+'</a></p>'+(tools.qr_url?'<p>QR pickup link: <a href="'+html(tools.qr_url)+'" target="_blank" rel="noopener">'+html(tools.qr_url)+'</a></p>':'')+'<p>Campaign type: '+html(c.campaign_type)+' · Status: '+html(c.status)+'</p>';
    var contactRows=(c.contacts||[]).slice(0,6).map(function(x){return '<div class="mg-product-card"><span><strong>'+html(x.name||x.email)+'</strong><span>'+html(x.email)+' · '+html(x.source)+'</span><small>'+Number(x.wallet_count||0)+' wallet items · '+Number(x.redeemed_count||0)+' completed</small></span><span class="mg-card-meta"><em>'+html(x.opt_in_status)+'</em></span></div>';}).join('');
    var eventRows=(c.events||[]).slice(0,6).map(function(e){return '<div class="mg-product-card"><span><strong>'+html(e.event_type)+'</strong><span>'+html(e.contact_email||e.wallet_item_id||'Campaign event')+'</span><small>'+html(e.created_at||'')+'</small></span></div>';}).join('');
    detailBox.innerHTML='<div class="mg-product-card"><span><strong>'+html(c.title)+'</strong><span>'+html((c.reward_template||{}).title||'No reward template')+'</span>'+counts(c)+'</span><span class="mg-card-meta"><em>'+html(c.status)+'</em></span></div>'+(contactRows?'<h3>Recent contacts</h3>'+contactRows:'')+(eventRows?'<h3>Recent events</h3>'+eventRows:'');
  }
  async function load(campaignId){
    toolBox.innerHTML='<p>Loading campaign tools...</p>';
    detailBox.innerHTML='';
    var response=await Microgifter.get('/api/merchant/campaign-detail.php?campaign_id='+encodeURIComponent(campaignId));
    var campaign=(response.data||response).campaign;
    if(campaign){renderDetail(campaign);}
  }
  campaignList.addEventListener('click',function(event){var target=event.target.closest('[data-campaign-id]');if(target){load(target.getAttribute('data-campaign-id')).catch(function(error){toolBox.innerHTML='<p>'+html(error.message||'Unable to load campaign tools.')+'</p>';});}});
});
