<?php
   require 'config/db_connection.php';
   // require 'functions/profile_functions.php';
   
   
   
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
       $stmt = $conn->prepare("SELECT business_name, business_description, business_address, google_direction, designation FROM business_info WHERE user_id = ?");
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
       $stmt = $conn->prepare("SELECT id, mobile_number, upload_qr_code, payment_type, is_default FROM qrcode_details WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
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
   // require 'includes/header.php';
   // require 'includes/navigation.php';
   // require 'includes/profile_header.php';
   // require 'includes/business_info.php';
   // require 'includes/products.php';
   // require 'includes/services.php';
   // require 'includes/gallery.php';
   // require 'includes/ratings.php';
   // require 'includes/bank_details.php';
   // require 'includes/qr_codes.php';
   // require 'includes/share_section.php';
   // require 'includes/footer.php';
   
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
   </head>
   <body class="restaurant">
      <div class="main">








<!-- products -->
<div class="products">
    <h6>Products</h6>
    
    <div class="mb-3">
        <div class="input-group">
            <input type="text" id="productSearch" class="form-control" placeholder="Search products...">
            <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                <i class="bi bi-x"></i>
            </button>
        </div>
    </div>
    
    



<!-- Shopping Cart Sidebar -->
<div class="cart-sidebar">
    <div class="cart-header">
        <h5>Your Cart</h5>
        <button class="btn-close" onclick="closeCart()"></button>
    </div>
    <div class="cart-items" id="cartItems">
        <!-- Cart items will be displayed here -->
    </div>
    <div class="customer-details p-3">
        <h6>Customer Information</h6>
        <div class="mb-3">
            <label for="customerName" class="form-label">Name*</label>
            <input type="text" class="form-control" id="customerName" placeholder="Your name" required>
        </div>
        <div class="mb-3">
            <label for="customerPhone" class="form-label">Phone*</label>
            <input type="tel" class="form-control" id="customerPhone" placeholder="Your phone number" required>
        </div>
        <div class="mb-3">
            <label for="customerAddress" class="form-label">Address*</label>
            <textarea class="form-control" id="customerAddress" rows="2" placeholder="Delivery address" required></textarea>
        </div>
        <div class="mb-3">
            <label for="customerNotes" class="form-label">Order Notes</label>
            <textarea class="form-control" id="customerNotes" rows="2" placeholder="Any special instructions"></textarea>
        </div>
    </div>
    <div class="cart-footer">
        <div class="cart-total">
            Total: ₹<span id="cartTotal">0.00</span>
        </div>
        <button class="btn btn-success w-100" onclick="placeOrderOnWhatsApp()">
            <i class="bi bi-whatsapp"></i> Place Order
        </button>
    </div>
</div>






    
    <div class="row" id="productsContainer">
        <?php if (!empty($products)): ?>
            <?php foreach ($products as $product): ?>
            <div class="col-sm-12 product-item" 
                data-name="<?= htmlspecialchars(strtolower($product['product_name'])) ?>"
                data-desc="<?= htmlspecialchars(strtolower($product['description'])) ?>">
                <div class="card product-card">
                    <img src="<?= !empty($product['image_path']) ? htmlspecialchars($product['image_path']) : 'images/no-image.jpg' ?>" 
                         class="card-img-top product-img" 
                         alt="<?= htmlspecialchars($product['product_name']) ?>">
                    <div class="card-body">
                        <h5 class="card-title"><?= htmlspecialchars($product['product_name']) ?></h5>
                        <p class="card-text"><?= htmlspecialchars($product['description']) ?></p>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-primary fw-bold">₹<?= number_format($product['price']) ?></span>
                            <span class="badge bg-<?= ($product['quantity'] > 0) ? 'success' : 'danger' ?>">
                                <?= ($product['quantity'] > 0) ? 'In Stock' : 'Out of Stock' ?>
                            </span>
                        </div>
                        <?php if ($product['quantity'] > 0): ?>
                        <small class="text-muted">Quantity: <?= $product['quantity'] ?></small>
                        <?php endif; ?>
                        <div class="mt-3">
                            <button class="btn btn-primary w-100 add-to-cart" 
                                    data-id="<?= htmlspecialchars($product['product_name']) ?>"
                                    data-name="<?= htmlspecialchars($product['product_name']) ?>"
                                    data-price="<?= $product['price'] ?>"
                                    data-max="<?= $product['quantity'] ?>"
                                    data-image="<?= htmlspecialchars($product['image_path']) ?>"> 
                                <i class="bi bi-cart-plus"></i> Add to Cart
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info">No products available yet.</div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Cart Button -->
    <div class="cart-button-container">
        <button class="btn btn-primary cart-button" onclick="toggleCart()">
            <i class="bi bi-cart"></i> 
            <span class="cart-count">0</span>
        </button>
    </div>
</div>



<script>
    // Cart functionality
    let cart = [];
    
    // Initialize cart from localStorage if available
    if (localStorage.getItem('cart')) {
        cart = JSON.parse(localStorage.getItem('cart'));
        updateCartUI();
    }
    
    // Add to cart button click handler
    document.querySelectorAll('.add-to-cart').forEach(button => {
        button.addEventListener('click', function() {
            const product = {
                id: this.dataset.id,
                name: this.dataset.name,
                price: parseFloat(this.dataset.price),
                max: parseInt(this.dataset.max),
                quantity: 1,
                image_path: this.dataset.image
            };
            
            // Check if product already in cart
            const existingItem = cart.find(item => item.id === product.id);
            
            if (existingItem) {
                if (existingItem.quantity < existingItem.max) {
                    existingItem.quantity++;
                } else {
                    alert('Maximum quantity reached for this product');
                    return;
                }
            } else {
                cart.push(product);
            }
            
            saveCart();
            updateCartUI();
            showCart(); // Show cart when adding an item
        });
    });
    
    // Save cart to localStorage
    function saveCart() {
        localStorage.setItem('cart', JSON.stringify(cart));
    }
    
    // Update cart UI
    function updateCartUI() {
    // Update cart items list
    const cartItemsContainer = document.getElementById('cartItems');
    cartItemsContainer.innerHTML = '';
    
    let total = 0;
    
    cart.forEach((item, index) => {
        total += item.price * item.quantity;
        
        const itemElement = document.createElement('div');
        itemElement.className = 'cart-item';
        
        // Get the product image (use default image if not available)
        const productImage = item.image_path ? item.image_path : 'images/no-image.jpg';

        itemElement.innerHTML = `
            <div class="cart-item-info d-flex">
                <img src="${productImage}" alt="${item.name}" class="cart-item-img" />
                <div class="ms-3">
                    <h6>${item.name}</h6>
                    <div>₹${item.price.toFixed(2)} x ${item.quantity}</div>
                </div>
            </div>
            <div class="cart-item-controls">
                <button class="btn btn-sm btn-outline-secondary" onclick="updateQuantity(${index}, -1)">
                    <i class="bi bi-dash"></i>
                </button>
                <input type="number" value="${item.quantity}" min="1" max="${item.max}" 
                       onchange="updateQuantityInput(${index}, this.value)">
                <button class="btn btn-sm btn-outline-secondary" onclick="updateQuantity(${index}, 1)">
                    <i class="bi bi-plus"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger ms-2" onclick="removeFromCart(${index})">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        `;
        
        cartItemsContainer.appendChild(itemElement);
    });
    
    // Update total
    document.getElementById('cartTotal').textContent = total.toFixed(2);
    
    // Update cart count
    const itemCount = cart.reduce((sum, item) => sum + item.quantity, 0);
    document.querySelector('.cart-count').textContent = itemCount;
}

    
    // Update quantity with buttons
    function updateQuantity(index, change) {
        const item = cart[index];
        const newQuantity = item.quantity + change;
        
        if (newQuantity < 1) {
            removeFromCart(index);
            return;
        }
        
        if (newQuantity > item.max) {
            alert('Maximum quantity reached for this product');
            return;
        }
        
        item.quantity = newQuantity;
        saveCart();
        updateCartUI();
    }
    
    // Update quantity with input
    function updateQuantityInput(index, value) {
        const item = cart[index];
        const newQuantity = parseInt(value);
        
        if (isNaN(newQuantity) || newQuantity < 1) {
            item.quantity = 1;
        } else if (newQuantity > item.max) {
            alert('Maximum quantity reached for this product');
            item.quantity = item.max;
        } else {
            item.quantity = newQuantity;
        }
        
        saveCart();
        updateCartUI();
    }
    
    // Remove item from cart
    function removeFromCart(index) {
        cart.splice(index, 1);
        saveCart();
        updateCartUI();
    }
    
    // Toggle cart visibility
    function toggleCart() {
        document.querySelector('.cart-sidebar').classList.toggle('open');
    }
    
    function showCart() {
        document.querySelector('.cart-sidebar').classList.add('open');
    }
    
    function closeCart() {
        document.querySelector('.cart-sidebar').classList.remove('open');
    }
    
    // Place order on WhatsApp
function placeOrderOnWhatsApp() {
    if (cart.length === 0) {
        alert('Your cart is empty');
        return;
    }
    
    // Get customer details
    const customerName = document.getElementById('customerName').value;
    const customerPhone = document.getElementById('customerPhone').value;
    const customerAddress = document.getElementById('customerAddress').value;
    const customerNotes = document.getElementById('customerNotes').value;
    
    // Validate required fields
    if (!customerName || !customerPhone) {
        alert('Please provide your name and phone number');
        return;
    }
    
    const whatsappLink = "<?= $social_link['whatsapp'] ?? '' ?>";
    let phoneNumber = whatsappLink.match(/wa\.me\/(\d+)/)?.[1] || "<?= $user['phone'] ?? '' ?>";
    
    if (!phoneNumber) {
        alert('WhatsApp number not available');
        return;
    }
    
    let message = `*##### NEW ORDER #####*\n\n`;
    
    message += `--------------------------\n`;
    message += `*Order Details:*\n`;
    message += `--------------------------\n`;
    let total = 0;
    
    cart.forEach(item => {
        message += `*${item.name}*\n`;
        message += `Price: ₹${item.price.toFixed(2)}\n`;
        message += `Quantity: ${item.quantity}\n`;
        message += `Subtotal: ₹${(item.price * item.quantity).toFixed(2)}\n\n`;
        total += item.price * item.quantity;
        message += `--------------------------\n`;
    });
    

    message += `*Total Amount: ₹${total.toFixed(2)}*\n`;
    message += `--------------------------\n\n`;


    message += `*Customer Details:*\n`;
    message += `Name: ${customerName}\n`;
    message += `Phone: ${customerPhone}\n`;
    if (customerAddress) message += `Address: ${customerAddress}\n`;
    if (customerNotes) message += `*Notes:* ${customerNotes}\n\n`;



    message += `*Please confirm this order.*`;
    
    window.open(`https://wa.me/${phoneNumber}?text=${encodeURIComponent(message)}`, '_blank');
    
    // Optional: Clear cart after order
    // cart = [];
    // saveCart();
    // updateCartUI();
    // closeCart();
}
</script>
