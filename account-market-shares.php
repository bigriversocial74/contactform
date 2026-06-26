<?php
const MG_ACCOUNT_VIEW = 'share_market';

ob_start();
include __DIR__ . '/account.php';
$html = (string)ob_get_clean();
$script = '<script src="/assets/js/dave-share-market-state.js"></script>';
$script .= '<script src="/assets/js/dave-share-market-review-feedback.js"></script>';
$script .= '<script src="/assets/js/dave-share-market-credit-reserve.js"></script>';

if (stripos($html, '</body>') !== false) {
    echo str_ireplace('</body>', $script . '</body>', $html);
} else {
    echo $html . $script;
}
