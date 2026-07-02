document.addEventListener('DOMContentLoaded',function(){
  'use strict';
  var root=document.querySelector('[data-admin-system-health]');
  if(!root)return;
  var staleTypes={
    'admin.queue_reporting.failed':true,
    'admin.queue_automation.failed':true,
    'admin.risk_forecast.failed':true,
    'admin.operations_command.failed':true,
    'admin.ops_activity.failed':true,
    'admin.system_sql_diagnostics.failed':true
  };
  function rowTime(row){
    var small=row.querySelector('small');
    if(!small)return 0;
    var text=small.textContent||'';
    var parts=text.split('·');
    var raw=(parts[1]||'').trim();
    if(!raw)return 0;
    var time=Date.parse(raw);
    return Number.isFinite(time)?time:0;
  }
  function filterWarnings(){
    var list=root.querySelector('[data-system-health-warnings]');
    if(!list)return;
    var rows=list.querySelectorAll('.mg-system-health-warning');
    var now=Date.now();
    rows.forEach(function(row){
      var title=row.querySelector('strong');
      var type=title?(title.textContent||'').trim():'';
      if(!staleTypes[type])return;
      var time=rowTime(row);
      if(time&&time<now-3600000)row.remove();
    });
    if(!list.querySelector('.mg-system-health-warning')&&!list.querySelector('.mg-system-health-empty')){
      var empty=document.createElement('div');
      empty.className='mg-system-health-empty';
      empty.innerHTML='<strong>No recent warnings</strong><p>Previous admin request failures are older than the active warning window.</p>';
      list.appendChild(empty);
    }
  }
  filterWarnings();
  var list=root.querySelector('[data-system-health-warnings]');
  if(list&&'MutationObserver'in window){
    new MutationObserver(filterWarnings).observe(list,{childList:true,subtree:true});
  }
});

document.addEventListener('DOMContentLoaded',function(){
  'use strict';
  var root=document.querySelector('[data-admin-system-health]');
  if(!root||!window.Microgifter)return;
  var MG=window.Microgifter;
  function token(){return root.dataset.sensitiveConfirmToken||'';}
  function confirmMessage(action){return {
    verify_storage:'Run protected storage verification? This requires a fresh admin security token.',
    retry_notifications:'Requeue failed notifications? This requires a fresh admin security token.',
    clean_uploads:'Archive and remove abandoned uploads? This requires a fresh admin security token.',
    migration_plan:'Prepare the migration recovery plan? This requires a fresh admin security token.',
    admin_ops_sql_plan:'Prepare and download the Admin Ops SQL plan? This requires a fresh admin security token.',
    test_pwa_notification:'Send a PWA test notification? This requires a fresh admin security token.'
  }[action]||'Run this protected action?';}
  function downloadText(filename,text){
    var blob=new Blob([text||''],{type:'text/sql;charset=utf-8'});
    var url=URL.createObjectURL(blob);
    var link=document.createElement('a');
    link.href=url;
    link.download=filename||'microgifter_admin_ops_recovery.sql';
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(function(){URL.revokeObjectURL(url);},800);
  }
  function resultMessage(action,result){
    if(action==='verify_storage')return 'Persistent storage verified.';
    if(action==='retry_notifications')return Number(result.retried||0).toLocaleString()+' notification deliveries queued for retry.';
    if(action==='clean_uploads')return Number(result.archived||0).toLocaleString()+' abandoned uploads archived; '+Number(result.files_deleted||0).toLocaleString()+' files removed.';
    if(action==='migration_plan')return Number(result.missing_count||0).toLocaleString()+' missing migration file(s).';
    if(action==='admin_ops_sql_plan')return 'Admin Ops SQL plan prepared.';
    if(action==='test_pwa_notification')return 'PWA test notification queued.';
    return 'Protected action completed.';
  }
  async function run(button,event){
    if(event){event.preventDefault();event.stopImmediatePropagation();}
    var action=button.dataset.healthAction;
    if(button.dataset.healthActionEnabled!=='true')return;
    if(!token()){
      if(MG.toast)MG.toast('Security confirmation is unavailable. Refresh the page and try again.','error');
      return;
    }
    if(!window.confirm(confirmMessage(action)))return;
    var original=button.textContent;
    button.disabled=true;
    button.textContent='Running…';
    try{
      var response=await MG.post('/api/admin/system-health-action.php',{action:action,sensitive_confirm_token:token()});
      var data=response.data||response;
      var result=data.result||{};
      if(action==='admin_ops_sql_plan'&&result.sql)downloadText(result.filename||'microgifter_admin_ops_recovery.sql',result.sql);
      if(MG.toast)MG.toast(resultMessage(action,result),'success');
      var refresh=root.querySelector('[data-system-health-refresh]');
      if(refresh)refresh.click();
    }catch(error){
      if(MG.toast)MG.toast(error.message||'Unable to complete protected action.','error');
    }finally{
      button.textContent=original;
      if(button.dataset.healthActionEnabled==='true')button.disabled=false;
    }
  }
  root.addEventListener('click',function(event){
    var button=event.target.closest('[data-health-action]');
    if(button)run(button,event);
  },true);
});
