# Logged-out header initial width

The first paint can show the logged-out public header in a narrower layout before the later public cleanup layer finishes applying. The fix is to load a small critical width rule before the public header cleanup layer.
