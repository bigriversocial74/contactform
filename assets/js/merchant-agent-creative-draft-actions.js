document.addEventListener('DOMContentLoaded',function(){
  'use strict';
  var root=document.querySelector('[data-merchant-agent-chat]');
  if(!root||!window.Microgifter)return;
  var draftTypes=[
    ['social','Save Social Draft'],
    ['sms','Save SMS Draft'],
    ['email','Save Email Draft'],
    ['campaign','Save Campaign Draft'],
    ['reward','Save Reward Copy']
  ];
  function payload(response){return response&&response.data?response.data:response;}
  function cleanErrorMessage(error){return String((error&&error.message)||error||'Unable to save creative draft.').trim();}
  function setStatus(message,type){
    var status=root.querySelector('[data-agent-chat-status]');
    if(!status)return;
    status.textContent=message||'';
    status.className='mg-form-status'+(type?' is-'+type:'');
  }
  function decorate(){
    root.querySelectorAll('[data-agent-chat-review]').forEach(function(reviewButton){
      var actions=reviewButton.closest('.mg-agent-chat-card-actions');
      if(!actions||actions.querySelector('[data-agent-creative-draft-actions]'))return;
      var messageId=reviewButton.getAttribute('data-message-id')||'';
      var cardIndex=reviewButton.getAttribute('data-card-index')||'-1';
      if(!messageId)return;
      var wrap=document.createElement('div');
      wrap.className='mg-agent-creative-draft-actions';
      wrap.setAttribute('data-agent-creative-draft-actions','');
      wrap.innerHTML=draftTypes.map(function(item){return '<button class="mg-btn mg-btn-soft" type="button" data-agent-save-creative-draft="'+item[0]+'" data-message-id="'+messageId.replace(/"/g,'&quot;')+'" data-card-index="'+cardIndex+'">'+item[1]+'</button>';}).join('');
      actions.appendChild(wrap);
    });
  }
  async function save(button){
    var draftType=button.getAttribute('data-agent-save-creative-draft')||'';
    var messageId=button.getAttribute('data-message-id')||'';
    var cardIndex=parseInt(button.getAttribute('data-card-index')||'-1',10);
    if(!draftType||!messageId)return;
    var original=button.textContent;
    button.disabled=true;
    button.textContent='Saving…';
    setStatus('Saving creative draft to review queue…','');
    try{
      var data=payload(await Microgifter.post('/api/ai/merchant-agent-creative-draft.php',{message_id:messageId,card_index:cardIndex,draft_type:draftType}));
      button.textContent=(data&&data.already_saved)?'Already saved':'Saved';
      button.classList.add('is-saved');
      setStatus('Creative draft saved to Agent Review queue.','success');
      var reviewLink=document.createElement('a');
      reviewLink.className='mg-btn mg-btn-soft mg-agent-creative-draft-review-link';
      reviewLink.href='/merchant-agent-approvals.php';
      reviewLink.textContent='Open Review Queue';
      button.parentNode.appendChild(reviewLink);
    }catch(error){
      button.disabled=false;
      button.textContent=original;
      setStatus(cleanErrorMessage(error),'error');
    }
  }
  root.addEventListener('click',function(event){
    var button=event.target.closest('[data-agent-save-creative-draft]');
    if(button)save(button);
  });
  decorate();
  var observer=new MutationObserver(decorate);
  observer.observe(root,{childList:true,subtree:true});
});
