<?php
require 'config/db_connection.php';
require 'functions/profile_functions.php';

// Validate profile URL
if (!isset($_GET['profile_url'])) {
    header("HTTP/1.0 400 Bad Request");
    die("Profile URL is required");
}

$profile_url = $_GET['profile_url'];

// Get user ID from profile URL
$profile_data = getUserByProfileUrl($conn, $profile_url);
if (!$profile_data) {
    header("Location: page-not-found.php");
    exit();
}

$user_id = $profile_data['user_id'];

// Get all profile data
$user = getUserById($conn, $user_id);
if (!$user) {
    die("User not found");
}

// Fetch theme data using PDO
$theme_sql = "SELECT primary_color, secondary_color FROM theme WHERE user_id = ?";
$theme_stmt = $conn->prepare($theme_sql);
$theme_stmt->execute([$user_id]);
$theme_data = $theme_stmt->fetch(PDO::FETCH_ASSOC);

// Set default colors if no theme exists
$primary_color = $theme_data['primary_color'] ?? '#4e73df';
$secondary_color = $theme_data['secondary_color'] ?? '#858796';

// Get other profile data
$business_info = getBusinessInfo($conn, $user_id);
$photos = getProfilePhotos($conn, $user_id);
$social_link = getSocialLinks($conn, $user_id);
$products = getProducts($conn, $user_id);
$services = getServices($conn, $user_id);
$gallery = getGallery($conn, $user_id);
$ratings = getRatings($conn, $user_id);
$bank_details = getBankDetails($conn, $user_id);
$qr_codes = getQrCodes($conn, $user_id);

// Handle rating submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_rating'])) {
    $rating_data = [
        'reviewer_name' => $_POST['reviewer_name'] ?? '',
        'reviewer_email' => $_POST['reviewer_email'] ?? '',
        'reviewer_phone' => $_POST['reviewer_phone'] ?? '',
        'rating' => intval($_POST['rating'] ?? 0),
        'feedback' => $_POST['feedback'] ?? ''
    ];
    
    if (submitRating($conn, $user_id, $rating_data)) {
        header("Location: ?profile_url=$profile_url");
        exit();
    }
}

// Include HTML components
require 'includes/header.php';
require 'includes/navigation.php';
require 'includes/profile_header.php';
require 'includes/business_info.php';
require 'includes/products.php';
require 'includes/services.php';
require 'includes/gallery.php';
require 'includes/ratings.php';
require 'includes/bank_details.php';
require 'includes/qr_codes.php';
require 'includes/share_section.php';
require 'includes/footer.php';

// Close connection
$conn = null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title><?= htmlspecialchars($user['name']) ?> | <?= htmlspecialchars($business_info['business_name'] ?? '') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="assets/css/main.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?= $primary_color ?>;
            --secondary-color: <?= $secondary_color ?>;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .text-primary {
            color: var(--primary-color) !important;
        }
        
        .bg-primary {
            background-color: var(--primary-color) !important;
        }
        
        .border-primary {
            border-color: var(--primary-color) !important;
        }
        
        .btn-secondary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        .social_networks li a {
            background: var(--primary-color) !important;
        }
        .btn-success {
            background: var(--primary-color) !important;
        }
        /*.personal_contact, .business_details, .products, .services, .gallery, .display_ratings, .rating, .bank_details, .qr_code_details, .share-section, .product-card, .form-control {
            background-color: var(--secondary-color);
        }*/
        body {
            background-color: var(--secondary-color);
        }
    </style>
    <script>
        window.addEventListener('scroll', function() {
            const coverPhoto = document.querySelector('.cover_photo');
            const profilePhoto = document.querySelector('.profile_photo');
            const burgerMenu = document.querySelector('.burger-menu');
            
            if (window.scrollY > 50) {
                coverPhoto?.classList.add('small');
                profilePhoto?.classList.add('small', 'with-burger');
                burgerMenu?.classList.add('show');
            } else {
                coverPhoto?.classList.remove('small');
                profilePhoto?.classList.remove('small', 'with-burger');
                burgerMenu?.classList.remove('show');
            }
        });
    </script>
</head>
<body class="restaurant">
    <div class="main">