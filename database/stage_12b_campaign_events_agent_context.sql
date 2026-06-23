-- Stage 12B Campaign Events Agent Context
-- Allows Stage 12 campaign_events to record wallet-only agent activity when no campaign owns the event.

ALTER TABLE campaign_events
  MODIFY campaign_id BIGINT UNSIGNED NULL;

CREATE INDEX idx_campaign_events_merchant_created
  ON campaign_events (merchant_user_id, created_at, id);
