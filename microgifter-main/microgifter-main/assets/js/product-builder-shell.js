document.addEventListener('DOMContentLoaded',function(){
'use strict';
var root=document.querySelector('[data-builder-app]');
if(!root)return;
var sidebar=root.querySelector('[data-builder-sidebar]');
var close=root.querySelector('[data-builder-sidebar-close]');
var backdrop=root.querySelector('[data-builder-sidebar-backdrop]');
var label=root.querySelector('[data-preview-template-label]');
var card=root.querySelector('[data-builder-card]');
var labels={simple_product:'Simple product',greeting_card:'Greeting card',multimedia_greeting_card:'Multi-media greeting card',simple_collab:'Simple collab'};
function setSidebar(open){if(!sidebar)return;sidebar.classList.toggle('is-open',open);if(backdrop)backdrop.hidden=!open;document.body.classList.toggle('mg-builder-menu-open',open);}
function syncTemplate(){var selected=root.querySelector('input[name="builder_type"]:checked');var type=selected?selected.value:'simple_product';root.dataset.activeTemplate=type;if(label)label.textContent=labels[type]||'Product template';}
function setDevice(device){document.querySelectorAll('[data-device]').forEach(function(button){button.classList.toggle('is-active',button.dataset.device===device);});if(card)card.classList.toggle('is-mobile',device==='mobile');}
if(close)close.addEventListener('click',function(){setSidebar(false);});
if(backdrop)backdrop.addEventListener('click',function(){setSidebar(false);});
document.querySelectorAll('[data-device]').forEach(function(button){button.addEventListener('click',function(event){event.preventDefault();setDevice(button.dataset.device||'desktop');});});
root.querySelectorAll('input[name="builder_type"]').forEach(function(input){input.addEventListener('change',syncTemplate);});
root.querySelectorAll('[data-builder-step]').forEach(function(button){button.addEventListener('click',function(){if(window.innerWidth<=900)setSidebar(false);});});
document.addEventListener('keydown',function(event){if(event.key==='Escape')setSidebar(false);});
window.addEventListener('resize',function(){if(window.innerWidth>900)setSidebar(false);});
syncTemplate();
var activeDevice=document.querySelector('[data-device].is-active');
setDevice(activeDevice&&activeDevice.dataset.device?activeDevice.dataset.device:'desktop');
});