<?php
declare(strict_types=1);

/**
 * Shared quick-action catalog for Personal Agent and Merchant Agent.
 *
 * Keep prompts here so the sidebar modal, tests, and future additions use one
 * authoritative list rather than duplicating commands in page markup.
 */
function mg_agent_quick_action_catalog(): array
{
    return [
        'personal' => [
            'title' => 'Personal Agent',
            'description' => 'Private account intelligence, gifting plans, reminders, saved opportunities, and marketplace discovery.',
            'suggestions' => [
                ['icon'=>'◎','label'=>'Count my contacts','detail'=>'Read the current private contact total.','prompt'=>'How many contacts do I have?'],
                ['icon'=>'◷','label'=>'Upcoming birthdays','detail'=>'Find birthdays and important dates coming soon.','prompt'=>'Who has a birthday coming up?'],
                ['icon'=>'!','label'=>'Missing birthdays','detail'=>'Find contacts that need birthday information.','prompt'=>'Which contacts are missing birthdays?'],
                ['icon'=>'✦','label'=>'Relationship signals','detail'=>'Review people and occasions that may need planning.','prompt'=>'Show my relationship signals and next gifting opportunities.'],
                ['icon'=>'☆','label'=>'Saved opportunities','detail'=>'Reopen saved Personal Agent recommendations.','prompt'=>'Show my saved opportunities.'],
                ['icon'=>'↻','label'=>'Resume unfinished actions','detail'=>'Find unfinished carts, checkouts, gifts, or campaigns.','prompt'=>'Resume my unfinished opportunities.'],
                ['icon'=>'↓','label'=>'Show Inbox gifts','detail'=>'Load current received gifts as actionable cards.','prompt'=>'Show my Inbox gifts.'],
                ['icon'=>'◫','label'=>'Review Agent Memory','detail'=>'Summarize the preferences currently available to the agent.','prompt'=>'What do you remember about my gifting preferences?'],
                ['icon'=>'◇','label'=>'Prepare a gift plan','detail'=>'Create an approval-first plan without purchasing or sending.','prompt'=>'Help me prepare an approval-first gift plan for an upcoming occasion.'],
            ],
            'keyword_groups' => [
                [
                    'label' => 'Account intelligence',
                    'items' => [
                        ['keyword'=>'contacts','detail'=>'Show private contacts','prompt'=>'Show all my contacts.'],
                        ['keyword'=>'contact count','detail'=>'Current contact total','prompt'=>'How many contacts do I have?'],
                        ['keyword'=>'list count','detail'=>'Current list total','prompt'=>'How many lists do I have?'],
                        ['keyword'=>'birthdays','detail'=>'Upcoming birthdays','prompt'=>'Who has a birthday coming up?'],
                        ['keyword'=>'missing birthdays','detail'=>'Contacts without birthdays','prompt'=>'Which contacts are missing birthdays?'],
                        ['keyword'=>'signals','detail'=>'Relationship and occasion signals','prompt'=>'Show my relationship signals and next gifting opportunities.'],
                    ],
                ],
                [
                    'label' => 'Gifts and opportunities',
                    'items' => [
                        ['keyword'=>'inbox','detail'=>'Received gifts','prompt'=>'Show my Inbox gifts.'],
                        ['keyword'=>'sent','detail'=>'Outbound gifts','prompt'=>'Show my Sent gifts.'],
                        ['keyword'=>'claimed','detail'=>'Claimed gifts and rewards','prompt'=>'Show my Claimed gifts.'],
                        ['keyword'=>'saved opportunities','detail'=>'Saved recommendations','prompt'=>'Show my saved opportunities.'],
                        ['keyword'=>'resume','detail'=>'Unfinished actions','prompt'=>'Resume my unfinished opportunities.'],
                        ['keyword'=>'similar','detail'=>'Similar local options','prompt'=>'Find something similar to my last recommendation.'],
                        ['keyword'=>'remind me','detail'=>'Follow up on the latest opportunity','prompt'=>'Remind me tomorrow about my most recent opportunity.'],
                    ],
                ],
                [
                    'label' => 'Planning and account actions',
                    'items' => [
                        ['keyword'=>'memory','detail'=>'Review saved preferences','prompt'=>'What do you remember about my gifting preferences?'],
                        ['keyword'=>'create contact','detail'=>'Prepare a private contact for confirmation','prompt'=>'Create a contact called '],
                        ['keyword'=>'create list','detail'=>'Prepare a contact list for confirmation','prompt'=>'Create a contact list called '],
                        ['keyword'=>'set birthday','detail'=>'Prepare a birthday update','prompt'=>"Set [name]'s birthday to "],
                        ['keyword'=>'add date','detail'=>'Prepare an important date','prompt'=>'Add an important date for [name] called [occasion] on '],
                        ['keyword'=>'schedule reminder','detail'=>'Prepare a gifting reminder','prompt'=>'Schedule a gifting reminder for [name] on '],
                        ['keyword'=>'/m','detail'=>'Route a request to Merchant Agent','prompt'=>'/m snapshot'],
                    ],
                ],
            ],
        ],
        'merchant' => [
            'title' => 'Merchant Agent',
            'description' => 'Permission-checked business intelligence, database snapshots, merchant memory, campaigns, CRM, and review-ready actions.',
            'suggestions' => [
                ['icon'=>'▦','label'=>'Current snapshot','detail'=>'Query the latest 30-day merchant activity from the database.','prompt'=>'snapshot'],
                ['icon'=>'30','label'=>'30-day snapshot','detail'=>'Purchases, customers, campaigns, engagement, and action signals.','prompt'=>'snapshot 30 days'],
                ['icon'=>'90','label'=>'90-day snapshot','detail'=>'Review a wider merchant performance window.','prompt'=>'snapshot 90 days'],
                ['icon'=>'!','label'=>'What needs attention','detail'=>'Find customers, orders, rewards, claims, and reviews requiring action.','prompt'=>'Review my merchant data and tell me what requires action today.'],
                ['icon'=>'◎','label'=>'CRM follow-ups','detail'=>'Find customer follow-up and engagement opportunities.','prompt'=>'Use recent CRM activity as context and find customer follow-up opportunities.'],
                ['icon'=>'◈','label'=>'Campaign review','detail'=>'Review current campaigns and prepare improvements.','prompt'=>'Review my active campaigns and prepare an approval-first improvement plan.'],
                ['icon'=>'◇','label'=>'Product opportunities','detail'=>'Review products, availability, purchases, and demand signals.','prompt'=>'Review my products, purchases, and availability for current opportunities.'],
                ['icon'=>'◫','label'=>'Add merchant memory','detail'=>'Open the existing Merchant Memory workflow.','prompt'=>'MEMORY'],
                ['icon'=>'✦','label'=>'Create action plan','detail'=>'Prepare a review-ready plan from current merchant context.','prompt'=>'Create a review-ready action plan from the current merchant context.'],
            ],
            'keyword_groups' => [
                [
                    'label' => 'Database snapshots',
                    'items' => [
                        ['keyword'=>'snapshot','detail'=>'Default database snapshot','prompt'=>'snapshot'],
                        ['keyword'=>'/snapshot','detail'=>'Slash form','prompt'=>'/snapshot'],
                        ['keyword'=>'current snapshot','detail'=>'Current merchant snapshot','prompt'=>'current snapshot'],
                        ['keyword'=>'merchant snapshot','detail'=>'Merchant snapshot alias','prompt'=>'merchant snapshot'],
                        ['keyword'=>'snapshot 7','detail'=>'Last 7 days','prompt'=>'snapshot 7 days'],
                        ['keyword'=>'snapshot 30','detail'=>'Last 30 days','prompt'=>'snapshot 30 days'],
                        ['keyword'=>'snapshot 90','detail'=>'Last 90 days','prompt'=>'snapshot 90 days'],
                        ['keyword'=>'snapshot 365','detail'=>'Last year','prompt'=>'snapshot 365 days'],
                    ],
                ],
                [
                    'label' => 'Merchant Memory',
                    'items' => [
                        ['keyword'=>'memory','detail'=>'Open the Merchant Memory menu','prompt'=>'MEMORY'],
                        ['keyword'=>'brand voice','detail'=>'Store brand voice guidance','prompt'=>'MEMORY\nBrand voice: '],
                        ['keyword'=>'campaign style','detail'=>'Store campaign preferences','prompt'=>'MEMORY\nCampaign style: '],
                        ['keyword'=>'customer tone','detail'=>'Store customer communication tone','prompt'=>'MEMORY\nCustomer tone: '],
                        ['keyword'=>'default offer type','detail'=>'Store preferred offer structure','prompt'=>'MEMORY\nDefault offer type: '],
                        ['keyword'=>'business goals','detail'=>'Store current business goals','prompt'=>'MEMORY\nBusiness goals: '],
                        ['keyword'=>'local market notes','detail'=>'Store local market context','prompt'=>'MEMORY\nLocal market notes: '],
                    ],
                ],
                [
                    'label' => 'Analysis scopes',
                    'items' => [
                        ['keyword'=>'campaigns','detail'=>'Campaign performance and planning','prompt'=>'Review my active campaigns and recommend next steps.'],
                        ['keyword'=>'CRM','detail'=>'Customer activity and follow-ups','prompt'=>'Use recent CRM activity as context and find customer follow-up opportunities.'],
                        ['keyword'=>'products','detail'=>'Products and availability','prompt'=>'Review my products and current availability.'],
                        ['keyword'=>'rewards','detail'=>'Reward activity and gaps','prompt'=>'Use rewards and claims as context and flag any issues or opportunities.'],
                        ['keyword'=>'claims','detail'=>'Claims and redemptions','prompt'=>'Review current claims and redemption activity.'],
                        ['keyword'=>'analytics','detail'=>'Analysis and charts','prompt'=>'Use the Analysis + Charts skill to review my products, claims, redemptions, and opportunities.'],
                        ['keyword'=>'locations','detail'=>'Merchant location operations','prompt'=>'Review my merchant locations and identify operational opportunities.'],
                        ['keyword'=>'developer API','detail'=>'Connected app and API context','prompt'=>'Review my Developer API activity and integration readiness.'],
                    ],
                ],
                [
                    'label' => 'Output and approval',
                    'items' => [
                        ['keyword'=>'action plan','detail'=>'Review-ready action plan','prompt'=>'Create a review-ready action plan from the current merchant context.'],
                        ['keyword'=>'message draft','detail'=>'Customer-facing draft','prompt'=>'Prepare a customer message draft from the current merchant context.'],
                        ['keyword'=>'review checklist','detail'=>'Operational checklist','prompt'=>'Create a review checklist for the current merchant opportunity.'],
                        ['keyword'=>'campaign idea','detail'=>'New campaign concept','prompt'=>'Create a campaign idea using my current merchant data.'],
                        ['keyword'=>'social campaign','detail'=>'Social Campaign Advisor','prompt'=>'Use the Social Campaign Advisor skill to create social media campaign advice based on my merchant data.'],
                        ['keyword'=>'review queue','detail'=>'Prepare an action for review','prompt'=>'Prepare this as a review-queue recommendation without executing it.'],
                    ],
                ],
            ],
        ],
    ];
}

function mg_agent_quick_actions(string $mode): array
{
    $catalog = mg_agent_quick_action_catalog();
    return $catalog[$mode === 'merchant' ? 'merchant' : 'personal'];
}
