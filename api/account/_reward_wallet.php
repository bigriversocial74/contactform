<?php
declare(strict_types=1);

function mg_rw_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function mg_rw_json(mixed $value): array
{
    if (!is_string($value) || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_rw_effective_status(array $row): string
{
    $status = strtolower((string)($row['status'] ?? 'issued'));
    if (in_array($status, ['redeemed','cancelled'], true)) return $status;
    $expiresAt = trim((string)($row['expires_at'] ?? ''));
    if ($expiresAt !== '' && strtotime($expiresAt) <= time()) return 'expired';
    return in_array($status, ['issued','viewed','claimed','expired'], true) ? $status : 'issued';
}

function mg_rw_value(array $row): string
{
    $currency = strtoupper((string)($row['currency_snapshot'] ?? $row['currency'] ?? 'USD'));
    $cents = (int)($row['value_cents_snapshot'] ?? $row['value_amount_cents'] ?? 0);
    $percent = $row['value_percent'] ?? null;
    if ($percent !== null && (float)$percent > 0) return rtrim(rtrim(number_format((float)$percent, 2), '0'), '.') . '%';
    return $currency . ' ' . number_format($cents / 100, 2);
}

function mg_rw_base_select(): string
{
    return "SELECT wi.*,rt.public_id reward_template_public_id,rt.title reward_template_title,rt.description reward_description,
        rt.redemption_instructions,rt.terms reward_terms,rt.value_type,rt.value_amount_cents,rt.value_percent,rt.currency,
        c.public_id campaign_public_id,c.public_slug,c.title campaign_title,c.campaign_type,
        COALESCE(pp.display_name,mw.display_name,mu.display_name,mu.full_name,'Microgifter Merchant') merchant_name,
        pp.slug merchant_slug,pp.avatar_url merchant_avatar
        FROM wallet_items wi
        INNER JOIN users mu ON mu.id=wi.merchant_user_id
        LEFT JOIN reward_templates rt ON rt.id=wi.reward_template_id
        LEFT JOIN campaigns c ON c.id=wi.campaign_id
        LEFT JOIN public_profiles pp ON pp.user_id=wi.merchant_user_id AND pp.status='active' AND pp.visibility IN ('public','unlisted')
        LEFT JOIN merchant_workspaces mw ON mw.merchant_user_id=wi.merchant_user_id";
}

function mg_rw_find(PDO $pdo, int $userId, string $publicId, bool $forUpdate = false): array
{
    if (strlen($publicId) !== 36 || preg_match('/^[a-f0-9-]{36}$/', $publicId) !== 1) mg_fail('Invalid reward.', 422);
    $sql = mg_rw_base_select() . ' WHERE wi.public_id=? AND wi.user_id=? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$publicId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) mg_fail('Reward not found.', 404);
    return $row;
}

function mg_rw_active_token(PDO $pdo, int $walletItemId, int $userId): ?array
{
    $stmt = $pdo->prepare("SELECT public_id,token_last4,status,expires_at,used_at,created_at FROM wallet_reward_claim_tokens WHERE wallet_item_id=? AND user_id=? AND status='active' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$walletItemId, $userId]);
    $token = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$token) return null;
    if (strtotime((string)$token['expires_at']) <= time()) {
        $token['status'] = 'expired';
        return $token;
    }
    return $token;
}

function mg_rw_timeline(PDO $pdo, array $row): array
{
    $items = [];
    $items[] = ['type'=>'issued','label'=>'Reward issued','created_at'=>$row['issued_at'] ?? $row['created_at'] ?? null];
    $eventStmt = $pdo->prepare('SELECT event_type,event_context_json,created_at FROM campaign_events WHERE wallet_item_id=? AND merchant_user_id=? ORDER BY created_at ASC,id ASC LIMIT 100');
    $eventStmt->execute([(int)$row['id'], (int)$row['merchant_user_id']]);
    foreach ($eventStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $event) {
        $type = (string)$event['event_type'];
        $items[] = ['type'=>$type,'label'=>ucwords(str_replace(['.','_'], ' ', $type)),'context'=>mg_rw_json($event['event_context_json'] ?? null),'created_at'=>$event['created_at'] ?? null];
    }
    $tokenStmt = $pdo->prepare('SELECT status,token_last4,expires_at,used_at,created_at FROM wallet_reward_claim_tokens WHERE wallet_item_id=? AND user_id=? ORDER BY created_at ASC,id ASC LIMIT 100');
    $tokenStmt->execute([(int)$row['id'], (int)$row['user_id']]);
    foreach ($tokenStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $token) {
        $items[] = ['type'=>'claim_code.' . (string)$token['status'],'label'=>'Claim code ' . (string)$token['status'],'detail'=>'Code ending ' . (string)$token['token_last4'],'expires_at'=>$token['expires_at'] ?? null,'created_at'=>$token['used_at'] ?? $token['created_at'] ?? null];
    }
    usort($items, static fn(array $a, array $b): int => strtotime((string)($a['created_at'] ?? '1970-01-01')) <=> strtotime((string)($b['created_at'] ?? '1970-01-01')));
    return $items;
}

function mg_rw_support_cases(PDO $pdo, int $walletItemId, int $userId): array
{
    $stmt = $pdo->prepare('SELECT public_id,category,status,subject,message,resolution_note,resolved_at,created_at,updated_at FROM wallet_reward_support_cases WHERE wallet_item_id=? AND user_id=? ORDER BY created_at DESC,id DESC LIMIT 25');
    $stmt->execute([$walletItemId, $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mg_rw_row(PDO $pdo, array $row, bool $detail = false): array
{
    $metadata = mg_rw_json($row['metadata_json'] ?? null);
    $status = mg_rw_effective_status($row);
    $token = null;
    $support = [];
    $timeline = [];
    if ($detail) {
        $token = mg_rw_active_token($pdo, (int)$row['id'], (int)$row['user_id']);
        $support = mg_rw_support_cases($pdo, (int)$row['id'], (int)$row['user_id']);
        $timeline = mg_rw_timeline($pdo, $row);
    }
    $campaignRef = (string)($row['public_slug'] ?: $row['campaign_public_id'] ?? '');
    return [
        'id'=>(string)$row['public_id'],
        'status'=>$status,
        'stored_status'=>(string)$row['status'],
        'title'=>(string)($row['reward_template_title'] ?: $row['title_snapshot'] ?: 'Microgifter Reward'),
        'description'=>(string)($row['reward_description'] ?? ''),
        'value'=>mg_rw_value($row),
        'value_cents'=>(int)($row['value_cents_snapshot'] ?? 0),
        'currency'=>(string)($row['currency_snapshot'] ?? 'USD'),
        'issued_at'=>$row['issued_at'] ?? $row['created_at'] ?? null,
        'expires_at'=>$row['expires_at'] ?? null,
        'redemption_instructions'=>(string)($row['redemption_instructions'] ?? ''),
        'terms'=>(string)($row['reward_terms'] ?? ''),
        'source_type'=>(string)($row['source_type'] ?? ''),
        'regift_allowed'=>!empty($metadata['regift_allowed']),
        'merchant'=>[
            'id'=>(int)$row['merchant_user_id'],
            'name'=>(string)$row['merchant_name'],
            'slug'=>$row['merchant_slug'] ?? null,
            'avatar_url'=>$row['merchant_avatar'] ?? null,
            'url'=>!empty($row['merchant_slug']) ? '/profile.php?slug=' . rawurlencode((string)$row['merchant_slug']) : null,
        ],
        'campaign'=>[
            'id'=>$row['campaign_public_id'] ?? null,
            'title'=>$row['campaign_title'] ?? null,
            'type'=>$row['campaign_type'] ?? null,
            'url'=>$campaignRef !== '' && (string)($row['campaign_type'] ?? '') === 'loyalty_quest' ? '/loyalty-quest.php?campaign=' . rawurlencode($campaignRef) : null,
        ],
        'claim_code'=>$token,
        'support_cases'=>$support,
        'timeline'=>$timeline,
        'metadata'=>$detail ? $metadata : [],
    ];
}

function mg_rw_event(PDO $pdo, array $row, string $eventType, array $context = []): void
{
    if (empty($row['campaign_id'])) return;
    $stmt = $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
    $stmt->execute([mg_rw_uuid(),(int)$row['merchant_user_id'],(int)$row['campaign_id'],(int)$row['id'],$row['contact_id'] ?? null,$eventType,json_encode($context,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
}

function mg_rw_plain_code(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 12; $i++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    return $code;
}
