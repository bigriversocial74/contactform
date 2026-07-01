(function(window, document){
  'use strict';
  var root = document.querySelector('[data-admin-ad-placements]');
  if (!root) return;
  var csrf = root.getAttribute('data-csrf-token') || '';
  var state = {placements: [], campaigns: [], assignments: [], metrics: {}};
  function esc(value){return String(value == null ? '' : value).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
  function qs(sel, scope){return (scope||root).querySelector(sel);}
  function qsa(sel, scope){return Array.prototype.slice.call((scope||root).querySelectorAll(sel));}
  function status(message,error){var node=qs('[data-placement-status]'); if(node){node.textContent=message||''; node.style.color=error?'#b91c1c':'#64748b';}}
  async function api(options){
    var res = await fetch('/api/ads/admin-placement-control.php', Object.assign({credentials:'same-origin'}, options || {}));
    var out = await res.json().catch(function(){return {ok:false,message:'Invalid server response'};});
    if (!out.ok) throw new Error(out.message || 'Request failed');
    return out.data || {};
  }
  function metric(key, type){return Number((state.metrics[key] && state.metrics[key][type]) || 0).toLocaleString();}
  function campaignOptions(selected){
    return '<option value="">Select approved/active campaign</option>' + state.campaigns.map(function(c){return '<option value="'+esc(c.id)+'" '+(selected===c.id?'selected':'')+'>'+esc(c.title)+' · '+esc(c.status)+' · '+esc(c.merchant_name)+'</option>';}).join('');
  }
  function placementCard(p){
    var rows = state.assignments.filter(function(a){return a.placement_key === p.placement_key;});
    var active = Number(p.is_active) === 1;
    var assignments = rows.length ? rows.map(assignmentRow).join('') : '<div class="mg-ads-empty">No campaigns assigned to this placement yet.</div>';
    return '<article class="mg-ads-placement-card" data-placement-key="'+esc(p.placement_key)+'">'
      + '<div class="mg-ads-row-head"><div><span class="mg-ads-eyebrow">'+esc(p.surface||'ads')+'</span><h2>'+esc(p.placement_name||p.placement_key)+'</h2><p class="mg-ads-muted">'+esc(p.description||'')+'</p></div><span class="mg-ads-pill '+(active?'is-active':'is-paused')+'">'+(active?'Active':'Inactive')+'</span></div>'
      + '<div class="mg-ads-placement-metrics"><span><strong>'+metric(p.placement_key,'impression')+'</strong> impressions</span><span><strong>'+metric(p.placement_key,'click')+'</strong> clicks</span><span><strong>'+metric(p.placement_key,'wallet_save')+'</strong> saves</span><span><strong>'+metric(p.placement_key,'redeem')+'</strong> redemptions</span></div>'
      + '<form class="mg-ads-placement-settings" data-placement-settings><label>Enabled <input type="checkbox" name="is_active" '+(active?'checked':'')+'></label><label>Max ads <input type="number" min="1" max="20" name="max_ads" value="'+esc(p.max_ads||1)+'"></label><button class="mg-btn mg-btn-soft" type="submit">Save settings</button></form>'
      + '<form class="mg-ads-placement-assign" data-placement-assign><select name="campaign_id">'+campaignOptions('')+'</select><input type="number" min="1" max="999" name="priority" value="100" aria-label="Priority"><button class="mg-btn mg-btn-primary" type="submit">Assign campaign</button></form>'
      + '<div class="mg-ads-assignment-list">'+assignments+'</div>'
      + '</article>';
  }
  function assignmentRow(a){
    return '<div class="mg-ads-placement-assignment" data-assignment-id="'+esc(a.assignment_id)+'">'
      + '<div><strong>'+esc(a.campaign_title)+'</strong><span>'+esc(a.creative_headline || a.objective || '')+' · '+esc(a.merchant_name || '')+'</span></div>'
      + '<label>Priority <input type="number" min="1" max="999" name="priority" value="'+esc(a.priority||100)+'"></label>'
      + '<label>Status <select name="status"><option value="active" '+(a.status==='active'?'selected':'')+'>Active</option><option value="paused" '+(a.status==='paused'?'selected':'')+'>Paused</option></select></label>'
      + '<button class="mg-btn mg-btn-soft" type="button" data-save-assignment>Save</button><button class="mg-btn mg-btn-ghost" type="button" data-archive-assignment>Remove</button>'
      + '</div>';
  }
  function render(){
    var list = qs('[data-placement-list]');
    list.innerHTML = state.placements.length ? state.placements.map(placementCard).join('') : '<div class="mg-ads-empty">No placements available. Run the Campaign Ads Manager SQL migration.</div>';
    bindControls(list);
    var summary = qs('[data-placement-summary]');
    if (summary) summary.textContent = state.assignments.length + ' campaign placement assignments across ' + state.placements.length + ' surfaces.';
  }
  function bindControls(scope){
    qsa('[data-placement-settings]', scope).forEach(function(form){
      form.addEventListener('submit', function(e){
        e.preventDefault();
        var card = form.closest('[data-placement-key]');
        save({action:'update_placement', placement_key:card.getAttribute('data-placement-key'), is_active:form.elements.is_active.checked ? 1 : 0, max_ads:form.elements.max_ads.value});
      });
    });
    qsa('[data-placement-assign]', scope).forEach(function(form){
      form.addEventListener('submit', function(e){
        e.preventDefault();
        var card = form.closest('[data-placement-key]');
        if (!form.elements.campaign_id.value) { status('Select a campaign first.', true); return; }
        save({action:'assign_campaign', placement_key:card.getAttribute('data-placement-key'), campaign_id:form.elements.campaign_id.value, priority:form.elements.priority.value});
      });
    });
    qsa('[data-save-assignment]', scope).forEach(function(btn){
      btn.addEventListener('click', function(){
        var row = btn.closest('[data-assignment-id]');
        save({action:'update_assignment', assignment_id:row.getAttribute('data-assignment-id'), priority:qs('[name="priority"]', row).value, status:qs('[name="status"]', row).value});
      });
    });
    qsa('[data-archive-assignment]', scope).forEach(function(btn){
      btn.addEventListener('click', function(){
        var row = btn.closest('[data-assignment-id]');
        save({action:'archive_assignment', assignment_id:row.getAttribute('data-assignment-id')});
      });
    });
  }
  async function save(payload){
    status('Saving placement controls...');
    payload.csrf_token = csrf;
    var data = await api({method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify(payload)});
    state = data;
    status('Placement controls saved.');
    render();
  }
  async function load(){
    status('Loading placement controls...');
    var data = await api({method:'GET'});
    if (!data.schema_ready) {
      qs('[data-placement-list]').innerHTML = '<div class="mg-ads-alert">Campaign Ads Manager migration is required before placement controls can load.</div>';
      return;
    }
    state = data;
    status('Placement controls loaded.');
    render();
  }
  var refresh = qs('[data-placement-refresh]');
  if (refresh) refresh.addEventListener('click', function(){load().catch(function(e){status(e.message,true);});});
  load().catch(function(e){status(e.message,true);});
})(window, document);
