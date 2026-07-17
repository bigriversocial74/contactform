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
    $runtime = mg_action_modal_read($root, 'assets/js/gift-action-center-runtime-v4.js');
    $actions = mg_action_modal_read($root, 'assets/js/gift-action-center-actions.js');
    $send = mg_action_modal_read($root, 'assets/js/gift-action-center-send-modal.js');
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
        str_contains($portal, 'function queueActionModalNormalization(modal)')
        && str_contains($portal, 'window.requestAnimationFrame(() =>')
        && str_contains($portal, "const desiredLabel = 'Review regift';")
        && str_contains($portal, 'primary.textContent.trim() !== desiredLabel')
        && !str_contains($portal, '/^Review Regift$/i'),
        'Regift normalization is frame-queued and idempotent instead of creating a MutationObserver loop',
        $failures,
        $passes
    );

    mg_action_modal_expect(
        str_contains($portal, "const cancel = actions && actions.querySelector('.mg-send-exact-secondary,[data-action-modal-close]')")
        && str_contains($portal, 'if (cancel) cancel.remove();')
        && str_contains($portal, "actions.dataset.singleAction = 'true'")
        && !str_contains($portal, "cancel.textContent = 'Cancel'")
        && str_contains($portal, "close.setAttribute('data-action-modal-close', '')"),
        'Regift removes the footer Cancel control while retaining the canonical header close button',
        $failures,
        $passes
    );

    mg_action_modal_expect(
        str_contains($portal, 'if (window.__mgGiftActionCenterModalPortalBooted) return;')
        && str_contains($portal, 'window.cancelAnimationFrame(normalizationFrame)')
        && str_contains($portal, "drawer.classList.remove('is-open', 'mg-load-envelope-drawer')"),
        'Overlay controller boots once and clears pending Regift and Load presentation state on close',
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
        str_contains($runtime, "actionButton(c,'send','Regift'")
        && str_contains($runtime, "actionButton(c,'claim','Claim'")
        && str_contains($runtime, "actionButton(c,'load','Load'")
        && str_contains($runtime, "actionButton(c,'follow-up','Follow Up'")
        && str_contains($runtime, "actionButton(c,'message','Message'")
        && str_contains($runtime, "actionButton(c,'tip','Tip'"),
        'Runtime v4 renders Inbox, Sent, and Claimed action buttons',
        $failures,
        $passes
    );

    mg_action_modal_expect(
        str_contains($runtime, "if(action==='load'){event.preventDefault();openDrawer(c);return;}")
        && str_contains($runtime, "if(action==='claim')")
        && str_contains($runtime, "mg:gift-claim:open")
        && str_contains($runtime, "if(['send','follow-up','message','tip'].includes(action))openModal(action,c);"),
        'Runtime v4 routes Load, Claim, Regift, Follow Up, Message, and Tip through current overlays',
        $failures,
        $passes
    );

    mg_action_modal_expect(
        str_contains($actions, "['send', 'follow-up', 'claim', 'message', 'tip'].includes(type)")
        && str_contains($actions, "app.addEventListener('mg:gift-action:submit'")
        && str_contains($send, "action.dataset.giftAction !== 'send'")
        && str_contains($send, 'window.requestAnimationFrame(function () { buildExactSendModal(row); });'),
        'Mutation and exact-recipient Regift controllers remain connected to Runtime v4 actions',
        $failures,
        $passes
    );

    mg_action_modal_expect(
        str_contains($include, '/assets/js/gift-action-center-runtime-v4.js?v=4.0.0')
        && str_contains($include, '/assets/js/gift-action-center-modal-portal.js?v=1.1.0')
        && str_contains($include, 'data-action-modal')
        && str_contains($include, 'data-gift-drawer')
        && !str_contains($include, 'gift-action-center-feed-v3.js')
        && !str_contains($include, 'gift-action-center-load-envelope.js'),
        'Shared include loads Runtime v4 with the portaled modal and drawer markup only',
        $failures,
        $passes
    );

    mg_action_modal_expect(
        str_contains($inbox, "require __DIR__ . '/includes/gift-action-center.php';")
        && str_contains($sent, "require __DIR__ . '/includes/gift-action-center.php';")
        && str_contains($claimed, "require __DIR__ . '/includes/gift-action-center.php';")
        && str_contains($inbox, '/assets/js/gift-action-center-send-modal.js')
        && str_contains($inbox, '/assets/js/gift-action-center-claim-modal.js')
        && !str_contains($sent, '/assets/js/gift-action-center.js')
        && !str_contains($claimed, '/assets/js/gift-action-center.js'),
        'Inbox, Sent, and Claimed share Runtime v4 while Inbox keeps specialized Regift and Claim controllers',
        $failures,
        $passes
    );

    mg_action_modal_expect(
        !str_contains($portal, 'fetch(')
        && !str_contains($portal, 'Microgifter.post(')
        && !str_contains($portal, 'localStorage')
        && !str_contains($portal, 'sessionStorage'),
        'Boot-order and Regift presentation changes create no mutation authority',
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
