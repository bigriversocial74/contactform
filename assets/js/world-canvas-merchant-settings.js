window.Microgifter = window.Microgifter || {};
(function(window, document){
  'use strict';
  var root = document.querySelector('[data-world-canvas]');
  if (!root || !window.Microgifter) return;
  var MG = window.Microgifter;
  var map = root.querySelector('[data-world-map]');
  if (!map) return;

  var state = { tab: 'overview', settings: null, drops: [], campaigns: [], tools: [], catalog: [], loading: false };
  var tabs = [
    ['overview','Overview'],
    ['active','Active'],
    ['drafts','Drafts'],
    ['tools','Tools'],
    ['rewards','Rewards'],
    ['settings','Settings']
  ];

  function esc(value){return String(value == null ? '' : value).replace(/[&<>"']/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];});}
  function token(){var meta=document.querySelector('meta[name="csrf-token"],meta[name="mg-csrf-token"]');return meta?meta.getAttribute('content')||'':(window.MG_CSRF_TOKEN||'');}
  function pointFromNode(node){return {x:parseFloat(node.dataset.worldTargetX||node.style.left||50),y:parseFloat(node.dataset.worldTargetY||node.style.top||50)};}
  function zoneSize(meters){var m=Math.max(50,Math.min(5000,Number(meters||250)));return Math.max(36,Math.min(180,Math.round(34+(m/5000)*146)));}
  function addressText(location){return [location.address_line1,location.city,location.region,location.postal_code,location.country_code].filter(Boolean).join(', ');}
  function activeDrops(){return state.drops.filter(function(d){return ['launching','active','scheduled'].indexOf(d.status)>-1;});}
  function draftDrops(){return state.drops.filter(function(d){return d.owned && d.status==='draft';});}
  function ownedTools(){return state.tools || [];}

  function ensureButton(){
    var button = map.querySelector('[data-world-merchant-settings-open]');
    if (button) { button.innerHTML = '<span>World Dashboard</span>'; return button; }
    button = document.createElement('button');
    button.type = 'button';
    button.className = 'mg-world-merchant-settings-btn';
    button.dataset.worldMerchantSettingsOpen = '1';
    button.innerHTML = '<span>World Dashboard</span>';
    map.appendChild(button);
    return button;
  }

  function ensurePanel(){
    var panel = document.querySelector('[data-world-merchant-settings-panel]');
    if (panel) return panel;
    panel = document.createElement('aside');
    panel.className = 'mg-world-merchant-settings-panel mg-world-dashboard-panel';
    panel.dataset.worldMerchantSettingsPanel = '1';
    panel.setAttribute('aria-hidden','true');
    panel.innerHTML = '<div class="mg-world-merchant-settings-head"><div><span>World Dashboard</span><strong>My World</strong><small>Campaigns, drafts, tools, rewards, and location settings for the World Canvas.</small></div><button type="button" data-world-merchant-settings-close>×</button></div><div class="mg-world-dashboard-tabs" data-world-dashboard-tabs></div><div class="mg-world-merchant-settings-body" data-world-merchant-settings-body><p>Loading World Dashboard…</p></div>';
    document.body.appendChild(panel);
    return panel;
  }

  function ensureZoneLayer(){
    var viewport = map.querySelector('[data-world-viewport]');
    var layer = map.querySelector('[data-world-merchant-zone-layer]');
    if (!layer) { layer = document.createElement('div'); layer.className = 'mg-world-merchant-zone-layer'; layer.dataset.worldMerchantZoneLayer = '1'; }
    if (viewport && layer.parentNode !== viewport) viewport.insertBefore(layer, viewport.firstChild);
    else if (!viewport && layer.parentNode !== map) map.insertBefore(layer, map.firstChild);
    return layer;
  }

  function renderZones(locations){
    var layer = ensureZoneLayer();
    var byId = {};
    (locations||[]).forEach(function(location){byId[location.public_id]=location;});
    var html = [];
    map.querySelectorAll('[data-world-node][data-world-type="merchant"]').forEach(function(node){
      var detail = node.dataset.worldDetailId || String(node.dataset.worldNodeId||'').replace(/^merchant:/,'');
      var location = byId[detail] || {};
      var point = pointFromNode(node);
      var size = zoneSize(location.world_zone_radius_meters || node.dataset.worldZoneRadius || 250);
      html.push('<span class="mg-world-merchant-zone" style="left:'+point.x+'%;top:'+point.y+'%;width:'+size+'px;height:'+size+'px" data-zone-for="'+esc(detail)+'"><b>MERCHANT</b></span>');
    });
    layer.innerHTML = html.join('');
  }

  function tabNav(){return tabs.map(function(t){return '<button type="button" data-world-dashboard-tab="'+t[0]+'" class="'+(state.tab===t[0]?'is-active':'')+'">'+t[1]+'</button>';}).join('');}
  function renderChrome(){var panel=ensurePanel(), nav=panel.querySelector('[data-world-dashboard-tabs]'); if(nav) nav.innerHTML=tabNav();}
  function metric(label,value){return '<div class="mg-world-dashboard-metric"><b>'+esc(value)+'</b><span>'+esc(label)+'</span></div>';}
  function dropCard(d){return '<article class="mg-world-dashboard-card"><div><strong>'+esc(d.campaign_title||d.drop_name||'Target Drop')+'</strong><span>'+esc((d.status||'draft').toUpperCase())+' · '+esc(d.payload_type||'reward')+'</span></div><p>'+esc((d.interest_count||0)+' interested · '+(d.radius_meters||0)+'m radius')+'</p><button type="button" data-dashboard-open-drop="'+esc(d.id)+'">Open Target Zone</button></article>';}
  function toolCard(t, locked){return '<article class="mg-world-tool-card '+(locked?'is-locked':'')+'"><div><strong>'+esc(t.name)+'</strong><span>'+esc((t.category||'tool')+' · '+(t.rarity||'common'))+'</span></div><p>'+esc(t.description||'World Canvas tool')+'</p><dl><div><dt>Range</dt><dd>'+esc(t.range_meters||0)+'m</dd></div><div><dt>Success</dt><dd>+'+esc(t.success_bonus_percent||0)+'</dd></div><div><dt>Cooldown</dt><dd>'+esc(t.cooldown_seconds||0)+'s</dd></div></dl><em>'+(locked?'Locked / coming soon':esc((t.status||'owned').toUpperCase()))+'</em></article>';}
  function rewardCard(name,type,desc){return '<article class="mg-world-reward-type-card"><span>'+esc(type)+'</span><strong>'+esc(name)+'</strong><p>'+esc(desc)+'</p><button type="button" disabled>Coming next</button></article>';}

  function overviewHtml(){
    var active = activeDrops(), drafts = draftDrops(), tools = ownedTools();
    return '<section class="mg-world-dashboard-section"><div class="mg-world-dashboard-metrics">'+metric('Active / Scheduled',active.length)+metric('Saved Drafts',drafts.length)+metric('Owned Tools',tools.length)+metric('Catalog Items',state.catalog.length)+'</div><div class="mg-world-dashboard-split"><div><h3>Active Target Campaigns</h3>'+(active.length?active.slice(0,4).map(dropCard).join(''):'<div class="mg-world-dashboard-empty">No active target campaigns yet.</div>')+'</div><div><h3>Tool Status</h3>'+(tools.length?tools.slice(0,3).map(function(t){return toolCard(t,false);}).join(''):'<div class="mg-world-dashboard-empty">Starter scanner will appear when tools are available.</div>')+'</div></div></section>';
  }
  function activeHtml(){var active=activeDrops();return '<section class="mg-world-dashboard-section"><h3>Active Target Campaigns</h3>'+(active.length?active.map(dropCard).join(''):'<div class="mg-world-dashboard-empty">No active or scheduled Target Drops.</div>')+'</section>';}
  function draftsHtml(){var drafts=draftDrops();return '<section class="mg-world-dashboard-section"><h3>Saved Drafts</h3>'+(drafts.length?drafts.map(dropCard).join(''):'<div class="mg-world-dashboard-empty">No saved draft Target Drops.</div>')+'</section>';}
  function toolsHtml(){
    var owned = ownedTools();
    var ownedKeys = {}; owned.forEach(function(t){ownedKeys[t.tool_id || t.id]=true;});
    var locked = (state.catalog||[]).filter(function(t){return !ownedKeys[t.id];});
    return '<section class="mg-world-dashboard-section"><h3>Tools / Equipment</h3><p class="mg-world-dashboard-note">Tools are permission-ready. All account types can view them for now.</p><h4>Owned</h4>'+(owned.length?owned.map(function(t){return toolCard(t,false);}).join(''):'<div class="mg-world-dashboard-empty">No owned tools loaded yet.</div>')+'<h4>Catalog</h4>'+(locked.length?locked.map(function(t){return toolCard(t,true);}).join(''):'<div class="mg-world-dashboard-empty">All catalog tools are owned or unavailable.</div>')+'</section>';
  }
  function rewardsHtml(){return '<section class="mg-world-dashboard-section"><h3>Reward Types</h3><p class="mg-world-dashboard-note">These are placeholders for the next reward-type build. They will become assignable to campaigns and Target Zones.</p><div class="mg-world-reward-type-grid">'+[
    rewardCard('Standard Reward','Claim','Basic coupon, discount, free item, or voucher.'),
    rewardCard('Timed Reward','Flash','Only claimable during a launch window.'),
    rewardCard('Proximity Reward','Geo','Claimable near a merchant location or Target Zone.'),
    rewardCard('Audio Pack Reward','Content','Unlocks audio, media, or content.'),
    rewardCard('Contest Entry Reward','Contest','Claim/join action creates an entry.'),
    rewardCard('Intercept Reward','Game','Unlocked only when a delivery is intercepted.'),
    rewardCard('Group Reward','Group','Unlocks after enough users join.'),
    rewardCard('Mystery Box Reward','Mystery','Hidden value until opened.')
  ].join('')+'</div></section>';}

  function formHtml(location){
    var address=addressText(location), mapped = location.latitude != null && location.longitude != null;
    var statusClass = mapped ? 'is-mapped' : 'is-missing', statusText = mapped ? 'Mapped' : 'Missing coordinates';
    var mapsUrl = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(address || location.name || '');
    return '<form class="mg-world-merchant-location-form '+statusClass+'" data-world-merchant-location-form data-location-id="'+esc(location.public_id)+'">'
      +'<div><strong>'+esc(location.name||'Merchant location')+'</strong><span>'+esc(address||location.location_code||'No address saved')+'</span>'+(Number(location.is_primary)?'<em>Primary</em>':'')+'<mark>'+esc(statusText)+'</mark></div>'
      +'<label>Latitude<input name="latitude" inputmode="decimal" value="'+esc(location.latitude==null?'':location.latitude)+'" placeholder="33.4484"></label>'
      +'<label>Longitude<input name="longitude" inputmode="decimal" value="'+esc(location.longitude==null?'':location.longitude)+'" placeholder="-112.0740"></label>'
      +'<label>Zone radius meters<input name="world_zone_radius_meters" inputmode="numeric" value="'+esc(location.world_zone_radius_meters||250)+'"></label>'
      +'<input type="hidden" name="location_id" value="'+esc(location.public_id)+'"><input type="hidden" name="geo_accuracy_meters" value="'+esc(location.geo_accuracy_meters||0)+'">'
      +'<div class="mg-world-merchant-location-actions"><button type="submit">Save zone</button><button type="button" data-world-use-current-location>Use current location</button><button type="button" data-world-find-location>Find on World Canvas</button><a href="'+esc(mapsUrl)+'" target="_blank" rel="noopener">Search address</a><a href="/merchant-locations.php">Edit location</a></div><p data-world-location-status></p></form>';
  }
  function settingsHtml(){
    var payload = state.settings || {};
    if (payload.error) return '<section class="mg-world-dashboard-section"><h3>Settings</h3><div class="mg-world-merchant-settings-empty"><strong>Settings unavailable</strong><p>'+esc(payload.error)+'</p></div></section>';
    if (!payload.schema_ready) return '<section class="mg-world-dashboard-section"><h3>Settings</h3><div class="mg-world-merchant-settings-empty"><strong>World geo columns missing</strong><p>Import Stage 27 before setting map zones.</p></div></section>';
    var locations = payload.locations || [];
    return '<section class="mg-world-dashboard-section"><h3>Location Zones</h3><p class="mg-world-dashboard-note">Merchant location mapping stays here while the dashboard expands.</p>'+(locations.length ? locations.map(formHtml).join('') : '<div class="mg-world-merchant-settings-empty"><strong>No merchant locations</strong><p>Add locations first.</p><a href="/merchant-locations.php">Open merchant locations</a></div>')+'</section>';
  }

  function renderBody(){
    var body = ensurePanel().querySelector('[data-world-merchant-settings-body]'); if(!body)return;
    if (state.loading) body.innerHTML = '<div class="mg-world-dashboard-empty">Loading World Dashboard…</div>';
    else if (state.tab==='active') body.innerHTML = activeHtml();
    else if (state.tab==='drafts') body.innerHTML = draftsHtml();
    else if (state.tab==='tools') body.innerHTML = toolsHtml();
    else if (state.tab==='rewards') body.innerHTML = rewardsHtml();
    else if (state.tab==='settings') body.innerHTML = settingsHtml();
    else body.innerHTML = overviewHtml();
    bindLocationForms(body);
  }

  function renderDashboard(){renderChrome();renderBody();}
  function focusLocation(form){var id=form.dataset.locationId||'';var node=map.querySelector('[data-world-node-id="'+CSS.escape(id)+'"], [data-world-detail-id="'+CSS.escape(id)+'"]');if(node){node.scrollIntoView({behavior:'smooth',block:'center',inline:'center'});node.classList.add('is-active');setTimeout(function(){node.classList.remove('is-active');},1800);}}
  function useCurrentLocation(form){var status=form.querySelector('[data-world-location-status]');if(!navigator.geolocation){if(status)status.textContent='Browser location is not available.';return;}if(status)status.textContent='Reading browser location…';navigator.geolocation.getCurrentPosition(function(pos){if(form.elements.latitude)form.elements.latitude.value=pos.coords.latitude.toFixed(7);if(form.elements.longitude)form.elements.longitude.value=pos.coords.longitude.toFixed(7);if(form.elements.geo_accuracy_meters)form.elements.geo_accuracy_meters.value=Math.round(pos.coords.accuracy||0);if(status)status.textContent='Location filled. Save zone to map this merchant location.';},function(){if(status)status.textContent='Unable to read browser location.';},{enableHighAccuracy:false,timeout:8000,maximumAge:300000});}
  function bindLocationForms(body){
    body.querySelectorAll('[data-world-merchant-location-form]').forEach(function(form){
      form.addEventListener('click',function(event){if(event.target.closest('[data-world-use-current-location]'))useCurrentLocation(form);if(event.target.closest('[data-world-find-location]'))focusLocation(form);});
      form.addEventListener('submit',async function(event){event.preventDefault();var status=form.querySelector('[data-world-location-status]'),data=Object.fromEntries(new FormData(form).entries()),csrf=token();data.csrf_token=csrf;data._csrf=csrf;data.csrf=csrf;data.geo_source='world_dashboard';try{if(status)status.textContent='Saving…';await MG.post('/api/world-canvas/merchant-world-settings.php',data);if(status)status.textContent='Saved. Refreshing map zones…';await loadDashboard(false);document.dispatchEvent(new CustomEvent('mg:world-merchant-settings-saved'));}catch(error){if(status)status.textContent=error.message||'Unable to save zone.';}});
    });
  }

  async function loadDashboard(open){
    var panel = ensurePanel();
    if(open){panel.classList.add('is-open');panel.setAttribute('aria-hidden','false');}
    if(!state.settings && !state.drops.length) state.loading = true;
    renderDashboard();
    var settingsReq = MG.get('/api/world-canvas/merchant-world-settings.php').then(function(r){state.settings=r.data||r||{};renderZones((state.settings&&state.settings.locations)||[]);}).catch(function(e){state.settings={error:e.message||'Unable to load location settings.'};});
    var dropsReq = MG.get('/api/world-canvas/target-drops.php').then(function(r){var p=r.data||r||{};state.drops=p.drops||[];state.campaigns=p.campaigns||[];}).catch(function(){state.drops=[];state.campaigns=[];});
    var toolsReq = MG.get('/api/world-canvas/intercept-tools.php').then(function(r){var p=r.data||r||{};state.tools=p.tools||[];state.catalog=p.catalog||[];}).catch(function(){state.tools=[];state.catalog=[];});
    await Promise.allSettled([settingsReq,dropsReq,toolsReq]);
    state.loading = false;
    renderDashboard();
  }

  ensureButton().addEventListener('click', function(){ loadDashboard(true); });
  document.addEventListener('click', function(event){
    var tab = event.target.closest('[data-world-dashboard-tab]');
    if(tab){state.tab=tab.dataset.worldDashboardTab||'overview';renderDashboard();return;}
    if(event.target.closest('[data-world-merchant-settings-close]')){var panel=ensurePanel();panel.classList.remove('is-open');panel.setAttribute('aria-hidden','true');return;}
    var openDrop = event.target.closest('[data-dashboard-open-drop]');
    if(openDrop){var id=openDrop.dataset.dashboardOpenDrop;var btn=map.querySelector('[data-target-drop-id="'+CSS.escape(id)+'"]');if(btn)btn.click();}
  });
  window.setInterval(function(){loadDashboard(false);}, 7000);
  document.addEventListener('mg:world-merchant-settings-saved', function(){setTimeout(function(){loadDashboard(false);}, 500);});
  document.addEventListener('mg:world-target-drop-saved', function(){setTimeout(function(){loadDashboard(false);}, 500);});
  loadDashboard(false);
})(window, document);
