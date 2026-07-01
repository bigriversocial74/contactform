window.Microgifter = window.Microgifter || {};
(function(window, document){
  'use strict';
  var MG = window.Microgifter;
  function esc(value){return String(value == null ? '' : value).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
  function safeUrl(value){
    var raw = String(value || '').trim();
    if (!raw || /[\u0000-\u001f\u007f]/.test(raw)) return '';
    try { var parsed = new URL(raw, window.location.origin); if (!['http:','https:'].includes(parsed.protocol) || parsed.username || parsed.password) return ''; return raw.charAt(0)==='/' ? parsed.pathname + parsed.search + parsed.hash : parsed.href; } catch(e){ return ''; }
  }
  function initials(name){return String(name || 'MG').split(/\s+/).filter(Boolean).slice(0,2).map(function(p){return p.charAt(0);}).join('').toUpperCase() || 'MG';}
  function destination(item){
    var c = item && item.creative || {};
    var direct = safeUrl(c.destination_url || '');
    if (direct) return direct;
    if (c.destination_type === 'campaign' && c.destination_id) return '/campaign.php?id=' + encodeURIComponent(c.destination_id);
    if (item && item.public_id) return '/feed.php?ad=' + encodeURIComponent(item.public_id);
    return '/feed.php';
  }
  function track(item, eventType, extra){
    if (!item || !item.tracking || !item.tracking.ad_campaign_id) return Promise.resolve();
    var payload = Object.assign({}, item.tracking, {event_type:eventType, metadata: extra || {}});
    return fetch('/api/ads/track.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload),credentials:'same-origin'}).catch(function(){});
  }
  function cardHtml(item, options){
    var creative = item.creative || {};
    var merchant = item.merchant || {};
    var compact = options && options.compact;
    var media = safeUrl(creative.image_url || '');
    var href = destination(item);
    var label = creative.sponsored_label || (item.placement_key === 'target_zone_sponsored_drop' ? 'Sponsored Local Drop' : 'Sponsored');
    var desc = creative.description || '';
    var zone = item.zone && item.zone.name ? '<span class="mg-sponsored-pill">Zone: '+esc(item.zone.name)+'</span>' : '';
    return '<article class="mg-sponsored-card '+(compact?'mg-sponsored-sidebar':'')+'" data-sponsored-card data-ad-campaign-id="'+esc(item.public_id || '')+'">'
      + '<header class="mg-sponsored-top"><span class="mg-sponsored-avatar">'+(merchant.merchant_avatar_url?'<img src="'+esc(safeUrl(merchant.merchant_avatar_url))+'" alt="">':esc(initials(merchant.merchant_name)))+'</span><span class="mg-sponsored-identity"><strong>'+esc(merchant.merchant_name || 'Microgifter Merchant')+'</strong><small>'+esc(item.objective || 'local opportunity').replace(/[_-]+/g,' ')+'</small></span><span class="mg-sponsored-label">'+esc(label)+'</span></header>'
      + (media ? '<div class="mg-sponsored-media"><img src="'+esc(media)+'" alt="" loading="lazy"></div>' : '')
      + '<div class="mg-sponsored-body"><h3>'+esc(creative.headline || item.title || 'Sponsored Local Offer')+'</h3>'+(desc?'<p>'+esc(desc)+'</p>':'')+'</div>'
      + '<div class="mg-sponsored-meta"><span class="mg-sponsored-pill">'+esc((item.placement_key || 'feed').replace(/[_-]+/g,' '))+'</span>'+zone+(item.ends_at?'<span class="mg-sponsored-pill">Ends '+esc(item.ends_at)+'</span>':'')+'</div>'
      + '<div class="mg-sponsored-actions"><a class="mg-sponsored-cta" href="'+esc(href)+'" data-sponsored-click>'+esc(creative.cta_label || 'View Offer')+' →</a><button class="mg-sponsored-secondary" type="button" data-sponsored-save>Save</button></div>'
      + '</article>';
  }
  function mapHtml(item){
    var zone = item.zone || {};
    var x = Number(zone.x); var y = Number(zone.y); var w = Number(zone.width); var h = Number(zone.height);
    if (!Number.isFinite(x)) x = 62; if (!Number.isFinite(y)) y = 28; if (!Number.isFinite(w)) w = 28; if (!Number.isFinite(h)) h = 18;
    if (item.placement_key === 'target_zone_sponsored_drop') {
      return '<button class="mg-sponsored-zone" type="button" style="--mg-sponsored-x:'+x+'%;--mg-sponsored-y:'+y+'%;--mg-sponsored-w:'+w+'%;--mg-sponsored-h:'+h+'%;" data-sponsored-map-item data-ad-campaign-id="'+esc(item.public_id || '')+'"><span>'+esc((item.creative&&item.creative.headline)||'Sponsored Drop')+'</span></button>';
    }
    return '<button class="mg-sponsored-map-pin" type="button" style="--mg-sponsored-x:'+x+'%;--mg-sponsored-y:'+y+'%;" data-sponsored-map-item data-ad-campaign-id="'+esc(item.public_id || '')+'">Sponsored</button>';
  }
  function observeImpressions(container, items){
    if (!('IntersectionObserver' in window)) { items.forEach(function(item){track(item,'impression',{fallback:true});}); return; }
    var seen = new Set();
    var byId = {}; items.forEach(function(item){byId[item.public_id]=item;});
    var observer = new IntersectionObserver(function(entries){entries.forEach(function(entry){if(!entry.isIntersecting)return; var id=entry.target.getAttribute('data-ad-campaign-id'); if(!id||seen.has(id))return; seen.add(id); track(byId[id],'impression',{ratio:entry.intersectionRatio}); observer.unobserve(entry.target);});},{threshold:.35});
    container.querySelectorAll('[data-ad-campaign-id]').forEach(function(node){observer.observe(node);});
  }
  function renderContainer(container){
    var placement = container.getAttribute('data-mg-ad-placement') || 'feed_sponsored_card';
    var limit = container.getAttribute('data-mg-ad-limit') || (placement === 'sidebar_sponsored_card' ? '1' : '2');
    var isMap = placement === 'world_canvas_sponsored_pin' || placement === 'target_zone_sponsored_drop';
    var url = '/api/ads/render-placement.php?placement_key=' + encodeURIComponent(placement) + '&limit=' + encodeURIComponent(limit);
    fetch(url,{credentials:'same-origin'}).then(function(res){return res.json();}).then(function(out){
      var data = out && out.data || {}; var items = Array.isArray(data.items) ? data.items : [];
      if (!items.length) { container.classList.add('mg-sponsored-empty'); container.innerHTML=''; return; }
      container.classList.remove('mg-sponsored-empty');
      container.innerHTML = items.map(function(item){return isMap ? mapHtml(item) : cardHtml(item,{compact:placement==='sidebar_sponsored_card'});}).join('');
      container.querySelectorAll('[data-sponsored-click]').forEach(function(link){link.addEventListener('click',function(){var id=link.closest('[data-ad-campaign-id]').getAttribute('data-ad-campaign-id'); var item=items.find(function(i){return i.public_id===id;}); track(item,'click',{cta:true});});});
      container.querySelectorAll('[data-sponsored-save]').forEach(function(btn){btn.addEventListener('click',function(){var id=btn.closest('[data-ad-campaign-id]').getAttribute('data-ad-campaign-id'); var item=items.find(function(i){return i.public_id===id;}); track(item,'wallet_save',{local_preview:true}); btn.textContent='Saved'; btn.disabled=true;});});
      container.querySelectorAll('[data-sponsored-map-item]').forEach(function(btn){btn.addEventListener('click',function(){var id=btn.getAttribute('data-ad-campaign-id'); var item=items.find(function(i){return i.public_id===id;}); track(item,'click',{map:true}); alert(((item.creative&&item.creative.headline)||item.title||'Sponsored Local Drop') + '\n\n' + ((item.creative&&item.creative.description)||'Open this sponsored local opportunity from the feed or merchant campaign page.'));});});
      observeImpressions(container, items);
    }).catch(function(){container.classList.add('mg-sponsored-empty');});
  }
  MG.renderSponsoredCampaignCard = cardHtml;
  MG.renderSponsoredPlacements = function(scope){Array.prototype.slice.call((scope||document).querySelectorAll('[data-mg-ad-placement]')).forEach(renderContainer);};
  document.addEventListener('DOMContentLoaded',function(){MG.renderSponsoredPlacements(document);});
})(window, document);
