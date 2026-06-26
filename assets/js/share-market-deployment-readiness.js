window.Microgifter=window.Microgifter||{};
(function(window,document){
'use strict';
var MG=window.Microgifter,root,readiness=null;
function q(s,r){return(r||document).querySelector(s)}
function esc(v){return String(v==null?'':v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;')}
function label(v){return String(v||'').replace(/_/g,' ').replace(/\b\w/g,function(c){return c.toUpperCase()})}
function status(msg){var n=q('[data-share-deploy-status]',root);if(n)n.textContent=msg||''}
function card(k,v){return'<article class="sm-packet-card"><span>'+esc(k)+'</span><strong>'+esc(v==null||v===''?'—':v)+'</strong></article>'}
function row(c){return'<div class="sm-packet-row '+(c.passed?'pass':'fail')+'"><span>'+esc(c.label||c.key)+'<br><small>'+esc(c.message||'')+'</small></span><strong>'+esc(c.passed?'Pass':label(c.type||'Missing'))+'</strong></div>'}
function render(data){readiness=data||{};var s=readiness.summary||{};q('[data-share-deploy-summary]',root).innerHTML=[card('Deployment ready',readiness.ready?'Yes':'No'),card('Readiness score',(readiness.score||0)+'%'),card('Total checks',s.total_checks),card('Passed checks',s.passed_checks),card('Missing checks',s.missing_checks),card('Table checks',s.table_checks),card('Permission checks',s.permission_checks),card('File checks',s.file_checks)].join('');q('[data-share-deploy-missing]',root).innerHTML=(readiness.missing||[]).map(row).join('')||'<div class="sm-packet-row pass"><span>No missing install items.</span><strong>Ready</strong></div>';q('[data-share-deploy-checks]',root).innerHTML=(readiness.checks||[]).map(row).join('');q('[data-share-deploy-json]',root).textContent=JSON.stringify(readiness,null,2);status('Deployment readiness loaded.')}
async function load(){status('Loading deployment readiness…');try{var res=await MG.get('/api/admin/share-market/deployment-readiness.php');render((res.data&&res.data.readiness)||res.readiness||{})}catch(e){status(e.message||'Unable to load deployment readiness.')}}
function download(){if(!readiness)return;var blob=new Blob([JSON.stringify(readiness,null,2)],{type:'application/json'}),url=URL.createObjectURL(blob),a=document.createElement('a');a.href=url;a.download='buy-in-deployment-readiness.json';document.body.appendChild(a);a.click();a.remove();setTimeout(function(){URL.revokeObjectURL(url)},250)}
document.addEventListener('DOMContentLoaded',function(){root=q('[data-share-deploy-root]');if(!root)return;load();q('[data-share-deploy-refresh]',root).addEventListener('click',load);q('[data-share-deploy-download]',root).addEventListener('click',download)});
})(window,document);
