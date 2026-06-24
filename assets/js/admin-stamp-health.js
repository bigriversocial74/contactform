document.addEventListener('DOMContentLoaded',function(){
  if(!window.Microgifter)return;
  var button=document.querySelector('[data-run-stamp-health]');
  var list=document.querySelector('[data-stamp-health-list]');
  var message=document.querySelector('[data-stamp-health-message]');
  function esc(v){return String(v==null?'':v).replace(/[&<>'\"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','\"':'&quot;'})[c];});}
  function setText(sel,value){var el=document.querySelector(sel);if(el)el.textContent=value;}
  function summarize(items){var total=items.length;var ok=items.filter(function(i){return !!i.ok;}).length;return ok+'/'+total;}
  function detail(row){if(row.error)return row.error;if(row.missing_columns&&row.missing_columns.length)return 'Missing columns: '+row.missing_columns.join(', ');if(row.path)return row.path;if(row.count!=null)return 'Count: '+row.count;return row.exists===false?'Missing':'Ready';}
  async function run(){
    try{
      if(message)message.textContent='Running Stamp health check...';
      var r=await Microgifter.get('/api/stamps/health.php');
      var data=r.data||r;
      var checks=data.checks||[];
      setText('[data-stamp-health-status]',data.status||'unknown');
      setText('[data-health-overall]',data.ok?'Green':'Needs work');
      setText('[data-health-tables]',summarize(data.tables||[]));
      setText('[data-health-files]',summarize(data.files||[]));
      setText('[data-health-actions]',String((data.counts&&data.counts.enabled_stamp_actions)||0));
      setText('[data-health-bundles]',String((data.counts&&data.counts.active_stamp_bundles)||0));
      if(message)message.textContent=data.ok?'Stamp system is green.':'Stamp system needs attention before production.';
      if(list)list.innerHTML=checks.map(function(row){return '<tr><td><strong>'+esc(row.name||row.path||'check')+'</strong></td><td>'+(row.ok?'Ready':'Needs attention')+'</td><td>'+esc(detail(row))+'</td></tr>';}).join('')||'<tr><td colspan="3">No checks returned.</td></tr>';
    }catch(error){if(message)message.textContent=error.message||'Unable to run Stamp health check.';if(list)list.innerHTML='<tr><td colspan="3">Health check failed.</td></tr>';}
  }
  if(button)button.addEventListener('click',run);
  run();
});
