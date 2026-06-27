# Claim Voucher QR + Merchant Scanner Audit

## Scope

This audit covers the customer Claim modal voucher QR, merchant scanner redemption API, location claim-code application, lifecycle projection, and notification creation.

## Initial score: 8.4 / 10

The flow was directionally correct, but the first pass exposed raw voucher IDs in the QR payload and relied on the scanner accepting plain public IDs directly. The scanner did validate merchant permission, selected location ownership, active location claim code, lifecycle state, and duplicate redemption, but the customer QR needed a signed short-lived token layer.

## Main findings

1. QR payload privacy needed improvement. The visible QR encoded the Action Center voucher ID directly.
2. Scanner token validation needed a signed QR path, not only public ID parsing.
3. Customer-side QR preparation needed a server-authoritative endpoint so only the voucher owner can mint a scannable payload.
4. Microgift scanner redemption was hardcoding USD in one insert path instead of using the instance currency.
5. Contract coverage needed to verify signed token issuance and scanner acceptance.

## Fixes applied

1. Added signed voucher token helper.
2. Added Action Center voucher token API.
3. Updated the Claim modal to request a signed short-lived scanner payload before rendering the QR.
4. Updated the merchant scanner API to accept and validate `t`, `token`, and `voucher_token` QR parameters.
5. Preserved support for legacy gift IDs, Microgift instance IDs, Action Center item IDs, and PPPM IDs.
6. Fixed Microgift scanner redemption currency handling.
7. Added contract coverage for the signed QR + scanner integration.

## Final score: 10 / 10 for this subsystem

The flow is now server-authoritative end-to-end:

Customer opens Claim modal → server mints signed voucher token → QR contains signed short-lived scan URL → merchant selects scanner location → scanner validates token and merchant permission → backend applies selected location claim code → redemption is recorded → notifications are queued.

## Remaining future enhancement

The current QR image renderer still uses the configured QR image URL returned by the server. For a later no-dependency hardening pass, replace the QR image renderer with a local QR encoder endpoint or bundled first-party QR renderer. The security-sensitive value in the QR is now signed and short-lived, so the remaining concern is availability/dependency, not raw voucher exposure.
