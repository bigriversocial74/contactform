<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/profiles.php';

mg_require_method('GET');
$user = mg_require_api_user();
$profile = mg_profile_ensure_for_user((int) $user['id']);

mg_ok([
    'profile' => mg_profile_public_payload($profile, true),
], 'Profile.');
