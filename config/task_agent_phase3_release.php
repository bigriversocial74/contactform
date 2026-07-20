<?php
declare(strict_types=1);

return [
    'release_key' => 'task_agent_phase3_v1',
    'required_migrations' => [
        '20260714_user_contact_lists_phase1.sql',
        'stage_19_ai_provider_models.sql',
        '20260714_personal_gifting_agent_phase2.sql',
        '20260714_personal_gifting_workflows_phase3.sql',
        '20260719_multi_agent_runtime_memory_v1.sql',
        '20260720_task_agent_phase3_shortlist_v1.sql',
    ],
    'runtime_assets' => [
        '/assets/js/multi-agent-runtime.js?v=1.7.0',
        '/assets/js/task-agent-shortlist-runtime.js?v=1.1.0',
        '/assets/js/task-agent-delivery-runtime.js?v=1.0.0',
        '/assets/js/task-agent-order-tracking-runtime.js?v=1.0.0',
        '/assets/js/task-agent-lifecycle-runtime.js?v=1.0.0',
    ],
    'release_boundaries' => [
        'discovery_and_shortlisting_are_deterministic',
        'plan_selection_requires_explicit_approval',
        'cart_handoff_uses_canonical_commerce_api',
        'delivery_schedules_are_prepare_only',
        'recipient_information_is_permission_scoped',
        'purchase_and_pppm_tracking_are_read_only',
        'lifecycle_mutations_require_action_center_handoff',
        'claim_and_redemption_codes_never_enter_agent_context',
        'ai_is_used_only_for_explicit_synthesis',
    ],
];
