(function(){
function initCrmDraftApprovalGate(){
'use strict';
if(!window.Microgifter)return;
var root=document.querySelector('[data-crm-campaign-builder]');if(!root||root.dataset.crmDraftApprovalGateReady==='1')return;root.dataset.crmDraftApprovalGateReady='1';
var state={contacts:[],selectedSegment:'all',activeDraftId:''};
function qs(s,r){return(r||document).querySelector(s)}
function esc(v){return String(v==null?'':v).replace(/[&<>'"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[c]})}
function val(s){var e=qs(s,root);return e?String(e.value||'').trim():''}
function toast(m){Microgifter.toast?Microgifter.toast(m):alert(m)}
function store(){try{return JSON.parse(localStorage.getItem('mgCrmDraftReviewState')||'{}')||{}}catch(e){return{}}}
function saveStore(s){try{localStorage.setItem('mgCrmDraftReviewState',JSON.stringify(s||{}))}catch(e){}}
function matchSegment(c,key){key=String(key||'all');if(key==='accounts')return!!c.has_account;if(key==='no_accounts')return!c.has_account;if(key==='verified')return!!c.email_verified;if(key==='reward_issued')return Number(c.issued_count||0)>0||Number(c.wallet_count||0)>0;if(key==='reward_claimed')return Number(c.claimed_count||0)>0||Number(c.redeemed_count||0)>0;if(key==='invite_pending')return Number(c.invite_pending_count||0)>0;if(key==='no_recent_activity')return!!c.no_recent_activity;return true}
function audienceCount(){return state.contacts.filter(function(c){return matchSegment(c,state.selectedSegment)}).length}
function payload(){return{campaign_name:val('[data-crm-builder-name]'),segment_key:state.selectedSegment||val('[data-crm-builder-segment]')||'all',message:val('[data-crm-builder-message]'),reward_template_id:val('[data-crm-builder-reward]'),follow_up_note:val('[data-crm-builder-followup-note]')}}
function isReady(){var id=String(state.activeDraftId||''),s=store();return !!(id&&s[id]&&s[id].status==='ready'&&!s[id].archived)}
function checks(){var p=payload(),count=audienceCount();return[['campaign name present',!!p.campaign_name],['audience selected',!!p.segment_key],['contact count above zero',count>0],['message, reward, or follow-up exists',!!(p.message||p.reward_template_id||p.follow_up_note)],['draft marked ready in Draft Review',isReady()]]}
function ready(){return checks().every(function(c){return c[1]})}
function ensure(){if(qs('[data-crm-approval-gate]',root))return;var el=document.createElement('div');el.className='mg-crm-approval-gate';el.setAttribute('data-crm-approval-gate','');el.innerHTML='<div><span class="mg-eyebrow">Approval gate</span><strong data-crm-approval-title>Needs review</strong><p data-crm-approval-copy>Complete the checklist before using the reviewed draft.</p></div><ul data-crm-approval-checks></ul><button class="mg-btn mg-btn-soft" type="button" data-crm-approval-ready>Mark Ready</button>';root.insertBefore(el,root.firstElementChild||null)}
function render(){ensure();var ok=ready(),gate=qs('[data-crm-approval-gate]',root),title=qs('[data-crm-approval-title]',root),copy=qs('[data-crm-approval-copy]',root),list=qs('[data-crm-approval-checks]',root),btn=qs('[data-crm-approval-ready]',root);if(gate)gate.classList.toggle('is-ready',ok);if(title)title.textContent=ok?'Ready':'Needs review';if(copy)copy.textContent=ok?'This draft has passed the review checklist.':'Complete the checklist before using the reviewed draft.';if(list)list.innerHTML=checks().map(function(c){return'<li class="'+(c[1]?'is-good':'is-missing')+'"><span></span>'+esc(c[0])+'</li>'}).join('');if(btn)btn.disabled=!state.activeDraftId}
function markReady(){var id=String(state.activeDraftId||'');if(!id){toast('Save or load a draft before marking ready.');return}var s=store();s[id]=Object.assign({},s[id]||{},{status:'ready'});saveStore(s);render();document.dispatchEvent(new CustomEvent('mg:crm-draft-review:updated'));toast('Draft marked ready.')}
function block(ev){if(!ev.target||!ev.target.closest('[data-crm-launch-campaign]'))return;render();if(ready())return;ev.preventDefault();ev.stopPropagation();if(ev.stopImmediatePropagation)ev.stopImmediatePropagation();var status=qs('[data-crm-builder-status]',root);if(status)status.textContent='Approval gate blocked this draft. Complete the checklist first.';toast('Complete the approval checklist first.')}
document.addEventListener('mg:crm-contacts:rendered',function(ev){state.contacts=(ev.detail&&ev.detail.contacts)||[];render()});
document.addEventListener('mg:crm-builder:prefill',function(ev){var d=(ev&&ev.detail)||{};state.activeDraftId=String(d.draft_id||'');if(d.segment_key)state.selectedSegment=String(d.segment_key);setTimeout(render,50)});
document.addEventListener('mg:crm-draft-review:updated',render);
root.addEventListener('input',render);
root.addEventListener('change',function(ev){if(ev.target&&ev.target.matches('[data-crm-builder-segment]'))state.selectedSegment=ev.target.value||'all';render()});
root.addEventListener('click',block,true);
root.addEventListener('click',function(ev){if(ev.target&&ev.target.closest('[data-crm-approval-ready]'))markReady()});
ensure();render();
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',initCrmDraftApprovalGate);else initCrmDraftApprovalGate();
})();
