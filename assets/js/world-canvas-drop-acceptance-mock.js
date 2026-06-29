window.Microgifter = window.Microgifter || {};
(function(window, document){
  'use strict';
  var root = document.querySelector('[data-world-canvas]');
  if (!root || !window.Microgifter || window.Microgifter.__worldDropAcceptanceMock) return;
  window.Microgifter.__worldDropAcceptanceMock = true;

  var MG = window.Microgifter;
  var active = null;

  function esc(v){return String(v == null ? '' : v).replace(/[&<>"']/g,function(c){return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];});}
  function clamp(v,min,max){return Math.max(min,Math.min(max,v));}
  function num(v,f){var n = Number(v); return Number.isFinite(n) ? n : f;}
  function csrf(){var m=document.querySelector('meta[name="csrf-token"],meta[name="mg-csrf-token"]');return m ? (m.getAttribute('content') || '') : (window.MG_CSRF_TOKEN || '');}
  function money(cents){return '$' + (Math.round(cents) / 100).toFixed(2);}
  function hash(v){var s=String(v||'x'),h=0;for(var i=0;i<s.length;i++){h=((h<<5)-h)+s.charCodeAt(i);h|=0;}return Math.abs(h);}

  function ensureStyle(){
    if (document.querySelector('[data-world-drop-acceptance-style]')) return;
    var style=document.createElement('style');
    style.dataset.worldDropAcceptanceStyle='1';
    style.textContent='.mg-accept-backdrop{position:fixed;inset:0;z-index:2147483200;background:rgba(15,23,42,.34);backdrop-filter:blur(10px);display:grid;place-items:center;padding:22px}.mg-accept-modal{width:min(980px,calc(100vw - 28px));max-height:calc(100dvh - 36px);overflow:auto;border-radius:24px;background:#fff;box-shadow:0 32px 110px rgba(15,23,42,.32);border:1px solid rgba(203,213,225,.82)}.mg-accept-head{display:flex;justify-content:space-between;gap:18px;padding:20px 22px;border-bottom:1px solid rgba(203,213,225,.7);background:linear-gradient(135deg,#fff,#f8fbff)}.mg-accept-head span{display:block;color:#2563eb;font-size:11px;font-weight:950;text-transform:uppercase;letter-spacing:.13em}.mg-accept-head strong{display:block;margin-top:4px;color:#0f172a;font-size:24px;letter-spacing:-.04em}.mg-accept-head p{margin:7px 0 0;color:#64748b;font-size:13px;line-height:1.45}.mg-accept-close{width:38px;height:38px;border:0;border-radius:12px;background:#eef2ff;color:#0f172a;font-size:22px;cursor:pointer}.mg-accept-body{padding:20px 22px 22px;display:grid;gap:16px}.mg-accept-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.mg-accept-metric{border:1px solid rgba(203,213,225,.82);border-radius:18px;background:#f8fafc;padding:14px}.mg-accept-metric b{display:block;color:#0f172a;font-size:25px;letter-spacing:-.05em}.mg-accept-metric span{display:block;margin-top:4px;color:#64748b;font-size:10px;font-weight:950;text-transform:uppercase;letter-spacing:.08em}.mg-accept-flow{display:grid;grid-template-columns:1fr 1fr;gap:14px}.mg-accept-card{border:1px solid rgba(203,213,225,.82);border-radius:20px;background:#fff;padding:16px}.mg-accept-card h3{margin:0 0 8px;color:#0f172a;font-size:16px;letter-spacing:-.03em}.mg-accept-card p{margin:0;color:#64748b;font-size:12px;line-height:1.45}.mg-accept-users{display:grid;gap:8px;margin-top:12px}.mg-accept-user{display:grid;grid-template-columns:auto 1fr auto;gap:10px;align-items:center;border:1px solid rgba(203,213,225,.68);border-radius:14px;background:#f8fafc;padding:9px}.mg-accept-user i{display:grid;place-items:center;width:32px;height:32px;border-radius:999px;background:#cffafe;color:#0e7490;font-style:normal;font-size:11px;font-weight:950}.mg-accept-user strong{display:block;color:#0f172a;font-size:12px}.mg-accept-user small{display:block;color:#64748b;font-size:10px;margin-top:2px}.mg-accept-user em{border-radius:999px;background:#e2e8f0;color:#334155;padding:5px 8px;font-style:normal;font-size:10px;font-weight:950;text-transform:uppercase}.mg-accept-user.is-notified em{background:#dbeafe;color:#1d4ed8}.mg-accept-user.is-accepted em{background:#dcfce7;color:#166534}.mg-accept-user.is-declined em{background:#fee2e2;color:#991b1b}.mg-accept-bar{height:14px;border-radius:999px;background:#e2e8f0;overflow:hidden;margin:12px 0 8px}.mg-accept-bar span{display:block;height:100%;width:0;background:linear-gradient(90deg,#2563eb,#10b981);transition:width .35s ease}.mg-accept-note{border:1px dashed rgba(37,99,235,.28);border-radius:16px;background:#eff6ff;color:#1e3a8a;padding:12px;font-size:12px;line-height:1.45;font-weight:800}.mg-accept-warn{border-color:rgba(245,158,11,.42);background:#fffbeb;color:#92400e}.mg-accept-ok{border-color:rgba(16,185,129,.34);background:#ecfdf5;color:#065f46}.mg-accept-actions{display:flex;flex-wrap:wrap;gap:10px;justify-content:flex-end;border-top:1px solid rgba(203,213,225,.72);padding-top:14px}.mg-accept-actions button{min-height:42px;border:0;border-radius:13px;padding:0 14px;font-size:12px;font-weight:950;cursor:pointer}.mg-accept-actions .dark{background:#0f172a;color:#fff}.mg-accept-actions button:disabled{opacity:.5;cursor:not-allowed}@media(max-width:820px){.mg-accept-grid,.mg-accept-flow{grid-template-columns:1fr}.mg-accept-head{padding:16px}.mg-accept-body{padding:16px}}';
    document.head.appendChild(style);
  }

  function form(){return document.querySelector('[data-target-zone-form]');}
  function dropFromForm(f){
    if(!f || !f.elements.id) return null;
    return {
      id:String(f.elements.id.value || ''),
      title:String((f.elements.campaign_title && f.elements.campaign_title.value) || 'Dropped media pack'),
      campaign_id:String((f.elements.campaign_public_id && f.elements.campaign_public_id.value) || ''),
      payload:String((f.elements.payload_type && f.elements.payload_type.value) || 'audio_pack'),
      radius:num(f.elements.radius_meters && f.elements.radius_meters.value,2500),
      lat:num(f.elements.target_latitude && f.elements.target_latitude.value,0),
      lng:num(f.elements.target_longitude && f.elements.target_longitude.value,0),
      form_quantity:num(f.elements.quantity_limit && f.elements.quantity_limit.value,0)
    };
  }
  function estimate(d){
    var h=hash(d.id+':'+d.radius+':'+d.lat+':'+d.lng), sqKm=Math.PI*Math.pow(d.radius/1000,2);
    var people=Math.round(clamp(sqKm*(18+(h%64)),12,5200));
    var reachable=Math.round(clamp(people*(0.18+((h%17)/100)),4,950));
    var expectedRate=clamp(0.42+((h%25)/100),0.42,0.67);
    var projectedAccepts=Math.max(1,Math.round(reachable*expectedRate));
    var reservedRewards=Math.max(1,Math.ceil(projectedAccepts*1.1));
    var stampCents=15;
    return {people:people,reachable:reachable,expectedRate:expectedRate,projectedAccepts:projectedAccepts,reservedRewards:reservedRewards,stampCents:stampCents,totalCents:reservedRewards*stampCents};
  }
  function makeUsers(est,d){
    var names=['Ava','Mia','Leo','Noah','Ezra','Luna','Kai','Ivy','Zoe','Max'],out=[],show=Math.min(10,est.reachable),h=hash(d.id);
    for(var i=0;i<show;i++) out.push({name:names[(h+i)%names.length]+' · User '+String(100+h%700+i),status:'eligible'});
    return out;
  }
  async function fetchInventory(d){
    var t=csrf();
    try{
      var res=await MG.post('/api/world-canvas/drop-campaign-inventory.php',{id:d.id,campaign_public_id:d.campaign_id,csrf_token:t,_csrf:t,csrf:t});
      return res.data || res || {};
    }catch(error){
      return {campaign_found:false,message:(error && error.message) ? error.message : 'Unable to check campaign reward quantity.'};
    }
  }
  function availableQty(){
    if(!active) return 0;
    var inv=active.inventory || {};
    if(inv.available_quantity === null || inv.available_quantity === undefined) return active.drop.form_quantity;
    return num(inv.available_quantity,0);
  }
  function qtySource(){
    if(!active) return 'Target Drop';
    var inv=active.inventory || {};
    if(inv.campaign_found && inv.campaign) return 'Campaign: '+(inv.campaign.campaign_title || active.drop.title);
    if(active.drop.campaign_id) return 'Attached campaign not found';
    return 'Target Drop form quantity';
  }
  function hasEnoughRewards(){return active && availableQty() >= active.est.reservedRewards;}
  function metric(label,value){return '<div class="mg-accept-metric"><b>'+esc(value)+'</b><span>'+esc(label)+'</span></div>';}
  function statusNote(){
    if(!active) return '';
    var q=availableQty(), need=active.est.reservedRewards;
    if(active.loading) return '<div class="mg-accept-note">Checking the attached campaign and reward quantity…</div>';
    if(active.stage === 'preflight' && q < need) return '<div class="mg-accept-note mg-accept-warn">Reward quantity warning only. '+esc(qtySource())+' has '+q+' available, and this Target Drop estimates '+need+' rewards for projected post-delivery accepts. Test launch is still enabled.</div>';
    if(active.stage === 'preflight') return '<div class="mg-accept-note mg-accept-ok">Ready to send. You are sending the campaign first and reserving stamps/rewards for expected acceptance after the media pack lands.</div>';
    if(active.stage === 'sending') return '<div class="mg-accept-note">Campaign sent. Delivery animation is in flight. Interaction tracking starts after the drop lands.</div>';
    return '<div class="mg-accept-note mg-accept-ok">Drop landed. Users are accepting or declining the media pack from their inbox notification.</div>';
  }
  function render(){
    if(!active || !active.el) return;
    var e=active.est, q=availableQty(), accepted=active.accepted || 0, declined=active.declined || 0, responded=accepted+declined;
    var rate=Math.round((accepted/Math.max(1,e.reachable))*100), progress=Math.round((responded/Math.max(1,e.reachable))*100);
    var rewardTitle=(active.inventory && active.inventory.reward && active.inventory.reward.title) || active.drop.title;
    var disabled=active.loading || active.stage !== 'preflight';
    var btnText=active.stage === 'preflight' ? 'Agree to pay '+money(e.totalCents)+' for stamps and send' : (active.stage === 'sending' ? 'Sent — waiting for landing' : 'Tracking post-delivery accepts');
    active.el.querySelector('[data-accept-body]').innerHTML='<div class="mg-accept-grid">'+metric('Estimated people',e.people)+metric('Reachable inboxes',e.reachable)+metric('Projected accepts',e.projectedAccepts)+metric('Reserved rewards',e.reservedRewards)+'</div><div class="mg-accept-flow"><section class="mg-accept-card"><h3>Campaign reward quantity</h3><p>'+esc(qtySource())+'</p><div class="mg-accept-grid" style="grid-template-columns:repeat(2,minmax(0,1fr));margin-top:12px">'+metric('Reward/media pack',rewardTitle)+metric('Available quantity',q)+metric('Needed reserve',e.reservedRewards)+metric('Stamp budget',money(e.totalCents))+'</div></section><section class="mg-accept-card"><h3>Post-landing acceptance tracking</h3><p>Interaction analytics start after the campaign lands in the Target Zone.</p><div class="mg-accept-bar"><span style="width:'+progress+'%"></span></div><p>'+progress+'% responses simulated · '+accepted+' accepted · '+declined+' declined · '+rate+'% accept rate.</p></section></div><section class="mg-accept-card"><h3>Sample users in Target Zone</h3><div class="mg-accept-users">'+active.users.map(function(u,i){return '<div class="mg-accept-user '+(u.status==='accepted'?'is-accepted':u.status==='declined'?'is-declined':u.status==='notified'?'is-notified':'')+'"><i>U'+(i+1)+'</i><div><strong>'+esc(u.name)+'</strong><small>Eligible for '+esc(active.drop.payload||'media pack')+' · '+esc(active.drop.title)+'</small></div><em>'+esc(u.status)+'</em></div>';}).join('')+'</div></section>'+statusNote()+'<div class="mg-accept-actions"><button type="button" class="dark" data-accept-send '+(disabled?'disabled':'')+'>'+esc(btnText)+'</button></div>';
  }
  async function openPreflight(d,f){
    ensureStyle(); close();
    var est=estimate(d), wrap=document.createElement('div');
    wrap.className='mg-accept-backdrop';
    wrap.innerHTML='<article class="mg-accept-modal"><header class="mg-accept-head"><div><span>Target Zone preflight</span><strong>Send first, track accepts after landing</strong><p>Mock flow for stamp-funded audio/media pack drops.</p></div><button type="button" class="mg-accept-close" data-accept-close>×</button></header><div class="mg-accept-body" data-accept-body></div></article>';
    document.body.appendChild(wrap);
    active={el:wrap,form:f,drop:d,est:est,users:makeUsers(est,d),inventory:null,loading:true,stage:'preflight',accepted:0,declined:0,timer:0};
    render();
    active.inventory=await fetchInventory(d);
    active.loading=false;
    render();
  }
  function close(){if(active && active.el && active.el.parentNode) active.el.remove(); if(active && active.timer) clearInterval(active.timer); active=null;}
  function setPanelStatus(message){var f=form(),slot=f&&f.querySelector('[data-target-zone-status]');if(slot)slot.textContent=message;}
  function startTracking(){
    if(!active) return;
    active.stage='tracking'; active.accepted=0; active.declined=0;
    active.users.forEach(function(u){u.status='notified';});
    var ticks=0,totalTicks=12,acceptedFinal=active.est.projectedAccepts,declinedFinal=Math.max(0,active.est.reachable-acceptedFinal);
    render();
    active.timer=setInterval(function(){
      ticks++;
      active.accepted=Math.min(acceptedFinal,Math.round(acceptedFinal*(ticks/totalTicks)));
      active.declined=Math.min(declinedFinal,Math.round(declinedFinal*(ticks/totalTicks)));
      active.users.forEach(function(u,i){if(i<Math.ceil(active.users.length*(ticks/totalTicks))) u.status=(i%4===3?'declined':'accepted');});
      render();
      if(ticks>=totalTicks){clearInterval(active.timer);active.timer=0;active.accepted=acceptedFinal;active.declined=declinedFinal;active.users.forEach(function(u,i){u.status=(i%4===3?'declined':'accepted');});render();setPanelStatus('Drop landed. Tracking complete: '+acceptedFinal+' accepts, '+Math.round((acceptedFinal/Math.max(1,active.est.reachable))*100)+'% accept rate.');}
    },260);
  }
  async function sendDrop(){
    if(!active || active.stage !== 'preflight') return;
    var d=active.drop,t=csrf(),reserve=active.est.reservedRewards;
    active.stage='sending'; render();
    setPanelStatus('Paid '+money(active.est.totalCents)+' for '+reserve+' stamps. Sending Target Drop…');
    try{
      var res=await MG.post('/api/world-canvas/runs.php',{id:d.id,csrf_token:t,_csrf:t,csrf:t,mock_stamp_count:String(reserve),mock_reward_quantity:String(reserve),mock_acceptance_mode:'post_delivery'});
      var data=res.data||res||{}, run=data.delivery_run||null, duration=30000;
      if(run && window.MicrogifterTargetDropTestLaunch && typeof window.MicrogifterTargetDropTestLaunch.launch === 'function'){
        duration=Number(run.duration_ms||30000);
        window.MicrogifterTargetDropTestLaunch.launch(run,{duration:duration,elapsed_ms:Number(run.elapsed_ms||0)});
      }
      window.setTimeout(startTracking, Math.max(900, Math.min(duration, 30000)));
    }catch(error){
      active.stage='preflight'; render();
      setPanelStatus((error&&error.message)?error.message:'Unable to send Target Drop.');
    }
  }
  window.addEventListener('click',function(event){
    if(event.target.closest('[data-accept-close]')){event.preventDefault();close();return;}
    if(event.target.closest('[data-accept-send]')){event.preventDefault();sendDrop();return;}
    var btn=event.target.closest('[data-target-zone-test]');
    if(!btn) return;
    var f=btn.closest('[data-target-zone-form]')||form(), d=dropFromForm(f);
    if(!d || !d.id) return;
    event.preventDefault();event.stopPropagation();if(event.stopImmediatePropagation)event.stopImmediatePropagation();
    openPreflight(d,f);
  },true);
})(window,document);
