document.addEventListener('DOMContentLoaded',function(){
'use strict';
var root=document.querySelector('[data-customer-profile-page]');
if(!root)return;
root.querySelectorAll('[data-profile-tab]').forEach(function(tab){
  tab.addEventListener('click',function(){
    root.querySelectorAll('[data-profile-tab]').forEach(function(btn){btn.classList.remove('is-active');});
    tab.classList.add('is-active');
  });
});
root.querySelectorAll('.mg-cp-actions button,.mg-cp-card-head button').forEach(function(button){
  button.addEventListener('click',function(){
    var original=button.textContent;
    button.textContent='Pass 2 action';
    window.setTimeout(function(){button.textContent=original;},1200);
  });
});
});
