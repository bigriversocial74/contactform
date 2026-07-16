<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

mg_require_method('GET');
$user = mg_require_api_user();

mg_user_agent_api_run(static fn(): array => [
    'signals' => mg_personal_agent_contact_signals(mg_db(), (int)$user['id'], true),
]);
