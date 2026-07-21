<?php
declare(strict_types=1);
return [
    'release_key' => 'microgifter_mcp_native_draft_status_phase3c_v1',
    'program' => 'microgifter_platform_phase5',
    'phase' => 'mcp_native_draft_status_phase3c',
    'depends_on' => ['microgifter_mcp_approved_draft_conversion_phase3b_v1'],
    'required_migrations' => [],
    'operation_ceiling' => 'read_only_native_status',
    'tools' => ['microgifter.drafts.get_handoff_status', 'microgifter.drafts.list_handoffs'],
    'capabilities' => ['canonical_native_status_resolution', 'event_ledger_state_change_receipts', 'connection_scoped_handoff_visibility', 'owner_status_refresh'],
];
