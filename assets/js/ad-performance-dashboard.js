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
      kpi('Wallet saves', num(summary.wallet_saves), 'Save rate '+pct(summary.save_rate)),
      kpi('Claims', num(summary.claims), 'Claim rate '+pct(summary.claim_rate)),
      kpi('Redemptions', num(summary.redemptions), 'Conversion '+pct(summary.conversion_rate)),
      kpi('CRM contacts', num(summary.crm_contacts_created), 'Follow-ups '+num(summary.followups_created)),
      kpi('Cost / claim', money(summary.cost_per_claim), 'Budget placeholder'),
      kpi('Cost / redemption', money(summary.cost_per_redemption), 'Budget placeholder')
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
    target.innerHTML = (rows||[]).length ? '<table class="mg-ads-table"><thead><tr><th>Placement</th><th>Impressions</th><th>Clicks</th><th>CTR</th><th>Saves</th><th>Claims</th><th>Redemptions</th><th>Conv.</th></tr></thead><tbody>' + rows.map(function(row){
      return '<tr><td><strong>'+esc(row.placement_key).replace(/_/g,' ')+'</strong><small>'+esc(row.surface||'')+'</small></td><td>'+num(row.impressions)+'</td><td>'+num(row.clicks)+'</td><td>'+pct(row.ctr)+'</td><td>'+num(row.wallet_saves)+'</td><td>'+num(row.claims)+'</td><td>'+num(row.redemptions)+'</td><td>'+pct(row.conversion_rate)+'</td></tr>';
    }).join('') + '</tbody></table>' : '<div class="mg-ads-empty">No placement performance yet.</div>';
  }
  function renderCampaigns(rows){
    var target = qs('[data-performance-campaigns]');
    if (!target) return;
    target.innerHTML = (rows||[]).length ? rows.map(function(row){
      return '<article class="mg-ads-row"><div class="mg-ads-row-head"><div><h3>'+esc(row.title)+'</h3><p>'+esc(row.headline||row.objective||'')+'</p>'+(scope==='admin'?'<p><strong>Merchant:</strong> '+esc(row.merchant_name||'')+'</p>':'')+'</div><span class="mg-ads-pill is-'+esc(row.status)+'">'+esc(row.status).replace(/_/g,' ')+'</span></div><div class="mg-ads-mini-grid"><span><strong>'+num(row.impressions)+'</strong> impressions</span><span><strong>'+num(row.clicks)+'</strong> clicks</span><span><strong>'+pct(row.ctr)+'</strong> CTR</span><span><strong>'+num(row.claims)+'</strong> claims</span><span><strong>'+num(row.wallet_saves)+'</strong> saves</span><span><strong>'+num(row.redemptions)+'</strong> redemptions</span></div></article>';
    }).join('') : '<div class="mg-ads-empty">No campaign performance yet.</div>';
  }
  function renderValue(summary, notes){
    var target = qs('[data-performance-value]');
    if (!target) return;
    target.innerHTML = '<div class="mg-ads-mini-grid"><span><strong>'+money(summary.claimed_value)+'</strong> claimed value</span><span><strong>'+money(summary.redeemed_value)+'</strong> redeemed value</span><span><strong>'+money(summary.unredeemed_future_demand)+'</strong> future demand</span><span><strong>'+money(summary.pre_sale_revenue_impact)+'</strong> PSR impact</span></div><p class="mg-ads-muted">'+esc(notes||'Value attribution will activate when live value sources are wired.')+'</p>';
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
    renderValue(summary, performance.notes||'');
    setStatus('Performance loaded.');
  }
  var refresh = qs('[data-performance-refresh]');
  if (refresh) refresh.addEventListener('click', function(){load().catch(function(error){setStatus(error.message,true);});});
  load().catch(function(error){setStatus(error.message,true);});
})(window, document);
