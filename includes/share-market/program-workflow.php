<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-actions.php';

if (!function_exists('mg_share_market_enrollment_states')) {
    function mg_share_market_enrollment_states(): array
    {
        return ['not_enrolled','interested','under_review','approved','active','paused','suspended','rejected','closed'];
    }
}

if (!function_exists('mg_share_market_series_states')) {
    function mg_share_market_series_states(): array
    {
        return ['draft','submitted','approved','live','paused','closed','rejected','changes_requested','archived'];
    }
}

if (!function_exists('mg_share_market_redemption_types')) {
    function mg_share_market_redemption_types(): array
    {
        return ['microgift_card','event_ticket','merch','vip_access','digital_download','custom_reward'];
    }
}

if (!function_exists('mg_share_market_series_public_id')) {
    function mg_share_market_series_public_id(string $prefix = 'sm'): string
    {
        return $prefix . '_' . str_replace('-', '', mg_share_market_admin_manifest_id());
    }
}

if (!function_exists('mg_share_market_program_canonical_hash')) {
    function mg_share_market_program_canonical_hash(array $payload): string
    {
        unset($payload['payload_hash']);
        return hash('sha256', json_encode(mg_share_market_admin_canonicalize($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}

if (!function_exists('mg_share_market_program_append_event')) {
    function mg_share_market_program_append_event(PDO $pdo, string $eventType, array $payload, int $userId): int
    {
        $payload['payload_hash'] = mg_share_market_program_canonical_hash($payload);
        $stmt = $pdo->prepare('INSERT INTO events (event_type,user_id,payload_json,created_at) VALUES (?,?,?,NOW())');
        $stmt->execute([$eventType, $userId, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
        return (int)$pdo->lastInsertId();
    }
}

if (!function_exists('mg_share_market_require_text')) {
    function mg_share_market_require_text(array $input, string $key, int $min = 1, int $max = 160): string
    {
        $value = trim((string)($input[$key] ?? ''));
        $len = mb_strlen($value);
        if ($len < $min || $len > $max) {
            throw new InvalidArgumentException(str_replace('_', ' ', $key) . " must be between {$min} and {$max} characters.");
        }
        return $value;
    }
}

if (!function_exists('mg_share_market_validate_enrollment_request')) {
    function mg_share_market_validate_enrollment_request(array $input, array $user): array
    {
        $userId = (int)($user['id'] ?? 0);
        if ($userId < 1) throw new DomainException('Authentication required.');
        $participantType = strtolower(trim((string)($input['participant_type'] ?? '')));
        if (!in_array($participantType, ['merchant','artist','band','creator','venue'], true)) {
            throw new InvalidArgumentException('Select a valid participant type.');
        }
        $legalName = mg_share_market_require_text($input, 'legal_name', 2, 140);
        $publicName = mg_share_market_require_text($input, 'public_name', 2, 120);
        $useCase = mg_share_market_require_text($input, 'use_case', 20, 1200);
        $utilityPlan = mg_share_market_require_text($input, 'utility_plan', 20, 1200);
        $acceptOptional = !empty($input['accept_optional']) && in_array((string)$input['accept_optional'], ['1','true','yes','on'], true);
        $acceptReview = !empty($input['accept_review']) && in_array((string)$input['accept_review'], ['1','true','yes','on'], true);
        if (!$acceptOptional || !$acceptReview) {
            throw new InvalidArgumentException('Confirm optional participation and admin review before submitting.');
        }
        return [
            'participant_id' => 'participant_user_' . $userId,
            'participant_user_id' => $userId,
            'participant_type' => $participantType,
            'legal_name' => $legalName,
            'public_name' => $publicName,
            'website' => trim((string)($input['website'] ?? '')),
            'use_case' => $useCase,
            'utility_plan' => $utilityPlan,
            'requested_state' => 'under_review',
            'previous_state' => 'not_enrolled',
            'terms' => [
                'optional_participation_confirmed' => true,
                'admin_review_confirmed' => true,
                'execution_enabled' => false,
            ],
        ];
    }
}

if (!function_exists('mg_share_market_validate_series_draft')) {
    function mg_share_market_validate_series_draft(array $input, array $user, bool $submit = false): array
    {
        $userId = (int)($user['id'] ?? 0);
        if ($userId < 1) throw new DomainException('Authentication required.');
        $seriesId = trim((string)($input['series_id'] ?? ''));
        if ($seriesId !== '' && preg_match('/^sm_[A-Za-z0-9]{20,64}$/', $seriesId) !== 1) {
            throw new InvalidArgumentException('Invalid series identifier.');
        }
        if ($seriesId === '') $seriesId = mg_share_market_series_public_id('sm');
        $name = mg_share_market_require_text($input, 'name', 3, 120);
        $description = mg_share_market_require_text($input, 'description', 20, 1800);
        $supply = filter_var($input['supply'] ?? null, FILTER_VALIDATE_INT);
        if ($supply === false || $supply < 100 || $supply > 1000000) throw new InvalidArgumentException('Supply must be between 100 and 1,000,000.');
        $launchPriceCents = (int) round(((float)($input['launch_price'] ?? 0)) * 100);
        if ($launchPriceCents < 100 || $launchPriceCents > 1000000) throw new InvalidArgumentException('Launch price must be between $1 and $10,000.');
        $maxPerBuyer = filter_var($input['max_per_buyer'] ?? null, FILTER_VALIDATE_INT);
        if ($maxPerBuyer === false || $maxPerBuyer < 1 || $maxPerBuyer > $supply) throw new InvalidArgumentException('Max per buyer must be at least 1 and cannot exceed supply.');
        $redemptionType = strtolower(trim((string)($input['redemption_type'] ?? '')));
        if (!in_array($redemptionType, mg_share_market_redemption_types(), true)) throw new InvalidArgumentException('Select a valid redemption type.');
        $redemptionTitle = mg_share_market_require_text($input, 'redemption_title', 3, 140);
        $redemptionDetails = mg_share_market_require_text($input, 'redemption_details', 20, 1200);
        $redemptionShareCost = filter_var($input['redemption_share_cost'] ?? null, FILTER_VALIDATE_INT);
        if ($redemptionShareCost === false || $redemptionShareCost < 1 || $redemptionShareCost > $supply) throw new InvalidArgumentException('Redemption share cost must be between 1 and supply.');
        $reissueMilestone = trim((string)($input['reissue_milestone'] ?? ''));
        if (mb_strlen($reissueMilestone) > 300) throw new InvalidArgumentException('Reissue milestone cannot exceed 300 characters.');
        return [
            'series_id' => $seriesId,
            'participant_user_id' => $userId,
            'participant_id' => 'participant_user_' . $userId,
            'name' => $name,
            'description' => $description,
            'supply' => (int)$supply,
            'launch_price_cents' => $launchPriceCents,
            'max_per_buyer' => (int)$maxPerBuyer,
            'redemption_enabled' => !empty($input['redemption_enabled']),
            'resale_enabled' => !empty($input['resale_enabled']),
            'reissue_milestone' => $reissueMilestone,
            'redemption' => [
                'type' => $redemptionType,
                'title' => $redemptionTitle,
                'details' => $redemptionDetails,
                'share_cost' => (int)$redemptionShareCost,
            ],
            'state' => $submit ? 'submitted' : 'draft',
            'execution_enabled' => false,
        ];
    }
}

if (!function_exists('mg_share_market_program_fetch_events')) {
    function mg_share_market_program_fetch_events(PDO $pdo, ?int $userId = null, int $limit = 1000): array
    {
        $limit = max(1, min(2000, $limit));
        if ($userId) {
            $stmt = $pdo->prepare("SELECT id,event_type,user_id,payload_json,created_at FROM events WHERE event_type LIKE 'share_market.program.%' AND user_id=? ORDER BY id DESC LIMIT {$limit}");
            $stmt->execute([$userId]);
        } else {
            $stmt = $pdo->query("SELECT id,event_type,user_id,payload_json,created_at FROM events WHERE event_type LIKE 'share_market.program.%' ORDER BY id DESC LIMIT {$limit}");
        }
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $events = [];
        foreach (array_reverse($rows ?: []) as $row) {
            $payload = json_decode((string)($row['payload_json'] ?? '{}'), true);
            if (!is_array($payload)) $payload = [];
            $events[] = ['id'=>(int)$row['id'], 'event_type'=>(string)$row['event_type'], 'user_id'=>(int)$row['user_id'], 'payload'=>$payload, 'created_at'=>(string)$row['created_at']];
        }
        return $events;
    }
}

if (!function_exists('mg_share_market_program_fold')) {
    function mg_share_market_program_fold(array $events): array
    {
        $enrollments = [];
        $series = [];
        foreach ($events as $event) {
            $type = (string)($event['event_type'] ?? '');
            $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];
            if ($type === 'share_market.program.enrollment_submitted') {
                $id = (string)($payload['participant_id'] ?? '');
                if ($id === '') continue;
                $enrollments[$id] = array_merge($payload, [
                    'status' => 'under_review',
                    'last_event_type' => $type,
                    'last_event_id' => (int)$event['id'],
                    'created_at' => (string)$event['created_at'],
                    'updated_at' => (string)$event['created_at'],
                ]);
            } elseif (str_starts_with($type, 'share_market.program.enrollment_')) {
                $id = (string)($payload['participant_id'] ?? '');
                if ($id === '') continue;
                $enrollments[$id] = $enrollments[$id] ?? ['participant_id'=>$id, 'participant_user_id'=>(int)($payload['participant_user_id'] ?? 0)];
                $state = match ($type) {
                    'share_market.program.enrollment_approved' => 'approved',
                    'share_market.program.enrollment_activated' => 'active',
                    'share_market.program.enrollment_paused' => 'paused',
                    'share_market.program.enrollment_suspended' => 'suspended',
                    'share_market.program.enrollment_rejected' => 'rejected',
                    default => (string)($payload['state'] ?? $enrollments[$id]['status'] ?? 'under_review'),
                };
                $enrollments[$id]['status'] = $state;
                $enrollments[$id]['admin_note'] = (string)($payload['note'] ?? '');
                $enrollments[$id]['last_event_type'] = $type;
                $enrollments[$id]['last_event_id'] = (int)$event['id'];
                $enrollments[$id]['updated_at'] = (string)$event['created_at'];
            } elseif ($type === 'share_market.program.series_draft_saved' || $type === 'share_market.program.series_submitted') {
                $id = (string)($payload['series_id'] ?? '');
                if ($id === '') continue;
                $existing = $series[$id] ?? [];
                $series[$id] = array_merge($existing, $payload, [
                    'state' => $type === 'share_market.program.series_submitted' ? 'submitted' : ($payload['state'] ?? 'draft'),
                    'last_event_type' => $type,
                    'last_event_id' => (int)$event['id'],
                    'updated_at' => (string)$event['created_at'],
                    'created_at' => $existing['created_at'] ?? (string)$event['created_at'],
                ]);
            } elseif (str_starts_with($type, 'share_market.program.series_')) {
                $id = (string)($payload['series_id'] ?? '');
                if ($id === '') continue;
                $series[$id] = $series[$id] ?? ['series_id'=>$id, 'participant_user_id'=>(int)($payload['participant_user_id'] ?? 0)];
                $state = match ($type) {
                    'share_market.program.series_approved' => 'approved',
                    'share_market.program.series_rejected' => 'rejected',
                    'share_market.program.series_changes_requested' => 'changes_requested',
                    'share_market.program.series_paused' => 'paused',
                    default => (string)($payload['state'] ?? $series[$id]['state'] ?? 'draft'),
                };
                $series[$id]['state'] = $state;
                $series[$id]['admin_note'] = (string)($payload['note'] ?? '');
                $series[$id]['last_event_type'] = $type;
                $series[$id]['last_event_id'] = (int)$event['id'];
                $series[$id]['updated_at'] = (string)$event['created_at'];
            }
        }
        return [
            'enrollments' => array_values($enrollments),
            'series' => array_values($series),
        ];
    }
}

if (!function_exists('mg_share_market_user_snapshot')) {
    function mg_share_market_user_snapshot(PDO $pdo, int $userId): array
    {
        $folded = mg_share_market_program_fold(mg_share_market_program_fetch_events($pdo, $userId));
        $enrollment = $folded['enrollments'][0] ?? null;
        usort($folded['series'], static fn(array $a, array $b): int => strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? '')));
        return [
            'enrollment' => $enrollment,
            'series' => $folded['series'],
            'execution_enabled' => false,
        ];
    }
}

if (!function_exists('mg_share_market_admin_review_snapshot')) {
    function mg_share_market_admin_review_snapshot(PDO $pdo): array
    {
        $folded = mg_share_market_program_fold(mg_share_market_program_fetch_events($pdo, null, 2000));
        $submittedEnrollments = array_values(array_filter($folded['enrollments'], static fn(array $e): bool => in_array((string)($e['status'] ?? ''), ['under_review'], true)));
        $submittedSeries = array_values(array_filter($folded['series'], static fn(array $s): bool => in_array((string)($s['state'] ?? ''), ['submitted','changes_requested'], true)));
        return [
            'enrollments' => $submittedEnrollments,
            'series' => $submittedSeries,
            'summary' => [
                'enrollments' => count($submittedEnrollments),
                'series' => count($submittedSeries),
                'execution_enabled' => false,
            ],
        ];
    }
}
