document.addEventListener('DOMContentLoaded',function(){
'use strict';
var root=document.querySelector('[data-my-loyalty-quests]');if(!root||!window.Microgifter)return;
var list=root.querySelector('[data-my-quest-list]'),statusNode=root.querySelector('[data-my-quest-status]'),filter=root.querySelector('[data-my-quest-filter]');
function esc(value){return String(value==null?'':value).replace(/[&<>'"]/g,function(char){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[char];});}
function label(value){return String(value||'').replace(/_/g,' ').replace(/\b\w/g,function(char){return char.toUpperCase();});}
function date(value){if(!value)return 'Not set';var item=new Date(String(value).replace(' ','T'));return isNaN(item.getTime())?String(value):item.toLocaleString();}
function text(selector,value){var node=root.querySelector(selector);if(node)node.textContent=String(value==null?'':value);}
function setStatus(message,type){if(window.Microgifter&&typeof Microgifter.setStatus==='function'){Microgifter.setStatus(statusNode,message,type);return;}statusNode.textContent=message||'';statusNode.classList.toggle('is-error',type==='error');}
function actionLabel(item){if(item.status==='completed')return 'View completed quest';if(item.status==='pending_review')return 'View review status';if(item.status==='rejected')return 'Correct and resubmit';if(item.quest&&['ended','archived','paused'].indexOf(item.quest.status)!==-1)return 'View quest history';return 'Continue quest';}
function render(items){
  list.className='mg-my-quest-list';
  if(!items.length){list.innerHTML='<div class="mg-my-quest-empty"><h2>No quests in this view.</h2><p>Explore active Loyalty Quests and start one with your Microgifter account.</p><a href="/quests.php">Explore quests</a></div>';return;}
  list.innerHTML=items.map(function(item){
    var quest=item.quest||{},reward=item.reward||{};
    var note=item.latest_review_note?'<div class="mg-my-quest-review-note"><strong>Merchant review</strong><p>'+esc(item.latest_review_note)+'</p></div>':'';
    var inbox=reward.wallet_item_id?'<a class="is-secondary" href="/inbox.php">Open reward in Inbox</a>':'';
    return '<article class="mg-my-quest-card"><div><span class="mg-lqp-state is-'+esc(String(item.status).replace(/_/g,'-'))+'">'+esc(label(item.status))+'</span><h2>'+esc(quest.title)+'</h2><p>'+esc(quest.description||'Continue this Loyalty Quest.')+'</p><div class="mg-my-quest-meta"><span>'+esc(item.merchant&&item.merchant.name||'Microgifter Merchant')+'</span><span>'+esc(label(quest.action_type||'Quest action'))+'</span><span>'+esc(label(quest.verification_type||'Verification'))+'</span><span>Ends '+esc(date(quest.ends_at))+'</span><span>'+esc(String(item.evidence_count||0))+' evidence item'+((item.evidence_count||0)===1?'':'s')+'</span></div>'+note+'<div class="mg-my-quest-progress"><div><span>Progress</span><strong>'+esc(item.progress_count)+' of '+esc(item.required_count)+'</strong></div><div class="mg-my-quest-track"><span style="width:'+Math.max(0,Math.min(100,Number(item.completion_percent||0)))+'%"></span></div></div></div><div class="mg-my-quest-actions"><a href="'+esc(quest.url)+'">'+esc(actionLabel(item))+'</a>'+inbox+'</div></article>';
  }).join('');
}
async function load(){
  setStatus('Loading your quests…');
  var query=filter.value==='all'?'':'?status='+encodeURIComponent(filter.value);
  try{
    var response=await Microgifter.get('/api/account/loyalty-quests.php'+query),data=response.data||response,totals=data.totals||{};
    text('[data-my-quest-total]',Number(totals.total||0).toLocaleString());text('[data-my-quest-progress]',Number(totals.in_progress||0).toLocaleString());text('[data-my-quest-pending]',Number(totals.pending_review||0).toLocaleString());text('[data-my-quest-completed]',Number(totals.completed||0).toLocaleString());
    render(data.participations||[]);setStatus((data.participations||[]).length+' quest'+((data.participations||[]).length===1?'':'s')+' shown.');
  }catch(error){render([]);setStatus(error.message||'Unable to load your quests.','error');}
}
filter.addEventListener('change',load);var refresh=root.querySelector('[data-my-quest-refresh]');if(refresh)refresh.addEventListener('click',load);load();
});
