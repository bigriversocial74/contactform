<?php
declare(strict_types=1);
if(!is_file(__DIR__.'/config.php')){header('Location: install.php');exit;}
require __DIR__.'/participant-auth.php';require __DIR__.'/auth-view.php';$message=null;
if(($_POST['action']??'')==='resend'){lqr_auth_resend_verification((string)($_POST['email']??''));$message='If that account still needs verification, a new secure link has been prepared.';}
lqr_auth_header('Resend verification','Request a new Local Quest email-verification link.');?>
<h2>Resend verification</h2><p>Enter your account email to receive a fresh single-use verification link.</p><?php lqr_auth_notice($message);?>
<form method="post"><label>Email address<input name="email" type="email" autocomplete="email" required></label><button class="button" name="action" value="resend">Send verification link</button></form><a class="button secondary" href="signin.php">Back to sign in</a><?php lqr_auth_preview_link();lqr_auth_footer();