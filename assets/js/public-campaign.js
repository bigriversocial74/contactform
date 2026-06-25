document.addEventListener('DOMContentLoaded',function(){
'use strict';
if(!window.Microgifter)return;
var detailEndpoint='/api/public/campaigns/detail.php';
function esc(v){return String(v==null?'':v).replace(/[&<>'"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[c];});}
function idKey(prefix){return prefix+':campaign:'+Date.now()+':'+Math.random().toString(16).slice(2);}
function setButtonBusy(button,busy){button.disabled=!!busy;button.setAttribute('aria-busy',busy?'true':'false');}
function followerCountDelta(delta){
  document.querySelectorAll('[data-follower-count]').forEach(function(node){
    var raw=String(node.textContent||'0').replace(/[^0-9]/g,'');
    var next=Math.max(0,(parseInt(raw||'0',10)||0)+delta);
    node.textContent=next.toLocaleString();
  });
}
function loadLegacyCampaignDetail(){
  var root=document.querySelector('[data-public-campaign]');
  if(!root)return;
  var params=new URLSearchParams(window.location.search);
  var ref=params.get('c')||params.get('campaign')||params.get('slug')||'';
  var token=params.get('token')||params.get('qr_token')||'';
  if(!ref&&!token)return;
  Microgifter.get(detailEndpoint+'?campaign='+encodeURIComponent(ref)+'&token='+encodeURIComponent(token)).catch(function(){});
}
loadLegacyCampaignDetail();
document.querySelectorAll('[data-follow-profile]').forEach(function(button){
  button.addEventListener('click',async function(){
    var profile=button.getAttribute('data-follow-profile')||'';
    var following=button.getAttribute('data-following')==='true';
    var action=following?'unfollow':'follow';
    if(!profile)return;
    try{
      setButtonBusy(button,true);
      var response=await Microgifter.post('/api/social/relationship.php',{profile_id:profile,action:action,idempotency_key:idKey(action)});
      var relation=(response.data||response).relationship||{};
      var nowFollowing=!!relation.following;
      button.setAttribute('data-following',nowFollowing?'true':'false');
      button.textContent=nowFollowing?'Following':'Follow';
      if(typeof relation.followers==='number'){
        document.querySelectorAll('[data-follower-count]').forEach(function(node){node.textContent=Number(relation.followers).toLocaleString();});
      }else{
        followerCountDelta(nowFollowing?1:-1);
      }
    }catch(error){
      if(String(error.message||'').toLowerCase().indexOf('permission')!==-1){window.location.href='/signin.php?redirect='+encodeURIComponent(window.location.pathname+window.location.search);return;}
      button.textContent=error.message||'Unable to follow';
      window.setTimeout(function(){button.textContent=following?'Following':'Follow';},1800);
    }finally{
      setButtonBusy(button,false);
    }
  });
});
document.querySelectorAll('[data-campaign-form]').forEach(function(form){
  var status=form.querySelector('[data-campaign-status]')||document.querySelector('[data-campaign-status]');
  var result=form.parentElement&&form.parentElement.querySelector('[data-campaign-result]')||document.querySelector('[data-campaign-result]');
  function setStatus(message,type){
    if(window.Microgifter&&typeof Microgifter.setStatus==='function'){Microgifter.setStatus(status,message,type);return;}
    if(status)status.textContent=message||'';
  }
  function showResult(message,payload){
    var data=(payload&&payload.data)||payload||{};
    var details=[];
    if(data.reward_title)details.push('<span>Reward: '+esc(data.reward_title)+'</span>');
    if(data.wallet_item_id)details.push('<span>Wallet item: '+esc(data.wallet_item_id)+'</span>');
    if(data.wallet_status)details.push('<span>Status: '+esc(data.already_issued?'already issued':data.wallet_status)+'</span>');
    if(data.expires_at)details.push('<span>Expires: '+esc(data.expires_at)+'</span>');
    if(result){
      result.classList.add('is-visible');
      result.innerHTML='<strong>'+esc(message||'Submitted.')+'</strong>'+(details.length?'<div class="mg-public-campaign-result-details">'+details.join('')+'</div>':'');
    }
    form.hidden=true;
  }
  form.addEventListener('submit',async function(event){
    event.preventDefault();
    var endpoint=form.dataset.submitEndpoint||form.dataset.endpoint||'/api/public/campaigns/engage.php';
    var data=Object.fromEntries(new FormData(form).entries());
    if(data.entry_note){data.entry={note:data.entry_note};delete data.entry_note;}
    try{
      setStatus('Submitting…');
      var response=await Microgifter.post(endpoint,data);
      showResult(response.message||'Campaign response submitted.',response);
    }catch(error){
      setStatus(error.message||'Unable to submit campaign form.','error');
    }
  });
});
});
