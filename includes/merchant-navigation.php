<?php
declare(strict_types=1);

if (!function_exists('mg_merchant_navigation_items')) {
    function mg_merchant_navigation_items(): array
    {
        return [
            'overview' => ['Overview', 'Workspace health', '/merchant.php', 'Dashboard'],
            'notifications' => ['Notifications', 'Tips, voucher messages, alerts', '/merchant-notifications.php', 'Dashboard'],

            'products' => ['Products', 'Catalog and builder', '/merchant-products.php', 'Commerce'],
            'reward_templates' => ['Rewards', 'Wallet-ready offers', '/merchant-reward-templates.php', 'Commerce'],
            'orders' => ['Orders', 'Payments and delivery recovery', '/merchant-orders.php', 'Commerce'],
            'claims' => ['Claims', 'Verification and redemption', '/merchant-claims.php', 'Commerce'],
            'pppm' => ['Microgift Totals', 'Items and lifecycle', '/merchant-pppm.php', 'Commerce'],

            'merchant_crm' => ['Merchant CRM', 'Customers and campaign history', '/merchant-crm.php', 'Customers & Campaigns'],
            'reviews' => ['Customer Reviews', 'Replies and customer follow-up', '/merchant-reviews.php', 'Customers & Campaigns'],
            'campaigns' => ['Campaigns', 'Forms, contests, QR drops', '/merchant-campaigns.php', 'Customers & Campaigns'],
            'campaign_ads' => ['Campaign Ads', 'Boost campaigns and local drops', '/merchant-ad-manager.php', 'Customers & Campaigns'],
            'distribution' => ['Distribution', 'Programs and inputs', '/merchant-distribution.php', 'Customers & Campaigns'],
            'hosted_games' => ['Hosted Games', 'Upload games and connect rewards', '/merchant-games.php', 'Customers & Campaigns'],
            'agent_chat' => ['Agent Chat', 'Merchant agent feed', '/merchant-agent-chat.php', 'Customers & Campaigns'],

            'storefront' => ['Storefront', 'Public merchant page', '/merchant-storefront.php', 'Store Presence'],
            'merchant_pwa' => ['Branded App', 'Merchant PWA install screen', '/merchant-pwa.php', 'Store Presence'],
            'store_canvas' => ['Store Canvas', 'Live avatars and customer activity', '/merchant-canvas.php', 'Store Presence'],
            'media' => ['Media', 'Assets and processing', '/merchant-media.php', 'Store Presence'],

            'payments' => ['Payments', 'Checkout and reconciliation', '/merchant-payments.php', 'Finance'],
            'stamps' => ['Stamp Ledger', 'Sends and balance', '/merchant-stamps.php', 'Finance'],

            'locations' => ['Locations', 'Stores and claim scope', '/merchant-locations.php', 'Business Settings'],
            'team' => ['Team', 'Roles and access', '/merchant-team.php', 'Business Settings'],
            'integrations' => ['Connected Apps', 'CRM and web-store connections', '/merchant-integrations.php', 'Business Settings'],
            'developer_api' => ['Developer API', 'Apps and access', '/merchant-distribution.php?developer_api=1', 'Business Settings'],
            'settings' => ['Settings', 'Business configuration', '/merchant-settings.php', 'Business Settings'],
        ];
    }
}

if (!function_exists('mg_merchant_navigation_active_key')) {
    function mg_merchant_navigation_active_key(string $active): string
    {
        $active = trim($active);
        $aliases = [
            'merchant' => 'overview',
            'merchant-notifications' => 'notifications',
            'onboarding' => 'overview',
            'merchant-onboarding' => 'overview',
            'intelligence' => 'overview',
            'merchant-intelligence' => 'overview',
            'product' => 'products',
            'merchant-product' => 'products',
            'merchant-products' => 'products',
            'merchant-reward-templates' => 'reward_templates',
            'merchant-orders' => 'orders',
            'claim' => 'claims',
            'merchant-claim' => 'claims',
            'merchant-claims' => 'claims',
            'wallet_redemptions' => 'claims',
            'merchant-wallet-redemptions' => 'claims',
            'pppm_item' => 'pppm',
            'merchant-pppm-item' => 'pppm',
            'merchant-pppm' => 'pppm',
            'merchant-crm' => 'merchant_crm',
            'merchant-reviews' => 'reviews',
            'customer-reviews' => 'reviews',
            'merchant-campaigns' => 'campaigns',
            'loyalty_quests' => 'campaigns',
            'merchant-loyalty-quests' => 'campaigns',
            'quest_creative' => 'campaigns',
            'merchant-loyalty-quest-creative' => 'campaigns',
            'quest_reviews' => 'campaigns',
            'merchant-quest-reviews' => 'campaigns',
            'quest_delivery' => 'campaigns',
            'merchant-loyalty-quest-delivery' => 'campaigns',
            'quest_analytics' => 'campaigns',
            'merchant-loyalty-quest-analytics' => 'campaigns',
            'campaign_embed_leads' => 'campaigns',
            'merchant-campaign-embed-leads' => 'campaigns',
            'campaign_embed_analytics' => 'campaigns',
            'merchant-campaign-embed-analytics' => 'campaigns',
            'campaign_stamps' => 'stamps',
            'merchant-campaign-stamps' => 'stamps',
            'ads-manager' => 'campaign_ads',
            'merchant-ad-manager' => 'campaign_ads',
            'merchant-ad-performance' => 'campaign_ads',
            'merchant-distribution' => 'distribution',
            'distribution_program' => 'distribution',
            'merchant-distribution-program' => 'distribution',
            'merchant-games' => 'hosted_games',
            'merchant-hosted-games' => 'hosted_games',
            'hosted-games' => 'hosted_games',
            'merchant-agent-chat' => 'agent_chat',
            'merchant-storefront' => 'storefront',
            'storefront_preview' => 'storefront',
            'merchant-storefront-preview' => 'storefront',
            'merchant-pwa' => 'merchant_pwa',
            'store-canvas' => 'store_canvas',
            'merchant-canvas' => 'store_canvas',
            'merchant-media' => 'media',
            'merchant-payments' => 'payments',
            'merchant-stamps' => 'stamps',
            'merchant-locations' => 'locations',
            'merchant-team' => 'team',
            'merchant-integrations' => 'integrations',
            'merchant-developer-api' => 'developer_api',
            'merchant-settings' => 'settings',
        ];

        return $aliases[$active] ?? $active;
    }
}

if (!function_exists('mg_merchant_navigation_sidebar')) {
    function mg_merchant_navigation_sidebar(string $active = ''): array
    {
        $activeKey = mg_merchant_navigation_active_key($active);
        $sidebar = [];

        foreach (mg_merchant_navigation_items() as $key => $item) {
            $sidebar[$key] = [
                'section' => $item[3] ?? '',
                'label' => $item[0] ?? $key,
                'detail' => $item[1] ?? '',
                'href' => $item[2] ?? '#',
                'visible' => true,
                'active' => $activeKey === $key,
            ];
        }

        return $sidebar;
    }
}
