# Stage 13 operational runbook

Run due renewals with:

```bash
php scripts/process_subscriptions.php 100
```

The processor locks each due subscription, creates one cycle-scoped renewal attempt, and delegates payment to the canonical Stage 12 tip authority. Repeated runs are safe because both the subscription attempt and resulting tip are idempotent.

Monitor `past_due` subscriptions, failed attempts, and paused subscriptions. Stripe settlement is completed by `POST /api/subscriptions/payment-webhook.php?provider=stripe` using the existing payment webhook signature secret.
