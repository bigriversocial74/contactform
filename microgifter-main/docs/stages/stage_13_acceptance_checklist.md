# Stage 13 acceptance checklist

- recurring plans resolve through existing monetization targets;
- subscribers cannot subscribe to their own plans;
- subscription enrollment is idempotent;
- each renewal cycle and retry attempt is idempotent;
- wallet renewals use Stage 12 tip and Stage 7 ledger posting;
- Stripe renewals use signed payment webhooks;
- failed renewals enter bounded dunning and eventually pause;
- pause, resume, immediate cancel, and period-end cancel are owner-scoped;
- subscription lifecycle events are append-only;
- operational alerts use the existing communications foundation;
- ordered upgrade, clean install, security, PHPUnit, and browser validation pass.
