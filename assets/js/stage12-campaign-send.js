document.addEventListener('DOMContentLoaded',function(){
  if(!window.Microgifter)return;
  var form=document.querySelector('[data-stage12-campaign-send]');
  var select=document.querySelector('[data-stage12-campaign-send-select]');
  var status=document.querySelector('[data-stage12-campaign-send-status]');
  var channel=document.querySelector('[data-stamp-channel]');
  var quantity=document.querySelector('[data-stamp-quantity]');
  var estimate=document.querySelector('[data-stamp-estimate]');
  if(!form)return;
  function esc(v){return String(v==null?'':v).replace(/[&<>'"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[c];});}
  function setText(sel,val){var el=document.querySelector(sel);if(el)el.textContent=val;}
  function count(v){return Number(v||0).toLocaleString();}
  function setStatus(message,type){if(window.Microgifter&&typeof Microgifter.setStatus==='function'){Microgifter.setStatus(status,message,type);return;}if(status)status.textContent=message||'';}
  function setStatusHtml(message,type){setStatus(message,type);if(status&&String(message||'').indexOf('<')>=0)status.innerHTML=message;}
  function idem(){return 'campaign-send-'+Date.now()+'-'+Math.random().toString(16).slice(2);}
  function rate(){var v=channel?channel.value:'feed';return v==='sms'?3:v==='agent'?2:1;}
  function debit(){return Math.max(1,Number(quantity&&quantity.value||1))*rate();}
  function updateEstimate(){var d=debit();if(estimate)estimate.textContent=count(d)+' Stamp'+(d===1?'':'s');setText('[data-stamp-kpi-need]',count(Math.max(500,d*5)));}
  function updateCampaignMetrics(items){var active=items.length,reach=Math.max(0,active*250),need=Math.max(500,active*250);setText('[data-stamp-kpi-campaigns]',count(active));setText('[data-stamp-kpi-reach]',count(reach||500));setText('[data-stamp-kpi-need]',count(need));setText('[data-stamp-kpi-used]','—');setText('[data-stamp-kpi-available]','—');setText('[data-stamp-readiness-score]',active?'70/100':'—');setText('[data-stamp-ready-primary]',active?count(active)+' active campaign'+(active===1?' is':'s are')+' ready for stamped distribution.':'Create or activate a campaign before recording stamped distribution.');}
  async function loadCampaigns(){if(!select)return;try{var r=await Microgifter.get('/api/merchant/campaigns.php?status=active');var items=(r.data||r).campaigns||[];select.innerHTML='<option value="">General distribution / no campaign selected</option>'+items.map(function(c){return'<option value="'+esc(c.id)+'">'+esc(c.title)+' · '+esc(c.campaign_type)+'</option>';}).join('');updateCampaignMetrics(items);}catch(e){}}
  if(channel)channel.addEventListener('change',updateEstimate);
  if(quantity)quantity.addEventListener('input',updateEstimate);
  form.addEventListener('submit',async function(event){
    event.preventDefault();
    var data=Object.fromEntries(new FormData(form).entries());
    data.idempotency_key=idem();
    try{setStatus('Recording Stamp usage...');var r=await Microgifter.post('/api/merchant/campaign-send.php',data);var entry=(r.data&&r.data.stamp_ledger&&r.data.stamp_ledger.entry)||{};if(entry.balance_after!=null){setText('[data-stamp-kpi-available]',count(entry.balance_after));var score=entry.balance_after>1000?'95/100':entry.balance_after>250?'75/100':'45/100';setText('[data-stamp-readiness-score]',score);setText('[data-stamp-ready-secondary]',entry.balance_after>250?'Stamp balance has enough reserve for smaller campaign sends.':'Low balance: buy stamps before scaling campaign distribution.');}setStatus((r.message||'Campaign send recorded.')+' Balance: '+(entry.balance_after==null?'updated':entry.balance_after),'success');}
    catch(error){var msg=error.message||'Unable to record campaign send.';if(/stamp/i.test(msg)){setStatusHtml(esc(msg)+' <a href="/merchant-stamps.php#stamp-purchases">Buy Stamps</a> to continue campaign distribution.','error');}else{setStatus(msg,'error');}}
  });
  updateEstimate();
  loadCampaigns();
});
