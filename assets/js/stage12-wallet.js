document.addEventListener('DOMContentLoaded',function(){
  var root=document.querySelector('[data-stage12-wallet]');
  if(!root||!window.Microgifter){return;}
  var list=root.querySelector('[data-wallet-list]');
  var status=root.querySelector('[data-wallet-status]');
  function html(v){return String(v==null?'':v).replace(/[&<>'"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[c];});}
  function setStatus(message){if(status){status.textContent=message||'';}}
  function label(s){return String(s||'').replace(/_/g,' ').replace(/\b\w/g,function(c){return c.toUpperCase();});}
  function step(item){
    if(item.status==='redeemed'){return 'Completed at merchant.';}
    if(item.status==='claimed'){return 'Claimed. Show this wallet item to the merchant.';}
    if(item.status==='expired'){return 'Expired.';}
    if(item.status==='cancelled'){return 'Cancelled.';}
    return 'Ready to claim.';
  }
  function card(item){
    var action=item.can_claim?'<button class="mg-btn mg-btn-primary" type="button" data-claim="'+html(item.id)+'">Claim</button>':'';
    var merchant=item.merchant_label||'Local merchant';
    var campaign=item.campaign_title||label(item.source_type);
    var timeline=item.timeline||{};
    var expires=timeline.expires_at?' · Expires '+html(timeline.expires_at):'';
    return '<div class="mg-product-card"><span><strong>'+html(item.title)+'</strong><span>'+html(merchant)+' · '+html(campaign)+' · '+html(item.display_value||'Reward')+'</span><small>'+html(step(item))+' '+html(item.redemption_instructions||'')+expires+'</small></span><span class="mg-card-meta"><em>'+html(label(item.status))+'</em>'+action+'</span></div>';
  }
  function summary(totals){return '<div class="mg-empty-state"><strong>Wallet summary</strong><p>'+Number(totals.claimable||0)+' claimable · '+Number(totals.claimed||0)+' claimed · '+Number(totals.redeemed||0)+' completed · '+Number(totals.expired||0)+' expired</p></div>';}
  async function load(){
    var response=await Microgifter.get('/api/account/wallet-items.php');
    var data=response.data||response;
    var items=data.items||[];
    list.innerHTML=summary(data.totals||{})+(items.length?items.map(card).join(''):'<div class="mg-empty-state"><p>No wallet items yet.</p></div>');
    list.querySelectorAll('[data-claim]').forEach(function(button){button.addEventListener('click',async function(){
      try{setStatus('Claiming reward...');var r=await Microgifter.post('/api/account/wallet-claim.php',{wallet_item_id:button.getAttribute('data-claim')});setStatus(r.message||'Reward claimed.');await load();}
      catch(error){setStatus(error.message||'Unable to claim reward.');}
    });});
  }
  load().catch(function(error){setStatus(error.message||'Unable to load wallet.');});
});
