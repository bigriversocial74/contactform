window.Microgifter = window.Microgifter || {};
(function(window, document){
  'use strict';

  var MG = window.Microgifter;
  var ATTR_KEY = 'mg_ad_attribution_v1';

  function esc(value){return String(value == null ? '' : value).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
  function safeUrl(value){
    var raw = String(value || '').trim();
    if (!raw || /[\u0000-\u001f\u007f]/.test(raw)) return '';
    try {
      var parsed = new URL(raw, window.location.origin);
      if (!['http:','https:'].includes(parsed.protocol) || parsed.username || parsed.password) return '';
      return raw.charAt(0)==='/' ? parsed.pathname + parsed.search + parsed.hash : parsed.href;
    } catch(e){ return ''; }
  }
  function initials(name){return String(name || 'MG').split(/\s+/).filter(Boolean).slice(0,2).map(function(p){return p.charAt(0);}).join('').toUpperCase() || 'MG';}
  function cleanText(value, fallback){var text = String(value == null ? '' : value).trim(); return text || fallback || '';}
  function normalizePlacement(value, fallback){return cleanText(value, fallback || 'sidebar_sponsored_card').replace(/[^a-z0-9_-]/gi,'').toLowerCase() || fallback || 'sidebar_sponsored_card';}
  function labelForPlacement(placement){return placement.replace(/[_-]+/g,' ');}

  function normalizeItem(item, placement){
    item = item && typeof item === 'object' ? item : {};
    var creative = item.creative && typeof item.creative === 'object' ? item.creative : {};
    var merchant = item.merchant && typeof item.merchant === 'object' ? item.merchant : {};
    var publicId = cleanText(item.public_id || item.id, '');
    var placementKey = normalizePlacement(item.placement_key || placement, placement || 'sidebar_sponsored_card');
    var title = cleanText(item.title, 'Sponsored Local Offer');
    var headline = cleanText(creative.headline, title);
    var tracking = item.tracking && typeof item.tracking === 'object' ? item.tracking : null;
    if (!tracking && publicId) {
      tracking = {ad_campaign_id: publicId, placement_key: placementKey, surface: cleanText(item.surface, '')};
    }
    return Object.assign({}, item, {
      public_id: publicId,
      title: title,
      objective: cleanText(item.objective, 'local opportunity'),
      placement_key: placementKey,
      merchant: {
        merchant_name: cleanText(merchant.merchant_name || merchant.name, 'Microgifter Merchant'),
        merchant_avatar_url: safeUrl(merchant.merchant_avatar_url || merchant.avatar_url || '')
      },
      creative: {
        id: cleanText(creative.id, ''),
        headline: headline,
        description: cleanText(creative.description, ''),
        image_url: safeUrl(creative.image_url || ''),
        cta_label: cleanText(creative.cta_label, 'View Offer'),
        destination_type: cleanText(creative.destination_type, ''),
        destination_id: creative.destination_id || null,
        destination_url: safeUrl(creative.destination_url || ''),
        sponsored_label: cleanText(creative.sponsored_label, placementKey === 'target_zone_sponsored_drop' ? 'Sponsored Local Drop' : 'Sponsored')
      },
      tracking: tracking,
      zone: item.zone && typeof item.zone === 'object' ? item.zone : null
    });
  }

  function destination(item){
    item = normalizeItem(item);
    var c = item.creative || {};
    if (c.destination_url) return c.destination_url;
    if (c.destination_type === 'campaign' && c.destination_id) return '/campaign.php?id=' + encodeURIComponent(c.destination_id);
    if (item.public_id) return '/feed.php?ad=' + encodeURIComponent(item.public_id);
    return '/feed.php';
  }

  function attribution(item, source){
    if (!item || !item.tracking || !item.tracking.ad_campaign_id) return null;
    return {
      ad_campaign_id: item.tracking.ad_campaign_id,
      placement_key: item.tracking.placement_key || item.placement_key || 'sidebar_sponsored_card',
      surface: item.tracking.surface || item.surface || '',
      source: source || 'sponsored_campaign',
      captured_at: new Date().toISOString()
    };
  }

  function remember(item, source){
    var attr = attribution(item, source);
    if (!attr) return null;
    try {
      var payload = {ad_attribution: attr, expires_at: Date.now() + (1000 * 60 * 60 * 24 * 14)};
      window.sessionStorage && window.sessionStorage.setItem(ATTR_KEY, JSON.stringify(payload));
      window.localStorage && window.localStorage.setItem(ATTR_KEY, JSON.stringify(payload));
    } catch(e) {}
    MG.currentAdAttribution = attr;
    return attr;
  }

  function readStored(){
    var raw = '';
    try { raw = (window.sessionStorage && window.sessionStorage.getItem(ATTR_KEY)) || (window.localStorage && window.localStorage.getItem(ATTR_KEY)) || ''; } catch(e) { raw = ''; }
    if (!raw) return null;
    try {
      var parsed = JSON.parse(raw);
      if (!parsed || !parsed.ad_attribution || (parsed.expires_at && Date.now() > Number(parsed.expires_at))) return null;
      return parsed.ad_attribution;
    } catch(e) { return null; }
  }

  MG.getAdAttribution = function(){ return MG.currentAdAttribution || readStored(); };
  MG.applyAdAttribution = function(payload){
    payload = payload && typeof payload === 'object' ? payload : {};
    var attr = MG.getAdAttribution();
    if (attr && attr.ad_campaign_id && attr.placement_key) payload.ad_attribution = attr;
    return payload;
  };

  function track(item, eventType, extra){
    item = normalizeItem(item);
    if (!item.tracking || !item.tracking.ad_campaign_id) return Promise.resolve();
    var attr = eventType === 'click' || eventType === 'wallet_save' ? remember(item, eventType) : attribution(item, eventType);
    var meta = Object.assign({}, extra || {});
    if (attr) meta.ad_attribution = attr;
    var payload = Object.assign({}, item.tracking, {event_type:eventType, metadata: meta});
    return fetch('/api/ads/track.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload),credentials:'same-origin'}).catch(function(){});
  }

  function mediaHtml(item){
    var creative = item.creative || {};
    var media = safeUrl(creative.image_url || '');
    if (!media) return '<div class="mg-sponsored-media is-placeholder" aria-hidden="true"><span>'+esc(initials(creative.headline || item.title || 'MG'))+'</span></div>';
    return '<div class="mg-sponsored-media"><img src="'+esc(media)+'" alt="" loading="lazy" data-sponsored-image></div>';
  }

  function cardHtml(rawItem, options){
    var item = normalizeItem(rawItem, rawItem && rawItem.placement_key);
    var creative = item.creative || {};
    var merchant = item.merchant || {};
    var compact = options && options.compact;
    var href = destination(item);
    var zone = item.zone && item.zone.name ? '<span class="mg-sponsored-pill">Zone: '+esc(item.zone.name)+'</span>' : '';
    return '<article class="mg-sponsored-card '+(compact?'mg-sponsored-sidebar':'')+'" data-sponsored-card data-ad-campaign-id="'+esc(item.public_id || '')+'">'
      + '<header class="mg-sponsored-top"><span class="mg-sponsored-avatar">'+(merchant.merchant_avatar_url?'<img src="'+esc(merchant.merchant_avatar_url)+'" alt="" data-sponsored-image>':esc(initials(merchant.merchant_name)))+'</span><span class="mg-sponsored-identity"><strong>'+esc(merchant.merchant_name || 'Microgifter Merchant')+'</strong><small>'+esc(item.objective || 'local opportunity').replace(/[_-]+/g,' ')+'</small></span><span class="mg-sponsored-label">'+esc(creative.sponsored_label || 'Sponsored')+'</span></header>'
      + mediaHtml(item)
      + '<div class="mg-sponsored-body"><h3>'+esc(creative.headline || item.title || 'Sponsored Local Offer')+'</h3>'+(creative.description?'<p>'+esc(creative.description)+'</p>':'<p>Explore this sponsored local opportunity from Microgifter.</p>')+'</div>'
      + '<div class="mg-sponsored-meta"><span class="mg-sponsored-pill">'+esc(labelForPlacement(item.placement_key || 'feed'))+'</span>'+zone+(item.ends_at?'<span class="mg-sponsored-pill">Ends '+esc(item.ends_at)+'</span>':'')+'</div>'
      + '<div class="mg-sponsored-actions"><a class="mg-sponsored-cta" href="'+esc(href)+'" data-sponsored-click>'+esc(creative.cta_label || 'View Offer')+' →</a><button class="mg-sponsored-secondary" type="button" data-sponsored-save>Save</button></div>'
      + '</article>';
  }

  function clampPercent(value, fallback){
    var n = Number(value);
    if (!Number.isFinite(n)) n = fallback;
    return Math.max(0, Math.min(100, n));
  }

  function mapHtml(rawItem){
    var item = normalizeItem(rawItem, rawItem && rawItem.placement_key);
    var zone = item.zone || {};
    var x = clampPercent(zone.x, 62);
    var y = clampPercent(zone.y, 28);
    var w = Math.max(8, Math.min(80, Number(zone.width) || 28));
    var h = Math.max(8, Math.min(80, Number(zone.height) || 18));
    if (item.placement_key === 'target_zone_sponsored_drop') {
      return '<button class="mg-sponsored-zone" type="button" style="--mg-sponsored-x:'+x+'%;--mg-sponsored-y:'+y+'%;--mg-sponsored-w:'+w+'%;--mg-sponsored-h:'+h+'%;" data-sponsored-map-item data-ad-campaign-id="'+esc(item.public_id || '')+'"><span>'+esc((item.creative&&item.creative.headline)||'Sponsored Drop')+'</span></button>';
    }
    return '<button class="mg-sponsored-map-pin" type="button" style="--mg-sponsored-x:'+x+'%;--mg-sponsored-y:'+y+'%;" data-sponsored-map-item data-ad-campaign-id="'+esc(item.public_id || '')+'">Sponsored</button>';
  }

  function observeImpressions(container, items){
    if (!items.length) return;
    if (!('IntersectionObserver' in window)) { items.forEach(function(item){track(item,'impression',{fallback:true});}); return; }
    var seen = new Set();
    var byId = {}; items.forEach(function(item){if(item.public_id) byId[item.public_id]=item;});
    var observer = new IntersectionObserver(function(entries){entries.forEach(function(entry){if(!entry.isIntersecting)return; var id=entry.target.getAttribute('data-ad-campaign-id'); if(!id||seen.has(id))return; seen.add(id); track(byId[id],'impression',{ratio:entry.intersectionRatio}); observer.unobserve(entry.target);});},{threshold:.35});
    container.querySelectorAll('[data-ad-campaign-id]').forEach(function(node){observer.observe(node);});
  }

  function attachImageFallbacks(container){
    container.querySelectorAll('[data-sponsored-image]').forEach(function(img){
      img.addEventListener('error', function(){
        var wrap = img.closest('.mg-sponsored-media,.mg-sponsored-avatar');
        if (!wrap) return;
        wrap.classList.add('is-image-missing');
        if (wrap.classList.contains('mg-sponsored-media')) {
          wrap.innerHTML = '<span>Sponsored</span>';
        } else {
          wrap.textContent = 'MG';
        }
      }, {once:true});
    });
  }

  function setEmpty(container, reason){
    container.classList.add('mg-sponsored-empty');
    container.removeAttribute('aria-busy');
    container.setAttribute('data-sponsored-empty-reason', reason || 'empty');
    container.innerHTML='';
  }

  function renderContainer(container){
    var placement = normalizePlacement(container.getAttribute('data-mg-ad-placement') || 'feed_sponsored_card');
    var limit = container.getAttribute('data-mg-ad-limit') || (placement === 'sidebar_sponsored_card' ? '1' : '2');
    var isMap = placement === 'world_canvas_sponsored_pin' || placement === 'target_zone_sponsored_drop';
    var url = '/api/ads/render-placement.php?placement_key=' + encodeURIComponent(placement) + '&limit=' + encodeURIComponent(limit);
    container.setAttribute('aria-busy','true');
    fetch(url,{credentials:'same-origin'}).then(function(res){return res.json();}).then(function(out){
      var data = out && out.data || {};
      var rawItems = Array.isArray(data.items) ? data.items : [];
      var items = rawItems.map(function(item){return normalizeItem(item, placement);}).filter(function(item){return item.public_id && item.creative && item.creative.headline;});
      if (!items.length) { setEmpty(container, data.empty_reason || (data.placement_active === false ? 'placement_inactive' : 'no_ads')); return; }
      container.classList.remove('mg-sponsored-empty');
      container.removeAttribute('aria-busy');
      container.removeAttribute('data-sponsored-empty-reason');
      container.innerHTML = items.map(function(item){return isMap ? mapHtml(item) : cardHtml(item,{compact:placement==='sidebar_sponsored_card'});}).join('');
      attachImageFallbacks(container);
      container.querySelectorAll('[data-sponsored-click]').forEach(function(link){link.addEventListener('click',function(){var card=link.closest('[data-ad-campaign-id]'); var id=card && card.getAttribute('data-ad-campaign-id'); var item=items.find(function(i){return i.public_id===id;}); track(item,'click',{cta:true});});});
      container.querySelectorAll('[data-sponsored-save]').forEach(function(btn){btn.addEventListener('click',function(){var card=btn.closest('[data-ad-campaign-id]'); var id=card && card.getAttribute('data-ad-campaign-id'); var item=items.find(function(i){return i.public_id===id;}); track(item,'wallet_save',{local_preview:true}); btn.textContent='Saved'; btn.disabled=true;});});
      container.querySelectorAll('[data-sponsored-map-item]').forEach(function(btn){btn.addEventListener('click',function(){var id=btn.getAttribute('data-ad-campaign-id'); var item=items.find(function(i){return i.public_id===id;}); remember(item,'map_click'); track(item,'click',{map:true}); window.dispatchEvent(new CustomEvent('mg:sponsored-map-click',{detail:{item:item}})); if (!window.Microgifter || typeof window.Microgifter.openSponsoredMapCard !== 'function') alert(((item.creative&&item.creative.headline)||item.title||'Sponsored Local Drop') + '\n\n' + ((item.creative&&item.creative.description)||'Open this sponsored local opportunity from the feed or merchant campaign page.'));});});
      observeImpressions(container, items);
    }).catch(function(){setEmpty(container,'request_failed');});
  }

  MG.renderSponsoredCampaignCard = cardHtml;
  MG.renderSponsoredPlacements = function(scope){Array.prototype.slice.call((scope||document).querySelectorAll('[data-mg-ad-placement]')).forEach(renderContainer);};
  document.addEventListener('DOMContentLoaded',function(){MG.renderSponsoredPlacements(document);});
})(window, document);
