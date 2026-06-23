(function(){
  'use strict';
  function qs(scope, sel){return (scope||document).querySelector(sel);}
  function qsa(scope, sel){return Array.prototype.slice.call((scope||document).querySelectorAll(sel));}
  function setText(node, text){if(node)node.textContent=text;}
  function fillGeo(form, pos){
    if(!form||!pos||!pos.coords)return;
    var lat=qs(form,'[name="geo_lat"]'),lng=qs(form,'[name="geo_lng"]'),acc=qs(form,'[name="geo_accuracy"]'),ts=qs(form,'[name="geo_captured_at"]');
    if(lat)lat.value=String(pos.coords.latitude);
    if(lng)lng.value=String(pos.coords.longitude);
    if(acc)acc.value=String(pos.coords.accuracy||'');
    if(ts)ts.value=new Date().toISOString();
  }
  function captureGeo(target){
    var form=target.closest('form')||document;
    var box=target.closest('[data-lq-geo-box]')||form;
    var result=qs(box,'[data-lq-geo-result]');
    if(!navigator.geolocation){setText(result,'Geolocation is not available in this browser.');return;}
    setText(result,'Requesting location…');
    navigator.geolocation.getCurrentPosition(function(pos){
      fillGeo(form,pos);
      setText(result,'Captured '+pos.coords.latitude.toFixed(6)+', '+pos.coords.longitude.toFixed(6)+' ± '+Math.round(pos.coords.accuracy||0)+'m');
    },function(err){setText(result,err.message||'Unable to capture location.');},{enableHighAccuracy:true,timeout:12000,maximumAge:30000});
  }
  async function startQr(target){
    var box=target.closest('[data-lq-qr-box]')||document;
    var form=target.closest('form')||document;
    var video=qs(box,'video');
    var result=qs(box,'[data-lq-qr-result]');
    var qrInput=qs(form,'[name="qr_payload"]');
    var manual=qs(box,'[data-lq-qr-manual]');
    if(manual&&manual.value&&qrInput){qrInput.value=manual.value;setText(result,'QR/manual payload captured.');return;}
    if(!('BarcodeDetector' in window)||!navigator.mediaDevices||!navigator.mediaDevices.getUserMedia){setText(result,'Camera QR scanning is not available here. Paste a code manually.');return;}
    try{
      var detector=new BarcodeDetector({formats:['qr_code']});
      var stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'}});
      video.srcObject=stream;video.style.display='block';await video.play();
      setText(result,'Scanning…');
      var stopped=false;
      var stop=function(){if(stopped)return;stopped=true;stream.getTracks().forEach(function(t){t.stop();});video.pause();video.style.display='none';};
      var tick=async function(){
        if(stopped)return;
        try{
          var codes=await detector.detect(video);
          if(codes&&codes.length){
            var value=codes[0].rawValue||'';
            if(qrInput)qrInput.value=value;
            if(manual)manual.value=value;
            setText(result,'QR captured: '+value);
            stop();return;
          }
        }catch(e){}
        requestAnimationFrame(tick);
      };
      tick();
      setTimeout(function(){if(!stopped){setText(result,'Still scanning. Hold the QR code steady or paste it manually.');}},8000);
    }catch(err){setText(result,err.message||'Unable to start camera. Paste a code manually.');}
  }
  document.addEventListener('click',function(e){
    var geo=e.target.closest('[data-lq-capture-geo]');
    if(geo){e.preventDefault();captureGeo(geo);return;}
    var qr=e.target.closest('[data-lq-start-qr]');
    if(qr){e.preventDefault();startQr(qr);}
  });
})();
