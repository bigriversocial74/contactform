<?php
declare(strict_types=1);

/**
 * Creator Campaign merchant builder service entry point.
 *
 * The implementation is divided by responsibility to keep the builder domain
 * reviewable while preserving one stable include contract for callers.
 */
require_once __DIR__ . '/builder-core.php';
require_once __DIR__ . '/builder-options.php';
require_once __DIR__ . '/builder-save.php';
require_once __DIR__ . '/builder-validation.php';
require_once __DIR__ . '/builder-duplicate.php';
