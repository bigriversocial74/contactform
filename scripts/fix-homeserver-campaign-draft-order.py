#!/usr/bin/env python3
from pathlib import Path

path = Path("api/homeserver/_operational_intelligence.php")
text = path.read_text(encoding="utf-8")

old_prepare = '''    if ($actionType === 'campaign.draft') {
        $providerResponse = mg_homeserver_campaign_save_draft($pdo, $merchantId, $campaignType, $campaignId, $input);
        $campaignId = (string)$providerResponse['campaign_id'];
        $actualValueCents = max(0, (int)$providerResponse['value_cents']);
    } else {
'''
new_prepare = '''    if ($actionType === 'campaign.draft') {
        $draftRewardPublicId = strtolower(trim((string)($input['reward_template_id'] ?? '')));
        $draftReward = mg_homeserver_campaign_reward($pdo, $merchantId, $draftRewardPublicId);
        $actualValueCents = $draftReward ? max(0, (int)$draftReward['value_amount_cents']) : 0;
    } else {
'''
if old_prepare in text:
    text = text.replace(old_prepare, new_prepare, 1)
elif new_prepare not in text:
    raise SystemExit("campaign draft pre-policy persistence anchor was not found")

request_anchor = '''    $request = $input;
'''
request_replacement = '''    if ($actionType === 'campaign.draft') {
        $providerResponse = mg_homeserver_campaign_save_draft($pdo, $merchantId, $campaignType, $campaignId, $input);
        $campaignId = (string)$providerResponse['campaign_id'];
    }

    $request = $input;
'''
if "$providerResponse = mg_homeserver_campaign_save_draft($pdo, $merchantId, $campaignType, $campaignId, $input);" not in text[text.find("$requiresApproval ="):]:
    if text.count(request_anchor) != 1:
        raise SystemExit("campaign draft post-policy persistence anchor was not found")
    text = text.replace(request_anchor, request_replacement, 1)

path.write_text(text, encoding="utf-8", newline="\n")

validator_path = Path("scripts/validate_homeserver_operational_intelligence_v1.py")
validator = validator_path.read_text(encoding="utf-8")
validation_anchor = '''# Caller-supplied values may be stripped, but may never be accepted as proof or value authority.
'''
validation_code = '''runtime_source = read(RUNTIME)
draft_persist = runtime_source.find("$providerResponse = mg_homeserver_campaign_save_draft($pdo, $merchantId, $campaignType, $campaignId, $input);")
value_limit = runtime_source.find("Campaign value exceeds the per-recipient authorization")
daily_limit = runtime_source.find("merchant-authorized daily value")
total_limit = runtime_source.find("merchant-authorized total value")
if draft_persist < 0 or value_limit < 0 or daily_limit < 0 or total_limit < 0 or not (value_limit < daily_limit < total_limit < draft_persist):
    ERRORS.append("provider campaign drafts are not persisted strictly after value, daily, and total authorization checks")

# Caller-supplied values may be stripped, but may never be accepted as proof or value authority.
'''
if "provider campaign drafts are not persisted strictly after" not in validator:
    if validator.count(validation_anchor) != 1:
        raise SystemExit("campaign draft ordering validator anchor was not found")
    validator = validator.replace(validation_anchor, validation_code, 1)
validator_path.write_text(validator, encoding="utf-8", newline="\n")

print("Provider campaign drafts are now persisted only after all value and budget checks pass.")
