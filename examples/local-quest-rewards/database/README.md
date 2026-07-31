# Local Quest database order

Fresh installation order:

1. `local_quest_rewards.sql`
2. `local_quest_admin_auth.sql`
3. `local_quest_production_foundation_v1.sql`
4. `local_quest_participant_auth_v1.sql`

Existing installations should import `local_quest_production_foundation_v1.sql` and `local_quest_participant_auth_v1.sql` before using the participant authentication runtime.
