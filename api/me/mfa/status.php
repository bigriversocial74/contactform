<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
mg_require_method('GET');
$user = mg_require_api_user();
mg_ok(mg_mfa_status(mg_db(), (int) $user['id']), 'MFA status loaded.');
