<?php
declare(strict_types=1);

require_once __DIR__ . '/_action_center.php';

mg_require_method('POST');
mg_require_api_user();
$input=mg_input();
mg_require_csrf_for_write($input);

mg_fail(
    'Customer-side redemption has been retired. Present the Microgift to an authorized merchant location; the merchant completes redemption with its private location claim code.',
    410,
    ['canonical_endpoint'=>'/api/merchant/microgift-claim.php']
);
