(function(){
  'use strict';
  var key = 'mg_scanner_device_id_v1';
  function uuid(){
    if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g,function(c){var r=Math.random()*16|0,v=c==='x'?r:(r&3|8);return v.toString(16);});
  }
  function deviceId(){
    try { var id = localStorage.getItem(key); if (!id) { id = uuid(); localStorage.setItem(key, id); } return id; }
    catch (e) { return uuid(); }
  }
  function label(){
    var ua = navigator.userAgent || 'Browser';
    if (/iPhone|iPad/i.test(ua)) return 'iOS scanner';
    if (/Android/i.test(ua)) return 'Android scanner';
    if (/Chrome/i.test(ua)) return 'Chrome scanner';
    if (/Safari/i.test(ua)) return 'Safari scanner';
    return 'Merchant scanner';
  }
  function patch(){
    if (!window.Microgifter || typeof window.Microgifter.post !== 'function' || window.Microgifter.__scannerDevicePatched) return false;
    var original = window.Microgifter.post;
    window.Microgifter.post = function(url, payload){
      if (String(url || '').indexOf('/api/merchant/scanner-claim-trust.php') !== -1 && payload && typeof payload === 'object') {
        payload.scanner_device_id = payload.scanner_device_id || deviceId();
        payload.scanner_device_label = payload.scanner_device_label || label();
      }
      return original.apply(this, arguments);
    };
    window.Microgifter.__scannerDevicePatched = true;
    return true;
  }
  if (!patch()) {
    var tries = 0;
    var timer = setInterval(function(){ tries++; if (patch() || tries > 40) clearInterval(timer); }, 100);
  }
})();
