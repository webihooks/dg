<?php
// fcm-service.php
class FCMServiceV1 {
    private $serviceAccount;
    private $projectId;
    private $accessToken;
    
    public function __construct($serviceAccountPath) {
        if (!file_exists($serviceAccountPath)) {
            throw new Exception("Service account file not found: " . $serviceAccountPath);
        }
        
        $this->serviceAccount = json_decode(file_get_contents($serviceAccountPath), true);
        $this->projectId = $this->serviceAccount['project_id'];
        $this->accessToken = $this->getAccessToken();
    }
    
    private function getAccessToken() {
        $url = 'https://oauth2.googleapis.com/token';
        
        $jwt = $this->createJWT();
        
        $data = [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("FCM Token Error: " . $response);
            throw new Exception("Failed to get access token");
        }
        
        $responseData = json_decode($response, true);
        return $responseData['access_token'];
    }
    
    private function createJWT() {
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT'
        ];
        
        $now = time();
        $payload = [
            'iss' => $this->serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now
        ];
        
        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));
        
        $signature = '';
        $privateKey = $this->serviceAccount['private_key'];
        
        openssl_sign(
            $headerEncoded . '.' . $payloadEncoded,
            $signature,
            $privateKey,
            'sha256'
        );
        
        $signatureEncoded = $this->base64UrlEncode($signature);
        
        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }
    
    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    public function sendNotification($token, $title, $body, $data = []) {
        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";
        
        $message = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body
                ],
                'data' => $data,
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'sound' => 'default',
                        'channel_id' => 'high_importance_channel',
                        'visibility' => 'public',
                        'notification_count' => 1
                    ]
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                            'badge' => 1,
                            'content-available' => 1
                        ]
                    ],
                    'headers' => [
                        'apns-priority' => '10'
                    ]
                ],
                'webpush' => [
                    'headers' => [
                        'Urgency' => 'high'
                    ],
                    'notification' => [
                        'requireInteraction' => true,
                        'badge' => '/icons/badge-72x72.png',
                        'icon' => '/icons/icon-192x192.png',
                        'actions' => [
                            [
                                'action' => 'view_order',
                                'title' => '📱 View Order'
                            ]
                        ],
                        'vibrate' => [100, 50, 100],
                        'tag' => 'new-order'
                    ]
                ]
            ]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        return [
            'http_code' => $httpCode,
            'response' => json_decode($response, true),
            'error' => $error
        ];
    }
    
    public function sendToTopic($topic, $title, $body, $data = []) {
        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";
        
        $message = [
            'message' => [
                'topic' => $topic,
                'notification' => [
                    'title' => $title,
                    'body' => $body
                ],
                'data' => $data,
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'sound' => 'default',
                        'channel_id' => 'high_importance_channel'
                    ]
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                            'badge' => 1
                        ]
                    ]
                ]
            ]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'http_code' => $httpCode,
            'response' => json_decode($response, true)
        ];
    }
}
?>