<?php
declare(strict_types=1);

$path = __DIR__ . '/apply_public_donations_phase2_patch.php';
$text = file_get_contents($path);
if (!is_string($text)) throw new RuntimeException('Unable to read Phase 2 patch script.');
$old = <<<'PHP'
    $text = phase2_replace($text,
        "            'internal_only' => !empty(\$definition['internal_only']),\n            'public_enabled' => !empty(\$definition['public_enabled']),\n        ],",
        "            'internal_only' => !empty(\$definition['internal_only']),\n            'public_enabled' => !empty(\$definition['public_enabled']),\n            'public_transactional' => mg_campaign_type_public_transactional((string)\$definition['key']),\n            'public_mode' => mg_campaign_type_public_mode((string)\$definition['key']),\n        ],",
        'campaign type options metadata', 1);
    $text = phase2_replace($text,
        "            'internal_only' => !empty(\$definition['internal_only']),\n            'public_enabled' => !empty(\$definition['public_enabled']),\n        ],",
        "            'internal_only' => !empty(\$definition['internal_only']),\n            'public_enabled' => !empty(\$definition['public_enabled']),\n            'public_transactional' => mg_campaign_type_public_transactional((string)\$definition['key']),\n            'public_mode' => mg_campaign_type_public_mode((string)\$definition['key']),\n        ],",
        'campaign client registry metadata', 1);
PHP;
$new = <<<'PHP'
    $text = phase2_replace($text,
        "            'internal_only' => !empty(\$definition['internal_only']),\n            'public_enabled' => !empty(\$definition['public_enabled']),\n        ],",
        "            'internal_only' => !empty(\$definition['internal_only']),\n            'public_enabled' => !empty(\$definition['public_enabled']),\n            'public_transactional' => mg_campaign_type_public_transactional((string)\$definition['key']),\n            'public_mode' => mg_campaign_type_public_mode((string)\$definition['key']),\n        ],",
        'campaign public metadata', 2);
PHP;
if (substr_count($text, $old) !== 1) throw new RuntimeException('Phase 2 metadata patch block changed unexpectedly.');
$text = str_replace($old, $new, $text);
if (file_put_contents($path, $text) === false) throw new RuntimeException('Unable to update Phase 2 patch script.');
echo "Phase 2 patch-script metadata assertion corrected.\n";
