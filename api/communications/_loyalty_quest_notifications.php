<?php
declare(strict_types=1);

require_once __DIR__ . '/_communications.php';
require_once __DIR__ . '/_delivery.php';
require_once dirname(__DIR__, 2) . '/includes/mail.php';

function mg_lqn_base_url(): string
{
    return rtrim(mg_app_base_url(), '/');
}

function mg_lqn_user(PDO $pdo, int $userId): ?array
{
    if ($userId < 1) return null;
    $stmt = $pdo->prepare("SELECT id,email,display_name,full_name,status FROM users WHERE id=? AND status='active' LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    $row['name'] = trim((string)($row['display_name'] ?? $row['full_name'] ?? '')) ?: (string)$row['email'];
    return $row;
}

function mg_lqn_campaign_ref(array $campaign): string
{
    return (string)($campaign['public_slug'] ?? '') !== '' ? (string)$campaign['public_slug'] : (string)($campaign['public_id'] ?? '');
}

function mg_lqn_source_id(array $context): string
{
    foreach (['source_public_id','evidence_id','participation_id','wallet_item_id','pppm_item_id','campaign_public_id'] as $key) {
        $value = trim((string)($context[$key] ?? ''));
        if ($value !== '') return mb_substr($value, 0, 190);
    }
    return substr(hash('sha256', json_encode(mg_delivery_redact($context), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)), 0, 64);
}

function mg_lqn_safe_note(string $value, int $limit = 320): string
{
    $value = preg_replace('/\s+/', ' ', trim($value)) ?? '';
    return mb_substr($value, 0, $limit);
}

function mg_lqn_template(string $eventType, array $campaign, array $context, array $recipient): array
{
    $title = trim((string)($campaign['title'] ?? $context['campaign_title'] ?? 'Loyalty Quest')) ?: 'Loyalty Quest';
    $merchant = trim((string)($context['merchant_name'] ?? $campaign['merchant_name'] ?? 'Microgifter Merchant')) ?: 'Microgifter Merchant';
    $ref = mg_lqn_campaign_ref($campaign);
    $questUrl = mg_lqn_base_url() . '/loyalty-quest.php?campaign=' . rawurlencode($ref);
    $reviewUrl = mg_lqn_base_url() . '/merchant-quest-reviews.php' . ($ref !== '' ? '?campaign=' . rawurlencode((string)($campaign['public_id'] ?? $ref)) : '');
    $inboxUrl = mg_lqn_base_url() . '/inbox.php';
    $notificationsUrl = mg_lqn_base_url() . '/notifications.php';
    $name = (string)($recipient['name'] ?? 'there');
    $note = mg_lqn_safe_note((string)($context['review_note'] ?? ''));
    $progress = max(0, (int)($context['progress_count'] ?? 0));
    $required = max(1, (int)($context['required_count'] ?? 1));
    $reward = trim((string)($context['reward_title'] ?? 'Microgifter reward')) ?: 'Microgifter reward';
    $expires = trim((string)($context['expires_at'] ?? ''));

    $templates = [
        'participant_joined' => [
            'title'=>'Loyalty Quest started',
            'body'=>"You started {$title} from {$merchant}. Complete the required action to keep your progress moving.",
            'button'=>'Continue quest','url'=>$questUrl,
        ],
        'merchant_participant_joined' => [
            'title'=>'A participant started your quest',
            'body'=>"A participant started {$title}. Their progress and evidence will appear in your Loyalty Quest workspace.",
            'button'=>'Manage quest','url'=>mg_lqn_base_url().'/merchant-loyalty-quests.php',
        ],
        'evidence_submitted' => [
            'title'=>'Quest evidence submitted',
            'body'=>"Your completion evidence for {$title} was received and is waiting for merchant review.",
            'button'=>'View quest status','url'=>$questUrl,
        ],
        'merchant_review_required' => [
            'title'=>'Loyalty Quest evidence needs review',
            'body'=>"A participant submitted evidence for {$title}. Review it before the reward is released.",
            'button'=>'Review evidence','url'=>$reviewUrl,
        ],
        'evidence_approved' => [
            'title'=>'Quest evidence approved',
            'body'=>"{$merchant} approved your evidence for {$title}. Your progress is {$progress} of {$required}.",
            'button'=>'View progress','url'=>$questUrl,
        ],
        'evidence_rejected' => [
            'title'=>'Quest evidence needs an update',
            'body'=>"{$merchant} returned your evidence for {$title}." . ($note !== '' ? " Review note: {$note}" : ' Open the quest and submit corrected proof.'),
            'button'=>'Correct evidence','url'=>$questUrl,
        ],
        'progress_verified' => [
            'title'=>'Loyalty Quest progress verified',
            'body'=>"Your verified progress for {$title} is now {$progress} of {$required}.",
            'button'=>'Continue quest','url'=>$questUrl,
        ],
        'reward_delivered' => [
            'title'=>'Your Loyalty Quest reward arrived',
            'body'=>"You completed {$title}. {$reward} is now available in your Microgifter Inbox.",
            'button'=>'Open Inbox','url'=>$inboxUrl,
        ],
        'quest_expiring' => [
            'title'=>'Your Loyalty Quest ends soon',
            'body'=>"{$title} ends soon" . ($expires !== '' ? " on {$expires}" : '') . ". Complete the remaining action before the campaign closes.",
            'button'=>'Continue quest','url'=>$questUrl,
        ],
        'reward_expiring' => [
            'title'=>'Your reward expires soon',
            'body'=>"{$reward} from {$title} expires soon" . ($expires !== '' ? " on {$expires}" : '') . '. Open Inbox to review the Microgift and redemption terms.',
            'button'=>'Open Inbox','url'=>$inboxUrl,
        ],
        'redemption_receipt' => [
            'title'=>'Reward redeemed',
            'body'=>"Your {$reward} from {$title} was redeemed successfully. The completed ownership history remains in Microgifter.",
            'button'=>'View notifications','url'=>$notificationsUrl,
        ],
        'merchant_redemption_receipt' => [
            'title'=>'Loyalty Quest reward redeemed',
            'body'=>"A participant redeemed {$reward} from {$title}. The redemption is recorded in merchant claim history.",
            'button'=>'Open claims','url'=>mg_lqn_base_url().'/merchant-wallet-redemptions.php',
        ],
    ];
    $template = $templates[$eventType] ?? [
        'title'=>'Loyalty Quest update','body'=>"There is an update for {$title}.",'button'=>'Open notifications','url'=>$notificationsUrl,
    ];
    $template['subject'] = (string)$template['title'] . ' | Microgifter';
    $bodyHtml = '<p style="margin:0 0 16px;color:#334155;font-size:16px;line-height:1.6;">Hi ' . mg_mail_escape($name) . ',</p>'
        . '<p style="margin:0 0 16px;color:#334155;font-size:16px;line-height:1.6;">' . mg_mail_escape((string)$template['body']) . '</p>'
        . mg_email_button((string)$template['url'], (string)$template['button'])
        . '<p style="margin:0;color:#64748b;font-size:13px;line-height:1.6;">You can change Loyalty Quest delivery preferences in Microgifter notification settings.</p>';
    $template['html'] = mg_email_layout((string)$template['title'], $bodyHtml, (string)$template['body']);
    $template['text'] = "Hi {$name},\n\n" . (string)$template['body'] . "\n\n" . (string)$template['url'];
    return $template;
}

function mg_lqn_dispatch(PDO $pdo, string $eventType, int $recipientUserId, array $campaign, array $context = []): array
{
    $recipient = mg_lqn_user($pdo, $recipientUserId);
    if (!$recipient) return ['status'=>'skipped','reason'=>'recipient_unavailable'];
    $preference = mg_notification_preference($pdo, $recipientUserId, 'loyalty_quest');
    $template = mg_lqn_template($eventType, $campaign, $context, $recipient);
    $sourcePublicId = mg_lqn_source_id($context + ['campaign_public_id'=>$campaign['public_id'] ?? null]);
    $eventKey = substr('loyalty-quest:' . $eventType . ':' . $sourcePublicId . ':user:' . $recipientUserId, 0, 190);
    $result = ['status'=>'created','event_key'=>$eventKey,'in_app'=>null,'email'=>null];

    if (!empty($preference['in_app_enabled'])) {
        $result['in_app'] = mg_create_notification(
            $pdo,
            $recipientUserId,
            'loyalty_quest',
            (string)$template['title'],
            (string)$template['body'],
            parse_url((string)$template['url'], PHP_URL_PATH) . (($query=parse_url((string)$template['url'],PHP_URL_QUERY)) ? '?' . $query : ''),
            [
                'event_key'=>$eventKey,
                'in_app_only'=>true,
                'allow_self'=>true,
                'merchant_user_id'=>(int)($campaign['merchant_user_id'] ?? $context['merchant_user_id'] ?? 0),
                'campaign_id'=>(int)($campaign['id'] ?? $context['campaign_id'] ?? 0),
                'campaign_public_id'=>(string)($campaign['public_id'] ?? ''),
                'source_public_id'=>$sourcePublicId,
                'event_type'=>$eventType,
                'pppm_item_id'=>$context['pppm_item_id'] ?? null,
            ]
        );
    }

    $email = trim((string)($recipient['email'] ?? ''));
    $emailAllowed = !empty($preference['email_enabled'])
        && (string)($preference['digest_mode'] ?? 'immediate') !== 'off'
        && mg_notification_delivery_channel_available('email')
        && filter_var($email, FILTER_VALIDATE_EMAIL);
    if ($emailAllowed) {
        $result['email'] = mg_delivery_enqueue($pdo, [
            'idempotency_key'=>$eventKey . ':email',
            'event_type'=>'loyalty_quest.' . $eventType,
            'category'=>'loyalty_quest',
            'recipient_user_id'=>$recipientUserId,
            'merchant_user_id'=>(int)($campaign['merchant_user_id'] ?? $context['merchant_user_id'] ?? 0),
            'campaign_id'=>(int)($campaign['id'] ?? $context['campaign_id'] ?? 0),
            'source_public_id'=>$sourcePublicId,
            'channel'=>'email',
            'template_key'=>'loyalty_quest.' . $eventType,
            'max_attempts'=>5,
            'next_attempt_at'=>mg_notification_delivery_time($preference, 'email'),
            'recipient_snapshot'=>['email'=>$email,'name'=>(string)$recipient['name']],
            'payload'=>[
                'subject'=>(string)$template['subject'],
                'html'=>(string)$template['html'],
                'text'=>(string)$template['text'],
                'campaign_public_id'=>(string)($campaign['public_id'] ?? ''),
                'source_public_id'=>$sourcePublicId,
                'event_type'=>$eventType,
            ],
        ]);
    } else {
        $result['email'] = ['status'=>'skipped','reason'=>'preference_or_channel_disabled'];
    }
    return $result;
}

function mg_lqn_notify_participant(PDO $pdo, string $eventType, array $campaign, int $participantUserId, array $context = []): array
{
    return mg_lqn_dispatch($pdo, $eventType, $participantUserId, $campaign, $context);
}

function mg_lqn_notify_merchant(PDO $pdo, string $eventType, array $campaign, array $context = []): array
{
    return mg_lqn_dispatch($pdo, $eventType, (int)($campaign['merchant_user_id'] ?? 0), $campaign, $context);
}
