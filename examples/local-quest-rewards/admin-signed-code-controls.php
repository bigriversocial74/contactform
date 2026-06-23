<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
require __DIR__ . '/quest-controls.php';
require __DIR__ . '/admin-auth.php';

$config = lqr_config();
$state = lqr_load_state();
$quests = lqr_quests();
$message = '';
$error = '';

try {
    lqr_admin_require($state, $config);
    if (($_POST['action'] ?? '') === 'save_signed_controls') {
        $questId = (string)($_POST['quest_id'] ?? '');
        if (!isset($quests[$questId]) || !is_array($quests[$questId])) throw new RuntimeException('Quest not found.');
        $type = in_array((string)($_POST['signed_code_type'] ?? 'quest_checkin'), ['quest_checkin','reward_claim','venue_proof'], true) ? (string)$_POST['signed_code_type'] : 'quest_checkin';
        $controls = lqr_quest_controls($quests[$questId]);
        $controls['requires_signed_code'] = !empty($_POST['requires_signed_code']);
        $controls['signed_code_type'] = $type;
        lqr_update_quest_controls_file($questId, $controls);
        lqr_add_event($state, 'admin.signed_code_controls_saved', 'Signed QR controls updated.', ['quest_id'=>$questId, 'requires_signed_code'=>$controls['requires_signed_code'], 'signed_code_type'=>$type]);
        lqr_save_state($state);
        $quests = lqr_quests();
        $message = 'Signed QR controls saved.';
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Signed QR Controls</title><style>body{margin:0;background:#071225;color:#f5f9ff;font-family:Arial,sans-serif}.wrap{width:min(1060px,94%);margin:0 auto;padding:32px 0}.card{background:#0d1b2f;border:1px solid #24415f;border-radius:18px;padding:18px;margin:12px 0}.grid{display:grid;grid-template-columns:1fr 360px;gap:16px}.btn,button{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:0 12px;border-radius:12px;background:#172b47;color:#f5f9ff;border:1px solid #24415f;font-weight:800;text-decoration:none;cursor:pointer}.green{background:#4ade80;color:#062113;border:0}input,select{width:100%;min-height:38px;margin-top:5px;border-radius:10px;border:1px solid #24415f;background:#07192d;color:#f5f9ff;padding:6px 9px}label{display:block;margin-top:10px;font-size:12px;font-weight:800;color:#c8dbef}.tag{display:inline-flex;padding:5px 9px;border-radius:999px;background:#172b47;font-size:12px}.secure{background:rgba(251,191,36,.18);color:#fde68a}.open{background:rgba(74,222,128,.18);color:#c7ffd9}.notice{padding:10px 12px;border-radius:12px;background:#10243d}.error{background:rgba(251,113,133,.16);color:#ffd7df}p,small{color:#9db3cc}@media(max-width:900px){.grid{display:block}}</style></head><body><main class="wrap"><header><h1>Signed QR Controls</h1><p><a class="btn" href="admin.php">Admin home</a> <a class="btn" href="admin-quest-controls.php">Quest controls</a> <a class="btn" href="admin-signed-codes.php">Generate codes</a></p></header><?php if($message):?><p class="notice"><?= lqr_h($message) ?></p><?php endif;?><?php if($error):?><p class="notice error"><?= lqr_h($error) ?></p><?php endif;?><?php foreach($quests as $questId=>$quest): if(!is_array($quest)) continue; $controls=lqr_quest_controls($quest); ?><article class="card grid"><section><span class="tag <?= !empty($controls['requires_signed_code']) ? 'secure' : 'open' ?>"><?= !empty($controls['requires_signed_code']) ? 'Signed QR required' : 'Manual/open allowed' ?></span><h2><?= lqr_h((string)($quest['title'] ?? $questId)) ?></h2><p><?= lqr_h((string)($quest['description'] ?? '')) ?></p><p>Current signed code type: <strong><?= lqr_h((string)$controls['signed_code_type']) ?></strong></p></section><form method="post"><input type="hidden" name="quest_id" value="<?= lqr_h((string)$questId) ?>"><label><input type="checkbox" name="requires_signed_code" value="1" <?= !empty($controls['requires_signed_code'])?'checked':'' ?>> Require signed QR for completion</label><label>Signed code type<select name="signed_code_type"><option value="quest_checkin" <?= $controls['signed_code_type']==='quest_checkin'?'selected':'' ?>>Quest check-in</option><option value="reward_claim" <?= $controls['signed_code_type']==='reward_claim'?'selected':'' ?>>Reward claim</option><option value="venue_proof" <?= $controls['signed_code_type']==='venue_proof'?'selected':'' ?>>Venue proof</option></select></label><button class="green" name="action" value="save_signed_controls" style="width:100%;margin-top:12px">Save signed QR controls</button></form></article><?php endforeach; ?></main></body></html>
