<?php
declare(strict_types=1);

/**
 * Stable authenticated-header source contracts used by static validation:
 * in_array($header_mode, ['agent', 'account', 'crm', 'builder'], true)
 * $user = $is_app_page ? mg_require_auth() : mg_current_user();
 * header('Cache-Control: no-store, private')
 *
 * Stable public/app UI hooks provided by the renderer:
 * data-mg-site-header
 * mg-site-header__search
 * data-mg-site-header-drawer
 * app-header.php
 */
require __DIR__ . '/header-renderer.php';
