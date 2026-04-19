<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title>DEEGEECARD | Zero Commissions + Borzo Delivery Integration</title>

    <meta name="description" content="DEEGEECARD - Your own branded food ordering system with zero commission and integrated Borzo delivery partner.">
    <link rel="icon" type="image/png" href="https://deegeecard.com/images/dg_logo.png">
    <meta property="og:title" content="DEEGEECARD + Borzo Delivery">
    <meta property="og:description" content="Get Orders Directly, Zero Commissions! Integrated Borzo delivery partner.">
    <meta property="og:image" content="https://deegeecard.com/images/dg_logo.png">
    <meta property="og:type" content="restaurant">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="DEEGEECARD + Borzo">
    <meta name="twitter:description" content="Get Orders Directly, Zero Commissions! Plus Borzo delivery integration.">
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
                        borzo: '#1A1A3A',
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes slideUpFadeIn{0%{opacity:0;transform:translateY(40px)}100%{opacity:1;transform:translateY(0)}}nav a{padding:8px 0}.animate-slide-up{animation:1.2s ease-out forwards slideUpFadeIn}.phone-frame{position:relative;width:400px;height:750px;margin:0 auto;border-radius:40px;box-shadow:0 30px 60px -12px rgba(0,0,0,.25),0 18px 36px -18px rgba(0,0,0,.3),inset 0 -2px 6px 0 rgba(0,0,0,.1);overflow:hidden;background:#111;opacity:0}.phone-screen{position:absolute;top:12px;left:12px;right:12px;bottom:12px;border-radius:30px;overflow:hidden;background:#fff}.phone-button,.phone-notch{position:absolute;left:50%;transform:translateX(-50%)}.phone-notch{top:12px;width:50%;height:25px;background:#111;border-radius:0 0 12px 12px;z-index:10}.phone-iframe{width:100%;height:100%;border:none}.phone-button{bottom:12px;width:40px;height:4px;background:#333;border-radius:4px}.mobile-menu{max-height:0;overflow:hidden;transition:max-height .3s ease-out}.mobile-menu.open{max-height:300px}.clients-section{padding:4rem 0;overflow:hidden;position:relative}.client-carousel{display:flex;transition:transform .5s;padding:2rem 0}.client-item{flex:0 0 auto;margin:0 1rem;transition:transform .3s}.carousel-nav,.feature-icon{display:flex;align-items:center}.client-item:hover{transform:scale(1.05)}.client-logo{width:200px;height:200px;object-fit:contain;border-radius:10px;background:#fff;box-shadow:0 5px 15px rgba(0,0,0,.1)}.carousel-dot,.carousel-nav{border-radius:50%;cursor:pointer}.carousel-container{position:relative;overflow:hidden;width:100%}.carousel-nav{position:absolute;top:50%;transform:translateY(-50%);background:#fff;width:50px;height:50px;margin-top:-12px;justify-content:center;box-shadow:0 2px 10px rgba(0,0,0,.1);z-index:20;transition:.3s}.carousel-nav:hover{background:#f1f5f9;transform:translateY(-50%) scale(1.05)}.carousel-nav.prev{left:20px}.carousel-nav.next{right:20px}.carousel-nav i{font-size:1.5rem;color:#fb5b29}.carousel-dots{display:flex;justify-content:center;margin-top:10px}.carousel-dot{width:12px;height:12px;background:#cbd5e1;margin:0 5px;transition:background .3s}.carousel-dot.active{background:#fb5b29}.restaurant-section{background:linear-gradient(to right,#fb5b29,#fbbd29);color:#fff;padding:5rem 0}.feature-icon{width:60px;height:60px;background:rgba(255,255,255,.1);border-radius:12px;justify-content:center;margin-bottom:1rem}.feature-card{background:rgba(255,255,255,.05);border-radius:16px;padding:2rem;height:100%;transition:.3s;border:1px solid rgba(255,255,255,.1)}.feature-card:hover{transform:translateY(-5px);background:rgba(255,255,255,.1);box-shadow:0 10px 30px rgba(0,0,0,.2)}.cta-button{background:linear-gradient(to right,#fbbd29,#fb5b29);color:#fff;font-weight:700;padding:1rem 2rem;border-radius:50px;display:inline-block;transition:.3s;border:5px solid #fff}.cta-button:hover{transform:scale(1.05);box-shadow:0 10px 20px rgba(0,0,0,.2)}.footer{background:#111827;color:#fff;padding:4rem 0 2rem}.footer-heading{font-size:1.25rem;font-weight:600;margin-bottom:1.5rem;position:relative}.footer-heading:after{content:'';position:absolute;bottom:-.5rem;left:0;width:40px;height:3px;background:#fb5b29}.footer-link{display:block;margin-bottom:.75rem;color:#d1d5db;transition:.3s}.footer-link:hover{color:#fb5b29;transform:translateX(5px)}.newsletter-input{background:rgba(255,255,255,.1);border:none;padding:.75rem 1rem;border-radius:.375rem 0 0 .375rem;color:#fff;width:100%}.newsletter-input:focus{outline:0;background:rgba(255,255,255,.15)}.newsletter-btn{background:#fb5b29;color:#fff;border:none;padding:.75rem 1.25rem;border-radius:0 .375rem .375rem 0;cursor:pointer;transition:.3s}.newsletter-btn:hover{background:#fbbd29}.social-icon{width:40px;height:40px;display:inline-flex;align-items:center;justify-content:center;background:rgba(255,255,255,.1);border-radius:50%;margin-right:.75rem;transition:.3s}.social-icon:hover{background:#fb5b29;transform:translateY(-3px)}.borzo-highlight{border:2px solid rgba(255,255,255,0.3);background:linear-gradient(135deg,rgba(0,0,0,0.2),rgba(255,255,255,0.05));backdrop-filter:blur(2px)}.borzo-partner-badge{background:#1A1A3A;color:white;border-radius:40px;padding:0.4rem 1.2rem;display:inline-flex;align-items:center;gap:8px;font-weight:600;box-shadow:0 4px 12px rgba(0,0,0,0.2)}.borzo-logo-white{filter:brightness(0) invert(1)}/* Floating Icons Animation */
        

.floating-icon {
    position: absolute;
    font-size: 1.8rem;
    opacity: 0.75;
    z-index: 5;
    pointer-events: none;
    animation: floatAround 8s infinite ease-in-out;
    filter: drop-shadow(0 4px 8px rgba(0,0,0,0.15));
    transition: transform 0.2s;
}
@keyframes floatAround {
    0% { transform: translateY(0px) translateX(0) rotate(0deg); }
    50% { transform: translateY(-18px) translateX(6px) rotate(3deg); }
    100% { transform: translateY(0px) translateX(0) rotate(0deg); }
}
/* Different delays */
.float-delay-1 { animation-delay: 0s; }
.float-delay-2 { animation-delay: 1.3s; }
.float-delay-3 { animation-delay: 2.7s; }
.float-delay-4 { animation-delay: 0.8s; }
.float-delay-5 { animation-delay: 3.2s; }
.float-delay-6 { animation-delay: 1.9s; }

@media (max-width: 768px) {
    .floating-icon {
        font-size: 1.2rem;
        opacity: 0.6;
    }
}        
    </style>
</head>
<body>

<!-- Header with navigation -->
<header class="bg-white shadow-md sticky top-0 z-50">
    <div class="container mx-auto px-4 py-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <img src="images/deegeecard_logo.png" alt="DeeGeeCard Logo" class="h-10 md:h-12">
            </div>
            <nav class="hidden md:flex space-x-8">
                <a href="index.php" class="text-gray-600 hover:text-primary font-medium">Home</a>
                <a href="contact.php" class="text-gray-600 hover:text-primary font-medium">Contact</a>
                <a href="help.php" class="text-gray-600 hover:text-primary font-medium">Help</a>
                <a href="login.php" class="text-gray-600 hover:text-primary font-medium">Login</a>
            </nav>
            <button id="mobile-menu-button" class="md:hidden text-gray-600">
                <i class="fas fa-bars text-2xl"></i>
            </button>
        </div>
        <div id="mobile-menu" class="mobile-menu md:hidden">
            <div class="flex flex-col space-y-4 pb-4">
                <a href="index.php" class="text-gray-600 hover:text-primary font-medium py-2">Home</a>
                <a href="contact.php" class="text-gray-600 hover:text-primary font-medium py-2">Contact</a>
                <a href="help.php" class="text-gray-600 hover:text-primary font-medium py-2">Help</a>
                <a href="login.php" class="text-gray-600 hover:text-primary font-medium py-2">Login</a>
            </div>
        </div>
    </div>
</header>

<!-- Hero Section with Floating Icons (bg-gradient-to-r from-primary to-secondary) -->
<section class="bg-gradient-to-r from-primary to-secondary text-white py-10 md:py-24 relative overflow-hidden">
    <div class="container mx-auto px-4 relative z-10">
        <div class="flex flex-col md:flex-row items-center">
            <div class="md:w-1/2 mb-10 md:mb-0 text-center md:text-left">
                <div class="inline-block mb-4">
                    <div class="borzo-partner-badge">
                        <i class="fas fa-truck-fast"></i> 
                        <span>Official Delivery Partner:</span>
                        <img src="https://borzodelivery.com/img/global/logo.svg" alt="Borzo" class="h-5 ml-1">
                    </div>
                </div>
                <h1 class="text-4xl md:text-6xl font-bold mb-6">Get Orders Directly, <br> Zero Commissions!!! </h1>
                <p class="text-xl mb-4 max-w-2xl mx-auto md:mx-0">Introducing DeeGeeCard – Your own branded food ordering system with <span class="font-bold">ZERO commission, forever!</span> <strong class="block mt-2 text-yellow-200">🚚 Integrated with Borzo Delivery – hassle-free logistics!</strong></p>
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

<!-- Floating Icons Set - Evenly distributed across hero banner -->
<div class="floating-icon float-delay-1" style="top: 5%; left: 2%;"><i class="fas fa-shopping-cart"></i></div>
<div class="floating-icon float-delay-2" style="top: 15%; right: 4%;"><i class="fab fa-whatsapp"></i></div>
<div class="floating-icon float-delay-3" style="bottom: 10%; left: 6%;"><i class="fab fa-instagram"></i></div>
<div class="floating-icon float-delay-4" style="top: 30%; left: 1%;"><i class="fab fa-facebook-f"></i></div>
<div class="floating-icon float-delay-5" style="bottom: 30%; right: 5%;"><i class="fab fa-youtube"></i></div>
<div class="floating-icon float-delay-1" style="top: 60%; right: 10%;"><i class="fas fa-receipt"></i></div>
<div class="floating-icon float-delay-2" style="top: 10%; right: 20%;"><i class="fas fa-tag"></i></div>
<div class="floating-icon float-delay-3" style="bottom: 45%; left: 10%;"><i class="fas fa-truck"></i></div>
<div class="floating-icon float-delay-4" style="top: 80%; left: 15%;"><i class="fas fa-gift"></i></div>
<div class="floating-icon float-delay-5" style="bottom: 5%; right: 25%;"><i class="fas fa-phone-alt"></i></div>
<div class="floating-icon float-delay-6" style="top: 25%; left: 12%;"><i class="fas fa-map-marker-alt"></i></div>
<div class="floating-icon float-delay-1" style="bottom: 65%; right: 2%;"><i class="fas fa-robot"></i></div>
<div class="floating-icon float-delay-2" style="top: 50%; left: 4%;"><i class="fas fa-pizza-slice"></i></div>
<div class="floating-icon float-delay-3" style="bottom: 75%; right: 15%;"><i class="fas fa-hamburger"></i></div>
<div class="floating-icon float-delay-4" style="top: 88%; right: 8%;"><i class="fab fa-android"></i></div>
<div class="floating-icon float-delay-5" style="bottom: 20%; left: 18%;"><i class="fas fa-file-invoice-dollar"></i></div>
<div class="floating-icon float-delay-6" style="top: 42%; right: 28%;"><i class="fas fa-ice-cream"></i></div>
<!-- Floating Icons Set - Evenly distributed across hero banner -->

</section>

<!-- Client Logos Section -->
<section class="clients-section">
    <div class="container mx-auto px-4">
        <h2 class="text-3xl font-bold text-center">Our Valued Clients</h2>
        <div class="carousel-container">
            <div class="carousel-nav prev"><i class="fas fa-chevron-left"></i></div>
            <div class="carousel-nav next"><i class="fas fa-chevron-right"></i></div>
            <?php
            $clientLogos = [];
            try {
                require 'config/index_db_connection.php';
                if (isset($pdo) && $pdo instanceof PDO) {
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
            <div class="feature-card"><div class="feature-icon"><i class="fas fa-globe text-2xl text-white"></i></div><h3 class="text-xl font-bold mb-4">Your Own Ordering Website</h3><p class="text-white">Just like your current Delivery Partner App, but branded for YOUR restaurant. Zero commission fees.</p></div>
            <div class="feature-card"><div class="feature-icon"><i class="fas fa-mobile-alt text-2xl text-white"></i></div><h3 class="text-xl font-bold mb-4">Admin Management App for Desktop & Mobile</h3><p class="text-white">Accept/reject orders, update menus & prices in real-time from your phone.</p></div>
            <div class="feature-card"><div class="feature-icon"><i class="fab fa-android text-2xl text-white"></i></div><h3 class="text-xl font-bold mb-4">Your Own Android App</h3><p class="text-white">Increase loyalty with a seamless app under your restaurant's name.<br><span style="float:right;">*Condition apply</span></p></div>
            <div class="feature-card"><div class="feature-icon"><i class="fas fa-receipt text-2xl text-white"></i></div><h3 class="text-xl font-bold mb-4">KOT & Bill Printing</h3><p class="text-white">Generate kitchen order tickets and bills in just one click.</p></div>
            <div class="feature-card"><div class="feature-icon"><i class="fas fa-cogs text-2xl text-white"></i></div><h3 class="text-xl font-bold mb-4">Full Store Control</h3><p class="text-white">Set store timings, delivery charges, GST, discounts, coupon codes, and menu categories easily.</p></div>
            <div class="feature-card"><div class="feature-icon"><i class="fas fa-qrcode text-2xl text-white"></i></div><h3 class="text-xl font-bold mb-4">Personalized Scan-to-Order QR Cards & Table Standees</h3><p class="text-white">1000 Personalized Scan-to-Order QR Cards & 8 table standees to turn every table into a self-ordering station.</p></div>
            <div class="feature-card"><div class="feature-icon"><i class="fab fa-whatsapp text-2xl text-white"></i></div><h3 class="text-xl font-bold mb-4">Bulk WhatsApp Marketing</h3><p class="text-white">FREE 1 month Bulk WhatsApp Marketing App to send offers directly to customers.</p></div>
            <div class="feature-card"><div class="feature-icon"><i class="fas fa-credit-card text-2xl text-white"></i></div><h3 class="text-xl font-bold mb-4">Direct Payments</h3><p class="text-white">Receive money via UPI/Cards instantly with 0% platform fee directly to your account.</p></div>
            <div class="feature-card"><div class="feature-icon"><i class="fas fa-comment-dots text-2xl text-white"></i></div><h3 class="text-xl font-bold mb-4">Reply to Reviews Instantly</h3><p class="text-white">Respond to customer reviews directly via WhatsApp in one click.</p></div>
        </div>

        <!-- BORZO DELIVERY PARTNER HIGHLIGHT SECTION - MAIN FEATURE -->
        <div class="bg-white rounded-2xl overflow-hidden shadow-2xl mb-16 transform transition-all hover:scale-[1.01] duration-300 border-b-8 border-primary">
            <div class="p-1 bg-gradient-to-r from-primary to-secondary"></div>
            <div class="p-6 md:p-8">
                <div class="flex flex-col lg:flex-row items-center gap-8">
                    <div class="flex-shrink-0 bg-gray-50 p-4 rounded-2xl shadow-md">
                        <img src="https://borzodelivery.com/img/global/logo.svg" alt="Borzo Official Delivery Partner" class="h-20 md:h-24 w-auto object-contain">
                    </div>
                    <div class="flex-1 text-center lg:text-left">
                        <div class="inline-block bg-primary/10 text-primary px-4 py-1 rounded-full text-sm font-semibold mb-3">⭐ EXCLUSIVE INTEGRATION</div>
                        <h3 class="text-2xl md:text-3xl font-extrabold text-gray-800 mb-3">Seamless Borzo Delivery Integration</h3>
                        <p class="text-gray-700 text-lg mb-4">Connect your DeeGeeCard ordering system directly with <strong class="text-primary">Borzo (formerly Wefast)</strong> — India’s most reliable on-demand delivery network. Dispatch orders instantly, get real-time tracking, and expand your delivery zone without managing your own fleet.</p>
                        <div class="grid grid-cols-1 md:grid-cols-1 gap-4 mt-4">
                            <div class="flex items-center gap-2" style="color:rgb(251 91 41 / var(--tw-text-opacity, 1));"><i class="fas fa-bolt text-primary"></i><span>Real-time dispatching</span></div>
                            <div class="flex items-center gap-2" style="color:rgb(251 91 41 / var(--tw-text-opacity, 1));"><i class="fas fa-map-marked-alt text-primary"></i><span>Live order tracking</span></div>
                            <div class="flex items-center gap-2" style="color:rgb(251 91 41 / var(--tw-text-opacity, 1));"><i class="fas fa-rupee-sign text-primary"></i><span>Pay only delivery fee – 0% commission</span></div>
                        </div>
                    </div>
                    <div class="flex-shrink-0">
                        <div class="bg-orange-50 rounded-xl p-4 text-center border border-orange-200">
                            <i class="fas fa-truck-fast text-3xl text-primary mb-2 block"></i>
                            <span class="text-sm font-bold text-gray-700">Delivery Partner</span>
                            <div class="text-xs text-gray-500 mt-1">No hidden costs</div>
                        </div>
                    </div>
                </div>
                <div class="mt-6 pt-4 border-t border-gray-100 text-sm text-gray-500 flex flex-wrap justify-between items-center">
                    <span><i class="fas fa-check-circle text-green-500"></i> Auto-assign nearest Borzo partner</span>
                    <span><i class="fas fa-chart-line text-green-500"></i> Boost delivery radius up to 15km</span>
                    <a href="https://borzodelivery.com" target="_blank" class="text-primary font-medium hover:underline">Learn more about Borzo →</a>
                </div>
            </div>
        </div>

        <!-- Plus FREE Integrations -->
        <div class="bg-white text-gray-800 rounded-2xl p-8 mb-12">
            <h3 class="text-2xl font-bold mb-6 text-center">Plus FREE Integrations</h3>
            <p class="text-center mb-6">Google, Instagram, Facebook, YouTube and Maps so customers find you easily with just a click.</p>
            <div class="flex flex-wrap justify-center gap-6 mb-8">
                <div class="flex items-center"><i class="fab fa-google text-3xl text-blue-500 mr-2"></i><span>Google</span></div>
                <div class="flex items-center"><i class="fab fa-instagram text-3xl text-pink-500 mr-2"></i><span>Instagram</span></div>
                <div class="flex items-center"><i class="fab fa-facebook text-3xl text-blue-600 mr-2"></i><span>Facebook</span></div>
                <div class="flex items-center"><i class="fab fa-youtube text-3xl text-red-600 mr-2"></i><span>YouTube</span></div>
                <div class="flex items-center"><i class="fas fa-map-marked-alt text-3xl text-green-500 mr-2"></i><span>Maps</span></div>
            </div>
            <p class="text-center font-bold text-lg">Stop paying commissions. Start keeping 100% of your profits.</p>
        </div>

        <!-- Pricing + CTA with Borzo mention -->
        <div class="text-center mb-12">
            <h3 class="text-5xl font-bold mb-6">All this for just ₹14,999/year <span style="text-decoration: line-through; opacity: 0.5;">₹20,000</span></h3>
            <p class="text-3xl mb-8">We set everything up for you. Go live the same day!</p>
            <div class="flex flex-col sm:flex-row justify-center gap-6 items-center">
                <a href="tel:+919819411026" class="cta-button">📞 CALL US NOW TO GET STARTED</a>
                <a href="https://wa.me/919819411026?text=Hi%20Team%20DeeGeeCard%20%F0%9F%91%8B%2C%20I%E2%80%99m%20ready%20to%20grow%20my%20restaurant%20with%20ZERO%20commission%20orders%20and%20Borzo%20delivery%20integration!%20Please%20help%20me%20get%20started." target="_blank" class="cta-button">💬 WHATSAPP US NOW</a>
            </div>
            <div class="mt-6 inline-flex items-center gap-2 bg-black/20 backdrop-blur-sm rounded-full px-5 py-2 text-sm font-medium">
                <i class="fas fa-truck"></i> <span>✅ Includes Borzo Delivery Partner integration ready</span>
            </div>
        </div>
    </div>
</section>

<!-- Footer Section -->
<footer class="footer">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-12">
            <div>
                <h3 class="footer-heading">About DeeGeeCard</h3>
                <p class="text-gray-400 mb-6">DeeGeeCard empowers restaurants to go fully digital with their own branded ordering website and mobile app — no commissions, no middlemen. In just 60 minutes, we set up your ordering platform, admin app, QR code menus, and WhatsApp marketing tools. Now with <strong class="text-primary">Borzo Delivery Integration</strong> for seamless logistics.</p>
                <div class="flex">
                    <a href="https://www.facebook.com/deegeecard" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                    <a href="https://www.instagram.com/deegeecard" class="social-icon"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            <div>
                <h3 class="footer-heading">Quick Links</h3>
                <a href="index.php" class="footer-link">Home</a>
                <a href="contact.php" class="footer-link">Contact us</a>
                <a href="help.php" class="footer-link">Help</a>
            </div>
            <div>
                <h3 class="footer-heading">Support</h3>
                <a href="terms-and-conditions.php" class="footer-link">Terms & conditions</a>
                <a href="privacy-policy.php" class="footer-link">Privacy policy</a>
                <a href="cancellation-refund-policy.php" class="footer-link">Cancellation & Refund Policy</a>
                <a href="shipping-delivery.php" class="footer-link">Shipping and Delivery</a>
            </div>
            <div>
                <h3 class="footer-heading">Subscribe us</h3>
                <p class="text-gray-400 mb-4">Subscribe our newsletter to receive latest updates regularly from us!</p>
                <div class="flex mb-2">
                    <input type="email" placeholder="Your email address" class="newsletter-input">
                    <button class="newsletter-btn"><i class="fas fa-paper-plane"></i></button>
                </div>
                <p class="text-xs text-gray-500">By clicking send link you agree to receive messages.</p>
            </div>
        </div>
        <div class="border-t border-gray-800 pt-6 pb-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <p class="text-gray-400 text-sm mb-4 md:mb-0">© 2025 DeeGeeCard. All rights reserved. | Delivery partner: Borzo</p>
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
    document.addEventListener('DOMContentLoaded', function() {
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');
        const menuIcon = mobileMenuButton.querySelector('i');
        mobileMenuButton.addEventListener('click', function() {
            mobileMenu.classList.toggle('open');
            if (mobileMenu.classList.contains('open')) {
                menuIcon.classList.remove('fa-bars');
                menuIcon.classList.add('fa-times');
            } else {
                menuIcon.classList.remove('fa-times');
                menuIcon.classList.add('fa-bars');
            }
        });
        const phone = document.querySelector('.phone-frame');
        if(phone) phone.classList.add('animate-slide-up');
        document.addEventListener('click', function(event) {
            if (!mobileMenu.contains(event.target) && !mobileMenuButton.contains(event.target)) {
                mobileMenu.classList.remove('open');
                if(menuIcon) {
                    menuIcon.classList.remove('fa-times');
                    menuIcon.classList.add('fa-bars');
                }
            }
        });
        initCarousel();
    });

    function initCarousel() {
        const carousel = document.querySelector('.client-carousel');
        if(!carousel) return;
        const items = document.querySelectorAll('.client-item');
        if(items.length === 0) return;
        const prevBtn = document.querySelector('.carousel-nav.prev');
        const nextBtn = document.querySelector('.carousel-nav.next');
        const dotsContainer = document.querySelector('.carousel-dots');
        let currentIndex = 0;
        const itemWidth = items[0].offsetWidth + parseInt(getComputedStyle(items[0]).marginRight) * 2;
        const visibleItems = Math.floor(carousel.offsetWidth / itemWidth);
        dotsContainer.innerHTML = '';
        items.forEach((_, index) => {
            const dot = document.createElement('div');
            dot.classList.add('carousel-dot');
            if (index === 0) dot.classList.add('active');
            dot.addEventListener('click', () => goToSlide(index));
            dotsContainer.appendChild(dot);
        });
        const dots = document.querySelectorAll('.carousel-dot');
        function updateCarousel() {
            carousel.style.transform = `translateX(-${currentIndex * itemWidth}px)`;
            dots.forEach((dot, index) => { dot.classList.toggle('active', index === currentIndex); });
        }
        function goToSlide(index) {
            currentIndex = Math.max(0, Math.min(index, items.length - visibleItems));
            updateCarousel();
        }
        function nextSlide() { if (currentIndex < items.length - visibleItems) { currentIndex++; updateCarousel(); } }
        function prevSlide() { if (currentIndex > 0) { currentIndex--; updateCarousel(); } }
        if(nextBtn) nextBtn.addEventListener('click', nextSlide);
        if(prevBtn) prevBtn.addEventListener('click', prevSlide);
        window.addEventListener('resize', () => {
            const newItemWidth = items[0].offsetWidth + parseInt(getComputedStyle(items[0]).marginRight) * 2;
            const newVisible = Math.floor(carousel.offsetWidth / newItemWidth);
            if (currentIndex > items.length - newVisible) currentIndex = Math.max(0, items.length - newVisible);
            carousel.style.transform = `translateX(-${currentIndex * newItemWidth}px)`;
        });
    }

    // Service Worker
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('/sw.js').then(reg => console.log('SW registered')).catch(err => console.log('SW failed', err));
        });
    }
    let deferredPrompt;
    window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); deferredPrompt = e; console.log('App installable'); });
</script>
</body>
</html>