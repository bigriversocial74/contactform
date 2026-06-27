<?php
declare(strict_types=1);
require __DIR__ . '/app.php';

$config = lqr_config();
$state = lqr_load_state();
if (empty($_SESSION['lqr_admin_authed'])) {
    header('Location: admin.php');
    exit;
}
$message = null;
$error = null;
try {
    $action = (string)($_POST['action'] ?? '');
    $settings = lqr_app_console_settings($state, $config);
    if ($action === 'save_app') {
        $settings = array_replace($settings, [
            'app_name' => trim((string)($_POST['app_name'] ?? '')) ?: 'Local Quest Rewards',
            'app_status' => in_array((string)($_POST['app_status'] ?? ''), ['sandbox','review','live','disabled'], true) ? (string)$_POST['app_status'] : 'sandbox',
            'approval_status' => in_array((string)($_POST['approval_status'] ?? ''), ['approved','requested','rejected'], true) ? (string)$_POST['approval_status'] : 'approved',
            'callback_url' => trim((string)($_POST['callback_url'] ?? '')),
            'webhook_url' => trim((string)($_POST['webhook_url'] ?? '')),
            'allowed_programs' => lqr_app_console_lines((string)($_POST['allowed_programs'] ?? '')),
            'allowed_templates' => lqr_app_console_lines((string)($_POST['allowed_templates'] ?? '')),
            'review_note' => trim((string)($_POST['review_note'] ?? '')),
        ]);
        lqr_app_console_save($state, $settings);
        lqr_add_event($state, 'app_console.saved', 'Partner app console settings saved.', ['app_status'=>$settings['app_status'], 'approval_status'=>$settings['approval_status']]);
        lqr_save_state($state);
        $message = 'Partner app console saved.';
    } elseif ($action === 'request_review') {
        $settings['app_status'] = 'review';
        $settings['approval_status'] = 'requested';
        $settings['review_note'] = 'Partner app review requested.';
        lqr_app_console_save($state, $settings);
        lqr_add_event($state, 'app_console.review_requested', 'Partner app review requested.');
        lqr_save_state($state);
        $message = 'Review requested.';
    } elseif ($action === 'approve_app') {
        $settings['app_status'] = 'live';
        $settings['approval_status'] = 'approved';
        $settings['review_note'] = 'Approved for live partner distribution.';
        lqr_app_console_save($state, $settings);
        lqr_add_event($state, 'app_console.approved', 'Partner app approved.');
        lqr_save_state($state);
        $message = 'Partner app approved.';
    } elseif ($action === 'reject_app') {
        $settings['approval_status'] = 'rejected';
        $settings['review_note'] = 'Needs changes before distribution access.';
        lqr_app_console_save($state, $settings);
        lqr_add_event($state, 'app_console.rejected', 'Partner app rejected.');
        lqr_save_state($state);
        $message = 'Partner app rejected.';
    } elseif ($action === 'disable_app') {
        $settings['app_status'] = 'disabled';
        lqr_app_console_save($state, $settings);
        lqr_add_event($state, 'app_console.disabled', 'Partner app disabled.');
        lqr_save_state($state);
        $message = 'Partner app disabled.';
    } elseif ($action === 'seed_app') {
        lqr_app_console_save($state, lqr_app_console_default($config));
        lqr_add_event($state, 'app_console.seeded', 'Partner app console defaults seeded.');
        lqr_save_state($state);
        $message = 'Default app console restored.';
    }
    $state = lqr_load_state();
} catch (Throwable $e) {
    $error = $e->getMessage();
}
$app = lqr_app_console_settings($state, $config);
$gate = lqr_app_status_gate($state, $config);
$programText = implode("\n", is_array($app['allowed_programs'] ?? null) ? $app['allowed_programs'] : []);
$templateText = implode("\n", is_array($app['allowed_templates'] ?? null) ? $app['allowed_templates'] : []);
$statusOptions = ['sandbox'=>'sandbox','review'=>'review','live'=>'live','disabled'=>'disabled'];
$approvalOptions = ['approved'=>'approved','requested'=>'requested','rejected'=>'rejected'];
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Partner App Console | Local Quest Rewards</title><link rel="stylesheet" href="assets/portal.css"><style>.lq-console-hero{background:linear-gradient(135deg,#101828,#211816 48%,#4b1729);color:#fff;border-radius:18px;padding:32px;margin-bottom:22px;box-shadow:var(--lq-shadow)}.lq-console-hero h1{font-size:clamp(36px,6vw,68px);line-height:.92;letter-spacing:-.08em;margin:12px 0}.lq-console-hero p{color:#ffe6ef;max-width:900px}.lq-console-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:22px 0}.lq-console-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.lq-console-field label{display:block;font-size:12px;font-weight:950;color:var(--lq-muted);text-transform:uppercase;letter-spacing:.06em;margin:0 0 6px}.lq-console-field input,.lq-console-field select,.lq-console-field textarea{width:100%;border:1px solid var(--lq-border);border-radius:10px;padding:11px;background:#fff}.lq-console-field.full{grid-column:1/-1}.lq-console-code{background:#071225;color:#eef6ff;border-radius:10px;padding:14px;overflow:auto;font-size:12px;white-space:pre-wrap}.lq-console-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}@media(max-width:920px){.lq-console-grid,.lq-console-form{grid-template-columns:1fr}}</style></head><body class="lq-portal"><div class="lq-shell">
<header class="lq-topbar"><div class="lq-brand"><span class="lq-logo">LQ</span><span>Local Quest</span></div><div class="lq-top-actions"><a class="lq-upgrade" href="developer-starter.php">Developer Starter</a><a class="lq-upgrade" href="admin-programs.php">Program Builder</a><a class="lq-upgrade" href="admin-developer-readiness.php">Readiness</a></div></header>
<aside class="lq-sidebar"><a class="lq-side-link" href="admin.php"><span class="lq-side-icon">⚙</span><span class="lq-side-label">Admin</span></a><a class="lq-side-link active" href="app-console-admin.php"><span class="lq-side-icon">APP</span><span class="lq-side-label">App Console</span></a><a class="lq-side-link" href="admin-programs.php"><span class="lq-side-icon">BLD</span><span class="lq-side-label">Program Builder</span></a><a class="lq-side-link" href="admin-developer-readiness.php"><span class="lq-side-icon">QA</span><span class="lq-side-label">Readiness</span></a><a class="lq-side-link" href="webhook.php"><span class="lq-side-icon">◷</span><span class="lq-side-label">Webhook status</span></a></aside>
<main class="lq-main"><section class="lq-console-hero"><span class="lq-eyebrow">Partner developer control</span><h1>App Console.</h1><p>Approve, pause, or disable the outside app before it can issue merchant rewards. This is the partner access layer above Program Builder and claim sync.</p><div class="lq-actions"><form method="post"><button class="lq-btn primary" name="action" value="request_review">Request review</button></form><form method="post"><button class="lq-btn green" name="action" value="approve_app">Approve live</button></form><form method="post"><button class="lq-btn amber" name="action" value="disable_app">Disable</button></form></div></section>
<?php if ($message): ?><div class="lq-notice"><?= lqr_h($message) ?></div><?php endif; ?><?php if ($error): ?><div class="lq-notice error"><?= lqr_h($error) ?></div><?php endif; ?>
<div class="lq-console-grid"><div class="lq-kpi"><span>Status</span><strong><?= lqr_h((string)$app['app_status']) ?></strong></div><div class="lq-kpi"><span>Approval</span><strong><?= lqr_h((string)$app['approval_status']) ?></strong></div><div class="lq-kpi"><span>API</span><strong><?= lqr_h((string)($app['last_api_at'] ?: 'none')) ?></strong></div><div class="lq-kpi"><span>Webhook</span><strong><?= lqr_h((string)($app['last_webhook_at'] ?: 'none')) ?></strong></div></div>
<section class="lq-card"><div class="lq-card-head"><div><h2>Access gate</h2><p><?= lqr_h((string)$gate['message']) ?></p></div><span class="lq-pill <?= !empty($gate['ok'])?'green':'amber' ?>"><?= !empty($gate['ok'])?'Allowed':'Blocked' ?></span></div><form method="post" class="lq-console-form"><input type="hidden" name="action" value="save_app"><div class="lq-console-field"><label>App name</label><input name="app_name" value="<?= lqr_h((string)$app['app_name']) ?>"></div><div class="lq-console-field"><label>App status</label><select name="app_status"><?php foreach ($statusOptions as $value => $label): ?><option value="<?= lqr_h($value) ?>" <?= $app['app_status']===$value?'selected':'' ?>><?= lqr_h($label) ?></option><?php endforeach; ?></select></div><div class="lq-console-field"><label>Approval</label><select name="approval_status"><?php foreach ($approvalOptions as $value => $label): ?><option value="<?= lqr_h($value) ?>" <?= $app['approval_status']===$value?'selected':'' ?>><?= lqr_h($label) ?></option><?php endforeach; ?></select></div><div class="lq-console-field"><label>Callback URL</label><input name="callback_url" value="<?= lqr_h((string)$app['callback_url']) ?>"></div><div class="lq-console-field full"><label>Webhook URL</label><input name="webhook_url" value="<?= lqr_h((string)$app['webhook_url']) ?>"></div><div class="lq-console-field"><label>Allowed program IDs</label><textarea name="allowed_programs" rows="5"><?= lqr_h($programText) ?></textarea></div><div class="lq-console-field"><label>Allowed reward templates</label><textarea name="allowed_templates" rows="5"><?= lqr_h($templateText) ?></textarea></div><div class="lq-console-field full"><label>Review note</label><textarea name="review_note" rows="3"><?= lqr_h((string)$app['review_note']) ?></textarea></div><div class="lq-console-field full"><div class="lq-console-actions"><button class="lq-btn primary">Save console</button><button class="lq-btn soft" name="action" value="seed_app">Restore defaults</button><button class="lq-btn amber" name="action" value="reject_app">Reject</button></div></div></form></section>
<section class="lq-card" style="margin-top:22px"><div class="lq-card-head"><div><h2>Developer handoff</h2><p>Copy these values into the partner implementation checklist.</p></div><a class="lq-btn soft" href="developer-starter.php">Open Developer Starter</a></div><pre class="lq-console-code">App: <?= lqr_h((string)$app['app_name']) ?>
Status: <?= lqr_h((string)$app['app_status']) ?> / <?= lqr_h((string)$app['approval_status']) ?>
Callback: <?= lqr_h((string)$app['callback_url']) ?>
Webhook: <?= lqr_h((string)$app['webhook_url']) ?>
Allowed programs:
<?= lqr_h($programText ?: 'none') ?>
Allowed templates:
<?= lqr_h($templateText ?: 'none') ?></pre></section>
</main></div></body></html>
