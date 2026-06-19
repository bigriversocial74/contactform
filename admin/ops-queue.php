<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';

$user = mg_require_auth();
$canManageOps = mg_has_role('super_admin')
    || mg_has_permission('ops.alerts.assign')
    || mg_has_permission('ops.alerts.resolve');
if (!$canManageOps) {
    http_response_code(403);
}

$page_title = 'Operations Queue | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-ops-queue-page';
$page_styles = ['/assets/css/admin-shell.css'];
$adminActive = 'ops-queue';

require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <section class="mg-ops-queue-shell" data-ops-queue>
      <header class="mg-ops-queue-head">
        <div>
          <a class="mg-system-health-back" href="/account-admin.php">← Admin dashboard</a>
          <span class="mg-eyebrow">Operational alerts</span>
          <h1>Operations queue</h1>
          <p>Review provider, delivery, payout, and operational alerts using your authenticated operations session.</p>
        </div>
      </header>

      <?php if (!$canManageOps): ?>
        <section class="mg-app-panel mg-ops-queue-panel">
          <h2>Operations queue access is not active.</h2>
          <p>This page requires alert assignment or resolution permission.</p>
          <a class="mg-btn mg-btn-soft" href="/account-admin.php">Back to admin</a>
        </section>
      <?php else: ?>
        <section class="mg-ops-queue-panel">
          <div class="mg-ops-queue-bar">
            <select data-ops-status aria-label="Alert status"><option value="open">Open</option><option value="assigned">Assigned</option><option value="resolved">Resolved</option><option value="">All statuses</option></select>
            <select data-ops-severity aria-label="Alert severity"><option value="">All severities</option><option value="critical">Critical</option><option value="warning">Warning</option><option value="info">Info</option></select>
            <input data-ops-source placeholder="Source type" aria-label="Source type">
            <button class="mg-btn mg-btn-soft" type="button" data-ops-refresh>Refresh</button>
          </div>
          <div class="mg-ops-queue-grid">
            <section><h2>Alerts</h2><div class="mg-ops-queue-list" data-ops-list><p class="mg-muted">Loading alerts…</p></div></section>
            <section><h2>Detail</h2><div class="mg-ops-queue-detail mg-muted" data-ops-detail>Select an alert.</div></section>
          </div>
        </section>
      <?php endif; ?>
    </section>
  </div>
</section>
<?php if ($canManageOps): ?>
<script>
document.addEventListener('DOMContentLoaded',function(){
'use strict';
var app=document.querySelector('[data-ops-queue]');if(!app||!window.Microgifter)return;
var selected=null,list=app.querySelector('[data-ops-list]'),detail=app.querySelector('[data-ops-detail]'),status=app.querySelector('[data-ops-status]'),severity=app.querySelector('[data-ops-severity]'),source=app.querySelector('[data-ops-source]'),refresh=app.querySelector('[data-ops-refresh]');
function esc(value){return String(value==null?'':value).replace(/[&<>"']/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];});}
function params(values){var output=new URLSearchParams();Object.keys(values).forEach(function(key){var value=values[key];if(value!==''&&value!=null)output.set(key,value);});return output.toString();}
async function call(action,data){data=data||{};if(action==='list'||action==='detail'){var response=await Microgifter.get('/api/ops/queue.php?'+params(Object.assign({action:action},data)));return response.data||response;}var write=await Microgifter.post('/api/ops/queue.php',Object.assign({action:action},data));return write.data||write;}
function pill(value){var safe=String(value||'').toLowerCase().replace(/[^a-z]/g,'');return '<span class="mg-ops-pill is-'+esc(safe)+'">'+esc(value)+'</span>';}
function render(items){list.innerHTML=items.length?items.map(function(item){return '<button class="mg-ops-queue-card'+(selected===item.alert_id?' is-active':'')+'" type="button" data-ops-alert="'+esc(item.alert_id)+'"><strong>'+esc(item.title)+'</strong><br>'+pill(item.status)+pill(item.severity)+'<span class="mg-muted">'+esc(item.source_type)+'</span><br><small>'+esc(item.alert_key)+'</small></button>';}).join(''):'<div class="mg-muted">No alerts found.</div>';}
async function loadList(){refresh.disabled=true;try{var data=await call('list',{status:status.value,severity:severity.value,source_type:source.value.trim(),limit:50});render(Array.isArray(data.items)?data.items:[]);}catch(error){list.innerHTML='<div class="mg-muted">'+esc(error.message||'Unable to load alerts.')+'</div>';}finally{refresh.disabled=false;}}
async function loadDetail(id){selected=id;detail.innerHTML='<div class="mg-muted">Loading alert detail…</div>';try{var data=await call('detail',{alert_id:id}),item=data.alert||{};detail.innerHTML='<strong>'+esc(item.title)+'</strong><p>'+esc(item.body)+'</p><p>'+pill(item.status)+pill(item.severity)+pill(item.source_type)+'</p><p><small>'+esc(item.alert_key)+'</small></p><div class="mg-ops-actions"><input type="number" min="1" step="1" data-ops-assignee placeholder="Assign user ID"><button class="mg-btn mg-btn-soft" type="button" data-ops-assign>Assign</button><input type="text" data-ops-reason placeholder="Resolution reason"><button class="mg-btn mg-btn-primary" type="button" data-ops-resolve>Resolve</button></div><h3>Events</h3><div class="mg-ops-events">'+esc(JSON.stringify(data.events||[],null,2))+'</div>';await loadList();}catch(error){detail.innerHTML='<div class="mg-muted">'+esc(error.message||'Unable to load alert detail.')+'</div>';}}
list.addEventListener('click',function(event){var card=event.target.closest('[data-ops-alert]');if(card)loadDetail(card.dataset.opsAlert);});
detail.addEventListener('click',async function(event){if(!selected)return;try{if(event.target.closest('[data-ops-assign]')){var assignee=detail.querySelector('[data-ops-assignee]').value;await call('assign',{alert_id:selected,assigned_to_user_id:assignee,request_key:'ui:assign:'+selected+':'+assignee});await loadDetail(selected);}if(event.target.closest('[data-ops-resolve]')){var reason=detail.querySelector('[data-ops-reason]').value.trim();await call('resolve',{alert_id:selected,resolution_reason:reason,request_key:'ui:resolve:'+selected+':'+reason});await loadDetail(selected);}}catch(error){Microgifter.toast(error.message||'Unable to update the alert.','error');}});
refresh.addEventListener('click',loadList);status.addEventListener('change',loadList);severity.addEventListener('change',loadList);source.addEventListener('input',function(){clearTimeout(source._timer);source._timer=setTimeout(loadList,300);});loadList();
});
</script>
<?php endif; ?>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
