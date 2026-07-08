<?php
declare(strict_types=1);

if (!function_exists('mg_public_campaign_embed_cors')) {
    function mg_public_campaign_embed_cors(): void
    {
        $origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
        if ($origin !== '') {
            header('Vary: Origin', false);
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Accept, X-Requested-With');
            header('Access-Control-Max-Age: 86400');
        }

        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
