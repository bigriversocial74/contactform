<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

mg_require_method('GET');

$user = mg_refresh_session_user();

if (!$user) {
    mg_ok([
        'user' => null,
        'authenticated' => false,
    ], 'Guest.');
}

mg_ok([
    'user' => $user,
    'authenticated' => true,
], 'Authenticated.');
