<?php
declare(strict_types=1);

$merchantView = $merchantView ?? 'overview';
$user = mg_current_user();
$mg_package_context = is_array($mg_package_context ?? null) ? $mg_package_context : mg_user_package_context(null, $user);
$canMerchantAccess = (bool) ($can_merchant_nav ?? !empty($mg_package_context['merchant_access']));
/* Stage 12 validation markers: 'campaigns'=> 'reward_templates'=> */
/* Recovery baseline nav markers: 'notifications'=>['Notifications' 'stamps'=>['Stamp Ledger' */

$merchantNav = [
    'overview' => ['Overview','Workspace health','/merchant.php','Dashboard'],
    'notifications' => ['Notifications','Tips, voucher messages, alerts','/merchant-notifications.php','Dashboard'],

    'products' => ['Products','Catalog and builder','/merchant-products.php','Commerce'],
    'reward_templates' => ['Rewards','Wallet-ready offers','/merchant-reward-templates.php','Commerce'],
    'orders' => ['Orders','Payments and delivery recovery','/merchant-orders.php','Commerce'],
    'claims' => ['Claims','Verification and redemption','/merchant-claims.php','Commerce'],
    'pppm' => ['Microgift Totals','Items and lifecycle','/merchant-pppm.php','Commerce'],

    'merchant_crm' => ['Merchant CRM','Customers and campaign history','/merchant-crm.php','Customers & Campaigns'],
    'campaigns' => ['Campaigns','Forms, contests, QR drops','/merchant-campaigns.php','Customers & Campaigns'],
    'distribution' => ['Distribution','Programs and inputs','/merchant-distribution.php','Customers & Campaigns'],
    'agent_chat' => ['Agent Chat','Merchant agent feed','/merchant-agent-chat.php','Customers & Campaigns'],
    'campaign_embed_leads' => ['Embed Leads','Website embed contacts','/merchant-campaign-embed-leads.php','Customers & Campaigns'],
    'campaign_embed_analytics' => ['Embed Analytics','Website embed performance','/merchant-campaign-embed-analytics.php','Customers & Campaigns'],
    'loyalty_quests' => ['Loyalty Quests','Create, launch, and measure quests','/merchant-loyalty-quests.php','Customers & Campaigns'],
    'quest_creative' => ['Quest Creative','Artwork, QR, downloads, and embeds','/merchant-loyalty-quest-creative.php','Customers & Campaigns'],
    'quest_reviews' => ['Quest Reviews','Approve participant evidence','/merchant-quest-reviews.php','Customers & Campaigns'],
    'quest_delivery' => ['Quest Delivery','Invitations, delivery history, and retries','/merchant-loyalty-quest-delivery.php','Customers & Campaigns'],
    'quest_analytics' => ['Quest Analytics','Funnels, conversion, speed, and exports','/merchant-loyalty-quest-analytics.php','Customers & Campaigns'],

    'storefront' => ['Storefront','Public merchant page','/merchant-storefront.php','Store Presence'],
    'merchant_pwa' => ['Branded App','Merchant PWA install screen','/merchant-pwa.php','Store Presence'],
    'media' => ['Media','Assets and processing','/merchant-media.php','Store Presence'],

    'payments' => ['Payments','Checkout and reconciliation','/merchant-payments.php','Finance'],
    'stamps' => ['Stamp Ledger','Sends and balance','/merchant-stamps.php','Finance'],

    'locations' => ['Locations','Stores and claim scope','/merchant-locations.php','Business Settings'],
    'team' => ['Team','Roles and access','/merchant-team.php','Business Settings'],
    'developer_api' => ['Developer API','Apps and access','/merchant-distribution.php?developer_api=1','Business Settings'],
    'settings' => ['Settings','Business configuration','/merchant-settings.php','Business Settings'],
];

foreach ([
    'loyalty_quests',
    'quest_creative',
    'quest_reviews',
    'quest_delivery',
    'quest_analytics',
    'campaign_embed_leads',
    'campaign_embed_analytics',
] as $globallyHiddenMerchantNavKey) {
    unset($merchantNav[$globallyHiddenMerchantNavKey]);
}

$appSidebarNav = [];
if ($canMerchantAccess) {
    foreach ($merchantNav as $key => $item) {
        $appSidebarNav[$key] = ['section' => $item[3] ?? '', 'label' => $item[0], 'detail' => $item[1], 'href' => $item[2], 'visible' => true, 'active' => $merchantView === $key];
    }
} else {
    $appSidebarNav = [
        'inbox' => ['section' => 'Workspace', 'label' => 'Inbox', 'detail' => 'Gift inbox', 'href' => '/inbox.php', 'visible' => true],
        'sent' => ['label' => 'Sent', 'detail' => 'Outbound gifts', 'href' => '/sent.php', 'visible' => true],
        'claimed' => ['label' => 'Claimed', 'detail' => 'Redeemed gifts', 'href' => '/claimed.php', 'visible' => true],
        'subscriptions' => ['section' => 'Merchant', 'label' => 'Upgrade', 'detail' => 'Unlock merchant tools', 'href' => '/pricing.php', 'visible' => true],
    ];
}

$appSidebarBeforeNav = '';
$appSidebarAfterNav = '';
$appSidebarFooter = '';
$appSidebarVariant = $canMerchantAccess ? 'merchant' : 'utility';
$appSidebarLabel = $canMerchantAccess ? 'Merchant' : 'Workspace';
$appSidebarActive = $canMerchantAccess ? $merchantView : 'subscriptions';
$appSidebarCompact = true;
?>
<?php if ($canMerchantAccess): ?>
<link rel="stylesheet" href="/assets/css/merchant-module-limits.css">
<?php endif; ?>
<section class="mg-app-shell mg-merchant-app" data-merchant-app data-merchant-view="<?= mg_e($merchantView) ?>" data-sidebar-contract="mg-app-sidebar" data-merchant-access="<?= $canMerchantAccess ? 'true' : 'false' ?>">
  <?php require __DIR__ . '/app-sidebar.php'; ?>
  <main class="mg-app-workspace mg-merchant-main">
    <?php if (!$user): ?>
      <section class="mg-app-panel">
        <div class="mg-app-panel-head"><div><h2>Merchant access</h2><p>Sign in to open your merchant workspace.</p></div></div>
        <div class="mg-app-panel-body"><a class="mg-btn mg-btn-primary" href="/signin.php">Sign in</a></div>
      </section>
    <?php elseif (!$canMerchantAccess): ?>
      <section class="mg-app-panel">
        <div class="mg-app-panel-head">
          <div>
            <span class="mg-eyebrow">Package access</span>
            <h2>Merchant workspace is not active.</h2>
            <p>Your free account includes the gift Inbox, sent gifts, claimed gifts, feed, and social gifting. Upgrade to Starter, Growth, or Pro to unlock merchant campaigns, rewards, products, locations, and CRM tools.</p>
          </div>
        </div>
        <div class="mg-app-panel-body">
          <div class="mg-action-row">
            <a class="mg-btn mg-btn-primary" href="/pricing.php">View packages</a>
            <a class="mg-btn mg-btn-ghost" href="/account-subscriptions.php">My subscription</a>
            <a class="mg-btn mg-btn-soft" href="/inbox.php">Back to inbox</a>
          </div>
        </div>
      </section>
    <?php else: ?>
      <?php require __DIR__ . '/merchant-view.php'; ?>
    <?php endif; ?>
  </main>
</section>
<?php if ($canMerchantAccess): ?>
<script src="/assets/js/merchant-module-limits.js" defer></script>
<?php endif; ?>