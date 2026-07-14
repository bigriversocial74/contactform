# Reward Drop deployment checklist

1. Import `database/reward_drop_game_v1.sql`.
2. Deploy the latest `integration-from-repair-20260628` code.
3. Configure the environment values documented in `README.md`.
4. In Developer API, use an active live app and active live credential.
5. Grant `distribution:rewards.issue` and `distribution:rewards.status`.
6. Add `https://microgifter.com` to allowed origins.
7. Set the webhook URL to `https://microgifter.com/games/reward-drop/webhook.php`.
8. Rotate the webhook signing value and place the shown value in `MG_REWARD_DROP_WEBHOOK_SECRET`.
9. Create or activate a gaming Distribution Program with the configured reward template attached.
10. Open `/merchant-distribution.php#distribution-game` and confirm all readiness checks show Ready.
11. Test sign-in recognition, one-time Inbox connection, game completion, reward queueing, webhook confirmation, and Inbox delivery.
