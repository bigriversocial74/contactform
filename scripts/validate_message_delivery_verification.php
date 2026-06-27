<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
function must_contain(string $path, string $needle, array &$failures): void
{
    $source = file_get_contents(dirname(__DIR__) . '/' . $path);
    if (!is_string($source) || !str_contains($source, $needle)) $failures[] = $path . ' missing ' . $needle;
}
foreach ([
    'assets/js/notifications-source-metadata.js',
    'assets/css/message-delivery-proof.css',
    'scripts/smoke_crm_message_delivery.php',
] as $file) {
    if (!is_file($root . '/' . $file)) $failures[] = 'Missing ' . $file;
}
must_contain('notifications.php', 'notifications-source-metadata.js', $failures);
must_contain('notifications.php', 'message-delivery-proof.css', $failures);
must_contain('messages.php', 'message-delivery-proof.css', $failures);
must_contain('assets/js/notifications-source-metadata.js', 'Merchant CRM', $failures);
must_contain('assets/js/merchant-crm-messages.js', 'mg-crm-delivery-proof', $failures);
must_contain('assets/js/messages-center.js', 'Delivery context', $failures);
must_contain('api/messages/thread.php', 'mg_messages_source_context', $failures);
must_contain('api/messages/thread.php', 'merchant_crm_message', $failures);
must_contain('scripts/smoke_crm_message_delivery.php', 'message_thread_participants', $failures);
must_contain('scripts/smoke_crm_message_delivery.php', 'notification_created', $failures);
if ($failures) {
    fwrite(STDERR, "Message delivery verification validation failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "Message delivery verification validation passed.\n";
