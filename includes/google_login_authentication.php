<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load Google config
require_once __DIR__ . '/../config/google_config.php';

/**
 * Generate Google OAuth URL
 */
function getGoogleAuthUrl() {
    $params = [
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope' => 'email profile',
        'access_type' => 'online',
        'prompt' => 'select_account'
    ];
    return GOOGLE_AUTH_URL . '?' . http_build_query($params);
}

/**
 * Exchange authorization code for user info
 */
function getGoogleUser($code) {
    // Exchange code for access token
    $postData = [
        'code' => $code,
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'grant_type' => 'authorization_code'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, GOOGLE_TOKEN_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("Google Token Error: " . $response);
        return null;
    }

    $tokenData = json_decode($response, true);
    if (isset($tokenData['error'])) {
        error_log("Google Token Error: " . print_r($tokenData, true));
        return null;
    }

    // Get user info using access token
    $accessToken = $tokenData['access_token'];
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => 'Authorization: Bearer ' . $accessToken
        ]
    ];
    $context = stream_context_create($opts);
    $userInfo = file_get_contents(GOOGLE_USERINFO_URL, false, $context);
    
    if ($userInfo === false) {
        error_log("Failed to get user info from Google");
        return null;
    }
    
    return json_decode($userInfo, true);
}

/**
 * Save or update customer data in the database
 * Returns array with customer_id, is_new, points
 */
function saveOrUpdateCustomer($conn, $user_id, $googleUser) {
    $google_id = $googleUser['id'];
    $email = $googleUser['email'];
    $name = $googleUser['name'];
    $picture = $googleUser['picture'] ?? '';

    // Check if customer already exists for this restaurant
    $stmt = $conn->prepare("SELECT id, loyalty_points FROM customer_google_accounts WHERE restaurant_user_id = ? AND (google_id = ? OR email = ?)");
    $stmt->execute([$user_id, $google_id, $email]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // Update last_login only (no new points for existing customers)
        $update = $conn->prepare("UPDATE customer_google_accounts SET last_login = NOW() WHERE id = ?");
        $update->execute([$existing['id']]);
        return [
            'customer_id' => $existing['id'],
            'is_new' => false,
            'points' => $existing['loyalty_points']
        ];
    } else {
        // Insert new record with 1000 loyalty points
        $loyalty_points = 1000;
        $insert = $conn->prepare("INSERT INTO customer_google_accounts 
            (restaurant_user_id, google_id, email, name, picture, loyalty_points, created_at, last_login) 
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $insert->execute([$user_id, $google_id, $email, $name, $picture, $loyalty_points]);
        $new_id = $conn->lastInsertId();
        return [
            'customer_id' => $new_id,
            'is_new' => true,
            'points' => $loyalty_points
        ];
    }
}

/**
 * Get customer data for the currently logged-in user (restaurant-specific)
 */
function getCustomerData($conn, $user_id) {
    if (!isset($_SESSION['customer_logged_in']) || !isset($_SESSION['customer_restaurant_id']) || $_SESSION['customer_restaurant_id'] != $user_id) {
        return null;
    }
    $customer_id = $_SESSION['customer_id'];
    $stmt = $conn->prepare("SELECT * FROM customer_google_accounts WHERE id = ? AND restaurant_user_id = ?");
    $stmt->execute([$customer_id, $user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Check if customer is logged in for this restaurant
 */
function isCustomerLoggedIn($user_id) {
    return isset($_SESSION['customer_logged_in']) && 
           isset($_SESSION['customer_restaurant_id']) && 
           $_SESSION['customer_restaurant_id'] == $user_id;
}

/**
 * Logout customer
 */
function logoutCustomer() {
    session_destroy();
    session_start(); // Start new session for next requests
}

/**
 * Update customer address & phone after order
 */
function updateCustomerDetails($conn, $user_id, $customer_id, $phone, $address_data) {
    $address_json = json_encode($address_data);
    $sql = "UPDATE customer_google_accounts 
            SET phone = ?, delivery_address = ?, updated_at = NOW() 
            WHERE id = ? AND restaurant_user_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->errorInfo()[2]);
        return false;
    }
    $success = $stmt->execute([$phone, $address_json, $customer_id, $user_id]);
    if (!$success) {
        error_log("Execute failed: " . implode(", ", $stmt->errorInfo()));
    }
    return $success;
}




/**
 * Display login modal (only for non-logged-in users)
 * @param int $user_id Restaurant user ID
 * @param string $profile_url Profile URL for logout redirect
 * @param array|null $customer_data Ignored (kept for compatibility)
 */
function showLoginPopup($user_id, $profile_url, $customer_data = null) {
    ?>
    <!-- Login Modal -->
    <div class="modal fade" id="loginStatusModal" tabindex="-1" aria-labelledby="loginStatusModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="padding: 0 10px;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 style="text-transform: capitalize;">Google Login Required</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-exclamation-triangle"></i> 
                        <strong>Please login with Google to place an order.</strong>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="<?= getGoogleAuthUrl() . '&state=' . urlencode('user_id=' . $user_id . '&profile_url=' . $profile_url) ?>" 
                       class="btn btn-danger w-100">
                        <i class="bi bi-google"></i> Login with Google
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php
}




?>