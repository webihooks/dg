<?php
// /borzo/api/test-auth.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

$token = '6CDDE6B80E8DAF4E99B05DEA3F0E80E812E9DF54';
$base_url = 'https://robotapitest-in.borzodelivery.com/api/business/1.6';

$payload = [
    'external_order_id' => 'TEST_' . time(),
    'route' => [
        'pickup' => [
            'address' => 'Saket, New Delhi, Delhi, India',
            'contact_person' => [
                'name' => 'Test Restaurant',
                'phone' => '918880000001'
            ]
        ],
        'dropoff' => [
            'address' => 'Connaught Place, New Delhi, Delhi, India',
            'contact_person' => [
                'name' => 'Test Customer',
                'phone' => '919876543210'
            ]
        ]
    ],
    'items' => [
        [
            'name' => 'Test Item',
            'quantity' => 1,
            'weight' => 200,
            'cost' => 100
        ]
    ],
    'payment_method' => 'cash',
    'cod_amount' => 100,
    'vehicle_type_id' => 8
];

$methods = [
    'token_in_url' => [
        'url' => $base_url . '/orders/create?token=' . $token,
        'headers' => ['Content-Type: application/json']
    ],
    'x_api_key_header' => [
        'url' => $base_url . '/orders/create',
        'headers' => [
            'Content-Type: application/json',
            'X-API-Key: ' . $token
        ]
    ],
    'bearer_header' => [
        'url' => $base_url . '/orders/create',
        'headers' => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ]
    ],
    'token_header' => [
        'url' => $base_url . '/orders/create',
        'headers' => [
            'Content-Type: application/json',
            'Authorization: Token ' . $token
        ]
    ],
    'api_key_in_body' => [
        'url' => $base_url . '/orders/create',
        'headers' => ['Content-Type: application/json'],
        'body_extra' => ['api_key' => $token]
    ]
];

$results = [];

foreach ($methods as $name => $method) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $method['url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $method['headers']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $body = $payload;
    if (isset($method['body_extra'])) {
        $body = array_merge($payload, $method['body_extra']);
    }
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    $results[$name] = [
        'http_code' => $http_code,
        'response' => json_decode($response, true),
        'error' => $error
    ];
}

echo json_encode($results, JSON_PRETTY_PRINT);
?>