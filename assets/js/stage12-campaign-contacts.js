document.addEventListener('DOMContentLoaded',function(){
  if(!window.Microgifter){return;}
  var list=document.querySelector('[data-stage12-contact-list]');
  var status=document.querySelector('[data-stage12-contact-status]');
  if(!list||!status){return;}
  function html(v){return String(v==null?'':v).replace(/[&<>'"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[c];});}
  function setStatus(message){status.textContent=message||'';}
  function card(c){
    var action=c.source==='contest_entry'&&c.winner_action_allowed?'<button class="mg-btn mg-btn-primary" type="button" data-award="'+html(c.id)+'" data-campaign="'+html(c.campaign_id)+'">Issue prize</button>':'';
    return '<div class="mg-product-card"><span><strong>'+html(c.name||c.email)+'</strong><span>'+html(c.email)+' · '+html(c.source)+'</span><small>'+Number(c.wallet_count||0)+' wallet items · '+Number(c.redeemed_count||0)+' redeemed · '+Number(c.winner_count||0)+' winner rewards</small></span><span class="mg-card-meta"><em>'+html(c.opt_in_status)+'</em>'+action+'</span></div>';
  }
  async function load(campaignId){
    if(window.MicrogifterCampaignTabs&&typeof window.MicrogifterCampaignTabs.activate==='function'){window.MicrogifterCampaignTabs.activate('contacts',{scroll:true});}
    setStatus('Loading campaign contacts...');
    var url='/api/merchant/campaign-contacts.php'+(campaignId?'?campaign_id='+encodeURIComponent(campaignId):'');
    var response=await Microgifter.get(url);
    var contacts=(response.data||response).contacts||[];
    list.innerHTML=contacts.length?contacts.map(card).join(''):'<div class="mg-empty-state"><p>No contacts yet.</p></div>';
    setStatus(contacts.length+' contacts loaded.');
    list.querySelectorAll('[data-award]').forEach(function(button){button.addEventListener('click',async function(){
      try{setStatus('Issuing prize...');var r=await Microgifter.post('/api/merchant/campaign-winner.php',{campaign_id:button.getAttribute('data-campaign'),contact_id:button.getAttribute('data-award')});setStatus(r.message||'Prize issued.');await load(button.getAttribute('data-campaign'));}
      catch(error){setStatus(error.message||'Unable to issue prize.');}
    });});
  }
  document.addEventListener('click',function(event){
    var target=event.target.closest('[data-campaign-contact-id]');
    if(target){load(target.getAttribute('data-campaign-contact-id')).catch(function(error){setStatus(error.message||'Unable to load contacts.');});}
  });
});
