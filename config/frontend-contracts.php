<?php
declare(strict_types=1);

return [
    'stable_entrypoints' => [
        'cart' => [
            'path' => 'assets/js/cart.js',
            'required_tokens' => [
                '/api/commerce/cart.php',
                '/api/commerce/cart-item.php',
                '[data-cart-add],[data-add-to-cart]',
                'createCheckoutFromCart',
                'window.Microgifter.cart =',
            ],
            'forbidden_tokens' => [
                '/assets/js/cart-core.js',
            ],
        ],
        'customer_commerce' => [
            'path' => 'assets/js/customer-commerce.js',
            'required_tokens' => [
                '/api/commerce/cart-items.php',
                '/api/commerce/cart-checkout.php',
                'checkoutWorkflowKey',
                'safeCheckoutUrl',
                'window.MGCustomerCommerce =',
            ],
            'forbidden_tokens' => [
                '/api/commerce/checkout-draft.php',
                '/api/commerce/orders.php',
                '/api/payments/order-checkout-session.php',
            ],
        ],
        'public_index' => [
            'path' => 'assets/js/index-agentic-onboarding.js',
            'required_tokens' => [
                'mg_agentic_index_progress_v2',
                '/api/public/website-product-ideas.php',
                'data-agentic-field',
            ],
        ],
        'action_center_markup' => [
            'path' => 'includes/gift-action-center.php',
            'required_tokens' => [
                "mg_has_role('super_admin')",
                'data-demo-enabled',
                'data-contract-version="2"',
                'data-gift-drawer',
                'data-gift-drawer-content',
                'data-action-modal',
                'gift-center-sidebar.php',
                'gift-action-center-runtime-v4.js?v=4.0.0',
                'gift-action-center-user-search-v2.js?v=2.0.0',
            ],
            'forbidden_tokens' => [
                'account-sidebar.php',
                'mg-gift-folder-tabs',
                'gift-action-center-feed-v3.js',
                'gift-action-center-pagination.js',
                'gift-action-center-user-search-fix.js',
                'gift-action-center-user-search.js"',
            ],
        ],
        'action_center_script' => [
            'path' => 'assets/js/gift-action-center-runtime-v4.js',
            'required_tokens' => [
                '/api/account/action-center.php?folder=',
                'Microgifter.api',
                'state.demoEnabled',
                'demo-coffee-001',
                'demo-sent-001',
                'demo-claimed-001',
                'contract_version:2',
                'mg-pppm-post-stack',
                'mg-pppm-post',
                'Protected voucher',
                'No real payment, ownership transfer, regift, Follow Up, claim, message, tip, notification, ledger entry, payout, or webhook was created.',
                "actionButton(c,'send','Regift'",
                "actionButton(c,'follow-up','Follow Up'",
                'MicrogifterActionCenterRuntime',
            ],
            'forbidden_tokens' => [
                'metadata_json',
                'instance_metadata_json',
                'data-gift-action="resend"',
                'This creates a new resend timestamp',
                'new MutationObserver',
            ],
            'ordered_tokens' => [],
        ],
        'action_center_user_search' => [
            'path' => 'assets/js/gift-action-center-user-search-v2.js',
            'required_tokens' => [
                '/api/account/action-center-recipient-search.php?q=',
                '/api/social/relationship.php',
                'dataUserSearchProfileLink',
                'MicrogifterActionCenterUserSearch',
                "button.setAttribute('aria-pressed'",
            ],
            'forbidden_tokens' => [
                'stopImmediatePropagation',
            ],
        ],
    ],
    'dom_contracts' => [
        'cart_add' => '[data-cart-add],[data-add-to-cart]',
        'cart_page' => '[data-cart-page]',
        'agentic_onboarding' => '[data-agentic-onboarding]',
        'agentic_stage' => '[data-agentic-stage]',
        'action_center' => '[data-gift-center]',
        'action_center_drawer' => '[data-gift-drawer]',
        'action_center_modal' => '[data-action-modal]',
    ],
];
