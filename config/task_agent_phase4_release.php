<?php
declare(strict_types=1);

return [
    'release_key' => 'task_agent_phase4_v1',
    'depends_on' => ['task_agent_phase3_v1'],
    'required_migrations' => [
        '20260720_task_agent_phase4_v1.sql',
    ],
    'migration_after' => '20260720_task_agent_phase3_shortlist_v1.sql',
    'runtime_assets' => [
        '/assets/js/task-agent-recurring-programs-runtime.js?v=1.0.0',
        '/assets/css/task-agent-recurring-programs-v1.css?v=1.0.0',
        '/assets/js/task-agent-group-gifts-runtime.js?v=1.0.0',
        '/assets/css/task-agent-group-gifts-v1.css?v=1.0.0',
        '/assets/js/task-agent-program-coordination-runtime.js?v=1.0.0',
        '/assets/css/task-agent-program-coordination-v1.css?v=1.0.0',
        '/assets/js/task-agent-policy-approvals-runtime.js?v=1.0.0',
        '/assets/css/task-agent-policy-approvals-v1.css?v=1.0.0',
        '/assets/js/task-agent-monitoring-runtime.js?v=1.0.0',
        '/assets/css/task-agent-monitoring-v1.css?v=1.0.0',
    ],
    'canonical_authorities' => [
        'recurring_programs' => ['user_recurring_gift_programs','user_recurring_gift_runs','user_gifting_plans'],
        'group_gifting' => ['user_group_gifts','user_group_gift_participants','user_contact_lists'],
        'distribution_programs' => ['distribution_programs','distribution_program_products','distribution_recipients','distribution_allocations','distribution_issuance_jobs'],
        'rules_and_approvals' => ['agent_strategies','agent_workflow_runs','agent_workflow_actions','agent_approval_requests'],
        'delivery_readiness' => ['user_gifting_schedules','user_recipient_data_requests'],
    ],
    'release_boundaries' => [
        'existing_program_authorities_are_reused_not_duplicated',
        'recurring_cycles_create_reviewable_draft_plans_only',
        'group_gifts_are_pledge_only_and_collect_no_payment',
        'distribution_program_mutations_stay_in_canonical_merchant_workspaces',
        'rules_budgets_strategies_and_approval_decisions_are_read_only_in_specialized_agents',
        'monitoring_is_on_demand_and_persists_no_alert_feed',
        'recipient_readiness_exposes_booleans_not_private_address_values',
        'no_automatic_purchase_message_allocation_issuance_or_approval',
        'routine_phase4_routes_use_system_queries_and_zero_ai_credits',
        'ai_is_reserved_for_explicit_sanitized_synthesis_only',
    ],
];
