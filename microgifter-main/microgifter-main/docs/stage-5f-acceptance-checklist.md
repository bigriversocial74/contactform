# Stage 5F Acceptance Checklist

- Merchant intelligence queries are scoped by `merchant_user_id`.
- Date-range filters drive all dashboard comparisons.
- Demand, engagement, claim, redemption, and issued-value KPIs are visible.
- PPPM lifecycle funnel is derived from existing item states.
- Product, campaign, and location comparisons are available.
- Existing Stage 4F forecasts, alerts, snapshots, and privacy-safe exports remain authoritative.
- Saved reports require merchant reporting permission and CSRF protection.
- Scheduled-report recipients are stored as keyed hashes, not plaintext email addresses.
- Forecasts are labeled as decision-support signals rather than guaranteed revenue.
- The intelligence page reuses the Stage 5 merchant shell on desktop and mobile.
