document.addEventListener('DOMContentLoaded',function(){
'use strict';
var input=document.querySelector('[data-agent-global-search]');
if(!input)return;
function filterRows(){var query=input.value.trim().toLowerCase();document.querySelectorAll('[data-gift-list] .mg-gift-row').forEach(function(row){row.hidden=query!==''&&!row.textContent.toLowerCase().includes(query);});}
input.addEventListener('input',filterRows);
var list=document.querySelector('[data-gift-list]');
if(list)new MutationObserver(filterRows).observe(list,{childList:true,subtree:true});
});
