<?php
declare(strict_types=1);

function mg_creator_campaign_deliverable_assert_schema(PDO $pdo): void
{
    if (!mg_creator_campaign_deliverables_installed($pdo)) {
        throw new RuntimeException('Creator Deliverables schema is incomplete. Import database/20260721_creator_campaign_deliverables_v4.sql.');
    }
}

function mg_creator_campaign_deliverable_merchant_context(PDO $pdo, array $user, string $permission): array
{
    mg_creator_campaign_participation_assert_schema($pdo);
    mg_creator_campaign_deliverable_assert_schema($pdo);
    return mg_creator_campaign_participation_merchant_context($pdo, $user, $permission);
}

function mg_creator_campaign_deliverable_creator_context(PDO $pdo, array $user, string $permission): array
{
    mg_creator_campaign_participation_assert_schema($pdo);
    mg_creator_campaign_deliverable_assert_schema($pdo);
    return mg_creator_campaign_creator_context($pdo, $user, $permission);
}

function mg_creator_campaign_deliverable_require_active_participant(array $participant): void
{
    if ((string) ($participant['participant_status'] ?? $participant['status'] ?? '') !== 'active') {
        throw new DomainException('An accepted active Creator Campaign agreement is required before deliverables can be managed.');
    }
    if ((int) ($participant['latest_accepted_version_id'] ?? 0) < 1) {
        throw new DomainException('The participant does not have an accepted agreement version.');
    }
}
