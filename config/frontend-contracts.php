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
                'window.Microgifter.cart=',
            ],
            'forbidden_tokens' => [
                '/assets/js/cart-core.js',
            ],
        ],
        'customer_commerce' => [
            'path' => 'assets/js/customer-commerce.js',
            'required_tokens' => [
                '/api/commerce/cart-items.php',
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
                'data-gift-drawer',
                'data-gift-drawer-content',
                'data-action-modal',
                'agent-sidebar.php',
            ],
            'forbidden_tokens' => [
                'account-sidebar.php',
                'mg-gift-folder-tabs',
            ],
        ],
        'action_center_script' => [
            'path' => 'assets/js/gift-action-center.js',
            'required_tokens' => [
                '/api/account/action-center.php',
                "app.dataset.demoEnabled === 'true'",
                'demo-coffee-001',
                'demo-sent-001',
                'demo-claimed-001',
                'mg-pppm-post-stack',
                'mg-pppm-post',
                'Protected voucher',
                'No real payment, ownership transfer, regift, Follow Up, claim, message, tip, notification, ledger entry, payout, or webhook was created.',
                'data-gift-action="send">Regift',
                'data-gift-action="follow-up">Follow Up',
                'Only the most recent sender can follow up',
            ],
            'forbidden_tokens' => [
                'data-gift-action="resend"',
                'This creates a new resend timestamp',
            ],
            'ordered_tokens' => [
                ['mg-pppm-post-stack','Protected voucher'],
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
