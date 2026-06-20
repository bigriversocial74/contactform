<?php
declare(strict_types=1);

require_once __DIR__ . '/_action_center.php';

mg_require_method('POST');
mg_require_api_user();
$input=mg_input();
mg_require_csrf_for_write($input);

mg_fail('Resend has been retired. Use Follow Up to message the current recipient without changing ownership or delivery history.',410);
