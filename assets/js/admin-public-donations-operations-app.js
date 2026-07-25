import{show,clear,setNotice,setBusy,renderStats,renderReadiness,renderRollout,renderMerchantResults,renderPermissions,renderReceipts,renderOperations,renderResult}from'./admin-public-donations-operations-ui.js?v=20260724-v1';

export function boot(){
  const root=document.querySelector('[data-public-donations-operations]');
  if(!root)return;
  const state={data:null,selected:new Map(),loading:false};
  const csrf=String(root.dataset.csrf||'');
  const loading=root.querySelector('[data-pdo-loading]');
  const errorBox=root.querySelector('[data-pdo-error]');
  const errorMessage=root.querySelector('[data-pdo-error-message]');
  const refresh=root.querySelector('[data-pdo-refresh]');
  const rolloutForm=root.querySelector('[data-pdo-rollout-form]');
  const rolloutNotice=root.querySelector('[data-pdo-rollout-notice]');
  const featureState=root.querySelector('[data-pdo-feature-state]');
  const selectedBlock=root.querySelector('[data-pdo-selected-block]');
  const selectedList=root.querySelector('[data-pdo-selected-merchants]');
  const merchantQuery=root.querySelector('[data-pdo-merchant-query]');
  const merchantResults=root.querySelector('[data-pdo-merchant-results]');
  const reconcileForm=root.querySelector('[data-pdo-reconcile-form]');
  const reconcileNotice=root.querySelector('[data-pdo-reconcile-notice]');
  const repairModes=root.querySelector('[data-pdo-repair-modes]');
  const repairConfirm=root.querySelector('[data-pdo-repair-confirmation-wrap]');
  const reconcileSubmit=root.querySelector('[data-pdo-reconcile-submit]');

  const removeMerchant=(id)=>{state.selected.delete(id);renderSelected()};
  const renderSelected=()=>{clear(selectedList);if(!state.selected.size){const empty=document.createElement('span');empty.className='mg-pdo-empty-inline';empty.textContent='No merchants selected.';selectedList.appendChild(empty);return}Array.from(state.selected.values()).sort((a,b)=>a.id-b.id).forEach((merchant)=>{const chip=document.createElement('span');chip.className='mg-pdo-chip';const copy=document.createElement('span');copy.textContent=`${merchant.display_name||merchant.email||'Merchant'} · #${merchant.id}`;const remove=document.createElement('button');remove.type='button';remove.textContent='×';remove.setAttribute('aria-label',`Remove merchant ${merchant.id}`);remove.addEventListener('click',()=>removeMerchant(Number(merchant.id)));chip.append(copy,remove);selectedList.appendChild(chip)})};

  async function getJson(url){const response=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json'}});const payload=await response.json().catch(()=>null);if(!response.ok||!payload?.ok)throw new Error(payload?.message||'Request failed.');return payload.data||{}}
  async function postJson(payload){const response=await Microgifter.post('/api/admin/public-donations-operations-action.php',{...payload,csrf_token:csrf});if(!response?.ok)throw new Error(response?.message||'Request failed.');return response.data||{}}

  function syncMode(){const mode=reconcileForm.querySelector('input[name="execution_mode"]:checked')?.value||'dry_run';const repairing=mode==='repair';repairModes.disabled=!repairing;repairModes.querySelectorAll('input').forEach((input)=>{input.disabled=!repairing});repairConfirm.hidden=!repairing;reconcileSubmit.textContent=repairing?'Execute safe repair':'Run dry reconciliation'}
  function renderAll(){renderStats(root,state.data);renderReadiness(root,state.data);renderRollout(root,state.data,state.selected,removeMerchant);renderPermissions(root,state.data,syncMode);renderReceipts(root,state.data);renderOperations(root,state.data);root.querySelector('[data-pdo-updated]').textContent=new Intl.DateTimeFormat(undefined,{hour:'numeric',minute:'2-digit',second:'2-digit'}).format(new Date())}

  async function load(){if(state.loading)return;state.loading=true;refresh.disabled=true;show(loading,true);show(errorBox,false);try{state.data=await getJson('/api/admin/public-donations-operations.php');renderAll();show(loading,false)}catch(error){errorMessage.textContent=error.message||'Unable to load Public Donations operations.';show(loading,false);show(errorBox,true)}finally{state.loading=false;refresh.disabled=false}}

  async function searchMerchants(){const query=merchantQuery.value.trim();if(!query){setNotice(rolloutNotice,'Enter a merchant name, email, or user ID.','error');return}const button=root.querySelector('[data-pdo-search-merchants]');button.disabled=true;clear(merchantResults);try{const data=await getJson(`/api/admin/public-donations-operations.php?q=${encodeURIComponent(query)}`);const items=Array.isArray(data.merchant_search)?data.merchant_search:[];renderMerchantResults(merchantResults,items,state.selected,(merchant)=>{state.selected.set(Number(merchant.id),merchant);renderSelected();renderMerchantResults(merchantResults,items,state.selected,(item)=>{state.selected.set(Number(item.id),item);renderSelected();searchMerchants()})})}catch(error){setNotice(rolloutNotice,error.message||'Unable to search merchants.','error')}finally{button.disabled=false}}

  featureState.addEventListener('change',()=>selectedBlock.classList.toggle('is-disabled',featureState.value!=='selected_merchants'));
  root.querySelector('[data-pdo-clear-merchants]').addEventListener('click',()=>{state.selected.clear();renderSelected()});
  root.querySelector('[data-pdo-search-merchants]').addEventListener('click',searchMerchants);
  merchantQuery.addEventListener('keydown',(event)=>{if(event.key==='Enter'){event.preventDefault();searchMerchants()}});
  refresh.addEventListener('click',load);
  root.querySelector('[data-pdo-retry]').addEventListener('click',load);
  reconcileForm.querySelectorAll('input[name="execution_mode"]').forEach((input)=>input.addEventListener('change',syncMode));

  rolloutForm.addEventListener('submit',async(event)=>{event.preventDefault();const data=new FormData(rolloutForm);const payload={action:'update_rollout',feature_state:String(data.get('feature_state')||'disabled'),selected_merchant_ids:Array.from(state.selected.keys()),reason:String(data.get('reason')||'').trim(),confirmation:String(data.get('confirmation')||'').trim()};if(payload.reason.length<8){setNotice(rolloutNotice,'Enter an action reason with at least 8 characters.','error');return}if(payload.confirmation!=='UPDATE PUBLIC DONATIONS ROLLOUT'){setNotice(rolloutNotice,'Type UPDATE PUBLIC DONATIONS ROLLOUT to confirm.','error');return}if(payload.feature_state==='selected_merchants'&&!payload.selected_merchant_ids.length){setNotice(rolloutNotice,'Select at least one merchant.','error');return}setBusy(rolloutForm,true);setNotice(rolloutNotice,'Applying rollout configuration…');try{const response=await postJson(payload);state.data=response.operations||state.data;renderAll();rolloutForm.elements.namedItem('reason').value='';rolloutForm.elements.namedItem('confirmation').value='';setNotice(rolloutNotice,'Rollout updated and audit evidence recorded.','success')}catch(error){setNotice(rolloutNotice,error.message||'Unable to update rollout.','error')}finally{setBusy(rolloutForm,false);selectedBlock.classList.toggle('is-disabled',featureState.value!=='selected_merchants')}});

  root.querySelector('[data-pdo-environment]').addEventListener('click',async()=>{const reason=String(rolloutForm.elements.namedItem('reason').value||'').trim();const confirmation=String(rolloutForm.elements.namedItem('confirmation').value||'').trim();if(reason.length<8){setNotice(rolloutNotice,'Enter an action reason with at least 8 characters.','error');return}if(confirmation!=='RETURN TO ENVIRONMENT CONFIG'){setNotice(rolloutNotice,'Type RETURN TO ENVIRONMENT CONFIG to confirm.','error');return}setBusy(rolloutForm,true);setNotice(rolloutNotice,'Returning authority to environment configuration…');try{const response=await postJson({action:'return_to_environment',reason,confirmation});state.data=response.operations||state.data;renderAll();rolloutForm.elements.namedItem('reason').value='';rolloutForm.elements.namedItem('confirmation').value='';setNotice(rolloutNotice,'Environment configuration is authoritative again.','success')}catch(error){setNotice(rolloutNotice,error.message||'Unable to return to environment configuration.','error')}finally{setBusy(rolloutForm,false)}});

  reconcileForm.addEventListener('submit',async(event)=>{event.preventDefault();const data=new FormData(reconcileForm);const mode=String(data.get('execution_mode')||'dry_run');const modes=Array.from(reconcileForm.querySelectorAll('input[name="repair_modes[]"]:checked')).map((input)=>input.value);const payload={action:'reconcile',merchant_id:Number(data.get('merchant_id')||0),campaign:String(data.get('campaign')||'').trim(),operation:String(data.get('operation')||'').trim(),limit:Number(data.get('limit')||100),repair:mode==='repair'?modes.join(','):'',reason:String(data.get('reason')||'').trim(),confirmation:String(data.get('confirmation')||'').trim()};if(!payload.merchant_id){setNotice(reconcileNotice,'Enter a valid merchant user ID.','error');return}if(payload.reason.length<8){setNotice(reconcileNotice,'Enter an action reason with at least 8 characters.','error');return}if(mode==='repair'&&!modes.length){setNotice(reconcileNotice,'Select at least one deterministic repair mode.','error');return}if(mode==='repair'&&payload.confirmation!=='REPAIR PUBLIC DONATIONS'){setNotice(reconcileNotice,'Type REPAIR PUBLIC DONATIONS to confirm.','error');return}setBusy(reconcileForm,true);setNotice(reconcileNotice,mode==='repair'?'Applying deterministic repairs…':'Scanning canonical lifecycle…');try{const response=await postJson(payload);renderResult(root,response.reconciliation);state.data=response.operations||state.data;renderStats(root,state.data);renderReadiness(root,state.data);renderReceipts(root,state.data);renderOperations(root,state.data);setNotice(reconcileNotice,mode==='repair'?'Repair completed and receipt recorded.':'Dry run completed and receipt recorded.','success');reconcileForm.elements.namedItem('confirmation').value=''}catch(error){setNotice(reconcileNotice,error.message||'Unable to reconcile Public Donations.','error')}finally{setBusy(reconcileForm,false);syncMode()}});

  syncMode();load();
}
