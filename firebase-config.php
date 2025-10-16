<?php
// Firebase FCM Configuration
class FirebaseConfig {
    const PROJECT_ID = 'deegeecard-16-oct-25';
    const SERVICE_ACCOUNT_FILE = 'deegeecard-16-oct-25-firebase-adminsdk-fbsvc-3211ef62c3.json';
    
    public static function getAccessToken() {
        $serviceAccountPath = __DIR__ . '/' . self::SERVICE_ACCOUNT_FILE;
        
        error_log("Looking for service account file at: " . $serviceAccountPath);
        
        if (!file_exists($serviceAccountPath)) {
            throw new Exception('Firebase service account file not found at: ' . $serviceAccountPath);
        }
        
        $serviceAccount = json_decode(file_get_contents($serviceAccountPath), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON in service account file: ' . json_last_error_msg());
        }
        
        $privateKey = $serviceAccount['private_key'];
        $clientEmail = $serviceAccount['client_email'];
        
        error_log("Service account loaded for: " . $clientEmail);
        
        // Create JWT token for FCM V1 API
        $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $payload = json_encode([
            'iss' => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => time() + 3600,
            'iat' => time()
        ]);
        
        $base64Header = self::base64UrlEncode($header);
        $base64Payload = self::base64UrlEncode($payload);
        $signature = '';
        
        $signResult = openssl_sign($base64Header . "." . $base64Payload, $signature, $privateKey, 'SHA256');
        if (!$signResult) {
            throw new Exception('OpenSSL sign failed');
        }
        
        $base64Signature = self::base64UrlEncode($signature);
        
        $jwt = $base64Header . "." . $base64Payload . "." . $base64Signature;
        
        // Exchange JWT for access token
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://oauth2.googleapis.com/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ],
            CURLOPT_SSL_VERIFYPEER => false, // For debugging, remove in production
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("OAuth token request failed. HTTP Code: $httpCode, Response: $response, Error: $curlError");
            throw new Exception('Failed to get OAuth token. HTTP Code: ' . $httpCode);
        }
        
        $data = json_decode($response, true);
        if (isset($data['access_token'])) {
            error_log("Access token obtained successfully");
            return $data['access_token'];
        } else {
            error_log("No access token in response: " . $response);
            throw new Exception('No access token in response');
        }
    }
    
    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
?>