<?php
declare(strict_types=1);

require_once __DIR__ . '/campaign-landing-foundation.php';
require_once __DIR__ . '/campaign-user-details.php';

function mg_campaign_media_pick(array $source, array $keys): string
{
    foreach ($keys as $key) {
        if (!array_key_exists($key, $source)) continue;
        $value = trim((string)$source[$key]);
        if ($value !== '') return $value;
    }
    return '';
}

function mg_campaign_media_money(mixed $cents, string $currency): string
{
    if (!is_numeric($cents)) return '';
    $amount = max(0, (int)$cents);
    return $amount > 0 ? '$' . number_format($amount / 100, 2) . ' ' . strtoupper($currency ?: 'USD') : '';
}

function mg_campaign_media_level_label(array $percents): string
{
    $levels = [];
    foreach ($percents as $percent) {
        $percent = max(1, min(100, (int)$percent));
        $levels[$percent] = $percent . '%';
    }
    ksort($levels, SORT_NUMERIC);
    return implode(', ', array_values($levels));
}

function mg_campaign_media_level_percents(array $milestones, int $requiredPercent): array
{
    $levels = [];
    foreach ($milestones as $milestone) {
        if (!is_array($milestone) || !isset($milestone['percent'])) continue;
        $percent = max(1, min(100, (int)$milestone['percent']));
        $levels[$percent] = $percent;
    }
    if (!$levels) $levels[$requiredPercent] = $requiredPercent;
    ksort($levels, SORT_NUMERIC);
    return array_slice(array_values($levels), 0, 4);
}

function mg_campaign_media_allocations(array $milestones, array $defaults): array
{
    $groups = [];
    foreach ($milestones as $milestone) {
        if (!is_array($milestone)) continue;
        $percent = max(1, min(100, (int)($milestone['percent'] ?? $defaults['required_percent'] ?? 100)));
        $title = mg_campaign_media_pick($milestone, [
            'reward_title', 'reward_name', 'reward_template_title', 'product_name', 'gift_name', 'title',
        ]) ?: (string)$defaults['title'];
        $value = mg_campaign_media_money(
            $milestone['value_amount_cents'] ?? $milestone['reward_value_amount_cents'] ?? $milestone['value_cents'] ?? null,
            (string)$defaults['currency']
        );
        $value = $value ?: (mg_campaign_media_pick($milestone, [
            'reward_value', 'value_label', 'display_value', 'value',
        ]) ?: (string)$defaults['value']);
        $image = mg_campaign_landing_safe_url(
            $milestone['reward_image_url'] ?? $milestone['reward_image'] ?? $milestone['gift_image_url'] ?? null
        ) ?: ($defaults['image'] ?? null);
        $key = sha1($title . '|' . $value . '|' . (string)$image);
        if (!isset($groups[$key])) {
            $groups[$key] = ['title' => $title, 'value' => $value, 'image' => $image, 'levels' => []];
        }
        $groups[$key]['levels'][] = $percent;
    }
    if (!$groups) {
        $groups[] = [
            'title' => (string)$defaults['title'],
            'value' => (string)$defaults['value'],
            'image' => $defaults['image'] ?? null,
            'levels' => $defaults['levels'] ?? [(int)$defaults['required_percent']],
        ];
    }
    return array_values($groups);
}

function mg_campaign_media_render_join(array $context): void
{
    $kind = in_array((string)($context['kind'] ?? ''), ['watch', 'listen'], true)
        ? (string)$context['kind']
        : 'watch';
    $campaign = is_array($context['campaign'] ?? null) ? $context['campaign'] : [];
    $profile = is_array($context['profile'] ?? null) ? $context['profile'] : [];
    $state = is_array($context['state'] ?? null) ? $context['state'] : [];
    $prefill = is_array($context['prefill'] ?? null) ? $context['prefill'] : [];
    $preview = !empty($context['preview']);
    $closed = !empty($state['closed']);
    $verb = $kind === 'watch' ? 'watching' : 'listening';
    $button = $kind === 'watch'
        ? 'Start Watching & Join Campaign'
        : 'Start Listening & Join Campaign';
    $dataAttribute = $kind === 'watch' ? 'data-watch-reward-form' : 'data-listen-reward-form';
    $resultAttribute = $kind === 'watch' ? 'data-watch-reward-result' : 'data-listen-reward-result';

    mg_campaign_landing_render_profile($profile);

    if ($closed): ?>
      <div class="mg-public-campaign-result is-visible" data-campaign-closed-state="<?= mg_e((string)($state['code'] ?? 'closed')) ?>">
        <strong><?= mg_e((string)($state['message'] ?? 'This campaign is currently closed.')) ?></strong>
      </div>
    <?php else: ?>
      <form class="mg-rl-form mg-rl-media-form" <?= $dataAttribute ?><?= $preview ? ' data-campaign-preview="merchant" onsubmit="return false"' : '' ?> novalidate>
        <input type="hidden" name="campaign_id" value="<?= mg_e((string)($campaign['public_id'] ?? '')) ?>">
        <input type="hidden" name="campaign" value="<?= mg_e((string)($campaign['public_slug'] ?? $campaign['public_id'] ?? '')) ?>">
        <input type="hidden" name="campaign_type" value="<?= mg_e((string)($campaign['campaign_type'] ?? '')) ?>">
        <h3>Join this campaign</h3>
        <p>Enter your details to begin <?= mg_e($verb) ?> and connect milestone rewards to your Microgifter Inbox.</p>
        <?php mg_campaign_render_user_details($prefill); ?>
        <button class="mg-rl-btn mg-rl-btn-dark" type="<?= $preview ? 'button' : 'submit' ?>"<?= $preview ? ' disabled aria-disabled="true"' : '' ?>><?= mg_e($preview ? 'Preview only - activate to publish' : $button) ?></button>
        <div class="mg-public-campaign-status" data-campaign-status><?= $preview ? 'Preview mode: media playback is available, but reward tracking is disabled.' : '' ?></div>
        <p class="mg-public-campaign-privacy">Progress and issued rewards remain connected to this campaign and your Microgifter Inbox.</p>
      </form>
      <div class="mg-public-campaign-result" <?= $resultAttribute ?>></div>
    <?php endif;
}

function mg_campaign_media_render_cards(array $context): void
{
    $kind = in_array((string)($context['kind'] ?? ''), ['watch', 'listen'], true)
        ? (string)$context['kind']
        : 'watch';
    $rewardAllocations = is_array($context['reward_allocations'] ?? null) ? $context['reward_allocations'] : [];
    $merchantName = trim((string)($context['merchant_name'] ?? '')) ?: 'Microgifter merchant';
    $rewardDescription = trim((string)($context['reward_description'] ?? ''));
    $levelPercents = is_array($context['level_percents'] ?? null) ? $context['level_percents'] : [];
    $requiredPercent = max(1, min(100, (int)($context['required_percent'] ?? 100)));
    $state = is_array($context['state'] ?? null) ? $context['state'] : [];
    $initialStatus = trim((string)($context['initial_status'] ?? '')) ?: (string)($state['active_status'] ?? 'Campaign status');
    $statusAttribute = $kind === 'watch' ? 'data-watch-reward-status' : 'data-listen-reward-status';
    $historyAttribute = $kind === 'watch' ? 'data-watch-reward-history' : 'data-listen-reward-history';
    $issueAttribute = $kind === 'watch' ? 'data-watch-reward-issue-history' : 'data-listen-reward-issue-history';
    $activityLabel = $kind === 'watch' ? 'watch' : 'listening';
    ?>
    <div class="mg-rl-bottom mg-rl-media-cards" data-campaign-foundation-cards>
      <article class="mg-rl-card mg-rl-reward-info">
        <span class="mg-rl-eyebrow">Reward Info</span>
        <div class="mg-rl-reward-carousel">
          <div class="mg-rl-reward-stack <?= count($rewardAllocations) > 1 ? 'has-multiple' : '' ?>">
            <?php foreach ($rewardAllocations as $allocation): ?>
              <div class="mg-rl-reward-item <?= !empty($allocation['image']) ? 'has-image' : 'is-text-only' ?>">
                <?php if (!empty($allocation['image'])): ?><img class="mg-rl-reward-image" src="<?= mg_e((string)$allocation['image']) ?>" alt="<?= mg_e((string)$allocation['title']) ?> reward image"><?php endif; ?>
                <span class="mg-rl-reward-copy">
                  <strong class="mg-rl-reward-business"><?= mg_e($merchantName) ?></strong>
                  <b class="mg-rl-reward-name"><?= mg_e((string)$allocation['title']) ?></b>
                  <small class="mg-rl-reward-value"><?= mg_e((string)$allocation['value']) ?></small>
                  <small class="mg-rl-reward-levels">Reward level<?= count($allocation['levels']) > 1 ? 's' : '' ?>: <?= mg_e(mg_campaign_media_level_label((array)$allocation['levels'])) ?></small>
                </span>
              </div>
            <?php endforeach; ?>
          </div>
          <small class="mg-rl-carousel-hint">Scroll to view all rewards</small>
        </div>
        <?php if ($rewardDescription !== ''): ?><p><?= mg_e($rewardDescription) ?></p><?php endif; ?>
      </article>
      <article class="mg-rl-card mg-rl-levels">
        <span class="mg-rl-eyebrow">Reward Levels</span>
        <h3><?= $kind === 'watch' ? 'Watch' : 'Listen' ?> progress unlocks reward milestones.</h3>
        <div class="mg-rl-progress-row">
          <?php foreach ($levelPercents as $percent): ?>
            <div class="mg-rl-step <?= (int)$percent <= $requiredPercent ? 'is-active' : '' ?>">
              <span class="mg-rl-dot"><?= mg_e((string)$percent) ?>%</span><b><?= mg_e((string)$percent) ?>%</b>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="mg-rl-bar"><span style="width:<?= mg_e((string)$requiredPercent) ?>%"></span></div>
      </article>
      <article class="mg-rl-card mg-rl-status-card">
        <span class="mg-rl-eyebrow">Active Status &amp; Updates</span>
        <h3 <?= $statusAttribute ?>><?= mg_e($initialStatus) ?></h3>
        <ul class="mg-rl-list" <?= $historyAttribute ?>><li>No <?= mg_e($activityLabel) ?> activity yet.</li></ul>
        <ul class="mg-rl-list" <?= $issueAttribute ?>><li>No rewards issued yet.</li></ul>
      </article>
    </div>
    <?php
}
