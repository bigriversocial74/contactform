<?php
declare(strict_types=1);

$configPath = __DIR__ . '/config.php';
if (!is_file($configPath)) {
    $configPath = __DIR__ . '/config.example.php';
}
$config = require $configPath;

function mg_demo_value(array $source, string $key, string $fallback = ''): string
{
    $value = trim((string)($source[$key] ?? ''));
    return $value !== '' ? $value : $fallback;
}

function mg_demo_request(array $config, string $method, string $path, ?array $body = null, array $headers = []): array
{
    $baseUrl = rtrim((string)$config['base_url'], '/');
    $apiKey = (string)$config['api_key'];
    $url = $baseUrl . $path;
    $requestHeaders = array_merge([
        'Authorization: Bearer ' . $apiKey,
        'Accept: application/json',
    ], $headers);

    $payload = null;
    if ($body !== null) {
        $payload = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($payload)) {
            throw new RuntimeException('Unable to encode request JSON.');
        }
        $requestHeaders[] = 'Content-Type: application/json';
    }

    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('Unable to initialize curl.');
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $requestHeaders,
        CURLOPT_TIMEOUT => 30,
    ]);

    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    }

    $raw = curl_exec($ch);
    if ($raw === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException($error);
    }

    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $rawHeaders = substr((string)$raw, 0, $headerSize);
    $rawBody = substr((string)$raw, $headerSize);
    $decoded = json_decode($rawBody, true);

    return [
        'status' => $status,
        'headers' => $rawHeaders,
        'body' => is_array($decoded) ? $decoded : $rawBody,
    ];
}

$externalUserId = mg_demo_value($_POST, 'external_user_id', (string)$config['default_external_user_id']);
$externalEventId = mg_demo_value($_POST, 'external_event_id', (string)$config['default_external_event_id']);
$programId = mg_demo_value($_POST, 'program_id', (string)$config['program_id']);
$templateId = mg_demo_value($_POST, 'template_id', (string)$config['template_id']);
$linkedAccountId = mg_demo_value($_POST, 'linked_account_id');
$rewardId = mg_demo_value($_POST, 'reward_id');
$result = null;
$error = null;

try {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'list_programs') {
        $result = mg_demo_request($config, 'GET', '/api/public/v1/programs/index.php');
    } elseif ($action === 'sandbox_link') {
        $result = mg_demo_request($config, 'POST', '/api/public/v1/sandbox/linked-account.php', [
            'external_user_id' => $externalUserId,
        ]);
        if (is_array($result['body']) && isset($result['body']['linked_account_id'])) {
            $linkedAccountId = (string)$result['body']['linked_account_id'];
        }
    } elseif ($action === 'issue_reward') {
        $idempotencyKey = $externalEventId !== '' ? $externalEventId : 'demo-' . date('YmdHis');
        $result = mg_demo_request($config, 'POST', '/api/public/v1/rewards/issue.php', [
            'program_id' => $programId,
            'external_event_id' => $externalEventId,
            'event_type' => 'achievement_reward',
            'recipient' => ['linked_account_id' => $linkedAccountId],
            'reward' => ['template_id' => $templateId, 'quantity' => 1],
            'metadata' => ['source' => 'microgifter-api-test-app'],
        ], [
            'X-Request-ID: req_' . preg_replace('/[^a-zA-Z0-9_.:-]/', '_', $idempotencyKey),
            'X-Idempotency-Key: ' . $idempotencyKey,
        ]);
        if (is_array($result['body']) && isset($result['body']['reward_id'])) {
            $rewardId = (string)$result['body']['reward_id'];
        }
    } elseif ($action === 'check_status') {
        $result = mg_demo_request($config, 'GET', '/api/public/v1/rewards/status.php?id=' . rawurlencode($rewardId));
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

function mg_demo_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Microgifter API Test App</title>
<style>
body{margin:0;background:#f7faff;color:#071225;font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.wrap{width:min(1100px,92%);margin:0 auto;padding:36px 0 70px}.hero{background:#fff;border:1px solid #dce7f4;border-radius:24px;padding:28px;box-shadow:0 18px 46px rgba(15,23,42,.05)}h1{margin:0;font-size:42px;line-height:1;letter-spacing:-.06em}p{color:#5f7088;line-height:1.55}.grid{display:grid;grid-template-columns:360px minmax(0,1fr);gap:18px;margin-top:18px}.card{background:#fff;border:1px solid #dce7f4;border-radius:22px;padding:22px;box-shadow:0 18px 46px rgba(15,23,42,.04)}label{display:block;margin-top:12px;color:#40516a;font-size:13px;font-weight:900}input{width:100%;min-height:42px;border:1px solid #dce7f4;border-radius:12px;padding:0 12px;font:inherit}button{width:100%;min-height:44px;margin-top:10px;border:0;border-radius:12px;background:#195bd7;color:#fff;font-weight:950;cursor:pointer}.secondary{background:#071225}.muted{background:#eef6ff;color:#195bd7}.error{background:#fff8ed;border:1px solid #f4d7a1;color:#725116;border-radius:14px;padding:14px}pre{overflow:auto;margin:0;background:#071225;color:#eaf2ff;border-radius:16px;padding:18px;font-size:13px;line-height:1.55;white-space:pre-wrap}.hint{font-size:13px;color:#5f7088}@media(max-width:860px){.grid{grid-template-columns:1fr}h1{font-size:34px}}
</style>
</head>
<body>
<div class="wrap">
  <section class="hero">
    <h1>Microgifter API Test App</h1>
    <p>This standalone app validates the public developer docs by acting like a third-party backend that lists programs, creates a sandbox linked account, issues a reward, and checks status.</p>
    <p class="hint">Using config file: <strong><?= mg_demo_h(basename($configPath)) ?></strong></p>
  </section>

  <div class="grid">
    <form class="card" method="post">
      <h2>Configuration</h2>
      <label>External user ID</label>
      <input name="external_user_id" value="<?= mg_demo_h($externalUserId) ?>">

      <label>External event ID / idempotency key</label>
      <input name="external_event_id" value="<?= mg_demo_h($externalEventId) ?>">

      <label>Program ID</label>
      <input name="program_id" value="<?= mg_demo_h($programId) ?>">

      <label>Template ID</label>
      <input name="template_id" value="<?= mg_demo_h($templateId) ?>">

      <label>Linked account ID</label>
      <input name="linked_account_id" value="<?= mg_demo_h($linkedAccountId) ?>" placeholder="Returned by sandbox linked-account call">

      <label>Reward ID</label>
      <input name="reward_id" value="<?= mg_demo_h($rewardId) ?>" placeholder="Returned by reward issue call">

      <button name="action" value="list_programs" class="muted">List programs</button>
      <button name="action" value="sandbox_link">Create sandbox linked account</button>
      <button name="action" value="issue_reward">Issue reward</button>
      <button name="action" value="check_status" class="secondary">Check status</button>
    </form>

    <section class="card">
      <h2>Result</h2>
      <?php if ($error !== null): ?>
        <div class="error"><?= mg_demo_h($error) ?></div>
      <?php elseif ($result !== null): ?>
        <pre><?= mg_demo_h(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
      <?php else: ?>
        <p>Click the actions in order. Any missing setup value or unexpected API response is a documentation bug.</p>
      <?php endif; ?>
    </section>
  </div>
</div>
</body>
</html>
