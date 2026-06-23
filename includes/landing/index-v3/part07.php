  0%,100%{transform:rotateY(-6deg) rotateZ(1.4deg) translateY(0);}
  50%{transform:rotateY(-4.5deg) rotateZ(0.9deg) translateY(-9px);}
}


/* Mobile hero desktop-tilt match: use the same perspective tilt language as desktop. */
@media(max-width:780px){
  .mg-phone-orbit{
    transform:rotateY(-16deg) rotateZ(3deg) translateY(0)!important;
    animation:mgMobileDesktopTiltFloat 5.8s ease-in-out infinite!important;
  }
}
@media(max-width:520px){
  .mg-phone-orbit{
    transform:rotateY(-14deg) rotateZ(2.6deg) translateY(0)!important;
    animation:mgMobileDesktopTiltFloatSmall 5.4s ease-in-out infinite!important;
  }
}
@keyframes mgMobileDesktopTiltFloat{
  0%,100%{transform:rotateY(-16deg) rotateZ(3deg) translateY(0);}
  50%{transform:rotateY(-13deg) rotateZ(2.4deg) translateY(-12px);}
}
@keyframes mgMobileDesktopTiltFloatSmall{
  0%,100%{transform:rotateY(-14deg) rotateZ(2.6deg) translateY(0);}
  50%{transform:rotateY(-11.5deg) rotateZ(2deg) translateY(-9px);}
}


/* Future demand header copy polish. */
.mg-future-demand-note{
  margin-top:18px;
  max-width:620px;
  color:#9f998f;
  font-size:12px;
  line-height:1.55;
}
.mg-future-demand-note strong{
  color:#d9a735;
}


/* Experience Market ticker: black NASDAQ/NYSE-inspired subheader. */
.mg-market-ticker{
  position:sticky;
  top:54px;
  z-index:90;
  width:100%;
  overflow:hidden;
  background:#000;
  border-top:1px solid rgba(217,167,53,.22);
  border-bottom:1px solid rgba(217,167,53,.24);
  box-shadow:0 12px 30px rgba(0,0,0,.28);
}
.mg-market-ticker-inner{
  display:flex;
  align-items:center;
  min-height:34px;
}
.mg-market-label{
  position:relative;
  z-index:2;
  flex:0 0 auto;
  display:flex;
  align-items:center;
  min-height:34px;
  padding:0 16px 0 max(18px,calc((100vw - var(--mg-max))/2 + 36px));
  background:linear-gradient(90deg,#000 0%,#050505 70%,rgba(0,0,0,0) 100%);
  color:#d9a735;
  font-size:10px;
  font-weight:900;
  letter-spacing:.24em;
  text-transform:uppercase;
  white-space:nowrap;
}
.mg-market-label::before{
  content:"";
  width:7px;
  height:7px;
  margin-right:9px;
  border-radius:999px;
  background:#20d475;
  box-shadow:0 0 14px rgba(32,212,117,.75);
}
.mg-market-track{
  flex:1;
  overflow:hidden;
  min-width:0;
}
.mg-market-marquee{
  display:flex;
  align-items:center;
  gap:0;
  width:max-content;
  min-height:34px;
  animation:mgTickerScroll 44s linear infinite;
}
.mg-market-row{
  display:flex;
  align-items:center;
  gap:0;
}
.mg-ticker-item{
  display:inline-flex;
  align-items:center;
  gap:8px;
  min-height:34px;
  padding:0 18px;
  border-left:1px solid rgba(255,255,255,.07);
  white-space:nowrap;
  font-size:11px;
  font-weight:850;
  letter-spacing:.04em;
  color:#f4f1e8;
}
.mg-ticker-symbol{
  color:#fff;
  letter-spacing:.12em;
}
.mg-ticker-name{
  color:#9f9a91;
  font-weight:700;
  letter-spacing:.02em;
}
.mg-ticker-price{
  color:#f4f1e8;
  font-variant-numeric:tabular-nums;
}
.mg-ticker-change{
  display:inline-flex;
  align-items:center;
  gap:3px;
  font-variant-numeric:tabular-nums;
}
.mg-ticker-change.up{
  color:#20d475;
}
.mg-ticker-change.down{
  color:#ff5f57;
}
.mg-ticker-change.flat{
  color:#d9a735;
}
@keyframes mgTickerScroll{
  from{transform:translateX(0);}
  to{transform:translateX(-50%);}
}
.mg-market-ticker:hover .mg-market-marquee{
  animation-play-state:paused;
}
@media(max-width:780px){
