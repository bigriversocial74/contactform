<?php
declare(strict_types=1);
if(!is_file(__DIR__.'/config.php')){header('Location: install.php');exit;}
require __DIR__.'/participant-auth.php';require __DIR__.'/auth-view.php';$error=null;$token=(string)($_GET['token']??$_POST['token']??'');
try{if(($_POST['action']??'')==='reset'){lqr_auth_reset_password($token,(string)($_POST['password']??''),(string)($_POST['password_confirmation']??''));header('Location: signin.php?reset=1');exit;}}catch(Throwable $e){$error=$e->getMessage();}
lqr_auth_header('Choose a new password','Create a new secure password for your Local Quest account.');?>
<h2>Choose a new password</h2><p>This secure link is single-use and expires automatically.</p><?php lqr_auth_notice($error,true);?>
<form method="post"><input type="hidden" name="token" value="<?=lqr_h($token)?>"><label>New password<input name="password" type="password" autocomplete="new-password" minlength="12" required></label><label>Confirm new password<input name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required></label><button class="button" name="action" value="reset">Update password</button></form><a class="button secondary" href="forgot-password.php">Request another link</a><?php lqr_auth_footer();