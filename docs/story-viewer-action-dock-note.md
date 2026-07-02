# Story viewer action dock QA

This scoped fix redesigns story viewer owner actions from stacked full-width buttons into a horizontal circular icon dock.

Expected actions:
- View Product
- Analytics
- Save Highlight
- Promote Story
- Delete

Expected behavior:
- The actions display as circular icon buttons across the bottom of the story viewer.
- Existing click behavior is preserved because the original buttons/anchors are reused.
- Analytics remains visible in the dock.
- The dock works when the story viewer is inserted after page load.
