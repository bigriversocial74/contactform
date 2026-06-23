  .mg-market-ticker{
    top:54px;
  }
  .mg-market-label{
    padding-left:16px;
    padding-right:12px;
    font-size:9px;
    letter-spacing:.18em;
  }
  .mg-ticker-item{
    padding:0 13px;
    font-size:10px;
  }
  .mg-ticker-name{
    display:none;
  }
}


/* Header + ticker flush stack fix */
.mg-site-header,
header.mg-site-header{
  margin-bottom:0!important;
  border-bottom:0!important;
}
.mg-site-header + .mg-market-ticker{
  margin-top:0!important;
}
.mg-market-ticker{
  top:54px!important;
  margin-top:0!important;
  border-top:0!important;
}
.mg-market-ticker-inner{
  margin-top:0!important;
}
.mg-market-ticker + .mg-page,
.mg-market-ticker + main{
  margin-top:0!important;
  padding-top:0!important;
}


/* Header ticker integrated stack fix */
.mg-site-header{
  padding-bottom:0!important;
  border-bottom:0!important;
}
.mg-site-header .mg-nav{
  margin-bottom:0!important;
}
.mg-site-header .mg-market-ticker{
  position:relative!important;
  top:auto!important;
  left:auto!important;
  right:auto!important;
  z-index:auto!important;
  margin:0!important;
  width:100%!important;
  border-top:1px solid rgba(217,167,53,.18)!important;
  border-bottom:1px solid rgba(217,167,53,.24)!important;
  box-shadow:none!important;
  background:#000!important;
}
.mg-site-header .mg-market-ticker-inner{
  min-height:34px!important;
}
.mg-site-header .mg-market-label{
  padding-top:0!important;
  padding-bottom:0!important;
}
.mg-page{
  overflow-x:hidden;
}
@media(max-width:780px){
  .mg-site-header .mg-market-ticker{
    display:block!important;
  }
}


/* Header center ticker layout */
.mg-nav-market{
  height:64px!important;
  display:grid!important;
  grid-template-columns:auto minmax(300px,1fr) auto!important;
  align-items:center!important;
  gap:22px!important;
}
.mg-nav-left,
.mg-nav-center,
.mg-nav-right{
  min-width:0;
  display:flex;
  align-items:center;
}
.mg-nav-left{
  justify-content:flex-start;
}
.mg-nav-center{
  justify-content:center;
  overflow:hidden;
}
.mg-nav-right{
  justify-content:flex-end;
  gap:24px;
}
.mg-nav-right .mg-nav-links{
  margin-left:0!important;
  display:flex!important;
  justify-content:flex-end!important;
  gap:24px!important;
}
.mg-nav-right .mg-header-cta{
  flex:0 0 auto;
}
.mg-market-ticker-center{
  position:relative!important;
  top:auto!important;
  left:auto!important;
  right:auto!important;
  z-index:1!important;
  width:min(560px,100%)!important;
  max-width:560px!important;
  margin:0 auto!important;
  border:1px solid rgba(217,167,53,.24)!important;
  border-radius:999px!important;
  overflow:hidden!important;
  box-shadow:0 0 0 1px rgba(255,255,255,.035) inset,0 14px 34px rgba(0,0,0,.24)!important;
  background:#000!important;
}
.mg-market-ticker-center .mg-market-ticker-inner{
  min-height:34px!important;
}
.mg-market-ticker-center .mg-market-label{
  min-height:34px!important;
  padding:0 13px!important;
  font-size:9px!important;
  letter-spacing:.18em!important;
  background:linear-gradient(90deg,#000 0%,#050505 88%,rgba(0,0,0,0) 100%)!important;
}
.mg-market-ticker-center .mg-market-track{
  min-width:0!important;
}
.mg-market-ticker-center .mg-ticker-item{
  min-height:34px!important;
  padding:0 13px!important;
  font-size:10px!important;
}
.mg-market-ticker-center .mg-ticker-name{
