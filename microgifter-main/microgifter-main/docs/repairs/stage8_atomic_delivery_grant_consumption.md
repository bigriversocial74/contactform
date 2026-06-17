# Stage 8 Atomic Delivery Grant Consumption

## Defect

The protected download endpoint selected a one-time delivery grant with `FOR UPDATE` but did not start a transaction. The row lock therefore did not protect the following consume operation, and the endpoint did not check whether the conditional update actually consumed the grant. Two concurrent requests could both validate the same grant and both return a successful delivery response.

## Repair

- begin a database transaction before locking the delivery grant
- validate entitlement, asset, expiry, ownership, and token while the row is locked
- conditionally consume the grant and require exactly one affected row
- record successful access inside the same transaction
- roll back on failure and record denied reuse attempts separately

## Scope

No schema, entitlement ownership, PPPM, product asset, or storage-provider changes.
