# Stage API-12 — Webhook hardening

Stage API-12 tightens the public distribution API before live developer use.

Public API credentials are now accepted through the Authorization bearer header only. Query-string credential fallback is removed.

Developer apps can rotate the webhook signing key from the merchant Developer API. The full key is returned once during rotation. App lists expose only a short hint and the rotation time.

Webhook deliveries include event, delivery, timestamp, signature, and signature version headers.

Live apps require HTTPS webhook URLs. Live delivery blocks literal localhost, private IP, and reserved IP hosts. Test apps can still use HTTP for local integration work.

No new tables are required. This stage uses the existing developer app columns.
