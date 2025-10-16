<?php
function getUserByProfileUrl($conn, $profile_url) {
    $stmt = $conn->prepare("SELECT user_id FROM profile_url_details WHERE profile_url = ?");
    $stmt->execute([$profile_url]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getUserById($conn, $user_id) {
    $stmt = $conn->prepare("SELECT name, email, phone FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getBusinessInfo($conn, $user_id) {
    $stmt = $conn->prepare("SELECT business_name, business_description, business_address, google_direction, designation, website FROM business_info WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getProfilePhotos($conn, $user_id) {
    $stmt = $conn->prepare("SELECT profile_photo, cover_photo FROM profile_cover_photo WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getSocialLinks($conn, $user_id) {
    $stmt = $conn->prepare("SELECT facebook, instagram, whatsapp, linkedin, youtube, telegram FROM social_link WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getProducts($conn, $user_id) {
    $stmt = $conn->prepare("SELECT product_name, description, price, quantity, image_path FROM products WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getServices($conn, $user_id) {
    $stmt = $conn->prepare("SELECT service_name, description, price, duration, image_path FROM services WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getGallery($conn, $user_id) {
    $stmt = $conn->prepare("SELECT filename, photo_gallery_path, title, description FROM photo_gallery WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRatings($conn, $user_id) {
    $stmt = $conn->prepare("SELECT reviewer_name, rating, feedback, created_at FROM ratings WHERE user_id = ? AND rating IN (3, 4, 5) ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getBankDetails($conn, $user_id) {
    $stmt = $conn->prepare("SELECT account_name, bank_name, account_number, account_type, ifsc_code FROM bank_details WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getQrCodes($conn, $user_id) {
    $stmt = $conn->prepare("SELECT id, mobile_number, upi_id, upload_qr_code, payment_type, is_default FROM qrcode_details WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function submitRating($conn, $user_id, $data) {
    if (!empty($data['reviewer_name']) && $data['rating'] >= 1 && $data['rating'] <= 5) {
        $stmt = $conn->prepare("INSERT INTO ratings (user_id, reviewer_name, reviewer_email, reviewer_phone, rating, feedback) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $user_id,
            $data['reviewer_name'],
            $data['reviewer_email'] ?? '',
            $data['reviewer_phone'] ?? '',
            $data['rating'],
            $data['feedback'] ?? ''
        ]);
        return true;
    }
    return false;
}


?>