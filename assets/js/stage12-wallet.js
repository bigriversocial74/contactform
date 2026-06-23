document.addEventListener('DOMContentLoaded',function(){
  var root=document.querySelector('[data-stage12-wallet]');
  if(!root||!window.Microgifter){return;}
  var list=root.querySelector('[data-wallet-list]');
  var status=root.querySelector('[data-wallet-status]');
  function html(v){return String(v==null?'':v).replace(/[&<>'"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[c];});}
  function setStatus(message){if(status){status.textContent=message||'';}}
  function money(cents,currency){return (currency||'USD')+' '+(Number(cents||0)/100).toFixed(2);}
  function card(item){
    var action=item.status==='issued'||item.status==='viewed'?'<button class="mg-btn mg-btn-primary" type="button" data-claim="'+html(item.id)+'">Claim</button>':'';
    return '<div class="mg-product-card"><span><strong>'+html(item.title)+'</strong><span>'+html(item.campaign_title||item.source_type)+' · '+html(money(item.value_cents,item.currency))+'</span><small>'+html(item.redemption_instructions||'Show this wallet item to the merchant when redeeming.')+'</small></span><span class="mg-card-meta"><em>'+html(item.status)+'</em>'+action+'</span></div>';
  }
  async function load(){
    var response=await Microgifter.get('/api/account/wallet-items.php');
    var items=(response.data||response).items||[];
    list.innerHTML=items.length?items.map(card).join(''):'<div class="mg-empty-state"><p>No wallet items yet.</p></div>';
    list.querySelectorAll('[data-claim]').forEach(function(button){button.addEventListener('click',async function(){
      try{setStatus('Claiming reward...');var r=await Microgifter.post('/api/account/wallet-claim.php',{wallet_item_id:button.getAttribute('data-claim')});setStatus(r.message||'Reward claimed.');await load();}
      catch(error){setStatus(error.message||'Unable to claim reward.');}
    });});
  }
  load().catch(function(error){setStatus(error.message||'Unable to load wallet.');});
});
