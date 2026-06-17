# Stage 6B Customer Account Commerce Integration

Stage 6B exposes existing commerce, PPPM, gift, claim, redemption, order, and receipt records through authenticated customer APIs and a unified account page.

## Delivered

- Commerce summary
- Orders and receipts
- Purchased, owned, sent, received, and redeemed PPPM item views
- Sent and received gift views
- Claim and redemption history with status filtering
- Account-menu and checkout-success navigation
- Responsive loading, empty, status, and error states

## APIs

- `/api/account/commerce-summary.php`
- `/api/account/items.php`
- `/api/account/gifts.php`
- `/api/account/claims.php`

## Security

All endpoints require authentication, accept only whitelisted filters, apply bounded result limits, and scope records by buyer, owner, issuer, recipient, claimant, or gift participant. Order IDs are returned only to the matching buyer. The customer frontend does not call merchant or admin APIs.

## Identity boundary

Orders, receipts, gifts, claims, issuance requests, and PPPM items remain separate permanent records. The account center displays their relationships without merging their identifiers.

## Validation

Stage 6B includes PHPUnit contract coverage and uses the consolidated PR Validation workflow. No new stage-specific GitHub Actions workflow is added.
