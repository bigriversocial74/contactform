document.addEventListener('DOMContentLoaded',function(){
  if(!window.Microgifter){return;}
  var contactStatus=document.querySelector('[data-stage12-contact-status]');
  if(!contactStatus){return;}
  var toolBox=document.createElement('div');
  toolBox.className='mg-empty-state';
  toolBox.setAttribute('data-stage12-campaign-tools','');
  toolBox.innerHTML='<p>Select a campaign to view public links and operations.</p>';
  contactStatus.parentNode.insertBefore(toolBox,contactStatus);
  var detailBox=document.createElement('div');
  detailBox.className='mg-product-list';
  detailBox.setAttribute('data-stage12-campaign-detail','');
  contactStatus.parentNode.insertBefore(detailBox,contactStatus.nextSibling);
  var activeCampaignId='';
  function html(v){return String(v==null?'':v).replace(/[&<>'"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[c];});}
  function counts(c){var w=c.wallet_status||{};return '<small>'+Number((c.contacts||[]).length||0)+' contacts · '+Number(w.issued||0)+' issued · '+Number(w.claimed||0)+' claimed · '+Number(w.redeemed||0)+' completed</small>';}
  function rulesLabel(c){var r=c.rules||{};if(c.campaign_type!=='contest_giveaway')return '';var mode=String(r.mode||'');if(mode==='first_x')return 'First '+Number(r.winner_limit||c.quantity_limit||100)+' entries get the reward';if(mode==='random_draw')return 'Random drawing';if(mode==='manual_winner')return 'Manual winner selection';if(mode==='instant_reward')return 'Every entry gets the reward';return '';}
  function contestAction(c){var r=c.rules||{};if(c.campaign_type!=='contest_giveaway')return '';if(String(r.mode||'')!=='random_draw')return '';return '<div class="mg-heading-actions" style="margin-top:12px;justify-content:flex-start"><button class="mg-btn mg-btn-primary" type="button" data-random-contest-winner="'+html(c.id)+'">Pick random winner</button></div>';}
  function renderDetail(c){
    var tools=c.public_tools||{};
    var contestActions=contestAction(c);
    toolBox.innerHTML='<strong>Public campaign links</strong><p><a href="'+html(tools.public_url||'')+'" target="_blank" rel="noopener">'+html(tools.public_url||'')+'</a></p>'+(tools.qr_url?'<p>QR pickup link: <a href="'+html(tools.qr_url)+'" target="_blank" rel="noopener">'+html(tools.qr_url)+'</a></p>':'')+'<p>Campaign type: '+html(c.campaign_type)+' · Status: '+html(c.status)+(rulesLabel(c)?' · '+html(rulesLabel(c)):'')+'</p>'+contestActions;
    var contactRows=(c.contacts||[]).slice(0,6).map(function(x){return '<div class="mg-product-card"><span><strong>'+html(x.name||x.email)+'</strong><span>'+html(x.email)+' · '+html(x.source)+'</span><small>'+Number(x.wallet_count||0)+' wallet items · '+Number(x.redeemed_count||0)+' completed · '+Number(x.winner_count||0)+' winner rewards</small></span><span class="mg-card-meta"><em>'+html(x.opt_in_status)+'</em></span></div>';}).join('');
    var eventRows=(c.events||[]).slice(0,6).map(function(e){return '<div class="mg-product-card"><span><strong>'+html(e.event_type)+'</strong><span>'+html(e.contact_email||e.wallet_item_id||'Campaign event')+'</span><small>'+html(e.created_at||'')+'</small></span></div>';}).join('');
    detailBox.innerHTML='<div class="mg-product-card"><span><strong>'+html(c.title)+'</strong><span>'+html((c.reward_template||{}).title||'No reward template')+'</span>'+counts(c)+'</span><span class="mg-card-meta"><em>'+html(c.status)+'</em></span></div>'+(contactRows?'<h3>Recent contacts</h3>'+contactRows:'')+(eventRows?'<h3>Recent events</h3>'+eventRows:'');
  }
  async function load(campaignId){
    activeCampaignId=campaignId;
    toolBox.innerHTML='<p>Loading campaign tools...</p>';
    detailBox.innerHTML='';
    var response=await Microgifter.get('/api/merchant/campaign-detail.php?campaign_id='+encodeURIComponent(campaignId));
    var campaign=(response.data||response).campaign;
    if(campaign){renderDetail(campaign);}
  }
  async function pickRandomWinner(campaignId){
    var target=campaignId||activeCampaignId;
    if(!target){return;}
    toolBox.innerHTML='<p>Selecting random winner...</p>';
    try{var response=await Microgifter.post('/api/merchant/campaign-random-winner.php',{campaign_id:target});var data=(response.data||response);toolBox.innerHTML='<strong>'+html(response.message||'Random winner selected.')+'</strong><p>'+html(data.name||data.email||'Winner')+' received wallet item '+html(data.wallet_item_id||'')+'.</p>';await load(target);}catch(error){toolBox.innerHTML='<p>'+html(error.message||'Unable to select random winner.')+'</p>';}
  }
  document.addEventListener('click',function(event){
    var target=event.target.closest('[data-campaign-contact-id]');
    if(target){load(target.getAttribute('data-campaign-contact-id')).catch(function(error){toolBox.innerHTML='<p>'+html(error.message||'Unable to load campaign tools.')+'</p>';});}
    var random=event.target.closest('[data-random-contest-winner]');
    if(random){pickRandomWinner(random.getAttribute('data-random-contest-winner'));}
  });
});
