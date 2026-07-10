# Participant Quest Experience v1 Notes

The signed QR format is `base64url(payload).nonce.hmac`, bound to one Loyalty Quest and expiration time. The replay ledger makes each generated signed code single-use. Reusable printed QR campaigns use Static QR with a merchant-managed hashed completion code.
