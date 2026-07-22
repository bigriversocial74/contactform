<?php
declare(strict_types=1);

function mg_creator_campaign_message_assert_schema(PDO $pdo): void
{
    mg_creator_campaign_payout_assert_schema($pdo);
    if (!mg_creator_campaign_message_installed($pdo)) {
        throw new RuntimeException('Creator Campaign Messaging schema is incomplete. Import database/20260722_creator_campaign_messaging_notifications_v9_single_install.sql.');
    }
}

function mg_creator_campaign_message_merchant_context(PDO $pdo, array $user, string $permission): array
{
    mg_creator_campaign_message_assert_schema($pdo);
    return mg_creator_campaign_payout_merchant_context($pdo, $user, $permission);
}

function mg_creator_campaign_message_creator_context(PDO $pdo, array $user, string $permission): array
{
    mg_creator_campaign_message_assert_schema($pdo);
    return mg_creator_campaign_payout_creator_context($pdo, $user, $permission);
}
