document.addEventListener('DOMContentLoaded',function(){
  'use strict';
  var root=document.querySelector('[data-merchant-agent-chat]');
  if(!root)return;
  var form=root.querySelector('[data-agent-chat-form]');
  var textarea=root.querySelector('[data-agent-chat-textarea],textarea[name="message"]');
  if(!form||!textarea||root.querySelector('[data-agent-creative-presets]'))return;

  var presets=[
    ['social_post','Social Post','social_campaign','campaigns','Create a social post set for my next merchant promotion. Base it on my merchant account data, saved memory, uploaded documents, website memory, products, rewards, campaigns, and recent feed posts. Include 3 channel-ready posts, a CTA, and the offer angle.'],
    ['sms','SMS','message_draft','crm','Write 3 short message drafts for my merchant account. Base the copy on my saved brand voice, customer tone, memory documents, website memory, products, rewards, campaigns, and recent feed posts. Keep it concise, local, and ready for review.'],
    ['email','Email','message_draft','campaigns','Draft a customer email campaign for my merchant account. Use my merchant data, memory documents, website memory, product and reward details, existing campaigns, and recent feed posts. Include subject line, preview text, body copy, CTA, and a short follow-up version.'],
    ['campaign_idea','Campaign Idea','campaign_idea','campaigns','Create one practical local campaign idea for my merchant account. Base it on my business profile, memory documents, website memory, products, rewards, campaigns, and recent feed posts. Include audience, offer, timing, CTA, content angle, and launch checklist.'],
    ['reward_copy','Reward Copy','campaign_idea','rewards','Create improved reward and offer copy for my merchant account. Use my saved memory, uploaded documents, website memory, existing rewards, products, campaign history, and recent feed posts. Give me headline options, short descriptions, CTA text, and a customer-facing explanation.'],
    ['local_event','Local Event Promo','social_campaign','campaigns','Create a local event promotion concept for my merchant account. Base it on my merchant profile, memory documents, website memory, products, rewards, campaigns, and recent feed posts. Include event hook, social copy, in-store angle, offer, CTA, and follow-up message.']
  ];

  function setValue(selector,value){
    var el=root.querySelector(selector);
    if(el){el.value=value;el.dispatchEvent(new Event('change',{bubbles:true}));}
  }
  function ensureSkill(value){
    var box=root.querySelector('[data-agent-skill][value="'+value+'"]');
    if(box&&!box.checked){box.checked=true;box.dispatchEvent(new Event('change',{bubbles:true}));}
  }

  var panel=document.createElement('section');
  panel.className='mg-agent-creative-presets';
  panel.setAttribute('data-agent-creative-presets','');
  panel.innerHTML='<div class="mg-agent-creative-presets-head"><span>Creative presets</span><strong>Ground every result in merchant data, memory, website sources, and feed posts.</strong></div><div class="mg-agent-creative-presets-list">'+presets.map(function(p){return '<button type="button" data-agent-creative-preset="'+p[0]+'">'+p[1]+'</button>';}).join('')+'</div>';
  form.parentNode.insertBefore(panel,form);

  panel.addEventListener('click',function(event){
    var btn=event.target.closest('[data-agent-creative-preset]');
    if(!btn)return;
    var preset=presets.find(function(p){return p[0]===btn.dataset.agentCreativePreset;});
    if(!preset)return;
    setValue('[data-agent-chat-output]',preset[2]);
    setValue('[data-agent-chat-scope]',preset[3]);
    setValue('[data-agent-chat-approval]','advisory');
    setValue('[data-agent-chat-mode]','draft');
    if(preset[2]==='social_campaign')ensureSkill('social_campaign_advisor');
    textarea.value=preset[4];
    textarea.focus();
    textarea.dispatchEvent(new Event('input',{bubbles:true}));
  });
});