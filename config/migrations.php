<?php
declare(strict_types=1);

/**
 * Canonical Microgifter migration manifest.
 *
 * Every clean install, incremental upgrade, readiness check, and CI validation
 * must consume this file rather than maintaining a separate migration list.
 */
return [
    'ordered_files' => [
        'stage_1_identity.sql',
        'stage_1_repair_03M.sql',
        'stage_1_security_hardening_03N.sql',
        'stage_1_security_hardening_03N_3.sql',
        'stage_1_high_volume_foundation_03O.sql',
        'stage_1_delivery_events_03R.sql',
        'stage_1_foundation_closure.sql',
        '02A_user_models_identity_profiles.sql',
        '02C_model_default_roles_seed.sql',
        '02D_public_profiles_schema.sql',
        '02G_sales_model_and_crm_schema.sql',
        '02I_sales_presence_and_employee_chat.sql',
        'stage_3_agent_persistence.sql',
        'stage_3_gift_activity_persistence.sql',
        'stage_3_gift_lifecycle.sql',
        'stage_3_merchant_claim_codes.sql',
        'stage_3_pppm_core.sql',
        'stage_3_pppm_activity_layer.sql',
        'stage_3_pppm_delivery_assignment.sql',
        'stage_4_product_asset_foundation.sql',
        'stage_4b_builder_persistence.sql',
        'stage_4c_feed_stream_storefronts.sql',
        'stage_4d_digital_fulfillment_media.sql',
        'stage_4e_distribution_external_inputs.sql',
        'stage_4f_future_demand_intelligence.sql',
        'stage_5a_merchant_workspace.sql',
        'stage_5c_storefront_management.sql',
        'stage_5d_merchant_pppm_operations.sql',
        'stage_5e_merchant_distribution_operations.sql',
        'stage_5f_merchant_intelligence_reporting.sql',
        'stage_5g_claim_operations.sql',
        'stage_5h_notifications_messaging_alerts.sql',
        'stage_5i_payments_checkout_reconciliation.sql',
        'stage_3_commerce_microgift_fulfillment.sql',
        'stage_5j_foundation_reconciliation.sql',
        'stage_7b_money_engine.sql',
        'stage_8b_entitlements_library.sql',
        'stage_8c_entitlement_lifecycle.sql',
        'stage_9b_microgift_engine.sql',
        'stage_9c_microgift_lifecycle.sql',
        'stage_9d_microgift_operations.sql',
        'stage_10b_location_claim_authority.sql',
        'stage_10c_atomic_claim_redemption_inbox.sql',
        'stage_10d_merchant_claim_operations.sql',
        'stage_10e_outbox_dashboard_policies_retention.sql',
        'stage_10f_architecture_deployment_action_center.sql',
        'stage_11g_action_center_durable_messaging.sql',
        'schema_v2_action_center_crm_addendum.sql',
        'stage_11h_backend_hardening.sql',
        'stage_12_universal_tips.sql',
        'stage_12a_tip_financial_integrity.sql',
        'stage_12d_tip_recovery.sql',
        'stage_13_subscriptions_monetization.sql',
        'stage_14_posts_feed_social.sql',
        'stage_14b_social_content.sql',
        'stage_15_psr_demand_intelligence.sql',
        'stage_15c_prepaid_demand_commitments.sql',
        'stage_16_agent_execution_orchestration.sql',
        'stage_17_multi_agent_swarms.sql',
        'stage_17b_demand_signal_agent_orchestration.sql',
        'stage_18_production_hardening_launch_readiness.sql',
        'stage_18b_demand_orchestration_operations.sql',
        'stage_18c_demand_orchestration_recovery.sql',
        'stage_18c2_demand_orchestration_retention.sql',
        'stage_18d_profile_moderation.sql',
        'stage_18e_engagement_mutations.sql',
        'stage_18f_pppm_publish_distribution.sql',
    ],

    // Historical production databases may contain consolidated markers instead
    // of every earlier migration key. A marker satisfies every file through the
    // named cutoff, but never causes later migrations to be skipped.
    'coverage_markers' => [
        'stage_9e4_consolidated_stage1_to_stage9_upgrade' => 'stage_9d_microgift_operations.sql',
        'stage_11h_backend_hardening' => 'schema_v2_action_center_crm_addendum.sql',
    ],

    'manual_only' => [
        '03Z_bootstrap_super_admin_user1.sql' => 'Promotes user ID 1 to super_admin and requires explicit operator confirmation.',
    ],
];
