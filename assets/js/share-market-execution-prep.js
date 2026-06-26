window.Microgifter=window.Microgifter||{};
(function(document){
'use strict';
function ensurePasswordField(){
  var form=document.querySelector('[data-share-execution-form]');
  if(!form||form.elements.password)return;
  var wrap=document.createElement('div');
  wrap.className='sm-approval-field';
  wrap.innerHTML='<label for="sm-execution-password">Fresh password verification</label><input id="sm-execution-password" name="password" type="password" autocomplete="current-password" required>';
  var button=form.querySelector('button[type="submit"]');
  form.insertBefore(wrap,button||null);
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',ensurePasswordField);else ensurePasswordField();
})(document);
