# Wallet → Microgift → PPPM → Inbox Authority

`wallet_items` is an internal campaign-reward staging table. It is not a customer-facing ownership system.

The authoritative delivery path is:

1. Create or identify the internal `wallet_items` staging record.
2. Create one merchant-scoped PPPM reward source and idempotent source event.
3. Create one earned-reward PPPM issuance request and delivered PPPM item.
4. Create or reuse one published merchant Microgift reward template/version.
5. Create one delivered Microgift instance linked to the PPPM item.
6. Project the Microgift to the merchant Sent folder and recipient Inbox.
7. Store the PPPM link on the wallet staging record.
8. Run all customer claim, regift, redemption, messaging, and history through the Action Center/PPPM lifecycle.

The standalone Reward Wallet, wallet claim tokens, wallet support cases, and wallet-specific merchant redemption path are retired compatibility surfaces.
