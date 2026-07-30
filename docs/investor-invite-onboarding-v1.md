# Investor Invite and Onboarding v1

## Purpose

A Super Admin can invite a specific prospective investor without manually assigning the Investor role. The recipient creates or links a normal Microgifter account, completes professional information and disclosures, and enters the existing governed Investor Access approval workflow.

## Authority boundaries

An invitation does **not** grant:

- the `investor` role;
- an active `investor_profiles` record;
- Investor Portal or Data Room access;
- selected-round access;
- an allocation, commitment, purchase agreement, or securities transaction.

Only the existing Super Admin approval action creates the Investor role and active Investor profile.

## Administrative flow

1. Open `/admin/investor-invitations.php`.
2. Enter the recipient email and optional name, firm, round context, expected range, and personal message.
3. Choose a 7, 14, 30, or 60-day expiration.
4. Send by configured Microgifter email or copy the returned secure link manually.
5. Monitor Created, Sent, Viewed, Accepted, Expired, and Revoked states.
6. Resending rotates the token and invalidates the previous link.
7. After acceptance, review the resulting request at `/admin/investor-access-requests.php`.

## Recipient flow

1. Open the invitation link.
2. Sign in or create a free account using the exact invited email.
3. Verify the email when the verification gate is enabled.
4. Return automatically to the invitation.
5. Complete professional information and required disclosures.
6. Submit a pending Investor Access request.
7. Await Super Admin approval.

## Security controls

- 256-bit random invitation token.
- Only the SHA-256 token hash is persisted.
- Email hash is compared with `hash_equals` during onboarding.
- Single active invitation per recipient email.
- Expiration and explicit revocation.
- Resend rotates the token.
- Accepted invitations cannot be reused.
- CSRF protection and per-user write rate limits.
- Super Admin-only create, resend, and revoke actions.
- Invitation and access-request audit events.
- No role or portal access is created by invitation acceptance.

## Deployment

Import first:

`database/20260729_investor_invite_onboarding_v1.sql`

Then deploy the merged code while preserving live configuration and runtime storage. The code detects a missing schema and disables invitation creation with an explicit migration message.

## Production smoke test

Use a synthetic email/account:

1. Create an invitation without sending email and copy the secure link.
2. Confirm the page masks the recipient email while signed out.
3. Create the invited account and verify the safe return path.
4. Confirm a mismatched signed-in account cannot accept.
5. Complete onboarding with the matching account.
6. Confirm the invitation becomes Accepted and a Pending access request appears.
7. Approve the request and confirm the third Investor dropdown tab appears.
8. Create a second invitation, resend it, and confirm the old link fails.
9. Revoke the new invitation and confirm its current link fails.
