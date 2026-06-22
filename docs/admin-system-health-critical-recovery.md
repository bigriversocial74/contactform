# Admin system health critical recovery

This recovery patch addresses the System Health critical state shown by production after the public API stages.

## Migration health

Production has a consolidated release trigger migration key:

- `stage_v1_release_trigger_portability`

That key represents the deployed release bundle through the current manifest. The canonical migration manifest now marks it as a coverage marker for the latest release trigger file. This lets System Health show migrations as ready when the production release trigger is present, instead of flagging every bundled migration as missing.

## Admin queue endpoints

Two admin queues were creating repeated warning entries when optional operating tables were not present yet:

- Commerce operations queue
- Content review queue

The recovery patch makes both queues return empty setup-required payloads instead of logging repeated server failures when their backing tables are absent.

This prevents the System Health recent warning list from being filled by avoidable queue failures while keeping real permission and runtime issues visible.
