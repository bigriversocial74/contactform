# World Canvas launch diagnostics

Purpose:
- Make the Target Zone Test Launch button visibly report whether the click handler, API request, delivery run payload, and animation renderer are working.

Behavior:
- Shows a toast as soon as Test Launch is clicked.
- Shows the Target Drop id used for the request.
- Shows a success toast when the delivery run is created.
- Shows a toast when the animation renderer starts the arc.
- Shows API or JavaScript error messages directly in the toast and Target Zone status area.

Why:
- David reported the button looked dead and no launch animation appeared.
- This adds a window-level capture handler so the click cannot be swallowed by older handlers without diagnostics.

SQL:
- No SQL required.
