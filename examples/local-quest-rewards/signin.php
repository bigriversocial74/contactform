<?php
declare(strict_types=1);
if (!is_file(__DIR__.'/config.php')) { header('Location: install.php'); exit; }
require __DIR__.'/participant-auth.php';
require __DIR__.'/auth-view.php';
if(isset($_GET['mode'])&&$_GET['mode']==='signup'){header('Location: signup.php');exit;}
$error=null;$message=isset($_GET['reset'])?'Password updated. Sign in with your new password.':null;
try{if(($_POST['action']??'')==='login'){lqr_auth_login((string)($_POST['email']??''),(string)($_POST['password']??''));header('Location: index.php');exit;}}catch(Throwable $e){$error=$e->getMessage();}
lqr_auth_header('Sign in','Sign in securely to your Microgifter Local Quest account.');
?>
<div class="auth-tabs"><a class="active" href="signin.php">Sign in</a><a href="signup.php">Create account</a></div>
<h2>Welcome back</h2><p>Sign in to continue your quests, wallet, and connected rewards.</p>
<?php lqr_auth_notice($message);lqr_auth_notice($error,true); ?>
<form method="post"><label>Email address<input name="email" type="email" autocomplete="email" required></label><label>Password<input name="password" type="password" autocomplete="current-password" required></label><button class="button" name="action" value="login">Sign in</button></form>
<div class="inline-links"><a href="forgot-password.php">Forgot password?</a><a href="resend-verification.php">Resend verification</a></div>
<div class="microgifter-connect"><strong>Microgifter connection</strong><p>After sign-in, connect your Microgifter account to receive and manage eligible quest rewards.</p></div>
<?php lqr_auth_footer();