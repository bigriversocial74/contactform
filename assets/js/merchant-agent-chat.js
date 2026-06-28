document.addEventListener('DOMContentLoaded',function(){
  'use strict';
  var root=document.querySelector('[data-merchant-agent-chat]');
  if(!root||!window.Microgifter)return;
  var feed=root.querySelector('[data-agent-chat-feed]');
  var form=root.querySelector('[data-agent-chat-form]');
  var status=root.querySelector('[data-agent-chat-status]');
  var send=root.querySelector('[data-agent-chat-send]');
  var state={messages:[],quick_prompts:[]};
  function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];});}
  function payload(r){return r&&r.data?r.data:r;}
  function time(v){var d=Date.parse(v||'');return d?new Date(d).toLocaleString():'';}
  function setStatus(msg,type){if(!status)return;status.textContent=msg||'';status.className='mg-form-status'+(type?' is-'+type:'');}
  function busy(on){if(send){send.disabled=!!on;send.textContent=on?'Thinking…':'Send';}if(form&&form.elements.message)form.elements.message.disabled=!!on;}
  function cardHtml(card){var link=card.action_url?'<a class="mg-btn mg-btn-soft" href="'+esc(card.action_url)+'">'+esc(card.action_label||'Open')+'</a>':'';return '<article class="mg-agent-chat-card"><span>'+esc(card.type||'recommendation')+'</span><strong>'+esc(card.title||'Agent note')+'</strong><p>'+esc(card.body||'')+'</p>'+link+'</article>';}
  function messageHtml(m){var mine=m.role==='user';var cards=Array.isArray(m.cards)?m.cards:[];return '<article class="mg-agent-chat-message '+(mine?'is-user':'is-agent')+'"><div class="mg-agent-chat-bubble"><div class="mg-agent-chat-meta"><strong>'+esc(mine?'You':'Merchant Agent')+'</strong><time>'+esc(time(m.created_at))+'</time></div><p>'+esc(m.body||'')+'</p>'+(cards.length?'<div class="mg-agent-chat-cards">'+cards.map(cardHtml).join('')+'</div>':'')+'</div></article>';}
  function render(){if(!feed)return;if(!state.messages.length){feed.innerHTML='<div class="mg-agent-chat-empty"><strong>Ask the merchant agent a question.</strong><p>Try: “What should I focus on today?” or “Review my campaigns and rewards.”</p></div>';return;}feed.innerHTML=state.messages.map(messageHtml).join('');feed.scrollTop=feed.scrollHeight;}
  function renderPrompts(){var box=root.querySelector('[data-agent-chat-prompts]');if(!box||!state.quick_prompts||!state.quick_prompts.length)return;box.innerHTML=state.quick_prompts.map(function(p){return '<button type="button">'+esc(p)+'</button>';}).join('');}
  async function load(){try{setStatus('Loading agent chat…','');var data=payload(await Microgifter.get('/api/ai/merchant-agent-chat.php'));state.messages=data.messages||[];state.quick_prompts=data.quick_prompts||[];render();renderPrompts();setStatus('','');}catch(e){setStatus(e.message||'Unable to load agent chat.','error');}}
  async function submit(message){message=String(message||'').trim();if(!message)return;var scope=(root.querySelector('[data-agent-chat-scope]')||{}).value||'overview';var days=parseInt((root.querySelector('[data-agent-chat-days]')||{}).value||'90',10)||90;busy(true);setStatus('Merchant agent is reviewing your workspace…','');try{var data=payload(await Microgifter.post('/api/ai/merchant-agent-chat.php',{message:message,scope:scope,days:days}));state.messages=(data.state&&data.state.messages)||state.messages.concat([data.user_message,data.assistant_message].filter(Boolean));render();form.reset();setStatus('Agent reply created.','success');}catch(e){setStatus(e.message||'Unable to run merchant agent chat.','error');}finally{busy(false);}}
  if(form){form.addEventListener('submit',function(e){e.preventDefault();submit(form.elements.message.value);});form.elements.message.addEventListener('keydown',function(e){if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();form.requestSubmit();}});}
  root.addEventListener('click',function(e){var prompt=e.target.closest&&e.target.closest('[data-agent-chat-prompts] button');if(prompt&&form){form.elements.message.value=prompt.textContent.trim();form.elements.message.focus();}var refresh=e.target.closest&&e.target.closest('[data-agent-chat-refresh]');if(refresh)load();});
  load();
});
