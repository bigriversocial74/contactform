<?php
define('MG_ACCOUNT_VIEW', 'subscriptions');
ob_start();
require __DIR__ . '/account.php';
$page = ob_get_clean();
$script = '<script src="/assets/js/subscription-activation-status.js"></script>';
if (strpos($page, '</body>') !== false) {
    echo str_replace('</body>', $script . '</body>', $page);
} else {
    echo $page . $script;
}
