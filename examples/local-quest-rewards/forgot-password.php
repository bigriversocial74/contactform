<?php
declare(strict_types=1);
if(!is_file(__DIR__.'/config.php')){header('Location: install.php');exit;}
require __DIR__.'/participant-auth.php';require __DIR__.'/auth-view.php';$message=null;
if(($_POST['action']??'')==='request'){lqr_auth_request_reset((string)($_POST['email']??''));$message=lqr_auth_generic_recovery_message();}
lqr_auth_header('Forgot password','Request a secure password reset for Microgifter Local Quest.');?>
<h2>Reset your password</h2><p>Enter your account email. For privacy, the response is the same whether an account exists or not.</p><?php lqr_auth_notice($message);?>
<form method="post"><label>Email address<input name="email" type="email" autocomplete="email" required></label><button class="button" name="action" value="request">Send secure reset link</button></form><a class="button secondary" href="signin.php">Back to sign in</a><?php lqr_auth_preview_link();lqr_auth_footer();