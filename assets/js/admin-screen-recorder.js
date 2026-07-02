(function(){
  'use strict';
  const root=document.querySelector('[data-screen-recordings]');
  if(!root)return;
  const csrf=root.getAttribute('data-csrf-token')||'';
  const canManage=root.getAttribute('data-can-manage')==='1';
  const statusEl=root.querySelector('[data-recorder-status]');
  const startBtn=root.querySelector('[data-recorder-open]');
  const titleInput=root.querySelector('[data-recorder-title]');
  const micInput=root.querySelector('[data-recorder-mic]');
  const systemAudioInput=root.querySelector('[data-recorder-system-audio]');
  let mediaRecorder=null,displayStream=null,micStream=null,mixedStream=null,chunks=[],recording=null,startedAt=0,timer=null,dashWindow=null;

  function setStatus(message,type){if(!statusEl)return;statusEl.textContent=message;statusEl.classList.toggle('is-active',type==='active');statusEl.classList.toggle('is-error',type==='error');}
  function fmt(sec){sec=Math.max(0,Math.floor(sec));const m=Math.floor(sec/60),s=sec%60;return m+':'+String(s).padStart(2,'0');}
  async function api(url,options){const res=await fetch(url,options||{});const data=await res.json().catch(()=>({ok:false,message:'Invalid server response.'}));if(!res.ok||!data.ok)throw new Error(data.message||'Request failed.');return data.data||{};}
  function openDash(){
    dashWindow=window.open('','mgScreenRecorderDash','popup=yes,width=320,height=220,left=24,top=520');
    if(!dashWindow||dashWindow.closed){return false;}
    dashWindow.document.open();
    dashWindow.document.write('<!doctype html><html><head><title>Recorder Controls</title><style>body{margin:0;font-family:Arial,sans-serif;background:#111827;color:#fff}main{padding:14px}.dash{border:1px solid rgba(255,255,255,.18);border-radius:18px;padding:14px;background:#0b1120;box-shadow:0 24px 70px rgba(0,0,0,.35)}strong{display:block;font-size:18px;margin-bottom:4px}.time{font-size:32px;font-weight:900;letter-spacing:-.05em;margin:10px 0}.controls{display:flex;gap:8px;flex-wrap:wrap}button{border:0;border-radius:999px;padding:9px 12px;font-weight:900;cursor:pointer}.primary{background:#fff;color:#111827}.soft{background:#1f2937;color:#fff}.danger{background:#7f1d1d;color:#fff}p{color:#cbd5e1;font-size:12px;line-height:1.4;margin:8px 0 0}</style></head><body><main><section class="dash"><strong>Microgifter recorder</strong><span>Detached controller</span><div class="time" id="time">0:00</div><div class="controls"><button class="soft" id="pause">Pause</button><button class="soft" id="resume">Resume</button><button class="danger" id="stop">Stop & save</button></div><p>Keep this controller outside the tab/window being captured for a clean recording.</p></section><script>function send(a){window.opener&&window.opener.postMessage({source:"mg-recorder-dash",action:a},"*")}document.getElementById("pause").onclick=()=>send("pause");document.getElementById("resume").onclick=()=>send("resume");document.getElementById("stop").onclick=()=>send("stop");window.addEventListener("message",e=>{if(e.data&&e.data.source==="mg-recorder-main"&&e.data.time){document.getElementById("time").textContent=e.data.time}});</script></main></body></html>');
    dashWindow.document.close();
    return true;
  }
  function dashMessage(payload){try{if(dashWindow&&!dashWindow.closed)dashWindow.postMessage(Object.assign({source:'mg-recorder-main'},payload),'*');}catch(e){}}
  function closeDash(){try{if(dashWindow&&!dashWindow.closed)dashWindow.close();}catch(e){}dashWindow=null;}
  async function createSession(mimeType){
    const title=(titleInput&&titleInput.value.trim())||('Admin screen recording '+new Date().toLocaleString());
    return api('/api/admin/screen-recordings/create-session.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify({csrf_token:csrf,title:title,mime_type:mimeType,capture_surface:'screen_or_tab'})});
  }
  async function buildMixedStream(){
    const displayAudio=!!(systemAudioInput&&systemAudioInput.checked);
    displayStream=await navigator.mediaDevices.getDisplayMedia({video:{frameRate:30,displaySurface:'browser'},audio:displayAudio});
    const tracks=[...displayStream.getVideoTracks()];
    const audioTracks=[];
    if(displayStream.getAudioTracks().length)audioTracks.push(...displayStream.getAudioTracks());
    if(micInput&&micInput.checked){
      try{micStream=await navigator.mediaDevices.getUserMedia({audio:true,video:false});if(micStream.getAudioTracks().length)audioTracks.push(...micStream.getAudioTracks());}catch(e){setStatus('Microphone was not available. Recording display audio/video only.','active');}
    }
    if(audioTracks.length>1&&window.AudioContext){
      const ctx=new AudioContext();
      const dest=ctx.createMediaStreamDestination();
      audioTracks.forEach(track=>{const source=ctx.createMediaStreamSource(new MediaStream([track]));source.connect(dest);});
      tracks.push(...dest.stream.getAudioTracks());
    }else if(audioTracks.length===1){tracks.push(audioTracks[0]);}
    mixedStream=new MediaStream(tracks);
    mixedStream.getVideoTracks().forEach(track=>track.addEventListener('ended',()=>stopRecording()));
    return mixedStream;
  }
  function cleanupStreams(){[displayStream,micStream,mixedStream].forEach(stream=>{if(stream)stream.getTracks().forEach(track=>track.stop());});displayStream=null;micStream=null;mixedStream=null;}
  async function startRecording(){
    if(!canManage){setStatus('You do not have permission to create recordings.','error');return;}
    if(!navigator.mediaDevices||!navigator.mediaDevices.getDisplayMedia){setStatus('This browser does not support screen recording.','error');return;}
    try{
      chunks=[];
      const detached=openDash();
      if(!detached){setStatus('Popup blocked. Enable popups so the controller can stay outside the capture area.','error');}
      const stream=await buildMixedStream();
      const mime=MediaRecorder.isTypeSupported('video/webm;codecs=vp9,opus')?'video/webm;codecs=vp9,opus':(MediaRecorder.isTypeSupported('video/webm;codecs=vp8,opus')?'video/webm;codecs=vp8,opus':'video/webm');
      const session=await createSession('video/webm');
      recording=session.recording;
      mediaRecorder=new MediaRecorder(stream,{mimeType:mime});
      mediaRecorder.ondataavailable=e=>{if(e.data&&e.data.size>0)chunks.push(e.data);};
      mediaRecorder.onstop=saveRecording;
      mediaRecorder.start(1000);
      startedAt=Date.now();
      timer=setInterval(()=>{const t=fmt((Date.now()-startedAt)/1000);dashMessage({time:t});setStatus('Recording… '+t,'active');},500);
      setStatus('Recording started. Use the detached controller to pause, resume, or stop.','active');
      if(startBtn)startBtn.disabled=true;
    }catch(e){cleanupStreams();closeDash();setStatus(e.message||'Unable to start recording.','error');if(startBtn)startBtn.disabled=false;}
  }
  function pauseRecording(){if(mediaRecorder&&mediaRecorder.state==='recording'){mediaRecorder.pause();setStatus('Recording paused.','active');}}
  function resumeRecording(){if(mediaRecorder&&mediaRecorder.state==='paused'){mediaRecorder.resume();setStatus('Recording resumed.','active');}}
  function stopRecording(){if(mediaRecorder&&(mediaRecorder.state==='recording'||mediaRecorder.state==='paused')){mediaRecorder.stop();}else{cleanupStreams();closeDash();if(startBtn)startBtn.disabled=false;}}
  async function saveRecording(){
    clearInterval(timer);timer=null;cleanupStreams();closeDash();
    if(!recording||!recording.id){setStatus('Recording stopped, but no session was available.','error');if(startBtn)startBtn.disabled=false;return;}
    try{
      setStatus('Saving recording…','active');
      const blob=new Blob(chunks,{type:'video/webm'});
      const fd=new FormData();
      fd.append('csrf_token',csrf);fd.append('recording_id',String(recording.id));fd.append('duration_seconds',String((Date.now()-startedAt)/1000));fd.append('video_file',blob,((recording.title||'screen-recording').toLowerCase().replace(/[^a-z0-9]+/g,'-')||'screen-recording')+'.webm');
      const res=await fetch('/api/admin/screen-recordings/finalize.php',{method:'POST',headers:{'X-CSRF-Token':csrf},body:fd});
      const data=await res.json();
      if(!res.ok||!data.ok)throw new Error(data.message||'Unable to save recording.');
      setStatus('Recording saved to admin library.','active');
      window.dispatchEvent(new CustomEvent('mg-screen-recording-saved',{detail:data.data}));
    }catch(e){setStatus(e.message||'Unable to save recording.','error');}
    if(startBtn)startBtn.disabled=false;
  }
  window.addEventListener('message',event=>{if(!event.data||event.data.source!=='mg-recorder-dash')return;if(event.data.action==='pause')pauseRecording();if(event.data.action==='resume')resumeRecording();if(event.data.action==='stop')stopRecording();});
  if(startBtn)startBtn.addEventListener('click',startRecording);
})();
