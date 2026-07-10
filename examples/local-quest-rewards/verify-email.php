<?php
declare(strict_types=1);
if(!is_file(__DIR__.'/config.php')){header('Location: install.php');exit;}
require __DIR__.'/participant-auth.php';require __DIR__.'/auth-view.php';$ok=lqr_auth_verify_email((string)($_GET['token']??''));
lqr_auth_header('Verify email','Confirm your Microgifter Local Quest account email.');?>
<h2><?=$ok?'Email verified':'Verification link unavailable'?></h2><p><?=$ok?'Your email is confirmed. Your participant account is ready for Microgifter connection and reward delivery.':'This verification link is invalid, expired, or already used.'?></p><a class="button" href="<?=$ok&&lqr_is_authenticated()?'profile.php':'signin.php'?>"><?=$ok?'Continue':'Return to sign in'?></a><?php if(!$ok):?><a class="button secondary" href="resend-verification.php">Request a new link</a><?php endif;lqr_auth_footer();