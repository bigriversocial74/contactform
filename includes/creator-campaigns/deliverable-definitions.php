<?php
declare(strict_types=1);

function mg_creator_campaign_deliverable_required_tables(): array
{
    return [
        'creator_campaign_deliverables',
        'creator_campaign_participant_deliverables',
        'creator_campaign_submissions',
        'creator_campaign_submission_revisions',
        'creator_campaign_assets',
    ];
}

function mg_creator_campaign_deliverable_types(): array
{
    return ['photo','short_video','long_video','story','reel','post','article','audio','livestream','event_appearance','product_review','other'];
}

function mg_creator_campaign_deliverable_statuses(): array
{
    return ['draft','active','retired'];
}

function mg_creator_campaign_assignment_statuses(): array
{
    return ['assigned','in_progress','submitted','revision_requested','approved','rejected','published','verified','waived','cancelled'];
}

function mg_creator_campaign_submission_statuses(): array
{
    return ['draft','submitted','under_review','revision_requested','approved','rejected','published','proof_submitted','verified','withdrawn'];
}

function mg_creator_campaign_submission_transitions(): array
{
    return [
        'draft' => ['submitted','withdrawn'],
        'submitted' => ['under_review','revision_requested','approved','rejected','withdrawn'],
        'under_review' => ['revision_requested','approved','rejected'],
        'revision_requested' => ['submitted','withdrawn'],
        'approved' => ['published','proof_submitted'],
        'rejected' => ['submitted','withdrawn'],
        'published' => ['proof_submitted','verified'],
        'proof_submitted' => ['verified','revision_requested','rejected'],
        'verified' => [],
        'withdrawn' => ['submitted'],
    ];
}

function mg_creator_campaign_assert_submission_transition(string $from, string $to): void
{
    $map = mg_creator_campaign_submission_transitions();
    if (!isset($map[$from]) || !in_array($to, $map[$from], true)) {
        throw new DomainException("Invalid submission transition from {$from} to {$to}.");
    }
}

function mg_creator_campaign_deliverables_installed(PDO $pdo): bool
{
    $tables = mg_creator_campaign_deliverable_required_tables();
    $placeholders = implode(',', array_fill(0, count($tables), '?'));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ({$placeholders})");
    $stmt->execute($tables);
    return (int) $stmt->fetchColumn() === count($tables);
}

function mg_creator_campaign_deliverable_bool(mixed $value): int
{
    return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true ? 1 : 0;
}

function mg_creator_campaign_deliverable_string_list(mixed $value, string $field, int $maxItems = 25): array
{
    if ($value === null || $value === '') return [];
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        $value = json_last_error() === JSON_ERROR_NONE ? $decoded : preg_split('/\r?\n/', $value);
    }
    if (!is_array($value)) throw new InvalidArgumentException("{$field} must be a list.");
    $items = [];
    foreach ($value as $item) {
        $text = trim((string) $item);
        if ($text === '') continue;
        $items[] = mb_substr($text, 0, 500);
        if (count($items) >= $maxItems) break;
    }
    return array_values(array_unique($items));
}

function mg_creator_campaign_deliverable_url(mixed $value, string $field, bool $required = false): ?string
{
    $url = trim((string) $value);
    if ($url === '') {
        if ($required) throw new InvalidArgumentException("{$field} is required.");
        return null;
    }
    if (mb_strlen($url) > 1000 || filter_var($url, FILTER_VALIDATE_URL) === false) {
        throw new InvalidArgumentException("{$field} must be a valid URL.");
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, ['https','http'], true)) throw new InvalidArgumentException("{$field} must use HTTP or HTTPS.");
    return $url;
}
