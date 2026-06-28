document.addEventListener('DOMContentLoaded',function(){
  'use strict';
  var root=document.querySelector('[data-merchant-agent-chat]');
  if(!root||!window.Microgifter)return;
  var q=function(sel){return document.querySelector(sel);};
  var command={modes:[],goals:{},health_scores:[],timeline:[],can_demo:false,demo_mode:false};
  function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];});}
  function payload(r){return r&&r.data?r.data:r;}
  function status(msg,type){var s=q('[data-agent-chat-status]');if(s){s.textContent=msg||'';s.className='mg-form-status'+(type?' is-'+type:'');}}
  function modeByKey(key){return (command.modes||[]).filter(function(m){return m.key===key;})[0]||(command.modes||[])[0]||null;}
  function applyMode(mode){if(!mode)return;var scope=q('[data-agent-chat-scope]');if(scope)scope.value=mode.scope||'overview';var txt=q('[data-agent-chat-form] textarea');if(txt&&mode.placeholder)txt.placeholder=mode.placeholder;document.querySelectorAll('[data-agent-chat-prompts]').forEach(function(prompts){if(mode.prompts){prompts.innerHTML=mode.prompts.map(function(p){return '<button type="button">'+esc(p)+'</button>';}).join('');}});}
  function renderModes(){var select=q('[data-agent-mode-select]');if(!select)return;var modes=command.modes||[];select.innerHTML=modes.length?modes.map(function(m,i){return '<option value="'+esc(m.key)+'" '+(i===0?'selected':'')+'>'+esc(m.label)+'</option>';}).join(''):'<option value="">Mission Control</option>';applyMode(modeByKey(select.value));}
  function renderGoals(){var f=q('[data-agent-goals-form]');if(!f)return;['primary_goal','secondary_goal','focus','tone','budget'].forEach(function(k){if(f.elements[k])f.elements[k].value=command.goals&&command.goals[k]?command.goals[k]:'';});}
  function renderHealth(){var box=q('[data-agent-health-list]');if(!box)return;var scores=command.demo_mode&&command.demo&&command.demo.health_scores?command.demo.health_scores:command.health_scores||[];box.innerHTML=scores.map(function(s){var n=Math.max(0,Math.min(100,parseInt(s.score||0,10)||0));return '<article><div><strong>'+esc(s.label)+'</strong><span>'+n+'/100</span></div><meter min="0" max="100" value="'+n+'"></meter></article>';}).join('')||'<div class="mg-empty-state"><strong>No scores yet.</strong></div>';}
  function renderTimeline(){var box=q('[data-agent-timeline]');if(!box)return;var rows=command.demo_mode&&command.demo&&command.demo.timeline?command.demo.timeline:command.timeline||[];box.innerHTML=rows.map(function(r){return '<article><strong>'+esc(r.title||'Agent activity')+'</strong><span>'+esc(r.status||'activity')+' · '+esc(r.time||'')+'</span></article>';}).join('')||'<div class="mg-empty-state"><strong>No timeline yet.</strong></div>';}
  function renderDemo(){var wrap=q('[data-agent-demo-wrap]');var input=q('[data-agent-demo-mode]');if(!wrap||!input)return;wrap.hidden=!command.can_demo;input.checked=!!command.demo_mode;}
  function renderAll(){renderModes();renderGoals();renderHealth();renderTimeline();renderDemo();}
  async function loadCommand(){try{var input=q('[data-agent-demo-mode]');var data=payload(await Microgifter.get('/api/ai/merchant-agent-command.php'+(input&&input.checked?'?demo=1':'')));command=data||{};renderAll();}catch(e){status(e.message||'Unable to load mission control.','error');}}
  async function postCommand(body,ok){try{status('Working...','');var data=payload(await Microgifter.post('/api/ai/merchant-agent-command.php',body));if(data.state)command=data.state;else command=data||command;renderAll();status(ok||'Done.','success');return data;}catch(e){status(e.message||'Command failed.','error');}}
  document.addEventListener('click',function(e){
    if(e.target.closest&&e.target.closest('[data-agent-daily-brief]'))postCommand({action:'daily_briefing'},'Daily briefing created.').then(function(){setTimeout(function(){window.location.reload();},600);});
    if(e.target.closest&&e.target.closest('[data-agent-package-create]'))postCommand({action:'create_package',title:'Three-part agent package',scope:(q('[data-agent-chat-scope]')||{}).value||'campaigns'},'Package sent to queue.');
  });
  document.addEventListener('change',function(e){
    if(e.target&&e.target.matches('[data-agent-demo-mode]'))loadCommand();
    if(e.target&&e.target.matches('[data-agent-mode-select]'))applyMode(modeByKey(e.target.value));
  });
  var goals=q('[data-agent-goals-form]');if(goals){goals.addEventListener('submit',function(e){e.preventDefault();postCommand({action:'save_goals',primary_goal:goals.elements.primary_goal.value,secondary_goal:goals.elements.secondary_goal.value,focus:goals.elements.focus.value,tone:goals.elements.tone.value,budget:goals.elements.budget.value},'Goals saved.');});}
  loadCommand();
});