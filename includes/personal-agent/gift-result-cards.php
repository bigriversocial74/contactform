<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/api/account/_action_center.php';
require_once dirname(__DIR__, 2) . '/api/account/_action_center_wallet.php';

function mg_personal_agent_account_gift_folder(string $message): string
{
    $message = mb_strtolower(str_replace(['’', '‘'], "'", $message));

    if (mg_personal_agent_message_mentions($message, [
        'claimed gifts', 'claimed gift', 'redeemed gifts', 'redeemed gift', 'used gifts', 'used gift',
        'my claimed', 'my redeemed', 'claim history', 'redemption history',
    ])) return 'claimed';

    if (mg_personal_agent_message_mentions($message, [
        'sent gifts', 'sent gift', 'gifts i sent', 'gift i sent', 'my sent', 'outbox', 'out box',
        'regifted gifts', 'regifted gift', 'transferred gifts', 'transferred gift',
    ])) return 'sent';

    if (mg_personal_agent_message_mentions($message, [
        'gift inbox', 'my inbox', 'inbox gifts', 'inbox gift', 'received gifts', 'received gift',
        'gifts i received', 'gift i received', 'owned gifts', 'owned gift', 'my microgifts',
        'my microgift', 'my gifts', 'show gifts', 'show my gifts',
    ])) return 'inbox';

    return '';
}

function mg_personal_agent_account_gift_email(PDO $pdo, int $userId): string
{
    try {
        $stmt = $pdo->prepare('SELECT email FROM users WHERE id=? LIMIT 1');
        $stmt->execute([$userId]);
        return strtolower(trim((string) ($stmt->fetchColumn() ?: '')));
    } catch (Throwable) {
        return '';
    }
}

function mg_personal_agent_account_gift_safe_url(mixed $value): string
{
    $value = trim((string) $value);
    if ($value === '' || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) return '';
    if (str_starts_with($value, '/') && !str_starts_with($value, '//')) return mb_substr($value, 0, 700);
    if (preg_match('#^https?://#i', $value) === 1) return mb_substr($value, 0, 700);
    return '';
}

function mg_personal_agent_account_gift_image(array $item): string
{
    foreach (['reward_image_url', 'image_url', 'thumbnail_url', 'cover_image_url', 'merchant_avatar_url'] as $key) {
        $url = mg_personal_agent_account_gift_safe_url($item[$key] ?? '');
        if ($url !== '') return $url;
    }

    $metadata = [];
    $raw = $item['metadata_json'] ?? null;
    if (is_array($raw)) {
        $metadata = $raw;
    } elseif (is_string($raw) && trim($raw) !== '') {
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($decoded)) $metadata = $decoded;
        } catch (Throwable) {
            $metadata = [];
        }
    }

    $reward = is_array($metadata['reward_template_metadata'] ?? null) ? $metadata['reward_template_metadata'] : [];
    $pack = is_array($reward['media_pack'] ?? null) ? $reward['media_pack'] : (is_array($metadata['media_pack'] ?? null) ? $metadata['media_pack'] : []);
    foreach (['reward_image_url', 'image_url', 'thumbnail_url', 'cover_image_url'] as $key) {
        $url = mg_personal_agent_account_gift_safe_url($metadata[$key] ?? $reward[$key] ?? $pack[$key] ?? '');
        if ($url !== '') return $url;
    }

    foreach ((array) ($metadata['posts'] ?? []) as $post) {
        if (!is_array($post)) continue;
        $url = mg_personal_agent_account_gift_safe_url($post['url'] ?? '');
        if ($url !== '' && in_array((string) ($post['type'] ?? $post['media_type'] ?? ''), ['cover', 'image'], true)) return $url;
    }

    return '';
}

function mg_personal_agent_account_gift_money(int $cents, string $currency): string
{
    $currency = strtoupper(trim($currency)) ?: 'USD';
    $amount = number_format(max(0, $cents) / 100, 2);
    return $currency === 'USD' ? '$' . $amount : $currency . ' ' . $amount;
}

function mg_personal_agent_account_gift_can_send(array $item, string $folder): bool
{
    if ($folder !== 'inbox') return false;
    $state = strtolower(trim((string) ($item['state'] ?? $item['instance_status'] ?? '')));
    if (in_array($state, ['expired', 'redeemed', 'cancelled'], true)) return false;
    if (!empty($item['redeemed_at'])) return false;
    return true;
}

function mg_personal_agent_account_gift_items(PDO $pdo, int $userId, string $folder, int $limit = 12): array
{
    $folder = mg_action_center_folder($folder);
    $limit = max(1, min(24, $limit));
    $items = [];

    try {
        $items = mg_action_center_items($pdo, $userId, $folder, $limit);
    } catch (Throwable $error) {
        if (function_exists('mg_security_log')) {
            mg_security_log('warning', 'user_agent.account_gift_items_partial', 'Personal Agent Action Center gifts were partially unavailable.', ['exception_type' => $error::class], $userId);
        }
    }

    try {
        $walletItems = mg_ac_wallet_items($pdo, $userId, mg_personal_agent_account_gift_email($pdo, $userId), $folder, $limit);
        $items = array_merge($items, $walletItems);
    } catch (Throwable $error) {
        if (function_exists('mg_security_log')) {
            mg_security_log('warning', 'user_agent.account_wallet_gifts_partial', 'Personal Agent wallet gifts were partially unavailable.', ['exception_type' => $error::class], $userId);
        }
    }

    $deduped = [];
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $id = trim((string) ($item['action_item_id'] ?? ''));
        if ($id === '' || isset($deduped[$id])) continue;
        $deduped[$id] = $item;
    }

    $items = array_values($deduped);
    usort($items, static function (array $left, array $right): int {
        $a = strtotime((string) ($left['updated_at'] ?? $left['sent_at'] ?? $left['first_received_at'] ?? '')) ?: 0;
        $b = strtotime((string) ($right['updated_at'] ?? $right['sent_at'] ?? $right['first_received_at'] ?? '')) ?: 0;
        return $b <=> $a;
    });

    return array_slice($items, 0, $limit);
}

function mg_personal_agent_account_gift_cards(PDO $pdo, int $userId, string $folder, int $limit = 12): array
{
    $cards = [];
    foreach (mg_personal_agent_account_gift_items($pdo, $userId, $folder, $limit) as $item) {
        $actionItemId = trim((string) ($item['action_item_id'] ?? ''));
        if ($actionItemId === '') continue;

        $title = trim((string) ($item['template_name'] ?? $item['title'] ?? 'Microgift')) ?: 'Microgift';
        $merchant = trim((string) ($item['business_name'] ?? $item['merchant_name'] ?? 'Microgifter')) ?: 'Microgifter';
        $sender = trim((string) ($item['sender_name'] ?? ''));
        $recipient = trim((string) ($item['recipient_name'] ?? ''));
        $state = trim((string) ($item['state'] ?? $folder)) ?: $folder;
        $value = mg_personal_agent_account_gift_money((int) ($item['face_value_cents'] ?? 0), (string) ($item['currency'] ?? 'USD'));
        $canSend = mg_personal_agent_account_gift_can_send($item, $folder);
        $route = '/' . $folder . '.php?item=' . rawurlencode($actionItemId);

        $meta = [
            ['label' => 'Merchant', 'value' => $merchant],
            ['label' => 'Status', 'value' => ucfirst(str_replace('_', ' ', $state))],
        ];
        if ($folder === 'sent' && $recipient !== '') $meta[] = ['label' => 'To', 'value' => $recipient];
        if ($folder === 'inbox' && $sender !== '') $meta[] = ['label' => 'From', 'value' => $sender];
        if ($folder === 'claimed' && !empty($item['redeemed_at'] ?? $item['claimed_at'] ?? null)) {
            $meta[] = ['label' => 'Completed', 'value' => (string) ($item['redeemed_at'] ?? $item['claimed_at'])];
        }
        if (!empty($item['expires_at'])) $meta[] = ['label' => 'Expires', 'value' => (string) $item['expires_at']];

        $cards[] = [
            'type' => 'account_gift',
            'result_kind' => 'gift',
            'folder' => $folder,
            'action_item_id' => $actionItemId,
            'id' => $actionItemId,
            'eyebrow' => ucfirst($folder) . ' Microgift',
            'title' => $title,
            'body' => mg_personal_agent_text($item['message'] ?? $item['location_name'] ?? 'Open this Microgift to review its current details.', 700),
            'image_url' => mg_personal_agent_account_gift_image($item),
            'image_alt' => $title,
            'price' => $value,
            'merchant_name' => $merchant,
            'state' => $state,
            'can_send' => $canSend,
            'send_label' => 'Send',
            'url' => $route,
            'url_label' => 'Open gift',
            'meta' => array_slice($meta, 0, 5),
            'risk_level' => $canSend ? 'medium' : 'low',
        ];
    }

    return $cards;
}

function mg_personal_agent_account_gift_reply(string $folder, int $count, bool $hasSendable): string
{
    $label = ucfirst($folder);
    if ($count < 1) return 'Your ' . $label . ' does not currently contain any gifts.';

    $reply = 'I found ' . $count . ' ' . ($count === 1 ? 'gift' : 'gifts') . ' in your ' . $label . '. Each result below is tied to your signed-in account and current Action Center state.';
    if ($hasSendable) $reply .= ' Select Send on an eligible Inbox gift to choose a recipient and review the transfer before anything is sent.';
    if ($folder !== 'inbox') $reply .= ' These results are view-only because only currently owned Inbox gifts can be transferred.';
    return $reply;
}

function mg_personal_agent_chat_with_account_gift_response(PDO $pdo, int $userId, array $input): array
{
    $result = mg_personal_agent_chat_with_marketplace_response($pdo, $userId, $input);
    $message = mg_personal_agent_text($input['message'] ?? '', 2000);
    $folder = mg_personal_agent_account_gift_folder($message);
    if ($folder === '') return $result;

    $giftCards = mg_personal_agent_account_gift_cards($pdo, $userId, $folder, 12);
    $hasSendable = false;
    foreach ($giftCards as $card) {
        if (!empty($card['can_send'])) {
            $hasSendable = true;
            break;
        }
    }

    $reply = mg_personal_agent_account_gift_reply($folder, count($giftCards), $hasSendable);
    $assistantId = (string) ($result['assistant_message']['id'] ?? '');
    if ($assistantId !== '') {
        try {
            $stmt = $pdo->prepare("UPDATE user_agent_messages SET body=?,cards_json=? WHERE owner_user_id=? AND public_id=? AND role='assistant'");
            $stmt->execute([$reply, $giftCards ? mg_personal_agent_json_encode($giftCards) : null, $userId, $assistantId]);
        } catch (Throwable $error) {
            if (function_exists('mg_security_log')) {
                mg_security_log('warning', 'user_agent.account_gift_cards_persist_failed', 'Account gift result cards could not be persisted.', ['exception_type' => $error::class], $userId);
            }
        }
    }

    $result['assistant_message']['body'] = $reply;
    $result['assistant_message']['cards'] = $giftCards;
    $result['account_gift_folder'] = $folder;
    $result['account_gift_result_count'] = count($giftCards);
    return $result;
}
