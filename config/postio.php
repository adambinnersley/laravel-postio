<?php

return [
    // Postio API key (pk_...). Empty disables the lookup: isConfigured() is false and
    // the bundled controller answers 503 so forms fall back to manual entry.
    'key' => env('POSTIO_API_KEY', ''),

    // Postio bills every postcode searched. Results are kept in this cache store so
    // picking an address from the list, and searching the same postcode again, are
    // free. Null uses the default store; use a store every web server shares.
    'cache_store' => env('POSTIO_CACHE_STORE'),
    'cache_ttl' => (int) env('POSTIO_CACHE_TTL', 2592000),

    // Seconds per attempt, and extra attempts after a 429/5xx/connection failure.
    'timeout' => (float) env('POSTIO_TIMEOUT', 4),
    'retries' => (int) env('POSTIO_RETRIES', 2),

    // Address lines the forms have room for; longer addresses are folded to fit.
    'max_lines' => (int) env('POSTIO_MAX_LINES', 3),
];
