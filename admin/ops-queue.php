<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/api/bootstrap.php';

$user = mg_require_api_user();
if (
    !mg_api_user_has_permission($user, 'ops.alerts.assign')
    && !mg_api_user_has_permission($user, 'ops.alerts.resolve')
) {
    mg_fail('Permission denied.', 403);
}
$csrfToken = mg_csrf_token();
$cspNonce = bin2hex(random_bytes(16));
header_remove('Content-Security-Policy');
$legacyStandaloneCspPolicy = "Content-Security-Policy: default-src 'none'; style-src 'nonce-{$cspNonce}'; script-src 'nonce-{$cspNonce}'; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'";
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'nonce-{$cspNonce}'; script-src 'self' 'nonce-{$cspNonce}'; connect-src 'self'; img-src 'self' data: https:; font-src 'self' data:; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");

$page_title = 'Operations Queue | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-ops-queue-page';
$page_styles = ['/assets/css/admin-shell.css'];
$adminActive = 'ops-queue';

require dirname(__DIR__) . '/includes/header.php';
?>
<style nonce="<?php echo htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8'); ?>">
.mg-ops-queue-card strong{display:inline-block;margin-bottom:4px}.mg-ops-queue-card small{color:#64748b}.mg-ops-queue-card .mg-muted{margin-left:4px}
</style>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <section class="mg-ops-queue-shell" data-ops-queue>
      <header class="mg-ops-queue-head">
        <div>
          <a class="mg-system-health-back" href="/account-admin.php">← Admin dashboard</a>
          <span class="mg-eyebrow">Operational alerts</span>
          <h1>Operations queue</h1>
          <p>Review provider, delivery, payout, and operational alerts using your authenticated ops session.</p>
        </div>
      </header>

      <section class="mg-ops-queue-panel">
        <div class="mg-ops-queue-bar">
          <select id="status" aria-label="Alert status"><option value="open">Open</option><option value="assigned">Assigned</option><option value="resolved">Resolved</option><option value="">All statuses</option></select>
          <select id="severity" aria-label="Alert severity"><option value="">All severities</option><option value="critical">Critical</option><option value="warning">Warning</option><option value="info">Info</option></select>
          <input id="source" placeholder="Source type" aria-label="Source type">
          <button class="mg-btn mg-btn-soft" id="refresh" type="button">Refresh</button>
        </div>
        <div class="mg-ops-queue-grid">
          <section><h2>Alerts</h2><div id="alerts" class="mg-ops-queue-list"><p class="mg-muted">Loading alerts…</p></div></section>
          <section><h2>Detail</h2><div id="detail" class="mg-ops-queue-detail mg-muted">Select an alert.</div></section>
        </div>
      </section>
    </section>
  </div>
</section>
<script nonce="<?php echo htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8'); ?>">
document.addEventListener('DOMContentLoaded',function(){
const api='/api/ops/queue.php';let selected=null;const csrfToken=<?php echo json_encode($csrfToken, JSON_UNESCAPED_SLASHES); ?>;
function qs(o){return new URLSearchParams(Object.entries(o).filter(([,v])=>v!==''&&v!=null)).toString()}
async function call(action,data={}){const res=await fetch(api,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken},body:JSON.stringify({action,csrf_token:csrfToken,...data})});const out=await res.json();if(!out.ok)throw new Error(out.error||out.message||'Ops request failed');return out.data}
function safe(v){return String(v==null?'':v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}
function badge(v){return '<span class="mg-ops-pill is-'+String(v).replace(/[^a-z]/g,'')+'">'+safe(v)+'</span>'}
function renderList(items){const box=document.getElementById('alerts');box.innerHTML=items.map(a=>`<button class="mg-ops-queue-card ${selected===a.alert_id?'is-active':''}" data-id="${safe(a.alert_id)}"><strong>${safe(a.title)}</strong><br>${badge(a.status)}${badge(a.severity)}<span class="mg-muted">${safe(a.source_type)}</span><br><small>${safe(a.alert_key)}</small></button>`).join('')||'<div class="mg-muted">No alerts found.</div>';box.querySelectorAll('[data-id]').forEach(b=>b.onclick=()=>loadDetail(b.dataset.id));}
async function loadList(){const data=await call('list',{status:document.getElementById('status').value,severity:document.getElementById('severity').value,source_type:document.getElementById('source').value.trim(),limit:50});renderList(data.items||[])}
async function loadDetail(id){selected=id;const data=await call('detail',{alert_id:id});const a=data.alert;document.getElementById('detail').innerHTML=`<strong>${safe(a.title)}</strong><p>${safe(a.body)}</p><p>${badge(a.status)}${badge(a.severity)}${badge(a.source_type)}</p><p><small>${safe(a.alert_key)}</small></p><div class="mg-ops-actions"><input id="assignee" placeholder="assign user id"><button class="mg-btn mg-btn-soft" id="assign">Assign</button><input id="reason" placeholder="resolution reason"><button class="mg-btn mg-btn-primary" id="resolve">Resolve</button></div><h3>Events</h3><div class="mg-ops-events">${safe(JSON.stringify(data.events||[],null,2))}</div>`;document.getElementById('assign').onclick=async()=>{await call('assign',{alert_id:id,assigned_to_user_id:document.getElementById('assignee').value,request_key:'ui:assign:'+id+':'+document.getElementById('assignee').value});await loadDetail(id);await loadList()};document.getElementById('resolve').onclick=async()=>{await call('resolve',{alert_id:id,resolution_reason:document.getElementById('reason').value,request_key:'ui:resolve:'+id+':'+document.getElementById('reason').value});await loadDetail(id);await loadList()};selected=id;await loadList()}
document.getElementById('refresh').onclick=()=>loadList().catch(e=>alert(e.message));document.getElementById('status').onchange=()=>loadList().catch(e=>alert(e.message));document.getElementById('severity').onchange=()=>loadList().catch(e=>alert(e.message));document.getElementById('source').oninput=()=>{clearTimeout(window.mgOpsQueueTimer);window.mgOpsQueueTimer=setTimeout(()=>loadList().catch(e=>alert(e.message)),300)};loadList().catch(e=>alert(e.message));
});
</script>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
