<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/profiles.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);

$user = mg_require_api_user();
$profile = mg_profile_update((int) $user['id'], $input);

mg_ok([
    'profile' => mg_profile_public_payload($profile, true),
], 'Profile updated.');
