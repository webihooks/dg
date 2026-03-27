<?php
// borzo/config/borzo.php

return [
    'environment' => 'test', // Change to 'production' when live
    
    'api' => [
        'test' => [
            'url' => 'https://robotapitest-in.borzodelivery.com/api/business/1.6',
            'token' => '6CDDE6B80E8DAF4E99B05DEA3F0E80E812E9DF54'
        ],
        'production' => [
            'url' => 'https://robot-in.borzodelivery.com/api/business/1.6',
            'token' => '' // Fill when you get production token
        ]
    ],
    
    'webhook' => [
        'secret' => '8ab431d2b7d996929556458816506a13d78406aab4b4f3dea5eb0c1a291152ee',
        'url' => 'https://deegeecard.com/borzo/webhook/index.php'
    ],
    
    'store' => [
        'pickup_address' => 'Saket, New Delhi, Delhi, India', // Default fallback
        'phone' => '918880000001',
        'name' => 'DeeGeeCard Store'
    ],
    
    'options' => [
        'default_vehicle_type' => 8,
        'max_points' => 99,
        'enable_notifications' => true,
        'enable_cod' => true,
        'cache_delivery_rates' => true,
        'cache_expiry' => 3600
    ],
    
    'logging' => [
        'api_log' => __DIR__ . '/../logs/borzo-api.log',
        'webhook_log' => __DIR__ . '/../logs/borzo-webhook.log',
        'error_log' => __DIR__ . '/../logs/borzo-errors.log'
    ]
];