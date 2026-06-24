document.addEventListener('DOMContentLoaded',function(){
  if(!window.Microgifter)return;
  var form=document.querySelector('[data-stage12-campaign-send]');
  var select=document.querySelector('[data-stage12-campaign-send-select]');
  var status=document.querySelector('[data-stage12-campaign-send-status]');
  if(!form)return;
  function esc(v){return String(v==null?'':v).replace(/[&<>'"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[c];});}
  function setStatus(message,type){if(window.Microgifter&&typeof Microgifter.setStatus==='function'){Microgifter.setStatus(status,message,type);return;}if(status)status.textContent=message||'';}
  function idem(){return 'campaign-send-'+Date.now()+'-'+Math.random().toString(16).slice(2);}
  async function loadCampaigns(){if(!select)return;try{var r=await Microgifter.get('/api/merchant/campaigns.php?status=active');var items=(r.data||r).campaigns||[];select.innerHTML='<option value="">General distribution / no campaign selected</option>'+items.map(function(c){return'<option value="'+esc(c.id)+'">'+esc(c.title)+' · '+esc(c.campaign_type)+'</option>';}).join('');}catch(e){}}
  form.addEventListener('submit',async function(event){
    event.preventDefault();
    var data=Object.fromEntries(new FormData(form).entries());
    data.idempotency_key=idem();
    try{setStatus('Recording Stamp usage...');var r=await Microgifter.post('/api/merchant/campaign-send.php',data);var entry=(r.data&&r.data.stamp_ledger&&r.data.stamp_ledger.entry)||{};setStatus((r.message||'Campaign send recorded.')+' Balance: '+(entry.balance_after==null?'updated':entry.balance_after),'success');}
    catch(error){setStatus(error.message||'Unable to record campaign send.','error');}
  });
  loadCampaigns();
});
