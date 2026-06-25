document.addEventListener('DOMContentLoaded',function(){
  var form=document.querySelector('[data-stage12-template-builder]');
  var list=document.querySelector('[data-stage12-template-list]');
  if(!form||!list||!window.Microgifter){return;}
  var status=form.querySelector('[data-stage12-template-status]');
  var params=new URLSearchParams(window.location.search);
  function safe(v){var d=document.createElement('div');d.textContent=String(v==null?'':v);return d.innerHTML;}
  function setStatus(message,type){if(status){status.textContent=message||'';status.className='mg-form-status'+(type?' is-'+type:'');}}
  function render(items){
    if(!items.length){list.innerHTML='<div class="mg-empty-state"><p>No reward templates yet.</p></div>';return;}
    list.innerHTML=items.map(function(t){return '<button type="button" class="mg-product-card" data-id="'+safe(t.id)+'"><span><strong>'+safe(t.title)+'</strong><span>'+safe(t.reward_type)+' · '+safe(t.value_label||t.value_amount||'Reward')+' · '+safe(t.status)+'</span><small>'+safe(t.redemption_instructions||'No redemption instructions yet.')+'</small></span><span class="mg-card-meta"><em>'+safe(t.status)+'</em></span></button>';}).join('');
    list.querySelectorAll('[data-id]').forEach(function(btn){btn.addEventListener('click',function(){
      var item=items.find(function(t){return t.id===btn.getAttribute('data-id');});
      if(!item){return;}
      Object.keys(item).forEach(function(k){var el=form.elements[k];if(!el){return;}if(el.type==='checkbox'){el.checked=!!item[k];}else if(Array.isArray(item[k])){el.value=item[k].join(', ');}else{el.value=item[k]==null?'':String(item[k]);}});
      if(form.elements.template_id){form.elements.template_id.value=item.id||'';}
      if(form.elements.source_product_id){form.elements.source_product_id.value='';}
      setStatus('Editing '+(item.title||'template'),'info');
    });});
  }
  async function refresh(){var r=await Microgifter.get('/api/merchant/reward-templates.php');render((r.data||r).templates||[]);}
  function syncDefaults(){var reward=form.elements.reward_type&&form.elements.reward_type.value;var value=form.elements.value_type;if(!value)return;if(reward==='discount')value.value='percent';if(reward==='free_item'&&value.value==='fixed_amount')value.value='free_item';if(reward==='dollar_credit')value.value='fixed_amount';}
  if(params.get('source_product_id')&&form.elements.source_product_id){form.elements.source_product_id.value=params.get('source_product_id');setStatus('Product selected. Save to create a reward template from this product.','info');}
  if(form.elements.reward_type){form.elements.reward_type.addEventListener('change',syncDefaults);}
  form.addEventListener('submit',async function(e){e.preventDefault();var data=Object.fromEntries(new FormData(form).entries());['agent_discoverable','agent_add_to_wallet_allowed','agent_gift_send_allowed'].forEach(function(k){data[k]=form.elements[k]&&form.elements[k].checked?1:0;});try{setStatus('Saving reward template...');var r=await Microgifter.post('/api/merchant/reward-templates.php',data);setStatus(r.message||'Reward template saved.','success');form.reset();if(form.elements.template_id){form.elements.template_id.value='';}if(form.elements.source_product_id){form.elements.source_product_id.value='';}if(form.elements.per_user_limit){form.elements.per_user_limit.value='1';}if(form.elements.currency){form.elements.currency.value='USD';}await refresh();}catch(error){setStatus(error.message||'Unable to save reward template.','error');}});
  var next=document.querySelector('[data-stage12-template-new]');
  if(next){next.addEventListener('click',function(){form.reset();if(form.elements.template_id){form.elements.template_id.value='';}if(form.elements.source_product_id){form.elements.source_product_id.value='';}if(form.elements.per_user_limit){form.elements.per_user_limit.value='1';}if(form.elements.currency){form.elements.currency.value='USD';}setStatus('Ready to save a reward template.');});}
  refresh().catch(function(error){setStatus(error.message||'Unable to load reward templates.','error');});
});