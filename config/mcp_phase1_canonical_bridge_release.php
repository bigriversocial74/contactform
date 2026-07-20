<?php
declare(strict_types=1);

return [
    'release_key' => 'microgifter_mcp_phase1_canonical_bridge_v1',
    'program' => 'microgifter_platform_phase5',
    'phase' => 'mcp_phase1_canonical_bridge',
    'depends_on' => [
        'microgifter_mcp_phase1_protocol_v1',
        'microgifter_mcp_phase1_foundation_v1',
    ],
    'required_migrations' => [],
    'foundation_migration' => '20260720_microgifter_mcp_automation_foundation_v1.sql',
    'endpoint' => '/api/internal/mcp-bridge.php',
    'operations' => [
        'connection.resolve' => 'profile:read',
        'catalog.search' => 'catalog:read',
        'catalog.get_item' => 'catalog:read',
        'receipt.record' => 'resolved_receipt_scope',
    ],
    'tools_activated' => [
        'microgifter.catalog.search',
        'microgifter.catalog.get_item',
    ],
    'authentication' => [
        'algorithm' => 'hmac_sha256',
        'timestamp_window_seconds' => 300,
        'nonce_replay_table' => 'mcp_idempotency_keys',
        'secret_minimum_length' => 32,
    ],
    'authority' => [
        'connection_status_rechecked',
        'client_status_rechecked',
        'user_status_rechecked',
        'connection_expiry_rechecked',
        'workspace_membership_rechecked',
        'database_scopes_rechecked_per_request',
        'maximum_operation_class_read_only',
    ],
    'canonical_services' => [
        'mg_product_discovery_search',
        'mg_public_product_load',
    ],
    'privacy' => [
        'no_email_or_phone_projection',
        'no_exact_street_address_projection',
        'no_private_catalog_metadata_projection',
        'published_products_only',
        'social_block_rules_preserved',
    ],
    'receipts' => [
        'table' => 'mcp_tool_invocations',
        'success_and_failure_recorded' => true,
        'input_fingerprint_only' => true,
    ],
    'boundaries' => [
        'disabled_by_default',
        'internal_http_only',
        'no_external_oauth',
        'no_node_database_credentials',
        'no_write_tools',
        'no_scheduler_or_worker',
        'no_unbounded_autonomous_mode',
    ],
];
