# Merchant Agent Notification + Result Digest QA

## Purpose
Verify that agent recommendations, review decisions, execution results, and failures surface as merchant notifications without new SQL.

## Setup
- Stage 19C tables are imported.
- PRs for agent chat, review bridge, hardening, and result layer are merged.
- Merchant account has merchant access and AI permissions.

## Test 1: Sidebar badges
1. Open `/merchant.php` as a merchant.
2. Confirm sidebar badges load on merchant agent links when counts are non-zero.
3. Create or approve an agent item.
4. Refresh the page.
5. Confirm the badge count updates.

## Test 2: Merchant dashboard digest
1. Open `/merchant.php`.
2. Confirm the Agent Activity widget appears below the KPI row.
3. Confirm it shows counts for pending reviews, results, failed executions, and unread notifications.
4. Click a pending review item and confirm it opens `/merchant-agent-approvals.php`.
5. Click a completed result item and confirm it opens the created resource or `/merchant-agent-execution.php`.

## Test 3: Notification center digest
1. Open `/merchant-notifications.php`.
2. Confirm Agent Result Digest appears above the customer notification feed.
3. Filter by Pending, Results, Retry, and Unread.
4. Confirm each filter returns the expected agent items.

## Test 4: Mark read / archive
1. In an agent digest card, click Mark read.
2. Confirm the item no longer counts as unread.
3. Click Archive.
4. Confirm the item is removed from standard digest filters.
5. Confirm archived items remain available through the `archived` API filter.

## Test 5: Full workflow
1. Open `/merchant-agent-chat.php`.
2. Generate a recommendation card.
3. Send it to review.
4. Confirm a pending digest item appears.
5. Approve the review item.
6. Confirm a completed result digest item appears.
7. Open the result from the digest.

## Pass criteria
- No new SQL required.
- Agent notifications are built from existing `ai_merchant_plan_items` and `campaign_events`.
- Read/archive states are stored as `campaign_events`.
- Badges update on merchant sidebars.
- Merchant dashboard and notification center both show agent digest cards.
