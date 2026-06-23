  .mg-phone-orbit{
    inset:10px 78px 48px 78px!important;
    transform:rotateY(0deg) rotateZ(0deg) translateY(0)!important;
    animation:none!important;
    filter:drop-shadow(0 28px 56px rgba(0,0,0,.55))!important;
  }
  .mg-device-base{
    width:300px!important;
    height:52px!important;
    bottom:18px!important;
    left:50%!important;
    transform:translateX(-50%) rotateX(72deg) rotateZ(0deg)!important;
  }
  .mg-location-marker.marker-a{left:18px!important;top:126px!important;}
  .mg-location-marker.marker-b{right:18px!important;top:118px!important;}
  .mg-location-marker.marker-c{left:40px!important;bottom:102px!important;}
  .mg-location-marker.marker-d{right:38px!important;bottom:132px!important;}
}
@media(max-width:520px){
  .mg-visual-stage{
    width:min(360px,100%)!important;
  }
  .mg-phone-orbit{
    inset:8px 52px 54px 52px!important;
    transform:rotateY(0deg) rotateZ(0deg) translateY(0)!important;
  }
  .mg-phone{
    border-radius:32px!important;
  }
  .mg-phone-screen{
    border-radius:24px!important;
  }
  .mg-device-base{
    width:260px!important;
    height:46px!important;
    bottom:14px!important;
    transform:translateX(-50%) rotateX(74deg) rotateZ(0deg)!important;
  }
}


/* Mobile front-facing hard fix: remove side-angle cues entirely. */
@media(max-width:780px){
  .mg-hero-visual{
    perspective:none!important;
    min-height:580px!important;
    display:block!important;
  }
  .mg-visual-stage{
    width:100%!important;
    max-width:420px!important;
    height:580px!important;
    margin:0 auto!important;
    display:flex!important;
    align-items:center!important;
    justify-content:center!important;
  }
  .mg-phone-orbit{
    position:relative!important;
    inset:auto!important;
    width:min(290px,78vw)!important;
    height:510px!important;
    margin:0 auto!important;
    transform:none!important;
    animation:none!important;
    filter:drop-shadow(0 24px 50px rgba(0,0,0,.42))!important;
  }
  .mg-phone{
    width:100%!important;
    height:100%!important;
    min-height:0!important;
    border-radius:34px!important;
  }
  .mg-phone::before{
    display:none!important;
  }
  .mg-phone::after{
    opacity:.22!important;
  }
  .mg-phone-screen{
    border-radius:26px!important;
  }
  .mg-device-base{
    display:none!important;
  }
  .mg-agent-orbit{
    width:420px!important;
    height:420px!important;
    left:50%!important;
    top:50%!important;
    transform:translate(-50%,-50%)!important;
  }
  .mg-location-marker.marker-a{left:8px!important;top:132px!important;}
  .mg-location-marker.marker-b{right:8px!important;top:126px!important;}
  .mg-location-marker.marker-c{left:24px!important;bottom:112px!important;}
  .mg-location-marker.marker-d{right:24px!important;bottom:136px!important;}
}
@media(max-width:520px){
  .mg-hero-visual{
    min-height:540px!important;
  }
  .mg-visual-stage{
    max-width:360px!important;
    height:540px!important;
  }
  .mg-phone-orbit{
    width:min(268px,76vw)!important;
    height:468px!important;
  }
  .mg-phone{
    border-radius:30px!important;
  }
  .mg-phone-screen{
    border-radius:22px!important;
  }
  .mg-agent-orbit{
    width:360px!important;
    height:360px!important;
  }
}


/* Mobile hero polish: hide GPS markers, add slight angle + bounce to phone. */
@media(max-width:780px){
  .mg-location-marker{
    display:none!important;
  }
  .mg-phone-orbit{
    transform:rotateY(-7deg) rotateZ(1.8deg) translateY(0)!important;
    animation:mgMobilePhoneFloat 5.8s ease-in-out infinite!important;
    transform-style:preserve-3d!important;
  }
  .mg-phone{
    box-shadow:
      0 0 0 2px rgba(255,255,255,.04) inset,
      0 0 0 4px rgba(0,0,0,.82) inset,
      0 22px 54px rgba(0,0,0,.42)!important;
  }
}
@media(max-width:520px){
  .mg-phone-orbit{
    transform:rotateY(-6deg) rotateZ(1.4deg) translateY(0)!important;
    animation:mgMobilePhoneFloatSmall 5.4s ease-in-out infinite!important;
  }
}
@keyframes mgMobilePhoneFloat{
  0%,100%{transform:rotateY(-7deg) rotateZ(1.8deg) translateY(0);}
  50%{transform:rotateY(-5deg) rotateZ(1.1deg) translateY(-12px);}
}
@keyframes mgMobilePhoneFloatSmall{
