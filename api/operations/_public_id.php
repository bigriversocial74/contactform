<?php
declare(strict_types=1);

if (!function_exists('mg_public_uuid')) {
    function mg_public_uuid(): string
    {
        $value = mg_db()->query('SELECT UUID()')->fetchColumn();
        if (!is_string($value) || $value === '') {
            throw new RuntimeException('Unable to generate public UUID.');
        }
        return $value;
    }
}
