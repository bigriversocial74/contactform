<?php
declare(strict_types=1);

return [
    'release_key' => 'microgifter_mcp_automation_grants_phase4a_v1',
    'phase' => 'mcp_automation_grants_phase4a',
    'depends_on' => [
        'microgifter_mcp_native_draft_status_phase3c_v1',
    ],
    'required_migrations' => [
        '20260720_microgifter_mcp_automation_foundation_v1',
        '20260720_mcp_external_agent_authorization_phase2a_v1',
        '20260720_mcp_approval_gated_drafts_phase3a_v1',
        '20260720_mcp_approved_draft_conversion_phase3b_v1',
    ],
    'new_migrations' => [],
    'operation_ceiling' => 'owner_grant_configuration_only',
    'runtime_execution_enabled' => false,
    'allowed_trigger_types' => ['manual'],
    'approval_policy' => 'always',
    'maximum_risk_ceiling' => 'medium',
    'capabilities' => [
        'owner_scoped_durable_grant_creation',
        'fixed_playbook_and_tool_allowlists',
        'scope_and_workspace_revalidation',
        'amount_quantity_frequency_target_and_expiration_limits',
        'activate_pause_resume_and_permanent_revoke_controls',
        'revocation_version_and_queued_run_cancellation_request',
        'fail_closed_future_worker_policy_evaluator',
        'audit_event_and_mcp_security_evidence',
    ],
    'prohibited' => [
        'automation_definition_creation',
        'schedule_creation',
        'canonical_event_trigger_registration',
        'queue_or_worker_execution',
        'external_effect_action',
        'purchase_or_charge',
        'gift_issuance_or_delivery',
        'campaign_publication_or_scheduling',
        'message_sending',
        'reward_activation_or_fulfillment',
        'node_runtime_activation',
        'production_key_generation',
    ],
];
