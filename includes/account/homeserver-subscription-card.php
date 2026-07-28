<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/homeserver-entitlements.php';
require_once dirname(__DIR__) . '/homeserver-releases.php';

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
$homeServerStateLabel = 'Not included';
$homeServerStateClass = 'is-muted';
$homeServerStateDetail = (string)($homeServerAccess['message'] ?? 'HomeServer requires an active package.');

if ($homeServerState === 'owner_required') {
    $homeServerStateLabel = 'Account owner required';
    $homeServerStateClass = 'is-warning';
} elseif (empty($homeServerAccess['included'])) {
    if ($homeServerState === 'suspended' || $homeServerActiveDevices || $homeServerRevokedDevices) {
        $homeServerStateLabel = 'Subscription attention';
        $homeServerStateClass = 'is-blocked';
    }
} elseif ($homeServerOnlineDevices) {
    $homeServerStateLabel = 'Connected';
    $homeServerStateClass = 'is-online';
    $homeServerStateDetail = count($homeServerOnlineDevices) === 1
        ? 'One HomeServer checked in during the last 10 minutes.'
        : count($homeServerOnlineDevices) . ' HomeServers checked in during the last 10 minutes.';
} elseif ($homeServerActiveDevices) {
    $homeServerStateLabel = 'Offline or stale';
    $homeServerStateClass = 'is-warning';
    $homeServerStateDetail = 'A paired HomeServer has not checked in during the last 10 minutes.';
} elseif ($homeServerRevokedDevices) {
    $homeServerStateLabel = 'Revoked';
    $homeServerStateClass = 'is-blocked';
    $homeServerStateDetail = 'The saved HomeServer connection has been revoked.';
} else {
    $homeServerStateLabel = 'Ready to install';
    $homeServerStateClass = 'is-ready';
    $homeServerStateDetail = 'Download HomeServer, install it on Windows, and connect it with a one-time Sync Code.';
}

$homeServerLatestRelease = null;
if (!empty($homeServerAccess['can_download']) && mg_homeserver_release_schema_ready($homeServerPdo)) {
    try {
        $releaseRow = mg_homeserver_release_latest($homeServerPdo);
        if ($releaseRow) $homeServerLatestRelease = mg_homeserver_release_row_payload($releaseRow);
    } catch (Throwable) {
        $homeServerLatestRelease = null;
    }
}

$homeServerDeviceLimit = $homeServerAccess['device_limit'] ?? 0;
$homeServerAllowanceLabel = $homeServerDeviceLimit === null
    ? count($homeServerActiveDevices) . ' active · unlimited allowance'
    : count($homeServerActiveDevices) . ' of ' . (int)$homeServerDeviceLimit . ' active';
$homeServerInstallerLabel = $homeServerLatestRelease
    ? 'v' . (string)$homeServerLatestRelease['version'] . ' · Windows ' . strtoupper((string)$homeServerLatestRelease['architecture'])
    : (!empty($homeServerAccess['can_download']) ? 'No release published' : 'Upgrade required');
$homeServerUpgradeNotice = isset($_GET['homeserver']) && (string)$_GET['homeserver'] === 'upgrade';
?>
<section class="mg-hs-subscription-card<?= $homeServerUpgradeNotice ? ' is-highlighted' : '' ?>" data-homeserver-subscription-card>
  <div class="mg-hs-subscription-head">
    <div>
      <span class="mg-hs-subscription-kicker">Private local infrastructure</span>
      <h3>HomeServer Management</h3>
      <p><?= mg_e($homeServerStateDetail) ?></p>
    </div>
    <span class="mg-hs-subscription-state <?= mg_e($homeServerStateClass) ?>">
      <i aria-hidden="true"></i><?= mg_e($homeServerStateLabel) ?>
    </span>
  </div>

  <div class="mg-hs-subscription-grid">
    <article>
      <span>Package access</span>
      <strong><?= mg_e((string)$homeServerAccess['package_name']) ?></strong>
      <small><?= !empty($homeServerAccess['is_complimentary']) ? 'Complimentary entitlement' : mg_e(ucwords(str_replace('_', ' ', (string)$homeServerAccess['subscription_status']))) ?></small>
    </article>
    <article>
      <span>Device allowance</span>
      <strong><?= mg_e($homeServerAllowanceLabel) ?></strong>
      <small><?= $homeServerDeviceLimit === null ? 'No fixed device cap' : mg_e((string)($homeServerAccess['remaining_device_slots'] ?? 0)) . ' remaining' ?></small>
    </article>
    <article>
      <span>Cloud connection</span>
      <strong><?= mg_e($homeServerStateLabel) ?></strong>
      <small><?= $homeServerOnlineDevices ? 'Last check-in is current' : 'Managed from the HomeServer page' ?></small>
    </article>
    <article>
      <span>Windows installer</span>
      <strong><?= mg_e($homeServerInstallerLabel) ?></strong>
      <small><?= $homeServerLatestRelease ? 'Stable channel' : 'Published by Microgifter administration' ?></small>
    </article>
  </div>

  <div class="mg-hs-subscription-actions">
    <?php if (!empty($homeServerAccess['can_download']) && $homeServerLatestRelease): ?>
      <a class="mg-btn mg-btn-primary" href="<?= mg_e((string)$homeServerLatestRelease['download_url']) ?>">Download HomeServer</a>
    <?php elseif (!empty($homeServerAccess['can_download'])): ?>
      <span class="mg-hs-subscription-disabled">Installer not published yet</span>
    <?php endif; ?>

    <?php if (!empty($homeServerAccess['can_manage'])): ?>
      <a class="mg-btn mg-btn-soft" href="/account-homeserver.php">Open HomeServer</a>
      <a class="mg-btn mg-btn-ghost" href="/account-homeserver.php#create-sync-code">Create Sync Code</a>
    <?php else: ?>
      <a class="mg-btn mg-btn-primary" href="/account-subscriptions.php?homeserver=upgrade">Upgrade to enable HomeServer</a>
    <?php endif; ?>
  </div>

  <p class="mg-hs-subscription-boundary">Subscription changes do not erase local HomeServer data. Cloud pairing, synchronization, and paid feature access remain separately controlled.</p>
</section>
