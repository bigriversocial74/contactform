<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/includes/admin-header.php';
?>
<main class="admin-shell" style="max-width:1280px;margin:0 auto;padding:24px">
 <header style="display:flex;justify-content:space-between;gap:20px;align-items:end;flex-wrap:wrap"><div><p style="margin:0;color:#667085;font-weight:700">PRODUCT BUNDLES · PHASE 12</p><h1 style="margin:.35rem 0">Production hardening</h1><p style="margin:0;color:#667085">Reversal dispatch, dead letters, retry controls, incidents, and settlement safeguards.</p></div><button id="refresh" class="button">Refresh</button></header>
 <section id="stats" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin:24px 0"></section>
 <section style="display:grid;grid-template-columns:1fr 1fr;gap:20px"><div><h2>Dead letters</h2><div id="dead"></div></div><div><h2>Incidents</h2><div id="incidents"></div></div></section>
</main>
<script>
const api='/api/admin/bundle-production-hardening.php';
const esc=s=>String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
async function load(){const r=await fetch(api);const j=await r.json();if(!r.ok||!j.ok)throw new Error(j.error||'Load failed');const s=j.data.summary;document.querySelector('#stats').innerHTML=Object.entries(s).map(([k,v])=>`<article style="border:1px solid #e4e7ec;border-radius:14px;padding:16px;background:#fff"><small>${esc(k.replaceAll('_',' '))}</small><strong style="display:block;font-size:24px">${esc(v)}</strong></article>`).join('');document.querySelector('#dead').innerHTML=j.data.dead_letters.map(x=>`<article style="border:1px solid #e4e7ec;border-radius:12px;padding:14px;margin-bottom:10px"><strong>${esc(x.source_type)} · ${esc(x.failure_code)}</strong><p>${esc(x.failure_message)}</p><small>${esc(x.status)} · retries ${esc(x.retry_count)}</small></article>`).join('')||'<p>No dead letters.</p>';document.querySelector('#incidents').innerHTML=j.data.incidents.map(x=>`<article style="border:1px solid #e4e7ec;border-radius:12px;padding:14px;margin-bottom:10px"><strong>${esc(x.severity)} · ${esc(x.incident_type)}</strong><p>${esc(x.summary)}</p><small>${esc(x.status)}</small></article>`).join('')||'<p>No incidents.</p>'}
document.querySelector('#refresh').addEventListener('click',()=>load().catch(e=>alert(e.message)));load().catch(e=>alert(e.message));
</script>
<?php require_once dirname(__DIR__).'/includes/admin-footer.php'; ?>
