<?php
/**
 * Microgifter / Training Lab config example.
 *
 * Rename this file:
 *   config-example.php
 *
 * To:
 *   config.php
 *
 * Put it in the project root, next to the labs folder:
 *
 * /contactform/
 *   config.php
 *   labs/
 *
 * Future build zips should include config-example.php only.
 * They should never include or overwrite config.php.
 */

return [
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'YOUR_DATABASE_NAME',
        'username' => 'YOUR_DATABASE_USER',
        'password' => 'YOUR_DATABASE_PASSWORD',
        'charset' => 'utf8mb4',
    ],

    'training_lab' => [
        'mode' => 'database',
        'proof_records_only_no_real_uploads' => true,
        'reward_events_only_no_wallet_balance_changes' => true,
        'payments_enabled' => false,
        'claim_redeem_enabled' => false,
        'use_existing_microgifter_auth' => true,
    ],
];
