<?php

// config/hdm.php

return [
    'gateway' => [
        'url' => env('HDM_GATEWAY_URL', 'http://localhost/hdm-gateway/index.php'),
        'token' => env('HDM_GATEWAY_TOKEN', 'SECRET_PROXY_TOKEN'),
    ],
];
