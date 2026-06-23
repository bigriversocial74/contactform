/* V3 image-fit patch: taller phone, better carousel image fit, cleaner growth images. */
.mg-hero-visual{
  min-height:660px!important;
}
.mg-visual-stage{
  height:660px!important;
  width:min(610px,100%)!important;
}
.mg-phone-orbit{
  inset:28px 154px 54px 128px!important;
}
.mg-phone{
  min-height:560px!important;
}
.mg-phone-screen{
  background:#030303!important;
}
.mg-carousel-screen{
  object-fit:contain!important;
  object-position:center center!important;
  background:#030303!important;
  padding:0!important;
}
.mg-device-base{
  bottom:24px!important;
}
.mg-agent-orbit{
  width:560px!important;
  height:560px!important;
}
.mg-signal-agent{
  top:88px!important;
}
.mg-signal-wallet{
  bottom:92px!important;
}
.mg-location-marker.marker-a{
  top:184px!important;
}
.mg-location-marker.marker-b{
  top:142px!important;
}
.mg-location-marker.marker-c{
  bottom:118px!important;
}
.mg-location-marker.marker-d{
  bottom:162px!important;
}

/* Growth/system cards: stop dashboard images from being awkwardly cropped. */
#growth .mg-ui-shell{
  min-height:250px!important;
  display:flex!important;
  align-items:center!important;
  justify-content:center!important;
  padding:12px!important;
  background:linear-gradient(145deg,rgba(0,0,0,.76),rgba(20,17,10,.52))!important;
}
#growth .mg-ui-shell img{
  width:100%!important;
  height:auto!important;
  max-height:282px!important;
  object-fit:contain!important;
  object-position:center center!important;
  border-radius:12px!important;
}

/* Discovery images can stay more visual, but still avoid harsh crops on smaller screens. */
#discovery .mg-ui-shell{
  display:flex!important;
  align-items:center!important;
  justify-content:center!important;
  padding:10px!important;
}
#discovery .mg-ui-shell img{
  width:100%!important;
  height:auto!important;
  max-height:292px!important;
  object-fit:contain!important;
  object-position:center center!important;
  border-radius:12px!important;
}

@media(max-width:1180px){
  .mg-hero-visual{
    min-height:620px!important;
  }
  .mg-visual-stage{
    height:620px!important;
  }
  .mg-phone-orbit{
    inset:26px 152px 58px 126px!important;
  }
}

@media(max-width:780px){
  .mg-hero-visual{
    min-height:560px!important;
  }
  .mg-visual-stage{
    height:560px!important;
  }
  .mg-phone-orbit{
    inset:18px 92px 56px 82px!important;
  }
  .mg-phone{
    min-height:486px!important;
  }
  .mg-agent-orbit{
    width:480px!important;
    height:480px!important;
  }
  #growth .mg-ui-shell,
  #discovery .mg-ui-shell{
    min-height:220px!important;
  }
  #growth .mg-ui-shell img,
  #discovery .mg-ui-shell img{
    max-height:250px!important;
  }
}

@media(max-width:520px){
  .mg-hero-visual{
    min-height:535px!important;
  }
  .mg-visual-stage{
    height:535px!important;
  }
  .mg-phone-orbit{
    inset:18px 58px 62px 48px!important;
  }
  .mg-phone{
    min-height:455px!important;
  }
  .mg-agent-orbit{
    width:390px!important;
    height:390px!important;
  }
}


/* Mobile phone orientation fix: front-facing instead of side angle. */
@media(max-width:780px){
  .mg-hero-visual{
    perspective:900px!important;
  }
  .mg-visual-stage{
    width:min(420px,100%)!important;
  }
