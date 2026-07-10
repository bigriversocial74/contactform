<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/api/db.php';

function mg_qm_json(mixed $value): array
{
    if (!is_string($value) || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_qm_image(array $rules): string
{
    foreach (['cover_image_url','quest_image_url','media_image_url','image_url'] as $key) {
        $url = trim((string)($rules[$key] ?? ''));
        if ($url !== '') return $url;
    }
    return '/assets/images/loyalty-quest-placeholder.svg';
}

$slug = strtolower(trim((string)($_GET['merchant'] ?? $_GET['slug'] ?? '')));
$slug = preg_replace('/[^a-z0-9_-]+/', '', $slug) ?? '';
$merchant = null;
$quests = [];
$error = '';
if ($slug === '') {
    $error = 'Merchant profile is missing.';
} else {
    try {
        $pdo = mg_db();
        $stmt = $pdo->prepare("SELECT pp.*,mw.display_name workspace_name,mw.website_url workspace_website,mw.support_email,mw.support_phone
            FROM public_profiles pp
            LEFT JOIN merchant_workspaces mw ON mw.merchant_user_id=pp.user_id
            WHERE pp.slug=? AND pp.visibility='public' AND pp.status='active' LIMIT 1");
        $stmt->execute([$slug]);
        $merchant = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$merchant) {
            $error = 'Merchant profile not found.';
        } else {
            $questStmt = $pdo->prepare("SELECT c.public_id,c.public_slug,c.title,c.description,c.ends_at,c.quantity_limit,c.issued_count,c.rules_json,
                rt.title reward_title,rt.value_type,rt.value_amount_cents,rt.value_percent,rt.currency,
                ml.name location_name,ml.city,ml.region
                FROM campaigns c
                LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id AND rt.status='active'
                LEFT JOIN merchant_locations ml ON ml.merchant_user_id=c.merchant_user_id AND ml.status='active' AND ml.public_id=JSON_UNQUOTE(JSON_EXTRACT(c.rules_json,'$.location_id'))
                WHERE c.merchant_user_id=? AND c.campaign_type='loyalty_quest' AND c.status='active'
                  AND (c.starts_at IS NULL OR c.starts_at<=NOW())
                  AND (c.ends_at IS NULL OR c.ends_at>NOW())
                  AND (JSON_EXTRACT(c.rules_json,'$.visibility') IS NULL OR JSON_UNQUOTE(JSON_EXTRACT(c.rules_json,'$.visibility'))='public')
                ORDER BY c.updated_at DESC LIMIT 50");
            $questStmt->execute([(int)$merchant['user_id']]);
            foreach ($questStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $rules = mg_qm_json($row['rules_json'] ?? null);
                $value = '';
                if (($row['value_type'] ?? '') === 'percent' && $row['value_percent'] !== null) {
                    $value = rtrim(rtrim(number_format((float)$row['value_percent'], 2), '0'), '.') . '%';
                } elseif ($row['value_amount_cents'] !== null) {
                    $value = strtoupper((string)($row['currency'] ?? 'USD')) . ' ' . number_format(((int)$row['value_amount_cents']) / 100, 2);
                }
                $remaining = $row['quantity_limit'] === null ? null : max(0, (int)$row['quantity_limit'] - (int)$row['issued_count']);
                $ref = (string)($row['public_slug'] ?: $row['public_id']);
                $quests[] = [
                    'title'=>(string)$row['title'],
                    'description'=>(string)($row['description'] ?? ''),
                    'image_url'=>mg_qm_image($rules),
                    'action_type'=>(string)($rules['action_type'] ?? ''),
                    'verification_type'=>(string)($rules['verification_type'] ?? ''),
                    'location'=>implode(', ', array_filter([(string)($row['location_name'] ?? ''),(string)($row['city'] ?? ''),(string)($row['region'] ?? '')])),
                    'reward_title'=>$row['reward_title'] ?? null,
                    'reward_value'=>$value,
                    'remaining'=>$remaining,
                    'ends_at'=>$row['ends_at'] ?? null,
                    'url'=>'/loyalty-quest.php?campaign=' . rawurlencode($ref),
                ];
            }
        }
    } catch (Throwable $exception) {
        $error = 'Merchant quests are temporarily unavailable.';
    }
}

$merchantName = $merchant ? trim((string)($merchant['display_name'] ?: $merchant['workspace_name'] ?: 'Microgifter Merchant')) : 'Merchant Quests';
$page_title = $merchantName . ' Loyalty Quests | Microgifter';
$page_section = 'quests';
$header_mode = 'public';
$page_styles = ['/assets/css/loyalty-quest-marketplace.css'];
$page_meta = ['description' => $merchant ? trim((string)($merchant['headline'] ?: $merchant['bio'] ?: 'Explore this merchant’s active Microgifter Loyalty Quests.')) : 'Explore Microgifter Loyalty Quests.'];
require __DIR__ . '/includes/header.php';
?>
<main class="mg-quest-marketplace mg-quest-merchant-page">
  <?php if ($error !== ''): ?>
    <section class="mg-quest-results-shell"><div class="mg-quest-empty"><h1>Merchant quests unavailable</h1><p><?= mg_e($error) ?></p><a href="/quests.php">Explore all quests</a></div></section>
  <?php else: ?>
    <section class="mg-quest-marketplace-hero mg-quest-merchant-hero">
      <div class="mg-quest-marketplace-copy">
        <span class="mg-quest-kicker">Microgifter Merchant</span>
        <div class="mg-quest-merchant-title">
          <?php if (!empty($merchant['avatar_url'])): ?><img src="<?= mg_e((string)$merchant['avatar_url']) ?>" alt=""><?php endif; ?>
          <div><h1><?= mg_e($merchantName) ?></h1><p><?= mg_e((string)($merchant['headline'] ?: $merchant['bio'] ?: 'Local rewards and verified customer quests.')) ?></p></div>
        </div>
        <div class="mg-quest-card-meta">
          <?php if (!empty($merchant['location_label'])): ?><span><?= mg_e((string)$merchant['location_label']) ?></span><?php endif; ?>
          <?php if (!empty($merchant['website_url']) || !empty($merchant['workspace_website'])): ?><a href="<?= mg_e((string)($merchant['website_url'] ?: $merchant['workspace_website'])) ?>" target="_blank" rel="noopener">Visit website</a><?php endif; ?>
          <a href="/quests.php">All Loyalty Quests</a>
        </div>
      </div>
      <div class="mg-quest-marketplace-summary"><strong><?= count($quests) ?></strong><span>active quest<?= count($quests) === 1 ? '' : 's' ?></span><small>Rewards are delivered through Microgifter.</small></div>
    </section>
    <section class="mg-quest-results-shell">
      <div class="mg-quest-results-head"><div><span class="mg-quest-kicker">Available now</span><h2>Choose a quest</h2></div></div>
      <div class="mg-quest-grid">
        <?php if ($quests === []): ?>
          <div class="mg-quest-empty"><h3>No active public quests right now.</h3><p>Check back soon or explore quests from other Microgifter merchants.</p><a href="/quests.php">Explore all quests</a></div>
        <?php else: foreach ($quests as $quest): ?>
          <article class="mg-quest-card">
            <div class="mg-quest-card-media"><img src="<?= mg_e($quest['image_url']) ?>" alt="<?= mg_e($quest['title']) ?>" loading="lazy"><div class="mg-quest-card-badges"><span><?= mg_e(ucwords(str_replace('_',' ',$quest['action_type'] ?: 'Loyalty Quest'))) ?></span><span><?= $quest['remaining'] === null ? 'Available' : mg_e((string)$quest['remaining']) . ' left' ?></span></div></div>
            <div class="mg-quest-card-body"><div class="mg-quest-card-merchant"><span><?= mg_e($merchantName) ?></span></div><h3><?= mg_e($quest['title']) ?></h3><p><?= mg_e($quest['description'] ?: 'Complete this quest and earn a Microgifter reward.') ?></p><div class="mg-quest-card-meta"><?php if ($quest['verification_type'] !== ''): ?><span><?= mg_e(ucwords(str_replace('_',' ',$quest['verification_type']))) ?></span><?php endif; ?><?php if ($quest['location'] !== ''): ?><span><?= mg_e($quest['location']) ?></span><?php endif; ?></div><div class="mg-quest-card-footer"><div class="mg-quest-card-reward"><small>Reward</small><strong><?= mg_e((string)($quest['reward_title'] ?: 'Microgifter reward')) ?></strong><small><?= mg_e($quest['reward_value']) ?></small></div><a href="<?= mg_e($quest['url']) ?>">View quest</a></div></div>
          </article>
        <?php endforeach; endif; ?>
      </div>
    </section>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php';
