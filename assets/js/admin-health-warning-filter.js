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
  var migrationStorageKey='microgifter.systemHealth.migrationPlan.v2';
  var reconciliationStorageKey='microgifter.systemHealth.migrationReconciliation.v1';

  function token(){return root.dataset.sensitiveConfirmToken||'';}
  function confirmMessage(action){return {
    verify_storage:'Run protected storage verification? This requires a fresh admin security token.',
    retry_notifications:'Requeue failed notifications? This requires a fresh admin security token.',
    clean_uploads:'Archive and remove abandoned uploads? This requires a fresh admin security token.',
    migration_plan:'Prepare the detailed read-only migration recovery plan? No database changes will be executed.',
    migration_reconciliation_plan:'Analyze unrecorded migrations against the live database? This analysis is read-only.',
    migration_reconciliation_apply:'Record only migrations proven fully installed by the live reconciliation checks? No migration DDL will run.',
    critical_schema_plan:'Prepare the read-only critical schema plan?',
    admin_ops_sql_plan:'Prepare and download the Admin Ops SQL plan? This requires a fresh admin security token.',
    test_pwa_notification:'Send a PWA test notification? This requires a fresh admin security token.'
  }[action]||'Run this protected action?';}

  function downloadText(filename,text,type){
    var blob=new Blob([text||''],{type:type||'text/plain;charset=utf-8'});
    var url=URL.createObjectURL(blob);
    var link=document.createElement('a');
    link.href=url;
    link.download=filename||'microgifter-export.txt';
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(function(){URL.revokeObjectURL(url);},800);
  }

  function resultMessage(action,result){
    if(action==='verify_storage')return 'Persistent storage verified.';
    if(action==='retry_notifications')return Number(result.retried||0).toLocaleString()+' notification deliveries queued for retry.';
    if(action==='clean_uploads')return Number(result.archived||0).toLocaleString()+' abandoned uploads archived; '+Number(result.files_deleted||0).toLocaleString()+' files removed.';
    if(action==='migration_plan'){
      var physical=Number((result.summary||{}).physical_missing||0);
      var unapplied=Number((result.summary||{}).unapplied||0);
      return 'Migration plan ready: '+physical+' file(s) absent from /database and '+unapplied+' present but unapplied.';
    }
    if(action==='migration_reconciliation_plan'){
      return 'Reconciliation ready: '+Number(result.recordable_count||0).toLocaleString()+' migration(s) are verified and safe to record.';
    }
    if(action==='migration_reconciliation_apply'){
      return Number(result.recorded_count||0).toLocaleString()+' verified migration ledger entr'+(Number(result.recorded_count||0)===1?'y':'ies')+' recorded.';
    }
    if(action==='critical_schema_plan')return result.ready?'Critical schema dependencies are satisfied.':'Critical schema remediation is required.';
    if(action==='admin_ops_sql_plan')return 'Admin Ops SQL plan prepared.';
    if(action==='test_pwa_notification')return 'PWA test notification queued.';
    return 'Protected action completed.';
  }

  function el(tag,className,text){
    var node=document.createElement(tag);
    if(className)node.className=className;
    if(text!==undefined)node.textContent=String(text);
    return node;
  }

  function ensureStyles(){
    if(document.getElementById('mg-migration-plan-styles'))return;
    var style=document.createElement('style');
    style.id='mg-migration-plan-styles';
    style.textContent='\
      .mg-migration-plan-panel,.mg-migration-reconciliation-panel{margin-top:18px;padding:20px;border:1px solid #d9e2ef;border-radius:18px;background:#fff;box-shadow:0 12px 32px rgba(15,23,42,.05)}\
      .mg-migration-plan-panel header,.mg-migration-reconciliation-panel>header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:16px}.mg-migration-plan-panel h2,.mg-migration-reconciliation-panel h2{margin:0 0 5px;font-size:20px}.mg-migration-plan-panel p,.mg-migration-reconciliation-panel p{margin:0;color:#64748b}\
      .mg-migration-plan-actions{display:flex;gap:8px;flex-wrap:wrap}.mg-migration-plan-actions button{border:1px solid #cbd5e1;background:#f8fafc;border-radius:999px;padding:9px 14px;font-weight:700;cursor:pointer}.mg-migration-plan-actions button.is-primary{background:#102d4c;color:#fff;border-color:#102d4c}.mg-migration-plan-actions button:disabled{opacity:.5;cursor:not-allowed}\
      .mg-migration-plan-metrics{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px;margin:14px 0}.mg-migration-plan-metrics article{padding:13px;border:1px solid #e2e8f0;border-radius:14px;background:#f8fafc}.mg-migration-plan-metrics span{display:block;font-size:12px;color:#64748b}.mg-migration-plan-metrics strong{display:block;margin-top:5px;font-size:22px}\
      .mg-migration-plan-groups{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.mg-migration-plan-group{border:1px solid #e2e8f0;border-radius:14px;padding:14px;min-width:0}.mg-migration-plan-group.is-critical{border-color:#fecaca;background:#fff7f7}.mg-migration-plan-group.is-warning{border-color:#fde68a;background:#fffbeb}.mg-migration-plan-group.is-healthy{border-color:#bbf7d0;background:#f0fdf4}.mg-migration-plan-group h3{margin:0 0 9px;font-size:15px}.mg-migration-plan-group ul{margin:0;padding-left:18px;max-height:260px;overflow:auto}.mg-migration-plan-group li{margin:6px 0;overflow-wrap:anywhere;font-size:13px}.mg-migration-plan-note{margin-top:14px;padding:12px;border-radius:12px;background:#f1f5f9;color:#334155;font-size:13px;overflow-wrap:anywhere}\
      .mg-migration-reconciliation-list{display:grid;gap:10px}.mg-migration-reconciliation-item{border:1px solid #e2e8f0;border-radius:14px;padding:14px;background:#fff}.mg-migration-reconciliation-item.is-installed{border-color:#bbf7d0;background:#f0fdf4}.mg-migration-reconciliation-item.is-partial{border-color:#fde68a;background:#fffbeb}.mg-migration-reconciliation-item.is-missing,.mg-migration-reconciliation-item.is-missing_file,.mg-migration-reconciliation-item.is-empty_file{border-color:#fecaca;background:#fff7f7}.mg-migration-reconciliation-item header{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}.mg-migration-reconciliation-item strong{overflow-wrap:anywhere}.mg-migration-reconciliation-item header span{font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.04em}.mg-migration-reconciliation-item small{display:block;margin-top:6px;color:#64748b}.mg-migration-reconciliation-item ul{margin:9px 0 0;padding-left:18px}.mg-migration-reconciliation-item li{font-size:13px;margin:4px 0;overflow-wrap:anywhere}\
      @media(max-width:1100px){.mg-migration-plan-metrics{grid-template-columns:repeat(3,minmax(0,1fr))}.mg-migration-plan-groups{grid-template-columns:1fr}}@media(max-width:640px){.mg-migration-plan-panel header,.mg-migration-reconciliation-panel>header{display:block}.mg-migration-plan-actions{margin-top:12px}.mg-migration-plan-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}}';
    document.head.appendChild(style);
  }

  function listGroup(title,items,tone,emptyText){
    var card=el('section','mg-migration-plan-group is-'+tone);
    card.appendChild(el('h3','',title+' ('+items.length.toLocaleString()+')'));
    var list=el('ul');
    if(!items.length)list.appendChild(el('li','',emptyText));
    items.forEach(function(item){list.appendChild(el('li','',typeof item==='string'?item:(item.file||JSON.stringify(item))));});
    card.appendChild(list);
    return card;
  }

  function exportMigrationPayload(result){
    return {
      report:'Microgifter Migration Status Export',generated_at:result.generated_at||new Date().toISOString(),ready:Boolean(result.ready),ledger_ready:Boolean(result.ledger_ready),manifest_count:Number(result.manifest_count||0),applied_key_count:Number(result.applied_key_count||0),coverage_cutoff:Number(result.coverage_cutoff||-1),summary:result.summary||{},physical_missing_files:result.physical_missing_files||[],unapplied_files:result.unapplied_files||[],checksum_mismatches:result.checksum_mismatches||[],items:result.items||[],recovery_command:result.command||'php scripts/run_migrations.php',note:result.note||''
    };
  }

  function copyPlan(result){
    var lines=['Microgifter Migration Plan','Generated: '+(result.generated_at||''),''];
    lines.push('UPLOAD REQUIRED:');
    (result.physical_missing_files||[]).forEach(function(file){lines.push('- '+file);});
    lines.push('','PRESENT BUT UNAPPLIED:');
    (result.unapplied_files||[]).forEach(function(file){lines.push('- '+file);});
    lines.push('','CHECKSUM MISMATCHES:');
    (result.checksum_mismatches||[]).forEach(function(item){lines.push('- '+(item.file||'unknown')+' ['+(item.key||'unknown key')+']');});
    var text=lines.join('\n');
    if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(text).then(function(){if(MG.toast)MG.toast('Migration plan copied.','success');});}
    else downloadText('microgifter-migration-plan.txt',text,'text/plain;charset=utf-8');
  }

  function insertPanel(panel,attribute){
    var old=root.querySelector('['+attribute+']');
    if(old)old.remove();
    var recovery=root.querySelector('[data-system-health-recovery]');
    if(!recovery)return false;
    var recoverySection=recovery.closest('.mg-system-health-section')||recovery.parentElement;
    var anchor=recoverySection;
    var migration=root.querySelector('[data-migration-plan-results]');
    if(attribute==='data-migration-reconciliation-results'&&migration)anchor=migration;
    anchor.insertAdjacentElement('afterend',panel);
    return true;
  }

  function renderMigrationPlan(result){
    if(!result)return;
    ensureStyles();
    var panel=el('section','mg-migration-plan-panel');
    panel.dataset.migrationPlanResults='';
    var header=el('header');
    var copy=el('div');
    copy.append(el('h2','','Migration Plan Results'),el('p','',result.ready?'The canonical migration manifest is satisfied.':'Read-only database and deployment comparison. Review before uploading or importing SQL.'));
    var actions=el('div','mg-migration-plan-actions');
    var reconcileButton=el('button','is-primary','Analyze installed schema');reconcileButton.type='button';reconcileButton.dataset.healthAction='migration_reconciliation_plan';reconcileButton.dataset.healthActionEnabled='true';
    var copyButton=el('button','','Copy plan');copyButton.type='button';copyButton.addEventListener('click',function(){copyPlan(result);});
    var downloadButton=el('button','','Download full JSON');downloadButton.type='button';downloadButton.addEventListener('click',function(){downloadText('microgifter-migration-status-'+new Date().toISOString().slice(0,10)+'.json',JSON.stringify(exportMigrationPayload(result),null,2),'application/json;charset=utf-8');});
    var clearButton=el('button','','Clear');clearButton.type='button';clearButton.addEventListener('click',function(){sessionStorage.removeItem(migrationStorageKey);panel.remove();});
    actions.append(reconcileButton,copyButton,downloadButton,clearButton);header.append(copy,actions);panel.appendChild(header);
    var summary=result.summary||{};
    var metrics=el('div','mg-migration-plan-metrics');
    [['Manifest',result.manifest_count||0],['Applied',summary.applied||0],['Covered',summary.covered||0],['Unapplied',summary.unapplied||0],['Files missing',summary.physical_missing||0],['Checksum drift',summary.checksum_mismatches||0]].forEach(function(metric){var card=el('article');card.append(el('span','',metric[0]),el('strong','',Number(metric[1]).toLocaleString()));metrics.appendChild(card);});
    panel.appendChild(metrics);
    var groups=el('div','mg-migration-plan-groups');
    groups.append(listGroup('Upload to /database',result.physical_missing_files||[],'critical','No physical migration files are missing.'),listGroup('Present but unapplied',result.unapplied_files||[],'warning','No present migration files are waiting to be applied.'),listGroup('Checksum mismatches',result.checksum_mismatches||[],'warning','No checksum mismatches were detected.'));
    panel.appendChild(groups);
    panel.appendChild(el('div','mg-migration-plan-note',(result.note||'Read-only report.')+' Recovery command: '+(result.command||'php scripts/run_migrations.php')));
    insertPanel(panel,'data-migration-plan-results');
  }

  function renderReconciliation(result){
    if(!result)return;
    ensureStyles();
    var panel=el('section','mg-migration-reconciliation-panel');
    panel.dataset.migrationReconciliationResults='';
    var header=el('header');
    var copy=el('div');
    copy.append(el('h2','','Migration Reconciliation'),el('p','',result.ready?'No unrecorded migrations remain.':'Live schema evidence for migrations present on disk but absent from the ledger.'));
    var actions=el('div','mg-migration-plan-actions');
    var apply=el('button','is-primary','Record verified installed');apply.type='button';apply.disabled=!result.reconciliation_token||Number(result.recordable_count||0)<1;apply.addEventListener('click',function(){applyReconciliation(result,apply);});
    var repair=el('button','','Download repair SQL');repair.type='button';repair.disabled=!(result.repair_plan&&result.repair_plan.available&&result.repair_plan.sql);repair.addEventListener('click',function(){if(!repair.disabled)downloadText(result.repair_plan.filename||'microgifter-migration-repair.sql',result.repair_plan.sql,'text/sql;charset=utf-8');});
    var json=el('button','','Download JSON');json.type='button';json.addEventListener('click',function(){downloadText('microgifter-migration-reconciliation-'+new Date().toISOString().slice(0,10)+'.json',JSON.stringify(result,null,2),'application/json;charset=utf-8');});
    var clear=el('button','','Clear');clear.type='button';clear.addEventListener('click',function(){sessionStorage.removeItem(reconciliationStorageKey);panel.remove();});
    actions.append(apply,repair,json,clear);header.append(copy,actions);panel.appendChild(header);
    var summary=result.summary||{};
    var metrics=el('div','mg-migration-plan-metrics');
    [['Unrecorded',result.unapplied_count||0],['Verified installed',summary.installed||0],['Partial',summary.partial||0],['Missing',summary.missing||0],['Unsupported',summary.unsupported||0],['Safe to record',result.recordable_count||0]].forEach(function(metric){var card=el('article');card.append(el('span','',metric[0]),el('strong','',Number(metric[1]).toLocaleString()));metrics.appendChild(card);});
    panel.appendChild(metrics);
    var list=el('div','mg-migration-reconciliation-list');
    (result.items||[]).forEach(function(item){
      var row=el('article','mg-migration-reconciliation-item is-'+String(item.status||'unsupported'));
      var rowHeader=el('header');rowHeader.append(el('strong','',item.file||'Migration'),el('span','',String(item.status||'unsupported').replace(/_/g,' ')));row.appendChild(rowHeader);
      row.appendChild(el('small','',Number(item.ready_check_count||0).toLocaleString()+' of '+Number(item.check_count||0).toLocaleString()+' evidence checks satisfied'+(item.recordable?' · safe to record':'')));
      var missing=(item.checks||[]).filter(function(check){return !check.ready;});
      if(missing.length){var ul=el('ul');missing.slice(0,8).forEach(function(check){ul.appendChild(el('li','',check.label||check.id||'Missing evidence'));});if(missing.length>8)ul.appendChild(el('li','',String(missing.length-8)+' additional findings'));row.appendChild(ul);}
      list.appendChild(row);
    });
    if(!(result.items||[]).length){var empty=el('div','mg-system-health-empty');empty.append(el('strong','','Ledger reconciled'),el('p','','No unrecorded migration files require analysis.'));list.appendChild(empty);}
    panel.appendChild(list);
    panel.appendChild(el('div','mg-migration-plan-note',result.note||'Reconciliation never executes migration DDL.'));
    insertPanel(panel,'data-migration-reconciliation-results');
  }

  function saveMigrationPlan(result){try{sessionStorage.setItem(migrationStorageKey,JSON.stringify(result));}catch(error){}renderMigrationPlan(result);}
  function saveReconciliation(result){try{sessionStorage.setItem(reconciliationStorageKey,JSON.stringify(result));}catch(error){}renderReconciliation(result);}
  function restorePanels(){
    try{var raw=sessionStorage.getItem(migrationStorageKey);if(raw)renderMigrationPlan(JSON.parse(raw));}catch(error){sessionStorage.removeItem(migrationStorageKey);}
    try{var reconcile=sessionStorage.getItem(reconciliationStorageKey);if(reconcile)renderReconciliation(JSON.parse(reconcile));}catch(error){sessionStorage.removeItem(reconciliationStorageKey);}
  }

  async function postAction(action,extra){
    var payload=Object.assign({action:action,sensitive_confirm_token:token()},extra||{});
    var response=await MG.post('/api/admin/system-health-action.php',payload);
    var data=response.data||response;
    return data.result||{};
  }

  async function applyReconciliation(result,button){
    if(!result.reconciliation_token||Number(result.recordable_count||0)<1)return;
    if(!window.confirm(confirmMessage('migration_reconciliation_apply')))return;
    var original=button.textContent;button.disabled=true;button.textContent='Recording…';
    try{
      var applied=await postAction('migration_reconciliation_apply',{reconciliation_token:result.reconciliation_token});
      var next=applied.plan||{};saveReconciliation(next);
      if(MG.toast)MG.toast(resultMessage('migration_reconciliation_apply',applied),'success');
      var refresh=root.querySelector('[data-system-health-refresh]');if(refresh)refresh.click();
    }catch(error){if(MG.toast)MG.toast(error.message||'Unable to reconcile the migration ledger.','error');}
    finally{button.textContent=original;if(document.body.contains(button))button.disabled=false;}
  }

  async function run(button,event){
    if(event){event.preventDefault();event.stopImmediatePropagation();}
    var action=button.dataset.healthAction;
    if(button.dataset.healthActionEnabled!=='true')return;
    if(!token()){if(MG.toast)MG.toast('Security confirmation is unavailable. Refresh the page and try again.','error');return;}
    if(!window.confirm(confirmMessage(action)))return;
    var original=button.textContent;button.disabled=true;button.textContent='Running…';
    try{
      var result=await postAction(action);
      if(action==='admin_ops_sql_plan'&&result.sql)downloadText(result.filename||'microgifter_admin_ops_recovery.sql',result.sql,'text/sql;charset=utf-8');
      if(action==='migration_plan')saveMigrationPlan(result);
      if(action==='migration_reconciliation_plan')saveReconciliation(result);
      if(action==='critical_schema_plan')downloadText('microgifter-critical-schema-plan-'+new Date().toISOString().slice(0,10)+'.json',JSON.stringify(result,null,2),'application/json;charset=utf-8');
      if(MG.toast)MG.toast(resultMessage(action,result),'success');
      if(action!=='migration_plan'&&action!=='migration_reconciliation_plan'){
        var refresh=root.querySelector('[data-system-health-refresh]');if(refresh)refresh.click();
      }
    }catch(error){if(MG.toast)MG.toast(error.message||'Unable to complete protected action.','error');}
    finally{button.textContent=original;if(button.dataset.healthActionEnabled==='true')button.disabled=false;}
  }

  root.addEventListener('click',function(event){var button=event.target.closest('[data-health-action]');if(button)run(button,event);},true);
  restorePanels();
});
