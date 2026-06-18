<?php
declare(strict_types=1);

return [
    'public_pages' => [
        'index.php','learn-more.php','signin.php','signup.php','forgot-password.php','reset-password.php','verify-email.php',
        'privacy.php','terms.php','pitch-deck.php','product.php','store.php','profile.php','merchant.php',
    ],
    'private_page_patterns' => [
        '/^account(?:-[a-z0-9-]+)?\.php$/',
        '/^agent\.php$/',
        '/^archived-agents\.php$/',
        '/^build\.php$/',
        '/^inbox\.php$/',
        '/^sent\.php$/',
        '/^claimed\.php$/',
        '/^messages\.php$/',
        '/^notifications\.php$/',
        '/^notification-preferences\.php$/',
        '/^sales-crm\.php$/',
        '/^merchant-[a-z0-9-]+\.php$/',
        '/^orders?\.php$/',
        '/^receipts?\.php$/',
        '/^claims?\.php$/',
        '/^redemptions?\.php$/',
    ],
    'public_api_patterns' => [
        '#^api/health\.php$#',
        '#^api/auth/(?:signin|signup|forgot-password|reset-password|verify-email|csrf)\.php$#',
        '#^api/public/#',
        '#^api/webhooks/#',
    ],
    'private_api_prefixes' => [
        'api/account/','api/admin/','api/agent/','api/catalog/','api/communications/','api/gifts/',
        'api/merchant/','api/merchants/','api/messages/','api/microgifts/','api/notifications/',
        'api/orders/','api/payments/','api/pppm/','api/security/','api/users/','api/vp3/',
    ],
    'write_methods' => ['POST','PUT','PATCH','DELETE'],
    'csrf_exempt_patterns' => [
        '#^api/webhooks/#',
        '#/webhook\.php$#',
        '#^api/auth/(?:signin|signup|forgot-password|reset-password|verify-email)\.php$#',
    ],
];
