# Customer Store History

## Goal

Give customers a clear history of merchant store visits created by Store Canvas. The page explains where messages and rewards came from, which merchant session created them, and what happened during the visit.

## Added

- `/store-history.php`
- `/api/customer-store/history.php`
- `/assets/js/store-history.js`
- `/assets/css/store-history.css`
- Account sidebar link under Activity
- Static validator: `scripts/validate_customer_store_history.php`

## Data sources

- `mg_store_sessions`
- `mg_store_session_events`
- `mg_customer_store_history`
- `public_profiles`
- `feed_posts`

## Timeline events

The customer timeline displays Store Canvas events such as:

- store entered
- merchant message received
- reward sent / received
- reward claimed
- product viewed
- gift sent
- store exited

## No SQL migration

The feature uses the existing Stage 20 Store Canvas history tables. No new migration is required.
