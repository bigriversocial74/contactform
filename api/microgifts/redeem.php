<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

mg_require_method('POST');
mg_require_api_user();
$input=mg_input();
mg_require_csrf_for_write($input);

mg_fail(
    'Direct customer redemption has been retired. Present the Microgift to an authorized merchant location; the merchant completes redemption using its private location claim code.',
    410,
    [
        'canonical_endpoint'=>'/api/merchant/microgift-claim.php',
        'customer_action'=>'Present this Microgift to the merchant.',
    ]
);
