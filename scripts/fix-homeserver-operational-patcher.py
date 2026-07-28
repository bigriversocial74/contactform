#!/usr/bin/env python3
from pathlib import Path

path = Path(__file__).resolve().parent / "apply_homeserver_operational_intelligence_campaigns_v1.py"
value = path.read_text(encoding="utf-8")

old_scopes = '''homeserver = replace_once(
    homeserver,
    """function mg_homeserver_scopes(): array
{
    return ['homeserver.status', 'homeserver.sync.write'];
}
""",
    """function mg_homeserver_scopes(): array
{
    return [
        'homeserver.status',
        'homeserver.sync.write',
        'homeserver.operational.read',
        'homeserver.reviews.read',
        'homeserver.messages.read',
        'homeserver.crm.read',
        'homeserver.commerce_history.read',
        'homeserver.gifts.read',
        'homeserver.campaigns.read',
        'homeserver.campaigns.execute',
    ];
}
""",
    "HomeServer scope set",
)
'''
new_scopes = '''if "homeserver.operational.read" not in homeserver:
    homeserver = replace_once(
        homeserver,
        """function mg_homeserver_scopes(): array
{
    return ['homeserver.status', 'homeserver.sync.write'];
}
""",
        """function mg_homeserver_scopes(): array
{
    return [
        'homeserver.status',
        'homeserver.sync.write',
        'homeserver.operational.read',
        'homeserver.reviews.read',
        'homeserver.messages.read',
        'homeserver.crm.read',
        'homeserver.commerce_history.read',
        'homeserver.gifts.read',
        'homeserver.campaigns.read',
        'homeserver.campaigns.execute',
    ];
}
""",
        "HomeServer scope set",
    )
'''
if old_scopes in value:
    value = value.replace(old_scopes, new_scopes, 1)
elif new_scopes not in value:
    raise SystemExit("HomeServer scope patch block was not found")

old_review = 'operational = replace_once(operational, review_anchor, review_replacement, "customer review source")\n'
new_review = '''if "mg_homeserver_source('customer_reviews'" not in operational:
    operational = replace_once(operational, review_anchor, review_replacement, "customer review source")
'''
if old_review in value:
    value = value.replace(old_review, new_review, 1)
elif new_review not in value:
    raise SystemExit("customer review patch block was not found")

old_event = 'operational = replace_once(operational, envelope_anchor, envelope_replacement, "event envelope")\n'
new_event = '''if "$events = [];" not in operational:
    operational = replace_once(operational, envelope_anchor, envelope_replacement, "event envelope")
'''
if old_event in value:
    value = value.replace(old_event, new_event, 1)
elif new_event not in value:
    raise SystemExit("event envelope patch block was not found")

path.write_text(value, encoding="utf-8", newline="\n")
print("Microgifter operational patcher made idempotent for current provider files.")
