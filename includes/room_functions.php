<?php
// Room-specific functions with unique names

// Fetch profile and cover photos for rooms
function getRoomProfilePhotos($conn, $user_id) {
    $sql = "SELECT profile_photo, cover_photo FROM profile_cover_photo WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    return [];
}

// Fetch business info for rooms
function getRoomBusinessInfo($conn, $user_id) {
    $sql = "SELECT business_name, business_description, business_address, designation, website, google_direction FROM business_info WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    return [];
}

// Fetch user contact details for rooms
function getRoomUserContactDetails($conn, $user_id) {
    $sql = "SELECT phone, name, Email FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    return null;
}

// Fetch profile URL for rooms
function getRoomProfileUrl($conn, $user_id) {
    $sql = "SELECT profile_url FROM profile_url_details WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['profile_url'] ?? null;
    }
    return null;
}

// Get user data for rooms
function getRoomUserData($conn, $user_id) {
    $sql = "SELECT id, Name as name, phone, Email as email, address FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    return null;
}

// Get services for rooms
function getRoomServices($conn, $user_id) {
    $sql = "SELECT service_name, description, price, duration, image_path FROM services WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    return [];
}

// Get gallery photos for rooms
function getRoomGallery($conn, $user_id) {
    $sql = "SELECT filename, photo_gallery_path, title, description FROM photo_gallery WHERE user_id = ? ORDER BY uploaded_at DESC";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    return [];
}

// Get ratings for rooms
function getRoomRatings($conn, $user_id) {
    $sql = "SELECT reviewer_name, rating, feedback, created_at FROM ratings WHERE user_id = ? ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    return [];
}

// Get social links for rooms
function getRoomSocialLinks($conn, $user_id) {
    $sql = "SELECT Facebook, Instagram, WhatsApp, LinkedIn, YouTube, Telegram FROM social_link WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    return [];
}

// Submit rating for rooms
function submitRoomRating($conn, $user_id, $rating_data) {
    $sql = "INSERT INTO ratings (user_id, reviewer_name, reviewer_email, reviewer_phone, rating, feedback) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        return $stmt->execute([
            $user_id,
            $rating_data['reviewer_name'],
            $rating_data['reviewer_email'],
            $rating_data['reviewer_phone'],
            $rating_data['rating'],
            $rating_data['feedback']
        ]);
    }
    return false;
}

// Get user APK for rooms
function getRoomUserApk($conn, $user_id) {
    $sql = "SELECT file_name, file_path FROM user_apks WHERE user_id = ? ORDER BY upload_date DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    return null;
}

// Main data fetching for rooms
$user = getRoomUserData($conn, $user_id);
$photos = getRoomProfilePhotos($conn, $user_id);
$business_info = getRoomBusinessInfo($conn, $user_id);
$social_link = getRoomSocialLinks($conn, $user_id);
$services = getRoomServices($conn, $user_id);
$gallery = getRoomGallery($conn, $user_id);
$ratings = getRoomRatings($conn, $user_id);
$apk_data = getRoomUserApk($conn, $user_id);
$profile_url = getRoomProfileUrl($conn, $user_id);

// Handle rating submission for rooms
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_rating'])) {
    $rating_data = [
        'reviewer_name' => $_POST['reviewer_name'] ?? '',
        'reviewer_email' => $_POST['reviewer_email'] ?? '',
        'reviewer_phone' => $_POST['reviewer_phone'] ?? '',
        'rating' => intval($_POST['rating'] ?? 0),
        'feedback' => $_POST['feedback'] ?? ''
    ];

    if (submitRoomRating($conn, $user_id, $rating_data)) {
        header("Location: ?profile_url=" . urlencode($profile_url));
        exit();
    } else {
        echo "<script>alert('Failed to submit rating. Please try again.');</script>";
    }
}






// Function to get QR code details for rooms
function getRoomQrCodes($conn, $user_id) {
    $sql = "SELECT mobile_number, upload_qr_code, payment_type, upi_id, is_default 
            FROM qrcode_details 
            WHERE user_id = ? 
            ORDER BY is_default DESC, id ASC";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->execute([$user_id]);
        $qr_codes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug: Check what data we're getting
        error_log("QR Codes for user $user_id: " . print_r($qr_codes, true));
        
        return $qr_codes;
    }
    return [];
}

// Get QR codes for the current user
$qr_codes = getRoomQrCodes($conn, $user_id);

// Function to get correct QR code path
function getQrCodePath($upload_qr_code) {
    if (empty($upload_qr_code)) {
        return null;
    }
    
    // Use the correct path: https://deegeecard.com/uploads/qrcodes/
    $correct_path = "https://deegeecard.com/uploads/qrcodes/{$upload_qr_code}";
    
    // Check if file exists using cURL
    $ch = curl_init($correct_path);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        error_log("QR code found at: $correct_path");
        return $correct_path;
    }
    
    error_log("QR code not found at: $correct_path");
    return null;
}

// Function to get payment type icon (Alternative options)
function getPaymentTypeIcon($payment_type) {
    $icons = [
        'UPI' => 'fa-mobile-alt',
        'Paytm' => 'fa-wallet',
        'PhonePe' => 'fa-mobile-alt',
        'Google Pay' => 'fa-wallet', // Alternative 1: Credit card icon
        // 'Google Pay' => 'fa-university', // Alternative 2: Bank icon
        // 'Google Pay' => 'fa-money-bill-wave', // Alternative 3: Money bill icon
        'Other' => 'fa-qrcode'
    ];
    return $icons[$payment_type] ?? 'fa-qrcode';
}

// Function to get payment type color
function getPaymentTypeColor($payment_type) {
    $colors = [
        'UPI' => 'bg-purple-100 text-purple-800 border-purple-200',
        'Paytm' => 'bg-blue-100 text-blue-800 border-blue-200',
        'PhonePe' => 'bg-purple-100 text-purple-800 border-purple-200',
        'Google Pay' => 'bg-teal-100 text-teal-800 border-teal-200',
        'Other' => 'bg-gray-100 text-gray-800 border-gray-200'
    ];
    return $colors[$payment_type] ?? 'bg-gray-100 text-gray-800 border-gray-200';
}

// Debug information
error_log("Total QR codes found: " . count($qr_codes));




// Room images for fallback
$room_images = [
    'https://images.unsplash.com/photo-1566665797739-1674de7a421a?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80',
    'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80',
    'https://images.unsplash.com/photo-1590490360182-c33d57733427?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80',
    'https://images.unsplash.com/photo-1611892440504-42a792e24d32?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80',
    'https://images.unsplash.com/photo-1586105251261-72a756497a11?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80'
];

$fallback_images = [
    'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNTAwIiBoZWlnaHQ9IjMwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iNTAwIiBoZWlnaHQ9IjMwMCIgZmlsbD0iI2YzZjRmNiIvPjx0ZXh0IHg9IjI1MCIgeT0iMTUwIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMjAiIGZpbGw9IiM5YzljOWMiPkRlbHV4ZSBSb29tPC90ZXh0Pjwvc3ZnPg==',
    'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNTAwIiBoZWlnaHQ9IjMwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iNTAwIiBoZWlnaHQ9IjMwMCIgZmlsbD0iI2UzZjBmZiIvPjx0ZXh0IHg9IjI1MCIgeT0iMTUwIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMjAiIGZpbGw9IiM3Nzg0YTMiPlN1aXRlIFJvb208L3RleHQ+PC9zdmc+',
    'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNTAwIiBoZWlnaHQ9IjMwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iNTAwIiBoZWlnaHQ9IjMwMCIgZmlsbD0iI2ZmZjBlNSIvPjx0ZXh0IHg9IjI1MCIgeT0iMTUwIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMjAiIGZpbGw9IiNjYzg3MDAiPlN0YW5kYXJkIFJvb208L3RleHQ+PC9zdmc+'
];
?>