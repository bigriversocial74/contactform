<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
function must_contain(string $path, string $needle, array &$failures): void
{
    $source = file_get_contents(dirname(__DIR__) . '/' . $path);
    if (!is_string($source) || !str_contains($source, $needle)) $failures[] = $path . ' missing ' . $needle;
}
must_contain('api/merchant/crm-message.php', "dirname(__DIR__) . '/gifts/_gift.php'", $failures);
must_contain('api/merchant/crm-message.php', 'mg_message_validate_body', $failures);
must_contain('assets/js/merchant-crm-messages.js', 'sendDirectMessage', $failures);
must_contain('assets/js/merchant-crm-messages.js', 'Message delivered to customer Messages.', $failures);
must_contain('assets/js/merchant-crm-messages.js', 'Message failed.', $failures);
must_contain('assets/js/merchant-crm-messages.js', 'event.stopImmediatePropagation', $failures);
must_contain('assets/js/merchant-crm-messages.js', 'thread/message proof', $failures);
if ($failures) {
    fwrite(STDERR, "CRM message send feedback validation failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "CRM message send feedback validation passed.\n";
