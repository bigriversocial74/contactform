<?php
declare(strict_types=1);

function mg_creator_campaign_budget_assert_schema(PDO $pdo): void
{
    mg_creator_campaign_compensation_assert_schema($pdo);
    if(!mg_creator_campaign_budget_installed($pdo)) throw new RuntimeException('Creator Campaign Budget schema is incomplete. Import database/20260722_creator_campaign_budget_controls_v7_single_install.sql.');
}

function mg_creator_campaign_budget_merchant_context(PDO $pdo,array $user,string $permission): array
{
    mg_creator_campaign_budget_assert_schema($pdo);
    return mg_creator_campaign_compensation_merchant_context($pdo,$user,$permission);
}
