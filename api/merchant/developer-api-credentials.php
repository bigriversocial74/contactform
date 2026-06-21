<?php
declare(strict_types=1);
require_once __DIR__ . '/_merchant.php';
mg_require_method('POST');
$user = mg_require_permission('merchant.developer_api.manage');
$input = mg_input();
mg_require_csrf_for_write($input);
mg_fail('Credential create and revoke actions are reserved for the public API auth build step.', 501);
