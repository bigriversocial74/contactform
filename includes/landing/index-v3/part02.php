  color:var(--mg-gold);
  opacity:.72;
  filter:drop-shadow(0 0 18px rgba(217,167,53,.38));
  animation:mgMarkerFloat 7s ease-in-out infinite;
}
.mg-location-marker::before{
  content:"";
  position:absolute;
  left:50%;
  top:50%;
  width:76px;
  height:76px;
  border-radius:999px;
  border:1px solid rgba(217,167,53,.16);
  transform:translate(-50%,-35%) scale(.7);
  opacity:.55;
}
.mg-location-marker svg{display:block;width:100%;height:100%;}
.mg-location-marker.marker-a{left:72px;top:188px;animation-delay:-1.2s;}
.mg-location-marker.marker-b{right:48px;top:144px;transform:scale(.78);animation-delay:-3.1s;}
.mg-location-marker.marker-c{left:118px;bottom:128px;transform:scale(.66);animation-delay:-4.4s;}
.mg-location-marker.marker-d{right:112px;bottom:180px;transform:scale(.58);animation-delay:-2.2s;}
@keyframes mgMarkerFloat{
  0%,100%{translate:0 0;opacity:.54;}
  50%{translate:0 -14px;opacity:.9;}
}
.mg-mesh-ribbon{
  position:absolute;
  z-index:0;
  inset:60px -30px 20px 20px;
  opacity:.54;
  background:url('/images/cosmic_golden_network_on_black.png') center/cover no-repeat;
  mix-blend-mode:screen;
  mask-image:radial-gradient(circle at 62% 48%,#000 0 44%,transparent 76%);
  pointer-events:none;
}

.mg-carousel-dots{
  position:absolute;
  z-index:8;
  left:50%;
  bottom:14px;
  transform:translateX(-50%);
  display:flex;
  gap:9px;
}
.mg-carousel-dot{
  width:8px;
  height:8px;
  border-radius:999px;
  border:1px solid rgba(217,167,53,.5);
  background:rgba(255,255,255,.08);
  transition:width .3s ease,background .3s ease;
}
.mg-carousel-dot.is-active{width:28px;background:var(--mg-gold);}
.mg-hero-microcopy{
  display:grid;
  grid-template-columns:repeat(3,1fr);
  gap:14px;
  max-width:620px;
  margin-top:46px;
}
.mg-metric{
  padding:15px 0 0;
  border-top:1px solid rgba(217,167,53,.32);
  color:#f7f2e8;
}
.mg-metric strong{display:block;font-size:20px;letter-spacing:-.03em;}
.mg-metric span{display:block;margin-top:4px;color:var(--mg-muted);font-size:12px;line-height:1.4;}

.mg-cards-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:24px;margin-top:58px;}
.mg-card{
  position:relative;
  min-height:270px;
  padding:34px 30px;
  border:1px solid rgba(217,167,53,.34);
  border-radius:20px;
  background:linear-gradient(145deg,rgba(11,11,10,.95),rgba(2,2,2,.78));
  box-shadow:0 24px 70px rgba(0,0,0,.36),0 0 0 1px rgba(255,255,255,.035) inset;
  overflow:hidden;
}
.mg-card::after{
  content:"";
  position:absolute;
  left:50%;
  bottom:22px;
  width:76px;
  height:2px;
  transform:translateX(-50%);
  background:linear-gradient(90deg,transparent,var(--mg-gold),transparent);
  opacity:.82;
}
.mg-icon{
  width:58px;
  height:58px;
  display:grid;
  place-items:center;
  margin:0 auto 34px;
  color:var(--mg-gold);
}
.mg-icon svg{width:58px;height:58px;stroke:currentColor;fill:none;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round;}
.mg-card h3{margin:0;color:#fff;text-align:center;font-size:25px;line-height:1.12;letter-spacing:-.04em;font-weight:720;}
.mg-card p{margin:18px auto 0;max-width:260px;text-align:center;color:var(--mg-soft);font-size:15px;line-height:1.55;}

.mg-split-intro{display:grid;grid-template-columns:.8fr 1.2fr;gap:44px;align-items:end;}
.mg-feature-panels{display:grid;grid-template-columns:repeat(3,1fr);gap:24px;margin-top:54px;}
.mg-panel{
  border:1px solid rgba(217,167,53,.36);
  border-radius:20px;
  background:linear-gradient(145deg,rgba(10,10,10,.96),rgba(3,3,3,.82));
  padding:28px;
  min-height:460px;
  box-shadow:0 24px 80px rgba(0,0,0,.42);
  overflow:hidden;
}
.mg-panel-head{display:grid;grid-template-columns:56px 1fr;gap:18px;align-items:start;margin-bottom:24px;}
.mg-panel-icon{width:56px;height:56px;border:1px solid rgba(217,167,53,.5);border-radius:12px;display:grid;place-items:center;color:var(--mg-gold);}
.mg-panel-icon svg{width:28px;height:28px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
.mg-panel h3{margin:0;color:#fff;font-size:28px;letter-spacing:-.04em;line-height:1;}
.mg-panel p{margin:10px 0 0;color:var(--mg-soft);font-size:15px;line-height:1.48;}
.mg-mini-ui{
  margin-top:22px;
  border:1px solid rgba(217,167,53,.22);
  border-radius:14px;
  overflow:hidden;
  background:#050505;
}
.mg-mini-ui img{display:block;width:100%;height:300px;object-fit:cover;object-position:top center;}
.mg-codebox{padding:22px;border-radius:14px;background:#050505;border:1px solid rgba(217,167,53,.22);margin-top:24px;font-family:"SFMono-Regular",Consolas,monospace;font-size:13px;line-height:1.72;color:#d7d2c7;overflow:hidden;}
.mg-codebox .gold{color:var(--mg-gold);}.mg-codebox .green{color:#7fd18a;}.mg-codebox .muted{color:#858076;}
.mg-panel-tags{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:22px;padding-top:20px;border-top:1px solid rgba(217,167,53,.24);}
.mg-panel-tags span{text-align:center;color:#ece7dc;font-size:13px;}

.mg-discovery .mg-panel .mg-mini-ui img{height:330px;}
.mg-footer{
  position:relative;
  padding:150px 0 66px;
  background:#020202;
  color:var(--mg-white);
  overflow:hidden;
}
.mg-footer-grid{display:grid;grid-template-columns:1.45fr repeat(3,1fr);gap:74px;align-items:start;}
.mg-footer-brand .mg-logo{margin-bottom:34px;}
.mg-footer-tag{max-width:360px;color:var(--mg-soft);font-size:20px;line-height:1.45;margin:0;}
.mg-socials{display:flex;gap:18px;margin-top:38px;}
.mg-socials a{width:54px;height:54px;display:grid;place-items:center;border:1px solid rgba(217,167,53,.4);border-radius:10px;color:#fff;text-decoration:none;transition:.2s ease;background:rgba(255,255,255,.02);}
.mg-socials a:hover{transform:translateY(-3px);border-color:rgba(241,193,90,.8);background:rgba(217,167,53,.08);}
.mg-socials svg{width:24px;height:24px;fill:currentColor;stroke:currentColor;}
.mg-contact{display:inline-flex;align-items:center;gap:14px;margin-top:36px;color:#e7e1d5;text-decoration:none;font-size:17px;}
.mg-contact svg{width:24px;height:24px;stroke:var(--mg-gold);fill:none;stroke-width:2;}
