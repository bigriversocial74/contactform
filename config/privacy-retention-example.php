<?php
/**
 * Privacy retention and account-erasure local configuration example.
 *
 * Prefer setting MG_PRIVACY_HASH_KEY through the hosting environment. The value
 * must be a stable, randomly generated secret and must not be committed.
 *
 * Example generation:
 *   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
 */

// putenv('MG_PRIVACY_HASH_KEY=PASTE_A_RANDOM_64_CHARACTER_HEX_SECRET_HERE');
