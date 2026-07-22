<?php
declare(strict_types=1);

/** Creator Campaign Phase 3 participation and agreement definitions. */
function mg_creator_campaign_application_statuses(): array
{
    return ['draft','submitted','under_review','information_requested','approved','declined','withdrawn'];
}

function mg_creator_campaign_invitation_statuses(): array
{
    return ['pending','accepted','declined','cancelled','expired'];
}

function mg_creator_campaign_participant_statuses(): array
{
    return ['approved','agreement_pending','active','completed','declined','removed','suspended'];
}

function mg_creator_campaign_agreement_statuses(): array
{
    return ['draft','offered','accepted','declined','cancelled'];
}

function mg_creator_campaign_agreement_version_statuses(): array
{
    return ['draft','offered','accepted','declined','superseded','cancelled'];
}

function mg_creator_campaign_application_transitions(): array
{
    return [
        'draft' => ['submitted','approved','withdrawn'],
        'submitted' => ['under_review','information_requested','approved','declined','withdrawn'],
        'under_review' => ['information_requested','approved','declined','withdrawn'],
        'information_requested' => ['submitted','approved','declined','withdrawn'],
        'approved' => [],
        'declined' => [],
        'withdrawn' => ['submitted','approved'],
    ];
}

function mg_creator_campaign_invitation_transitions(): array
{
    return [
        'pending' => ['accepted','declined','cancelled','expired'],
        'accepted' => [],
        'declined' => ['pending'],
        'cancelled' => ['pending'],
        'expired' => ['pending'],
    ];
}

function mg_creator_campaign_participant_transitions(): array
{
    return [
        'approved' => ['agreement_pending','removed','suspended'],
        'agreement_pending' => ['active','declined','removed','suspended'],
        'active' => ['agreement_pending','completed','removed','suspended'],
        'completed' => [],
        'declined' => [],
        'removed' => [],
        'suspended' => ['agreement_pending','removed'],
    ];
}

function mg_creator_campaign_agreement_transitions(): array
{
    return [
        'draft' => ['offered','cancelled'],
        'offered' => ['accepted','declined','cancelled'],
        'accepted' => ['offered','cancelled'],
        'declined' => ['offered','cancelled'],
        'cancelled' => [],
    ];
}

function mg_creator_campaign_assert_transition(string $domain, string $from, string $to): void
{
    $map = match ($domain) {
        'application' => mg_creator_campaign_application_transitions(),
        'invitation' => mg_creator_campaign_invitation_transitions(),
        'participant' => mg_creator_campaign_participant_transitions(),
        'agreement' => mg_creator_campaign_agreement_transitions(),
        default => throw new InvalidArgumentException('Unknown Creator Campaign transition domain.'),
    };
    if (!isset($map[$from]) || !in_array($to, $map[$from], true)) {
        throw new DomainException("Invalid {$domain} transition from {$from} to {$to}.");
    }
}

function mg_creator_campaign_participation_campaign_is_closed(array $campaign): bool
{
    return in_array((string) ($campaign['status'] ?? ''), ['completed','cancelled','archived'], true);
}

function mg_creator_campaign_participation_campaign_accepts_applications(array $campaign): bool
{
    if (mg_creator_campaign_participation_campaign_is_closed($campaign)) return false;
    if (!in_array((string) ($campaign['access_mode'] ?? ''), ['open','approved_creators','hybrid'], true)) return false;
    $deadline = trim((string) ($campaign['application_deadline_at'] ?? ''));
    return $deadline === '' || strtotime($deadline . ' UTC') >= time();
}

function mg_creator_campaign_participation_campaign_accepts_invitations(array $campaign): bool
{
    if (mg_creator_campaign_participation_campaign_is_closed($campaign)) return false;
    return in_array((string) ($campaign['access_mode'] ?? ''), ['invite_only','selected_creators','approved_creators','hybrid'], true);
}

function mg_creator_campaign_participation_public_campaign(array $campaign): bool
{
    return in_array((string) ($campaign['status'] ?? ''), ['scheduled','active'], true)
        && mg_creator_campaign_participation_campaign_accepts_applications($campaign);
}

function mg_creator_campaign_agreements_installed(PDO $pdo): bool
{
    $tables = [
        'creator_campaign_agreements',
        'creator_campaign_agreement_versions',
        'creator_campaign_agreement_acceptances',
    ];
    $placeholders = implode(',', array_fill(0, count($tables), '?'));
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema=DATABASE() AND table_name IN ({$placeholders})"
    );
    $stmt->execute($tables);
    return (int) $stmt->fetchColumn() === count($tables);
}

/** Backward-compatible name used by the Phase 2 builder/status gate. */
function mg_creator_campaign_participation_phase4_installed(PDO $pdo): bool
{
    return mg_creator_campaign_agreements_installed($pdo);
}
