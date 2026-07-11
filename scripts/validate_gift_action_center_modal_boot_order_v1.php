<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$passes = 0;

function mg_action_modal_read(string $root, string $path): string
{
    $full = $root . '/' . ltrim($path, '/');
    if (!is_file($full)) {
        throw new RuntimeException("Missing required file: {$path}");
    }
    $content = file_get_contents($full);
    if (!is_string($content)) {
        throw new RuntimeException("Unable to read required file: {$path}");
    }
    return $content;
}

function mg_action_modal_expect(bool $condition, string $label, array &$failures, int &$passes): void
{
    if ($condition) {
        $passes++;
        echo "PASS: {$label}\n";
        return;
    }
    $failures[] = $label;
    echo "FAIL: {$label}\n";
}

try {
    $portal = mg_action_modal_read($root, 'assets/js/gift-action-center-modal-portal.js');
    $center = mg_action_modal_read($root, 'assets/js/gift-action-center.js');
    $actions = mg_action_modal_read($root, 'assets/js/gift-action-center-actions.js');
    $send = mg_action_modal_read($root, 'assets/js/gift-action-center-send-modal.js');
    $load = mg_action_modal_read($root, 'assets/js/gift-action-center-load-envelope.js');
    $include = mg_action_modal_read($root, 'includes/gift-action-center.php');
    $inbox = mg_action_modal_read($root, 'inbox.php');
    $sent = mg_action_modal_read($root, 'sent.php');
    $claimed = mg_action_modal_read($root, 'claimed.php');

    mg_action_modal_expect(
        str_contains($portal, 'window.setTimeout(portalGiftCenterOverlays, 0);')
        && str_contains($portal, 'Page-level Action Center controllers also initialize on DOMContentLoaded')
        && strpos($portal, 'ensureActionModalCloseButtons();') < strpos($portal, 'window.setTimeout(portalGiftCenterOverlays, 0);'),
        'Modal and drawer portal runs after Action Center DOMContentLoaded initializers',
        $failures,
        $passes
    );

    mg_action_modal_expect(
        str_contains($portal, "document.querySelectorAll('.mg-action-modal')")
        && str_contains($portal, "document.querySelectorAll('.mg-gift-drawer')")
        && str_contains($portal, "event.target.closest('[data-action-modal-close]')")
        && str_contains($portal, "event.target.closest('[data-gift-drawer-close]')"),
        'Portaled action modal and Load drawer retain global close handling',
        $failures,
        $passes
    );

    mg_action_modal_expect(
        str_contains($center, "data-gift-action=\"send\"")
        && str_contains($center, "data-gift-action=\"claim\"")
        && str_contains($center, "data-gift-action=\"load\"")
        && str_contains($center, "data-gift-action=\"message\"")
        && str_contains($center, "data-gift-action=\"tip\"")
        && str_contains($center, "data-gift-action=\"follow-up\""),
        'Inbox, Sent, and Claimed action buttons remain rendered by the shared controller',
        $failures,
        $passes
    );

    mg_action_modal_expect(
        str_contains($center, 'function openModal(action, item)')
        && str_contains($center, 'function openContent(item)')
        && str_contains($center, "if (type === 'load') openContent(item)")
        && str_contains($center, 'else openModal(type, item)'),
        'Shared click routing opens either the action modal or Load drawer',
        $failures,
        $passes
    );

    mg_action_modal_expect(
        str_contains($actions, "['send', 'follow-up', 'claim', 'message', 'tip'].includes(type)")
        && str_contains($actions, "app.addEventListener('mg:gift-action:submit'")
        && str_contains($send, "action.dataset.giftAction !== 'send'")
        && str_contains($load, "[data-gift-action=\"load\"]"),
        'Regift, Follow Up, Message, Tip, Claim, and Load controllers remain connected',
        $failures,
        $passes
    );

    mg_action_modal_expect(
        str_contains($include, '/assets/js/gift-action-center-modal-portal.js')
        && str_contains($include, 'data-action-modal')
        && str_contains($include, 'data-gift-drawer'),
        'Shared Action Center include provides the portaled modal and drawer markup',
        $failures,
        $passes
    );

    mg_action_modal_expect(
        str_contains($inbox, '/assets/js/gift-action-center.js')
        && str_contains($inbox, '/assets/js/gift-action-center-load-envelope.js')
        && str_contains($inbox, '/assets/js/gift-action-center-send-modal.js')
        && str_contains($sent, '/assets/js/gift-action-center.js')
        && str_contains($sent, '/assets/js/gift-action-center-load-envelope.js')
        && str_contains($claimed, '/assets/js/gift-action-center.js'),
        'Inbox, Sent, and Claimed load the controllers required by their visible actions',
        $failures,
        $passes
    );

    mg_action_modal_expect(
        !str_contains($portal, 'fetch(')
        && !str_contains($portal, 'Microgifter.post(')
        && !str_contains($portal, 'localStorage')
        && !str_contains($portal, 'sessionStorage'),
        'Boot-order repair changes presentation timing without creating mutation authority',
        $failures,
        $passes
    );
} catch (Throwable $error) {
    $failures[] = $error->getMessage();
    echo 'FAIL: ' . $error->getMessage() . "\n";
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("Gift Action Center modal boot-order validation failed: %d failure(s), %d pass(es).\n", count($failures), $passes));
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "Gift Action Center modal boot-order validation passed: {$passes} checks.\n";
