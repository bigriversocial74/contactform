<?php
declare(strict_types=1);

if (!function_exists('mg_public_donations_view_number')) {
    function mg_public_donations_view_number(int $value): string
    {
        return number_format(max(0, $value));
    }
}

if (!function_exists('mg_public_donations_view_money')) {
    function mg_public_donations_view_money(int $cents, ?string $currency): string
    {
        $currency = strtoupper(trim((string)$currency));
        if ($currency === '') {
            return $cents === 0 ? '$0.00' : 'Mixed currencies';
        }
        return $currency . ' ' . number_format(max(0, $cents) / 100, 2);
    }
}

if (!is_array($publicDonationsPayload ?? null)):
    $message = trim((string)($publicDonationsUnavailable ?? '')) ?: 'Use the active campaign link shared by the merchant.';
    ?>
    <main class="mg-pd-public mg-pd-public--unavailable">
      <section class="mg-pd-public__empty">
        <span class="mg-pd-public__eyebrow">Public Donations</span>
        <h1>Campaign not available</h1>
        <p><?= mg_e($message) ?></p>
        <a class="mg-pd-public__button" href="/discover.php">Explore local offers</a>
      </section>
    </main>
    <?php return; endif;

$campaign = is_array($publicDonationsPayload['campaign'] ?? null) ? $publicDonationsPayload['campaign'] : [];
$merchant = is_array($publicDonationsPayload['merchant'] ?? null) ? $publicDonationsPayload['merchant'] : [];
$reward = is_array($publicDonationsPayload['reward'] ?? null) ? $publicDonationsPayload['reward'] : [];
$impact = is_array($publicDonationsPayload['impact'] ?? null) ? $publicDonationsPayload['impact'] : [];
$communityAccounts = is_array($publicDonationsPayload['community_accounts'] ?? null) ? $publicDonationsPayload['community_accounts'] : [];
$governance = is_array($publicDonationsPayload['governance'] ?? null) ? $publicDonationsPayload['governance'] : [];
$heroImage = trim((string)($campaign['image_url'] ?? '')) ?: trim((string)($reward['image_url'] ?? ''));
$merchantAvatar = trim((string)($merchant['avatar_url'] ?? ''));
$merchantCover = trim((string)($merchant['cover_url'] ?? ''));
$merchantName = trim((string)($merchant['display_name'] ?? '')) ?: 'Microgifter merchant';
$merchantInitial = mb_strtoupper(mb_substr($merchantName, 0, 1));
$valueBuckets = is_array($impact['stated_value_by_currency'] ?? null) ? $impact['stated_value_by_currency'] : [];
$endsAt = trim((string)($campaign['ends_at'] ?? ''));
$structuredData = [
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => (string)($campaign['headline'] ?? $campaign['title'] ?? 'Public Donations'),
    'description' => (string)($campaign['description'] ?? ''),
    'url' => (string)($campaign['public_url'] ?? ''),
    'isPartOf' => ['@type' => 'WebSite', 'name' => 'Microgifter'],
    'about' => [
        '@type' => 'Thing',
        'name' => 'Merchant-funded promotional Community rewards',
    ],
];
if ($heroImage !== '') {
    $structuredData['image'] = $heroImage;
}
?>
<script type="application/ld+json"><?= json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<main class="mg-pd-public" data-public-donations-campaign>
  <section class="mg-pd-public__hero">
    <div class="mg-pd-public__hero-backdrop"<?= $merchantCover !== '' ? ' style="background-image:url(' . mg_e($merchantCover) . ')"' : '' ?> aria-hidden="true"></div>
    <div class="mg-pd-public__hero-grid">
      <div class="mg-pd-public__hero-copy">
        <div class="mg-pd-public__status-row">
          <span class="mg-pd-public__eyebrow">Public Donations</span>
          <span class="mg-pd-public__status">Active informational campaign</span>
        </div>
        <h1><?= mg_e((string)($campaign['headline'] ?? $campaign['title'] ?? 'Community reward support')) ?></h1>
        <p class="mg-pd-public__lead"><?= mg_e((string)($campaign['description'] ?? '')) ?></p>
        <div class="mg-pd-public__trust-row" aria-label="Campaign rules">
          <span>Merchant allocated</span>
          <span>Promotional rewards</span>
          <span>No public transaction</span>
          <span>Privacy-safe reporting</span>
        </div>
        <?php if ($endsAt !== ''): ?><p class="mg-pd-public__date">Campaign scheduled through <?= mg_e(date('F j, Y', strtotime($endsAt) ?: time())) ?></p><?php endif; ?>
      </div>
      <div class="mg-pd-public__hero-art">
        <?php if ($heroImage !== ''): ?>
          <img src="<?= mg_e($heroImage) ?>" alt="<?= mg_e((string)($reward['title'] ?? $campaign['title'] ?? 'Public Donations campaign')) ?> artwork">
        <?php else: ?>
          <div class="mg-pd-public__art-placeholder" aria-hidden="true"><span>Community</span><strong>Reward Support</strong></div>
        <?php endif; ?>
        <div class="mg-pd-public__reward-chip">
          <span>Featured reward</span>
          <strong><?= mg_e((string)($reward['title'] ?? 'Promotional reward')) ?></strong>
          <small><?= mg_e((string)($reward['value_label'] ?? 'Promotional reward')) ?></small>
        </div>
      </div>
    </div>
  </section>

  <section class="mg-pd-public__merchant-card" aria-label="Campaign merchant">
    <div class="mg-pd-public__merchant-avatar">
      <?php if ($merchantAvatar !== ''): ?><img src="<?= mg_e($merchantAvatar) ?>" alt="<?= mg_e($merchantName) ?> profile image"><?php else: ?><span><?= mg_e($merchantInitial) ?></span><?php endif; ?>
    </div>
    <div class="mg-pd-public__merchant-copy">
      <span>Presented by</span>
      <h2><?= mg_e($merchantName) ?></h2>
      <?php if (trim((string)($merchant['headline'] ?? '')) !== ''): ?><p><?= mg_e((string)$merchant['headline']) ?></p><?php endif; ?>
      <?php if (trim((string)($merchant['location'] ?? '')) !== ''): ?><small><?= mg_e((string)$merchant['location']) ?></small><?php endif; ?>
    </div>
    <div class="mg-pd-public__merchant-links">
      <?php if (!empty($merchant['profile_url'])): ?><a href="<?= mg_e((string)$merchant['profile_url']) ?>">Merchant profile</a><?php endif; ?>
      <?php if (!empty($merchant['community_url'])): ?><a href="<?= mg_e((string)$merchant['community_url']) ?>">Community support</a><?php endif; ?>
      <a class="is-primary" href="<?= mg_e((string)($merchant['offers_url'] ?? '/discover.php')) ?>">Shop normal offers</a>
    </div>
  </section>

  <section class="mg-pd-public__impact" aria-labelledby="mg-pd-impact-title">
    <header class="mg-pd-public__section-head">
      <div>
        <span class="mg-pd-public__eyebrow">Campaign impact</span>
        <h2 id="mg-pd-impact-title">Promotional rewards allocated by the merchant</h2>
      </div>
      <p>Totals are based on campaign reward attribution. They do not reveal final recipients, claims, ownership records, or private account information.</p>
    </header>
    <div class="mg-pd-public__metrics">
      <article><span>Community accounts supported</span><strong><?= mg_e(mg_public_donations_view_number((int)($impact['supported_accounts'] ?? 0))) ?></strong></article>
      <article><span>Gross rewards allocated</span><strong><?= mg_e(mg_public_donations_view_number((int)($impact['gross_allocated'] ?? 0))) ?></strong></article>
      <article><span>Rewards recalled</span><strong><?= mg_e(mg_public_donations_view_number((int)($impact['recalled'] ?? 0))) ?></strong></article>
      <article><span>Net rewards allocated</span><strong><?= mg_e(mg_public_donations_view_number((int)($impact['net_allocated'] ?? 0))) ?></strong></article>
      <?php foreach ($valueBuckets as $bucket): ?>
        <article><span><?= mg_e((string)($bucket['currency'] ?? '')) ?> net stated promotional value</span><strong><?= mg_e(mg_public_donations_view_money((int)($bucket['net_cents'] ?? 0), (string)($bucket['currency'] ?? ''))) ?></strong></article>
      <?php endforeach; ?>
    </div>
    <p class="mg-pd-public__value-note">Stated promotional value describes the merchant reward offer. It is not cash, a charitable receipt, or a tax-deductible contribution.</p>
  </section>

  <section class="mg-pd-public__reward" aria-labelledby="mg-pd-reward-title">
    <div class="mg-pd-public__reward-art">
      <?php if (trim((string)($reward['image_url'] ?? '')) !== ''): ?><img src="<?= mg_e((string)$reward['image_url']) ?>" alt="<?= mg_e((string)($reward['title'] ?? 'Promotional reward')) ?> artwork"><?php else: ?><span>Promotional reward</span><?php endif; ?>
    </div>
    <div class="mg-pd-public__reward-copy">
      <span class="mg-pd-public__eyebrow">Reward details</span>
      <h2 id="mg-pd-reward-title"><?= mg_e((string)($reward['title'] ?? 'Promotional reward')) ?></h2>
      <strong><?= mg_e((string)($reward['value_label'] ?? 'Promotional reward')) ?></strong>
      <?php if (trim((string)($reward['description'] ?? '')) !== ''): ?><p><?= mg_e((string)$reward['description']) ?></p><?php endif; ?>
      <div class="mg-pd-public__allocation-note">
        <h3>How this campaign works</h3>
        <ol>
          <li><strong>The merchant selects Community accounts.</strong><span>Accounts cannot join or request placement from this public page.</span></li>
          <li><strong>The merchant allocates individual rewards.</strong><span>Each reward follows Microgifter’s normal private account lifecycle.</span></li>
          <li><strong>This page reports aggregate impact.</strong><span>Only approved public Community profiles are featured by name.</span></li>
        </ol>
      </div>
    </div>
  </section>

  <section class="mg-pd-public__community" aria-labelledby="mg-pd-community-title">
    <header class="mg-pd-public__section-head">
      <div>
        <span class="mg-pd-public__eyebrow">Supported Community</span>
        <h2 id="mg-pd-community-title">Community accounts featured by permission</h2>
      </div>
      <p><?= mg_e(mg_public_donations_view_number((int)($impact['publicly_featured_accounts'] ?? 0))) ?> publicly featured · <?= mg_e(mg_public_donations_view_number((int)($impact['anonymous_accounts'] ?? 0))) ?> included anonymously in totals</p>
    </header>

    <?php if ($communityAccounts): ?>
      <div class="mg-pd-public__community-grid">
        <?php foreach ($communityAccounts as $account):
            $accountName = trim((string)($account['display_name'] ?? '')) ?: 'Community member';
            $accountAvatar = trim((string)($account['avatar_url'] ?? ''));
            $accountCover = trim((string)($account['cover_url'] ?? ''));
            $support = is_array($account['support'] ?? null) ? $account['support'] : [];
            ?>
          <article class="mg-pd-public__community-card">
            <div class="mg-pd-public__community-cover"<?= $accountCover !== '' ? ' style="background-image:url(' . mg_e($accountCover) . ')"' : '' ?>></div>
            <div class="mg-pd-public__community-avatar">
              <?php if ($accountAvatar !== ''): ?><img src="<?= mg_e($accountAvatar) ?>" alt="<?= mg_e($accountName) ?> profile image"><?php else: ?><span><?= mg_e(mb_strtoupper(mb_substr($accountName, 0, 1))) ?></span><?php endif; ?>
            </div>
            <div class="mg-pd-public__community-copy">
              <span>★ Community</span>
              <h3><?= mg_e($accountName) ?></h3>
              <?php if (trim((string)($account['headline'] ?? '')) !== ''): ?><p><?= mg_e((string)$account['headline']) ?></p><?php endif; ?>
              <?php if (trim((string)($account['location'] ?? '')) !== ''): ?><small><?= mg_e((string)$account['location']) ?></small><?php endif; ?>
              <div class="mg-pd-public__community-support">
                <strong><?= mg_e(mg_public_donations_view_number((int)($support['net_allocated'] ?? 0))) ?></strong><span>net rewards allocated</span>
                <?php if ((int)($support['net_stated_value_cents'] ?? 0) > 0): ?><strong><?= mg_e(mg_public_donations_view_money((int)$support['net_stated_value_cents'], $support['currency'] ?? null)) ?></strong><span>stated promotional value</span><?php endif; ?>
              </div>
              <a href="<?= mg_e((string)($account['profile_url'] ?? '#')) ?>"<?= empty($account['profile_indexable']) ? ' rel="nofollow"' : '' ?>>View public profile</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="mg-pd-public__anonymous-state">
        <strong>Community support remains privacy protected.</strong>
        <p>Supported accounts without approved public profiles are included only in the aggregate totals above.</p>
      </div>
    <?php endif; ?>

    <?php if ((int)($impact['anonymous_accounts'] ?? 0) > 0): ?>
      <div class="mg-pd-public__anonymous-note"><strong>+<?= mg_e(mg_public_donations_view_number((int)$impact['anonymous_accounts'])) ?> private Community account<?= (int)$impact['anonymous_accounts'] === 1 ? '' : 's' ?></strong><span>Included in campaign totals without public identity or contact information.</span></div>
    <?php endif; ?>
  </section>

  <section class="mg-pd-public__governance" aria-label="Public Donations governance">
    <div>
      <span class="mg-pd-public__eyebrow">Important distinction</span>
      <h2>Merchant-funded promotional rewards—not cash donations</h2>
      <p><?= mg_e((string)($governance['statement'] ?? 'The merchant allocates promotional rewards directly to selected Community accounts.')) ?></p>
    </div>
    <ul>
      <li>No checkout or public purchase</li>
      <li>No join or reward request form</li>
      <li>No quantity or reservation controls</li>
      <li>No claim or redemption controls</li>
      <li>No email or contact capture</li>
      <li>No tax-deductible contribution claim</li>
    </ul>
  </section>

  <section class="mg-pd-public__final-links">
    <div><span class="mg-pd-public__eyebrow">Keep exploring</span><h2>Support the merchant through normal offers</h2><p>Public Donations rewards are allocated by the merchant. Purchasable products and experiences remain available through the merchant’s regular Microgifter profile.</p></div>
    <div>
      <?php if (!empty($merchant['profile_url'])): ?><a href="<?= mg_e((string)$merchant['profile_url']) ?>">View merchant profile</a><?php endif; ?>
      <a class="is-primary" href="<?= mg_e((string)($merchant['offers_url'] ?? '/discover.php')) ?>">Explore purchasable offers</a>
    </div>
  </section>
</main>
