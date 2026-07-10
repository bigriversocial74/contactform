<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/api/db.php';
$requestId = strtolower(trim((string)($_GET['request'] ?? '')));
$user = mg_current_user();
$request = null;
$error = '';
if (strlen($requestId) !== 36 || preg_match('/^[a-f0-9-]{36}$/', $requestId) !== 1) {
    $error = 'This authorization request is invalid.';
} else {
    try {
        $stmt = mg_db()->prepare("SELECT dia.*,mda.name app_name,mda.status app_status,u.display_name merchant_name,u.full_name merchant_full_name,u.email merchant_email FROM developer_identity_authorizations dia INNER JOIN merchant_developer_apps mda ON mda.id=dia.app_id INNER JOIN users u ON u.id=dia.merchant_user_id WHERE dia.public_id=? LIMIT 1");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch();
        if (!$request) $error = 'This authorization request was not found.';
        elseif ((string)$request['status'] !== 'pending') $error = 'This authorization request has already been completed.';
        elseif (strtotime((string)$request['expires_at']) < time()) $error = 'This authorization request has expired.';
        elseif ((string)$request['app_status'] !== 'active') $error = 'This application is not active.';
    } catch (Throwable) { $error = 'This authorization request cannot be loaded right now.'; }
}
$page_title='Continue with Microgifter';$page_section='identity';$header_mode='public';require __DIR__.'/includes/header.php';
?>
<main class="mg-merchant-main"><section class="mg-app-panel" style="max-width:760px;margin:72px auto"><div class="mg-app-panel-body">
<?php if($error!==''): ?><span class="mg-kicker">Microgifter Identity</span><h1>Authorization unavailable</h1><p><?= mg_e($error) ?></p><a class="mg-btn mg-btn-primary" href="/signin.php">Sign in</a>
<?php else: ?><span class="mg-kicker">Shared Microgifter Identity</span><h1>Continue with Microgifter</h1><p><strong><?= mg_e((string)$request['app_name']) ?></strong> wants to use your Microgifter account for this <?= mg_e((string)$request['requested_role']) ?> experience.</p><p>Merchant: <strong><?= mg_e((string)($request['merchant_name'] ?: $request['merchant_full_name'] ?: $request['merchant_email'])) ?></strong></p>
<?php if(!$user): ?><div class="mg-heading-actions"><a class="mg-btn mg-btn-primary" href="/signin.php?return=<?= rawurlencode('/identity-authorize.php?request='.$requestId) ?>">Sign in with Microgifter</a><a class="mg-btn mg-btn-soft" href="/signup.php?return=<?= rawurlencode('/identity-authorize.php?request='.$requestId) ?>">Create Microgifter account</a></div>
<?php else: ?><p>Signed in as <strong><?= mg_e(mg_user_display_name()) ?></strong>.</p><form method="post" action="/api/public/v1/identity-authorize-complete.php" class="mg-heading-actions"><?= mg_csrf_field() ?><input type="hidden" name="request" value="<?= mg_e($requestId) ?>"><button class="mg-btn mg-btn-primary" name="action" value="approve">Approve and continue</button><button class="mg-btn mg-btn-soft" name="action" value="cancel">Cancel</button></form><?php endif; ?>
<?php endif; ?></div></section></main><?php require __DIR__.'/includes/footer.php'; ?>
