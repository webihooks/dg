<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title><?= htmlspecialchars($business_info['business_name'] ?? '') ?></title>

    <meta name="description" content="<?= htmlspecialchars($business_info['business_description']) ?>">
    <link rel="icon" type="image/png" href="https://deegeecard.com/uploads/profile/<?= htmlspecialchars($photos['profile_photo']) ?>">

    <!-- Open Graph Tags (for social media sharing) -->
    <meta property="og:title" content="<?= htmlspecialchars($business_info['business_name'] ?? '') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($business_info['business_description']) ?>">
    <meta property="og:image" content="https://deegeecard.com/uploads/profile/<?= htmlspecialchars($photos['profile_photo']) ?>">
    <meta property="og:type" content="restaurant">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($business_info['business_name'] ?? '') ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($business_info['business_description']) ?>">
    <meta name="twitter:image" content="https://deegeecard.com/uploads/profile/<?= htmlspecialchars($photos['profile_photo']) ?>">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css?<?php echo time(); ?>" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css?<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-81W5S4MMGY"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'G-81W5S4MMGY');
    </script>


    <link href="https://deegeecard.com/assets/css/main.css?<?php echo time(); ?>" rel="stylesheet">
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
                // burgerMenu?.classList.remove('show');
            }
        });
        
        function sendProductEnquiry(productName, productPrice, productDescription) {
            const whatsappLink = "<?= $social_link['whatsapp'] ?? '' ?>";
            let phoneNumber = whatsappLink.match(/wa\.me\/(\d+)/)?.[1] || "<?= $user['phone'] ?? '' ?>";
            
            if (phoneNumber) {
                const message = `Product Enquiry:\n\n*Product Name:* ${productName}\n*Price:* ₹${productPrice}\n*Description:* ${productDescription}\n\nI'm interested in this product. Please provide more details.`;
                window.open(`https://wa.me/${phoneNumber}?text=${encodeURIComponent(message)}`, '_blank');
            }
        }
        
        function sendServiceEnquiry(serviceName, servicePrice, serviceDescription, serviceDuration) {
            const whatsappLink = "<?= $social_link['whatsapp'] ?? '' ?>";
            let phoneNumber = whatsappLink.match(/wa\.me\/(\d+)/)?.[1] || "<?= $user['phone'] ?? '' ?>";
            
            if (phoneNumber) {
                const message = `Service Enquiry:\n\n*Service Name:* ${serviceName}\n*Price:* ₹${servicePrice}\n*Duration:* ${serviceDuration}\n*Description:* ${serviceDescription}\n\nI'm interested in this service. Please provide more details.`;
                window.open(`https://wa.me/${phoneNumber}?text=${encodeURIComponent(message)}`, '_blank');
            }
        }
        
        function scrollToSection(sectionClass) {
            const section = document.querySelector(`.${sectionClass}`);
            if (section) {
                const burger = document.querySelector('.burger-menu');
                const overlay = document.getElementById('menuOverlay');
                burger?.classList.remove('change');
                overlay?.classList.remove('active');
                
                const offsetPosition = section.getBoundingClientRect().top + window.pageYOffset - 100;
                window.scrollTo({ top: offsetPosition, behavior: 'smooth' });
            }
        }
        
        function toggleMenu() {
            const burger = document.querySelector('.burger-menu');
            const overlay = document.getElementById('menuOverlay');
            burger?.classList.toggle('change');
            overlay?.classList.toggle('active');
        }
        
        function showQrModal(paymentType, imageSrc) {
            document.getElementById('qrModalTitle').textContent = paymentType + ' QR Code';
            document.getElementById('modalQrImage').src = imageSrc;
            document.getElementById('payNowLink').href = 'upi://pay?pa=' + encodeURIComponent('<?= $qr_codes[0]["mobile_number"] ?? "" ?>');
            new bootstrap.Modal(document.getElementById('qrModal')).show();
        }
        
        // Product search functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('productSearch');
            const clearSearch = document.getElementById('clearSearch');
            const productItems = document.querySelectorAll('.product-item');
            
            searchInput?.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                productItems.forEach(item => {
                    const matches = item.getAttribute('data-name').includes(searchTerm) || 
                                  item.getAttribute('data-desc').includes(searchTerm);
                    item.style.display = matches ? 'block' : 'none';
                });
            });
            
            clearSearch?.addEventListener('click', function() {
                searchInput.value = '';
                productItems.forEach(item => item.style.display = 'block');
            });
        });

    </script>
    
<script>
// Detect Android WebView
function isAndroidWebView() {
    return navigator.userAgent.toLowerCase().indexOf("wv") > -1 || 
           (navigator.userAgent.toLowerCase().indexOf("android") > -1 && 
            navigator.userAgent.toLowerCase().indexOf("chrome") === -1);
}

// Hide download button if in Android WebView
if (isAndroidWebView()) {
    document.addEventListener('DOMContentLoaded', function() {
        var downloadBtn = document.querySelector('.download_btn');
        if (downloadBtn) {
            downloadBtn.style.display = 'none';
        }
    });
}
</script>

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
        /*.btn-success {
            background: var(--primary-color) !important;
            height: 50px;
          font-size: 20px;
          font-weight: 700;
          text-shadow: 2px 1px 3px #151515;
          letter-spacing: 0.03em;
        }*/
        body {
            background-color: var(--secondary-color);
        }
        .burger-menu {
            background: var(--secondary-color);
        }
        .discount-card {
          background: var(--secondary-color);
        }
        .offer_popup .btn-close-black {
            background: var(--secondary-color);
        }
        .btn:hover, .btn-check:checked + .btn, .btn.active, .btn.show, .btn:first-child:active, :not(.btn-check) + .btn:active {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }
        /* Loader Styles */
        .loader-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.6);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.5s ease;
        }
        
        .loader {
            width: 48px;
            height: 48px;
            border: 5px solid <?php echo $primary_color; ?>;
            border-bottom-color: transparent;
            border-radius: 50%;
            display: inline-block;
            box-sizing: border-box;
            animation: rotation 1s linear infinite;
        }
        
        .img-loading-spinner {
            border-top-color: <?php echo $primary_color; ?>;
        }
        @keyframes rotation {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }
        body.loading {
            overflow: hidden;
            height: 100vh;
        }
        .bouncing-loader {
            display: flex;
            gap: 8px;
        }
        .bouncing-loader div {
            width: 12px;
            height: 12px;
            background: <?php echo $primary_color; ?>;
            border-radius: 50%;
            animation: bounce 0.6s infinite alternate;
        }
        .bouncing-loader div:nth-child(2) {
            animation-delay: 0.2s;
        }
        .bouncing-loader div:nth-child(3) {
            animation-delay: 0.4s;
        }
        @keyframes bounce {
            to { transform: translateY(-12px); }
        }
        .tag-btn.active {
            background: <?= $primary_color ?>;
            color: white;
            border-color: <?= $primary_color ?>;
        }
        .rating-input .form-check-input:checked + .form-check-label {
            background-color: <?php echo $primary_color; ?>;
            color: #fff;
        }
        a {
            color: <?php echo $primary_color; ?>;
        }
        .download_btn {
            width: 100%;
            margin: 20px 0 0 0;
            border-radius: 10px;
            font-size: 12px;
            background-color: <?php echo $primary_color; ?>;
            color: #fff;
            padding: 12px 0;
            text-shadow: 2px 2px 2px #3E3E3E;
        }
        

#deliveryBtn, #dinningBtn, #placeOrderBtn {
    cursor: pointer;
    background-color: <?php echo $primary_color; ?>;
    border: 2px solid <?php echo $primary_color; ?>;
    text-align: center;
    transition: 0.3s;
    color: #fff;
    transform: scale(1);
    animation: borderPulse 2s infinite;
    float: left;
}

#deliveryBtn, #dinningBtn {
    font-size: 15px;
}

#placeOrderBtn {
  height: 50px;
  font-size: 20px;
  font-weight: bold;
  text-shadow: 2px 2px 2px #3E3E3E;
}

.order-type-buttons .w-50 {
  width: 45.5% !important;
  margin: 0px 2.2%;
}

@keyframes borderPulse {
    0% {
        box-shadow: 0 0 0 0 <?php echo $primary_color; ?>b3;
    }
    70% {
        box-shadow: 0 0 0 10px <?php echo $primary_color; ?>00;
    }
    100% {
        box-shadow: 0 0 0 0 <?php echo $primary_color; ?>00;
    }
}
.choose_order_type {
  text-align: center;
  font-size: 15px;
  margin-bottom: 6px;
  font-weight: bold;
}
.back-to-home-btn {
    position: fixed;
    top: 15px;
    right: 66px;
    z-index: 99;
    background: <?= $primary_color ?>;
    color: #fff;
    padding: 5px 18px;
    border-radius: 5px;
    font-size: 14px;
    font-weight: bold;
    text-decoration: none;
    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    transition: background 0.3s;
}
.tag-btn {
    border-color:<?= $primary_color ?>;
    background-color: <?= $primary_color ?>;
    opacity: 0.6;
    color: #fff;
}
.tag-btn:hover {
    border-color:<?= $primary_color ?>;
    background-color: <?= $primary_color ?>;
    opacity: 1;
    color: #fff;
}
.tag-btn.active {
    opacity: 1;
}
#productSearch {
    border:2px solid <?= $primary_color ?>;
}
#clearSearch {
    border-color:<?= $primary_color ?>;
    background-color: <?= $primary_color ?>;
    color: #fff;
}


    </style>
</head>
<body class="restaurant">
<?php if ($show_subscription_popup): ?>
<!-- Overlay -->
<div class="overlay" id="subscriptionOverlay"></div>

<!-- Subscription Popup -->
<div class="subscription-popup" id="subscriptionPopup">
    <!-- <button type="button" class="btn-close" onclick="closeSubscriptionPopup()"></button> -->
    <h3>Subscription Expired</h3>
    <p>You don't have any active subscription. Please subscribe to continue using our services.</p>
    <button class="btn btn-primary" onclick="redirectToSubscription()">Subscribe Now</button>
</div>

<script>
    // Show the popup when page loads
    window.onload = function() {
        document.getElementById('subscriptionOverlay').style.display = 'block';
        document.getElementById('subscriptionPopup').style.display = 'block';
    };
    
    function closeSubscriptionPopup() {
        document.getElementById('subscriptionOverlay').style.display = 'none';
        document.getElementById('subscriptionPopup').style.display = 'none';
    }
    
    function redirectToSubscription() {
        // Replace with your actual subscription page URL
        window.location.href = 'login.php';
    }
</script>
<?php endif; ?>

<?php if (in_array($user_id, [67, 70, 71, 72, 73])): ?>
    <a href="https://deegeecard.com/pawankoliwada" 
       class="back-to-home-btn">
       Back to Home
    </a>
<?php endif; ?>

<?php if (in_array($user_id, [77, 78, 79, 80, 81])): ?>
    <a href="https://biryanibybulk.com" 
       class="back-to-home-btn">
       Back to Home
    </a>

    <style>
        .back-to-home-btn {
            background:#ffaa53;
            color:#000;
        }
    </style>
<?php endif; ?>


    <div class="main">

