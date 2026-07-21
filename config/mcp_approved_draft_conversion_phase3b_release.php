<?php
declare(strict_types=1);

return [
    'release_key' => 'microgifter_mcp_approved_draft_conversion_phase3b_v1',
    'program' => 'microgifter_platform_phase5',
    'phase' => 'mcp_approved_draft_conversion_phase3b',
    'depends_on' => ['microgifter_mcp_approval_gated_drafts_phase3a_v1'],
    'required_migrations' => ['20260720_mcp_approved_draft_conversion_phase3b_v1'],
    'operation_ceiling' => 'human_created_native_draft',
    'conversion_types' => ['gift_draft','campaign_draft','reward_template_draft','message_draft'],
    'capabilities' => [
        'owner_prepares_conversion',
        'owner_creates_inactive_native_draft',
        'idempotent_one_conversion_per_source_draft',
        'owner_only_open_redirect',
        'immutable_conversion_events',
        'live_permission_recheck',
        'live_workspace_access_recheck',
        'merchant_package_limit_recheck',
    ],
    'security' => [
        'source_draft_must_be_approved',
        'separate_owner_action_after_approval',
        'csrf_protected_prepare_create_cancel',
        'same_origin_relative_native_urls_only',
        'execution_enabled_false',
        'no_external_agent_conversion_tool',
        'no_direct_node_database_access',
    ],
    'boundaries' => [
        'no_publish',
        'no_message_send',
        'no_message_schedule',
        'no_commerce_execution',
        'no_gift_issuance_or_delivery',
        'no_reward_activation_or_fulfillment',
        'no_campaign_launch',
        'no_task_agent_execution_queue',
        'no_mcp_automation_worker_queue',
    ],
];
