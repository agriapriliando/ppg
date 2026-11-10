<?php

return [
    'url' => env('SEO_URL', env('APP_URL', 'http://localhost')),
    'indexable' => (bool) env('SEO_INDEXABLE', env('APP_ENV') === 'production'),
    'redirect_canonical' => (bool) env('SEO_REDIRECT_CANONICAL', env('APP_ENV') === 'production'),
];
