# Stage 896 linked Training Lab controller

This signed pilot-only endpoint is consumed by the linked Training Lab Stage 896 controller.

Deployment order:

1. Deploy this Microgifter endpoint disabled.
2. Deploy the Training Lab pilot controller disabled.
3. Configure the same dedicated Stage 896 issue secret on both servers.
4. Keep the scheduled Training Lab worker disabled.
5. Complete Stage 895 acceptance.
6. Enable one approved Stage 896 pilot.

The Stage 894 read-only lookup uses a separate secret. No SQL is required.
