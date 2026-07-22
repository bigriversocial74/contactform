<?php
declare(strict_types=1);

function mg_creator_campaign_analytics_assert_schema(PDO $pdo): void
{
    mg_creator_campaign_message_assert_schema($pdo);
}

function mg_creator_campaign_analytics_merchant_context(PDO $pdo, array $user): array
{
    mg_creator_campaign_analytics_assert_schema($pdo);
    return mg_creator_campaign_message_merchant_context($pdo, $user, 'merchant.intelligence.view');
}

function mg_creator_campaign_analytics_creator_context(PDO $pdo, array $user): array
{
    mg_creator_campaign_analytics_assert_schema($pdo);
    // Phase 9 maps this Creator-owned view permission to the canonical customer role.
    // The shared context still requires the active Creator model, approved profile,
    // and object ownership before any campaign analytics can be returned.
    return mg_creator_campaign_message_creator_context($pdo, $user, 'creator.campaign_messages.view_own');
}
