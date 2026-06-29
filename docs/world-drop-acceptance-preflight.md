# Target Drop acceptance preflight mock

Purpose:
- Mock the future stamp-funded Target Zone delivery flow from the Test Launch button.

Flow:
1. Merchant clicks Test Launch.
2. A preflight modal estimates people inside the Target Zone, reachable inboxes, accepted users, required stamps, and stamp fees.
3. Mock users inside the zone receive a drop notification and accept or decline.
4. Analytics update with accepted users and accept rate.
5. Reward quantity must match the accepted-user count before delivery can proceed.
6. Merchant clicks to match reward quantity if the current reward quantity is short.
7. Merchant funds stamps and runs the normal 30-second Test Launch animation.

Rules:
- Estimate is simulated for now.
- The mock only delivers to users who accepted the notification.
- The number of rewards included must cover the accepted users.
- The stamp count equals the accepted delivery count.
- No real inbox delivery rows are written yet.

SQL:
- No SQL required.
