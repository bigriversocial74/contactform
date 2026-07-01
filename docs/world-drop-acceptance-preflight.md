# Target Drop send-first acceptance mock

Purpose:
- Mock the future stamp-funded Target Zone delivery flow from the Test Launch button for audio/media pack drops.

Flow:
1. Merchant clicks Test Launch.
2. The preflight modal estimates people inside the Target Zone, reachable inboxes, projected accepts, recommended reserved rewards, required stamps, and stamp fees.
3. The system queries the attached campaign and reward quantity through `api/world-canvas/drop-campaign-inventory.php`.
4. If the attached campaign/reward quantity is too low, the merchant sees a warning and cannot send until more rewards are set aside.
5. If quantity is sufficient, the merchant gets one action button: `Agree to pay $X for stamps and send`.
6. The campaign is sent before users accept it.
7. The normal 30-second Test Launch animation runs.
8. Interaction tracking starts after the campaign lands.
9. Mock users in the Target Zone receive the post-landing notification and accept or decline the dropped media pack.
10. Analytics update with accepts, declines, response progress, and accept rate.

Rules:
- Estimate is simulated for now.
- Acceptance is uncertain and happens after delivery/landing.
- Recommended reward reserve is based on projected accepts plus a small buffer.
- The available quantity is checked from the attached campaign's `quantity_limit` when available.
- The merchant must have enough rewards set aside before paying stamps and sending.
- No real inbox delivery rows are written yet.

SQL:
- No SQL required.
