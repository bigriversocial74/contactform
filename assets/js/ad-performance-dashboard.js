(function(window, document){
  'use strict';
  var root = document.querySelector('[data-ad-performance-dashboard]');
  if (!root) return;
  var scope = root.getAttribute('data-performance-scope') || 'merchant';
  var endpoint = '/api/ads/performance.php' + (scope === 'admin' ? '?scope=admin' : '');
  function esc(value){return String(value == null ? '' : value).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
  function qs(sel, base){return (base||root).querySelector(sel);}
  function num(value){return Number(value || 0).toLocaleString();}
  function pct(value){return Number(value || 0).toFixed(2).replace(/\.00$/,'') + '%';}
  function money(value){return value == null ? '—' : '$' + Number(value || 0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});}
  function setStatus(message, error){var node=qs('[data-performance-status]'); if(node){node.textContent=message||''; node.style.color=error?'#b91c1c':'#64748b';}}
  function kpi(label, value, detail){return '<article class="mg-ads-kpi"><span>'+esc(label)+'</span><strong>'+esc(value)+'</strong><small>'+esc(detail||'')+'</small></article>';}
  function renderSummary(summary){
    var target = qs('[data-performance-kpis]');
    if (!target) return;
    target.innerHTML = [
      kpi('Impressions', num(summary.impressions), 'Total sponsored views'),
      kpi('Clicks', num(summary.clicks), 'CTR '+pct(summary.ctr)),
      kpi('Claimed value', money(summary.claimed_value), num(summary.attributed_claimed_items)+' attributed claims'),
      kpi('Redeemed value', money(summary.redeemed_value), num(summary.attributed_redeemed_items)+' redemptions'),
      kpi('Future demand', money(summary.unredeemed_future_demand), 'Claimed but not redeemed'),
      kpi('PSR impact', money(summary.pre_sale_revenue_impact), num(summary.attributed_wallet_items)+' attributed wallet items'),
      kpi('Cost / claim', money(summary.cost_per_claim), 'Budget ÷ attributed claims'),
      kpi('Cost / redemption', money(summary.cost_per_redemption), 'Budget ÷ attributed redemptions')
    ].join('');
  }
  function renderFunnel(funnel){
    var target = qs('[data-performance-funnel]');
    if (!target) return;
    target.innerHTML = (funnel||[]).map(function(step){
      var width = Math.max(3, Math.min(100, Number(step.rate || 0)));
      return '<div class="mg-ads-funnel-step"><div><strong>'+esc(step.label)+'</strong><span>'+num(step.value)+' · '+pct(step.rate)+'</span></div><em style="width:'+width+'%"></em></div>';
    }).join('') || '<div class="mg-ads-empty">No funnel data yet.</div>';
  }
  function renderPlacements(rows){
    var target = qs('[data-performance-placements]');
    if (!target) return;
    target.innerHTML = (rows||[]).length ? '<table class="mg-ads-table"><thead><tr><th>Placement</th><th>Impressions</th><th>Clicks</th><th>CTR</th><th>Claimed value</th><th>Redeemed value</th><th>Future demand</th><th>PSR impact</th></tr></thead><tbody>' + rows.map(function(row){
      return '<tr><td><strong>'+esc(row.placement_key).replace(/_/g,' ')+'</strong><small>'+esc(row.surface||'')+'</small></td><td>'+num(row.impressions)+'</td><td>'+num(row.clicks)+'</td><td>'+pct(row.ctr)+'</td><td>'+money(row.claimed_value)+'</td><td>'+money(row.redeemed_value)+'</td><td>'+money(row.unredeemed_future_demand)+'</td><td>'+money(row.pre_sale_revenue_impact)+'</td></tr>';
    }).join('') + '</tbody></table>' : '<div class="mg-ads-empty">No placement performance yet.</div>';
  }
  function renderCampaigns(rows){
    var target = qs('[data-performance-campaigns]');
    if (!target) return;
    target.innerHTML = (rows||[]).length ? rows.map(function(row){
      return '<article class="mg-ads-row"><div class="mg-ads-row-head"><div><h3>'+esc(row.title)+'</h3><p>'+esc(row.headline||row.objective||'')+'</p>'+(scope==='admin'?'<p><strong>Merchant:</strong> '+esc(row.merchant_name||'')+'</p>':'')+'</div><span class="mg-ads-pill is-'+esc(row.status)+'">'+esc(row.status).replace(/_/g,' ')+'</span></div><div class="mg-ads-mini-grid"><span><strong>'+num(row.impressions)+'</strong> impressions</span><span><strong>'+num(row.clicks)+'</strong> clicks</span><span><strong>'+money(row.claimed_value)+'</strong> claimed value</span><span><strong>'+money(row.redeemed_value)+'</strong> redeemed value</span><span><strong>'+money(row.unredeemed_future_demand)+'</strong> future demand</span><span><strong>'+money(row.pre_sale_revenue_impact)+'</strong> PSR impact</span><span><strong>'+num(row.attributed_wallet_items)+'</strong> wallet items</span><span><strong>'+money(row.cost_per_redemption)+'</strong> cost / redemption</span></div></article>';
    }).join('') : '<div class="mg-ads-empty">No campaign performance yet.</div>';
  }
  function renderValue(summary, notes, attribution){
    var target = qs('[data-performance-value]');
    if (!target) return;
    var source = attribution && attribution.source_breakdown ? attribution.source_breakdown : {};
    var direct = source.direct || {};
    var assisted = source.campaign_assisted || {};
    target.innerHTML = '<div class="mg-ads-mini-grid"><span><strong>'+money(summary.claimed_value)+'</strong> claimed value</span><span><strong>'+money(summary.redeemed_value)+'</strong> redeemed value</span><span><strong>'+money(summary.unredeemed_future_demand)+'</strong> future demand</span><span><strong>'+money(summary.pre_sale_revenue_impact)+'</strong> PSR impact</span><span><strong>'+num(summary.direct_wallet_items)+'</strong> direct wallet items</span><span><strong>'+num(summary.campaign_assisted_wallet_items)+'</strong> campaign-assisted items</span><span><strong>'+money(direct.pre_sale_revenue_impact)+'</strong> direct PSR</span><span><strong>'+money(assisted.pre_sale_revenue_impact)+'</strong> assisted PSR</span></div><p class="mg-ads-muted">'+esc(notes||'Read-only value attribution is active when wallet and campaign links are available.')+'</p>';
  }
  async function load(){
    setStatus('Loading Campaign Ads performance...');
    var res = await fetch(endpoint,{credentials:'same-origin',headers:{Accept:'application/json'}});
    var out = await res.json().catch(function(){return {ok:false,message:'Invalid server response'};});
    if (!out.ok) throw new Error(out.message||'Unable to load performance.');
    var data = out.data || {};
    if (!data.schema_ready) { setStatus('Campaign Ads Manager migration is required.', true); return; }
    var performance = data.performance || {};
    var summary = performance.summary || {};
    renderSummary(summary);
    renderFunnel(performance.funnel||[]);
    renderPlacements(performance.placements||[]);
    renderCampaigns(performance.campaigns||[]);
    renderValue(summary, performance.notes||'', performance.attribution||{});
    setStatus('Performance loaded.');
  }
  var refresh = qs('[data-performance-refresh]');
  if (refresh) refresh.addEventListener('click', function(){load().catch(function(error){setStatus(error.message,true);});});
  load().catch(function(error){setStatus(error.message,true);});
})(window, document);
