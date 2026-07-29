<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/homeserver-entitlements.php';

$homeServerUser = is_array($user ?? null) ? $user : mg_current_user();
if (!$homeServerUser) return;

$homeServerPdo = mg_db();
$homeServerEntitlement = mg_homeserver_entitlement_context($homeServerPdo, $homeServerUser);
$homeServerAccess = mg_homeserver_entitlement_payload($homeServerPdo, $homeServerUser, $homeServerEntitlement);
$homeServerDevices = [];
try {
    $homeServerDeviceStmt = $homeServerPdo->prepare(
        'SELECT public_id,server_name,version,status,last_seen_at,paired_at,revoked_at
         FROM homeserver_devices WHERE owner_user_id=? ORDER BY status ASC,updated_at DESC,id DESC'
    );
    $homeServerDeviceStmt->execute([(int)$homeServerUser['id']]);
    $homeServerDevices = $homeServerDeviceStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable) {
    $homeServerDevices = [];
}

$homeServerActiveDevices = array_values(array_filter(
    $homeServerDevices,
    static fn(array $device): bool => strtolower((string)($device['status'] ?? '')) === 'active'
));
$homeServerOnlineDevices = array_values(array_filter(
    $homeServerActiveDevices,
    static function (array $device): bool {
        $lastSeen = trim((string)($device['last_seen_at'] ?? ''));
        $timestamp = $lastSeen !== '' ? strtotime($lastSeen . ' UTC') : false;
        return $timestamp !== false && $timestamp >= time() - 600;
    }
));
$homeServerRevokedDevices = array_values(array_filter(
    $homeServerDevices,
    static fn(array $device): bool => strtolower((string)($device['status'] ?? '')) === 'revoked'
));

$homeServerState = (string)($homeServerAccess['state'] ?? 'not_included');
$homeServerStateLabel = 'Not connected';
$homeServerStateClass = 'is-muted';
$homeServerStateDetail = (string)($homeServerAccess['message'] ?? 'Microgifter provider access is not active.');

if ($homeServerState === 'owner_required') {
    $homeServerStateLabel = 'Account owner required';
    $homeServerStateClass = 'is-warning';
} elseif (empty($homeServerAccess['included'])) {
    if ($homeServerState === 'suspended' || $homeServerActiveDevices || $homeServerRevokedDevices) {
        $homeServerStateLabel = 'Microgifter access attention';
        $homeServerStateClass = 'is-blocked';
    }
} elseif ($homeServerOnlineDevices) {
    $homeServerStateLabel = 'Microgifter connected';
    $homeServerStateClass = 'is-online';
    $homeServerStateDetail = count($homeServerOnlineDevices) === 1
        ? 'One HomeServer checked in to Microgifter during the last 10 minutes.'
        : count($homeServerOnlineDevices) . ' HomeServers checked in to Microgifter during the last 10 minutes.';
} elseif ($homeServerActiveDevices) {
    $homeServerStateLabel = 'Offline or stale';
    $homeServerStateClass = 'is-warning';
    $homeServerStateDetail = 'A paired Microgifter provider connection has not checked in during the last 10 minutes.';
} elseif ($homeServerRevokedDevices) {
    $homeServerStateLabel = 'Revoked';
    $homeServerStateClass = 'is-blocked';
    $homeServerStateDetail = 'The saved Microgifter provider connection has been revoked.';
} else {
    $homeServerStateLabel = 'Ready to connect';
    $homeServerStateClass = 'is-ready';
    $homeServerStateDetail = 'Install and license HomeServer through VP3, then connect Microgifter with a one-time Sync Code.';
}

$homeServerDeviceLimit = $homeServerAccess['device_limit'] ?? 0;
$homeServerAllowanceLabel = $homeServerDeviceLimit === null
    ? count($homeServerActiveDevices) . ' active · unlimited Microgifter connections'
    : count($homeServerActiveDevices) . ' of ' . (int)$homeServerDeviceLimit . ' active';
$homeServerUpgradeNotice = isset($_GET['homeserver']) && in_array((string)$_GET['homeserver'], ['upgrade', 'provider-access'], true);
?>
<section class="mg-hs-subscription-card<?= $homeServerUpgradeNotice ? ' is-highlighted' : '' ?>" data-homeserver-subscription-card>
  <div class="mg-hs-subscription-head">
    <div>
      <span class="mg-hs-subscription-kicker">Independent provider connection</span>
      <h3>Microgifter HomeServer Connection</h3>
      <p><?= mg_e($homeServerStateDetail) ?></p>
    </div>
    <span class="mg-hs-subscription-state <?= mg_e($homeServerStateClass) ?>">
      <i aria-hidden="true"></i><?= mg_e($homeServerStateLabel) ?>
    </span>
  </div>

  <div class="mg-hs-subscription-grid">
    <article>
      <span>Microgifter access</span>
      <strong><?= mg_e((string)$homeServerAccess['package_name']) ?></strong>
      <small><?= !empty($homeServerAccess['is_complimentary']) ? 'Complimentary provider access' : mg_e(ucwords(str_replace('_', ' ', (string)$homeServerAccess['subscription_status']))) ?></small>
    </article>
    <article>
      <span>Provider connections</span>
      <strong><?= mg_e($homeServerAllowanceLabel) ?></strong>
      <small><?= $homeServerDeviceLimit === null ? 'No fixed Microgifter connection cap' : mg_e((string)($homeServerAccess['remaining_device_slots'] ?? 0)) . ' remaining' ?></small>
    </article>
    <article>
      <span>Microgifter connection</span>
      <strong><?= mg_e($homeServerStateLabel) ?></strong>
      <small><?= $homeServerOnlineDevices ? 'Last provider check-in is current' : 'Managed from the HomeServer Connections page' ?></small>
    </article>
    <article>
      <span>Software authority</span>
      <strong>VP3</strong>
      <small>License, installer, release channel, and updates</small>
    </article>
  </div>

  <div class="mg-hs-subscription-actions">
    <a class="mg-btn mg-btn-primary" href="https://vp3.me" rel="noopener">Open VP3</a>

    <?php if (!empty($homeServerAccess['can_manage'])): ?>
      <a class="mg-btn mg-btn-soft" href="/account-homeserver.php">Manage Microgifter Connection</a>
      <a class="mg-btn mg-btn-ghost" href="/account-homeserver.php#create-sync-code">Create Sync Code</a>
    <?php else: ?>
      <a class="mg-btn mg-btn-soft" href="/account-subscriptions.php?homeserver=provider-access">Enable Microgifter Provider Access</a>
    <?php endif; ?>
  </div>

  <p class="mg-hs-subscription-boundary">VP3 controls the HomeServer software license, registered device, installer, release channel, and updates. Microgifter separately controls merchant, site, dataset, CRM, campaign, commerce, gifting, and synchronization access.</p>
</section>
