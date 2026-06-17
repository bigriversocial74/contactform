(function(window,document){
'use strict';

var STATES={IDLE:'idle',PLAYING:'playing',PAUSED:'paused',WAITING:'waiting_for_input',COMPLETED:'completed',REPLAYING:'replaying'};
function readJson(id,fallback){var node=document.getElementById(id);if(!node)return fallback;try{return JSON.parse(node.textContent||'');}catch(error){console.error('[agent-presentation] invalid '+id,error);return fallback;}}
function clamp(value,min,max){return Math.max(min,Math.min(max,value));}
function wait(ms){return new Promise(function(resolve){window.setTimeout(resolve,ms);});}
function prefersReducedMotion(){return window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;}

function AgentPresentation(config){this.config=config||{};this.state=STATES.IDLE;this.index=0;this.timer=null;this.abortToken=0;this.sections=[];this.control=null;this.live=null;}
AgentPresentation.prototype.init=function(){
  if(!this.config.enabled)return;
  var selector=this.config.selector||'[data-agent-section]';
  this.sections=Array.from(document.querySelectorAll(selector));
  if(!this.sections.length)return;
  this.sections.forEach(function(section){
    section.classList.add('mg-agent-section');
    var visual=section.querySelector('[data-agent-visual],.lm-agent-card,.mg-auth-card,.mg-auth-aside');
    if(visual)visual.setAttribute('data-agent-visual','');
    else section.setAttribute('data-agent-visual','');
    if(!section.querySelector('[data-agent-pin]'))section.classList.add('mg-agent-direct');
  });
  document.documentElement.dataset.agentState=this.state;
  document.body.setAttribute('data-agent-page',this.config.page||'public');
  this.control=document.querySelector('[data-agent-presentation-control]');
  this.live=document.createElement('div');
  this.live.className='mg-agent-live';
  this.live.setAttribute('aria-live','polite');
  document.body.appendChild(this.live);
  this.bind();
  this.setState(this.config.autoplay===false?STATES.PAUSED:STATES.PLAYING);
  if(this.state===STATES.PLAYING)this.playCurrent();
};
AgentPresentation.prototype.bind=function(){
  var self=this;
  if(this.control)this.control.addEventListener('click',function(){if(self.state===STATES.COMPLETED){self.replay();return;}if(self.state===STATES.PLAYING){self.pause();return;}self.resume();});
  document.addEventListener('click',function(event){var next=event.target.closest('[data-agent-next]');var back=event.target.closest('[data-agent-back]');var replay=event.target.closest('[data-agent-replay]');if(next){event.preventDefault();if(self.validateCurrent())self.go(self.index+1,true);}if(back){event.preventDefault();self.go(self.index-1,true);}if(replay){event.preventDefault();self.replay();}});
  ['wheel','touchstart'].forEach(function(name){window.addEventListener(name,function(){if(self.state===STATES.PLAYING)self.pause();},{passive:true});});
  document.addEventListener('keydown',function(event){if(['ArrowDown','ArrowUp','PageDown','PageUp','Home','End',' '].indexOf(event.key)!==-1&&self.state===STATES.PLAYING)self.pause();});
};
AgentPresentation.prototype.setState=function(next){
  this.state=next;document.documentElement.dataset.agentState=next;
  if(this.control){var label=this.control.querySelector('[data-agent-control-label]');var icon=this.control.querySelector('[data-agent-control-icon]');var text=next===STATES.PLAYING?'Pause':next===STATES.COMPLETED?'Replay':'Play';if(label)label.textContent=text;if(icon)icon.textContent=next===STATES.PLAYING?'Ⅱ':next===STATES.COMPLETED?'↻':'▶';this.control.dataset.state=next;this.control.setAttribute('aria-label',text+' agent presentation');}
};
AgentPresentation.prototype.sectionConfig=function(index){var section=this.sections[index];var configured=(this.config.sections||[])[index]||{};return Object.assign({type:section&&section.dataset.agentType||'content',minReadMs:2200,maxReadMs:3600,waitForUser:false,focusMs:650},configured);};
AgentPresentation.prototype.validateCurrent=function(){var section=this.sections[this.index];if(!section)return true;var field=section.querySelector('input[required],select[required],textarea[required]');if(!field||field.checkValidity())return true;field.reportValidity();field.focus({preventScroll:true});return false;};
AgentPresentation.prototype.go=function(index,userDriven){this.abortToken+=1;window.clearTimeout(this.timer);this.index=clamp(index,0,this.sections.length-1);if(userDriven)this.setState(STATES.PAUSED);this.playCurrent(userDriven);};
AgentPresentation.prototype.scrollToSection=function(section){var header=document.querySelector('[data-mg-universal-header]');var offset=header?header.offsetHeight:76;var top=section.getBoundingClientRect().top+window.scrollY-offset;window.scrollTo({top:Math.max(0,top),behavior:prefersReducedMotion()?'auto':'smooth'});};
AgentPresentation.prototype.activate=function(section){this.sections.forEach(function(item){item.classList.remove('is-agent-active','is-agent-focus');item.style.setProperty('--agent-progress','0');});section.classList.add('is-agent-active');section.style.setProperty('--agent-progress','0.55');var title=section.querySelector('h1,h2,h3');if(this.live&&title)this.live.textContent=title.textContent||'';};
AgentPresentation.prototype.playCurrent=async function(userDriven){
  var token=++this.abortToken;var section=this.sections[this.index];if(!section)return;var cfg=this.sectionConfig(this.index);this.activate(section);this.scrollToSection(section);await wait(prefersReducedMotion()?0:Math.min(800,Number(this.config.scrollDurationMs||700)));if(token!==this.abortToken)return;section.classList.add('is-agent-focus');section.style.setProperty('--agent-progress','1');
  if(cfg.waitForUser||cfg.type==='input'||cfg.type==='questionnaire'){this.setState(STATES.WAITING);var field=section.querySelector('input,select,textarea');if(field)window.setTimeout(function(){field.focus({preventScroll:true});},120);return;}
  if(userDriven||this.state!==STATES.PLAYING)return;var min=Number(cfg.minReadMs||2200),max=Math.max(min,Number(cfg.maxReadMs||min));var hold=min+Math.floor(Math.random()*(max-min+1))+Number(cfg.focusMs||0);var self=this;this.timer=window.setTimeout(function(){if(self.index>=self.sections.length-1){self.complete();return;}self.index+=1;self.playCurrent();},prefersReducedMotion()?250:hold);
};
AgentPresentation.prototype.pause=function(){this.abortToken+=1;window.clearTimeout(this.timer);this.setState(STATES.PAUSED);};
AgentPresentation.prototype.resume=function(){if(this.state===STATES.COMPLETED){this.replay();return;}this.setState(STATES.PLAYING);this.playCurrent();};
AgentPresentation.prototype.complete=function(){this.abortToken+=1;window.clearTimeout(this.timer);this.setState(STATES.COMPLETED);document.dispatchEvent(new CustomEvent('mg:agent-complete',{detail:{page:this.config.page||''}}));};
AgentPresentation.prototype.replay=function(){this.abortToken+=1;window.clearTimeout(this.timer);this.index=0;this.setState(STATES.REPLAYING);this.sections.forEach(function(section){section.classList.remove('is-agent-active','is-agent-focus');section.style.setProperty('--agent-progress','0');});var self=this;window.scrollTo({top:0,behavior:prefersReducedMotion()?'auto':'smooth'});window.setTimeout(function(){self.setState(STATES.PLAYING);self.playCurrent();},prefersReducedMotion()?0:650);};
function boot(){var config=readJson('mg-page-onboarding',{enabled:false});if(!config.enabled)return;var engine=new AgentPresentation(config);window.MicrogifterAgentPresentation=engine;engine.init();}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot);else boot();
})(window,document);
