-- Stage 30 Free Regift Sends
-- Regifting an already-purchased Microgift or promotional reward should not require Stamps.

UPDATE stamp_debit_actions
SET stamp_value = 0,
    description = 'Regifting an already-purchased Microgift or promotional Reward to a new recipient. This send is free.',
    updated_at = NOW()
WHERE action_key = 'regift_send';
