<?php
declare(strict_types=1);

/**
 * Microgifter Delivery Operations & Capacity Foundation v1.
 *
 * Modular facade for the existing notification_delivery_jobs outbox.
 * This layer does not issue Microgifts or mutate Wallet/Inbox/PPPM ownership.
 * Inbox and the in-app notification row remain the durable delivery destination.
 */
require_once dirname(__DIR__) . '/api/communications/_communications.php';
require_once __DIR__ . '/pwa-push.php';
require_once __DIR__ . '/delivery-operations-config.php';
require_once __DIR__ . '/delivery-operations-adapters.php';
require_once __DIR__ . '/delivery-operations-worker.php';
require_once __DIR__ . '/delivery-operations-admin.php';
