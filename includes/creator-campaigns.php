<?php
declare(strict_types=1);

/**
 * Native Creator Campaign foundation entry point.
 *
 * No HTTP route is registered by this file. Later phases may call these
 * services after the standard API bootstrap has authenticated the request.
 */
require_once __DIR__ . '/user_models.php';
require_once __DIR__ . '/user_model_workflows.php';
require_once __DIR__ . '/package-entitlements.php';
require_once __DIR__ . '/creator-campaigns/bootstrap.php';
