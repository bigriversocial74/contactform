<?php
declare(strict_types=1);

/** Privacy-safe checkout capture and canonical purchase attribution. */

function mg_creator_campaign_affiliate_checkout_context(
    PDO $pdo,
    int $merchantUserId,
    array $items,
    ?string $sessionKey = null
): ?array {
    if ($merchantUserId < 1 || !mg_creator_campaign_affiliate_tables_ready($pdo)) return null;

    $sessionKey = trim((string) ($sessionKey ?? ($_COOKIE['mg_cc_session'] ?? '')));
    if ($sessionKey === '') return null;
    $sessionHash = mg_creator_campaign_tracking_hash($sessionKey, 'session');
    if ($sessionHash === null) return null;

    $itemTotals = mg_creator_campaign_affiliate_item_totals($items);
    if ($itemTotals === []) return null;

    $stmt = $pdo->prepare(
        "SELECT s.id source_db_id,s.public_id source_public_id,s.campaign_id,s.participant_id,s.creator_user_id,
                s.attribution_model,s.conversion_window_days,p.public_id participant_public_id,
                cc.public_id campaign_public_id,e.public_id touch_event_public_id,e.occurred_at
         FROM creator_campaign_tracking_events e
         INNER JOIN creator_campaign_tracking_sources s ON s.id=e.source_id
         INNER JOIN creator_campaign_participants p ON p.id=s.participant_id
         INNER JOIN creator_campaigns cc ON cc.id=s.campaign_id
         INNER JOIN merchant_workspaces mw ON mw.id=cc.workspace_id
         WHERE e.session_hash=? AND e.status='accepted'
           AND e.event_type IN ('click','landing_view','engagement')
           AND s.status='active' AND p.status='active'
           AND cc.status IN ('scheduled','active')
           AND (cc.starts_at IS NULL OR cc.starts_at<=UTC_TIMESTAMP())
           AND (cc.ends_at IS NULL OR cc.ends_at>=UTC_TIMESTAMP())
           AND mw.merchant_user_id=?
           AND e.occurred_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL s.conversion_window_days DAY)
         ORDER BY e.occurred_at DESC,e.id DESC
         LIMIT 100"
    );
    $stmt->execute([$sessionHash, $merchantUserId]);
    $touches = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if ($touches === []) return null;

    $productIds = array_map('intval', array_keys($itemTotals));
    $productPlaceholders = implode(',', array_fill(0, count($productIds), '?'));
    $relationStmt = $pdo->prepare(
        "SELECT product_id,selected_product_version_id,relationship_type
         FROM creator_campaign_products
         WHERE campaign_id=? AND product_id IN ({$productPlaceholders})"
    );

    $campaigns = [];
    foreach ($touches as $touch) {
        $campaignId = (int) $touch['campaign_id'];
        if (!array_key_exists($campaignId, $campaigns)) {
            $relationStmt->execute(array_merge([$campaignId], $productIds));
            $relations = $relationStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $allowed = [];
            $excluded = [];
            foreach ($relations as $relation) {
                $productId = (int) $relation['product_id'];
                $type = (string) $relation['relationship_type'];
                if ($type === 'excluded') {
                    $excluded[$productId] = true;
                    continue;
                }
                if (!in_array($type, ['primary','featured','commissionable'], true)) continue;
                $versionId = (int) ($relation['selected_product_version_id'] ?? 0);
                if ($versionId > 0 && empty($itemTotals[$productId]['version_ids'][$versionId])) continue;
                $allowed[$productId] = true;
            }
            $eligibleAmount = 0;
            $eligibleProducts = [];
            foreach ($allowed as $productId => $_) {
                if (!empty($excluded[$productId])) continue;
                $eligibleAmount += (int) $itemTotals[$productId]['amount_minor'];
                $eligibleProducts[] = (int) $productId;
            }
            if ($eligibleAmount < 1) {
                $campaigns[$campaignId] = null;
                continue;
            }
            $campaigns[$campaignId] = [
                'eligible_amount_minor' => $eligibleAmount,
                'product_ids' => $eligibleProducts,
                'touches' => [],
            ];
        }
        if (is_array($campaigns[$campaignId])) $campaigns[$campaignId]['touches'][] = $touch;
    }

    $eligibleCampaigns = array_filter($campaigns, 'is_array');
    if ($eligibleCampaigns === []) return null;

    uasort($eligibleCampaigns, static function (array $a, array $b): int {
        return strcmp((string) $b['touches'][0]['occurred_at'], (string) $a['touches'][0]['occurred_at']);
    });
    $selectedCampaign = reset($eligibleCampaigns);
    if (!is_array($selectedCampaign) || empty($selectedCampaign['touches'])) return null;

    $latestTouch = $selectedCampaign['touches'][0];
    $selectedTouch = (string) ($latestTouch['attribution_model'] ?? 'last_touch') === 'first_touch'
        ? $selectedCampaign['touches'][count($selectedCampaign['touches']) - 1]
        : $latestTouch;

    return [
        'version' => 2,
        'status' => 'captured',
        'source_id' => (string) $selectedTouch['source_public_id'],
        'campaign_id' => (string) $selectedTouch['campaign_public_id'],
        'participant_id' => (string) $selectedTouch['participant_public_id'],
        'touch_event_id' => (string) $selectedTouch['touch_event_public_id'],
        'attribution_model' => (string) $selectedTouch['attribution_model'],
        'session_hash' => $sessionHash,
        'eligible_amount_minor' => (int) $selectedCampaign['eligible_amount_minor'],
        'product_ids' => array_values($selectedCampaign['product_ids']),
        'captured_at' => gmdate('Y-m-d H:i:s'),
    ];
}

function mg_creator_campaign_affiliate_order_metadata(array $order): array
{
    return mg_creator_campaign_affiliate_decode_json($order['metadata_json'] ?? null);
}

function mg_creator_campaign_affiliate_update_order_context(PDO $pdo, int $orderId, array $context): void
{
    $stmt = $pdo->prepare('SELECT metadata_json FROM commerce_orders WHERE id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$orderId]);
    $metadata = mg_creator_campaign_affiliate_decode_json($stmt->fetchColumn());
    $metadata['creator_affiliate'] = $context;
    $pdo->prepare('UPDATE commerce_orders SET metadata_json=?,updated_at=NOW() WHERE id=?')
        ->execute([mg_creator_campaign_json_encode($metadata), $orderId]);
}

function mg_creator_campaign_affiliate_source(PDO $pdo, array $context, int $merchantUserId): array
{
    $stmt = $pdo->prepare(
        "SELECT cc.id campaign_id,cc.public_id campaign_public_id,cc.status campaign_status,
                cc.starts_at,cc.ends_at,mw.merchant_user_id
         FROM creator_campaigns cc
         INNER JOIN merchant_workspaces mw ON mw.id=cc.workspace_id
         WHERE cc.public_id=? AND mw.merchant_user_id=?
         LIMIT 1 FOR UPDATE"
    );
    $stmt->execute([trim((string) ($context['campaign_id'] ?? '')), $merchantUserId]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign) throw new RuntimeException('Creator affiliate campaign is unavailable.');
    if (!in_array((string) $campaign['campaign_status'], ['scheduled','active'], true)) {
        throw new DomainException('Creator affiliate campaign is no longer active.');
    }
    $now = time();
    if (!empty($campaign['starts_at']) && strtotime((string) $campaign['starts_at']) > $now) {
        throw new DomainException('Creator affiliate campaign has not started.');
    }
    if (!empty($campaign['ends_at']) && strtotime((string) $campaign['ends_at']) < $now) {
        throw new DomainException('Creator affiliate campaign has ended.');
    }
    return $campaign;
}

function mg_creator_campaign_affiliate_purchase_event(
    PDO $pdo,
    array $campaign,
    array $order,
    array $context
): array {
    $eventKey = 'purchase.order.' . strtolower(str_replace('-', '', (string) $order['public_id']));
    $stmt = $pdo->prepare(
        'SELECT public_id FROM creator_campaign_tracking_events WHERE campaign_id=? AND event_key=? LIMIT 1 FOR UPDATE'
    );
    $stmt->execute([(int) $campaign['campaign_id'], $eventKey]);
    $existingPublicId = (string) ($stmt->fetchColumn() ?: '');

    if ($existingPublicId !== '') {
        $event = mg_creator_campaign_tracking_event_by_public_id($pdo, $existingPublicId);
    } else {
        $sessionHash = trim((string) ($context['session_hash'] ?? ''));
        if (preg_match('/^[a-f0-9]{64}$/', $sessionHash) !== 1) {
            throw new DomainException('Creator affiliate checkout context has no valid privacy-safe session hash.');
        }
        $publicId = mg_creator_campaign_public_id('ccte');
        $metadata = [
            'order_id' => (string) $order['public_id'],
            'amount_minor' => (int) ($context['eligible_amount_minor'] ?? 0),
            'order_total_minor' => (int) ($order['total_cents'] ?? 0),
            'currency' => strtoupper((string) ($order['currency'] ?? 'USD')),
            'product_ids' => array_values(array_map('intval', $context['product_ids'] ?? [])),
            'merchant_user_id' => (int) ($order['merchant_user_id'] ?? 0),
            'payment_status' => 'paid',
            'captured_source_id' => (string) ($context['source_id'] ?? ''),
            'captured_touch_event_id' => (string) ($context['touch_event_id'] ?? ''),
        ];
        $occurredAt = !empty($order['paid_at']) ? (string) $order['paid_at'] : gmdate('Y-m-d H:i:s');
        $pdo->prepare(
            'INSERT INTO creator_campaign_tracking_events
             (public_id,campaign_id,source_id,participant_id,creator_user_id,event_type,event_key,session_hash,
              visitor_hash,request_hash,target_path,referrer_host,metadata_json,status,is_unique,risk_score,
              risk_flags_json,occurred_at,created_at)
             VALUES (?,?,NULL,NULL,NULL,\'purchase\',?,?,NULL,NULL,?,NULL,?,\'accepted\',1,0,NULL,?,NOW())'
        )->execute([
            $publicId,
            (int) $campaign['campaign_id'],
            $eventKey,
            $sessionHash,
            '/checkout-success.php?order=' . rawurlencode((string) $order['public_id']),
            mg_creator_campaign_json_encode($metadata),
            $occurredAt,
        ]);
        $event = mg_creator_campaign_tracking_event_by_public_id($pdo, $publicId);
    }

    $stmt = $pdo->prepare('SELECT public_id FROM creator_campaign_attributions WHERE conversion_event_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([(int) $event['id']]);
    $attributionPublicId = (string) ($stmt->fetchColumn() ?: '');
    $attribution = $attributionPublicId !== ''
        ? mg_creator_campaign_attribution_by_public_id($pdo, $attributionPublicId)
        : mg_creator_campaign_attribution_decide($pdo, $event, null, false);

    return ['event' => $event, 'attribution' => $attribution];
}
