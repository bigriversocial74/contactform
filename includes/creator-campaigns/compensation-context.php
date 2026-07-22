<?php
declare(strict_types=1);

function mg_creator_campaign_compensation_assert_schema(PDO $pdo): void
{
    mg_creator_campaign_tracking_assert_schema($pdo);
    if(!mg_creator_campaign_compensation_installed($pdo)){
        throw new RuntimeException('Creator Compensation schema is incomplete. Import database/20260722_creator_campaign_compensation_earnings_v6_single_install.sql.');
    }
}

function mg_creator_campaign_compensation_merchant_context(PDO $pdo,array $user,string $permission): array
{
    mg_creator_campaign_compensation_assert_schema($pdo);
    return mg_creator_campaign_tracking_merchant_context($pdo,$user,$permission);
}

function mg_creator_campaign_compensation_creator_context(PDO $pdo,array $user,string $permission): array
{
    mg_creator_campaign_compensation_assert_schema($pdo);
    return mg_creator_campaign_tracking_creator_context($pdo,$user,$permission);
}
