# Stage API-12 live hardening checklist

- Public API credentials must use the Authorization bearer header.
- Webhook delivery includes a versioned signature header.
- Live webhook endpoints must use HTTPS.
- Live webhook endpoints cannot use literal localhost, private IP, or reserved IP hosts.
- Developer apps can rotate the webhook signing key from the merchant API.
- The full webhook signing key is returned once; app lists expose only a short hint and rotation time.
