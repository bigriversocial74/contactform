<?php
declare(strict_types=1);
return [
    'release_key' => 'microgifter_mcp_native_draft_status_phase3c_v1',
    'phase' => 'mcp_native_draft_status_phase3c',
    'depends_on' => ['microgifter_mcp_approved_draft_conversion_phase3b_v1'],
    'required_migrations' => [],
    'operation_ceiling' => 'read_only_native_status',
    'capabilities' => [
        'existing_draft_read_enrichment',
        'canonical_native_status_resolution',
        'event_ledger_state_change_receipts',
        'owner_status_refresh',
    ],
];
