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
