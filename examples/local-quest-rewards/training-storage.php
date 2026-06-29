<?php
 declare(strict_types=1);

/**
 * Training Campaign Lab storage helpers.
 *
 * Phase 3 read model:
 * - Reads from SQL when configured and schema exists.
 * - Falls back to training-campaign-data.php when SQL is unavailable.
 * - Does not modify existing Local Quest storage files.
 */

require_once __DIR__ . '/training-campaign-data.php';

function tcl_storage_config(): array
{
    if (function_exists('lqr_config')) {
        $config = lqr_config();
        return is_array($config) ? $config : [];
    }

    $path = __DIR__ . '/config.php';
    if (!is_file($path)) $path = __DIR__ . '/config.example.php';
    $config = require $path;
    return is_array($config) ? $config : [];
}

function tcl_storage_h(string $value): string
{
    return function_exists('lqr_h') ? lqr_h($value) : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function tcl_storage_pdo(): ?PDO
{
    static $pdo = null;
    static $attempted = false;

    if ($attempted) return $pdo;
    $attempted = true;

    $config = tcl_storage_config();
    $storage = is_array($config['storage'] ?? null) ? $config['storage'] : [];
    if (($storage['driver'] ?? '') !== 'mysql') return null;

    $dsn = trim((string)($storage['dsn'] ?? ''));
    if ($dsn === '') return null;

    try {
        $options = is_array($storage['options'] ?? null) ? $storage['options'] : [];
        $options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;
        $options[PDO::ATTR_DEFAULT_FETCH_MODE] = PDO::FETCH_ASSOC;
        $pdo = new PDO($dsn, (string)($storage['username'] ?? ''), (string)($storage['password'] ?? ''), $options);
        return $pdo;
    } catch (Throwable $e) {
        return null;
    }
}

function tcl_storage_schema_available(): bool
{
    $pdo = tcl_storage_pdo();
    if (!$pdo) return false;

    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'training_campaigns'");
        return (bool)$stmt && (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function tcl_storage_using_sql(): bool
{
    return tcl_storage_schema_available();
}

function tcl_storage_seed_campaigns(): array
{
    return tcl_campaigns();
}

function tcl_storage_campaigns(): array
{
    if (!tcl_storage_schema_available()) return tcl_storage_seed_campaigns();

    $pdo = tcl_storage_pdo();
    if (!$pdo) return tcl_storage_seed_campaigns();

    try {
        $stmt = $pdo->query("SELECT * FROM training_campaigns WHERE status IN ('active','draft','paused','completed') ORDER BY FIELD(status,'active','draft','paused','completed'), title ASC");
        $campaigns = [];
        foreach ($stmt->fetchAll() as $row) {
            $campaign = tcl_storage_normalize_campaign_row($row);
            $campaign['sequence_count'] = tcl_storage_count_for_campaign('training_sequences', (int)$row['id']);
            $campaign['task_count'] = tcl_storage_count_for_campaign('training_tasks', (int)$row['id']);
            $campaign['participant_count'] = tcl_storage_count_for_campaign('training_participants', (int)$row['id']);
            $campaign['reward_ladder'] = tcl_storage_reward_ladder((int)$row['id']);
            $campaign['sequence'] = tcl_storage_first_sequence_bundle((int)$row['id']);
            $campaigns[(string)$campaign['id']] = $campaign;
        }
        return $campaigns ?: tcl_storage_seed_campaigns();
    } catch (Throwable $e) {
        return tcl_storage_seed_campaigns();
    }
}

function tcl_storage_count_for_campaign(string $table, int $campaignId): int
{
    $allowed = ['training_sequences','training_tasks','training_participants'];
    if (!in_array($table, $allowed, true)) return 0;
    $pdo = tcl_storage_pdo();
    if (!$pdo) return 0;

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE campaign_id = :campaign_id");
        $stmt->execute(['campaign_id' => $campaignId]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function tcl_storage_campaign_by_slug(string $slug): ?array
{
    $slug = trim($slug);
    if ($slug === '') return null;

    if (!tcl_storage_schema_available()) {
        $campaigns = tcl_storage_seed_campaigns();
        return $campaigns[$slug] ?? null;
    }

    $pdo = tcl_storage_pdo();
    if (!$pdo) return null;

    try {
        $stmt = $pdo->prepare('SELECT * FROM training_campaigns WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();
        if (!$row) return null;

        $campaign = tcl_storage_normalize_campaign_row($row);
        $campaign['sequence_count'] = tcl_storage_count_for_campaign('training_sequences', (int)$row['id']);
        $campaign['task_count'] = tcl_storage_count_for_campaign('training_tasks', (int)$row['id']);
        $campaign['participant_count'] = tcl_storage_count_for_campaign('training_participants', (int)$row['id']);
        $campaign['reward_ladder'] = tcl_storage_reward_ladder((int)$row['id']);
        $campaign['sequences'] = tcl_storage_sequences((int)$row['id']);
        $campaign['sequence'] = $campaign['sequences'][0] ?? tcl_storage_first_sequence_bundle((int)$row['id']);
        return $campaign;
    } catch (Throwable $e) {
        $campaigns = tcl_storage_seed_campaigns();
        return $campaigns[$slug] ?? null;
    }
}

function tcl_storage_normalize_campaign_row(array $row): array
{
    $metadata = [];
    if (!empty($row['metadata_json'])) {
        $decoded = json_decode((string)$row['metadata_json'], true);
        if (is_array($decoded)) $metadata = $decoded;
    }

    $tags = $metadata['tags'] ?? [];
    if (!is_array($tags)) $tags = [];

    return [
        'db_id' => (int)($row['id'] ?? 0),
        'public_id' => (string)($row['public_id'] ?? ''),
        'id' => (string)($row['slug'] ?? ''),
        'slug' => (string)($row['slug'] ?? ''),
        'title' => (string)($row['title'] ?? ''),
        'eyebrow' => (string)($row['subtitle'] ?: ucwords(str_replace('_', ' ', (string)($row['campaign_type'] ?? 'Campaign')))),
        'type' => (string)($row['campaign_type'] ?? 'general'),
        'status' => (string)($row['status'] ?? 'draft'),
        'difficulty' => (string)($row['difficulty'] ?? 'Standard'),
        'visibility' => (string)($row['visibility'] ?? 'public'),
        'description' => (string)($row['description'] ?? ''),
        'short_description' => (string)($row['description'] ?? ''),
        'image_hint' => strtoupper(substr((string)($row['campaign_type'] ?? 'TC'), 0, 2)),
        'reward_preview' => (string)($metadata['reward_preview'] ?? 'Reward configured'),
        'next_reward' => (string)($metadata['next_reward'] ?? ($metadata['reward_preview'] ?? 'Next reward')), 
        'sequence_count' => 0,
        'task_count' => 0,
        'participant_count' => 0,
        'duration' => (string)($metadata['duration'] ?? 'Campaign'),
        'streak' => (int)($metadata['streak'] ?? 0),
        'progress' => (int)($metadata['progress'] ?? 0),
        'points' => (int)($metadata['points'] ?? 0),
        'next_points' => (int)($metadata['next_points'] ?? 0),
        'tags' => array_values(array_map('strval', $tags)),
        'sequence' => ['title' => 'Sequence', 'description' => '', 'steps' => []],
        'reward_ladder' => [],
    ];
}

function tcl_storage_sequences(int $campaignId): array
{
    $pdo = tcl_storage_pdo();
    if (!$pdo) return [];

    try {
        $stmt = $pdo->prepare('SELECT * FROM training_sequences WHERE campaign_id = :campaign_id ORDER BY sort_order ASC, id ASC');
        $stmt->execute(['campaign_id' => $campaignId]);
        $sequences = [];
        foreach ($stmt->fetchAll() as $row) {
            $sequences[] = [
                'db_id' => (int)$row['id'],
                'public_id' => (string)$row['public_id'],
                'slug' => (string)$row['slug'],
                'title' => (string)$row['title'],
                'description' => (string)($row['description'] ?? ''),
                'status' => (string)$row['status'],
                'is_required' => (bool)$row['is_required'],
                'steps' => tcl_storage_tasks((int)$row['id']),
            ];
        }
        return $sequences;
    } catch (Throwable $e) {
        return [];
    }
}

function tcl_storage_first_sequence_bundle(int $campaignId): array
{
    $sequences = tcl_storage_sequences($campaignId);
    return $sequences[0] ?? ['title' => 'Sequence', 'description' => '', 'steps' => []];
}

function tcl_storage_tasks(int $sequenceId): array
{
    $pdo = tcl_storage_pdo();
    if (!$pdo) return [];

    try {
        $stmt = $pdo->prepare('SELECT * FROM training_tasks WHERE sequence_id = :sequence_id ORDER BY sort_order ASC, id ASC');
        $stmt->execute(['sequence_id' => $sequenceId]);
        $tasks = [];
        foreach ($stmt->fetchAll() as $index => $row) {
            $tasks[] = [
                'db_id' => (int)$row['id'],
                'public_id' => (string)$row['public_id'],
                'slug' => (string)$row['slug'],
                'title' => (string)$row['title'],
                'status' => $index === 0 ? 'current' : 'pending',
                'proof' => (string)$row['proof_type'],
                'points' => (int)$row['points'],
                'description' => (string)($row['description'] ?? ''),
                'instructions' => (string)($row['instructions'] ?? ''),
                'accepted_extensions' => (string)($row['accepted_extensions'] ?? ''),
                'max_file_size_mb' => $row['max_file_size_mb'] === null ? null : (int)$row['max_file_size_mb'],
                'is_required' => (bool)$row['is_required'],
            ];
        }
        return $tasks;
    } catch (Throwable $e) {
        return [];
    }
}

function tcl_storage_reward_ladder(int $campaignId): array
{
    $pdo = tcl_storage_pdo();
    if (!$pdo) return [];

    try {
        $stmt = $pdo->prepare('SELECT * FROM training_reward_rules WHERE campaign_id = :campaign_id ORDER BY sort_order ASC, id ASC');
        $stmt->execute(['campaign_id' => $campaignId]);
        $items = [];
        foreach ($stmt->fetchAll() as $index => $row) {
            $items[] = [
                'db_id' => (int)$row['id'],
                'public_id' => (string)$row['public_id'],
                'label' => (string)$row['title'],
                'requirement' => tcl_storage_reward_requirement_label($row),
                'reward' => (string)$row['reward_label'],
                'status' => $index === 0 ? 'current' : 'locked',
                'trigger_type' => (string)$row['trigger_type'],
                'reward_type' => (string)$row['reward_type'],
                'reward_value_cents' => (int)$row['reward_value_cents'],
            ];
        }
        return $items;
    } catch (Throwable $e) {
        return [];
    }
}

function tcl_storage_reward_requirement_label(array $row): string
{
    $trigger = (string)($row['trigger_type'] ?? 'sequence_completion');
    if ($trigger === 'streak_completion') return 'Complete ' . number_format((int)($row['required_streak'] ?? 0)) . ' day streak';
    if ($trigger === 'milestone_completion') return 'Reach milestone ' . number_format((int)($row['milestone_target'] ?? 0));
    if ($trigger === 'task_completion') return 'Complete ' . number_format((int)($row['required_completions'] ?? 1)) . ' task';
    return 'Complete ' . number_format((int)($row['required_completions'] ?? 1)) . ' sequence';
}

function tcl_storage_summary(array $campaigns): array
{
    $active = 0;
    $participants = 0;
    $tasks = 0;
    $progressTotal = 0;
    foreach ($campaigns as $campaign) {
        if (($campaign['status'] ?? '') === 'active') $active++;
        $participants += (int)($campaign['participant_count'] ?? 0);
        $tasks += (int)($campaign['task_count'] ?? 0);
        $progressTotal += (int)($campaign['progress'] ?? 0);
    }
    return [
        'active_campaigns' => $active,
        'total_participants' => $participants,
        'total_tasks' => $tasks,
        'average_progress' => $campaigns ? (int)round($progressTotal / max(1, count($campaigns))) : 0,
        'source' => tcl_storage_using_sql() ? 'sql' : 'seed',
    ];
}
