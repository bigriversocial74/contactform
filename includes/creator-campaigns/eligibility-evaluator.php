<?php
declare(strict_types=1);

function mg_creator_campaign_rule_scalar_values(mixed $value): array
{
    if ($value === null) return [];
    if (is_bool($value)) return [$value ? 'true' : 'false'];
    if (is_scalar($value)) return [trim((string) $value)];
    if (!is_array($value)) return [];
    $result = [];
    array_walk_recursive($value, static function (mixed $item) use (&$result): void {
        if (is_bool($item)) $result[] = $item ? 'true' : 'false';
        elseif (is_scalar($item)) $result[] = trim((string) $item);
    });
    return array_values(array_filter($result, static fn(string $item): bool => $item !== ''));
}

function mg_creator_campaign_rule_profile_values(array $snapshot, string $ruleType): array
{
    $metadata = is_array($snapshot['metadata'] ?? null) ? $snapshot['metadata'] : [];
    $candidates = match ($ruleType) {
        'specialty' => [
            $metadata['specialty'] ?? null,
            $metadata['specialties'] ?? null,
            $metadata['creator_specialties'] ?? null,
            $metadata['categories'] ?? null,
        ],
        'category' => [
            $metadata['category'] ?? null,
            $metadata['categories'] ?? null,
            $metadata['content_categories'] ?? null,
        ],
        'platform' => [
            $metadata['platform'] ?? null,
            $metadata['platforms'] ?? null,
            $metadata['social_platforms'] ?? null,
            $metadata['channels'] ?? null,
        ],
        'verification' => [
            $metadata['verified'] ?? null,
            $metadata['verification'] ?? null,
            $metadata['verification_status'] ?? null,
            $snapshot['completion_score'] ?? null,
        ],
        'location' => [
            $snapshot['location_label'] ?? null,
            $metadata['location'] ?? null,
            $metadata['locations'] ?? null,
            $metadata['service_area'] ?? null,
        ],
        'audience' => [
            $metadata['audience'] ?? null,
            $metadata['audience_size'] ?? null,
            $metadata['followers'] ?? null,
            $metadata['follower_count'] ?? null,
            $metadata['reach'] ?? null,
        ],
        'existing_relationship' => [
            $metadata['existing_relationship'] ?? null,
            $metadata['existing_relationships'] ?? null,
            $metadata['merchant_relationships'] ?? null,
        ],
        default => [],
    };
    $values = [];
    foreach ($candidates as $candidate) {
        array_push($values, ...mg_creator_campaign_rule_scalar_values($candidate));
    }
    return array_values(array_unique($values));
}

function mg_creator_campaign_rule_expected_values(mixed $value): array
{
    if (is_array($value) && array_is_list($value)) {
        return mg_creator_campaign_rule_scalar_values($value);
    }
    return mg_creator_campaign_rule_scalar_values($value);
}

function mg_creator_campaign_rule_matches(array $actualValues, string $operator, mixed $expected): bool
{
    $actualStrings = array_values(array_filter(array_map(
        static fn(string $value): string => mb_strtolower(trim($value)),
        $actualValues
    ), static fn(string $value): bool => $value !== ''));
    $expectedValues = array_values(array_filter(array_map(
        static fn(string $value): string => mb_strtolower(trim($value)),
        mg_creator_campaign_rule_expected_values($expected)
    ), static fn(string $value): bool => $value !== ''));

    if ($operator === 'exists') return $actualStrings !== [];
    if ($actualStrings === []) return false;

    if ($operator === 'equals') {
        return $expectedValues !== [] && count(array_intersect($actualStrings, $expectedValues)) > 0;
    }
    if ($operator === 'not_equals') {
        return $expectedValues !== [] && count(array_intersect($actualStrings, $expectedValues)) === 0;
    }
    if ($operator === 'contains') {
        foreach ($actualStrings as $actual) {
            foreach ($expectedValues as $wanted) {
                if ($wanted !== '' && str_contains($actual, $wanted)) return true;
            }
        }
        return false;
    }
    if ($operator === 'in') {
        return $expectedValues !== [] && count(array_intersect($actualStrings, $expectedValues)) > 0;
    }

    $actualNumbers = array_values(array_filter(array_map(
        static fn(string $value): ?float => is_numeric($value) ? (float) $value : null,
        $actualStrings
    ), static fn(?float $value): bool => $value !== null));
    $expectedNumbers = array_values(array_filter(array_map(
        static fn(string $value): ?float => is_numeric($value) ? (float) $value : null,
        $expectedValues
    ), static fn(?float $value): bool => $value !== null));
    if ($actualNumbers === [] || $expectedNumbers === []) return false;
    $actual = max($actualNumbers);

    return match ($operator) {
        'gte' => $actual >= $expectedNumbers[0],
        'lte' => $actual <= $expectedNumbers[0],
        'between' => count($expectedNumbers) >= 2
            && $actual >= min($expectedNumbers[0], $expectedNumbers[1])
            && $actual <= max($expectedNumbers[0], $expectedNumbers[1]),
        default => false,
    };
}

function mg_creator_campaign_evaluate_automatic_acceptance(
    PDO $pdo,
    array $campaign,
    int $creatorUserId
): array {
    $snapshot = mg_creator_campaign_participation_creator_snapshot($pdo, $creatorUserId);
    $stmt = $pdo->prepare(
        'SELECT public_id,rule_type,operator_key,value_json,is_required,sort_order
         FROM creator_campaign_eligibility_rules WHERE campaign_id=? ORDER BY sort_order,id'
    );
    $stmt->execute([(int) $campaign['id']]);
    $rules = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $checks = [];
    $eligible = true;

    foreach ($rules as $rule) {
        $actual = mg_creator_campaign_rule_profile_values($snapshot, (string) $rule['rule_type']);
        $expected = mg_creator_campaign_participation_decode_json($rule['value_json'] ?? null);
        $passed = mg_creator_campaign_rule_matches($actual, (string) $rule['operator_key'], $expected);
        $required = !empty($rule['is_required']);
        if ($required && !$passed) $eligible = false;
        $checks[] = [
            'rule_id' => (string) $rule['public_id'],
            'rule_type' => (string) $rule['rule_type'],
            'operator' => (string) $rule['operator_key'],
            'required' => $required,
            'passed' => $passed,
        ];
    }

    $capacity = mg_creator_campaign_participant_capacity($pdo, $campaign);
    if (!$capacity['available']) $eligible = false;

    return [
        'eligible' => $eligible,
        'checks' => $checks,
        'required_rule_count' => count(array_filter($checks, static fn(array $check): bool => $check['required'])),
        'capacity' => $capacity,
        'snapshot' => $snapshot,
    ];
}
