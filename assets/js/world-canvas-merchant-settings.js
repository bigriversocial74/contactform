window.Microgifter = window.Microgifter || {};
(function(window, document){
  'use strict';
  var root = document.querySelector('[data-world-canvas]');
  if (!root || !window.Microgifter) return;
  var MG = window.Microgifter;
  var map = root.querySelector('[data-world-map]');
  if (!map) return;

  function esc(value){return String(value == null ? '' : value).replace(/[&<>"']/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];});}
  function token(){var meta=document.querySelector('meta[name="csrf-token"],meta[name="mg-csrf-token"]');return meta?meta.getAttribute('content')||'':(window.MG_CSRF_TOKEN||'');}
  function pointFromNode(node){return {x:parseFloat(node.dataset.worldTargetX||node.style.left||50),y:parseFloat(node.dataset.worldTargetY||node.style.top||50)};}
  function zoneSize(meters){var m=Math.max(50,Math.min(5000,Number(meters||250)));return Math.max(36,Math.min(180,Math.round(34+(m/5000)*146)));}

  function ensureButton(){
    var button = map.querySelector('[data-world-merchant-settings-open]');
    if (button) return button;
    button = document.createElement('button');
    button.type = 'button';
    button.className = 'mg-world-merchant-settings-btn';
    button.dataset.worldMerchantSettingsOpen = '1';
    button.innerHTML = '<span>Merchant World Settings</span>';
    map.appendChild(button);
    return button;
  }

  function ensurePanel(){
    var panel = document.querySelector('[data-world-merchant-settings-panel]');
    if (panel) return panel;
    panel = document.createElement('aside');
    panel.className = 'mg-world-merchant-settings-panel';
    panel.dataset.worldMerchantSettingsPanel = '1';
    panel.setAttribute('aria-hidden','true');
    panel.innerHTML = '<div class="mg-world-merchant-settings-head"><div><span>Merchant World</span><strong>Location zones</strong><small>Use your existing merchant locations. Set lat/long and zone radius for the World Canvas map.</small></div><button type="button" data-world-merchant-settings-close>×</button></div><div class="mg-world-merchant-settings-body" data-world-merchant-settings-body><p>Loading merchant locations…</p></div>';
    document.body.appendChild(panel);
    return panel;
  }

  function ensureZoneLayer(){
    var viewport = map.querySelector('[data-world-viewport]');
    var layer = map.querySelector('[data-world-merchant-zone-layer]');
    if (!layer) {
      layer = document.createElement('div');
      layer.className = 'mg-world-merchant-zone-layer';
      layer.dataset.worldMerchantZoneLayer = '1';
    }
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

  function formHtml(location){
    var address=[location.address_line1,location.city,location.region].filter(Boolean).join(', ');
    return '<form class="mg-world-merchant-location-form" data-world-merchant-location-form data-location-id="'+esc(location.public_id)+'">'
      +'<div><strong>'+esc(location.name||'Merchant location')+'</strong><span>'+esc(address||location.location_code||'No address saved')+'</span>'+(Number(location.is_primary)?'<em>Primary</em>':'')+'</div>'
      +'<label>Latitude<input name="latitude" inputmode="decimal" value="'+esc(location.latitude==null?'':location.latitude)+'" placeholder="33.4484"></label>'
      +'<label>Longitude<input name="longitude" inputmode="decimal" value="'+esc(location.longitude==null?'':location.longitude)+'" placeholder="-112.0740"></label>'
      +'<label>Zone radius meters<input name="world_zone_radius_meters" inputmode="numeric" value="'+esc(location.world_zone_radius_meters||250)+'"></label>'
      +'<input type="hidden" name="location_id" value="'+esc(location.public_id)+'">'
      +'<div class="mg-world-merchant-location-actions"><button type="submit">Save zone</button><a href="/merchant-locations.php">Edit location</a></div>'
      +'<p data-world-location-status></p>'
      +'</form>';
  }

  function renderPanel(panel, payload){
    var body = panel.querySelector('[data-world-merchant-settings-body]');
    if (!body) return;
    if (!payload.schema_ready) {
      body.innerHTML = '<div class="mg-world-merchant-settings-empty"><strong>World geo columns missing</strong><p>Import Stage 27 before setting map zones.</p></div>';
      return;
    }
    var locations = payload.locations || [];
    body.innerHTML = locations.length ? locations.map(formHtml).join('') : '<div class="mg-world-merchant-settings-empty"><strong>No merchant locations</strong><p>Add locations in merchant-locations.php first.</p><a href="/merchant-locations.php">Open merchant locations</a></div>';
    body.querySelectorAll('[data-world-merchant-location-form]').forEach(function(form){
      form.addEventListener('submit', async function(event){
        event.preventDefault();
        var status = form.querySelector('[data-world-location-status]');
        var data = Object.fromEntries(new FormData(form).entries());
        var csrf = token();
        data.csrf_token = csrf; data._csrf = csrf; data.csrf = csrf; data.geo_source = 'merchant_world_settings';
        try{
          if(status) status.textContent = 'Saving…';
          await MG.post('/api/world-canvas/merchant-world-settings.php', data);
          if(status) status.textContent = 'Saved. Refreshing map zones…';
          await loadSettings(true);
          document.dispatchEvent(new CustomEvent('mg:world-merchant-settings-saved'));
        }catch(error){ if(status) status.textContent = error.message || 'Unable to save zone.'; }
      });
    });
  }

  async function loadSettings(open){
    var panel = ensurePanel();
    if(open){panel.classList.add('is-open');panel.setAttribute('aria-hidden','false');}
    try{
      var response = await MG.get('/api/world-canvas/merchant-world-settings.php');
      var payload = response.data || response || {};
      renderPanel(panel, payload);
      renderZones(payload.locations || []);
    }catch(error){
      var body = panel.querySelector('[data-world-merchant-settings-body]');
      if(body) body.innerHTML = '<div class="mg-world-merchant-settings-empty"><strong>Settings unavailable</strong><p>'+esc(error.message||'Unable to load merchant settings.')+'</p></div>';
    }
  }

  ensureButton().addEventListener('click', function(){ loadSettings(true); });
  document.addEventListener('click', function(event){
    if(event.target.closest('[data-world-merchant-settings-close]')){var panel=ensurePanel();panel.classList.remove('is-open');panel.setAttribute('aria-hidden','true');}
  });
  window.setInterval(function(){loadSettings(false);}, 5000);
  document.addEventListener('mg:world-merchant-settings-saved', function(){setTimeout(function(){loadSettings(false);}, 500);});
  loadSettings(false);
})(window, document);
