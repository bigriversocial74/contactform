<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/store/_canvas_schema.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
$pdo = mg_db();

if (!mg_user_has_merchant_access($user, $pdo)) {
    mg_fail('Merchant access required.', 403);
}

mg_rate_limit('merchant_canvas.auto_chat_contained', 'user:' . (int)$user['id'], 30, 60);

// merchant_canvas_automatic_actions_disabled
mg_fail(
    'Automatic Store Canvas proximity chat is paused by production containment. Send an explicit manual message from the Customer CRM drawer.',
    409
);
