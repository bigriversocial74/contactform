window.Microgifter = window.Microgifter || {};
(function(document){
  'use strict';
  var map=document.querySelector('[data-world-map]');
  if(!map||map.querySelector('[data-world-reference-svg]'))return;
  var wrap=document.createElement('div');
  wrap.className='mg-world-reference-map-svg';
  wrap.dataset.worldReferenceSvg='1';
  wrap.innerHTML='<svg viewBox="0 0 1000 520" preserveAspectRatio="xMidYMid meet" aria-hidden="true"><rect width="1000" height="520" fill="#dceeff"/><g class="grid"><path d="M0 104h1000M0 208h1000M0 312h1000M0 416h1000M125 0v520M250 0v520M375 0v520M500 0v520M625 0v520M750 0v520M875 0v520"/></g><g class="land"><path d="M117 129l35-21 51-12 48 5 32 18 29 7 30 28 5 31-19 20-43-4-25 13-30-13-27 3-20 26-31 10-31-8-15-27-35-10-14-25 13-25 46-16z"/><path d="M183 232l29-9 34 9 27 23 19 36 0 41-19 43-4 42-23 44-18 2-17-39-20-31 6-47-14-43-25-31 25-40z"/><path d="M298 93l49-29 61-9 45 15 19 26-8 27-37 16-50-5-42 16-36-13-1-44z"/><path d="M410 157l40-20 49-1 39 20 34 7 38 27-1 31-40 16-38-11-29 15-40-11-44 8-24-21 16-60z"/><path d="M505 236l57-22 72 6 38 29 22 45-9 48-38 29-13 45-28 47-31 1-12-52-27-33-43-27-22-43 11-48 25-26z"/><path d="M607 117l58-25 81 2 67 30 50 45 48 20 31 50-2 53-33 43-54 19-62-2-49-30-44-2-31 30-54-3-35-37 18-59-20-49 31-85z"/><path d="M784 361l53 5 44 26 18 40-17 31-46 15-47-12-28-34 7-44 16-27z"/><path d="M549 91l25-25 38-5 24 18-4 25-32 16-35-4-16-25z"/><path d="M833 117l31-10 29 9 11 22-24 14-33-5-14-30z"/></g><g class="shore"><path d="M118 129c74-42 135-39 195 25M183 232c55 8 96 70 89 137M412 157c82-39 142-4 198 33M505 236c96-39 165 28 180 106M607 117c132-69 260 6 335 122M784 361c76-1 126 63 98 102"/></g></svg>';
  map.insertBefore(wrap,map.firstChild);
})(document);
