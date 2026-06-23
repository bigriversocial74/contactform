document.addEventListener('DOMContentLoaded',function(){
  var form=document.querySelector('[data-stage12-template-builder]');
  var list=document.querySelector('[data-stage12-template-list]');
  if(!form||!list||!window.Microgifter){return;}
  var status=form.querySelector('[data-stage12-template-status]');
  function txt(v){return String(v==null?'':v);}
  function setStatus(message){if(status){status.textContent=message||'';}}
  function render(items){
    if(!items.length){list.innerHTML='<div class="mg-empty-state"><p>No reward templates yet.</p></div>';return;}
    list.innerHTML=items.map(function(t){return '<button type="button" class="mg-product-card" data-id="'+txt(t.id)+'"><span><strong>'+txt(t.title)+'</strong><span>'+txt(t.reward_type)+' · '+txt(t.status)+'</span></span></button>';}).join('');
    list.querySelectorAll('[data-id]').forEach(function(btn){btn.addEventListener('click',function(){
      var item=items.find(function(t){return t.id===btn.getAttribute('data-id');});
      if(!item){return;}
      Object.keys(item).forEach(function(k){var el=form.elements[k];if(!el){return;}if(el.type==='checkbox'){el.checked=!!item[k];}else{el.value=item[k]||'';}});
      if(form.elements.template_id){form.elements.template_id.value=item.id||'';}
      setStatus('Editing '+(item.title||'template'));
    });});
  }
  async function refresh(){var r=await Microgifter.get('/api/merchant/reward-templates.php');render((r.data||r).templates||[]);}
  form.addEventListener('submit',async function(e){e.preventDefault();var data=Object.fromEntries(new FormData(form).entries());data.agent_discoverable=form.elements.agent_discoverable&&form.elements.agent_discoverable.checked?1:0;try{setStatus('Saving reward template...');var r=await Microgifter.post('/api/merchant/reward-templates.php',data);setStatus(r.message||'Reward template saved.');form.reset();if(form.elements.template_id){form.elements.template_id.value='';}if(form.elements.per_user_limit){form.elements.per_user_limit.value='1';}await refresh();}catch(error){setStatus(error.message||'Unable to save reward template.');}});
  var next=document.querySelector('[data-stage12-template-new]');
  if(next){next.addEventListener('click',function(){form.reset();if(form.elements.template_id){form.elements.template_id.value='';}if(form.elements.per_user_limit){form.elements.per_user_limit.value='1';}setStatus('Ready to save a reward template.');});}
  refresh().catch(function(error){setStatus(error.message||'Unable to load reward templates.');});
});
