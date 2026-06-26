document.addEventListener('DOMContentLoaded',function(){
  var form=document.querySelector('[data-stage12-template-builder]');
  var list=document.querySelector('[data-stage12-template-list]');
  if(!form||!list||!window.Microgifter){return;}
  var status=form.querySelector('[data-stage12-template-status]');
  function txt(v){return String(v==null?'':v).replace(/[&<>'"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[c];});}
  function n(v){return Number(v||0)||0;}
  function count(v){return n(v).toLocaleString();}
  function set(sel,val){var el=document.querySelector(sel);if(el){el.textContent=val;}}
  function setStatus(message){if(status){status.textContent=message||'';}}
  function updateLibrary(items){
    var totals=items.reduce(function(acc,t){
      var st=String(t.status||'draft');
      if(st==='active')acc.active+=1;
      if(st==='draft')acc.draft+=1;
      if(st==='archived')acc.archived+=1;
      if(!String(t.redemption_instructions||'').trim())acc.missingInstructions+=1;
      if(String(t.expiration_rule||'none')==='none')acc.noExpiration+=1;
      if(!String(t.value_amount||'').trim())acc.noValue+=1;
      return acc;
    },{active:0,draft:0,archived:0,missingInstructions:0,noExpiration:0,noValue:0});
    set('[data-reward-kpi-active]',count(totals.active));
    set('[data-reward-kpi-draft]',count(totals.draft));
    set('[data-reward-kpi-issued]',count(items.length));
    set('[data-reward-kpi-claimed]','—');
    set('[data-reward-kpi-redeemed]','—');
    var score=Math.min(100,Math.round((totals.active?35:0)+(items.length?20:0)+(totals.missingInstructions<items.length?20:0)+(totals.noValue<items.length?10:0)+(totals.noExpiration<items.length?15:0)));
    set('[data-reward-readiness-score]',score?score+'/100':'—');
    set('[data-reward-ready-primary]',totals.active?count(totals.active)+' active reward'+(totals.active===1?' is':'s are')+' ready for campaigns and distribution.':'Create at least one active reward for campaign distribution.');
    set('[data-reward-ready-secondary]',totals.missingInstructions?count(totals.missingInstructions)+' reward'+(totals.missingInstructions===1?' needs':'s need')+' redemption instructions.':'Redemption instructions look complete across the current library.');
    set('[data-reward-ready-tertiary]',totals.noExpiration?count(totals.noExpiration)+' reward'+(totals.noExpiration===1?' has':'s have')+' no expiration control.':'Expiration controls are defined for the current reward library.');
  }
  function render(items){
    updateLibrary(items);
    if(!items.length){list.innerHTML='<div class="mg-empty-state"><p>No reward templates yet.</p></div>';return;}
    list.innerHTML=items.map(function(t){
      var value=t.value_amount?' · $'+txt(t.value_amount):'';
      var exp=t.expiration_rule?txt(t.expiration_rule).replace(/_/g,' '):'no expiration';
      return '<button type="button" class="mg-product-card mg-reward-card" data-id="'+txt(t.id)+'"><span><strong>'+txt(t.title)+'</strong><span>'+txt(t.reward_type).replace(/_/g,' ')+' · '+txt(t.status)+value+'</span><small>'+exp+' · '+(t.agent_discoverable?'Agent discoverable':'Manual distribution')+'</small></span><span class="mg-card-meta"><em>'+txt(t.status)+'</em></span></button>';
    }).join('');
    list.querySelectorAll('[data-id]').forEach(function(btn){btn.addEventListener('click',function(){
      var item=items.find(function(t){return t.id===btn.getAttribute('data-id');});
      if(!item){return;}
      Object.keys(item).forEach(function(k){var el=form.elements[k];if(!el){return;}if(el.type==='checkbox'){el.checked=!!item[k];}else{el.value=item[k]||'';}});
      if(form.elements.template_id){form.elements.template_id.value=item.id||'';}
      setStatus('Editing '+(item.title||'template'));
      var builder=document.getElementById('reward-builder');if(builder){builder.scrollIntoView({behavior:'smooth',block:'start'});}
    });});
  }
  async function refresh(){var r=await Microgifter.get('/api/merchant/reward-templates.php');render((r.data||r).templates||[]);}
  form.addEventListener('submit',async function(e){e.preventDefault();var data=Object.fromEntries(new FormData(form).entries());data.agent_discoverable=form.elements.agent_discoverable&&form.elements.agent_discoverable.checked?1:0;try{setStatus('Saving reward template...');var r=await Microgifter.post('/api/merchant/reward-templates.php',data);setStatus(r.message||'Reward template saved.');form.reset();if(form.elements.template_id){form.elements.template_id.value='';}if(form.elements.per_user_limit){form.elements.per_user_limit.value='1';}await refresh();}catch(error){setStatus(error.message||'Unable to save reward template.');}});
  var next=document.querySelector('[data-stage12-template-new]');
  if(next){next.addEventListener('click',function(){form.reset();if(form.elements.template_id){form.elements.template_id.value='';}if(form.elements.per_user_limit){form.elements.per_user_limit.value='1';}setStatus('Ready to save a reward template.');});}
  refresh().catch(function(error){setStatus(error.message||'Unable to load reward templates.');});
});