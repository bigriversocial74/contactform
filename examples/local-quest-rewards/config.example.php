<?php
declare(strict_types=1);

return [
    'app_name' => 'Local Quest Rewards',
    'app_public_url' => 'https://quest.example.com',
    'base_url' => 'https://microgifter.com',
    'api_key' => 'mg_test_replace_with_server_side_key',
    'default_program_id' => 'dist_prog_replace_me',
    'default_template_id' => 'tmpl_replace_me',
    'webhook_secret' => 'replace_with_rotated_webhook_signing_value',
    'mode' => 'test',
    'allow_sandbox_shortcut' => false,

    'security' => [
        'session_name' => 'LQRSESSID',
        'session_timeout_minutes' => 60,
        'csrf_field' => '_lqr_csrf',
        'csrf_ttl_minutes' => 120,
        'signed_code_ttl_minutes' => 15,
        'signed_code_secret' => 'replace_with_random_64_character_secret',
    ],

    // The installer creates the first owner directly in SQL.
    // Bootstrap credentials remain disabled in production configuration.
    'admin' => [
        'username' => '',
        'email' => '',
        'password' => '',
        'password_hash' => '',
        'bootstrap_enabled' => false,
        'reset_token_ttl_minutes' => 30,
    ],

    // Local Quest is SQL-only. JSON/file runtime storage is not supported.
    'storage' => [
        'driver' => 'mysql',
        'dsn' => 'mysql:host=127.0.0.1;dbname=local_quest_rewards;charset=utf8mb4',
        'username' => 'local_quest_user',
        'password' => 'replace_with_database_password',
        'options' => [],
    ],

    'installation' => [
        'schema_version' => '2026.07.10-production-foundation-v1',
        'installed_at' => '',
    ],
];
