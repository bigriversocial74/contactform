<?php
declare(strict_types=1);
$accountView=$accountView??'overview';
$accountNav=[
'overview'=>['Overview','Account summary','/account-commerce.php#overview'],
'orders'=>['Orders','Orders and receipts','/account-commerce.php#orders'],
'items'=>['Items','Purchased and owned items','/account-commerce.php#items'],
'cart'=>['Cart','Checkout and payment draft','/cart.php'],
'inbox'=>['Inbox','Received and redeemable gifts','/inbox.php'],
'sent'=>['Sent','Gifts sent to recipients','/sent.php'],
'claimed'=>['Claimed','Merchant-redeemed gifts','/claimed.php'],
'messages'=>['Messages','Gift and recipient conversations','/messages.php'],
'notifications'=>['Notifications','Activity and account alerts','/notifications.php'],
'preferences'=>['Preferences','Notification delivery settings','/notification-preferences.php'],
];
?>
<button class="mg-account-sidebar-toggle" type="button" data-account-sidebar-toggle aria-expanded="false" aria-controls="account-sidebar">Account menu</button>
<div class="mg-account-sidebar-backdrop" data-account-sidebar-backdrop hidden></div>
<aside class="mg-account-sidebar" id="account-sidebar" data-account-sidebar>
<div class="mg-account-sidebar-head"><div><span class="mg-account-eyebrow">Customer account</span><strong>My Microgifter</strong></div><button type="button" data-account-sidebar-close aria-label="Close account menu">Close</button></div>
<nav class="mg-account-side-nav" aria-label="Customer account">
<?php foreach($accountNav as $key=>$item): ?>
<a class="<?= $accountView===$key?'is-active':'' ?>" href="<?= mg_e($item[2]) ?>" data-account-nav="<?= mg_e($key) ?>"><strong><?= mg_e($item[0]) ?></strong><span><?= mg_e($item[1]) ?></span></a>
<?php endforeach; ?>
</nav>
<div class="mg-account-sidebar-actions"><a class="mg-btn mg-btn-primary" href="/cart.php">Open cart</a><a class="mg-btn mg-btn-soft" href="/index.php">Continue shopping</a></div>
</aside>