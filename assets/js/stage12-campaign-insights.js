document.addEventListener('DOMContentLoaded',function(){
  if(!window.Microgifter){return;}
  var anchor=document.querySelector('[data-stage12-campaign-list]');
  if(!anchor){return;}
  var demandPanel=document.createElement('section');
  demandPanel.className='mg-app-panel';
  demandPanel.innerHTML='<div class="mg-app-panel-head"><div><h2>Demand insights</h2><p>Agent adds, wallet claims, completions, and projected local value.</p></div></div><div class="mg-app-panel-body"><div class="mg-product-list" data-campaign-insights-list></div><div class="mg-form-status" data-campaign-insights-status>Loading insights...</div></div>';
  var mediaPanel=document.createElement('section');
  mediaPanel.className='mg-app-panel';
  mediaPanel.innerHTML='<div class="mg-app-panel-head"><div><span class="mg-eyebrow">Media Rewards</span><h2>Watch / Listen performance</h2><p>Track media starts, progress events, milestone rewards, embed handoffs, Inbox issues, claims, and redemptions.</p></div><div class="mg-heading-actions"><select class="mg-input" data-media-performance-days><option value="7">7 days</option><option value="30" selected>30 days</option><option value="90">90 days</option></select><button class="mg-btn mg-btn-soft" type="button" data-media-performance-refresh>Refresh</button></div></div><div class="mg-app-panel-body"><div class="mg-campaign-kpis" data-media-performance-kpis></div><div class="mg-product-list" data-media-performance-list></div><div class="mg-form-status" data-media-performance-status>Loading media reward performance...</div></div>';
  var parent=anchor.closest('.mg-app-panel');
  if(parent&&parent.parentNode){parent.parentNode.insertBefore(demandPanel,parent.nextSibling);parent.parentNode.insertBefore(mediaPanel,demandPanel.nextSibling);}
  var list=demandPanel.querySelector('[data-campaign-insights-list]');
  var status=demandPanel.querySelector('[data-campaign-insights-status]');
  var mediaKpis=mediaPanel.querySelector('[data-media-performance-kpis]');
  var mediaList=mediaPanel.querySelector('[data-media-performance-list]');
  var mediaStatus=mediaPanel.querySelector('[data-media-performance-status]');
  var mediaDays=mediaPanel.querySelector('[data-media-performance-days]');
  function safe(v){return String(v==null?'':v).replace(/[&<>'"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[c];});}
  function money(cents){return 'USD '+(Number(cents||0)/100).toFixed(2);}
  function count(v){return Number(v||0).toLocaleString();}
  function pct(v){return Math.round(Number(v||0)*100)+'%';}
  function pctValue(v){return Math.round(Number(v||0))+'%';}
  function note(message){if(status){status.textContent=message||'';}}
  function mediaNote(message,type){if(mediaStatus){mediaStatus.textContent=message||'';mediaStatus.classList.toggle('is-error',type==='error');}}
  function renderDemand(data){
    var campaigns=data.top_campaigns||[];
    var summary='<div class="mg-product-card"><span><strong>'+Number(data.projected_30d_completions||0)+' projected completions</strong><span>'+safe(money(data.projected_30d_value_cents))+' projected 30-day value · '+Number(data.agent_wallet_adds||0)+' agent adds</span><small>'+Number(data.claimed||0)+' claimed · '+Number(data.completed||0)+' completed · '+Math.round(Number(data.completion_rate||0)*100)+'% completion rate</small></span><span class="mg-card-meta"><em>'+Number(data.active_campaigns||0)+' active</em></span></div>';
    var rows=campaigns.map(function(c){return '<div class="mg-product-card"><span><strong>'+safe(c.title)+'</strong><span>'+safe(c.campaign_type)+' · '+safe(c.status)+'</span><small>'+Number(c.contacts||0)+' contacts · '+Number(c.claimed||0)+' claimed · '+Number(c.completed||0)+' completed · projected '+safe(money(c.projected_value_cents))+'</small></span><span class="mg-card-meta"><em>'+Math.round(Number(c.completion_rate||0)*100)+'%</em></span></div>';}).join('');
    list.innerHTML=summary+rows;
  }
  function renderMedia(media){
    media=media||{};
    var totals=media.totals||{};
    var rows=media.campaigns||[];
    if(mediaKpis){
      mediaKpis.innerHTML=[
        ['Media campaigns',count(totals.campaigns),'Active '+count(totals.active_campaigns)],
        ['Starts',count(totals.starts),'Progress '+count(totals.progress_events)],
        ['Rewards issued',count(totals.wallet_items),'Milestone events '+count(totals.issued_events)],
        ['Claims',count(totals.claimed),'Redeemed '+count(totals.redeemed)],
        ['Embed handoffs',count(totals.embed_opened),'Loaded '+count(totals.embed_loaded)]
      ].map(function(card){return '<article><span>'+safe(card[0])+'</span><strong>'+safe(card[1])+'</strong><small>'+safe(card[2])+'</small></article>';}).join('');
    }
    if(!rows.length){
      mediaList.innerHTML='<div class="mg-empty-state"><strong>No Watch/Listen media campaigns yet.</strong><p>Create a Watch Video Reward or Listen Music Reward campaign to see media attribution here.</p></div>';
      mediaNote(media.embed_analytics_ready===false?'Media performance loaded. Embed analytics SQL is not ready, so embed loads/opens are hidden.':'Media performance loaded.');
      return;
    }
    mediaList.innerHTML=rows.map(function(row){
      var progress='Max progress '+pctValue(row.max_progress_percent)+' · '+count(row.issued_milestones)+'/'+count(row.configured_milestones)+' milestones issued';
      var conversion=count(row.wallet_items)+' issued · '+count(row.claimed)+' claimed · '+count(row.redeemed)+' redeemed · '+pct(row.claim_rate)+' claim rate';
      var embed='Embed '+count(row.embed_loaded)+' loaded / '+count(row.embed_opened)+' opened';
      return '<div class="mg-product-card mg-campaign-card"><span><strong>'+safe(row.title)+'</strong><span>'+safe(row.campaign_type_label)+' · '+safe(row.provider_label)+' · '+safe(row.status)+'</span><small>'+count(row.contacts)+' contacts · '+count(row.starts)+' starts · '+count(row.progress_events)+' progress events</small><small>'+safe(progress)+'</small><small>'+safe(conversion)+'</small><small>'+safe(embed)+'</small></span><span class="mg-card-meta"><em>'+safe(row.track_label||row.campaign_type_label)+'</em><a class="mg-btn mg-btn-ghost" href="'+safe(row.media_page_url)+'" target="_blank" rel="noopener">Open page</a><a class="mg-btn mg-btn-soft" href="'+safe(row.embed_qa_url)+'">Embed QA</a></span></div>';
    }).join('');
    var best=totals.best_campaign;
    mediaNote(best?'Top media campaign: '+best.title+' with '+count(best.wallet_items)+' issued rewards.':(media.embed_analytics_ready===false?'Media performance loaded. Embed analytics SQL is not ready, so embed loads/opens are hidden.':'Media performance refreshed.'));
  }
  async function load(){
    var days=mediaDays?mediaDays.value:'30';
    var r=await Microgifter.get('/api/merchant/campaign-insights.php?days='+encodeURIComponent(days)+'&multiplier=1.5');
    var data=(r.data||r).insights;
    if(!data){note('Insights unavailable until campaign activity exists.');mediaNote('Media performance unavailable until campaign activity exists.','error');return;}
    renderDemand(data);renderMedia(data.media_performance||{});note('Insights refreshed.');
  }
  if(mediaDays){mediaDays.addEventListener('change',function(){load().catch(function(error){mediaNote(error.message||'Unable to load media performance.','error');});});}
  var refresh=mediaPanel.querySelector('[data-media-performance-refresh]');
  if(refresh){refresh.addEventListener('click',function(){load().catch(function(error){mediaNote(error.message||'Unable to load media performance.','error');});});}
  load().catch(function(error){note(error.message||'Unable to load campaign insights.');mediaNote(error.message||'Unable to load media performance.','error');});
});
