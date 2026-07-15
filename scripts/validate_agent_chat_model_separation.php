<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$passes = 0;

$read = static function (string $path) use ($root): string {
    $full = $root . '/' . ltrim($path, '/');
    if (!is_file($full)) throw new RuntimeException('Missing required file: ' . $path);
    $content = file_get_contents($full);
    if (!is_string($content)) throw new RuntimeException('Unable to read required file: ' . $path);
    return $content;
};

$expect = static function (bool $condition, string $label) use (&$failures, &$passes): void {
    if ($condition) {
        $passes++;
        echo "PASS: {$label}\n";
        return;
    }
    $failures[] = $label;
    echo "FAIL: {$label}\n";
};

try {
    $core = $read('includes/personal-agent/core.php');
    $personalChat = $read('includes/personal-agent/chat.php');
    $merchantPlanner = $read('includes/ai/merchant-agent-planner.php');
    $merchantChat = $read('includes/ai/merchant-agent-chat.php');
    $migration = $read('database/stage_19d_customer_haiku_merchant_sonnet_defaults.sql');
    $manifest = require $root . '/config/migrations.php';
    $ordered = array_values($manifest['ordered_files'] ?? []);

    $expect(
        str_contains($core, "return ['claude-haiku-4-5-20251001', 'claude-haiku-4-5'];")
        && str_contains($core, 'function mg_personal_agent_model_order_sql')
        && str_contains($core, "WHEN m.model_key='claude-haiku-4-5-20251001' THEN 0"),
        'Customer Personal Agent declares Haiku 4.5 as its explicit default'
    );

    $expect(
        str_contains($personalChat, '$orderSql=mg_personal_agent_model_order_sql();')
        && str_contains($personalChat, "ORDER BY '.$orderSql.'")
        && str_contains($personalChat, "m.model_key NOT IN ('claude-3-5-haiku-latest','claude-3-5-haiku-20241022')"),
        'Customer chat uses the Haiku-first selector and excludes retired Haiku models'
    );

    $expect(
        str_contains($core, "'is_default' => mg_personal_agent_is_default_model")
        && str_contains($core, "m.model_key NOT IN ('claude-3-5-haiku-latest','claude-3-5-haiku-20241022')"),
        'Customer model settings present Haiku 4.5 as the default and hide retired Haiku'
    );

    $expect(
        str_contains($merchantPlanner, "m.model_key IN ('claude-sonnet-4-6','claude-3-5-sonnet-latest')")
        && str_contains($merchantPlanner, "ORDER BY (m.model_key = 'claude-sonnet-4-6') DESC")
        && !str_contains($merchantPlanner, 'claude-haiku-4-5'),
        'Merchant planner remains Sonnet-only with Sonnet 4.6 preferred'
    );

    $expect(
        str_contains($merchantChat, 'mg_ai_merchant_find_anthropic_model($pdo, null)')
        && str_contains($merchantChat, "m.model_key IN ('claude-sonnet-4-6','claude-3-5-sonnet-latest')")
        && !str_contains($merchantChat, 'claude-haiku-4-5'),
        'Merchant Agent chat remains on the separate Sonnet selector'
    );

    $expect(
        !str_contains($personalChat, 'mg_ai_merchant_find_anthropic_model')
        && !str_contains($merchantChat, 'mg_personal_agent_model(')
        && !str_contains($personalChat, 'automatic_escalation')
        && !str_contains($merchantChat, 'automatic_escalation'),
        'Customer and merchant chats remain separate without automatic cross-routing'
    );

    $expect(
        str_contains($migration, "'claude-haiku-4-5-20251001', 'Claude Haiku 4.5', 1, 0, 20, 200000, 64000")
        && str_contains($migration, "'customer_chat_default', TRUE")
        && str_contains($migration, "'merchant_chat_default', FALSE"),
        'Migration installs Haiku 4.5 with customer-specific metadata and official token limits'
    );

    $expect(
        str_contains($migration, "m.model_key = 'claude-sonnet-4-6'")
        && str_contains($migration, 'm.is_default = 1')
        && str_contains($migration, "model_key IN ('claude-3-5-haiku-latest', 'claude-3-5-haiku-20241022')"),
        'Migration retains Sonnet 4.6 globally and disables retired Haiku 3.5 entries'
    );

    $expect(
        str_contains($migration, 'UPDATE user_agent_settings s')
        && str_contains($migration, 'SET s.preferred_model_id = new_model.id')
        && str_contains($migration, "'stage_19d_customer_haiku_merchant_sonnet_defaults'"),
        'Migration moves retired customer preferences and records its canonical key'
    );

    $phase2 = array_search('20260714_personal_gifting_agent_phase2.sql', $ordered, true);
    $stage19d = array_search('stage_19d_customer_haiku_merchant_sonnet_defaults.sql', $ordered, true);
    $phase3 = array_search('20260714_personal_gifting_workflows_phase3.sql', $ordered, true);
    $expect(
        is_int($phase2) && is_int($stage19d) && is_int($phase3)
        && $phase2 < $stage19d && $stage19d < $phase3,
        'Canonical migration order installs the customer settings table before model preference migration'
    );
} catch (Throwable $error) {
    $failures[] = $error->getMessage();
    echo 'FAIL: ' . $error->getMessage() . "\n";
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("Agent chat model separation validation failed: %d failure(s), %d pass(es).\n", count($failures), $passes));
    foreach ($failures as $failure) fwrite(STDERR, " - {$failure}\n");
    exit(1);
}

echo "Agent chat model separation validation passed: {$passes} checks.\n";
