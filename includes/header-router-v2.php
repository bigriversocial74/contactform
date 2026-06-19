<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

$is_app_page = in_array($header_mode, ['agent', 'account', 'crm', 'builder'], true);
$user = $is_app_page ? mg_require_auth() : mg_current_user();

require __DIR__ . '/header-renderer.php';
