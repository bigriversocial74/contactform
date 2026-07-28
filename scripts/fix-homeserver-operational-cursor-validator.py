#!/usr/bin/env python3
from pathlib import Path

path = Path("scripts/validate_homeserver_operational_intelligence_v1.py")
text = path.read_text(encoding="utf-8")
old = '''    "mg_homeserver_operational_cursor_decode",
    "mg_homeserver_operational_filter_row",
    "provider_authoritative",
'''
new = '''    "mg_homeserver_operational_cursor_decode",
    "mg_homeserver_operational_source_cursor",
    "mg_homeserver_operational_cursor_encode",
    "'version' => 2",
    "'sources' => $sources",
    "$sourceKey = $source['table']",
    "$rawRow",
    "$cursorSources[$sourceKey]",
    "mg_homeserver_operational_filter_row",
    "provider_authoritative",
'''
if old in text:
    text = text.replace(old, new, 1)
elif new not in text:
    raise SystemExit("operational cursor validator marker anchor was not found")
path.write_text(text, encoding="utf-8", newline="\n")
print("Permanent provider validator now requires source-aware opaque cursors.")
