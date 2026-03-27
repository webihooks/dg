<?php
// borzo-test.php - Run this file to test Borzo API

// ============================================
// YOUR API TOKEN - COPY THIS EXACTLY
// ============================================
$testToken = '6CDDE6B80E8DAF4E99B05DEA3F0E80E812E9DF54';
$apiUrl = 'https://robotapitest-in.borzodelivery.com/api/business/1.6';

// ============================================
// TEST DATA - Using Borzo's recommended addresses
// ============================================
$testAddresses = [
    'pickup' => 'Saket, New Delhi, Delhi',
    'delivery1' => 'Janakpuri, New Delhi, Delhi',
    'delivery2' => 'Connaught Place, New Delhi, Delhi'
];
$testPhone = '918880000001'; // Borzo test phone

// ============================================
// FUNCTION TO MAKE API CALLS
// ============================================
function callBorzoAPI($endpoint, $data, $token, $url) {
    $ch = curl_init();
    
    $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    curl_setopt($ch, CURLOPT_URL, $url . $endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-DV-Auth-Token: ' . $token,
        'Content-Type: application/json',
        'Content-Length: ' . strlen($jsonData)
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'http_code' => $httpCode,
        'response' => json_decode($result, true),
        'raw_response' => $result,
        'error' => $error
    ];
}

// ============================================
// START TESTING
// ============================================
echo "========================================\n";
echo "BORZO API TEST SCRIPT\n";
echo "========================================\n";
echo "Token: " . substr($testToken, 0, 10) . "...\n";
echo "URL: $apiUrl\n";
echo "========================================\n\n";

// TEST 1: Simple connection test
echo "🔍 TEST 1: Testing API connection...\n";
$testConnection = callBorzoAPI('', [], $testToken, $apiUrl);
if ($testConnection['http_code'] == 200) {
    echo "✅ API connection successful!\n\n";
} else {
    echo "❌ API connection failed. HTTP Code: " . $testConnection['http_code'] . "\n";
    if ($testConnection['error']) echo "Error: " . $testConnection['error'] . "\n";
    exit(1);
}

// TEST 2: Calculate order price
echo "💰 TEST 2: Calculating delivery price...\n";
$calculateData = [
    'matter' => 'Greeting Cards',
    'points' => [
        [
            'address' => $testAddresses['pickup'],
            'contact_person' => ['phone' => $testPhone]
        ],
        [
            'address' => $testAddresses['delivery1'],
            'contact_person' => ['phone' => $testPhone]
        ]
    ]
];

$result = callBorzoAPI('/calculate-order', $calculateData, $testToken, $apiUrl);

if ($result['http_code'] == 200 && isset($result['response']['is_successful']) && $result['response']['is_successful']) {
    echo "✅ Price calculated!\n";
    echo "   Delivery fee: ₹" . $result['response']['order']['delivery_fee_amount'] . "\n";
    echo "   Total: ₹" . $result['response']['order']['payment_amount'] . "\n";
    if (!empty($result['response']['warnings'])) {
        echo "   Warnings: " . implode(', ', $result['response']['warnings']) . "\n";
    }
    echo "\n";
} else {
    echo "❌ Calculation failed\n";
    echo "HTTP Code: " . $result['http_code'] . "\n";
    echo "Response: " . print_r($result['response'], true) . "\n\n";
}

// TEST 3: Create first order
echo "📦 TEST 3: Creating first test order...\n";
$order1Data = [
    'matter' => 'Greeting Cards - Test Order 1',
    'points' => [
        [
            'address' => $testAddresses['pickup'],
            'contact_person' => [
                'phone' => $testPhone,
                'name' => 'Store Person'
            ]
        ],
        [
            'address' => $testAddresses['delivery1'],
            'contact_person' => [
                'phone' => $testPhone,
                'name' => 'Customer 1'
            ],
            'note' => 'Please call before delivery'
        ]
    ]
];

$result = callBorzoAPI('/create-order', $order1Data, $testToken, $apiUrl);

if ($result['http_code'] == 200 && isset($result['response']['is_successful']) && $result['response']['is_successful']) {
    $order1Id = $result['response']['order']['order_id'];
    echo "✅ FIRST ORDER CREATED!\n";
    echo "   Order ID: " . $order1Id . "\n";
    echo "   Order Name: " . $result['response']['order']['order_name'] . "\n";
    echo "   Status: " . $result['response']['order']['status'] . "\n";
    echo "   Tracking: You can view this order in your Borzo dashboard\n\n";
} else {
    echo "❌ First order creation failed\n";
    if (isset($result['response']['errors'])) {
        echo "Errors: " . implode(', ', $result['response']['errors']) . "\n";
    }
    if (isset($result['response']['parameter_errors'])) {
        echo "Parameter errors: " . print_r($result['response']['parameter_errors'], true) . "\n";
    }
    echo "\n";
    $order1Id = null;
}

// TEST 4: Create second order (different address)
echo "📦 TEST 4: Creating second test order...\n";
$order2Data = [
    'matter' => 'Greeting Cards - Test Order 2',
    'points' => [
        [
            'address' => $testAddresses['pickup'],
            'contact_person' => [
                'phone' => $testPhone,
                'name' => 'Store Person'
            ]
        ],
        [
            'address' => $testAddresses['delivery2'],
            'contact_person' => [
                'phone' => $testPhone,
                'name' => 'Customer 2'
            ],
            'note' => 'Leave with security if not home'
        ]
    ]
];

$result = callBorzoAPI('/create-order', $order2Data, $testToken, $apiUrl);

if ($result['http_code'] == 200 && isset($result['response']['is_successful']) && $result['response']['is_successful']) {
    $order2Id = $result['response']['order']['order_id'];
    echo "✅ SECOND ORDER CREATED!\n";
    echo "   Order ID: " . $order2Id . "\n";
    echo "   Order Name: " . $result['response']['order']['order_name'] . "\n";
    echo "   Status: " . $result['response']['order']['status'] . "\n\n";
} else {
    echo "❌ Second order creation failed\n";
    if (isset($result['response']['errors'])) {
        echo "Errors: " . implode(', ', $result['response']['errors']) . "\n";
    }
    echo "\n";
    $order2Id = null;
}

// TEST 5: List orders to verify
echo "📋 TEST 5: Fetching recent orders...\n";
$result = callBorzoAPI('/orders', ['count' => 5], $testToken, $apiUrl);

if ($result['http_code'] == 200 && isset($result['response']['is_successful']) && $result['response']['is_successful']) {
    echo "✅ Found " . count($result['response']['orders']) . " recent orders\n";
    foreach ($result['response']['orders'] as $order) {
        echo "   - Order " . $order['order_id'] . ": " . $order['status'] . " (" . $order['matter'] . ")\n";
    }
    echo "\n";
}

// TEST 6: Get client info
echo "👤 TEST 6: Getting client profile...\n";
$result = callBorzoAPI('/client', [], $testToken, $apiUrl);

if ($result['http_code'] == 200 && isset($result['response']['is_successful']) && $result['response']['is_successful']) {
    echo "✅ Client info retrieved\n";
    echo "   Name: " . ($result['response']['client']['name'] ?? 'Not set') . "\n";
    echo "   Payment methods: " . implode(', ', $result['response']['client']['allowed_payment_methods'] ?? []) . "\n\n";
}

// ============================================
// SUMMARY
// ============================================
echo "========================================\n";
echo "TEST COMPLETE!\n";
echo "========================================\n";

if (isset($order1Id) && isset($order2Id)) {
    echo "✅ SUCCESS: Both test orders created!\n\n";
    echo "Next steps:\n";
    echo "1. Log in to https://apitest.borzodelivery.com/in\n";
    echo "2. Go to Orders section - you should see both orders\n";
    echo "3. Email Borzo at api.in@borzodelivery.com with:\n";
    echo "   \n";
    echo "   Subject: Production Access Request - DeeGeeCard.com\n";
    echo "   \n";
    echo "   Hello,\n";
    echo "   \n";
    echo "   We have successfully completed test integration.\n";
    echo "   Test Account: [YOUR TEST EMAIL]\n";
    echo "   Test Orders Created:\n";
    echo "   - Order ID: $order1Id\n";
    echo "   - Order ID: $order2Id\n";
    echo "   \n";
    echo "   Please provide production token.\n";
    echo "   \n";
    echo "   Best regards,\n";
    echo "   [Your Name]\n";
} else {
    echo "❌ Some tests failed. Please check the errors above.\n";
    echo "Common issues:\n";
    echo "- Check if token is correct: $testToken\n";
    echo "- Make sure you're using test environment URL\n";
    echo "- Verify addresses are valid\n";
}