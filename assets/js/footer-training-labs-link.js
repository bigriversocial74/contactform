document.addEventListener('DOMContentLoaded',function(){
  if(!document.body||document.body.dataset.authenticated==='true')return;
  var columns=document.querySelectorAll('.mg-footer-column');
  for(var i=0;i<columns.length;i++){
    var h=columns[i].querySelector('h2');
    if(!h||h.textContent.trim()!=='Workspace')continue;
    if(columns[i].querySelector('a[href="/examples/labs/"]'))return;
    var a=document.createElement('a');
    a.href='/examples/labs/';
    a.textContent='Training Labs';
    var agent=columns[i].querySelector('a[href="/agent.php"]');
    if(agent&&agent.nextSibling)columns[i].insertBefore(a,agent.nextSibling);else columns[i].appendChild(a);
    return;
  }
});
