(() => {
  'use strict';
  const root=document.querySelector('[data-design-content-calendar]');
  if(!root)return;
  const side=root.querySelector('[data-calendar-side]');
  const toggle=root.querySelector('.mg-design-calendar-view-toggle');
  if(!side||!toggle)return;

  const VIEW_KEY='microgifter.designCalendar.view';
  const DENSITY_KEY='microgifter.designCalendar.sideDensity';
  const views=new Set(['grid','stack','side']);
  const densities=new Set(['compact','standard','large']);
  let frame=0;

  function read(key,fallback){
    try{return localStorage.getItem(key)||fallback;}catch(_){return fallback;}
  }
  function write(key,value){
    try{localStorage.setItem(key,value);}catch(_){}
  }

  const controls=document.createElement('span');
  controls.className='mg-calendar-side-density';
  controls.hidden=true;
  controls.setAttribute('data-calendar-side-density','');
  controls.setAttribute('role','group');
  controls.setAttribute('aria-label','Side-by-side density');
  controls.innerHTML='<button type="button" data-calendar-density="compact" aria-pressed="false">Compact</button><button type="button" data-calendar-density="standard" aria-pressed="false">Standard</button><button type="button" data-calendar-density="large" aria-pressed="false">Large</button>';
  toggle.appendChild(controls);

  function equalize(){
    cancelAnimationFrame(frame);
    frame=requestAnimationFrame(()=>{
      const cards=Array.from(side.querySelectorAll('.mg-calendar-side-card'));
      cards.forEach((card)=>{card.style.minHeight='';});
      const rows=new Map();
      cards.forEach((card)=>{
        const top=Math.round(card.offsetTop);
        if(!rows.has(top))rows.set(top,[]);
        rows.get(top).push(card);
      });
      rows.forEach((group)=>{
        const tallest=Math.max(...group.map((card)=>card.offsetHeight));
        group.forEach((card)=>{card.style.minHeight=tallest+'px';});
      });
    });
  }

  function setDensity(value,persist=true){
    const density=densities.has(value)?value:'standard';
    side.dataset.calendarDensity=density;
    controls.querySelectorAll('[data-calendar-density]').forEach((button)=>{
      const active=button.dataset.calendarDensity===density;
      button.classList.toggle('is-active',active);
      button.setAttribute('aria-pressed',active?'true':'false');
    });
    if(persist)write(DENSITY_KEY,density);
    equalize();
  }

  controls.querySelectorAll('[data-calendar-density]').forEach((button)=>{
    button.addEventListener('click',()=>setDensity(button.dataset.calendarDensity||'standard'));
  });

  function activateView(value,persist=true){
    const view=views.has(value)?value:'grid';
    controls.hidden=view!=='side';
    if(persist)write(VIEW_KEY,view);
    if(view==='side')equalize();
  }

  root.querySelectorAll('[data-calendar-view]').forEach((button)=>{
    button.addEventListener('click',()=>activateView(button.dataset.calendarView||'grid'));
  });

  setDensity(read(DENSITY_KEY,'standard'),false);
  const stored=read(VIEW_KEY,'grid');
  const initial=views.has(stored)?stored:'grid';
  const button=root.querySelector('[data-calendar-view="'+initial+'"]');
  requestAnimationFrame(()=>{
    activateView(initial,false);
    button?.click();
  });

  if('ResizeObserver' in window)new ResizeObserver(equalize).observe(side);
  else window.addEventListener('resize',equalize,{passive:true});

  new MutationObserver(()=>{if(!side.hidden)equalize();}).observe(side,{childList:true,subtree:true});
})();