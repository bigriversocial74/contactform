<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/agents/_agent.php';

function mg_multi_agent_templates(): array
{
    return [
        'chat_agent' => [
            'name' => 'Chat Agent',
            'category' => 'personal',
            'icon' => '✦',
            'description' => 'Create another general-purpose Microgifter chat with its own tab, conversations, memory, setup, and lifecycle controls.',
            'welcome' => 'What would you like this Chat Agent to help you with?',
            'prompts' => ['Start a new plan', 'Help me organize an idea', 'Explore Microgifter options'],
            'status' => 'active',
        ],
        'birthday_occasion' => [
            'name' => 'Birthday & Occasion',
            'category' => 'family',
            'icon' => '🎂',
            'description' => 'Track important dates, recipient preferences, budgets, reminders, and recurring gift plans.',
            'welcome' => 'Who would you like to plan an occasion for?',
            'prompts' => ['Add a birthday', 'Review upcoming occasions', 'Set a gift budget'],
            'status' => 'active',
        ],
        'local_shopping' => [
            'name' => 'Local Shopping Concierge',
            'category' => 'friend',
            'icon' => '⌖',
            'description' => 'Discover local products, services, experiences, merchants, and gift bundles.',
            'welcome' => 'Tell me who you are shopping for, the occasion, location, and budget.',
            'prompts' => ['Find a local gift', 'Compare gift ideas', 'Explore local bundles'],
            'status' => 'active',
        ],
        'merchant_campaign' => [
            'name' => 'Merchant Campaigns',
            'category' => 'group',
            'icon' => '◈',
            'description' => 'Review products, identify opportunities, and prepare merchant campaign drafts.',
            'welcome' => 'What merchant result would you like to improve?',
            'prompts' => ['Review active campaigns', 'Create a weekend offer', 'Find a campaign opportunity'],
            'status' => 'active',
            'merchant_required' => true,
        ],
        'loyalty_recovery' => [
            'name' => 'Loyalty Recovery',
            'category' => 'group',
            'icon' => '↺',
            'description' => 'Reconnect with inactive customers through controlled recovery offers and follow-up plans.',
            'status' => 'coming_soon',
        ],
        'workplace_rewards' => [
            'name' => 'Workplace Rewards',
            'category' => 'coworker',
            'icon' => '▣',
            'description' => 'Coordinate employee recognition and locally funded reward programs.',
            'status' => 'coming_soon',
        ],
        'community_fundraising' => [
            'name' => 'Community Fundraising',
            'category' => 'fundraiser',
            'icon' => '◎',
            'description' => 'Prepare merchant-supported fundraising programs, prizes, and sponsor opportunities.',
            'status' => 'coming_soon',
        ],
        'creator_merch' => [
            'name' => 'Artist & Creator Merch',
            'category' => 'contest',
            'icon' => '★',
            'description' => 'Plan release promotions, merchandise bundles, fan rewards, and watch/listen campaigns.',
            'status' => 'coming_soon',
        ],
    ];
}

function mg_multi_agent_active_agents(?array $user = null): array
{
    $user = $user ?? mg_current_user();
    if (!$user || (int) ($user['id'] ?? 0) < 1) return [];
    try {
        $stmt = mg_db()->prepare("SELECT * FROM agents WHERE user_id=? AND lifecycle_status='active' ORDER BY updated_at DESC,id DESC");
        $stmt->execute([(int) $user['id']]);
        return array_map('mg_agent_row_to_public', $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable) {
        return [];
    }
}

function mg_multi_agent_open_tabs(array $agents): array
{
    return array_values(array_filter($agents, static function (array $agent): bool {
        $config = is_array($agent['config'] ?? null) ? $agent['config'] : [];
        return !array_key_exists('workspace_tab_open', $config) || !empty($config['workspace_tab_open']);
    }));
}