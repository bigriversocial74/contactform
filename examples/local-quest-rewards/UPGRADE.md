# Upgrade notes

Existing Local Quest installations should import `database/local_quest_production_foundation_v1.sql`, deploy the upgraded application files, verify `storage.driver` is `mysql`, and establish `.installed.lock` after confirming configuration. Do not expose `.install-unlock` or rerun the installer on a live application without a maintenance window and backup.
