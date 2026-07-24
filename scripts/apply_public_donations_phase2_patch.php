<?php
declare(strict_types=1);

$root = dirname(__DIR__);

function phase2_patch_file(string $path, callable $patcher): void
{
    global $root;
    $full = $root . '/' . $path;
    $before = file_get_contents($full);
    if (!is_string($before)) throw new RuntimeException('Unable to read ' . $path);
    $after = $patcher($before);
    if (!is_string($after) || $after === $before) throw new RuntimeException('Patch made no change to ' . $path);
    if (file_put_contents($full, $after) === false) throw new RuntimeException('Unable to write ' . $path);
}

function phase2_replace(string $text, string $needle, string $replacement, string $label, int $expected = 1): string
{
    $count = substr_count($text, $needle);
    if ($count !== $expected) throw new RuntimeException($label . ' expected ' . $expected . ' matches, found ' . $count);
    return str_replace($needle, $replacement, $text);
}

phase2_patch_file('includes/campaign-types.php', static function(string $text): string {
    $text = phase2_replace($text,
        "require_once __DIR__ . '/loyalty-quest-campaign-type.php';",
        "require_once __DIR__ . '/loyalty-quest-campaign-type.php';\nrequire_once __DIR__ . '/public-donations-campaign-type.php';",
        'campaign type require');
    $text = phase2_replace($text,
        "    \$registry['loyalty_quest'] = mg_loyalty_quest_campaign_definition();\n    return \$registry;",
        "    \$registry['loyalty_quest'] = mg_loyalty_quest_campaign_definition();\n    \$registry['public_donation'] = mg_public_donations_campaign_definition();\n    return \$registry;",
        'campaign registry extension');
    $text = phase2_replace($text,
        "function mg_campaign_type_public_enabled(string \$type): bool\n{\n    return !empty(mg_campaign_type_get(\$type)['public_enabled']);\n}\n\nfunction mg_campaign_type_submit_endpoint(string \$type): string\n{\n    return (string)(mg_campaign_type_get(\$type)['submit_endpoint'] ?? '/api/public/campaigns/engage.php');\n}",
        "function mg_campaign_type_public_enabled(string \$type): bool\n{\n    return !empty(mg_campaign_type_get(\$type)['public_enabled']);\n}\n\nfunction mg_campaign_type_public_transactional(string \$type): bool\n{\n    \$definition = mg_campaign_type_get(\$type);\n    if (!is_array(\$definition) || empty(\$definition['public_enabled'])) return false;\n    return !array_key_exists('public_transactional', \$definition) || !empty(\$definition['public_transactional']);\n}\n\nfunction mg_campaign_type_public_mode(string \$type): string\n{\n    \$definition = mg_campaign_type_get(\$type);\n    if (!is_array(\$definition) || empty(\$definition['public_enabled'])) return 'internal';\n    return (string)(\$definition['public_mode'] ?? (mg_campaign_type_public_transactional(\$type) ? 'transactional' : 'informational'));\n}\n\nfunction mg_campaign_type_submit_endpoint(string \$type): string\n{\n    if (!mg_campaign_type_public_transactional(\$type)) return '';\n    \$endpoint = trim((string)(mg_campaign_type_get(\$type)['submit_endpoint'] ?? ''));\n    return \$endpoint !== '' ? \$endpoint : '/api/public/campaigns/engage.php';\n}",
        'campaign public behavior helpers');
    $text = phase2_replace($text,
        "            'internal_only' => !empty(\$definition['internal_only']),\n            'public_enabled' => !empty(\$definition['public_enabled']),\n        ],",
        "            'internal_only' => !empty(\$definition['internal_only']),\n            'public_enabled' => !empty(\$definition['public_enabled']),\n            'public_transactional' => mg_campaign_type_public_transactional((string)\$definition['key']),\n            'public_mode' => mg_campaign_type_public_mode((string)\$definition['key']),\n        ],",
        'campaign type options metadata', 1);
    $text = phase2_replace($text,
        "            'internal_only' => !empty(\$definition['internal_only']),\n            'public_enabled' => !empty(\$definition['public_enabled']),\n        ],",
        "            'internal_only' => !empty(\$definition['internal_only']),\n            'public_enabled' => !empty(\$definition['public_enabled']),\n            'public_transactional' => mg_campaign_type_public_transactional((string)\$definition['key']),\n            'public_mode' => mg_campaign_type_public_mode((string)\$definition['key']),\n        ],",
        'campaign client registry metadata', 1);
    return $text;
});

phase2_patch_file('api/merchant/campaigns-core.php', static function(string $text): string {
    $text = phase2_replace($text,
        "require_once dirname(__DIR__, 2) . '/includes/campaign-types.php';",
        "require_once dirname(__DIR__, 2) . '/includes/campaign-types.php';\nrequire_once dirname(__DIR__, 2) . '/includes/public-donations-feature.php';",
        'merchant feature require');
    $text = phase2_replace($text,
        "        'public_enabled' => !empty(\$typeDefinition['public_enabled']),\n        'internal_only' => !empty(\$typeDefinition['internal_only']),",
        "        'public_enabled' => !empty(\$typeDefinition['public_enabled']),\n        'public_transactional' => mg_campaign_type_public_transactional(\$type),\n        'public_mode' => mg_campaign_type_public_mode(\$type),\n        'internal_only' => !empty(\$typeDefinition['internal_only']),",
        'merchant row public metadata');
    $text = phase2_replace($text,
        "    } else {\n        \$rules += [\n            'mode' => (string)(\$definition['rules_schema']['mode'] ?? 'instant_reward'),\n            'entry_reward_enabled' => true,\n        ];\n    }",
        "    } elseif (\$campaignType === 'public_donation') {\n        \$rules += [\n            'mode' => 'merchant_initiated_bulk',\n            'public_mode' => 'informational',\n            'public_transactional' => false,\n            'entry_reward_enabled' => false,\n        ];\n    } else {\n        \$rules += [\n            'mode' => (string)(\$definition['rules_schema']['mode'] ?? 'instant_reward'),\n            'entry_reward_enabled' => true,\n        ];\n    }",
        'public donation rule contract');
    $text = phase2_replace($text,
        "            'campaign_types' => mg_campaign_type_options(true),",
        "            'campaign_types' => mg_public_donations_campaign_type_options(\$merchantId, \$user, true),",
        'merchant campaign options', 2);
    $text = phase2_replace($text,
        "    mg_fail('Invalid campaign.', 422);\n}\n\nif (\$campaignType === 'watch_video_reward'",
        "    mg_fail('Invalid campaign.', 422);\n}\n\nif (\$campaignType === 'public_donation' && !mg_public_donations_is_enabled_for(\$merchantId, \$user)) {\n    mg_fail('Public Donations campaigns are not enabled for this merchant.', 403);\n}\n\nif (\$campaignType === 'watch_video_reward'",
        'merchant public donation write gate');
    return $text;
});

phase2_patch_file('api/merchant/campaigns.php', static function(string $text): string {
    return phase2_replace($text,
        "    \$method = strtoupper((string)(\$_SERVER['REQUEST_METHOD'] ?? 'GET'));\n\n    if (\$method === 'GET'",
        "    \$method = strtoupper((string)(\$_SERVER['REQUEST_METHOD'] ?? 'GET'));\n    \$merchantId = (int)(\$GLOBALS['merchantId'] ?? 0);\n    \$actor = is_array(\$GLOBALS['user'] ?? null) ? \$GLOBALS['user'] : null;\n    \$data['public_donations_feature'] = mg_public_donations_feature_context(\$merchantId > 0 ? \$merchantId : null, \$actor);\n\n    if (\$method === 'GET'",
        'merchant feature response');
});

phase2_patch_file('includes/merchant-campaigns-view.php', static function(string $text): string {
    $text = phase2_replace($text,
        "require_once __DIR__ . '/campaign-types.php';\n\$mgCampaignTypeOptions = mg_campaign_type_options(true);\n\$mgCampaignTypeClientRegistry = mg_campaign_type_client_registry(true);",
        "require_once __DIR__ . '/campaign-types.php';\nrequire_once __DIR__ . '/public-donations-feature.php';\n\$mgCampaignActor = is_array(\$user ?? null) ? \$user : (is_array(\$GLOBALS['user'] ?? null) ? \$GLOBALS['user'] : null);\n\$mgCampaignMerchantId = (int)(\$mgCampaignActor['id'] ?? 0);\n\$mgCampaignTypeOptions = mg_public_donations_campaign_type_options(\$mgCampaignMerchantId > 0 ? \$mgCampaignMerchantId : null, \$mgCampaignActor, true);\n\$mgCampaignTypeClientRegistry = mg_public_donations_client_registry(\$mgCampaignMerchantId > 0 ? \$mgCampaignMerchantId : null, \$mgCampaignActor, true);",
        'merchant builder feature filtering');
    $text = phase2_replace($text,
        '<div class="mg-app-panel-head mg-campaign-panel-head"><div><span class="mg-eyebrow">Builder</span><h2>Create campaign</h2><p>Choose the distribution trigger, attach a reward template, set the campaign rules, and save as draft or active.</p></div></div>',
        '<div class="mg-app-panel-head mg-campaign-panel-head"><div><span class="mg-eyebrow">Builder</span><h2>Create campaign</h2><p>Choose the distribution trigger, attach a reward template, set the campaign rules, and save as draft or active.</p></div></div><?php if (mg_public_donations_is_enabled_for($mgCampaignMerchantId > 0 ? $mgCampaignMerchantId : null, $mgCampaignActor)): ?><div class="mg-campaign-public-donation-note"><strong>Public Donations</strong>Allocate merchant-funded rewards directly to selected Community accounts. The public page is informational only; customers cannot buy, join, request, claim, or submit contact information.</div><?php endif; ?>',
        'merchant builder informational copy');
    return $text;
});

phase2_patch_file('includes/public-campaign-page.php', static function(string $text): string {
    $text = phase2_replace($text,
        "require_once __DIR__ . '/campaign-user-details.php';",
        "require_once __DIR__ . '/campaign-user-details.php';\nrequire_once __DIR__ . '/public-donations-feature.php';",
        'public feature require');
    $text = phase2_replace($text,
        "    \$endpoint = mg_campaign_type_submit_endpoint(\$type);\n    return \$endpoint !== '' ? \$endpoint : '/api/public/campaigns/engage.php';",
        "    return mg_campaign_type_submit_endpoint(\$type);",
        'public endpoint fallback removal');
    $text = phase2_replace($text,
        "        'agent_offer' => ['Offer interest', 'Your request is captured for merchant follow-up and reward routing.'],\n        default =>",
        "        'agent_offer' => ['Offer interest', 'Your request is captured for merchant follow-up and reward routing.'],\n        'public_donation' => ['Community reward support', 'The merchant allocates rewards directly to selected Community accounts and publishes aggregate impact.'],\n        default =>",
        'public donation outcome copy');
    $text = phase2_replace($text,
        "    \$verb = match (\$type) {",
        "    if (\$type === 'public_donation') {\n        return [\n            ['title' => 'Merchant allocates', 'copy' => 'The merchant selects eligible Community accounts and controls reward quantities.'],\n            ['title' => 'Community receives', 'copy' => 'Allocated rewards enter each Community account through the existing Inbox and PPPM lifecycle.'],\n            ['title' => 'Impact is reported', 'copy' => 'The public page shows aggregate campaign impact without exposing private allocation records.'],\n        ];\n    }\n    \$verb = match (\$type) {",
        'public donation steps');
    $text = phase2_replace($text,
        "    mg_campaign_landing_render_profile(\$profile);\n\n    if (\$closed): ?>",
        "    mg_campaign_landing_render_profile(\$profile);\n\n    if (!mg_campaign_type_public_transactional(\$campaignType)): ?>\n      <div class=\"mg-public-donations-info\" data-public-donations-informational>\n        <span class=\"mg-public-donations-info__badge\">Public Donations</span>\n        <h3>Merchant-directed Community support</h3>\n        <p>This campaign highlights rewards allocated directly by the merchant to Community accounts. These rewards are not available for public purchase or request.</p>\n        <p class=\"mg-public-donations-info__notice\">No purchase, join, request, checkout, claim, quantity, or contact-submission action is available on this page.</p>\n      </div>\n      <?php return; endif; ?>\n\n    <?php if (\$closed): ?>",
        'informational public panel');
    $text = phase2_replace($text,
        "\$campaignType = (string)\$mgCampaign['campaign_type'];\n\$typeLabel =",
        "\$campaignType = (string)\$mgCampaign['campaign_type'];\nif (\$campaignType === 'public_donation' && !mg_public_donations_is_enabled_for((int)(\$mgCampaign['merchant_user_id'] ?? 0), function_exists('mg_current_user') ? mg_current_user() : null)) {\n    mg_campaign_landing_render_unavailable((string)\$mgCampaignPageLabel, (string)\$mgCampaignPageIntro);\n    return;\n}\n\$typeLabel =",
        'public feature visibility gate');
    $text = phase2_replace($text,
        '<div class="mg-public-campaign-trust-row"><span><?= mg_e($typeLabel) ?></span><span>Reward sent to Inbox</span><span>PPPM tracked</span></div>',
        '<div class="mg-public-campaign-trust-row"><span><?= mg_e($typeLabel) ?></span><?php if ($campaignType === \'public_donation\'): ?><span>Merchant allocated</span><span>Aggregate impact</span><?php else: ?><span>Reward sent to Inbox</span><span>PPPM tracked</span><?php endif; ?></div>',
        'public trust row');
    return $text;
});

phase2_patch_file('api/public/campaigns/detail.php', static function(string $text): string {
    $text = phase2_replace($text,
        "require_once dirname(__DIR__, 3) . '/includes/campaign-types.php';",
        "require_once dirname(__DIR__, 3) . '/includes/campaign-types.php';\nrequire_once dirname(__DIR__, 3) . '/includes/public-donations-feature.php';",
        'detail feature require');
    $text = phase2_replace($text,
        "SELECT c.public_id,c.public_slug,c.qr_code_token,c.campaign_type",
        "SELECT c.public_id,c.public_slug,c.qr_code_token,c.merchant_user_id,c.campaign_type",
        'detail merchant id');
    $text = phase2_replace($text,
        "    if (!\$row) mg_fail('Campaign not found.', 404);\n\n    \$now = time();",
        "    if (!\$row) mg_fail('Campaign not found.', 404);\n    \$campaignType = (string)\$row['campaign_type'];\n    if (\$campaignType === 'public_donation' && !mg_public_donations_is_enabled_for((int)\$row['merchant_user_id'], mg_current_user())) mg_fail('Campaign not found.', 404);\n    if (!mg_campaign_type_public_enabled(\$campaignType)) mg_fail('Campaign not found.', 404);\n\n    \$now = time();",
        'detail feature gate');
    $text = phase2_replace($text,
        "    if (\$row['quantity_limit'] !== null && (int) \$row['issued_count'] >= (int) \$row['quantity_limit']) mg_fail('Campaign reward limit has been reached.', 409);",
        "    if (mg_campaign_type_public_transactional(\$campaignType) && \$row['quantity_limit'] !== null && (int) \$row['issued_count'] >= (int) \$row['quantity_limit']) mg_fail('Campaign reward limit has been reached.', 409);",
        'detail informational capacity');
    $text = phase2_replace($text,
        "    \$submitEndpoint = mg_campaign_type_submit_endpoint((string)\$row['campaign_type']);\n    if (\$submitEndpoint === '') \$submitEndpoint = '/api/public/campaigns/engage.php';",
        "    \$submitEndpoint = mg_campaign_type_submit_endpoint(\$campaignType);",
        'detail endpoint fallback removal');
    $text = phase2_replace($text,
        "        'campaign_type' => (string) \$row['campaign_type'],",
        "        'campaign_type' => \$campaignType,\n        'public_transactional' => mg_campaign_type_public_transactional(\$campaignType),\n        'public_mode' => mg_campaign_type_public_mode(\$campaignType),",
        'detail public metadata');
    return $text;
});

phase2_patch_file('includes/market/merchant-market-engine.php', static function(string $text): string {
    $text = phase2_replace($text,
        "require_once dirname(__DIR__, 2) . '/api/profiles/_public_profile.php';",
        "require_once dirname(__DIR__, 2) . '/api/profiles/_public_profile.php';\nrequire_once dirname(__DIR__) . '/campaign-types.php';\nrequire_once dirname(__DIR__) . '/public-donations-feature.php';",
        'market registry require');
    $text = phase2_replace($text,
        "'account_stamp_balances','stamp_ledger_entries','merchant_market_snapshots'",
        "'account_stamp_balances','stamp_ledger_entries','merchant_market_snapshots','campaign_community_assignments'",
        'market assignment table allowlist');
    $text = phase2_replace($text,
        "function mg_market_campaign_url(array \$row): ?string { \$slug = trim((string)(\$row['public_slug'] ?? '')); if (\$slug === '') return null; \$page = match ((string)(\$row['campaign_type'] ?? '')) { 'newsletter_signup'=>'/newsletter-signup.php','contest_giveaway'=>'/contest.php','qr_reward_drop'=>'/qr-reward.php','referral_reward'=>'/referral-reward.php','birthday_vip'=>'/birthday-vip.php','agent_offer'=>'/agent-offer.php', default=>'/campaign.php' }; return \$page . '?campaign=' . rawurlencode(\$slug); }",
        "function mg_market_campaign_url(array \$row): ?string { \$slug = trim((string)(\$row['public_slug'] ?? '')); if (\$slug === '') return null; \$page = mg_campaign_type_public_path((string)(\$row['campaign_type'] ?? '')); if (\$page === '') return null; return \$page . '?campaign=' . rawurlencode(\$slug); }",
        'market campaign URL registry');
    $text = phase2_replace($text,
        "            \$campaignItems[] = ['id'=>(string)\$row['public_id'],'type'=>(string)\$row['campaign_type'],'title'=>(string)\$row['title'],'description'=>\$row['description'] !== null ? (string)\$row['description'] : null,'status'=>(string)\$row['status'],'progress'=>mg_market_campaign_progress(\$row),'issued_count'=>(int)(\$row['issued_count'] ?? 0),'quantity_limit'=>\$row['quantity_limit'] !== null ? (int)\$row['quantity_limit'] : null,'url'=>mg_market_campaign_url(\$row)];",
        "            \$campaignType = (string)\$row['campaign_type'];\n            if (\$campaignType === 'public_donation' && !mg_public_donations_is_enabled_for(\$ownerId, null)) continue;\n            \$supported = 0;\n            if (\$campaignType === 'public_donation' && mg_market_table(\$pdo, 'campaign_community_assignments')) {\n                \$supported = (int)(mg_market_row(\$pdo, \"SELECT COUNT(*) total FROM campaign_community_assignments WHERE campaign_id=(SELECT id FROM campaigns WHERE public_id=? AND merchant_user_id=? LIMIT 1) AND status='active'\", [(string)\$row['public_id'], \$ownerId])['total'] ?? 0);\n            }\n            \$campaignItems[] = [\n                'id'=>(string)\$row['public_id'], 'type'=>\$campaignType, 'campaign_type'=>\$campaignType,\n                'title'=>(string)\$row['title'], 'description'=>\$row['description'] !== null ? (string)\$row['description'] : null,\n                'status'=>(string)\$row['status'], 'progress'=>mg_market_campaign_progress(\$row),\n                'issued_count'=>(int)(\$row['issued_count'] ?? 0), 'rewards_allocated'=>(int)(\$row['issued_count'] ?? 0),\n                'community_accounts_supported'=>\$supported,\n                'quantity_limit'=>\$row['quantity_limit'] !== null ? (int)\$row['quantity_limit'] : null,\n                'url'=>mg_market_campaign_url(\$row), 'action_label'=>'View Campaign',\n                'card_variant'=>\$campaignType === 'public_donation' ? 'public_donation' : 'standard',\n                'badge'=>\$campaignType === 'public_donation' ? 'Public Donations' : null,\n                'public_transactional'=>mg_campaign_type_public_transactional(\$campaignType),\n                'public_mode'=>mg_campaign_type_public_mode(\$campaignType),\n            ];",
        'market public donation card data');
    return $text;
});

phase2_patch_file('assets/js/public-profile-investment.js', static function(string $text): string {
    $text = phase2_replace($text,
        "    if (value.includes('referral')) return '↗';\n    return '•';",
        "    if (value.includes('referral')) return '↗';\n    if (value.includes('public_donation') || value.includes('public donation')) return '★';\n    return '•';",
        'profile public donation icon');
    $text = phase2_replace($text,
        "      var card = el('article', 'mg-profile-campaign-card');",
        "      var isPublicDonation = String(item.card_variant || item.campaign_type || item.type || '') === 'public_donation';\n      var card = el('article', 'mg-profile-campaign-card' + (isPublicDonation ? ' is-public-donation' : ''));",
        'profile campaign variant');
    $text = phase2_replace($text,
        "      if (item.url) title.href = href(item.url, '/campaign.php');\n      copy.append(title, el('p', '', item.description || 'Open this campaign to learn more.'));",
        "      if (item.url) title.href = href(item.url, '/campaign.php');\n      if (isPublicDonation) copy.appendChild(el('span', 'mg-profile-campaign-badge', 'Public Donations'));\n      copy.append(title, el('p', '', item.description || 'Open this campaign to learn more.'));\n      if (isPublicDonation) {\n        var impact = el('div', 'mg-profile-campaign-impact');\n        impact.append(\n          el('span', '', String(Number(item.community_accounts_supported || 0).toLocaleString()) + ' Community accounts supported'),\n          el('span', '', String(Number(item.rewards_allocated || item.issued_count || 0).toLocaleString()) + ' rewards allocated')\n        );\n        copy.appendChild(impact);\n        if (item.url) { var action = el('a', 'mg-profile-campaign-action', 'View Campaign'); action.href = href(item.url, '/public-donations.php'); copy.appendChild(action); }\n      }",
        'profile public donation impact card');
    return $text;
});

@unlink($root . '/scripts/apply_public_donations_phase2_patch.php');
@unlink($root . '/.github/workflows/apply-public-donations-phase2-patch.yml');

echo "Phase 2 canonical patches applied.\n";
