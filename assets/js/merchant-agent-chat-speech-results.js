document.addEventListener('DOMContentLoaded',function(){
  'use strict';
  var root=document.querySelector('[data-merchant-agent-chat]');
  if(!root)return;
  var feed=root.querySelector('[data-agent-chat-feed]');
  var toggle=root.querySelector('[data-agent-speak-results]');
  var stop=root.querySelector('[data-agent-speech-stop]');
  var note=root.querySelector('[data-agent-speech-status]');
  var reader=window.speechSynthesis;
  var key='mgMerchantAgentSpeakResults';
  var initialized=false;
  var lastText='';
  var timer=null;
  if(!feed||!toggle)return;
  function text(value){return String(value||'').replace(/\s+/g,' ').trim();}
  function setNote(value){if(note)note.textContent=value||'';}
  function stopReader(){if(reader)reader.cancel();if(stop)stop.hidden=true;if(toggle.checked)setNote('Spoken results enabled.');}
  function latestAgentResult(){
    var messages=Array.from(feed.querySelectorAll('.mg-agent-chat-message.is-agent:not(.is-pending):not(.is-error)'));
    if(!messages.length)return '';
    var message=messages[messages.length-1];
    var parts=[];
    message.querySelectorAll('.mg-agent-chat-bubble>p,.mg-agent-block-head strong,.mg-agent-block-head span,.mg-agent-chat-card strong,.mg-agent-chat-card p').forEach(function(node){
      var value=text(node.textContent);
      if(value)parts.push(value);
    });
    return text(parts.join('. '));
  }
  function read(value){
    value=text(value).slice(0,3600);
    if(!value||!reader)return;
    reader.cancel();
    var utterance=new SpeechSynthesisUtterance(value);
    utterance.lang=document.documentElement.lang||navigator.language||'en-US';
    utterance.rate=.96;
    utterance.onstart=function(){if(stop)stop.hidden=false;setNote('Reading agent result…');};
    utterance.onend=function(){if(stop)stop.hidden=true;setNote('Spoken results enabled.');};
    utterance.onerror=function(){if(stop)stop.hidden=true;setNote('Spoken results enabled.');};
    reader.speak(utterance);
  }
  function check(){
    window.clearTimeout(timer);
    timer=window.setTimeout(function(){
      var value=latestAgentResult();
      if(!value)return;
      if(!initialized){initialized=true;lastText=value;return;}
      if(value===lastText)return;
      lastText=value;
      if(toggle.checked)read(value);
    },240);
  }
  if(!reader){toggle.disabled=true;setNote('Spoken results are not supported by this browser.');return;}
  toggle.checked=localStorage.getItem(key)==='1';
  setNote(toggle.checked?'Spoken results enabled.':'Agent replies will read aloud after each result.');
  toggle.addEventListener('change',function(){
    localStorage.setItem(key,toggle.checked?'1':'0');
    if(toggle.checked){
      setNote('Spoken results enabled.');
      var value=latestAgentResult();
      if(value){lastText=value;initialized=true;read(value);}
    }else{
      stopReader();
      setNote('Agent replies will read aloud after each result.');
    }
  });
  if(stop)stop.addEventListener('click',stopReader);
  new MutationObserver(check).observe(feed,{childList:true,subtree:true});
  window.setTimeout(check,500);
});
