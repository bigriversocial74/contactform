# Agent Header Tab Behavior

The Agent application shell always renders the system tabs:

- Agent
- Inbox
- Sent
- Claimed

Saved-agent workspaces may add one active custom tab beside the system tabs. The custom tab contains its own delete control in the upper-right corner. Inactive saved agents remain available from the sidebar instead of expanding the header with many tabs.

The add-agent button opens `/agent.php?new=1`, which renders a dedicated unsaved workspace tab. Saving that workspace creates the agent and replaces the temporary tab with the saved-agent tab.

Deleting a saved agent from the active tab removes the agent record while preserving purchase, claim, redemption, and invoice history according to the existing backend policy.

Inbox, Sent, and Claimed never hide when an agent workspace is active.
