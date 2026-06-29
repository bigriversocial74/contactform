window.Microgifter = window.Microgifter || {};
(function(window, document){
  'use strict';
  var root = document.querySelector('[data-world-canvas]');
  if (!root || !window.Microgifter || window.Microgifter.__worldDropAcceptanceLite) return;
  window.Microgifter.__worldDropAcceptanceLite = true;

  var MG = window.Microgifter;
  var active = null;

  function esc(v){return String(v == null ? '' : v).replace(/[&<>"']/g,function(c){return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];});}
  function clamp(v,min,max){return Math.max(min,Math.min(max,v));}
  function num(v,f){var n = Number(v); return Number.isFinite(n) ? n : f;}
  function csrf(){var m=document.querySelector('meta[name="csrf-token"],meta[name="mg-csrf-token"]');return m ? (m.getAttribute('content') || '') : (window.MG_CSRF_TOKEN || '');}
  function money(cents){return '$' + (Math.round(cents) / 100).toFixed(2);}
  function hash(v){var s=String(v||'x'),h=0;for(var i=0;i<s.length;i++){h=((h<<5)-h)+s.charCodeAt(i);h|=0;}return Math.abs(h);}

  function ensureStyle(){
    if (document.querySelector('[data-world-drop-acceptance-lite-style]')) return;
    var style=document.createElement('style');
    style.dataset.worldDropAcceptanceLiteStyle='1';
    style.textContent='.mg-accept-backdrop{position:fixed;inset:0;z-index:2147483200;background:rgba(15,23,42,.34);backdrop-filter:blur(10px);display:grid;place-items:center;padding:22px}.mg-accept-modal{width:min(940px,calc(100vw - 28px));max-height:calc(100dvh - 36px);overflow:auto;border-radius:24px;background:#fff;box-shadow:0 32px 110px rgba(15,23,42,.32);border:1px solid rgba(203,213,225,.82)}.mg-accept-head{display:flex;justify-content:space-between;gap:18px;padding:20px 22px;border-bottom:1px solid rgba(203,213,225,.7);background:linear-gradient(135deg,#fff,#f8fbff)}.mg-accept-head span{display:block;color:#2563eb;font-size:11px;font-weight:950;text-transform:uppercase;letter-spacing:.13em}.mg-accept-head strong{display:block;margin-top:4px;color:#0f172a;font-size:24px;letter-spacing:-.04em}.mg-accept-head p{margin:7px 0 0;color:#64748b;font-size:13px;line-height:1.45}.mg-accept-close{width:38px;height:38px;border:0;border-radius:12px;background:#eef2ff;color:#0f172a;font-size:22px;cursor:pointer}.mg-accept-body{padding:20px 22px 22px;display:grid;gap:16px}.mg-accept-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.mg-accept-metric{border:1px solid rgba(203,213,225,.82);border-radius:18px;background:#f8fafc;padding:14px}.mg-accept-metric b{display:block;color:#0f172a;font-size:25px;letter-spacing:-.05em}.mg-accept-metric span{display:block;margin-top:4px;color:#64748b;font-size:10px;font-weight:950;text-transform:uppercase;letter-spacing:.08em}.mg-accept-note{border:1px dashed rgba(37,99,235,.28);border-radius:16px;background:#eff6ff;color:#1e3a8a;padding:12px;font-size:12px;line-height:1.45;font-weight:800}.mg-accept-card{border:1px solid rgba(203,213,225,.82);border-radius:20px;background:#fff;padding:16px}.mg-accept-card h3{margin:0 0 8px;color:#0f172a;font-size:16px;letter-spacing:-.03em}.mg-accept-card p{margin:0;color:#64748b;font-size:12px;line-height:1.45}.mg-accept-actions{display:flex;flex-wrap:wrap;gap:10px;justify-content:flex-end;border-top:1px solid rgba(203,213,225,.72);padding-top:14px}.mg-accept-actions button{min-height:42px;border:0;border-radius:13px;padding:0 14px;font-size:12px;font-weight:950;cursor:pointer}.mg-accept-actions .dark{background:#0f172a;color:#fff}.mg-accept-actions button:disabled{opacity:.5;cursor:not-allowed}@media(max-width:820px){.mg-accept-grid{grid-template-columns:1fr}.mg-accept-head{padding:16px}.mg-accept-body{padding:16px}}';
    document.head.appendChild(style);
  }

  function currentForm(){return document.querySelector('[data-target-zone-form]');}
  function selectedCampaignTitle(f){
    var select = f && f.elements.campaign_public_id;
    if (!select || !select.options || select.selectedIndex < 0) return '';
    var option = select.options[select.selectedIndex];
    return option && option.value ? option.textContent : '';
  }
  function dropFromForm(f){
    if(!f || !f.elements.id) return null;
    return {
      id:String(f.elements.id.value || ''),
      title:selectedCampaignTitle(f) || 'Target Drop launch test',
      campaign_id:String((f.elements.campaign_public_id && f.elements.campaign_public_id.value) || ''),
      radius:num(f.elements.radius_meters && f.elements.radius_meters.value,2500),
      lat:num(f.elements.target_latitude && f.elements.target_latitude.value,0),
      lng:num(f.elements.target_longitude && f.elements.target_longitude.value,0)
    };
  }
  function estimate(d){
    var h=hash(d.id+':'+d.radius+':'+d.lat+':'+d.lng), sqKm=Math.PI*Math.pow(d.radius/1000,2);
    var people=Math.round(clamp(sqKm*(18+(h%64)),12,5200));
    var reachable=Math.round(clamp(people*(0.18+((h%17)/100)),4,950));
    var expectedRate=clamp(0.42+((h%25)/100),0.42,0.67);
    var projectedAccepts=Math.max(1,Math.round(reachable*expectedRate));
    var stampCents=15;
    return {people:people,reachable:reachable,projectedAccepts:projectedAccepts,stampCents:stampCents,totalCents:projectedAccepts*stampCents};
  }
  function metric(label,value){return '<div class="mg-accept-metric"><b>'+esc(value)+'</b><span>'+esc(label)+'</span></div>';}
  function setPanelStatus(message){var f=currentForm(),slot=f&&f.querySelector('[data-target-zone-status]');if(slot)slot.textContent=message;}
  function render(){
    if(!active || !active.el) return;
    var e=active.est;
    var disabled=active.stage !== 'preflight' || active.loading;
    var campaign=active.drop.campaign_id ? active.drop.title : 'No campaign selected';
    var note=active.drop.campaign_id ? 'Launch test will use the saved campaign assignment. Reward quantity is not blocking this test run.' : 'No campaign is selected. Reward quantity is not blocking this test run.';
    active.el.querySelector('[data-accept-body]').innerHTML='<div class="mg-accept-grid">'+metric('Estimated people',e.people)+metric('Reachable inboxes',e.reachable)+metric('Projected accepts',e.projectedAccepts)+metric('Stamp budget',money(e.totalCents))+'</div><section class="mg-accept-card"><h3>Campaign assignment</h3><p>'+esc(campaign)+'</p></section><div class="mg-accept-note">'+esc(note)+'</div><div class="mg-accept-actions"><button type="button" class="dark" data-accept-send '+(disabled?'disabled':'')+'>Agree to pay '+money(e.totalCents)+' for stamps and send test launch</button></div>';
  }
  function close(){if(active && active.el && active.el.parentNode) active.el.remove(); active=null;}
  function openPreflight(d,f){
    ensureStyle(); close();
    var wrap=document.createElement('div');
    wrap.className='mg-accept-backdrop';
    wrap.innerHTML='<article class="mg-accept-modal"><header class="mg-accept-head"><div><span>Target Zone preflight</span><strong>Send first, track accepts after landing</strong><p>Campaign assignment comes from the Target Zone sidebar. Reward quantity is bypassed for this test launch.</p></div><button type="button" class="mg-accept-close" data-accept-close>×</button></header><div class="mg-accept-body" data-accept-body></div></article>';
    document.body.appendChild(wrap);
    active={el:wrap,form:f,drop:d,est:estimate(d),stage:'preflight',loading:false};
    render();
  }
  async function sendDrop(){
    if(!active || active.stage !== 'preflight') return;
    var d=active.drop,t=csrf(),reserve=active.est.projectedAccepts;
    active.stage='sending'; active.loading=true; render();
    setPanelStatus('Paid '+money(active.est.totalCents)+' for '+reserve+' stamps. Sending Target Drop test launch…');
    try{
      var res=await MG.post('/api/world-canvas/runs.php',{id:d.id,csrf_token:t,_csrf:t,csrf:t,mock_stamp_count:String(reserve),mock_reward_quantity:String(reserve),mock_acceptance_mode:'post_delivery'});
      var data=res.data||res||{}, run=data.delivery_run||null;
      if(run && window.MicrogifterTargetDropTestLaunch && typeof window.MicrogifterTargetDropTestLaunch.launch === 'function'){
        window.MicrogifterTargetDropTestLaunch.launch(run,{duration:Number(run.duration_ms||30000),elapsed_ms:Number(run.elapsed_ms||0)});
      }
      setPanelStatus('Test launch sent. Reward quantity did not block this run.');
      close();
    }catch(error){
      active.stage='preflight'; active.loading=false; render();
      setPanelStatus((error&&error.message)?error.message:'Unable to send Target Drop test launch.');
    }
  }
  window.addEventListener('click',function(event){
    if(event.target.closest('[data-accept-close]')){event.preventDefault();close();return;}
    if(event.target.closest('[data-accept-send]')){event.preventDefault();sendDrop();return;}
    var btn=event.target.closest('[data-target-zone-test]');
    if(!btn) return;
    var f=btn.closest('[data-target-zone-form]')||currentForm(), d=dropFromForm(f);
    if(!d || !d.id) return;
    event.preventDefault();event.stopPropagation();if(event.stopImmediatePropagation)event.stopImmediatePropagation();
    openPreflight(d,f);
  },true);
})(window,document);
