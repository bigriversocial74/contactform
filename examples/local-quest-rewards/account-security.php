<?php
declare(strict_types=1);
if(!is_file(__DIR__.'/config.php')){header('Location: install.php');exit;}
require __DIR__.'/participant-auth.php';require __DIR__.'/auth-view.php';$user=lqr_auth_require_user();$message=null;$error=null;
try{if(($_POST['action']??'')==='change_password'){lqr_auth_change_password($user,(string)($_POST['current_password']??''),(string)($_POST['password']??''),(string)($_POST['password_confirmation']??''));$message='Password updated. Other sessions are now invalid.';}}catch(Throwable $e){$error=$e->getMessage();}
lqr_auth_header('Account security','Manage your participant password and active session security.');?>
<h2>Account security</h2><p>Change your password and invalidate other signed-in sessions.</p><?php lqr_auth_notice($message);lqr_auth_notice($error,true);?>
<form method="post"><label>Current password<input name="current_password" type="password" autocomplete="current-password" required></label><label>New password<input name="password" type="password" autocomplete="new-password" minlength="12" required></label><label>Confirm new password<input name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required></label><button class="button" name="action" value="change_password">Update password</button></form><a class="button secondary" href="profile.php">Back to profile</a><?php lqr_auth_footer();