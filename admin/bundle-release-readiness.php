<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/includes/admin-header.php';
?>
<main class="admin-shell" style="max-width:1280px;margin:0 auto;padding:24px">
<header><p style="margin:0;color:#667085;font-weight:700">PRODUCT BUNDLES · PHASE 13</p><h1 style="margin:.35rem 0">Release readiness & staged activation</h1><p style="color:#667085">Health scoring, emergency stop, controlled rollout, immutable activation history, and post-launch monitoring.</p></header>
<div style="display:flex;gap:12px;margin:20px 0"><select id="env"><option value="test">Test</option><option value="live">Live</option></select><button id="refresh">Refresh</button></div>
<section id="health" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px"></section>
<section style="margin-top:24px;border:1px solid #e4e7ec;border-radius:16px;padding:18px"><h2>Release control</h2><pre id="control" style="white-space:pre-wrap"></pre></section>
<section style="margin-top:24px"><h2>Activation history</h2><div id="events"></div></section>
</main>
<script>
const api='/api/admin/bundle-release-readiness.php',esc=s=>String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));async function load(){const env=document.querySelector('#env').value,r=await fetch(api+'?environment='+env),j=await r.json();if(!r.ok||!j.ok)throw new Error(j.error||'Load failed');const h=j.data.health;document.querySelector('#health').innerHTML=[['status',h.status],['score',h.score],...Object.entries(h.checks)].map(([k,v])=>`<article style="border:1px solid #e4e7ec;border-radius:14px;padding:16px"><small>${esc(k.replaceAll('_',' '))}</small><strong style="display:block;font-size:24px">${esc(v)}</strong></article>`).join('');document.querySelector('#control').textContent=JSON.stringify(j.data.control,null,2);document.querySelector('#events').innerHTML=j.data.events.map(e=>`<article style="border-bottom:1px solid #eee;padding:12px 0"><strong>${esc(e.event_type)}</strong><p>${esc(e.reason)}</p><small>${esc(e.created_at)}</small></article>`).join('')||'<p>No release events.</p>'}document.querySelector('#refresh').onclick=()=>load().catch(e=>alert(e.message));document.querySelector('#env').onchange=()=>load().catch(e=>alert(e.message));load().catch(e=>alert(e.message));
</script>
<?php require_once dirname(__DIR__).'/includes/admin-footer.php'; ?>
