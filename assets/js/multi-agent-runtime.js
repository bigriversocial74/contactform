document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var selectedNode=document.getElementById('mg-selected-agent-id');
  var agentId=selectedNode?JSON.parse(selectedNode.textContent||'""'):'';
  if(!agentId)return;
  var root=document.querySelector('[data-agent-instance-canvas]');
  if(!root)return;
  var messages=root.querySelector('[data-agent-runtime-messages]');
  var composer=root.querySelector('[data-agent-runtime-composer]');
  var status=root.querySelector('[data-agent-runtime-status]');
  var threadList=root.querySelector('[data-agent-thread-list]');
  var memoryList=root.querySelector('[data-agent-memory-list]');
  var onboardingForm=root.querySelector('[data-agent-onboarding-form]');
  var currentThread='';

  function csrf(){var node=document.querySelector('meta[name="csrf-token"]');return node?node.content:'';}
  function esc(value){return String(value||'').replace(/[&<>"']/g,function(ch){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[ch];});}
  async function request(method,url,payload){
    var options={method:method,credentials:'same-origin',headers:{Accept:'application/json'}};
    if(payload){options.headers['Content-Type']='application/json';options.headers['X-CSRF-Token']=csrf();options.body=JSON.stringify(payload);}
    var response=await fetch(url,options);var json=await response.json();
    if(!response.ok||!json.ok)throw new Error(json.message||'Unable to complete the agent request.');
    return json.data||json;
  }
  function renderCards(cards){if(!Array.isArray(cards)||!cards.length)return'';return '<div class="mg-agent-runtime-cards">'+cards.map(function(card){return '<article><span>'+esc(card.type||'Agent draft')+'</span><h4>'+esc(card.title||'Next step')+'</h4><p>'+esc(card.body||'')+'</p>'+(card.action==='save_draft'?'<button type="button" data-save-agent-draft data-draft-title="'+esc(card.title||'Agent draft')+'" data-draft-payload="'+esc(JSON.stringify(card.review_payload||{}))+'">Save reviewable draft</button>':'')+'</article>';}).join('')+'</div>';}
  function renderMessages(items){messages.innerHTML=(items||[]).map(function(item){return '<article class="mg-agent-runtime-message is-'+esc(item.role)+'"><div><strong>'+esc(item.role==='assistant'?'Agent':'You')+'</strong><time>'+esc(item.created_at||'')+'</time></div><p>'+esc(item.body||'')+'</p>'+renderCards(item.cards||[])+'</article>';}).join('');messages.scrollTop=messages.scrollHeight;}
  function renderThreads(items){threadList.innerHTML=(items||[]).map(function(item){return '<a href="#" data-runtime-thread="'+esc(item.public_id)+'" class="'+(item.public_id===currentThread?'is-active':'')+'"><strong>'+esc(item.title||'Conversation')+'</strong><small>'+esc(item.last_message_at||item.updated_at||'')+'</small></a>';}).join('');}
  function renderMemory(items){memoryList.innerHTML=(items&&items.length)?items.map(function(item){var value=item.value;return '<article><span>'+esc(item.category||'memory')+'</span><strong>'+esc(item.title||item.memory_key)+'</strong><p>'+esc(typeof value==='string'?value:JSON.stringify(value))+'</p></article>';}).join(''):'<p>No saved memory yet.</p>';}
  async function load(threadId){
    status.textContent='Loading…';
    var url='/api/agents/runtime.php?id='+encodeURIComponent(agentId)+(threadId?'&thread_id='+encodeURIComponent(threadId):'');
    try{var data=await request('GET',url);currentThread=data.thread.id;renderMessages(data.messages);renderThreads(data.threads);renderMemory(data.memory);var onboarding=data.onboarding||{};var answers=onboarding.answers||{};Object.keys(answers).forEach(function(key){if(onboardingForm&&onboardingForm.elements[key])onboardingForm.elements[key].value=answers[key]||'';});status.textContent='';}
    catch(error){status.textContent=error.message;}
  }
  async function send(message){
    status.textContent='Thinking…';composer.querySelector('button').disabled=true;
    try{var data=await request('POST','/api/agents/runtime.php',{id:agentId,action:'chat',thread_id:currentThread,message:message});currentThread=data.thread.id;await load(currentThread);}
    catch(error){status.textContent=error.message;}finally{composer.querySelector('button').disabled=false;}
  }
  composer.addEventListener('submit',function(event){event.preventDefault();var field=composer.elements.message;var value=field.value.trim();if(!value)return;field.value='';send(value);});
  document.addEventListener('click',function(event){
    var prompt=event.target.closest('[data-agent-seed-prompt]');if(prompt){event.preventDefault();event.stopImmediatePropagation();composer.elements.message.value=prompt.getAttribute('data-agent-seed-prompt')||'';composer.elements.message.focus();return;}
    var thread=event.target.closest('[data-runtime-thread]');if(thread){event.preventDefault();event.stopImmediatePropagation();load(thread.getAttribute('data-runtime-thread'));return;}
    var fresh=event.target.closest('[data-agent-new-thread]');if(fresh){event.preventDefault();event.stopImmediatePropagation();request('POST','/api/agents/runtime.php',{id:agentId,action:'new_thread'}).then(function(data){load(data.thread.id);}).catch(function(error){status.textContent=error.message;});return;}
    var draft=event.target.closest('[data-save-agent-draft]');if(draft){event.preventDefault();event.stopImmediatePropagation();var payload={};try{payload=JSON.parse(draft.getAttribute('data-draft-payload')||'{}');}catch(e){}request('POST','/api/agents/runtime.php',{id:agentId,action:'save_draft',thread_id:currentThread,title:draft.getAttribute('data-draft-title')||'Agent draft',draft_type:'plan',payload:payload}).then(function(){status.textContent='Reviewable draft saved.';}).catch(function(error){status.textContent=error.message;});}
  },true);
  if(onboardingForm)onboardingForm.addEventListener('submit',function(event){event.preventDefault();var form=new FormData(onboardingForm);var answers={};form.forEach(function(value,key){answers[key]=String(value).trim();});var note=onboardingForm.querySelector('[data-agent-onboarding-status]');note.textContent='Saving…';request('POST','/api/agents/runtime.php',{id:agentId,action:'onboarding',status:'completed',current_step:'complete',answers:answers}).then(function(data){renderMemory(data.memory);note.textContent='Agent setup saved.';}).catch(function(error){note.textContent=error.message;});});
  load('');
});
