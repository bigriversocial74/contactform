window.Microgifter = window.Microgifter || {};
(function(window, document){
  'use strict';
  var root = document.querySelector('[data-world-canvas]');
  if (!root || !window.Microgifter || window.Microgifter.__worldDropAcceptanceMock) return;
  window.Microgifter.__worldDropAcceptanceMock = true;

  var MG = window.Microgifter;
  var active = null;

  function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];});}
  function clamp(v,min,max){return Math.max(min,Math.min(max,v));}
  function num(v,f){var n=Number(v);return Number.isFinite(n)?n:f;}
  function csrf(){var m=document.querySelector('meta[name="csrf-token"],meta[name="mg-csrf-token"]');return m?(m.getAttribute('content')||''):(window.MG_CSRF_TOKEN||'');}
  function money(cents){return '$'+(Math.round(cents)/100).toFixed(2);}
  function hash(v){var s=String(v||'x'),h=0;for(var i=0;i<s.length;i++){h=((h<<5)-h)+s.charCodeAt(i);h|=0;}return Math.abs(h);}
  function ensureStyle(){
    if(document.querySelector('[data-world-drop-acceptance-style]'))return;
    var style=document.createElement('style');
    style.dataset.worldDropAcceptanceStyle='1';
    style.textContent='.mg-accept-backdrop{position:fixed;inset:0;z-index:2147483200;background:rgba(15,23,42,.34);backdrop-filter:blur(10px);display:grid;place-items:center;padding:22px}.mg-accept-modal{width:min(980px,calc(100vw - 28px));max-height:calc(100dvh - 36px);overflow:auto;border-radius:24px;background:#fff;box-shadow:0 32px 110px rgba(15,23,42,.32);border:1px solid rgba(203,213,225,.82)}.mg-accept-head{display:flex;justify-content:space-between;gap:18px;padding:20px 22px;border-bottom:1px solid rgba(203,213,225,.7);background:linear-gradient(135deg,#fff,#f8fbff)}.mg-accept-head span{display:block;color:#2563eb;font-size:11px;font-weight:950;text-transform:uppercase;letter-spacing:.13em}.mg-accept-head strong{display:block;margin-top:4px;color:#0f172a;font-size:24px;letter-spacing:-.04em}.mg-accept-head p{margin:7px 0 0;color:#64748b;font-size:13px;line-height:1.45}.mg-accept-close{width:38px;height:38px;border:0;border-radius:12px;background:#eef2ff;color:#0f172a;font-size:22px;cursor:pointer}.mg-accept-body{padding:20px 22px 22px;display:grid;gap:16px}.mg-accept-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.mg-accept-metric{border:1px solid rgba(203,213,225,.82);border-radius:18px;background:#f8fafc;padding:14px}.mg-accept-metric b{display:block;color:#0f172a;font-size:25px;letter-spacing:-.05em}.mg-accept-metric span{display:block;margin-top:4px;color:#64748b;font-size:10px;font-weight:950;text-transform:uppercase;letter-spacing:.08em}.mg-accept-flow{display:grid;grid-template-columns:1fr 1fr;gap:14px}.mg-accept-card{border:1px solid rgba(203,213,225,.82);border-radius:20px;background:#fff;padding:16px}.mg-accept-card h3{margin:0 0 8px;color:#0f172a;font-size:16px;letter-spacing:-.03em}.mg-accept-card p{margin:0;color:#64748b;font-size:12px;line-height:1.45}.mg-accept-users{display:grid;gap:8px;margin-top:12px}.mg-accept-user{display:grid;grid-template-columns:auto 1fr auto;gap:10px;align-items:center;border:1px solid rgba(203,213,225,.68);border-radius:14px;background:#f8fafc;padding:9px}.mg-accept-user i{display:grid;place-items:center;width:32px;height:32px;border-radius:999px;background:#cffafe;color:#0e7490;font-style:normal;font-size:11px;font-weight:950}.mg-accept-user strong{display:block;color:#0f172a;font-size:12px}.mg-accept-user small{display:block;color:#64748b;font-size:10px;margin-top:2px}.mg-accept-user em{border-radius:999px;background:#e2e8f0;color:#334155;padding:5px 8px;font-style:normal;font-size:10px;font-weight:950;text-transform:uppercase}.mg-accept-user.is-accepted em{background:#dcfce7;color:#166534}.mg-accept-user.is-declined em{background:#fee2e2;color:#991b1b}.mg-accept-bar{position:relative;height:14px;border-radius:999px;background:#e2e8f0;overflow:hidden;margin:12px 0 8px}.mg-accept-bar span{display:block;height:100%;width:0;background:linear-gradient(90deg,#2563eb,#10b981);transition:width .35s ease}.mg-accept-note{border:1px dashed rgba(37,99,235,.28);border-radius:16px;background:#eff6ff;color:#1e3a8a;padding:12px;font-size:12px;line-height:1.45;font-weight:800}.mg-accept-warn{border-color:rgba(245,158,11,.42);background:#fffbeb;color:#92400e}.mg-accept-ok{border-color:rgba(16,185,129,.34);background:#ecfdf5;color:#065f46}.mg-accept-actions{display:flex;flex-wrap:wrap;gap:10px;justify-content:flex-end;border-top:1px solid rgba(203,213,225,.72);padding-top:14px}.mg-accept-actions button{min-height:42px;border:0;border-radius:13px;padding:0 14px;font-size:12px;font-weight:950;cursor:pointer}.mg-accept-actions .dark{background:#0f172a;color:#fff}.mg-accept-actions .blue{background:#2563eb;color:#fff}.mg-accept-actions .soft{background:#eff6ff;color:#1d4ed8}.mg-accept-actions button:disabled{opacity:.5;cursor:not-allowed}@media(max-width:820px){.mg-accept-grid,.mg-accept-flow{grid-template-columns:1fr}.mg-accept-head{padding:16px}.mg-accept-body{padding:16px}}';
    document.head.appendChild(style);
  }
  function form(){return document.querySelector('[data-target-zone-form]');}
  function dropFromForm(f){
    if(!f||!f.elements.id)return null;
    return {id:String(f.elements.id.value||''),name:String(f.elements.drop_name&&f.elements.drop_name.value||'Target Drop'),title:String(f.elements.campaign_title&&f.elements.campaign_title.value||'Dropped reward'),radius:num(f.elements.radius_meters&&f.elements.radius_meters.value,2500),lat:num(f.elements.target_latitude&&f.elements.target_latitude.value,0),lng:num(f.elements.target_longitude&&f.elements.target_longitude.value,0),quantity:num(f.elements.quantity_limit&&f.elements.quantity_limit.value,0)};
  }
  function estimate(d){
    var h=hash(d.id+':'+d.radius+':'+d.lat+':'+d.lng),sqKm=Math.PI*Math.pow(d.radius/1000,2),density=18+(h%64),people=Math.round(clamp(sqKm*density,12,5200));
    var reachable=Math.round(clamp(people*(0.18+((h%17)/100)),4,950));
    var expectedRate=clamp(0.38+((h%29)/100),0.38,0.67);
    var accepted=Math.max(1,Math.round(reachable*expectedRate));
    var declined=Math.max(0,reachable-accepted);
    var stampCents=15;
    return {people:people,reachable:reachable,expectedRate:expectedRate,accepted:accepted,declined:declined,stamps:accepted,stampCents:stampCents,totalCents:accepted*stampCents,rewardQuantityRequired:accepted};
  }
  function users(est,d){
    var names=['Ava','Mia','Leo','Noah','Ezra','Luna','Kai','Ivy','Zoe','Max'];
    var list=[],show=Math.min(10,est.reachable),h=hash(d.id);
    for(var i=0;i<show;i++)list.push({name:names[(h+i)%names.length]+' · User '+String(100+h%700+i),status:'pending'});
    return list;
  }
  function metric(label,value){return '<div class="mg-accept-metric"><b>'+esc(value)+'</b><span>'+esc(label)+'</span></div>';}
  function statusNote(s){
    if(!s.notified)return '<div class="mg-accept-note">Test Launch now runs a mocked preflight first: estimate reach, notify users in the drop zone, calculate accepted users, then match the reward quantity before the delivery animation.</div>';
    if(s.pending>0)return '<div class="mg-accept-note">Mock notifications are out. Waiting for users in the Target Zone to accept or decline before delivery.</div>';
    if(s.quantityOk)return '<div class="mg-accept-note mg-accept-ok">Quantity matched. '+s.est.accepted+' accepted users can receive this reward, requiring '+s.est.stamps+' funded stamps.</div>';
    return '<div class="mg-accept-note mg-accept-warn">Reward quantity is short. Accepted users: '+s.est.accepted+'. Current reward quantity: '+s.quantity+'. Match the quantity before launching.</div>';
  }
  function render(){
    if(!active||!active.el)return;
    var s=active,est=s.est,rate=Math.round((s.acceptedSeen/Math.max(1,est.reachable))*100),progress=s.notified?Math.round(((s.acceptedSeen+s.declinedSeen)/Math.max(1,est.reachable))*100):0;
    s.quantityOk=s.quantity>=est.rewardQuantityRequired;
    active.el.querySelector('[data-accept-body]').innerHTML = '<div class="mg-accept-grid">'+metric('Estimated people in zone',est.people)+metric('Reachable inboxes',est.reachable)+metric('Accepted users',s.acceptedSeen)+metric('Accept rate',rate+'%')+'</div><div class="mg-accept-flow"><section class="mg-accept-card"><h3>Reward + stamp budget</h3><p>The campaign only delivers rewards to users who accept the drop notification. The reward quantity must cover the accepted users.</p><div class="mg-accept-grid" style="grid-template-columns:repeat(2,minmax(0,1fr));margin-top:12px">'+metric('Rewards needed',est.rewardQuantityRequired)+metric('Current quantity',s.quantity||'Not set')+metric('Stamps funded',est.stamps)+metric('Stamp fee',money(est.totalCents))+'</div></section><section class="mg-accept-card"><h3>Drop-zone notification</h3><p>Mock users receive an accept/decline notification before the reward is dropped into inboxes.</p><div class="mg-accept-bar"><span style="width:'+progress+'%"></span></div><p>'+progress+'% notification responses simulated.</p></section></div><section class="mg-accept-card"><h3>Sample users in Target Zone</h3><div class="mg-accept-users">'+s.users.map(function(u,i){return '<div class="mg-accept-user '+(u.status==='accepted'?'is-accepted':u.status==='declined'?'is-declined':'')+'"><i>U'+(i+1)+'</i><div><strong>'+esc(u.name)+'</strong><small>Inside Target Zone · '+esc(s.drop.title)+'</small></div><em>'+esc(u.status)+'</em></div>';}).join('')+'</div></section>'+statusNote(s)+'<div class="mg-accept-actions"><button type="button" class="soft" data-accept-notify '+(s.notified?'disabled':'')+'>Notify users / simulate accepts</button><button type="button" class="blue" data-accept-match>Match reward quantity to '+est.rewardQuantityRequired+'</button><button type="button" class="dark" data-accept-launch '+(!s.notified||s.pending>0||!s.quantityOk?'disabled':'')+'>Fund stamps & run Test Launch</button></div>';
  }
  function openPreflight(d,f){
    ensureStyle(); close();
    var est=estimate(d),wrap=document.createElement('div');
    wrap.className='mg-accept-backdrop';
    wrap.innerHTML='<article class="mg-accept-modal"><header class="mg-accept-head"><div><span>Target Zone preflight</span><strong>Acceptance + quantity check</strong><p>Mock flow for stamp-funded Target Drop delivery before the launch animation.</p></div><button type="button" class="mg-accept-close" data-accept-close>×</button></header><div class="mg-accept-body" data-accept-body></div></article>';
    document.body.appendChild(wrap);
    active={el:wrap,form:f,drop:d,est:est,users:users(est,d),notified:false,pending:est.reachable,acceptedSeen:0,declinedSeen:0,quantity:d.quantity,quantityOk:false,timer:0};
    render();
  }
  function close(){if(active&&active.el&&active.el.parentNode)active.el.remove();if(active&&active.timer)clearInterval(active.timer);active=null;}
  function simulate(){
    if(!active||active.notified)return;
    active.notified=true;active.pending=active.est.reachable;active.acceptedSeen=0;active.declinedSeen=0;
    var ticks=0,totalTicks=12,acceptedFinal=active.est.accepted,declinedFinal=active.est.declined;
    active.timer=setInterval(function(){
      ticks++;
      active.acceptedSeen=Math.min(acceptedFinal,Math.round(acceptedFinal*(ticks/totalTicks)));
      active.declinedSeen=Math.min(declinedFinal,Math.round(declinedFinal*(ticks/totalTicks)));
      active.pending=Math.max(0,active.est.reachable-active.acceptedSeen-active.declinedSeen);
      active.users.forEach(function(u,i){if(i<Math.ceil(active.users.length*(ticks/totalTicks))){u.status=(i%4===3?'declined':'accepted');}});
      render();
      if(ticks>=totalTicks){clearInterval(active.timer);active.timer=0;active.acceptedSeen=acceptedFinal;active.declinedSeen=declinedFinal;active.pending=0;active.users.forEach(function(u,i){u.status=(i%4===3?'declined':'accepted');});render();}
    },220);
    render();
  }
  function matchQuantity(){if(!active)return;active.quantity=active.est.rewardQuantityRequired;if(active.form&&active.form.elements.quantity_limit)active.form.elements.quantity_limit.value=String(active.quantity);render();}
  function setPanelStatus(message){var f=form(),slot=f&&f.querySelector('[data-target-zone-status]');if(slot)slot.textContent=message;}
  async function runLaunch(){
    if(!active||!active.quantityOk||active.pending>0)return;
    var d=active.drop,token=csrf(),accepted=active.est.accepted,stamps=active.est.stamps,reachable=active.est.reachable,quantity=active.quantity;
    setPanelStatus('Accepted '+accepted+' users. Funding '+stamps+' stamps and starting Test Launch…');
    try{
      var res=await MG.post('/api/world-canvas/runs.php',{id:d.id,csrf_token:token,_csrf:token,csrf:token,mock_acceptance_count:String(accepted),mock_stamp_count:String(stamps),mock_reward_quantity:String(quantity)});
      var data=res.data||res||{},launchDrop=data.delivery_run||null;
      close();
      if(launchDrop&&window.MicrogifterTargetDropTestLaunch&&typeof window.MicrogifterTargetDropTestLaunch.launch==='function'){
        window.MicrogifterTargetDropTestLaunch.launch(launchDrop,{duration:Number(launchDrop.duration_ms||30000),elapsed_ms:Number(launchDrop.elapsed_ms||0)});
      }
      setPanelStatus('Mock delivery queued: '+accepted+' accepted users will receive the reward in their inbox after delivery. Accept rate: '+Math.round((accepted/Math.max(1,reachable))*100)+'%.');
    }catch(error){setPanelStatus((error&&error.message)?error.message:'Unable to start test launch.');}
  }
  window.addEventListener('click',function(event){
    if(event.target.closest('[data-accept-close]')){event.preventDefault();close();return;}
    if(event.target.closest('[data-accept-notify]')){event.preventDefault();simulate();return;}
    if(event.target.closest('[data-accept-match]')){event.preventDefault();matchQuantity();return;}
    if(event.target.closest('[data-accept-launch]')){event.preventDefault();runLaunch();return;}
    var btn=event.target.closest('[data-target-zone-test]');
    if(!btn)return;
    var f=btn.closest('[data-target-zone-form]')||form(),d=dropFromForm(f);
    if(!d||!d.id)return;
    event.preventDefault();event.stopPropagation();if(event.stopImmediatePropagation)event.stopImmediatePropagation();
    openPreflight(d,f);
  },true);
})(window,document);
