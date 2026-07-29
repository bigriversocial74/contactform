# VP3 HomeServer Software Authority Separation v1

## Authority boundary

VP3 owns the HomeServer software license, registered device, activation and entitlement lease, installer authorization, release channels, signed update manifests, update eligibility, suspension, revocation, replacement, and transfer.

Microgifter remains an independent HomeServer provider connection. It owns merchant and site assignments, dataset grants, CRM and campaign permissions, commerce and gifting synchronization, operational synchronization and receipts, and Microgifter-specific pairing credentials.

## Compatibility behavior

- `/account-homeserver.php` remains the canonical Microgifter provider-pairing page.
- One-time Microgifter Sync Codes remain valid only for authorizing the Microgifter connection.
- Microgifter entitlement leases no longer grant installer or software-update capabilities.
- Microgifter update authorization and receipt routes return an explicit VP3 delegation boundary for older clients.
- Installer metadata and downloads are no longer served by Microgifter.
- Existing merchant, site, dataset, campaign, synchronization, and operational-receipt authorities are unchanged.
- The Microgifter account status interface directs software-license and installer actions to VP3 while retaining Microgifter connection management locally.

## Deployment boundary

This change must be coordinated with the HomeServer VP3 software-authority client and the VP3 public activation, entitlement, heartbeat, manifest, and installer endpoints. Do not deploy the authority cutover until all three sides are certified.

No production secret, signing key, password, API token, or customer data belongs in this repository change.
