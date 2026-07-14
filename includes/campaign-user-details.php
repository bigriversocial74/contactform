<?php
declare(strict_types=1);

function mg_campaign_user_details_context(array $prefill = []): array
{
    $user = function_exists('mg_current_user') ? mg_current_user() : null;
    $loggedIn = is_array($user) && (int)($user['id'] ?? 0) > 0;

    $name = trim((string)($prefill['name'] ?? ''));
    $email = strtolower(trim((string)($prefill['email'] ?? '')));
    $phone = trim((string)($prefill['phone'] ?? ''));

    if ($loggedIn) {
        if ($name === '') {
            $name = trim((string)($user['display_name'] ?? $user['full_name'] ?? ''));
        }
        if ($email === '') {
            $email = strtolower(trim((string)($user['email'] ?? '')));
        }
        if ($phone === '') {
            $phone = trim((string)($user['phone'] ?? $user['phone_number'] ?? ''));
        }
    }

    return [
        'logged_in' => $loggedIn,
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
    ];
}

function mg_campaign_render_user_details(array $prefill = []): void
{
    $context = mg_campaign_user_details_context($prefill);
    $loggedIn = (bool)$context['logged_in'];
    ?>
    <details class="mg-campaign-user-details <?= $loggedIn ? 'is-authenticated' : 'is-guest' ?>" data-campaign-user-details data-user-authenticated="<?= $loggedIn ? '1' : '0' ?>"<?= $loggedIn ? '' : ' open' ?>>
      <summary>
        <span class="mg-campaign-user-details-copy">
          <strong><?= $loggedIn ? 'Microgifter account details' : 'Your contact details' ?></strong>
          <small><?= $loggedIn ? 'Saved account details are ready. Open to review or edit.' : 'Required to connect this campaign and reward.' ?></small>
        </span>
        <span class="mg-campaign-user-details-toggle" aria-hidden="true"></span>
      </summary>
      <div class="mg-campaign-user-details-fields">
        <label>Name<input name="name" autocomplete="name" placeholder="Your name" maxlength="180" value="<?= mg_e((string)$context['name']) ?>"></label>
        <label>Email<input name="email" type="email" autocomplete="email" placeholder="you@example.com" required maxlength="255" value="<?= mg_e((string)$context['email']) ?>"></label>
        <label>Phone <span>(optional)</span><input name="phone" type="tel" autocomplete="tel" placeholder="Optional" maxlength="60" value="<?= mg_e((string)$context['phone']) ?>"></label>
      </div>
    </details>
    <?php
}
