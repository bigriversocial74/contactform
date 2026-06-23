<?php
return [
    'app_name' => 'Local Quest Rewards',
    'app_public_url' => 'http://127.0.0.1:8090',
    'base_url' => 'https://microgifter.com',
    'api_key' => 'mg_test_replace_with_server_side_key',
    'default_program_id' => 'dist_prog_replace_me',
    'default_template_id' => 'tmpl_replace_me',
    'webhook_secret' => 'replace_with_rotated_webhook_signing_value',
    'mode' => 'test',
    'allow_sandbox_shortcut' => true,

    // Demo admin. Change these before exposing the app publicly.
    'admin' => [
        'username' => 'admin',
        'password' => 'change-me-admin-password',
    ],

    // Storage modes:
    // - json: zero-config local demo, writes data/state.json and data/quests.json
    // - mysql: real app mode target, schema lives in database/local_quest_rewards.sql
    'storage' => [
        'driver' => 'json',
        'dsn' => 'mysql:host=127.0.0.1;dbname=local_quest_rewards;charset=utf8mb4',
        'username' => 'local_quest_user',
        'password' => 'replace_with_database_password',
        'options' => [],
    ],
];
