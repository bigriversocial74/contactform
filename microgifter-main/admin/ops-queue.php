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
header("Content-Security-Policy: default-src 'none'; style-src 'nonce-{$cspNonce}'; script-src 'nonce-{$cspNonce}'; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Microgifter Ops Queue</title>
<style nonce="<?php echo htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8'); ?>">
body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f8fafc;color:#0f172a;margin:0}.wrap{max-width:1180px;margin:0 auto;padding:28px}.panel{background:#fff;border:1px solid #e2e8f0;border-radius:18px;box-shadow:0 18px 50px rgba(15,23,42,.08);padding:20px}h1{margin:0 0 8px;font-size:30px}.muted{color:#64748b}.bar{display:flex;gap:12px;flex-wrap:wrap;margin:18px 0}.bar input,.bar select,.bar button,.card button{border:1px solid #cbd5e1;border-radius:12px;padding:10px 12px;background:#fff}.bar button,.card button{background:#2563eb;color:#fff;border-color:#2563eb;cursor:pointer}.grid{display:grid;grid-template-columns:360px 1fr;gap:18px}.list{display:grid;gap:10px}.card{border:1px solid #e2e8f0;border-radius:14px;padding:14px;background:#fff}.card.active{outline:2px solid #2563eb}.pill{display:inline-flex;border-radius:999px;padding:3px 9px;background:#e2e8f0;font-size:12px;margin-right:5px}.critical{background:#fee2e2;color:#991b1b}.warning{background:#fef3c7;color:#92400e}.info{background:#dbeafe;color:#1d4ed8}.events{font-family:ui-monospace,Consolas,monospace;font-size:12px;white-space:pre-wrap;background:#0f172a;color:#e2e8f0;border-radius:14px;padding:14px;overflow:auto;max-height:320px}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}@media(max-width:850px){.grid{grid-template-columns:1fr}.wrap{padding:16px}}
</style>
</head>
<body>
<main class="wrap">
<section class="panel">
<h1>Ops Queue</h1>
<p class="muted">Review provider, delivery, payout, and operational alerts using your authenticated ops session.</p>
<div class="bar">
<select id="status"><option value="open">open</option><option value="assigned">assigned</option><option value="resolved">resolved</option><option value="">all statuses</option></select>
<select id="severity"><option value="">all severities</option><option value="critical">critical</option><option value="warning">warning</option><option value="info">info</option></select>
<input id="source" placeholder="source_type">
<button id="refresh">Refresh</button>
</div>
<div class="grid"><div><h2>Alerts</h2><div id="alerts" class="list"></div></div><div><h2>Detail</h2><div id="detail" class="card muted">Select an alert.</div></div></div>
</section>
</main>
<script nonce="<?php echo htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8'); ?>">
const api='/api/ops/queue.php';let selected=null;const csrfToken=<?php echo json_encode($csrfToken, JSON_UNESCAPED_SLASHES); ?>;
function qs(o){return new URLSearchParams(Object.entries(o).filter(([,v])=>v!==''&&v!=null)).toString()}
async function call(action,data={}){const res=await fetch(api,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken},body:JSON.stringify({action,csrf_token:csrfToken,...data})});const out=await res.json();if(!out.ok)throw new Error(out.error||out.message||'Ops request failed');return out.data}
function badge(v){return '<span class="pill '+String(v).replace(/[^a-z]/g,'')+'">'+v+'</span>'}
function renderList(items){const box=document.getElementById('alerts');box.innerHTML=items.map(a=>`<button class="card ${selected===a.alert_id?'active':''}" data-id="${a.alert_id}"><strong>${a.title}</strong><br>${badge(a.status)}${badge(a.severity)}<span class="muted">${a.source_type}</span><br><small>${a.alert_key}</small></button>`).join('')||'<div class="muted">No alerts found.</div>';box.querySelectorAll('[data-id]').forEach(b=>b.onclick=()=>loadDetail(b.dataset.id));}
async function loadList(){const data=await call('list',{status:document.getElementById('status').value,severity:document.getElementById('severity').value,source_type:document.getElementById('source').value.trim(),limit:50});renderList(data.items||[])}
async function loadDetail(id){selected=id;const data=await call('detail',{alert_id:id});const a=data.alert;document.getElementById('detail').innerHTML=`<strong>${a.title}</strong><p>${a.body}</p><p>${badge(a.status)}${badge(a.severity)}${badge(a.source_type)}</p><p><small>${a.alert_key}</small></p><div class="actions"><input id="assignee" placeholder="assign user id"><button id="assign">Assign</button><input id="reason" placeholder="resolution reason"><button id="resolve">Resolve</button></div><h3>Events</h3><div class="events">${JSON.stringify(data.events||[],null,2)}</div>`;document.getElementById('assign').onclick=async()=>{await call('assign',{alert_id:id,assigned_to_user_id:document.getElementById('assignee').value,request_key:'ui:assign:'+id+':'+document.getElementById('assignee').value});await loadDetail(id);await loadList()};document.getElementById('resolve').onclick=async()=>{await call('resolve',{alert_id:id,resolution_reason:document.getElementById('reason').value,request_key:'ui:resolve:'+id+':'+document.getElementById('reason').value});await loadDetail(id);await loadList()};selected=id;await loadList()}
document.getElementById('refresh').onclick=()=>loadList().catch(e=>alert(e.message));
</script>
</body>
</html>
