document.addEventListener('DOMContentLoaded',function(){
  'use strict';
  var root=document.querySelector('[data-admin-system-health]');
  if(!root||!window.Microgifter)return;
  var MG=window.Microgifter;
  var run=root.querySelector('[data-sql-diagnostics-refresh]');
  var dl=root.querySelector('[data-sql-diagnostics-download]');
  var plan=null;
  function setText(sel,text){var el=root.querySelector(sel);if(el)el.textContent=text;}
  function saveFile(name,text){var blob=new Blob([text||''],{type:'text/plain;charset=utf-8'});var url=URL.createObjectURL(blob);var a=document.createElement('a');a.href=url;a.download=name||'microgifter_system_diagnostics.txt';document.body.appendChild(a);a.click();a.remove();setTimeout(function(){URL.revokeObjectURL(url);},800);}
  function apply(data){data=data||{};plan=data.repair_plan||null;if(run){run.disabled=false;run.textContent='Run diagnostics';}if(dl){dl.disabled=!(plan&&plan.sql);dl.textContent=(plan&&plan.sql)?'Download diagnostics SQL':'Download repair SQL';}if(data.summary)setText('[data-sql-diagnostics-summary]',data.summary);}
  async function refresh(e){if(e){e.preventDefault();e.stopImmediatePropagation();}if(run){run.disabled=true;run.textContent='Running…';}setText('[data-sql-diagnostics-summary]','Running fresh SQL diagnostics…');try{var res=await MG.get('/api/admin/system-sql-diagnostics.php?_='+Date.now());apply(res.data||res);if(MG.toast)MG.toast('System SQL diagnostics refreshed.','success');}catch(err){if(run){run.disabled=false;run.textContent='Run diagnostics';}setText('[data-sql-diagnostics-summary]',err.message||'Unable to run system SQL diagnostics.');if(MG.toast)MG.toast(err.message||'Unable to run system SQL diagnostics.','error');}}
  if(run)run.addEventListener('click',refresh,true);
  if(dl)dl.addEventListener('click',function(e){e.preventDefault();e.stopImmediatePropagation();if(plan&&plan.sql)saveFile(plan.filename||'microgifter_system_sql_diagnostics.sql',plan.sql);},true);
  setTimeout(refresh,500);
});
