<?php
declare(strict_types=1);

if (!function_exists('mg_json')) {
    function mg_json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('mg_ok')) {
    function mg_ok(array $data = [], string $message = 'OK', int $status = 200): never
    {
        mg_json(['ok' => true, 'message' => $message, 'data' => $data], $status);
    }
}

if (!function_exists('mg_fail')) {
    function mg_fail(string $message, int $status = 400, array $errors = []): never
    {
        mg_json(['ok' => false, 'message' => $message, 'errors' => $errors], $status);
    }
}

if (!function_exists('mg_input')) {
    function mg_input(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input') ?: '';
            $json = json_decode($raw, true);
            return is_array($json) ? $json : [];
        }
        return $_POST;
    }
}
