# Microgifter YC Pitch Deck

Branch: `microgifter-yc-pitch-deck`

This branch contains the first source build system for the 13-slide Microgifter YC pitch deck.

Design direction:

- 16:9 black-and-white pitch deck
- consistent Microgifter M gift icon on every slide
- subtle mesh-grid terrain background
- flat rendered slide images placed into a PPTX for locked styling
- BAT / Bottom-Up TAM slide includes the centered concentric-circle market-share diagram

Generated local artifact from this build:

- `microgifter_yc_pitch_deck_flat.pptx`

Workflow:

1. Generate slide PNGs from the Python source.
2. Package the PNGs as full-slide images in a PPTX.
3. Revise slide copy/layout in the source script, then regenerate.
