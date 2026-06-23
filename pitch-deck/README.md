# Microgifter YC Pitch Deck

Branch: `microgifter-yc-pitch-deck`

This branch contains the source/build notes for the Microgifter YC pitch deck.

Current working version:

- `v6_team_added`
- 15 slides total
- Adds two new team/founder-market-fit slides after the v5 baseline

Design direction:

- 16:9 black-and-white pitch deck
- consistent Microgifter M gift icon on every slide
- left-side text styling locked to the accepted reference slides
- right-side display/image area uses the corresponding generated visual per slide
- subtle mesh-grid terrain background
- flat rendered slide images placed into a PPTX for locked styling
- BAT / Bottom-Up TAM slide includes the centered concentric-circle market-share diagram

New slides added:

14. Why This Team
15. Founder-Market Fit

Generated local artifacts from this build:

- `microgifter_yc_pitch_deck_v6_team_added.pptx`
- `microgifter_yc_pitch_deck_v6_team_added_assets.zip`
- `microgifter_yc_pitch_deck_v6_team_added_contact_sheet.jpg`

Workflow:

1. Generate slide PNGs from the source layout.
2. Package the PNGs as full-slide images in a PPTX.
3. Revise slide copy/layout in the source script, then regenerate.
