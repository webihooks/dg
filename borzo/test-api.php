<?php
// /borzo/test-api.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Borzo API Test</title>
    <style>
        body { font-family: monospace; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        pre { background: #f4f4f4; padding: 10px; overflow: auto; }
        button { padding: 10px 20px; margin: 10px; cursor: pointer; }
        .result { margin-top: 20px; }
    </style>
</head>
<body>
    <h1>Borzo API Authentication Test</h1>
    
    <button onclick="testAuth('query')">Test: Token in URL</button>
    <button onclick="testAuth('header_x_api')">Test: X-API-Key Header</button>
    <button onclick="testAuth('header_bearer')">Test: Bearer Header</button>
    <button onclick="testAuth('header_token')">Test: Token Header</button>
    <button onclick="testAuth('post_param')">Test: POST Parameter</button>
    
    <div class="result" id="result">
        <h3>Result:</h3>
        <pre id="output">Click a button to test...</pre>
    </div>
    
    <script>
        const API_URL = 'https://robotapitest-in.borzodelivery.com/api/business/1.6/orders/create';
        const TOKEN = '6CDDE6B80E8DAF4E99B05DEA3F0E80E812E9DF54';
        
        const payload = {
            external_order_id: 'TEST_' + Date.now(),
            route: {
                pickup: {
                    address: 'Saket, New Delhi, Delhi, India',
                    contact_person: {
                        name: 'Test Restaurant',
                        phone: '918880000001'
                    }
                },
                dropoff: {
                    address: 'Connaught Place, New Delhi, Delhi, India',
                    contact_person: {
                        name: 'Test Customer',
                        phone: '919876543210'
                    }
                }
            },
            items: [{
                name: 'Test Item',
                quantity: 1,
                weight: 200,
                cost: 100
            }],
            payment_method: 'cash',
            cod_amount: 100,
            vehicle_type_id: 8
        };
        
        async function testAuth(method) {
            const output = document.getElementById('output');
            output.textContent = 'Testing...';
            
            let url = API_URL;
            let headers = {
                'Content-Type': 'application/json'
            };
            let body = JSON.stringify(payload);
            
            switch(method) {
                case 'query':
                    url = API_URL + '?token=' + TOKEN;
                    break;
                case 'header_x_api':
                    headers['X-API-Key'] = TOKEN;
                    break;
                case 'header_bearer':
                    headers['Authorization'] = 'Bearer ' + TOKEN;
                    break;
                case 'header_token':
                    headers['Authorization'] = 'Token ' + TOKEN;
                    break;
                case 'post_param':
                    payload.api_key = TOKEN;
                    body = JSON.stringify(payload);
                    break;
            }
            
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: headers,
                    body: body
                });
                
                const result = await response.json();
                
                output.textContent = JSON.stringify({
                    method: method,
                    url: url,
                    headers: headers,
                    status: response.status,
                    response: result
                }, null, 2);
                
                if (result.is_successful === true || result.id) {
                    output.className = 'success';
                } else {
                    output.className = 'error';
                }
            } catch(error) {
                output.textContent = 'Error: ' + error.message;
                output.className = 'error';
            }
        }
    </script>
</body>
</html>