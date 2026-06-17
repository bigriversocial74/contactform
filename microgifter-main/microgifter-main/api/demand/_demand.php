<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/finance/_money.php';
require_once dirname(__DIR__) . '/communications/_communications.php';

const MG_PSR_SIGNAL_TYPES = [
    'future_visit',
    'purchase_intent',
    'committed_demand',
    'gift_interest',
    'repeat_visit',
    'reservation_interest',
];

const MG_PSR_ASSET_TYPES = [
    'merchant',
    'location',
    'product',
    'category',
    'service',
    'event',
    'other',
];

function mg_demand_event(
    PDO $pdo,
    int $signalId,
    string $eventType,
    ?string $from,
    ?string $to,
    ?int $actorUserId,
    array $payload = []
): void {
    $pdo->prepare(
        'INSERT INTO purchase_signal_events
         (public_id,purchase_signal_id,event_type,from_status,to_status,actor_user_id,payload_json,created_at)
         VALUES (?,?,?,?,?,?,?,NOW())'
    )->execute([
        mg_public_uuid(),
        $signalId,
        $eventType,
        $from,
        $to,
        $actorUserId,
        json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ]);
}

function mg_demand_resolve_scope(PDO $pdo, array $input): array
{
    $merchantRef = trim((string) ($input['merchant_id'] ?? ''));
    $locationRef = trim((string) ($input['location_id'] ?? ''));
    $productRef = trim((string) ($input['product_id'] ?? ''));

    $merchantUserId = 0;
    $locationId = null;
    $productId = null;

    if ($productRef !== '') {
        $stmt = $pdo->prepare(
            "SELECT id,merchant_user_id
             FROM catalog_products
             WHERE public_id=? AND status='published'
             LIMIT 1"
        );
        $stmt->execute([$productRef]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            throw new RuntimeException('Demand product is not available.');
        }
        $productId = (int) $product['id'];
        $merchantUserId = (int) $product['merchant_user_id'];
    }

    if ($locationRef !== '') {
        $stmt = $pdo->prepare(
            "SELECT ml.id,mw.merchant_user_id
             FROM merchant_locations ml
             INNER JOIN merchant_workspaces mw ON mw.id=ml.workspace_id
             WHERE ml.public_id=? AND ml.status='active' AND mw.status='active'
             LIMIT 1"
        );
        $stmt->execute([$locationRef]);
        $location = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$location) {
            throw new RuntimeException('Demand location is not available.');
        }
        $locationId = (int) $location['id'];
        if ($merchantUserId > 0 && $merchantUserId !== (int) $location['merchant_user_id']) {
            throw new RuntimeException('Product and location belong to different merchants.');
        }
        $merchantUserId = (int) $location['merchant_user_id'];
    }

    if ($merchantRef !== '') {
        $stmt = $pdo->prepare(
            "SELECT merchant_user_id
             FROM merchant_workspaces
             WHERE public_id=? AND status='active'
             LIMIT 1"
        );
        $stmt->execute([$merchantRef]);
        $resolved = (int) ($stmt->fetchColumn() ?: 0);
        if ($resolved < 1) {
            throw new RuntimeException('Demand merchant is not available.');
        }
        if ($merchantUserId > 0 && $merchantUserId !== $resolved) {
            throw new RuntimeException('Demand scope belongs to different merchants.');
        }
        $merchantUserId = $resolved;
    }

    if ($merchantUserId < 1) {
        throw new InvalidArgumentException('Merchant, location, or product is required.');
    }

    return [
        'merchant_user_id' => $merchantUserId,
        'location_id' => $locationId,
        'product_id' => $productId,
    ];
}

function mg_demand_create_psr(PDO $pdo, int $userId, array $input): array
{
    $signalType = trim((string) ($input['signal_type'] ?? ''));
    $assetType = trim((string) ($input['asset_type'] ?? 'merchant'));
    $idempotencyKey = trim((string) ($input['idempotency_key'] ?? ''));
    $quantity = (float) ($input['quantity'] ?? 1);
    $value = max(0, (int) ($input['estimated_value_cents'] ?? 0));
    $currency = mg_money_currency((string) ($input['currency'] ?? 'USD'));
    $confidence = (float) ($input['confidence_score'] ?? 0.5);
    $expectedFrom = trim((string) ($input['expected_from'] ?? ''));
    $expectedTo = trim((string) ($input['expected_to'] ?? ''));

    if (!in_array($signalType, MG_PSR_SIGNAL_TYPES, true)
        || !in_array($assetType, MG_PSR_ASSET_TYPES, true)) {
        throw new InvalidArgumentException('Invalid purchase signal type.');
    }
    if ($idempotencyKey === '' || $quantity <= 0 || $confidence < 0 || $confidence > 1 || $expectedFrom === '') {
        throw new InvalidArgumentException('Invalid purchase signal request.');
    }

    $from = new DateTimeImmutable($expectedFrom, new DateTimeZone('UTC'));
    $to = $expectedTo !== '' ? new DateTimeImmutable($expectedTo, new DateTimeZone('UTC')) : null;
    if ($to !== null && $to < $from) {
        throw new InvalidArgumentException('Expected demand window is invalid.');
    }

    $scope = mg_demand_resolve_scope($pdo, $input);

    $existing = $pdo->prepare(
        'SELECT * FROM purchase_signal_records
         WHERE user_id=? AND idempotency_key=?
         LIMIT 1 FOR UPDATE'
    );
    $existing->execute([$userId, $idempotencyKey]);
    if ($row = $existing->fetch(PDO::FETCH_ASSOC)) {
        $same = (string) $row['signal_type'] === $signalType
            && (string) $row['asset_type'] === $assetType
            && (int) $row['merchant_user_id'] === $scope['merchant_user_id']
            && ($row['location_id'] === null ? null : (int) $row['location_id']) === $scope['location_id']
            && ($row['product_id'] === null ? null : (int) $row['product_id']) === $scope['product_id']
            && (float) $row['quantity'] === $quantity
            && (int) $row['estimated_value_cents'] === $value
            && (string) $row['currency'] === $currency
            && (float) $row['confidence_score'] === $confidence
            && (string) $row['expected_from'] === $from->format('Y-m-d H:i:s')
            && (string) ($row['expected_to'] ?? '') === ($to?->format('Y-m-d H:i:s') ?? '');
        if (!$same) {
            throw new RuntimeException('Idempotency key is already bound to another purchase signal.');
        }
        return $row + ['duplicate' => true];
    }

    $publicId = mg_public_uuid();
    $expiresAt = trim((string) ($input['expires_at'] ?? ''));
    $pdo->prepare(
        "INSERT INTO purchase_signal_records
         (public_id,user_id,merchant_user_id,location_id,product_id,asset_type,asset_reference,signal_type,status,
          quantity,estimated_value_cents,currency,confidence_score,expected_from,expected_to,source_type,
          source_reference,idempotency_key,expires_at,metadata_json,created_at,updated_at)
         VALUES (?,?,?,?,?,?,?,?, 'outstanding',?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())"
    )->execute([
        $publicId,
        $userId,
        $scope['merchant_user_id'],
        $scope['location_id'],
        $scope['product_id'],
        $assetType,
        trim((string) ($input['asset_reference'] ?? '')) ?: null,
        $signalType,
        $quantity,
        $value,
        $currency,
        $confidence,
        $from->format('Y-m-d H:i:s'),
        $to?->format('Y-m-d H:i:s'),
        trim((string) ($input['source_type'] ?? 'manual')),
        trim((string) ($input['source_reference'] ?? '')) ?: null,
        $idempotencyKey,
        $expiresAt !== ''
            ? (new DateTimeImmutable($expiresAt, new DateTimeZone('UTC')))->format('Y-m-d H:i:s')
            : null,
        json_encode($input['metadata'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ]);

    $id = (int) $pdo->lastInsertId();
    mg_demand_event($pdo, $id, 'created', null, 'outstanding', $userId, ['signal_type' => $signalType]);

    $stmt = $pdo->prepare('SELECT * FROM purchase_signal_records WHERE id=?');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) + ['duplicate' => false];
}

function mg_demand_transition_psr(PDO $pdo, array $signal, string $action, int $actorUserId, array $input = []): array
{
    $from = (string) $signal['status'];
    $allowed = [
        'cancel' => ['outstanding'],
        'expire' => ['outstanding'],
        'redeem' => ['outstanding'],
        'reopen' => ['canceled', 'expired'],
    ];
    if (!isset($allowed[$action]) || !in_array($from, $allowed[$action], true)) {
        throw new RuntimeException('Purchase signal cannot perform this transition.');
    }

    $to = match ($action) {
        'cancel' => 'canceled',
        'expire' => 'expired',
        'redeem' => 'redeemed',
        'reopen' => 'outstanding',
    };

    $microgiftId = null;
    $redemptionId = null;
    if ($action === 'redeem') {
        $microgiftRef = trim((string) ($input['microgift_id'] ?? ''));
        $redemptionRef = trim((string) ($input['redemption_id'] ?? ''));

        if ($redemptionRef !== '') {
            $stmt = $pdo->prepare(
                "SELECT r.id,r.instance_id,r.merchant_user_id
                 FROM microgift_redemptions r
                 WHERE r.public_id=? AND r.status='completed'
                 LIMIT 1"
            );
            $stmt->execute([$redemptionRef]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || (int) $row['merchant_user_id'] !== (int) $signal['merchant_user_id']) {
                throw new RuntimeException('Redemption does not match purchase signal merchant.');
            }
            $redemptionId = (int) $row['id'];
            $microgiftId = (int) $row['instance_id'];
        } elseif ($microgiftRef !== '') {
            $stmt = $pdo->prepare(
                "SELECT i.id,r.id redemption_id,r.merchant_user_id
                 FROM microgift_instances i
                 INNER JOIN microgift_redemptions r
                    ON r.instance_id=i.id AND r.status='completed'
                 WHERE i.public_id=? AND i.status='redeemed'
                 LIMIT 1"
            );
            $stmt->execute([$microgiftRef]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || (int) $row['merchant_user_id'] !== (int) $signal['merchant_user_id']) {
                throw new RuntimeException('Microgift does not match purchase signal merchant.');
            }
            $microgiftId = (int) $row['id'];
            $redemptionId = (int) $row['redemption_id'];
        }
    }

    $pdo->prepare(
        "UPDATE purchase_signal_records
         SET status=?,
             redeemed_microgift_instance_id=?,
             redeemed_redemption_id=?,
             redeemed_at=IF(?='redeemed',NOW(),redeemed_at),
             canceled_at=IF(?='canceled',NOW(),IF(?='outstanding',NULL,canceled_at)),
             updated_at=NOW()
         WHERE id=?"
    )->execute([$to, $microgiftId, $redemptionId, $to, $to, $to, (int) $signal['id']]);

    mg_demand_event($pdo, (int) $signal['id'], $action, $from, $to, $actorUserId, [
        'microgift_id' => $input['microgift_id'] ?? null,
        'redemption_id' => $input['redemption_id'] ?? null,
    ]);

    return $signal + [
        'status' => $to,
        'redeemed_microgift_instance_id' => $microgiftId,
        'redeemed_redemption_id' => $redemptionId,
    ];
}

function mg_demand_scope_key(?int $locationId, ?int $productId): string
{
    return 'location:' . ($locationId ?? 0) . '|product:' . ($productId ?? 0);
}

function mg_demand_build_snapshot(
    PDO $pdo,
    int $merchantUserId,
    ?int $locationId,
    ?int $productId,
    int $horizonDays,
    DateTimeImmutable $asOf
): array {
    $horizonDays = max(1, min($horizonDays, 365));
    $from = $asOf->setTime(0, 0);
    $to = $from->modify('+' . $horizonDays . ' day');

    $where = ['merchant_user_id=?', 'expected_from<?'];
    $params = [$merchantUserId, $to->format('Y-m-d H:i:s')];
    if ($locationId !== null) {
        $where[] = 'location_id=?';
        $params[] = $locationId;
    }
    if ($productId !== null) {
        $where[] = 'product_id=?';
        $params[] = $productId;
    }

    $sql = "SELECT
                COUNT(*) total_count,
                COUNT(DISTINCT user_id) unique_users,
                SUM(CASE WHEN status='outstanding' THEN 1 ELSE 0 END) outstanding_count,
                SUM(CASE WHEN status='outstanding' THEN quantity ELSE 0 END) outstanding_quantity,
                SUM(CASE WHEN status='outstanding' THEN estimated_value_cents ELSE 0 END) outstanding_value,
                SUM(CASE WHEN status='outstanding' AND signal_type='committed_demand' THEN 1 ELSE 0 END) committed_count,
                SUM(CASE WHEN status='outstanding' AND signal_type='committed_demand' THEN estimated_value_cents ELSE 0 END) committed_value,
                SUM(CASE WHEN status='outstanding' AND signal_type IN ('future_visit','repeat_visit') THEN 1 ELSE 0 END) future_visits,
                SUM(CASE WHEN status='redeemed' THEN 1 ELSE 0 END) redeemed_count,
                SUM(CASE WHEN status='redeemed' THEN estimated_value_cents ELSE 0 END) redeemed_value,
                SUM(CASE WHEN status='outstanding' THEN estimated_value_cents*confidence_score ELSE 0 END) weighted_score
            FROM purchase_signal_records
            WHERE " . implode(' AND ', $where);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $velocity = static function (int $days) use ($pdo, $merchantUserId, $locationId, $productId, $asOf): float {
        $where = ['merchant_user_id=?', 'created_at>=?', 'created_at<?'];
        $params = [
            $merchantUserId,
            $asOf->modify('-' . $days . ' day')->format('Y-m-d H:i:s'),
            $asOf->format('Y-m-d H:i:s'),
        ];
        if ($locationId !== null) {
            $where[] = 'location_id=?';
            $params[] = $locationId;
        }
        if ($productId !== null) {
            $where[] = 'product_id=?';
            $params[] = $productId;
        }
        $stmt = $pdo->prepare(
            'SELECT COALESCE(SUM(estimated_value_cents*confidence_score),0)/?
             FROM purchase_signal_records
             WHERE ' . implode(' AND ', $where)
        );
        $stmt->execute(array_merge([$days], $params));
        return (float) $stmt->fetchColumn();
    };

    $outstanding = (int) ($row['outstanding_count'] ?? 0);
    $redeemed = (int) ($row['redeemed_count'] ?? 0);
    $conversion = ($outstanding + $redeemed) > 0
        ? $redeemed / ($outstanding + $redeemed)
        : null;

    $features = [
        'window_start' => $from->format('Y-m-d'),
        'window_end' => $to->format('Y-m-d'),
        'total_signals' => (int) ($row['total_count'] ?? 0),
        'confidence_weighted_value_cents' => (float) ($row['weighted_score'] ?? 0),
    ];
    $scopeKey = mg_demand_scope_key($locationId, $productId);

    $pdo->prepare(
        "INSERT INTO demand_scope_snapshots
         (public_id,snapshot_date,horizon_days,merchant_user_id,location_id,product_id,scope_key,
          outstanding_signal_count,outstanding_quantity,outstanding_value_cents,committed_signal_count,
          committed_value_cents,future_visit_count,redeemed_signal_count,redeemed_value_cents,unique_users,
          weighted_demand_score,velocity_7d,velocity_30d,conversion_rate,feature_version,features_json,created_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'psr_v1',?,NOW())
         ON DUPLICATE KEY UPDATE
          outstanding_signal_count=VALUES(outstanding_signal_count),
          outstanding_quantity=VALUES(outstanding_quantity),
          outstanding_value_cents=VALUES(outstanding_value_cents),
          committed_signal_count=VALUES(committed_signal_count),
          committed_value_cents=VALUES(committed_value_cents),
          future_visit_count=VALUES(future_visit_count),
          redeemed_signal_count=VALUES(redeemed_signal_count),
          redeemed_value_cents=VALUES(redeemed_value_cents),
          unique_users=VALUES(unique_users),
          weighted_demand_score=VALUES(weighted_demand_score),
          velocity_7d=VALUES(velocity_7d),
          velocity_30d=VALUES(velocity_30d),
          conversion_rate=VALUES(conversion_rate),
          features_json=VALUES(features_json)"
    )->execute([
        mg_public_uuid(),
        $asOf->format('Y-m-d'),
        $horizonDays,
        $merchantUserId,
        $locationId,
        $productId,
        $scopeKey,
        $outstanding,
        (float) ($row['outstanding_quantity'] ?? 0),
        (int) ($row['outstanding_value'] ?? 0),
        (int) ($row['committed_count'] ?? 0),
        (int) ($row['committed_value'] ?? 0),
        (int) ($row['future_visits'] ?? 0),
        $redeemed,
        (int) ($row['redeemed_value'] ?? 0),
        (int) ($row['unique_users'] ?? 0),
        (float) ($row['weighted_score'] ?? 0),
        $velocity(7),
        $velocity(30),
        $conversion,
        json_encode($features, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
    ]);

    $stmt = $pdo->prepare(
        "SELECT * FROM demand_scope_snapshots
         WHERE snapshot_date=? AND horizon_days=? AND merchant_user_id=? AND scope_key=? AND feature_version='psr_v1'
         LIMIT 1"
    );
    $stmt->execute([$asOf->format('Y-m-d'), $horizonDays, $merchantUserId, $scopeKey]);
    $snapshot = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$snapshot) {
        throw new RuntimeException('Demand snapshot was not persisted.');
    }
    return $snapshot;
}

function mg_demand_emit_agent_signals(PDO $pdo, array $snapshot): array
{
    $signals = [];
    $velocity7 = (float) ($snapshot['velocity_7d'] ?? 0);
    $velocity30 = (float) ($snapshot['velocity_30d'] ?? 0);
    $committed = (int) $snapshot['committed_value_cents'];
    $future = (int) $snapshot['future_visit_count'];
    $definitions = [];

    if ($velocity30 > 0 && $velocity7 > $velocity30 * 1.35) {
        $definitions[] = [
            'velocity_spike',
            'opportunity',
            $velocity7,
            $velocity30,
            'Demand velocity is accelerating.',
            ['action' => 'increase_capacity', 'horizon_days' => (int) $snapshot['horizon_days']],
        ];
    }
    if ($committed >= 100000) {
        $definitions[] = [
            'committed_demand',
            'opportunity',
            $committed,
            null,
            'Committed demand has crossed the configured opportunity threshold.',
            ['action' => 'prepare_inventory', 'committed_value_cents' => $committed],
        ];
    }
    if ($future >= 25) {
        $definitions[] = [
            'future_visit_cluster',
            'info',
            $future,
            null,
            'A significant cluster of future visits is forming.',
            ['action' => 'schedule_staffing', 'future_visit_count' => $future],
        ];
    }

    foreach ($definitions as [$key, $level, $observed, $baseline, $summary, $recommendation]) {
        $dedupe = $key . ':' . $snapshot['snapshot_date'] . ':' . $snapshot['horizon_days']
            . ':' . ($snapshot['location_id'] ?? 0) . ':' . ($snapshot['product_id'] ?? 0);
        $confidence = min(
            1,
            max(0, (float) $snapshot['weighted_demand_score'] / max(1, (float) $snapshot['outstanding_value_cents']))
        );
        $pdo->prepare(
            "INSERT INTO demand_agent_signals
             (public_id,merchant_user_id,location_id,product_id,signal_key,signal_level,status,observed_value,
              baseline_value,confidence_score,summary,recommendation_json,source_snapshot_id,dedupe_key,
              triggered_at,expires_at,created_at,updated_at)
             VALUES (?,?,?,?,?,?, 'open',?,?,?,?,?,?,?,NOW(),DATE_ADD(NOW(),INTERVAL 30 DAY),NOW(),NOW())
             ON DUPLICATE KEY UPDATE
              signal_level=VALUES(signal_level),
              observed_value=VALUES(observed_value),
              baseline_value=VALUES(baseline_value),
              confidence_score=VALUES(confidence_score),
              summary=VALUES(summary),
              recommendation_json=VALUES(recommendation_json),
              source_snapshot_id=VALUES(source_snapshot_id),
              status=IF(status='resolved',status,'open'),
              updated_at=NOW()"
        )->execute([
            mg_public_uuid(),
            (int) $snapshot['merchant_user_id'],
            $snapshot['location_id'],
            $snapshot['product_id'],
            $key,
            $level,
            $observed,
            $baseline,
            $confidence,
            $summary,
            json_encode($recommendation, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            (int) $snapshot['id'],
            $dedupe,
        ]);
        $signals[] = $key;
    }

    return $signals;
}
