  display:none!important;
}
.mg-market-ticker-center .mg-market-marquee{
  animation-duration:34s!important;
}
.mg-hero{
  padding-top:118px!important;
}
@media(max-width:1180px){
  .mg-nav-market{
    grid-template-columns:auto minmax(220px,1fr) auto!important;
    gap:16px!important;
  }
  .mg-market-ticker-center{
    width:min(420px,100%)!important;
  }
  .mg-nav-right .mg-nav-links{
    gap:16px!important;
  }
}
@media(max-width:900px){
  .mg-nav-market{
    grid-template-columns:auto 1fr auto!important;
    width:min(var(--mg-max),calc(100% - 32px))!important;
    gap:12px!important;
  }
  .mg-market-ticker-center{
    width:100%!important;
    max-width:330px!important;
  }
  .mg-market-ticker-center .mg-market-label{
    display:none!important;
  }
  .mg-nav-right .mg-nav-links{
    display:none!important;
  }
}
@media(max-width:620px){
  .mg-nav-market{
    grid-template-columns:auto 1fr auto!important;
  }
  .mg-logo-text{
    display:none!important;
  }
  .mg-market-ticker-center{
    max-width:190px!important;
  }
  .mg-market-ticker-center .mg-ticker-item{
    padding:0 10px!important;
    gap:6px!important;
  }
  .mg-market-ticker-center .mg-ticker-price{
    display:none!important;
  }
}


/* Full-width sticky header + wider center ticker */
.mg-site-header{
  width:100vw!important;
  max-width:none!important;
  left:0!important;
  right:0!important;
  margin:0!important;
  padding:0!important;
}
.mg-nav-market,
.mg-site-header .mg-nav-market,
.mg-site-header .mg-nav{
  width:100%!important;
  max-width:none!important;
  margin:0!important;
  padding-left:clamp(16px,2.2vw,34px)!important;
  padding-right:clamp(16px,2.2vw,34px)!important;
  box-sizing:border-box!important;
}
.mg-nav-market{
  grid-template-columns:minmax(150px,.7fr) minmax(420px,1.45fr) minmax(430px,1fr)!important;
  gap:18px!important;
}
.mg-nav-left{
  min-width:150px!important;
}
.mg-nav-center{
  width:100%!important;
  min-width:0!important;
}
.mg-nav-right{
  min-width:430px!important;
}
.mg-market-ticker-center{
  width:100%!important;
  max-width:820px!important;
  min-width:0!important;
  border-radius:999px!important;
}
.mg-market-ticker-center .mg-market-label{
  padding-left:10px!important;
  padding-right:8px!important;
  letter-spacing:.14em!important;
}
.mg-market-ticker-center .mg-market-label::before{
  margin-right:6px!important;
}
.mg-market-ticker-center .mg-ticker-item{
  padding-left:10px!important;
  padding-right:10px!important;
  gap:6px!important;
}
.mg-market-ticker-center .mg-market-marquee{
  animation-duration:38s!important;
}
@media(max-width:1260px){
  .mg-nav-market{
    grid-template-columns:minmax(132px,.55fr) minmax(360px,1.35fr) minmax(360px,.95fr)!important;
    gap:14px!important;
  }
  .mg-nav-left{min-width:132px!important;}
  .mg-nav-right{min-width:360px!important;}
  .mg-market-ticker-center{
    max-width:680px!important;
  }
  .mg-nav-right .mg-nav-links{
    gap:16px!important;
  }
}
@media(max-width:980px){
  .mg-nav-market{
    grid-template-columns:auto minmax(220px,1fr) auto!important;
    padding-left:14px!important;
    padding-right:14px!important;
  }
  .mg-nav-left,
  .mg-nav-right{
    min-width:0!important;
  }
  .mg-market-ticker-center{
    max-width:none!important;
  }
  .mg-nav-right .mg-nav-links{
    display:none!important;
  }
}
@media(max-width:620px){
  .mg-nav-market{
    gap:10px!important;
    padding-left:12px!important;
    padding-right:12px!important;
  }
  .mg-market-ticker-center{
