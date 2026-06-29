window.Microgifter = window.Microgifter || {};
(function(window, document){
  'use strict';
  var root = document.querySelector('[data-world-canvas]');
  if (!root || !window.Microgifter) return;
  var MG = window.Microgifter;
  var map = root.querySelector('[data-world-map]');
  if (!map) return;
  var drops = [];
  var activeDrop = null;
  var dragState = null;
  var suppressClick = false;

  function esc(value){return String(value == null ? '' : value).replace(/[&<>"']/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];});}
  function token(){var meta=document.querySelector('meta[name="csrf-token"],meta[name="mg-csrf-token"]');return meta?meta.getAttribute('content')||'':(window.MG_CSRF_TOKEN||'');}
  function clamp(v,min,max){return Math.max(min,Math.min(max,v));}
  function viewport(){return map.querySelector('[data-world-viewport]') || map;}
  function pointToGeo(point){return {latitude:clamp(85-(point.y/100)*170,-85,85),longitude:clamp((point.x/100)*360-180,-180,180)};}
  function geoToPoint(lat,lng){return {x:clamp(((Number(lng)+180)/360)*100,0,100),y:clamp(((85-Number(lat))/170)*100,0,100)};}
  function radiusSize(meters){var m=clamp(Number(meters||2500),250,5000000);return clamp(48+(Math.log10(m)-2.3)*58,48,260);}
  function metersFromSize(size){return Math.round(clamp(Math.pow(10, ((clamp(size,48,260)-48)/58)+2.3),250,5000000));}
  function nowIsoLocal(){var d=new Date(Date.now()+3600000);d.setMinutes(0,0,0);return d.toISOString().slice(0,16);}
  function expiresIsoLocal(){var d=new Date(Date.now()+86400000);d.setMinutes(0,0,0);return d.toISOString().slice(0,16);}

  function pointFromEvent(event){
    var vp = viewport();
    var rect = map.getBoundingClientRect();
    var x = event.clientX - rect.left;
    var y = event.clientY - rect.top;
    try{
      var matrix = new DOMMatrix(window.getComputedStyle(vp).transform);
      var inv = matrix.inverse();
      var p = new DOMPoint(x,y).matrixTransform(inv);
      x = p.x; y = p.y;
    }catch(e){}
    return {x:clamp((x/rect.width)*100,0,100),y:clamp((y/rect.height)*100,0,100)};
  }

  function layer(){
    var vp = viewport();
    var el = map.querySelector('[data-world-target-drops-layer]');
    if (!el) { el = document.createElement('div'); el.className = 'mg-world-target-drops-layer'; el.dataset.worldTargetDropsLayer = '1'; }
    if (el.parentNode !== vp) vp.appendChild(el);
    return el;
  }

  function launchLayer(){
    var vp = viewport();
    var el = map.querySelector('[data-world-target-launch-layer]');
    if (!el) { el = document.createElement('div'); el.className = 'mg-world-target-launch-layer'; el.dataset.worldTargetLaunchLayer = '1'; }
    if (el.parentNode !== vp) vp.appendChild(el);
    return el;
  }

  function ensurePanel(){
    var panel = document.querySelector('[data-world-target-zone-panel]');
    if (panel) return panel;
    panel = document.createElement('aside');
    panel.className = 'mg-world-target-zone-panel';
    panel.dataset.worldTargetZonePanel = '1';
    panel.setAttribute('aria-hidden','true');
    panel.innerHTML = '<div class="mg-target-zone-head"><div><span>Campaign Drops</span><strong>TARGET ZONE</strong><small>Attach a reward, schedule the drop, and launch it into a real place.</small></div><button type="button" data-target-zone-close>×</button></div><div class="mg-target-zone-body" data-target-zone-body></div>';
    document.body.appendChild(panel);
    return panel;
  }

  function openPanel(drop){activeDrop = drop; var panel = ensurePanel(); panel.classList.add('is-open'); panel.setAttribute('aria-hidden','false'); renderPanel(drop);}
  function closePanel(){var panel = ensurePanel(); panel.classList.remove('is-open'); panel.setAttribute('aria-hidden','true');}

  function renderPanel(drop){
    var body = ensurePanel().querySelector('[data-target-zone-body]');
    if (!body || !drop) return;
    body.innerHTML = '<form class="mg-target-zone-form" data-target-zone-form>'
      +'<input type="hidden" name="id" value="'+esc(drop.id)+'">'
      +'<label>Drop name<input name="drop_name" value="'+esc(drop.drop_name||'Target Drop')+'"></label>'
      +'<label>Campaign / reward title<input name="campaign_title" value="'+esc(drop.campaign_title||'')+'" placeholder="Free audio pack, coffee reward, contest entry"></label>'
      +'<label>Payload type<select name="payload_type"><option value="reward">Reward</option><option value="gift">Gift</option><option value="audio_pack">Audio Pack</option><option value="contest">Contest</option><option value="offer">Offer</option><option value="announcement">Announcement</option></select></label>'
      +'<div class="mg-target-zone-grid"><label>Target latitude<input name="target_latitude" value="'+esc(drop.target_latitude)+'"></label><label>Target longitude<input name="target_longitude" value="'+esc(drop.target_longitude)+'"></label></div>'
      +'<label>Spread / radius meters<input name="radius_meters" inputmode="numeric" value="'+esc(drop.radius_meters||2500)+'"></label>'
      +'<div class="mg-target-zone-grid"><label>Launch time<input type="datetime-local" name="launch_at" value="'+esc((drop.launch_at||'').replace(' ','T').slice(0,16) || nowIsoLocal())+'"></label><label>End time<input type="datetime-local" name="expires_at" value="'+esc((drop.expires_at||'').replace(' ','T').slice(0,16) || expiresIsoLocal())+'"></label></div>'
      +'<div class="mg-target-zone-grid"><label>Quantity limit<input name="quantity_limit" inputmode="numeric" value="'+esc(drop.quantity_limit||'')+'" placeholder="optional"></label><label>Claim limit / user<input name="claim_limit_per_user" inputmode="numeric" value="'+esc(drop.claim_limit_per_user||1)+'"></label></div>'
      +'<label>Visibility<select name="visibility"><option value="public">Public</option><option value="private">Private</option><option value="invite_only">Invite only</option><option value="audience">Audience</option></select></label>'
      +'<div class="mg-target-zone-switches"><label><input type="checkbox" name="teaser_enabled" '+(drop.teaser_enabled?'checked':'')+'> Show teaser before launch</label><label><input type="checkbox" name="signup_required" '+(drop.signup_required?'checked':'')+'> Signup required</label></div>'
      +'<div class="mg-target-zone-actions"><button type="submit">Save draft</button><button type="button" data-target-zone-publish>Publish / Schedule</button></div>'
      +'<p data-target-zone-status></p>'
      +'</form>';
    var form = body.querySelector('[data-target-zone-form]');
    if (form && form.elements.payload_type) form.elements.payload_type.value = drop.payload_type || 'reward';
    if (form && form.elements.visibility) form.elements.visibility.value = drop.visibility || 'public';
  }

  function renderDrops(){
    layer().innerHTML = drops.map(function(drop){
      var point = drop.target_x != null ? {x:Number(drop.target_x),y:Number(drop.target_y)} : geoToPoint(drop.target_latitude, drop.target_longitude);
      var size = radiusSize(drop.radius_meters);
      var label = drop.status === 'scheduled' ? 'SCHEDULED' : drop.status === 'active' || drop.status === 'launching' ? 'ACTIVE' : drop.status === 'expired' ? 'EXPIRED' : 'DRAFT';
      return '<button type="button" class="mg-world-target-drop is-'+esc(drop.status)+' '+(drop.owned?'is-owned':'')+'" data-target-drop-id="'+esc(drop.id)+'" style="left:'+point.x+'%;top:'+point.y+'%;width:'+size+'px;height:'+size+'px"><span></span><b>'+esc(label)+'</b><em>'+esc(drop.campaign_title||drop.drop_name||'Target Drop')+'</em><i data-target-radius-handle title="Drag to change spread"></i><small data-target-settings title="Target Zone settings">⚙</small></button>';
    }).join('');
  }

  function payloadFromForm(form, action){var data = Object.fromEntries(new FormData(form).entries()); data.action = action || 'update'; var csrf = token(); data.csrf_token = csrf; data._csrf = csrf; data.csrf = csrf; data.teaser_enabled = form.elements.teaser_enabled && form.elements.teaser_enabled.checked ? '1' : '0'; data.signup_required = form.elements.signup_required && form.elements.signup_required.checked ? '1' : '0'; return data;}
  function mergeDrop(drop){drops = drops.filter(function(item){return item.id!==drop.id;}); drops.unshift(drop); renderDrops(); if(activeDrop && activeDrop.id===drop.id) openPanel(drop);}
  async function saveDropQuick(drop){try{var csrf=token();await MG.post('/api/world-canvas/target-drops.php',{action:'update',id:drop.id,target_latitude:drop.target_latitude,target_longitude:drop.target_longitude,radius_meters:drop.radius_meters,drop_name:drop.drop_name||'Target Drop',campaign_title:drop.campaign_title||'',payload_type:drop.payload_type||'reward',visibility:drop.visibility||'public',launch_at:drop.launch_at||'',expires_at:drop.expires_at||'',quantity_limit:drop.quantity_limit||'',claim_limit_per_user:drop.claim_limit_per_user||1,teaser_enabled:drop.teaser_enabled?'1':'0',signup_required:drop.signup_required?'1':'0',csrf_token:csrf,_csrf:csrf,csrf:csrf});}catch(e){}}
  async function loadDrops(){try{var response = await MG.get('/api/world-canvas/target-drops.php'); var payload = response.data || response || {}; drops = payload.drops || []; renderDrops();}catch(e){}}
  async function createDropAt(point){var geo = pointToGeo(point); try{var csrf = token(); var response = await MG.post('/api/world-canvas/target-drops.php', {action:'create',target_latitude:geo.latitude,target_longitude:geo.longitude,radius_meters:2500,csrf_token:csrf,_csrf:csrf,csrf:csrf}); var drop = (response.data && response.data.drop) || response.drop; if (drop) { drops.unshift(drop); renderDrops(); openPanel(drop); }}catch(error){console.warn(error);}}

  function launchAnimation(drop){
    if (!drop || drop.launch_x == null || drop.launch_y == null) return;
    var target = drop.target_x != null ? {x:Number(drop.target_x),y:Number(drop.target_y)} : geoToPoint(drop.target_latitude, drop.target_longitude);
    var start = {x:Number(drop.launch_x),y:Number(drop.launch_y)};
    var el = document.createElement('div'); el.className = 'mg-world-drop-package'; el.innerHTML = '<span>🎁</span><i></i>'; el.style.left = start.x + '%'; el.style.top = start.y + '%'; launchLayer().appendChild(el);
    var startTime = performance.now();
    function frame(now){var t=Math.min(1,(now-startTime)/1300);var ease=t<.5?2*t*t:1-Math.pow(-2*t+2,2)/2;var arc=Math.sin(ease*Math.PI)*-13;el.style.left=(start.x+(target.x-start.x)*ease)+'%';el.style.top=(start.y+(target.y-start.y)*ease+arc)+'%';el.style.transform='translate(-50%,-50%) scale('+(1+.18*Math.sin(ease*Math.PI))+') rotate('+(ease*24)+'deg)';if(t<1)requestAnimationFrame(frame);else{el.classList.add('has-landed');setTimeout(function(){el.remove();},700);}}
    requestAnimationFrame(frame);
  }

  function startDrag(event){var dropEl = event.target.closest('[data-target-drop-id]'); if (!dropEl || event.target.closest('[data-target-settings]')) return; var drop = drops.find(function(item){return item.id===dropEl.dataset.targetDropId;}); if (!drop || !drop.owned) return; event.preventDefault(); event.stopPropagation(); suppressClick = true; dragState = {id:drop.id, mode:event.target.closest('[data-target-radius-handle]')?'radius':'center'}; document.body.classList.add('is-target-drop-dragging');}
  function moveDrag(event){if (!dragState) return; var drop = drops.find(function(item){return item.id===dragState.id;}); if (!drop) return; var point = pointFromEvent(event); if (dragState.mode === 'center') {var geo = pointToGeo(point); drop.target_latitude = geo.latitude.toFixed(7); drop.target_longitude = geo.longitude.toFixed(7); drop.target_x = point.x; drop.target_y = point.y;} else {var center = drop.target_x != null ? {x:Number(drop.target_x),y:Number(drop.target_y)} : geoToPoint(drop.target_latitude, drop.target_longitude); var rect = map.getBoundingClientRect(); var px = Math.hypot((point.x-center.x)/100*rect.width, (point.y-center.y)/100*rect.height) * 2; drop.radius_meters = metersFromSize(px);} renderDrops(); if (activeDrop && activeDrop.id === drop.id) renderPanel(drop);}
  function endDrag(){if (!dragState) return; var drop = drops.find(function(item){return item.id===dragState.id;}); dragState = null; document.body.classList.remove('is-target-drop-dragging'); if (drop) saveDropQuick(drop); setTimeout(function(){suppressClick=false;},100);}

  map.addEventListener('pointerdown', startDrag); document.addEventListener('pointermove', moveDrag); document.addEventListener('pointerup', endDrag);
  map.addEventListener('click', function(event){if (suppressClick) return; if (event.target.closest('[data-target-drop-id]')) return; if (event.target.closest('button,a,input,select,label,textarea,.mg-world-square-zoom,.mg-world-merchant-settings-btn')) return; createDropAt(pointFromEvent(event));});
  document.addEventListener('click', function(event){if (suppressClick) return; var close = event.target.closest('[data-target-zone-close]'); if (close) { closePanel(); return; } var settings = event.target.closest('[data-target-settings]'); var dropButton = event.target.closest('[data-target-drop-id]'); if (settings || dropButton) { var id = (dropButton || settings.closest('[data-target-drop-id]')).dataset.targetDropId; var drop = drops.find(function(item){return item.id===id;}); if (drop) openPanel(drop); }});
  document.addEventListener('submit', async function(event){var form = event.target.closest('[data-target-zone-form]'); if (!form) return; event.preventDefault(); var status = form.querySelector('[data-target-zone-status]'); try{if(status) status.textContent='Saving…'; var response = await MG.post('/api/world-canvas/target-drops.php', payloadFromForm(form,'update')); var drop = (response.data && response.data.drop) || response.drop; if(drop) mergeDrop(drop); if(status) status.textContent='Saved.';}catch(error){if(status) status.textContent=error.message||'Unable to save Target Drop.';}});
  document.addEventListener('click', async function(event){var publish = event.target.closest('[data-target-zone-publish]'); if (!publish) return; var form = publish.closest('[data-target-zone-form]'); if (!form) return; var status = form.querySelector('[data-target-zone-status]'); try{if(status) status.textContent='Publishing…'; var response = await MG.post('/api/world-canvas/target-drops.php', payloadFromForm(form,'publish')); var drop = (response.data && response.data.drop) || response.drop; if(drop){mergeDrop(drop); launchAnimation(drop);} if(status) status.textContent='Drop launched / scheduled.';}catch(error){if(status) status.textContent=error.message||'Unable to publish Target Drop.';}});

  window.setInterval(loadDrops, 12000);
  loadDrops();
})(window, document);
