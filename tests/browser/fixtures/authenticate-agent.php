<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/app.php';

$environment = (string) mg_env('MG_APP_ENV', '');
$browserAuthEnabled = (string) mg_env('MG_TEST_SKIP_AUTHENTICATED', '') === '1';
$remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$isLoopback = in_array($remoteAddress, ['127.0.0.1', '::1'], true);

if ($environment !== 'testing' || !$browserAuthEnabled || !$isLoopback) {
    http_response_code(404);
    exit('Not found.');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

session_regenerate_id(true);
$_SESSION['mg_user'] = [
    'id' => 999999,
    'public_id' => '99999999-9999-4999-8999-999999999999',
    'display_name' => 'Agent Test Owner',
    'email' => 'agent-test@example.test',
    'roles' => ['merchant'],
    'permissions' => [
        'agent.runtime.manage',
        'agent.strategies.manage',
        'agent.workflows.run',
        'agent.approvals.decide',
    ],
];

header('Cache-Control: private, no-store, max-age=0');
$view = strtolower(trim((string) ($_GET['view'] ?? 'strategies')));
if ($view === 'approvals') {
    header('Location: /agent.php?view=approvals', true, 302);
} else {
    header('Location: /agent.php?view=strategies', true, 302);
}
exit;
