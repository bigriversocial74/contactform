# Stage 5I Paid Order to PPPM Issuance

Successful checkout payment capture now triggers the existing PPPM issuance pipeline.

## Flow

1. Checkout creates a commerce order and immutable order lines.
2. Payment confirmation marks the order paid.
3. The financial ledger records processor clearing and merchant payable entries.
4. Each paid order line creates one PPPM issuance request.
5. Each purchased unit creates one permanent PPPM item.
6. Commerce order lines store the linked PPPM issuance request.
7. Order fulfillment state updates to issued or partial.
8. Buyer and merchant notifications are created.

Payment, order, order-line, issuance-request, PPPM-item, claim, and redemption identifiers remain separate.
