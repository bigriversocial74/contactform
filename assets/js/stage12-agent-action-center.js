document.addEventListener('DOMContentLoaded',function(){
  if(!window.Microgifter){return;}
  var anchor=document.querySelector('[data-stage12-campaign-list]');
  if(!anchor){return;}
  var panel=document.createElement('section');
  panel.className='mg-app-panel';
  panel.innerHTML='<div class="mg-app-panel-head"><div><h2>Agent action center</h2><p>Recommended merchant next steps from campaign and wallet activity.</p></div></div><div class="mg-app-panel-body"><div class="mg-product-list" data-agent-action-list></div><div class="mg-form-status" data-agent-action-status>Loading actions...</div></div>';
  var parent=anchor.closest('.mg-app-panel');
  if(parent&&parent.parentNode){parent.parentNode.insertBefore(panel,parent.nextSibling);}
  var list=panel.querySelector('[data-agent-action-list]');
  var status=panel.querySelector('[data-agent-action-status]');
  function safe(v){return String(v==null?'':v).replace(/[&<>'"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[c];});}
  function note(message){if(status){status.textContent=message||'';}}
  async function logStep(stepType,campaignId,label){try{await Microgifter.post('/api/merchant/campaign-next-step.php',{step_type:stepType,campaign_id:campaignId||'',label:label||''});}catch(e){}}
  function card(priority,title,message,cta,step,campaignId){return '<div class="mg-product-card"><span><strong>'+safe(title)+'</strong><span>'+safe(message)+'</span></span><span class="mg-card-meta"><em>'+safe(priority)+'</em><button class="mg-btn mg-btn-soft" type="button" data-next-step="'+safe(step||'review')+'" data-campaign-id="'+safe(campaignId||'')+'">'+safe(cta)+'</button></span></div>';}
  function bind(){list.querySelectorAll('[data-next-step]').forEach(function(button){button.addEventListener('click',async function(){await logStep(button.getAttribute('data-next-step'),button.getAttribute('data-campaign-id'),button.textContent);note('Merchant next step logged.');});});}
  function build(data){
    var rows=[];
    if(Number(data.agent_wallet_adds||0)===0){rows.push(card('medium','Improve agent discovery','No agent wallet adds in this window. Review discoverable templates and use-case copy.','Review offers','review',''));}
    if(Number(data.projected_30d_completions||0)===0){rows.push(card('medium','Increase conversion path','No projected completions yet. Share campaign links and tighten claim messaging.','Review campaigns','refresh',''));}
    (data.top_campaigns||[]).forEach(function(c){
      if(Number(c.contacts||0)===0){rows.push(card('medium','Share '+c.title,c.title+' has no contacts yet. Use the public link or QR flow.','Copy link','share',c.id));}
      if(Number(c.claimed||0)>Number(c.completed||0)){rows.push(card('high','Complete ready wallet items',c.title+' has wallet items ready for merchant completion.','Open console','complete',c.id));}
      if(Number(c.completion_rate||0)<0.25&&Number(c.claimed||0)>0){rows.push(card('low','Tune completion copy',c.title+' has low completion activity compared with claims.','Review copy','copy',c.id));}
    });
    if(!rows.length){rows.push(card('low','No urgent actions','Campaign and wallet activity is balanced for the current window.','Refresh','refresh',''));}
    list.innerHTML=rows.slice(0,8).join('');bind();
  }
  async function load(){var r=await Microgifter.get('/api/merchant/campaign-insights.php?days=30&multiplier=1.5');var data=(r.data||r).insights;if(data){build(data);note('Action center refreshed.');}else{note('Action center unavailable until campaign activity exists.');}}
  load().catch(function(error){note(error.message||'Unable to load action center.');});
});
