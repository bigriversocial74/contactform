<?php
declare(strict_types=1);

if (!function_exists('mg_merchant_navigation_items')) {
    function mg_merchant_navigation_items(): array
    {
        return [
            // Section 1: Products & Engagement
            'products' => ['Products', 'Catalog and builder', '/merchant-products.php', 'Products & Engagement'],
            'reward_templates' => ['Rewards', 'Wallet-ready offers', '/merchant-reward-templates.php', 'Products & Engagement'],
            'campaigns' => ['Campaigns', 'Forms, contests, QR drops', '/merchant-campaigns.php', 'Products & Engagement'],
            'claims' => ['Claims', 'Verification and redemption', '/merchant-claims.php', 'Products & Engagement'],
            'locations' => ['Locations', 'Stores and claim scope', '/merchant-locations.php', 'Products & Engagement'],
            'loyalty_quests' => ['Loyalty Quests', 'Challenges, proof, and rewards', '/merchant-loyalty-quests.php', 'Products & Engagement'],

            // Section 2: Insights & Records
            'overview' => ['Overview', 'Workspace health', '/merchant.php', 'Insights & Records'],
            'notifications' => ['Notifications', 'Tips, voucher messages, alerts', '/merchant-notifications.php', 'Insights & Records'],
            'orders' => ['My Orders', 'Payments and delivery recovery', '/merchant-orders.php', 'Insights & Records'],
            'stamps' => ['My Stamps / Ledger', 'Sends and balance', '/merchant-stamps.php', 'Insights & Records'],
            'reviews' => ['My Customer Reviews', 'Replies and customer follow-up', '/merchant-reviews.php', 'Insights & Records'],
            'pppm' => ['Microgift Totals', 'Items and lifecycle', '/merchant-pppm.php', 'Insights & Records'],
            'merchant_crm' => ['Merchant CRM', 'Customers and campaign history', '/merchant-crm.php', 'Insights & Records'],

            // Section 3: Storefront & Distribution
            'storefront' => ['Storefront', 'Public merchant page', '/merchant-storefront.php', 'Storefront & Distribution'],
            'merchant_pwa' => ['Branded Apps', 'Merchant PWA install screen', '/merchant-pwa.php', 'Storefront & Distribution'],
            'hosted_games' => ['Hosted Games', 'Upload games and connect rewards', '/merchant-games.php', 'Storefront & Distribution'],
            'distribution' => ['Distribution', 'Programs and inputs', '/merchant-distribution.php', 'Storefront & Distribution'],
            'developer_api' => ['Developer API', 'Apps and access', '/merchant-distribution.php?developer_api=1', 'Storefront & Distribution'],
            'store_canvas' => ['Store Canvas', 'Live avatars and customer activity', '/merchant-canvas.php', 'Storefront & Distribution'],
            'world_canvas' => ['World Canvas', 'Network activity and campaign movement', '/world-canvas.php', 'Storefront & Distribution'],
            'integrations' => ['Connected Apps', 'CRM and web-store connections', '/merchant-integrations.php', 'Storefront & Distribution'],

            // Section 4: Business Operations
            'campaign_ads' => ['Advertising', 'Boost campaigns and local drops', '/merchant-ad-manager.php', 'Business Operations'],
            'payments' => ['Payments', 'Checkout and reconciliation', '/merchant-payments.php', 'Business Operations'],
            'media' => ['Media', 'Assets and processing', '/merchant-media.php', 'Business Operations'],
            'team' => ['Team', 'Roles and access', '/merchant-team.php', 'Business Operations'],
            'agent_chat' => ['Agent Chat', 'Merchant agent feed', '/merchant-agent-chat.php', 'Business Operations'],
            'settings' => ['Settings', 'Business configuration', '/merchant-settings.php', 'Business Operations'],
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
            'loyalty_quests' => 'loyalty_quests',
            'merchant-loyalty-quests' => 'loyalty_quests',
            'quest_creative' => 'loyalty_quests',
            'merchant-loyalty-quest-creative' => 'loyalty_quests',
            'quest_reviews' => 'loyalty_quests',
            'merchant-quest-reviews' => 'loyalty_quests',
            'quest_delivery' => 'loyalty_quests',
            'merchant-loyalty-quest-delivery' => 'loyalty_quests',
            'quest_analytics' => 'loyalty_quests',
            'merchant-loyalty-quest-analytics' => 'loyalty_quests',
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
            'world-canvas' => 'world_canvas',
            'world_canvas' => 'world_canvas',
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
