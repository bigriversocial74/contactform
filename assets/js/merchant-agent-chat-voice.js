document.addEventListener('DOMContentLoaded',function(){
  'use strict';

  var root=document.querySelector('[data-merchant-agent-chat]');
  if(!root)return;

  var button=root.querySelector('[data-agent-chat-voice]');
  var textarea=root.querySelector('[data-agent-chat-textarea],textarea[name="message"]');
  var status=root.querySelector('[data-agent-chat-status]');
  var Recognition=window.SpeechRecognition||window.webkitSpeechRecognition;
  if(!button||!textarea)return;

  function setStatus(message,type){
    if(!status)return;
    status.textContent=message||'';
    status.className='mg-form-status'+(type?' is-'+type:'');
  }

  function notifyInput(){
    textarea.dispatchEvent(new Event('input',{bubbles:true}));
  }

  function setButton(listening){
    button.classList.toggle('is-listening',!!listening);
    button.setAttribute('aria-pressed',listening?'true':'false');
    button.setAttribute('aria-label',listening?'Stop voice input':'Start voice input');
    button.textContent=listening?'Stop':'Mic';
  }

  if(!Recognition){
    button.disabled=true;
    button.classList.add('is-unsupported');
    button.setAttribute('aria-hidden','true');
    button.title='Voice input is not supported by this browser.';
    return;
  }

  var recognition=new Recognition();
  var listening=false;
  var baseText='';
  recognition.continuous=true;
  recognition.interimResults=true;
  recognition.lang=document.documentElement.lang||navigator.language||'en-US';

  function joinVoiceText(finalText,interimText){
    var parts=[];
    if(baseText)parts.push(baseText);
    if(finalText)parts.push(finalText);
    if(interimText)parts.push(interimText);
    return parts.join(parts.length>1?' ':'').replace(/\s+/g,' ').trim();
  }

  recognition.onstart=function(){
    listening=true;
    setButton(true);
    setStatus('Listening… speak your message, then tap Stop or Send.', '');
  };

  recognition.onresult=function(event){
    var finalText='';
    var interimText='';
    for(var i=event.resultIndex;i<event.results.length;i+=1){
      var transcript=String(event.results[i][0].transcript||'').trim();
      if(!transcript)continue;
      if(event.results[i].isFinal)finalText+=(finalText?' ':'')+transcript;
      else interimText+=(interimText?' ':'')+transcript;
    }
    if(finalText){
      baseText=joinVoiceText(finalText,'');
    }
    textarea.value=joinVoiceText('',interimText);
    textarea.focus();
    notifyInput();
  };

  recognition.onerror=function(event){
    var code=String(event&&event.error||'');
    var message='Voice input stopped.';
    if(code==='not-allowed'||code==='service-not-allowed')message='Microphone permission is blocked. Allow microphone access to use voice input.';
    else if(code==='no-speech')message='No speech detected. Tap Mic and try again.';
    else if(code==='network')message='Voice input needs a working browser speech service.';
    setStatus(message,code==='no-speech'?'':'error');
  };

  recognition.onend=function(){
    listening=false;
    setButton(false);
  };

  button.addEventListener('click',function(){
    if(textarea.disabled)return;
    if(listening){
      recognition.stop();
      setStatus('Voice input added. Review or send your message.', 'success');
      return;
    }
    baseText=textarea.value.trim();
    try{
      recognition.start();
    }catch(error){
      setStatus('Voice input is already starting. Speak now or tap Stop.', '');
    }
  });

  root.addEventListener('submit',function(){
    if(listening)recognition.stop();
  },true);
});
