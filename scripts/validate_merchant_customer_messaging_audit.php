<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
function must_contain(string $path, string $needle, array &$failures): void
{
    $source = file_get_contents(dirname(__DIR__) . '/' . $path);
    if (!is_string($source) || !str_contains($source, $needle)) {
        $failures[] = $path . ' missing ' . $needle;
    }
}

must_contain('api/merchant/crm-message.php', 'resolved_user_id', $failures);
must_contain('api/merchant/crm-message.php', 'message_thread_participants', $failures);
must_contain('api/merchant/crm-message.php', "'message'", $failures);
must_contain('api/merchant/campaign-contacts.php', 'account_resolved_by_email', $failures);
must_contain('api/merchant/crm-bulk-message.php', 'mg_crm_bulk_resolve_message_contact_user', $failures);
must_contain('api/communications/dashboard.php', 'Merchant CRM', $failures);
must_contain('assets/js/merchant-crm.js', 'Message delivered to customer Messages', $failures);
must_contain('database/stage_12_crm_message_account_link_repair.sql', 'message_thread_participants', $failures);
must_contain('config/migrations.php', 'stage_12_crm_message_account_link_repair.sql', $failures);

if ($failures) {
    fwrite(STDERR, "Merchant/customer messaging audit validation failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Merchant/customer messaging audit validation passed.\n";
