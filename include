<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title><?= htmlspecialchars($user['name']) ?> | <?= htmlspecialchars($business_info['business_name'] ?? '') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-81W5S4MMGY"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'G-81W5S4MMGY');
    </script>


    <link href="assets/css/main.css" rel="stylesheet">
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
        body {
            background-color: var(--secondary-color);
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

    <div class="main">

