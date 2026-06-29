<?php
/**
 * Training Lab DB bootstrap.
 *
 * Expected project structure:
 *
 * /contactform/
 *   config.php
 *   config-example.php
 *   labs/
 *     includes/training-lab-db.php
 *
 * This file intentionally loads only config.php from the project root.
 * It does not use labs/config, config/training-lab-db.local.php,
 * or any second database config file.
 */

if (!function_exists('training_lab_project_root')) {
    function training_lab_project_root(): string
    {
        return dirname(__DIR__, 2);
    }
}

if (!function_exists('training_lab_expected_config_path')) {
    function training_lab_expected_config_path(): string
    {
        return training_lab_project_root() . DIRECTORY_SEPARATOR . 'config.php';
    }
}

if (!function_exists('training_lab_config_exists')) {
    function training_lab_config_exists(): bool
    {
        return is_file(training_lab_expected_config_path()) && is_readable(training_lab_expected_config_path());
    }
}

if (!function_exists('training_lab_load_config')) {
    function training_lab_load_config(): array
    {
        $path = training_lab_expected_config_path();

        if (!is_file($path) || !is_readable($path)) {
            return [
                '_loaded' => false,
                '_error' => 'config.php was not found or is not readable at the project root.',
                '_expected_path' => $path,
            ];
        }

        $config = require $path;

        if (!is_array($config)) {
            return [
                '_loaded' => false,
                '_error' => 'config.php must return an array.',
                '_expected_path' => $path,
            ];
        }

        $config['_loaded'] = true;
        $config['_expected_path'] = $path;

        return $config;
    }
}

if (!function_exists('training_lab_db_settings')) {
    function training_lab_db_settings(array $config): array
    {
        $db = $config['db'] ?? $config;

        return [
            'host' => $db['host'] ?? $db['DB_HOST'] ?? null,
            'port' => (int)($db['port'] ?? $db['DB_PORT'] ?? 3306),
            'database' => $db['database'] ?? $db['dbname'] ?? $db['DB_DATABASE'] ?? $db['DB_NAME'] ?? null,
            'username' => $db['username'] ?? $db['user'] ?? $db['DB_USERNAME'] ?? $db['DB_USER'] ?? null,
            'password' => $db['password'] ?? $db['pass'] ?? $db['DB_PASSWORD'] ?? $db['DB_PASS'] ?? '',
            'charset' => $db['charset'] ?? $db['DB_CHARSET'] ?? 'utf8mb4',
        ];
    }
}

if (!function_exists('training_lab_pdo')) {
    function training_lab_pdo(): ?PDO
    {
        static $pdo = null;
        static $attempted = false;

        if ($attempted) {
            return $pdo;
        }

        $attempted = true;
        $config = training_lab_load_config();

        if (empty($config['_loaded'])) {
            return null;
        }

        $db = training_lab_db_settings($config);

        if (!$db['host'] || !$db['database'] || !$db['username']) {
            return null;
        }

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $db['host'],
                $db['port'],
                $db['database'],
                $db['charset']
            );

            $pdo = new PDO($dsn, $db['username'], $db['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            return $pdo;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('training_lab_table_exists')) {
    function training_lab_table_exists(string $table): bool
    {
        $pdo = training_lab_pdo();

        if (!$pdo) {
            return false;
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table'
            );
            $stmt->execute(['table' => $table]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('training_lab_db_status')) {
    function training_lab_db_status(): array
    {
        $config = training_lab_load_config();
        $db = training_lab_db_settings($config);
        $pdo = training_lab_pdo();

        $tables = [
            'training_campaigns',
            'training_campaign_tasks',
            'training_participants',
            'training_proof_submissions',
            'training_reviews',
            'training_action_receipts',
            'training_reward_rules',
            'training_reward_events',
            'training_streaks',
            'training_events',
            'training_permission_catalog',
        ];

        $tableStatus = [];
        foreach ($tables as $table) {
            $tableStatus[$table] = $pdo ? training_lab_table_exists($table) : false;
        }

        return [
            'db_configured' => (bool)$pdo,
            'config' => [
                'expected_path' => $config['_expected_path'] ?? training_lab_expected_config_path(),
                'file_exists' => training_lab_config_exists(),
                'loaded' => !empty($config['_loaded']),
                'error' => $config['_error'] ?? null,
                'database_name_present' => !empty($db['database']),
                'username_present' => !empty($db['username']),
                'host_present' => !empty($db['host']),
            ],
            'tables' => $tableStatus,
            'safe_boundaries' => [
                'proof_records_only_no_real_uploads' => true,
                'reward_events_only_no_wallet_balance_changes' => true,
                'no_payments' => true,
                'no_claim_redeem_logic' => true,
                'existing_auth_required_later' => true,
            ],
        ];
    }
}
