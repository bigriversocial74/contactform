<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
require __DIR__ . '/admin-auth.php';

$config = lqr_config();
$state = lqr_load_state();
$quests = lqr_quests();
$message = '';
$error = '';
$signedCode = '';
$payload = [];
$printTitle = 'Signed Quest Code';

try {
    $admin = lqr_admin_require($state, $config);
    if (($_POST['action'] ?? '') === 'generate_signed_code') {
        $questId = trim((string)($_POST['quest_id'] ?? ''));
        if ($questId === '' || empty($quests[$questId]) || !is_array($quests[$questId])) throw new RuntimeException('Choose a valid quest.');
        $venueId = trim((string)($_POST['venue_id'] ?? '')) ?: 'default_venue';
        $codeType = trim((string)($_POST['code_type'] ?? 'quest_checkin')) ?: 'quest_checkin';
        $payload = [
            'type' => $codeType,
            'quest_id' => $questId,
            'venue_id' => $venueId,
            'issued_by_admin' => (string)($admin['id'] ?? ''),
        ];
        $printTitle = (string)($quests[$questId]['title'] ?? 'Signed Quest Code');
        $signedCode = lqr_signed_payload($config, $payload);
        lqr_add_event($state, 'admin.signed_code_created', 'Admin generated signed quest code.', ['quest_id'=>$questId, 'venue_id'=>$venueId, 'code_type'=>$codeType, 'admin_id'=>($admin['id'] ?? '')]);
        lqr_save_state($state);
        $message = 'Signed quest code generated.';
    }
} catch (Throwable $e) { $error = $e->getMessage(); }

?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Signed Quest Codes</title><style>body{margin:0;background:#071225;color:#f5f9ff;font-family:Arial,sans-serif}.wrap{width:min(980px,94%);margin:0 auto;padding:32px 0}.card{background:#0d1b2f;border:1px solid #24415f;border-radius:18px;padding:18px;margin:12px 0}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.btn,button{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:0 12px;border-radius:12px;background:#172b47;color:#f5f9ff;border:1px solid #24415f;font-weight:800;text-decoration:none;cursor:pointer}.green{background:#4ade80;color:#062113;border:0}input,select,textarea{width:100%;min-height:38px;margin-top:5px;border-radius:10px;border:1px solid #24415f;background:#07192d;color:#f5f9ff;padding:6px 9px}textarea{min-height:150px;font-family:ui-monospace,Menlo,Consolas,monospace}label{display:block;margin-top:9px;font-size:12px;font-weight:800;color:#c8dbef}.notice{padding:10px 12px;border-radius:12px;background:#10243d;word-break:break-word}.error{background:rgba(251,113,133,.16);color:#ffd7df}p,small{color:#9db3cc}@media(max-width:900px){.grid{display:block}}</style></head><body><main class="wrap"><header><h1>Signed Quest Codes</h1><p><a class="btn" href="admin.php">Admin home</a> <a class="btn" href="admin-quest-controls.php">Quest controls</a> <a class="btn" href="admin-signed-code-controls.php">Signed QR controls</a></p></header><?php if($message):?><p class="notice"><?= lqr_h($message) ?></p><?php endif;?><?php if($error):?><p class="notice error"><?= lqr_h($error) ?></p><?php endif;?><section class="grid"><form method="post" class="card"><h2>Generate signed code</h2><label>Quest<select name="quest_id" required><option value="">Choose quest</option><?php foreach($quests as $questId=>$quest): if(!is_array($quest)) continue; ?><option value="<?= lqr_h((string)$questId) ?>"><?= lqr_h((string)($quest['title'] ?? $questId)) ?></option><?php endforeach; ?></select></label><label>Code type<select name="code_type"><option value="quest_checkin">Quest check-in</option><option value="reward_claim">Reward claim handoff</option><option value="venue_proof">Venue proof</option></select></label><label>Venue / sponsor ID<input name="venue_id" placeholder="demo_venue"></label><button class="green" name="action" value="generate_signed_code" style="width:100%;margin-top:12px">Generate</button></form><section class="card"><h2>Result</h2><?php if($signedCode): ?><label>Signed payload<textarea readonly><?= lqr_h($signedCode) ?></textarea></label><p>Use this signed payload as QR content. The app can verify the signature, type, timestamp, and nonce through <code>lqr_verify_signed_payload()</code>.</p><p><a class="btn green" href="signed-code-print.php?title=<?= rawurlencode($printTitle) ?>&code=<?= rawurlencode($signedCode) ?>">Open printable QR</a></p><?php else: ?><p>No signed code generated yet.</p><?php endif; ?></section></section><section class="card"><h2>Production note</h2><p>This page generates signed QR content for protected quests. Use Signed QR Controls to enable enforcement per quest.</p></section></main></body></html>
