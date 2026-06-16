# Stage 10F — Canonical Action Center State Model

## Product truth

The customer Action Center has exactly three primary folders:

- **INBOX** — gifts currently owned by the user and available to keep, claim, redeem, or send.
- **SENT** — gifts transferred or delivered by the user to another recipient and still visible to the sender.
- **CLAIMED** — gifts for which a merchant successfully verified the location claim code and completed redemption.

## Lifecycle meaning

A user may purchase a gift or receive a free/promotional gift. The current owner may:

1. keep it in INBOX and redeem it personally;
2. send it to another user, which creates a SENT representation for the sender and an INBOX representation for the recipient;
3. present it to an authorized merchant location for code verification and redemption;
4. after successful merchant redemption, view it in CLAIMED.

## Canonical domain states

Domain lifecycle states remain more detailed than the three user-facing folders:

| Domain state | Action Center folder | Meaning |
|---|---|---|
| `received` | INBOX | Delivered or acquired by the current owner. |
| `claimable` | INBOX | Legacy alias for a gift available for redemption. |
| `redeemable` | INBOX | Current owner may redeem or send the gift. |
| `sent` / ownership transferred | SENT for sender; INBOX for recipient | Sender retains history; recipient becomes current owner. |
| `claimed` | transitional compatibility state | Ownership/acceptance may be recorded, but merchant redemption is not complete. Do not expose as a final folder. |
| `redeemed` | CLAIMED | Merchant code was verified and redemption committed. |
| `expired` | archived status within the originating folder | Gift can no longer be acted upon. |
| `revoked` | archived status within the originating folder | Gift was administratively disabled. |

## Important rules

- Folder is a user-facing read-model classification, not financial or ownership authority.
- PPPM remains canonical issued-unit ownership authority.
- Microgift instances remain canonical gift lifecycle authority.
- Merchant-location redemption is the only event that moves a gift to CLAIMED.
- Sending never marks a gift claimed or redeemed.
- A SENT gift remains redeemable by its recipient until redeemed, expired, revoked, refunded, or transferred again under policy.
- The sender's SENT record is historical and cannot be used to redeem after ownership transfer.
- A gift may have multiple user-facing read-model rows across its history, but only one current owner.

## Stage 11 contract

Stage 11 must build Action Center APIs from canonical Microgift, PPPM, delivery, transfer, claim, and redemption records. It must not introduce another ownership, redemption, or payment engine.
