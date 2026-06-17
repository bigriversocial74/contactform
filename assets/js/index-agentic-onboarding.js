document.addEventListener('DOMContentLoaded',function(){
'use strict';

const AGENTIC_PRESENTATION_CONFIG={
  speedMultiplier:1,
  slideHoldMin:5200,
  slideHoldMax:8200,
  transitionDuration:1400,
  fullFocusHold:2600,
  initialStartDelay:1800,
  sectionMinimumHold:6500,
  mobileHoldBonus:1800,
  manualScrollPauseThreshold:80,
  autoResumeAfterManualScroll:false,
  respectReducedMotion:true
};

var root=document.querySelector('[data-agentic-onboarding]');if(!root)return;
var stages=Array.from(root.querySelectorAll('[data-agentic-stage]'));
var progressBar=root.querySelector('[data-agentic-progress-bar]');
var progressText=root.querySelector('[data-agentic-progress-text]');
var progressLabel=root.querySelector('[data-agentic-step-label]');
var status=root.querySelector('[data-agentic-status]');
var resumeButton=root.querySelector('[data-agentic-resume]');
var storageKey='mg_agentic_index_progress_v2';
var reduceMotion=AGENTIC_PRESENTATION_CONFIG.respectReducedMotion&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
var state={playing:!reduceMotion,phase:'landing',landingIndex:0,step:0,interest:'',businessName:'',businessWebsite:'',ideas:[],selectedIdea:null,customProduct:'',skipped:[],complete:false};
var landingSections=Array.from(document.querySelectorAll('.hero,[data-sticky-section],.revenue-sticky,.roi-section')).filter(function(section){return !root.contains(section);});
var timer=null,scrollFrame=null,internalScroll=false,lastManualY=window.scrollY;
var labels=['Meet the agent','Choose objective','Business','Website scan','Product direction','Agent preview','Build handoff'];

function ms(value){return Math.round(value*AGENTIC_PRESENTATION_CONFIG.speedMultiplier);}
function clamp(value,min,max){return Math.min(max,Math.max(min,value));}
function wait(value){return new Promise(function(resolve){clearTimeout(timer);timer=setTimeout(resolve,ms(value));});}
function randomHold(){var c=AGENTIC_PRESENTATION_CONFIG;var random=c.slideHoldMin+Math.random()*(c.slideHoldMax-c.slideHoldMin);var mobile=window.matchMedia('(max-width:760px)').matches?c.mobileHoldBonus:0;return Math.max(random+mobile,c.sectionMinimumHold);}
function field(name){return root.querySelector('[data-agentic-field="'+name+'"]');}
function setStatus(text){if(status)status.textContent=text;}
function save(){try{sessionStorage.setItem(storageKey,JSON.stringify(state));}catch(e){}}
function load(){try{var saved=JSON.parse(sessionStorage.getItem(storageKey)||'null');if(saved&&typeof saved==='object')state=Object.assign(state,saved);}catch(e){}if(reduceMotion)state.playing=false;}
function emit(){document.dispatchEvent(new CustomEvent('mg:index-presentation-state',{detail:{playing:state.playing,phase:state.phase,step:state.step,complete:state.complete}}));}
function setPlaying(value){state.playing=Boolean(value)&&!reduceMotion;save();emit();if(resumeButton)resumeButton.classList.toggle('is-visible',!state.playing&&!reduceMotion);setStatus(state.playing?'Presentation in progress':'Presentation paused');}
function cancelMotion(){if(scrollFrame){cancelAnimationFrame(scrollFrame);scrollFrame=null;}clearTimeout(timer);timer=null;}
function smoothScrollTo(target,duration){cancelMotion();return new Promise(function(resolve){var start=window.scrollY;var end=Math.max(0,target);var distance=end-start;if(Math.abs(distance)<4){resolve();return;}var started=performance.now();internalScroll=true;function tick(now){if(!state.playing){internalScroll=false;scrollFrame=null;resolve();return;}var t=clamp((now-started)/ms(duration),0,1);var eased=t<.5?2*t*t:1-Math.pow(-2*t+2,2)/2;window.scrollTo(0,start+distance*eased);if(t<1)scrollFrame=requestAnimationFrame(tick);else{internalScroll=false;scrollFrame=null;resolve();}}scrollFrame=requestAnimationFrame(tick);});}
function sectionTarget(section){var header=document.querySelector('.nav');var offset=header?header.offsetHeight:76;return section.getBoundingClientRect().top+window.scrollY-offset;}
function focusVisual(container){var visual=container&&container.querySelector('[data-presentation-image]');if(!visual)return Promise.resolve();visual.classList.add('is-full-focus');return wait(AGENTIC_PRESENTATION_CONFIG.fullFocusHold).then(function(){visual.classList.remove('is-full-focus');});}
function updateProgress(){var pct=Math.round((state.step/(stages.length-1))*100);if(progressBar)progressBar.style.width=pct+'%';if(progressText)progressText.textContent=pct+'%';if(progressLabel)progressLabel.textContent=labels[state.step]||'Onboarding';stages.forEach(function(stage,index){stage.classList.toggle('is-active',index===state.step);stage.classList.toggle('is-complete',index<state.step);stage.classList.toggle('is-waiting',index===state.step&&stage.dataset.requiresInput==='true'&&!state.playing);});emit();}
function pause(reason,showResume){cancelMotion();setPlaying(false);if(showResume===false&&resumeButton)resumeButton.classList.remove('is-visible');setStatus(reason||'Presentation paused');}
function resume(){if(reduceMotion)return;setPlaying(true);runCurrent();}
function hydrate(){if(field('businessName'))field('businessName').value=state.businessName||'';if(field('businessWebsite'))field('businessWebsite').value=state.businessWebsite||'';if(field('customProduct'))field('customProduct').value=state.customProduct||'';root.querySelectorAll('[data-choice-value]').forEach(function(btn){btn.classList.toggle('is-selected',btn.dataset.choiceValue===state.interest);});renderIdeas();renderSummary();validateStage();updateProgress();}
function fallbackIdeas(){var name=state.businessName||'Your business';return[{title:name+' prepaid visit',description:'Sell flexible prepaid credit for a future visit.',value:'$25'},{title:'Limited early-access offer',description:'Reserve a future product, service, appointment, or experience.',value:'$50'},{title:'Local loyalty bundle',description:'Combine multiple future visits into one pre-sale package.',value:'$75'}];}
function escapeHtml(value){return String(value==null?'':value).replace(/[&<>'"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[c];});}
function renderIdeas(){var grid=root.querySelector('[data-agentic-product-grid]');if(!grid)return;var ideas=state.ideas&&state.ideas.length?state.ideas:fallbackIdeas();grid.innerHTML=ideas.map(function(idea,index){var selected=state.selectedIdea&&state.selectedIdea.title===idea.title;return '<button class="agentic-product-card '+(selected?'is-selected':'')+'" type="button" data-agentic-idea="'+index+'"><span><strong>'+escapeHtml(idea.title)+'</strong><p>'+escapeHtml(idea.description||'')+'</p></span><span class="agentic-product-value">'+escapeHtml(idea.value||'')+'</span></button>';}).join('');}
function renderSummary(){var summary=root.querySelector('[data-agentic-summary]');if(!summary)return;var product=state.customProduct||(state.selectedIdea?state.selectedIdea.title:'Not selected');summary.innerHTML=[['Objective',state.interest||'Skipped'],['Business',state.businessName||'Skipped'],['Website',state.businessWebsite||'Skipped'],['Product',product]].map(function(row){return '<div class="agentic-summary-row"><span>'+escapeHtml(row[0])+'</span><strong>'+escapeHtml(row[1])+'</strong></div>';}).join('');var signup=root.querySelector('[data-agentic-signup]');if(signup){var payload=btoa(unescape(encodeURIComponent(JSON.stringify({interest:state.interest,businessName:state.businessName,businessWebsite:state.businessWebsite,selectedIdea:state.selectedIdea,customProduct:state.customProduct}))));signup.href='/signup.php?onboarding='+encodeURIComponent(payload);}}
function validateStage(){var stage=stages[state.step];if(!stage)return;var next=stage.querySelector('[data-agentic-next]'),scan=stage.querySelector('[data-agentic-scan]'),valid=true;if(state.step===2)valid=state.businessName.trim().length>=2;else if(state.step===3)valid=/^https?:\/\//i.test(state.businessWebsite.trim())||/^[a-z0-9.-]+\.[a-z]{2,}/i.test(state.businessWebsite.trim());else if(state.step===4)valid=!!state.selectedIdea;else if(state.step===5)valid=state.customProduct.trim().length>=5;if(next)next.disabled=!valid;if(scan)scan.disabled=!valid;renderSummary();save();}
async function presentLanding(){if(!state.playing)return;if(state.landingIndex>=landingSections.length){state.phase='onboarding';state.step=0;save();updateProgress();return runCurrent();}var section=landingSections[state.landingIndex];setStatus('Presenting '+(section.id||'Microgifter'));await smoothScrollTo(sectionTarget(section),AGENTIC_PRESENTATION_CONFIG.transitionDuration);if(!state.playing)return;await focusVisual(section);if(!state.playing)return;await wait(randomHold());if(!state.playing)return;state.landingIndex+=1;save();presentLanding();}
async function presentOnboarding(){if(!state.playing)return;var stage=stages[state.step];if(!stage)return;updateProgress();await smoothScrollTo(sectionTarget(stage),AGENTIC_PRESENTATION_CONFIG.transitionDuration);if(!state.playing)return;await focusVisual(stage);if(!state.playing)return;if(stage.dataset.requiresInput==='true'){pause('Waiting for your input',false);return;}await wait(randomHold());if(!state.playing)return;if(state.step>=stages.length-1){state.complete=true;setPlaying(false);setStatus('Presentation complete');save();return;}state.step+=1;save();presentOnboarding();}
function runCurrent(){if(!state.playing)return;if(state.phase==='landing')presentLanding();else presentOnboarding();}
function continueOnboarding(){if(state.step<stages.length-1)state.step+=1;state.phase='onboarding';setPlaying(true);save();updateProgress();presentOnboarding();}
function skip(){if(state.skipped.indexOf(state.step)===-1)state.skipped.push(state.step);if(state.step===3&&!state.ideas.length)state.ideas=fallbackIdeas();if(state.step===4&&!state.selectedIdea)state.customProduct=state.customProduct||'Create a custom pre-sale product';continueOnboarding();}
async function scanWebsite(){var button=root.querySelector('[data-agentic-scan]'),box=root.querySelector('[data-agentic-scan-state]');if(!button||button.disabled)return;button.disabled=true;button.textContent='Scanning…';if(box){box.classList.add('is-visible');box.textContent='Reviewing public website content…';}try{var token=(document.querySelector('meta[name="csrf-token"]')||{}).content||'';var response=await fetch('/api/public/website-product-ideas.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':token},body:JSON.stringify({website:state.businessWebsite,business_name:state.businessName})});var data=await response.json();if(!response.ok||data.ok===false)throw new Error(data.error||'Website scan failed');var payload=data.data||data;state.ideas=Array.isArray(payload.ideas)&&payload.ideas.length?payload.ideas:fallbackIdeas();if(box)box.textContent='Scan complete. '+state.ideas.length+' product ideas are ready.';}catch(error){state.ideas=fallbackIdeas();if(box)box.textContent='Starter ideas are ready. You can continue.';}button.textContent='Scan website';button.disabled=false;renderIdeas();save();setTimeout(continueOnboarding,700);}
root.addEventListener('input',function(event){var el=event.target.closest('[data-agentic-field]');if(!el)return;state[el.dataset.agenticField]=el.value;pause('Presentation paused for your input',false);validateStage();});
root.addEventListener('click',function(event){var choice=event.target.closest('[data-choice-value]');if(choice){state.interest=choice.dataset.choiceValue;root.querySelectorAll('[data-choice-value]').forEach(function(btn){btn.classList.toggle('is-selected',btn===choice);});save();setTimeout(continueOnboarding,350);return;}var idea=event.target.closest('[data-agentic-idea]');if(idea){var ideas=state.ideas&&state.ideas.length?state.ideas:fallbackIdeas();state.selectedIdea=ideas[Number(idea.dataset.agenticIdea)]||null;if(state.selectedIdea)state.customProduct=state.selectedIdea.title+' — '+state.selectedIdea.description;renderIdeas();validateStage();return;}if(event.target.closest('[data-agentic-skip]')){skip();return;}if(event.target.closest('[data-agentic-next]')){continueOnboarding();return;}if(event.target.closest('[data-agentic-scan]')){scanWebsite();return;}if(event.target.closest('[data-agentic-custom]')){state.selectedIdea=null;state.customProduct='';state.step=5;save();hydrate();setPlaying(true);presentOnboarding();return;}if(event.target.closest('[data-agentic-restart]')){sessionStorage.removeItem(storageKey);state={playing:true,phase:'landing',landingIndex:0,step:0,interest:'',businessName:'',businessWebsite:'',ideas:[],selectedIdea:null,customProduct:'',skipped:[],complete:false};hydrate();runCurrent();}});
resumeButton.addEventListener('click',resume);
document.addEventListener('mg:index-presentation-toggle',function(){if(state.playing)pause('Presentation paused',true);else resume();});
document.addEventListener('mg:index-presentation-ready',emit);
function stopForUser(){if(internalScroll)return;var delta=Math.abs(window.scrollY-lastManualY);lastManualY=window.scrollY;if(delta>=AGENTIC_PRESENTATION_CONFIG.manualScrollPauseThreshold)pause('Presentation paused by manual scrolling',true);}
window.addEventListener('wheel',stopForUser,{passive:true});window.addEventListener('touchmove',stopForUser,{passive:true});
load();hydrate();setPlaying(!reduceMotion);setTimeout(runCurrent,ms(AGENTIC_PRESENTATION_CONFIG.initialStartDelay));
});
