<?php
declare(strict_types=1);

return [
    'release_key' => 'microgifter_mcp_automation_operations_phase4d_v1',
    'phase' => 'mcp_automation_operations_phase4d',
    'depends_on' => ['microgifter_mcp_automation_schedules_phase4c_v1'],
    'required_migrations' => [
        '20260720_microgifter_mcp_automation_foundation_v1',
        '20260720_mcp_external_agent_authorization_phase2a_v1',
        '20260720_mcp_approval_gated_drafts_phase3a_v1',
        '20260720_mcp_approved_draft_conversion_phase3b_v1',
    ],
    'new_migrations' => [],
    'operation_ceiling' => 'owner_operations_and_emergency_control_only',
    'runtime_execution_enabled' => false,
    'background_scheduler_enabled' => false,
    'worker_enabled' => false,
    'public_mcp_transport_enabled' => false,
    'action_receipts_expected' => 0,
    'capabilities' => [
        'owner_operations_dashboard',
        'owner_wide_emergency_pause',
        'connection_scoped_automation_pause',
        'mutable_run_cancellation_requests',
        'grant_definition_trigger_run_health_summary',
        'recent_security_event_history',
        'receipt_boundary_monitoring',
        'revocation_version_increment_on_emergency_pause',
    ],
    'prohibited' => [
        'bulk_resume','automatic_resume','cron_or_background_scheduler','queue_or_worker_execution',
        'canonical_command_invocation','action_receipt_creation','external_effect_action','purchase_or_charge',
        'gift_issuance_or_delivery','campaign_publication_or_scheduling','message_sending',
        'reward_activation_or_fulfillment','node_runtime_activation','production_key_generation',
    ],
];
