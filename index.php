<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title>DEEGEECARD | Get Orders Directly, Zero Commissions!!!</title>

    <meta name="description" content="DEEDEECARD">
    <link rel="icon" type="image/png" href="https://deegeecard.com/images/dg_logo.png">
    <meta property="og:title" content="DEEDEECARD">
    <meta property="og:description" content="Get Orders Directly, Zero Commissions!!!">
    <meta property="og:image" content="https://deegeecard.com/images/dg_logo.png">
    <meta property="og:type" content="restaurant">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="DEEDEECARD">
    <meta name="twitter:description" content="Get Orders Directly, Zero Commissions!!!">
    <meta name="twitter:image" content="https://deegeecard.com/images/dg_logo.png">

    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#fb5b29">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="DeeGeeCard">
    <link rel="apple-touch-icon" href="https://deegeecard.com/images/dg_logo.png">




    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-81W5S4MMGY"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-81W5S4MMGY');
</script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#fb5b29',
                        secondary: '#fbbd29',
                        accent: '#f97316',
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes slideUpFadeIn {
            0% {
                opacity: 0;
                transform: translateY(40px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }
        nav a {
            padding: 8px 0;
        }
        
        .animate-slide-up {
            animation: slideUpFadeIn 1.2s ease-out forwards;
        }
        
        .phone-frame {
            position: relative;
            width: 400px;
            height: 750px;
            margin: 0 auto;
            border-radius: 40px;
            box-shadow: 0 30px 60px -12px rgba(0, 0, 0, 0.25), 
                        0 18px 36px -18px rgba(0, 0, 0, 0.3),
                        inset 0 -2px 6px 0 rgba(0, 0, 0, 0.1);
            overflow: hidden;
            background: #111;
            opacity: 0;
        }
        
        .phone-screen {
            position: absolute;
            top: 12px;
            left: 12px;
            right: 12px;
            bottom: 12px;
            border-radius: 30px;
            overflow: hidden;
            background: white;
        }
        
        .phone-notch {
            position: absolute;
            top: 12px;
            left: 50%;
            transform: translateX(-50%);
            width: 50%;
            height: 25px;
            background: #111;
            border-radius: 0 0 12px 12px;
            z-index: 10;
        }
        
        .phone-iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
        
        .phone-button {
            position: absolute;
            bottom: 12px;
            left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 4px;
            background: #333;
            border-radius: 4px;
        }
        
        /* Mobile menu animation */
        .mobile-menu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }
        
        .mobile-menu.open {
            max-height: 300px;
        }
        
        /* Client logo carousel styles */
        .clients-section {
            /*background: linear-gradient(to right, #f8fafc, #e2e8f0);*/
            padding: 4rem 0;
            overflow: hidden;
            position: relative;
        }
        
        .client-carousel {
            display: flex;
            transition: transform 0.5s ease;
            padding: 2rem 0;
        }
        
        .client-item {
            flex: 0 0 auto;
            margin: 0 1rem;
            transition: transform 0.3s ease;
        }
        
        .client-item:hover {
            transform: scale(1.05);
        }
        
        .client-logo {
            width: 200px;
            height: 200px;
            object-fit: contain;
            border-radius: 10px;
            background: white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .carousel-container {
            position: relative;
            overflow: hidden;
            width: 100%;
        }
        
        .carousel-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: white;
            width: 50px;
            height: 50px;
            margin-top: -12px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            z-index: 20;
            transition: all 0.3s ease;
        }
        
        .carousel-nav:hover {
            background: #f1f5f9;
            transform: translateY(-50%) scale(1.05);
        }
        
        .carousel-nav.prev {
            left: 20px;
        }
        
        .carousel-nav.next {
            right: 20px;
        }
        
        .carousel-nav i {
            font-size: 1.5rem;
            color: #fb5b29;
        }
        
        .carousel-dots {
            display: flex;
            justify-content: center;
            margin-top: 10px;
        }
        
        .carousel-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #cbd5e1;
            margin: 0 5px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        
        .carousel-dot.active {
            background: #fb5b29;
        }
        
        /* Restaurant ordering system section */
        .restaurant-section {
            background: linear-gradient(to right, #fb5b29, #fbbd29);
            color: white;
            padding: 5rem 0;
        }
        
        .feature-icon {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }
        
        .feature-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 2rem;
            height: 100%;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.1);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .cta-button {
            background: linear-gradient(to right, #fbbd29, #fb5b29);
            color: white;
            font-weight: bold;
            padding: 1rem 2rem;
            border-radius: 50px;
            display: inline-block;
            transition: all 0.3s ease;
            border: 5px solid #fff;
        }
        
        .cta-button:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
        
        /* Footer styles */
        .footer {
            background: #111827;
            color: white;
            padding: 4rem 0 2rem;
        }
        
        .footer-heading {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .footer-heading:after {
            content: '';
            position: absolute;
            bottom: -0.5rem;
            left: 0;
            width: 40px;
            height: 3px;
            background: #fb5b29;
        }
        
        .footer-link {
            display: block;
            margin-bottom: 0.75rem;
            color: #d1d5db;
            transition: all 0.3s ease;
        }
        
        .footer-link:hover {
            color: #fb5b29;
            transform: translateX(5px);
        }
        
        .newsletter-input {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            padding: 0.75rem 1rem;
            border-radius: 0.375rem 0 0 0.375rem;
            color: white;
            width: 100%;
        }
        
        .newsletter-input:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.15);
        }
        
        .newsletter-btn {
            background: #fb5b29;
            color: white;
            border: none;
            padding: 0.75rem 1.25rem;
            border-radius: 0 0.375rem 0.375rem 0;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .newsletter-btn:hover {
            background: #fbbd29;
        }
        
        .social-icon {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            margin-right: 0.75rem;
            transition: all 0.3s ease;
        }
        
        .social-icon:hover {
            background: #fb5b29;
            transform: translateY(-3px);
        }
        
        @media (max-width: 768px) {
            .mobile_hide {
                display: none !important;
            }
            .phone-frame {
                width: 360px;
                height: 720px;
            }
            .client-logo {
                width: 200px;
                height: 200px;
            }
            .carousel-nav {
                width: 40px;
                height: 40px;
            }
            .carousel-nav.prev {
                left: 10px;
            }
            .carousel-nav.next {
                right: 10px;
            }
        }
        @media (max-width: 380px) {
            .phone-frame {
                width: 300px;
                height: 550px;
            }
        }
    </style>
</head>
<body>




    <!-- Header with navigation -->
    <header class="bg-white shadow-md sticky top-0 z-50">
        <div class="container mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <!-- Logo -->
                <div class="flex items-center">
                    <img src="images/deegeecard_logo.png" alt="DeeGeeCard Logo" class="h-10 md:h-12">
                </div>

                <!-- Desktop Navigation -->
                <nav class="hidden md:flex space-x-8">
                    <a href="index.php" class="text-gray-600 hover:text-primary font-medium">Home</a>
                    <a href="contact.php" class="text-gray-600 hover:text-primary font-medium">Contact</a>
                    <a href="help.php" class="text-gray-600 hover:text-primary font-medium">Help</a>
                    <a href="login.php" class="text-gray-600 hover:text-primary font-medium">Login</a>
                    <!-- <a href="register.php" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-secondary font-medium">Register</a> -->
                </nav>

                <!-- Mobile menu button -->
                <button id="mobile-menu-button" class="md:hidden text-gray-600">
                    <i class="fas fa-bars text-2xl"></i>
                </button>
            </div>

            <!-- Mobile Navigation -->
            <div id="mobile-menu" class="mobile-menu md:hidden">
                <div class="flex flex-col space-y-4 pb-4">
                    <a href="index.php" class="text-gray-600 hover:text-primary font-medium py-2">Home</a>
                    <a href="contact.php" class="text-gray-600 hover:text-primary font-medium py-2">Contact</a>
                    <a href="help.php" class="text-gray-600 hover:text-primary font-medium py-2">Help</a>
                    <a href="login.php" class="text-gray-600 hover:text-primary font-medium py-2">Login</a>
                    <!-- <a href="register.php" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-secondary font-medium text-center">Register</a> -->
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section with Phone -->
<!-- Hero Section with Phone -->
<section class="bg-gradient-to-r from-primary to-secondary text-white py-10 md:py-24">
    <div class="container mx-auto px-4">
        <div class="flex flex-col md:flex-row items-center">
            <div class="md:w-1/2 mb-10 md:mb-0 text-center md:text-left">
                <h1 class="text-4xl md:text-6xl font-bold mb-6">Get Orders Directly, <br> Zero Commissions!!! </h1>
                <p class="text-xl mb-8 max-w-2xl mx-auto md:mx-0">Introducing DeeGeeCard – Your own branded food ordering system with <span class="font-bold">ZERO commission, forever!</span></p>
                <a href="javascript:void(0)" class="cta-button">Check Live Demo</a>
            </div>
            
            <div class="md:w-1/2 flex justify-center">
                <div class="phone-frame animate-slide-up">
                    <div class="phone-notch"></div>
                    <div class="phone-screen">
                        <iframe src="https://deegeecard.com/thedhamaalcafe" class="phone-iframe" title="DeeGeeCard Example"></iframe>
                    </div>
                    <div class="phone-button"></div>
                </div>
            </div>
        </div>
    </div>
</section>

    <!-- Client Logos Section -->
    <section class="clients-section">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-bold text-center">Our Valued Clients</h2>
            <div class="carousel-container">
                <!-- Navigation buttons -->
                <div class="carousel-nav prev">
                    <i class="fas fa-chevron-left"></i>
                </div>
                <div class="carousel-nav next">
                    <i class="fas fa-chevron-right"></i>
                </div>
<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

$clientLogos = [];

try {
    require 'config/index_db_connection.php';

    if (isset($pdo) && $pdo instanceof PDO) {
        // Use LEFT JOIN so users appear even if some details are missing
        $stmt = $pdo->prepare("
            SELECT DISTINCT
                u.id AS user_id,
                p.profile_photo,
                b.business_name,
                url.profile_url
            FROM subscriptions s
            JOIN users u ON s.user_id = u.id
            LEFT JOIN profile_cover_photo p ON p.user_id = u.id
            LEFT JOIN profile_url_details url ON url.user_id = u.id
            LEFT JOIN business_info b ON b.user_id = u.id
            WHERE s.package_id IN (1, 3)
              AND p.profile_photo IS NOT NULL 
              AND p.profile_photo != ''
            ORDER BY p.created_at DESC
            LIMIT 100
        ");
        $stmt->execute();
        $clientLogos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Debug output if empty
        if (empty($clientLogos)) {
            echo "<pre>DEBUG: No clients found. Check if subscriptions has package_id=1 or 3 and profile_photo is not empty.</pre>";
        }
    } else {
        throw new Exception("Database connection not established");
    }
} catch (Exception $e) {
    error_log("Error in index.php: " . $e->getMessage());
    echo "<pre>DEBUG: " . $e->getMessage() . "</pre>";
}
?>
                
<div class="client-carousel">
    <?php if (!empty($clientLogos)): ?>
        <?php foreach ($clientLogos as $client): ?>
            <a href="https://deegeecard.com/<?php echo htmlspecialchars($client['profile_url']); ?>" 
               class="client-item" target="_blank" 
               title="<?php echo htmlspecialchars($client['business_name']); ?>">
                <img src="https://deegeecard.com/uploads/profile/<?php echo htmlspecialchars($client['profile_photo']); ?>" 
                     alt="<?php echo htmlspecialchars($client['business_name']); ?>" 
                     class="client-logo">
            </a>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="text-center text-gray-500">No clients available</p>
    <?php endif; ?>
</div>

                
                <!-- Dots indicator -->
                <div class="carousel-dots"></div>
            </div>
        </div>
    </section>

    <!-- Restaurant Ordering System Section -->
    <section class="restaurant-section">
        <div class="container mx-auto px-4">
            <div class="text-center mb-16">
                <h2 class="text-4xl md:text-5xl font-bold mb-6">Stop Losing Your Profits to Food Delivery Commissions</h2>
                <p class="text-xl max-w-3xl mx-auto">Introducing DeeGeeCard Restaurant Ordering System – Your own branded food ordering platform with <span class="font-bold">ZERO commission, forever!</span></p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-16">
                <!-- Feature 1 -->
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-globe text-2xl text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-4">Your Own Ordering Website</h3>
                    <p class="text-white">Just like your current Delivery Partner App, but branded for YOUR restaurant. Zero commission fees.</p>
                </div>
                
                <!-- Feature 2 -->
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fab fa-android text-2xl text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-4">Your Own Android App</h3>
                    <p class="text-white">Increase loyalty with a seamless app under your restaurant's name.
                        <br>
                        <span style="float:right;">*Condition apply</span></p>
                </div>
                
                <!-- Feature 3 -->
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-mobile-alt text-2xl text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-4">Admin Management App</h3>
                    <p class="text-white">Accept/reject orders, update menus & prices in real-time from your phone.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-receipt text-2xl text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-4">KOT & Bill Printing</h3>
                    <p class="text-white">Generate kitchen order tickets and bills in just one click.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-cogs text-2xl text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-4">Full Store Control</h3>
                    <p class="text-white">Set store timings, delivery charges, GST, discounts, coupon codes, and menu categories easily.</p>
                </div>
                
                <!-- Feature 4 -->
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-qrcode text-2xl text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-4">QR Code Visiting Cards & Table Standees</h3>
                    <p class="text-white">1000 QR code cards & 8 table standees to turn every table into a self-ordering station.</p>
                </div>
                
                <!-- Feature 5 -->
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fab fa-whatsapp text-2xl text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-4">Bulk WhatsApp Marketing</h3>
                    <p class="text-white">FREE 10,000 WhatsApp marketing credits to send offers directly to customers.</p>
                </div>
                
                <!-- Feature 6 -->
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-credit-card text-2xl text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-4">Direct Payments</h3>
                    <p class="text-white">Receive money via UPI/Cards instantly with 0% platform fee directly to your account.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-comment-dots text-2xl text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-4">Reply to Reviews Instantly</h3>
                    <p class="text-white">Respond to customer reviews directly via WhatsApp in one click.</p>
                </div>
            </div>
            
            <div class="bg-white text-gray-800 rounded-2xl p-8 mb-12">
                <h3 class="text-2xl font-bold mb-6 text-center">Plus FREE Integrations</h3>
                <p class="text-center mb-6">Google, Instagram, Facebook, YouTube and Maps so customers find you easily with just a click.</p>
                
                <div class="flex flex-wrap justify-center gap-6 mb-8">
                    <div class="flex items-center">
                        <i class="fab fa-google text-3xl text-blue-500 mr-2"></i>
                        <span>Google</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fab fa-instagram text-3xl text-pink-500 mr-2"></i>
                        <span>Instagram</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fab fa-facebook text-3xl text-blue-600 mr-2"></i>
                        <span>Facebook</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fab fa-youtube text-3xl text-red-600 mr-2"></i>
                        <span>YouTube</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-map-marked-alt text-3xl text-green-500 mr-2"></i>
                        <span>Maps</span>
                    </div>
                </div>
                
                <p class="text-center font-bold text-lg">Stop paying commissions. Start keeping 100% of your profits.</p>
            </div>
            
            <div class="text-center mb-12">
                <h3 class="text-5xl font-bold mb-6">All this for just ₹9,999/year</h3>
                <p class="text-3xl mb-8">We set everything up for you. Go live the same day!</p>
                <a href="tel:+919819411026" class="cta-button">CALL US NOW TO GET STARTED</a>
                <br>
                <br>
                <a href="https://wa.me/919819411026?text=Hi%20Team%20DeeGeeCard%20%F0%9F%91%8B%2C%20I%E2%80%99m%20ready%20to%20grow%20my%20restaurant%20with%20ZERO%20commission%20orders%21%20Please%20help%20me%20get%20started." target="_blank" class="cta-button">WHATSAPP US NOW TO GET STARTED</a>
            </div>
            




        </div>
    </section>

    <!-- Footer Section -->
    <footer class="footer">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-12">
                <!-- Company Info -->
                <div>
                    <h3 class="footer-heading">About DeeGeeCard</h3>
                    <p class="text-gray-400 mb-6">DeeGeeCard empowers restaurants to go fully digital with their own branded ordering website and mobile app — no commissions, no middlemen. In just 60 minutes, we set up your ordering platform, admin app, QR code menus, and WhatsApp marketing tools so you can accept orders, receive direct payments, and grow customer loyalty.</p>
                    <div class="flex">
                        <a href="https://www.facebook.com/deegeecard" class="social-icon">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <!-- <a href="index.php" class="social-icon">
                            <i class="fab fa-twitter"></i>
                        </a> -->
                        <a href="https://www.instagram.com/deegeecard" class="social-icon">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <!-- <a href="index.php" class="social-icon">
                            <i class="fab fa-linkedin-in"></i>
                        </a> -->
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div>
                    <h3 class="footer-heading">Quick Links</h3>
                    <a href="index.php" class="footer-link">Home</a>
                    <a href="contact.php" class="footer-link">Contact us</a>
                    <a href="help.php" class="footer-link">Help</a>
                </div>
                
                <!-- Support -->
                <div>
                    <h3 class="footer-heading">Support</h3>
                    <a href="terms-and-conditions.php" class="footer-link">Terms & conditions</a>
                    <a href="privacy-policy.php" class="footer-link">Privacy policy</a>
                    <a href="cancellation-refund-policy.php" class="footer-link">Cancellation & Refund Policy</a>
                    <a href="shipping-delivery.php" class="footer-link">Shipping and Delivery</a>
                </div>
                
                <!-- Newsletter -->
                <div>
                    <h3 class="footer-heading">Subscribe us</h3>
                    <p class="text-gray-400 mb-4">Subscribe our newsletter to receive latest updates regularly from us!</p>
                    <div class="flex mb-2">
                        <input type="email" placeholder="Your email address" class="newsletter-input">
                        <button class="newsletter-btn">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                    <p class="text-xs text-gray-500">By clicking send link you agree to receive messages.</p>
                </div>
            </div>
            
            <!-- Copyright -->
            <div class="border-t border-gray-800 pt-6 pb-4">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <p class="text-gray-400 text-sm mb-4 md:mb-0">© 2025 DeeGeeCard. All rights reserved.</p>
                    <div class="flex space-x-6">
                        <a href="privacy-policy.php" class="text-gray-400 hover:text-white text-sm">Privacy Policy</a>
                        <a href="terms-and-conditions.php" class="text-gray-400 hover:text-white text-sm">Terms of Service</a>
                        <a href="index.php" class="text-gray-400 hover:text-white text-sm">Sitemap</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Mobile menu toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuButton = document.getElementById('mobile-menu-button');
            const mobileMenu = document.getElementById('mobile-menu');
            const menuIcon = mobileMenuButton.querySelector('i');
            
            mobileMenuButton.addEventListener('click', function() {
                mobileMenu.classList.toggle('open');
                
                // Toggle between hamburger and close icon
                if (mobileMenu.classList.contains('open')) {
                    menuIcon.classList.remove('fa-bars');
                    menuIcon.classList.add('fa-times');
                } else {
                    menuIcon.classList.remove('fa-times');
                    menuIcon.classList.add('fa-bars');
                }
            });
            
            // Trigger phone animation when page loads
            const phone = document.querySelector('.phone-frame');
            phone.classList.add('animate-slide-up');
            
            // Close menu when clicking outside
            document.addEventListener('click', function(event) {
                if (!mobileMenu.contains(event.target) && !mobileMenuButton.contains(event.target)) {
                    mobileMenu.classList.remove('open');
                    menuIcon.classList.remove('fa-times');
                    menuIcon.classList.add('fa-bars');
                }
            });
            
            // Initialize the carousel
            initCarousel();
        });
        
        // Carousel functionality
        function initCarousel() {
            const carousel = document.querySelector('.client-carousel');
            const items = document.querySelectorAll('.client-item');
            const prevBtn = document.querySelector('.carousel-nav.prev');
            const nextBtn = document.querySelector('.carousel-nav.next');
            const dotsContainer = document.querySelector('.carousel-dots');
            
            let currentIndex = 0;
            const itemWidth = items[0].offsetWidth + parseInt(getComputedStyle(items[0]).marginRight) * 2;
            const visibleItems = Math.floor(carousel.offsetWidth / itemWidth);
            
            // Create dots
            items.forEach((_, index) => {
                const dot = document.createElement('div');
                dot.classList.add('carousel-dot');
                if (index === 0) dot.classList.add('active');
                dot.addEventListener('click', () => goToSlide(index));
                dotsContainer.appendChild(dot);
            });
            
            const dots = document.querySelectorAll('.carousel-dot');
            
            // Update carousel position
            function updateCarousel() {
                carousel.style.transform = `translateX(-${currentIndex * itemWidth}px)`;
                
                // Update active dot
                dots.forEach((dot, index) => {
                    dot.classList.toggle('active', index === currentIndex);
                });
            }
            
            // Go to specific slide
            function goToSlide(index) {
                currentIndex = Math.max(0, Math.min(index, items.length - visibleItems));
                updateCarousel();
            }
            
            // Next slide
            function nextSlide() {
                if (currentIndex < items.length - visibleItems) {
                    currentIndex++;
                    updateCarousel();
                }
            }
            
            // Previous slide
            function prevSlide() {
                if (currentIndex > 0) {
                    currentIndex--;
                    updateCarousel();
                }
            }
            
            // Event listeners for buttons
            nextBtn.addEventListener('click', nextSlide);
            prevBtn.addEventListener('click', prevSlide);
            
            // Handle window resize
            window.addEventListener('resize', () => {
                const newVisibleItems = Math.floor(carousel.offsetWidth / itemWidth);
                if (currentIndex > items.length - newVisibleItems) {
                    currentIndex = Math.max(0, items.length - newVisibleItems);
                    updateCarousel();
                }
            });
        }
    </script>
    <script>
// Register Service Worker
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('/sw.js')
            .then(function(registration) {
                console.log('ServiceWorker registration successful');
            })
            .catch(function(error) {
                console.log('ServiceWorker registration failed: ', error);
            });
    });
}

// Handle Add to Home Screen prompt
let deferredPrompt;
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    
    // Show install button (optional)
    showInstallPrompt();
});

function showInstallPrompt() {
    // Your custom install button logic
    console.log('App can be installed');
}
</script>
</body>
</html>