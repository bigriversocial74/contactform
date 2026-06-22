# Webhook verification examples

Microgifter signs webhook deliveries with HMAC-SHA256.

## Headers

```http
X-Microgifter-Event: reward.delivered
X-Microgifter-Delivery: <delivery-id>
X-Microgifter-Timestamp: <unix-timestamp>
X-Microgifter-Signature: sha256=<digest>
X-Microgifter-Signature-Version: v1
```

## Signature base string

```text
<timestamp>.<raw request body>
```

## PHP verification

```php
function verify_microgifter_webhook(string $rawBody, array $headers, string $secret): bool
{
    $timestamp = $headers['X-Microgifter-Timestamp'] ?? '';
    $signature = $headers['X-Microgifter-Signature'] ?? '';
    if ($timestamp === '' || $signature === '') return false;
    if (abs(time() - (int)$timestamp) > 300) return false;
    $expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
    return hash_equals($expected, $signature);
}
```

## Node.js verification

```js
import crypto from 'crypto';

export function verifyMicrogifterWebhook(rawBody, headers, secret) {
  const timestamp = headers['x-microgifter-timestamp'];
  const signature = headers['x-microgifter-signature'];
  if (!timestamp || !signature) return false;
  if (Math.abs(Date.now() / 1000 - Number(timestamp)) > 300) return false;
  const expected = 'sha256=' + crypto
    .createHmac('sha256', secret)
    .update(`${timestamp}.${rawBody}`)
    .digest('hex');
  return crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(signature));
}
```

## Handler rules

- Verify the signature before parsing or trusting the payload.
- Reject timestamps older than five minutes.
- Treat duplicate delivery IDs as safe replays and return 200 after confirming prior processing.
- Log `X-Microgifter-Delivery` for support.
