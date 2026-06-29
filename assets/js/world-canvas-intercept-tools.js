window.Microgifter = window.Microgifter || {};
(function(window, document){
  'use strict';
  var root = document.querySelector('[data-world-canvas]');
  if (!root || !window.Microgifter) return;
  var tools = [], activeRun = null;
  function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];});}
  function csrf(){var m=document.querySelector('meta[name="csrf-token"],meta[name="mg-csrf-token"]');return m?m.getAttribute('content')||'':(window.MG_CSRF_TOKEN||'');}
  function eligible(run){return !!(run && run.id && run.can_intercept !== false && run.intercept_ready !== false && !run.owned);}
  function panel(){
    var p=document.querySelector('[data-world-intercept-panel]'); if(p)return p;
    p=document.createElement('aside'); p.className='mg-world-intercept-panel'; p.dataset.worldInterceptPanel='1';
    p.innerHTML='<div class="mg-intercept-head"><div><span>Delivery Intercept</span><strong>TOOLS / EQUIPMENT</strong><small>Select equipment to attempt an intercept while the run is active.</small></div><button type="button" data-intercept-close>×</button></div><div class="mg-intercept-body" data-intercept-body></div>';
    document.body.appendChild(p); return p;
  }
  function render(){
    var p=panel(), body=p.querySelector('[data-intercept-body]'); if(!body)return;
    var list=tools.length?tools.map(function(t){return '<label class="mg-intercept-tool"><input type="radio" name="intercept_tool" value="'+esc(t.id)+'" '+(t.cooldown_active?'disabled':'')+'><span><b>'+esc(t.name)+'</b><em>'+esc(t.category)+' · '+esc(t.rarity)+'</em><small>Range '+esc(t.range_meters)+'m · +'+esc(t.success_bonus_percent)+' success</small></span></label>';}).join(''):'<p class="mg-intercept-empty">No tools available yet.</p>';
    body.innerHTML='<div class="mg-intercept-run"><b>'+esc(activeRun&&(activeRun.campaign_title||activeRun.drop_name)||'Active delivery run')+'</b><span>'+esc(activeRun&&activeRun.status||'sending')+'</span></div>'+list+'<button type="button" data-intercept-attempt>Attempt Intercept</button><p data-intercept-status></p>';
  }
  function open(run){if(!eligible(run)){close();return;}activeRun=run||activeRun;panel().classList.add('is-open');loadTools().then(render);}
  function close(){var p=document.querySelector('[data-world-intercept-panel]');if(p)p.classList.remove('is-open');}
  async function loadTools(){try{var r=await window.Microgifter.get('/api/world-canvas/intercept-tools.php');tools=((r.data||r||{}).tools||[]);}catch(e){tools=[];}}
  async function attempt(){
    var p=panel(), msg=p.querySelector('[data-intercept-status]'), selected=p.querySelector('input[name="intercept_tool"]:checked');
    if(!eligible(activeRun)){if(msg)msg.textContent='This delivery is not eligible.';return;}
    if(!selected){if(msg)msg.textContent='Select a tool first.';return;}
    try{if(msg)msg.textContent='Attempting intercept…';var c=csrf();var r=await window.Microgifter.post('/api/world-canvas/intercept-tools.php',{action:'attempt',run_id:activeRun.id,user_tool_id:selected.value,csrf_token:c,_csrf:c,csrf:c});var result=(r.data||r||{}).result||{};if(msg)msg.textContent=result.status==='success'?'Intercept successful.':'Intercept missed.';document.dispatchEvent(new CustomEvent('mg:world-intercept-result',{detail:{result:result,run:activeRun}}));}catch(e){if(msg)msg.textContent=e.message||'Intercept failed.';}
  }
  document.addEventListener('click',function(event){
    if(event.target.closest('[data-intercept-close]')){close();return;}
    if(event.target.closest('[data-intercept-attempt]')){attempt();return;}
    var button=event.target.closest('[data-world-run-intercept]');
    if(button&&window.MicrogifterDropRuns&&window.MicrogifterDropRuns.lastRun)open(window.MicrogifterDropRuns.lastRun);
  });
  document.addEventListener('mg:world-delivery-run-created',function(event){var run=event.detail&&event.detail.delivery_run?event.detail.delivery_run:null;if(eligible(run))activeRun=run;});
  window.MicrogifterInterceptTools={open:open,close:close,loadTools:loadTools};
})(window,document);
