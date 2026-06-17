<?php
/**
 * Public shallow health check.
 *
 * This endpoint verifies that the application runtime and database dependency are
 * available, but its public response intentionally exposes only a generic service
 * status. Detailed dependency failures are written to the server error log.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function mg_health_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function mg_health_fail(string $publicMessage, int $status = 500): void
{
    mg_health_json([
        'ok' => false,
        'message' => $publicMessage,
        'data' => [
            'service' => 'microgifter',
            'status' => 'unavailable',
        ],
    ], $status);
}

function mg_health_log(Throwable|string $error, array $context = []): void
{
    $message = $error instanceof Throwable ? $error->getMessage() : $error;
    $type = $error instanceof Throwable ? get_class($error) : 'HealthCheckFailure';

    $safeContext = [];
    foreach ($context as $key => $value) {
        if (is_scalar($value) || $value === null) {
            $safeContext[$key] = $value;
        }
    }

    error_log('[microgifter-health] ' . json_encode([
        'type' => $type,
        'message' => $message,
        'context' => $safeContext,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    mg_health_fail('Method not allowed.', 405);
}

try {
    $configFile = __DIR__ . '/config.php';
    if (!is_file($configFile)) {
        mg_health_log('Config file is missing.', ['file' => 'api/config.php']);
        mg_health_fail('Service configuration is unavailable.');
    }

    $config = require $configFile;
    if (!is_array($config) || empty($config['db']) || !is_array($config['db'])) {
        mg_health_log('Config file did not return valid database settings.');
        mg_health_fail('Service configuration is unavailable.');
    }

    if (!class_exists('PDO')) {
        mg_health_log('PDO is not available on this PHP runtime.');
        mg_health_fail('Required runtime is unavailable.');
    }

    if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
        mg_health_log('PDO MySQL driver is not available on this PHP runtime.');
        mg_health_fail('Required runtime is unavailable.');
    }

    $db = $config['db'];
    $host = (string) ($db['host'] ?? 'localhost');
    $name = (string) ($db['name'] ?? '');
    $user = (string) ($db['user'] ?? '');
    $pass = (string) ($db['pass'] ?? '');
    $charset = (string) ($db['charset'] ?? 'utf8mb4');

    if ($name === '' || $user === '') {
        mg_health_log('Database configuration is incomplete.', [
            'db_name_present' => $name !== '',
            'db_user_present' => $user !== '',
        ]);
        mg_health_fail('Service configuration is incomplete.');
    }

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $host, $name, $charset);
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->query('SELECT 1');

    mg_health_json([
        'ok' => true,
        'message' => 'OK',
        'data' => [
            'service' => 'microgifter',
            'status' => 'available',
        ],
    ], 200);
} catch (Throwable $e) {
    mg_health_log($e);
    mg_health_fail('Health check failed.');
}
