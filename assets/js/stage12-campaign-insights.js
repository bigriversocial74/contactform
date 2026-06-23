document.addEventListener('DOMContentLoaded',function(){
  if(!window.Microgifter){return;}
  var anchor=document.querySelector('[data-stage12-campaign-list]');
  if(!anchor){return;}
  var panel=document.createElement('section');
  panel.className='mg-app-panel';
  panel.innerHTML='<div class="mg-app-panel-head"><div><h2>Demand insights</h2><p>Agent adds, wallet claims, completions, and projected local value.</p></div></div><div class="mg-app-panel-body"><div class="mg-product-list" data-campaign-insights-list></div><div class="mg-form-status" data-campaign-insights-status>Loading insights...</div></div>';
  var parent=anchor.closest('.mg-app-panel');
  if(parent&&parent.parentNode){parent.parentNode.insertBefore(panel,parent.nextSibling);}
  var list=panel.querySelector('[data-campaign-insights-list]');
  var status=panel.querySelector('[data-campaign-insights-status]');
  function safe(v){return String(v==null?'':v).replace(/[&<>'"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[c];});}
  function money(cents){return 'USD '+(Number(cents||0)/100).toFixed(2);}
  function note(message){if(status){status.textContent=message||'';}}
  function render(data){
    var campaigns=data.top_campaigns||[];
    var summary='<div class="mg-product-card"><span><strong>'+Number(data.projected_30d_completions||0)+' projected completions</strong><span>'+safe(money(data.projected_30d_value_cents))+' projected 30-day value · '+Number(data.agent_wallet_adds||0)+' agent adds</span><small>'+Number(data.claimed||0)+' claimed · '+Number(data.completed||0)+' completed · '+Math.round(Number(data.completion_rate||0)*100)+'% completion rate</small></span><span class="mg-card-meta"><em>'+Number(data.active_campaigns||0)+' active</em></span></div>';
    var rows=campaigns.map(function(c){return '<div class="mg-product-card"><span><strong>'+safe(c.title)+'</strong><span>'+safe(c.campaign_type)+' · '+safe(c.status)+'</span><small>'+Number(c.contacts||0)+' contacts · '+Number(c.claimed||0)+' claimed · '+Number(c.completed||0)+' completed · projected '+safe(money(c.projected_value_cents))+'</small></span><span class="mg-card-meta"><em>'+Math.round(Number(c.completion_rate||0)*100)+'%</em></span></div>';}).join('');
    list.innerHTML=summary+rows;
  }
  async function load(){
    var r=await Microgifter.get('/api/merchant/campaign-insights.php?days=30&multiplier=1.5');
    var data=(r.data||r).insights;
    if(!data){note('Insights unavailable until campaign activity exists.');return;}
    render(data);note('Insights refreshed.');
  }
  load().catch(function(error){note(error.message||'Unable to load campaign insights.');});
});
