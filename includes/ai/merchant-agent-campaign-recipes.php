<?php
declare(strict_types=1);

function mg_ai_chat_campaign_recipe_catalog(): array
{
    return [
        'campaign_types' => [
            'newsletter_signup' => ['label' => 'Newsletter Signup', 'status' => 'current', 'best_for' => ['list growth','launch updates','monthly local offers'], 'channels' => ['email','feed','qr'], 'pairs_with_rewards' => ['discount','dollar_credit','free_item','custom']],
            'contest_giveaway' => ['label' => 'Contest / Giveaway', 'status' => 'current', 'best_for' => ['awareness','social engagement','new customer capture'], 'channels' => ['feed','story','email','qr'], 'pairs_with_rewards' => ['free_item','discount','event_reward','media_pack','custom']],
            'qr_reward_drop' => ['label' => 'QR Reward Drop', 'status' => 'current', 'best_for' => ['in-store traffic','event activation','table tents','flyers'], 'channels' => ['qr','feed','sms','in_store'], 'pairs_with_rewards' => ['dollar_credit','free_item','discount','perk_upgrade','event_reward']],
            'referral_reward' => ['label' => 'Referral Reward', 'status' => 'current', 'best_for' => ['word of mouth','bring-a-friend','community growth'], 'channels' => ['sms','email','feed'], 'pairs_with_rewards' => ['dollar_credit','discount','perk_upgrade','custom']],
            'birthday_vip' => ['label' => 'Birthday / VIP', 'status' => 'current', 'best_for' => ['retention','loyalty','personalized offers'], 'channels' => ['email','sms','feed'], 'pairs_with_rewards' => ['free_item','discount','perk_upgrade','event_reward']],
            'agent_offer' => ['label' => 'Agent Offer', 'status' => 'current', 'best_for' => ['agent-managed promos','guided campaigns','automated recommendations'], 'channels' => ['feed','sms','email','qr'], 'pairs_with_rewards' => ['dollar_credit','free_item','discount','perk_upgrade','custom']],
            'social_engagement_challenge' => ['label' => 'Social Engagement Challenge', 'status' => 'suggested', 'best_for' => ['comments','shares','votes','tag-a-friend prompts'], 'channels' => ['feed','story','social'], 'pairs_with_rewards' => ['free_item','discount','mystery_reward','perk_upgrade']],
            'flash_drop' => ['label' => 'Limited-Time Flash Drop', 'status' => 'suggested', 'best_for' => ['slow periods','same-day sales','urgency'], 'channels' => ['feed','sms','story','qr'], 'pairs_with_rewards' => ['discount','dollar_credit','free_item','bogo']],
            'prepurchase_campaign' => ['label' => 'Pre-Purchase Campaign', 'status' => 'suggested', 'best_for' => ['future demand','present-day revenue','gift planning'], 'channels' => ['email','feed','sms'], 'pairs_with_rewards' => ['prepurchase_credit','dollar_credit','event_reward','bundle_offer']],
            'winback_campaign' => ['label' => 'Win-Back Campaign', 'status' => 'suggested', 'best_for' => ['inactive customers','lapsed redemptions','slow traffic'], 'channels' => ['email','sms','feed'], 'pairs_with_rewards' => ['discount','free_item','dollar_credit','perk_upgrade']],
            'local_event_promo' => ['label' => 'Local Event Promo', 'status' => 'suggested', 'best_for' => ['community events','live entertainment','seasonal traffic'], 'channels' => ['feed','story','email','qr','in_store'], 'pairs_with_rewards' => ['event_reward','discount','free_item','vip_access']],
            'ugc_story_campaign' => ['label' => 'UGC / Story Campaign', 'status' => 'suggested', 'best_for' => ['customer stories','before-after posts','testimonials'], 'channels' => ['feed','story','social'], 'pairs_with_rewards' => ['media_pack','discount','perk_upgrade','community_prize']],
            'loyalty_milestone' => ['label' => 'Loyalty Milestone', 'status' => 'suggested', 'best_for' => ['repeat visits','streaks','customer appreciation'], 'channels' => ['email','sms','feed'], 'pairs_with_rewards' => ['loyalty_stamp','free_item','perk_upgrade','dollar_credit']],
            'partner_cross_merchant' => ['label' => 'Partner / Cross-Merchant', 'status' => 'suggested', 'best_for' => ['local partnerships','neighborhood bundles','shared audiences'], 'channels' => ['feed','email','qr','story'], 'pairs_with_rewards' => ['partner_reward','bundle_offer','discount','community_prize']],
        ],
        'reward_types' => [
            'dollar_credit' => ['label' => 'Dollar Credit', 'status' => 'current', 'value_types' => ['fixed_amount'], 'best_for' => ['pre-purchase','referrals','high-perceived value']],
            'free_item' => ['label' => 'Free Item', 'status' => 'current', 'value_types' => ['free_item','custom'], 'best_for' => ['contests','birthday/VIP','first visit']],
            'discount' => ['label' => 'Discount', 'status' => 'current', 'value_types' => ['percent','fixed_amount'], 'best_for' => ['flash drops','win-back','newsletter signup']],
            'perk_upgrade' => ['label' => 'Perk Upgrade', 'status' => 'current', 'value_types' => ['custom'], 'best_for' => ['VIP','loyalty','service upgrade']],
            'event_reward' => ['label' => 'Event Reward', 'status' => 'current', 'value_types' => ['custom'], 'best_for' => ['local events','tickets','check-ins']],
            'audio_pack' => ['label' => 'Audio Pack', 'status' => 'current', 'value_types' => ['custom'], 'best_for' => ['music','creator content','exclusive media']],
            'media_pack' => ['label' => 'Media Pack', 'status' => 'current', 'value_types' => ['custom'], 'best_for' => ['digital bundles','exclusive content','UGC rewards']],
            'custom' => ['label' => 'Custom Reward', 'status' => 'current', 'value_types' => ['custom'], 'best_for' => ['merchant-specific offer logic']],
            'bogo' => ['label' => 'BOGO', 'status' => 'suggested', 'value_types' => ['custom'], 'best_for' => ['restaurants','retail','bring-a-friend']],
            'bundle_offer' => ['label' => 'Bundle Offer', 'status' => 'suggested', 'value_types' => ['custom'], 'best_for' => ['pre-purchase','partners','higher order value']],
            'mystery_reward' => ['label' => 'Mystery Reward', 'status' => 'suggested', 'value_types' => ['custom'], 'best_for' => ['contests','engagement','gamified drops']],
            'vip_access' => ['label' => 'VIP Access', 'status' => 'suggested', 'value_types' => ['custom'], 'best_for' => ['events','loyalty','exclusive experiences']],
            'loyalty_stamp' => ['label' => 'Loyalty Stamp', 'status' => 'suggested', 'value_types' => ['custom'], 'best_for' => ['milestones','repeat visits','streaks']],
            'partner_reward' => ['label' => 'Partner Reward', 'status' => 'suggested', 'value_types' => ['custom'], 'best_for' => ['cross-merchant promos','community bundles']],
            'prepurchase_credit' => ['label' => 'Pre-Purchase Credit', 'status' => 'suggested', 'value_types' => ['fixed_amount','custom'], 'best_for' => ['future demand','gift planning','cash flow']],
            'membership_perk' => ['label' => 'Membership Perk', 'status' => 'suggested', 'value_types' => ['custom'], 'best_for' => ['VIP','subscriptions','exclusive clubs']],
            'service_upgrade' => ['label' => 'Service Upgrade', 'status' => 'suggested', 'value_types' => ['custom'], 'best_for' => ['salons','fitness','hospitality','service businesses']],
            'community_prize' => ['label' => 'Community Prize', 'status' => 'suggested', 'value_types' => ['custom'], 'best_for' => ['fundraisers','local events','UGC campaigns']],
        ],
        'channel_packages' => [
            'feed' => ['label' => 'Feed Post', 'draft_type' => 'social', 'use_for' => ['public awareness','merchant voice','offer explanation']],
            'story' => ['label' => 'Story / Short Clip', 'draft_type' => 'social_engagement', 'use_for' => ['urgency','behind-the-scenes','24-hour content']],
            'social' => ['label' => 'Social Interaction', 'draft_type' => 'social_engagement', 'use_for' => ['comment prompts','polls','votes','tag-a-friend']],
            'sms' => ['label' => 'SMS', 'draft_type' => 'sms', 'use_for' => ['urgent CTA','flash drop','win-back']],
            'email' => ['label' => 'Email / Newsletter', 'draft_type' => 'newsletter', 'use_for' => ['longer story','monthly campaign','list growth']],
            'qr' => ['label' => 'QR / Drop', 'draft_type' => 'qr_drop', 'use_for' => ['in-store redemption','flyers','events']],
            'in_store' => ['label' => 'In-Store Script', 'draft_type' => 'campaign_package', 'use_for' => ['staff prompt','counter sign','table tent']],
        ],
        'recipes' => [
            'newsletter_growth_offer' => ['label' => 'Newsletter Growth Offer', 'campaign_type' => 'newsletter_signup', 'reward_type' => 'discount', 'channels' => ['email','feed','qr'], 'draft_types' => ['newsletter','social','qr_drop'], 'best_for' => ['list building','monthly promos']],
            'comment_to_win' => ['label' => 'Comment-to-Win Contest', 'campaign_type' => 'contest_giveaway', 'reward_type' => 'free_item', 'channels' => ['feed','story','social'], 'draft_types' => ['contest','social_engagement'], 'best_for' => ['social engagement','brand awareness']],
            'slow_day_flash_drop' => ['label' => 'Slow-Day Flash Drop', 'campaign_type' => 'flash_drop', 'reward_type' => 'discount', 'channels' => ['sms','feed','story'], 'draft_types' => ['flash_drop','sms','social'], 'best_for' => ['same-day traffic','off-peak sales']],
            'in_store_qr_reward' => ['label' => 'In-Store QR Reward', 'campaign_type' => 'qr_reward_drop', 'reward_type' => 'dollar_credit', 'channels' => ['qr','feed','in_store'], 'draft_types' => ['qr_drop','campaign_package'], 'best_for' => ['walk-in traffic','table tents','receipts']],
            'birthday_vip_gift' => ['label' => 'Birthday VIP Gift', 'campaign_type' => 'birthday_vip', 'reward_type' => 'free_item', 'channels' => ['email','sms'], 'draft_types' => ['email','sms','reward'], 'best_for' => ['retention','customer appreciation']],
            'bring_a_friend_referral' => ['label' => 'Bring-a-Friend Referral', 'campaign_type' => 'referral_reward', 'reward_type' => 'perk_upgrade', 'channels' => ['sms','email','feed'], 'draft_types' => ['campaign','sms','email'], 'best_for' => ['new customers','community referrals']],
            'future_demand_presale' => ['label' => 'Future Demand Pre-Sale', 'campaign_type' => 'prepurchase_campaign', 'reward_type' => 'prepurchase_credit', 'channels' => ['email','feed','sms'], 'draft_types' => ['campaign_package','newsletter','social'], 'best_for' => ['pre-sale revenue','gift planning']],
            'local_event_boost' => ['label' => 'Local Event Boost', 'campaign_type' => 'local_event_promo', 'reward_type' => 'event_reward', 'channels' => ['feed','story','qr','email'], 'draft_types' => ['campaign_package','qr_drop','newsletter','social'], 'best_for' => ['event attendance','community traffic']],
            'ugc_story_prize' => ['label' => 'UGC Story Prize', 'campaign_type' => 'ugc_story_campaign', 'reward_type' => 'community_prize', 'channels' => ['story','feed','social'], 'draft_types' => ['social_engagement','contest'], 'best_for' => ['customer stories','community proof']],
            'loyalty_milestone_reward' => ['label' => 'Loyalty Milestone Reward', 'campaign_type' => 'loyalty_milestone', 'reward_type' => 'loyalty_stamp', 'channels' => ['sms','email','feed'], 'draft_types' => ['campaign','sms','email','reward'], 'best_for' => ['repeat visits','retention']],
        ],
        'draft_types' => [
            'social' => 'Social Draft',
            'sms' => 'SMS Draft',
            'email' => 'Email Draft',
            'newsletter' => 'Newsletter Draft',
            'contest' => 'Contest Draft',
            'qr_drop' => 'QR Drop Draft',
            'flash_drop' => 'Flash Drop Draft',
            'social_engagement' => 'Social Engagement Draft',
            'campaign' => 'Campaign Draft',
            'campaign_package' => 'Full Campaign Package',
            'reward' => 'Reward Copy Draft',
        ],
    ];
}

function mg_ai_chat_campaign_recipe_prompt_context(): array
{
    $catalog = mg_ai_chat_campaign_recipe_catalog();
    return [
        'instruction' => 'Use this recipe catalog as modular building blocks. Mix campaign types, reward types, and channels based on merchant data, memory/docs, website sources, feed posts, social engagement signals, products, rewards, and existing campaigns. Prefer current campaign/reward types for executable drafts. Suggested types may be recommended as strategy labels and stored in review payloads until full product UI support exists.',
        'current_campaign_types' => array_keys(array_filter($catalog['campaign_types'], static fn($row) => ($row['status'] ?? '') === 'current')),
        'current_reward_types' => array_keys(array_filter($catalog['reward_types'], static fn($row) => ($row['status'] ?? '') === 'current')),
        'suggested_campaign_types' => array_keys(array_filter($catalog['campaign_types'], static fn($row) => ($row['status'] ?? '') === 'suggested')),
        'suggested_reward_types' => array_keys(array_filter($catalog['reward_types'], static fn($row) => ($row['status'] ?? '') === 'suggested')),
        'channel_packages' => $catalog['channel_packages'],
        'recipes' => $catalog['recipes'],
        'draft_types' => $catalog['draft_types'],
        'output_contract' => [
            'recommended_campaign_type',
            'recommended_reward_type',
            'channel_package',
            'recipe_key',
            'why_this_recipe',
            'draft_artifacts',
            'review_payload',
        ],
    ];
}
