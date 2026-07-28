#!/usr/bin/env python3
from pathlib import Path

path = Path(__file__).resolve().parent / "apply_homeserver_operational_intelligence_campaigns_v1.py"
value = path.read_text(encoding="utf-8")
old = '''homeserver = replace_once(
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
new = '''if "homeserver.operational.read" not in homeserver:
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
if old in value:
    value = value.replace(old, new, 1)
elif new not in value:
    raise SystemExit("HomeServer scope patch block was not found")
path.write_text(value, encoding="utf-8", newline="\n")
print("Microgifter operational patcher made idempotent for current HomeServer scopes.")
