(() => {
  'use strict';
  const root=document.querySelector('[data-design-content-calendar]');
  if(!root)return;
  const form=root.querySelector('[data-calendar-generator]');
  const list=root.querySelector('[data-calendar-product-list]');
  const count=root.querySelector('[data-calendar-product-count]');
  if(!form||!list)return;

  let search='';
  let status='all';
  let selectedOnly=false;
  let frame=0;

  const tools=document.createElement('div');
  tools.className='mg-calendar-product-tools';
  tools.innerHTML='<label class="mg-calendar-product-search"><span class="sr-only">Search products</span><input type="search" placeholder="Search products" autocomplete="off" data-calendar-product-search></label>'+
    '<label class="mg-calendar-product-status-filter"><span class="sr-only">Filter products by status</span><select data-calendar-product-status><option value="all">All statuses</option><option value="published">Published</option><option value="draft">Draft</option></select></label>'+
    '<button type="button" data-calendar-product-selected-only aria-pressed="false">Selected only</button>'+
    '<button type="button" data-calendar-select-published>Select published</button>'+
    '<button type="button" data-calendar-clear-product-selection>Clear selection</button>';
  list.insertAdjacentElement('beforebegin',tools);

  const summary=document.createElement('div');
  summary.className='mg-calendar-selection-summary';
  summary.innerHTML='<strong data-calendar-selection-summary>0 products selected</strong><div><button type="button" data-calendar-review-selection aria-pressed="false">Review selected</button><button type="button" data-calendar-clear-selection-summary>Clear</button></div>';
  list.insertAdjacentElement('afterend',summary);

  const searchInput=tools.querySelector('[data-calendar-product-search]');
  const statusSelect=tools.querySelector('[data-calendar-product-status]');
  const selectedButton=tools.querySelector('[data-calendar-product-selected-only]');
  const reviewButton=summary.querySelector('[data-calendar-review-selection]');
  const summaryText=summary.querySelector('[data-calendar-selection-summary]');

  const text=(value)=>String(value==null?'':value).trim();
  const rows=()=>Array.from(list.querySelectorAll('.mg-design-calendar-product-option'));
  const inputFor=(row)=>row.querySelector('input[name="product_ids[]"]');
  const rowStatus=(row)=>text(row.querySelector('.mg-calendar-product-card-state em, em')?.textContent).toLowerCase();
  const rowText=(row)=>[
    row.querySelector('.mg-calendar-product-card-source')?.textContent,
    row.querySelector('.mg-calendar-product-card-copy strong, strong')?.textContent,
    row.querySelector('.mg-calendar-product-card-copy small, small')?.textContent,
    row.querySelector('.mg-calendar-product-card-meta')?.textContent,
    inputFor(row)?.value
  ].map(text).join(' ').toLowerCase();

  function selectedCount(name){
    return form.querySelectorAll('input[name="'+name+'[]"]:checked').length;
  }

  function updateSummary(){
    const productTotal=rows().filter((row)=>inputFor(row)?.checked).length;
    const formats=selectedCount('formats');
    const layouts=selectedCount('layouts');
    const themes=selectedCount('themes');
    summaryText.textContent=productTotal+' product'+(productTotal===1?'':'s')+' selected · '+formats+' format'+(formats===1?'':'s')+' · '+layouts+' layout'+(layouts===1?'':'s')+' · '+themes+' theme'+(themes===1?'':'s');
    [selectedButton,reviewButton].forEach((button)=>{
      button.classList.toggle('is-active',selectedOnly);
      button.setAttribute('aria-pressed',selectedOnly?'true':'false');
    });
  }

  function apply(){
    frame=0;
    const all=rows();
    let shown=0;
    all.forEach((row)=>{
      const box=inputFor(row);
      const visible=(!search||rowText(row).includes(search))&&(status==='all'||rowStatus(row).includes(status))&&(!selectedOnly||!!box?.checked);
      row.hidden=!visible;
      if(visible)shown+=1;
    });
    if(count)count.textContent=shown+' shown · '+all.length+' product'+(all.length===1?'':'s');
    updateSummary();
  }

  function schedule(){
    if(frame)return;
    frame=requestAnimationFrame(apply);
  }

  function setSelectedOnly(value){
    selectedOnly=!!value;
    schedule();
  }

  function clearSelection(){
    rows().forEach((row)=>{
      const box=inputFor(row);
      if(!box)return;
      box.checked=false;
      box.dispatchEvent(new Event('change',{bubbles:true}));
    });
    schedule();
  }

  searchInput.addEventListener('input',()=>{search=text(searchInput.value).toLowerCase();schedule();});
  statusSelect.addEventListener('change',()=>{status=text(statusSelect.value).toLowerCase()||'all';schedule();});
  selectedButton.addEventListener('click',()=>setSelectedOnly(!selectedOnly));
  reviewButton.addEventListener('click',()=>setSelectedOnly(!selectedOnly));

  tools.querySelector('[data-calendar-select-published]').addEventListener('click',()=>{
    rows().forEach((row)=>{
      const box=inputFor(row);
      if(!box||!rowStatus(row).includes('published')||box.checked)return;
      box.checked=true;
      box.dispatchEvent(new Event('change',{bubbles:true}));
    });
    schedule();
  });

  tools.querySelector('[data-calendar-clear-product-selection]').addEventListener('click',clearSelection);
  summary.querySelector('[data-calendar-clear-selection-summary]').addEventListener('click',clearSelection);
  form.addEventListener('change',schedule);
  new MutationObserver(schedule).observe(list,{childList:true,subtree:true});
  schedule();
})();