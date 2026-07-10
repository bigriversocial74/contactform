<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/training-lab-launch.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
mg_rate_limit('training_lab.signed_launch', (string) $user['id'], 20, 300);

try {
    $handoff = mg_training_lab_build_assertion($user);
    $target = (string) ($handoff['target_url'] ?? '');
    if (!mg_training_lab_target_is_safe($target)) {
        mg_fail('Training Lab target is not configured safely.', 503);
    }

    $parts = parse_url($target);
    $origin = strtolower((string) ($parts['scheme'] ?? '')) . '://' . (string) ($parts['host'] ?? '');
    if (!empty($parts['port'])) {
        $origin .= ':' . (int) $parts['port'];
    }

    mg_audit('training_lab.signed_launch', 'account', [
        'target_origin' => $origin,
        'role' => (string) ($handoff['payload']['role'] ?? 'participant'),
        'expires_at' => (int) ($handoff['payload']['exp'] ?? 0),
    ], (int) $user['id']);
    mg_event('training_lab.launch_created', [
        'role' => (string) ($handoff['payload']['role'] ?? 'participant'),
    ], (int) $user['id']);

    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, private');
    header('Pragma: no-cache');
    header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; script-src 'unsafe-inline'; form-action " . $origin . "; frame-ancestors 'none'; base-uri 'none'");

    $safeTarget = htmlspecialchars($target, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeAssertion = htmlspecialchars((string) $handoff['assertion'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>Opening Training Lab</title><style>body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;margin:0;min-height:100vh;display:grid;place-items:center;background:#f6f7fb;color:#111827}.card{width:min(92vw,560px);padding:32px;border:1px solid #e5e7eb;border-radius:22px;background:#fff;box-shadow:0 18px 50px rgba(15,23,42,.08);text-align:center}button{border:0;border-radius:999px;padding:13px 22px;font:inherit;font-weight:700;background:#111827;color:#fff;cursor:pointer}p{color:#64748b;line-height:1.6}</style></head><body>';
    echo '<main class="card"><h1>Opening Training Lab</h1><p>Your signed Microgifter account handoff is ready. You will be redirected securely.</p>';
    echo '<form id="training-lab-handoff" method="post" action="' . $safeTarget . '">';
    echo '<input type="hidden" name="identity_assertion" value="' . $safeAssertion . '">';
    echo '<noscript><button type="submit">Continue to Training Lab</button></noscript></form></main>';
    echo '<script>document.getElementById("training-lab-handoff").submit();</script></body></html>';
    exit;
} catch (Throwable $e) {
    mg_security_log('error', 'training_lab.launch_failed', 'Training Lab signed launch failed.', [
        'exception_class' => $e::class,
    ], (int) ($user['id'] ?? 0));
    mg_fail('Unable to open Training Lab right now.', 503);
}
