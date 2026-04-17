<style>
/* Grayscale effect for unavailable products */
.product-img.grayscale {
    filter: grayscale(100%) !important;
    opacity: 0.7;
}

/* Overlay for unavailable products */
.product-card.not-available {
    position: relative;
}

.product-card.not-available::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
}

/* Style for disabled add-to-cart button */
.add-to-cart.disabled {
    background-color: #6c757d !important;
    border-color: #6c757d !important;
    cursor: not-allowed;
    opacity: 0.65;
}

/* Time slot display styling */
.tag-time-slot.text-danger {
    color: #dc3545 !important;
    font-weight: bold;
}

.tag-time-slot.text-success {
    color: #198754 !important;
}

/* Flying image animation */
.flying-image {
    position: fixed;
    z-index: 9999;
    border-radius: 8px;
    object-fit: cover;
    pointer-events: none;
    transition: all 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    animation: flyToCart 1s forwards;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

@keyframes flyToCart {
    0% {
        transform: translate(0, 0) scale(1) rotate(0deg);
        opacity: 1;
    }
    50% {
        transform: translate(var(--mid-x), var(--mid-y)) scale(0.7) rotate(180deg);
        opacity: 0.8;
    }
    100% {
        transform: translate(var(--final-x), var(--final-y)) scale(0.2) rotate(360deg);
        opacity: 0;
    }
}

/* Address preview styling - Hidden as requested */
.address-preview {
    display: none !important;
}

/* Delivery form row styling */
.delivery-details .row {
    margin-left: -5px;
    margin-right: -5px;
}

.delivery-details .row > [class*="col-"] {
    padding-left: 5px;
    padding-right: 5px;
}

/* Form field focus styling */
.delivery-details input:focus,
.delivery-details textarea:focus {
    border-color: <?= $primary_color ?>;
    box-shadow: 0 0 0 0.25rem rgba(<?= hexdec(substr($primary_color,1,2)) ?>, <?= hexdec(substr($primary_color,3,2)) ?>, <?= hexdec(substr($primary_color,5,2)) ?>, 0.25);
}

/* Style for the location button */
#getLocationBtn {
    margin-top: 5px;
    background-color: #f8f9fa;
    border-color: #ced4da;
    color: #495057;
    transition: all 0.3s ease;
}

#getLocationBtn:hover {
    background-color: #e9ecef;
    border-color: #adb5bd;
}

#getLocationBtn:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

/* Optional: Add a pulse animation when location is detected */
@keyframes locationPulse {
    0% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.4); }
    70% { box-shadow: 0 0 0 10px rgba(40, 167, 69, 0); }
    100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
}

.location-detected {
    animation: locationPulse 1s ease;
}
</style>

<?php
// Force Indian currency only
$currency_symbol = '₹';
$currency_code = 'INR';

// Get products from user-specific table based on user_id
$table_name = "products_" . $user_id;

// Check if the user-specific products table exists
$check_table = $conn->prepare("SHOW TABLES LIKE ?");
$check_table->execute([$table_name]);
$table_exists = $check_table->fetch(PDO::FETCH_ASSOC);

if ($table_exists) {
    // Fetch products from user-specific table
    $products_sql = "SELECT * FROM $table_name ORDER BY id ASC";
    $products_stmt = $conn->prepare($products_sql);
    $products_stmt->execute();
    $products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $products = []; // Empty array if table doesn't exist
}
?>

<?php if ($active_subscription): ?>
    <?php if ($active_subscription['package_id'] == 1): ?>
        <style>
            #deliveryBtn { width: 100% !important; margin: 0 !important; }
        </style>
    <?php endif; ?>
<?php endif; ?>

<script>
// Google Maps API configuration - using API key from PHP
const GOOGLE_MAPS_API_KEY = '<?= defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : '' ?>';

// Get currency symbol from PHP - Indian Rupee only
const currencySymbol = '₹';
const currencyCode = 'INR';

// WhatsApp integration disabled
const ENABLE_WHATSAPP_ORDER = false;

// Phone validation for India only
document.addEventListener('DOMContentLoaded', function() {
    // Initialize lazy loading with fade-in effect
    initLazyLoading();
    
    // Add event listeners for address fields
    const addressFields = ['building', 'flatUnit', 'landmark'];
    addressFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.addEventListener('input', updateAddressPreview);
        }
    });
});

// Load Google Maps API dynamically
function loadGoogleMapsAPI(callback) {
    if (window.google && window.google.maps) {
        callback();
        return;
    }
    
    if (!GOOGLE_MAPS_API_KEY) {
        alert('Google Maps API key is not configured. Please contact support.');
        return;
    }
    
    const script = document.createElement('script');
    script.src = `https://maps.googleapis.com/maps/api/js?key=${GOOGLE_MAPS_API_KEY}&libraries=places&callback=initGoogleMapsCallback`;
    script.async = true;
    script.defer = true;
    
    window.initGoogleMapsCallback = function() {
        console.log('Google Maps API loaded successfully');
        callback();
    };
    
    script.onerror = function() {
        alert('Failed to load Google Maps API. Please check your internet connection and try again.');
    };
    
    document.head.appendChild(script);
}

// Get current location and reverse geocode
function getCurrentLocation() {
    if (!navigator.geolocation) {
        alert('Geolocation is not supported by your browser. Please enter your address manually.');
        return;
    }

    const btn = document.getElementById('getLocationBtn');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Detecting location...';
    btn.disabled = true;

    navigator.geolocation.getCurrentPosition(
        (position) => {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;

            loadGoogleMapsAPI(() => {
                const geocoder = new google.maps.Geocoder();
                
                geocoder.geocode(
                    { location: { lat, lng } },
                    (results, status) => {
                        btn.innerHTML = originalText;
                        btn.disabled = false;

                        if (status === 'OK' && results && results.length > 0) {
                            fillAddressFromGeocode(results[0]);
                        } else {
                            console.error('Geocoding failed:', status);
                            alert('Could not retrieve address from your location. Please enter manually.');
                        }
                    }
                );
            });
        },
        (error) => {
            btn.innerHTML = originalText;
            btn.disabled = false;
            
            let message = 'Unable to retrieve your location. ';
            switch (error.code) {
                case error.PERMISSION_DENIED:
                    message += 'Location access was denied. Please enable location permissions and try again, or enter address manually.';
                    break;
                case error.POSITION_UNAVAILABLE:
                    message += 'Location information is unavailable. Please enter address manually.';
                    break;
                case error.TIMEOUT:
                    message += 'Location request timed out. Please try again or enter address manually.';
                    break;
                default:
                    message += 'Please enter your address manually.';
            }
            alert(message);
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        }
    );
}

// Parse address components and fill form fields
function fillAddressFromGeocode(geocodeResult) {
    const addressComponents = geocodeResult.address_components;
    const formattedAddress = geocodeResult.formatted_address;

    const findComponent = (types) => {
        for (let component of addressComponents) {
            if (types.some(type => component.types.includes(type))) {
                return component.long_name;
            }
        }
        return '';
    };

    const streetNumber = findComponent(['street_number']);
    const route = findComponent(['route']);
    const subpremise = findComponent(['subpremise']);
    const premise = findComponent(['premise']);
    const sublocality = findComponent(['sublocality', 'sublocality_level_1', 'neighborhood']);
    const locality = findComponent(['locality', 'city']);
    const pointOfInterest = findComponent(['point_of_interest', 'establishment']);
    
    let building = '';
    if (premise) {
        building = premise;
    } else if (pointOfInterest) {
        building = pointOfInterest;
    } else if (route && streetNumber) {
        building = `${streetNumber} ${route}`;
    } else if (route) {
        building = route;
    } else {
        building = formattedAddress.split(',')[0];
    }
    
    const buildingField = document.getElementById('building');
    if (buildingField) buildingField.value = building;
    
    const flatUnitField = document.getElementById('flatUnit');
    if (flatUnitField && subpremise) flatUnitField.value = subpremise;
    
    let landmark = pointOfInterest || sublocality || locality || '';
    const landmarkField = document.getElementById('landmark');
    if (landmarkField) landmarkField.value = landmark;
    
    if (typeof updateAddressPreview === 'function') updateAddressPreview();
}

// Lazy loading with fade-in effect
function initLazyLoading() {
    const lazyImages = document.querySelectorAll('img.product-img-lazy');
    
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    preloadImage(img);
                    imageObserver.unobserve(img);
                }
            });
        }, {
            rootMargin: '0px 0px 200px 0px',
            threshold: 0.01
        });

        lazyImages.forEach(img => imageObserver.observe(img));
    } else {
        lazyImages.forEach(img => preloadImage(img));
    }
}

function preloadImage(img) {
    const spinner = img.parentElement.querySelector('.img-loading-spinner');
    if (spinner) spinner.style.display = 'block';
    
    const newImg = new Image();
    
    newImg.onload = function() {
        img.src = img.dataset.src;
        img.classList.remove('product-img-lazy');
        img.classList.add('product-img-loaded');
        if (spinner) spinner.style.display = 'none';
        if (img.classList.contains('product-img-placeholder')) {
            setTimeout(() => img.classList.remove('product-img-placeholder'), 500);
        }
    };
    
    newImg.onerror = function() {
        img.style.display = 'none';
        if (spinner) spinner.style.display = 'none';
        const productCard = img.closest('.product-card');
        if (productCard) {
            const cartBtnGroup = productCard.querySelector('.card-body .cart_btn_group');
            if (cartBtnGroup) cartBtnGroup.classList.add('top');
        }
    };
    
    newImg.src = img.dataset.src;
}

// Format number function with currency support
function formatNumber(num, withSymbol = false) {
    num = typeof num === 'string' ? parseFloat(num) : num;
    if (isNaN(num)) num = 0;
    
    const formatted = num % 1 === 0 ? num.toString() : num.toFixed(2).replace(/\.?0+$/, '');
    
    if (withSymbol) {
        return currencySymbol + formatted;
    }
    return formatted;
}

function formatCurrency(amount) {
    return currencySymbol + formatNumber(amount);
}

// Address preview (kept for functionality but UI hidden)
function updateAddressPreview() {
    return;
}

// Phone number validation - India only (10 digits, cannot start with 0)
function validatePhoneNumber(input) {
    input.value = input.value.replace(/\D/g, '');
    
    if (input.value.length > 10) input.value = input.value.substring(0, 10);
    if (input.value.length > 0 && input.value.startsWith('0')) {
        input.setCustomValidity('Phone number cannot start with 0');
        input.reportValidity();
        return false;
    }
    if (input.value.length !== 10 && input.value.length > 0) {
        input.setCustomValidity('Phone number must be exactly 10 digits');
        input.reportValidity();
        return false;
    }
    
    input.setCustomValidity('');
    return true;
}

function validatePhoneForOrder() {
    const phoneInput = document.getElementById('customerPhone');
    if (!phoneInput) return false;
    
    if (!validatePhoneNumber(phoneInput)) return false;
    
    if (phoneInput.value.length !== 10) {
        alert('Please enter a valid 10-digit phone number');
        phoneInput.focus();
        return false;
    }
    return true;
}

// Initialize cart
let cart = [];
let discountAmount = 0;
let discountType = '';

const storeName = window.location.pathname.split('/')[1] || 'default';
const cartKey = `cart_${storeName}`;

if (localStorage.getItem(cartKey)) {
    const savedCart = JSON.parse(localStorage.getItem(cartKey));
    cart = savedCart.items || [];
}

// Add to cart button click handler with image animation
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

        const existingItem = cart.find(item => item.id === product.id);

        if (existingItem) {
            if (existingItem.quantity < existingItem.max) {
                existingItem.quantity++;
                if (product.image_path) animateProductToCart(this, product.image_path);
            } else {
                alert('Maximum quantity reached for this product');
                return;
            }
        } else {
            cart.push(product);
            if (product.image_path) animateProductToCart(this, product.image_path);
        }
        
        const cartButton = document.querySelector('.cart-button');
        if (cartButton) {
            cartButton.classList.add('cart-item-added');
            setTimeout(() => cartButton.classList.remove('cart-item-added'), 500);
        }

        saveCart();
        updateCartUI();
        
        const cartButtonContainer = document.querySelector('.cart-button-container');
        if (cartButtonContainer && cartButtonContainer.style.display === 'none') {
            cartButtonContainer.style.display = 'block';
        }
    });
});

function animateProductToCart(buttonElement, imageSrc) {
    const productCard = buttonElement.closest('.product-card');
    const productImage = productCard ? productCard.querySelector('.product-img') : null;
    if (!productImage) return;
    
    const cartButtonContainer = document.querySelector('.cart-button-container');
    if (!cartButtonContainer) return;
    
    const cartButtonRect = cartButtonContainer.getBoundingClientRect();
    const flyingImage = document.createElement('img');
    flyingImage.src = imageSrc;
    flyingImage.className = 'flying-image';
    
    const imageRect = productImage.getBoundingClientRect();
    flyingImage.style.width = `${imageRect.width}px`;
    flyingImage.style.height = `${imageRect.height}px`;
    flyingImage.style.left = `${imageRect.left}px`;
    flyingImage.style.top = `${imageRect.top}px`;
    
    const finalX = (cartButtonRect.left + (cartButtonRect.width / 2)) - (imageRect.width / 2);
    const finalY = (cartButtonRect.top + (cartButtonRect.height / 2)) - (imageRect.height / 2);
    const midX = (finalX + imageRect.left) / 2 - 50;
    const midY = (finalY + imageRect.top) / 2 - 100;
    
    flyingImage.style.setProperty('--final-x', `${finalX - imageRect.left}px`);
    flyingImage.style.setProperty('--final-y', `${finalY - imageRect.top}px`);
    flyingImage.style.setProperty('--mid-x', `${midX - imageRect.left}px`);
    flyingImage.style.setProperty('--mid-y', `${midY - imageRect.top}px`);
    
    document.body.appendChild(flyingImage);
    setTimeout(() => {
        if (flyingImage.parentNode) flyingImage.parentNode.removeChild(flyingImage);
    }, 1000);
}

function saveCart() {
    localStorage.setItem(cartKey, JSON.stringify({
        items: cart.filter(item => item.id)
    }));
}

function updateCartUI() {
    const cartItemsContainer = document.getElementById('cartItems');
    const cartTotalDetails = document.querySelector('.cart-total-details');
    const orderTypeButtons = document.querySelector('.order-type-buttons');
    const cartFooter = document.querySelector('.cart-footer');
    const cartButtonContainer = document.querySelector('.cart-button-container');
    const emptyCartMsg = document.createElement('div');

    const existingEmptyMsg = cartItemsContainer.querySelector('.empty-cart-message');
    if (existingEmptyMsg) existingEmptyMsg.remove();

    cartItemsContainer.innerHTML = '';

    if (cart.length === 0) {
        emptyCartMsg.className = 'empty-cart-message text-center py-4';
        emptyCartMsg.innerHTML = `
            <i class="bi bi-cart-x fs-1 text-muted"></i>
            <p class="mt-2">Your cart is empty</p>
            <button class="btn btn-sm btn-outline-primary" onclick="closeCart()">
                Continue Shopping
            </button>
        `;
        cartItemsContainer.appendChild(emptyCartMsg);
        
        if (cartTotalDetails) cartTotalDetails.style.display = 'none';
        if (orderTypeButtons) orderTypeButtons.style.display = 'none';
        if (cartFooter) cartFooter.style.display = 'none';
        
        document.querySelector('.cart-count').textContent = '0 items added';
        if (cartButtonContainer) cartButtonContainer.style.display = 'none';
        return;
    }

    let subtotal = 0;
    const gstPercent = <?= $gst_percent ?? 0 ?>;

    if (orderTypeButtons) orderTypeButtons.style.display = 'block';
    if (cartFooter) cartFooter.style.display = 'block';
    if (cartButtonContainer) cartButtonContainer.style.display = 'block';

    cart.forEach((item, index) => {
        if (!item.id) return;
        subtotal += item.price * item.quantity;

        const itemElement = document.createElement('div');
        itemElement.className = 'cart-item';
        itemElement.innerHTML = `
            <div class="cart-item-info d-flex">
                <div class="ms-1">
                    <h6>${item.name}</h6>
                    <div>${currencySymbol}${formatNumber(item.price)} x ${item.quantity}</div>
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

    if (cartTotalDetails) cartTotalDetails.style.display = 'block';

    document.getElementById('cartSubtotal').textContent = formatNumber(subtotal);

    let amountAfterDiscount = subtotal; // No discount
    let total = amountAfterDiscount;
    if (gstPercent > 0) {
        const gstAmount = (amountAfterDiscount * gstPercent) / 100;
        document.getElementById('gstCharges').textContent = formatNumber(gstAmount);
        total += gstAmount;
    }

    // Delivery charges (always delivery)
    let actualDeliveryCharge = 0;
    const deliveryCharge = <?= isset($delivery_charges['delivery_charge']) ? $delivery_charges['delivery_charge'] : 0 ?>;
    const freeDeliveryMin = <?= isset($delivery_charges['free_delivery_minimum']) ? $delivery_charges['free_delivery_minimum'] : 0 ?>;
    const cartDeliveryChargesRow = document.querySelector('.cart-delivery-charges');
    
    if (deliveryCharge !== undefined) {
        if (freeDeliveryMin > 0 && amountAfterDiscount >= freeDeliveryMin) {
            actualDeliveryCharge = 0;
            document.getElementById('deliveryChargeText').textContent = 'FREE (Order above ' + currencySymbol + formatNumber(freeDeliveryMin) + ')';
            if (cartDeliveryChargesRow) cartDeliveryChargesRow.classList.add('free');
        } else {
            actualDeliveryCharge = parseFloat(deliveryCharge);
            if (freeDeliveryMin > 0) {
                const neededForFree = freeDeliveryMin - amountAfterDiscount;
                document.getElementById('deliveryChargeText').innerHTML =
                    `${currencySymbol}${formatNumber(deliveryCharge)} <span class="free-delivery-text"> (Add ${currencySymbol}${formatNumber(neededForFree)} more for FREE delivery)</span>`;
            } else {
                document.getElementById('deliveryChargeText').textContent = `${currencySymbol}${formatNumber(deliveryCharge)}`;
            }
            if (cartDeliveryChargesRow) cartDeliveryChargesRow.classList.remove('free');
        }
        if (cartDeliveryChargesRow) cartDeliveryChargesRow.style.display = 'block';
        total += actualDeliveryCharge;
    } else {
        if (cartDeliveryChargesRow) cartDeliveryChargesRow.style.display = 'none';
    }

    document.getElementById('cartTotal').textContent = formatNumber(total);
    const itemCount = cart.filter(item => item.id).reduce((sum, item) => sum + item.quantity, 0);
    document.querySelector('.cart-count').textContent = itemCount + (itemCount === 1 ? ' item added' : ' items added in cart');
}

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

function updateQuantityInput(index, value) {
    const item = cart[index];
    let newQuantity = parseInt(value);
    if (isNaN(newQuantity) || newQuantity < 1) newQuantity = 1;
    else if (newQuantity > item.max) {
        alert('Maximum quantity reached for this product');
        newQuantity = item.max;
    }
    item.quantity = newQuantity;
    saveCart();
    updateCartUI();
}

function removeFromCart(index) {
    cart.splice(index, 1);
    saveCart();
    updateCartUI();
    if (cart.length === 0) {
        const cartButtonContainer = document.querySelector('.cart-button-container');
        if (cartButtonContainer) cartButtonContainer.style.display = 'none';
    }
}

function toggleCart() {
    document.querySelector('.cart-sidebar').classList.toggle('open');
}

function showCart() {
    document.querySelector('.cart-sidebar').classList.add('open');
}

function closeCart() {
    document.querySelector('.cart-sidebar').classList.remove('open');
}

function calculateSubtotal() {
    return cart.filter(item => item.id).reduce((sum, item) => sum + (item.price * item.quantity), 0);
}

function placeOrder() {
    if (cart.length === 0) {
        alert('Your cart is empty');
        return;
    }

    if (!validatePhoneForOrder()) return;

    const deliveryCharge = <?= isset($delivery_charges['delivery_charge']) ? $delivery_charges['delivery_charge'] : 0 ?>;
    const freeDeliveryMin = <?= isset($delivery_charges['free_delivery_minimum']) ? $delivery_charges['free_delivery_minimum'] : 0 ?>;
    const gstPercent = <?= $gst_percent ?? 0 ?>;
    
    // No discount
    let discountAmount = 0;
    let discountType = '';
    
    const building = document.getElementById('building')?.value;
    const flatUnit = document.getElementById('flatUnit')?.value;
    const landmark = document.getElementById('landmark')?.value || '';
    const customerName = document.getElementById('customerName')?.value;
    const customerPhone = document.getElementById('customerPhone')?.value;
    const orderNotes = document.getElementById('customerNotes')?.value || '';
    
    if (!customerName || !customerPhone) {
        alert('Please provide your name and phone number');
        return;
    }
    if (!validatePhoneForOrder()) return;
    if (!building || !flatUnit) {
        alert('Please provide complete address (Building and Flat/Unit No. are required)');
        return;
    }
    
    const addressParts = [];
    if (flatUnit) addressParts.push(`Flat/Unit: ${flatUnit}`);
    if (building) addressParts.push(building);
    if (landmark) addressParts.push(`Landmark: ${landmark}`);
    const deliveryAddress = addressParts.join(', ');
    
    const orderData = {
        user_id: <?= $user_id ?>,
        currency_symbol: currencySymbol,
        currency_code: currencyCode,
        order_type: 'delivery',
        customer_name: customerName,
        customer_phone: customerPhone,
        delivery_address: deliveryAddress,
        address_components: {
            building: building,
            floor: '',
            flat_unit: flatUnit,
            landmark: landmark
        },
        table_number: null,
        order_notes: orderNotes,
        items: cart.filter(item => item.id).map(item => ({
            name: item.name,
            price: item.price,
            quantity: item.quantity
        })),
        discount_amount: discountAmount,
        discount_type: discountType,
        gst_percent: gstPercent,
        delivery_charge: deliveryCharge,
        free_delivery_min: freeDeliveryMin,
        coupon_data: null
    };
    
    const placeOrderBtn = document.getElementById('placeOrderBtn');
    const originalBtnText = placeOrderBtn.innerHTML;
    placeOrderBtn.disabled = true;
    placeOrderBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Order Processing...';
    
    fetch('place_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(orderData)
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => {
                console.error('Server response:', text);
                throw new Error(`Server returned ${response.status}: ${response.statusText}`);
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            cart = [];
            localStorage.removeItem(cartKey);
            
            // Clear form fields
            if (document.getElementById('customerName')) document.getElementById('customerName').value = '';
            if (document.getElementById('customerPhone')) document.getElementById('customerPhone').value = '';
            if (document.getElementById('building')) document.getElementById('building').value = '';
            if (document.getElementById('flatUnit')) document.getElementById('flatUnit').value = '';
            if (document.getElementById('landmark')) document.getElementById('landmark').value = '';
            if (document.getElementById('customerNotes')) document.getElementById('customerNotes').value = '';
            
            updateCartUI();
            closeCart();
            
            const orderId = data.order_id;
            
            const profileUrl = '<?= $profile_url ?>';
            if (orderId) {
                window.location.href = `order_status.php?order_id=${orderId}&profile_url=${profileUrl}`;
            } else {
                window.location.href = `order_status.php?profile_url=${profileUrl}`;
            }
        } else {
            throw new Error(data.message || 'Failed to place order');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        let errorMessage = 'Failed to place order. ';
        if (error.message.includes('Failed to fetch')) {
            errorMessage += 'Network error. Please check your internet connection.';
        } else if (error.message.includes('CORS')) {
            errorMessage += 'Cross-origin request blocked. Please contact support.';
        } else {
            errorMessage += error.message || 'Please try again.';
        }
        alert(errorMessage);
        placeOrderBtn.innerHTML = originalBtnText;
        placeOrderBtn.disabled = false;
    });
}

document.getElementById('placeOrderBtn').addEventListener('click', placeOrder);

function createConfetti() {
    const confettiContainer = document.getElementById('confettiContainer');
    if (!confettiContainer) return;
    confettiContainer.innerHTML = '';
    confettiContainer.style.display = 'block';
    const colors = ['#f94144', '#f3722c', '#f8961e', '#f9c74f', '#90be6d', '#43aa8b', '#577590'];
    for (let i = 0; i < 150; i++) {
        const confetti = document.createElement('div');
        confetti.className = 'confetti';
        const color = colors[Math.floor(Math.random() * colors.length)];
        const size = Math.random() * 10 + 5;
        const left = Math.random() * 100;
        const animationDelay = Math.random() * 5;
        const animationDuration = Math.random() * 3 + 3;
        confetti.style.backgroundColor = color;
        confetti.style.width = `${size}px`;
        confetti.style.height = `${size}px`;
        confetti.style.left = `${left}%`;
        confetti.style.animationDelay = `${animationDelay}s`;
        confetti.style.animationDuration = `${animationDuration}s`;
        if (Math.random() > 0.5) confetti.style.borderRadius = '50%';
        confettiContainer.appendChild(confetti);
    }
    setTimeout(() => {
        confettiContainer.style.display = 'none';
    }, 60000);
}
</script>

<!-- products.php -->
<div class="products">
    <h6>Products</h6>

    <?php if ($delivery_active): ?>
        <!-- Shopping Cart Sidebar -->
        <div class="cart-sidebar">
            <div class="cart-header">
                <h5>Your Cart</h5>
                <button class="btn-close" onclick="closeCart()"></button>
            </div>

            <div class="cart_group" id="cartGroup">
                <div class="cart-items" id="cartItems"></div>
                <div class="cart-total-details">
                    <div class="cart-subtotal">
                        Subtotal: <span id="cartSubtotal">0.00</span>
                    </div>

                    <?php if ($gst_percent > 0): ?>
                        <div class="cart-gst-charges">
                            GST (<?= $gst_percent ?>%): <span id="gstCharges">0.00</span>
                        </div>
                    <?php endif; ?>

                    <?php if ($delivery_active && isset($delivery_charges)): ?>
                        <div class="cart-delivery-charges">
                            Delivery: <span id="deliveryChargeText">0.00</span>
                        </div>
                    <?php endif; ?>

                    <div class="cart-total">
                        Total: <span id="cartTotal">0.00</span>
                    </div>
                </div>
                <button class="btn btn-outline-secondary mb-3 w-100" id="viewCartBtn" style="display: none;">
                    <i class="bi bi-cart blink"></i> View Cart
                </button>
            </div>

            <!-- Order Type Buttons - Only Delivery -->
            <div class="order-type-buttons mb-3">
                <div class="choose_order_type">Delivery Order</div>
                <button class="btn btn-outline-primary w-100 active" id="deliveryBtn">
                    <i class="bi bi-truck blink"></i> Delivery
                </button>
            </div>

            <div style="clear: both;"></div>

            <!-- Customer Details Section (delivery only) -->
            <div id="customerDetailsSection" style="display: none;">
                <div class="customer-details delivery-details" id="deliveryDetails">
                    <h6>Delivery Information</h6>
                    <div class="mb-1 col-half">
                        <label for="customerName" class="form-label">Name*</label>
                        <input type="text" class="form-control" id="customerName" placeholder="Your name" required>
                    </div>
                    <div class="mb-1 col-half">
                        <label for="customerPhone" class="form-label">Phone*</label>
                        <input type="tel" class="form-control" id="customerPhone" placeholder="Your phone number" pattern="[0-9]{10}" title="Please enter exactly 10 digits" required oninput="validatePhoneNumber(this)">
                    </div>
                    
                    <div class="mb-1 col-full">
                        <label for="building" class="form-label">Building / Society Name*</label>
                        <input type="text" class="form-control" id="building" required>
                    </div>
                    
                    <div class="row">
                        <div class="mb-1 col-6">
                            <label for="flatUnit" class="form-label">Flat/Unit No.*</label>
                            <input type="text" class="form-control" id="flatUnit" required>
                        </div>
                        <div class="mb-1 col-6">
                            <label for="landmark" class="form-label">Landmark / Area / City</label>
                            <input type="text" class="form-control" id="landmark">
                        </div>
                    </div>
                    
                    <div class="mb-1 col-full">
                        <button type="button" class="btn btn-outline-primary btn-sm w-100" id="getLocationBtn" onclick="getCurrentLocation()">
                            <i class="bi bi-geo-alt-fill"></i> <strong>Use My Current Location</strong>
                        </button>
                    </div>
                    
                    <div class="mb-1 col-full">
                        <label for="customerNotes" class="form-label">Order Notes</label>
                        <textarea class="form-control" id="customerNotes" rows="2" placeholder="Any special instructions for delivery"></textarea>
                    </div>
                </div>

                <div class="cart-footer">
                    <button class="btn btn-success w-100" id="placeOrderBtn">Place Order</button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row" id="productsContainer">
        <?php
        // Get products from user-specific table based on user_id
        $table_name = "products_" . $user_id;
        $check_table = $conn->prepare("SHOW TABLES LIKE ?");
        $check_table->execute([$table_name]);
        $table_exists = $check_table->fetch(PDO::FETCH_ASSOC);

        if ($table_exists) {
            $products_sql = "SELECT * FROM $table_name ORDER BY id ASC";
            $products_stmt = $conn->prepare($products_sql);
            $products_stmt->execute();
            $products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $products = [];
        }
        ?>

        <?php if (!empty($products)): ?>
            <?php foreach ($products as $product): ?>
                <div class="col-sm-12 product-item" 
                     data-name="<?= htmlspecialchars(strtolower($product['product_name'])) ?>" 
                     data-desc="<?= htmlspecialchars(strtolower($product['description'])) ?>">
                    <div class="card product-card">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($product['product_name']) ?></h5>
                            <p class="card-text"><?= htmlspecialchars($product['description']) ?></p>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-primary fw-bold">₹<?= number_format($product['price']) ?></span>
                                <span class="badge bg-<?= ($product['quantity'] > 0) ? 'success' : 'danger' ?>" style="display: none;">
                                    <?= ($product['quantity'] > 0) ? 'In Stock' : 'Out of Stock' ?>
                                </span>
                            </div>
                            <?php if ($product['quantity'] > 0): ?>
                                <small class="text-muted">Quantity: <?= $product['quantity'] ?></small>
                            <?php endif; ?>
                            
                            <?php if ($product['quantity'] > 0 && $delivery_active && $is_store_open): ?>
                                <div class="mt-3 cart_btn_group <?= empty($product['image_path']) ? 'top' : '' ?>">
                                    <button class="btn btn-primary w-100 add-to-cart" 
                                            data-id="<?= htmlspecialchars($product['product_name']) ?>" 
                                            data-name="<?= htmlspecialchars($product['product_name']) ?>" 
                                            data-price="<?= $product['price'] ?>" 
                                            data-max="<?= $product['quantity'] ?>" 
                                            data-image="<?= htmlspecialchars($product['image_path']) ?>">
                                        <i class="bi bi-cart-plus"></i> Add
                                    </button>
                                </div>
                            <?php elseif ($product['quantity'] > 0 && !$is_store_open): ?>
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <i class="bi bi-clock"></i> Currently unavailable (Store closed)
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($product['image_path'])): ?>
                            <div class="img-group">
                                <div class="aspect-ratio-box">
                                    <img 
                                        src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1 1'%3E%3C/svg%3E" 
                                        data-src="<?= htmlspecialchars($product['image_path']) ?>" 
                                        class="card-img-top product-img product-img-lazy product-img-placeholder" 
                                        alt="<?= htmlspecialchars($product['product_name']) ?>" 
                                        onerror="handleImageError(this)">
                                    <div class="img-loading-spinner"></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info">No products available yet.</div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Search (no tags) -->
    <div class="sticky-search-container">
        <div class="input-group sticky-search">
            <input type="text" id="productSearch" class="form-control" placeholder="Search products...">
            <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                <i class="bi bi-x"></i>
            </button>
        </div>
    </div>

    <?php if ($delivery_active): ?>
        <div class="cart-button-container" style="display: none;">
            <button class="btn btn-primary cart-button" onclick="toggleCart()">
                <span class="cart-count">0 item added</span>
                <i class="bi bi-cart blink"></i>
            </button>
        </div>
    <?php endif; ?>

<script>
    // Product search (no tag filtering)
    document.getElementById('productSearch').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const productItems = document.querySelectorAll('.product-item');
        productItems.forEach(item => {
            const productName = item.dataset.name;
            const productDesc = item.dataset.desc;
            if (productName.includes(searchTerm) || productDesc.includes(searchTerm)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    });

    document.getElementById('clearSearch').addEventListener('click', function() {
        document.getElementById('productSearch').value = '';
        document.querySelectorAll('.product-item').forEach(item => {
            item.style.display = 'block';
        });
        document.getElementById('productSearch').focus();
    });

    // View Cart button logic
    const viewCartBtn = document.getElementById('viewCartBtn');
    const customerDetailsSection = document.getElementById('customerDetailsSection');
    const cartItemsDiv = document.getElementById('cartItems');
    
    if (viewCartBtn) {
        viewCartBtn.addEventListener('click', function() {
            if (customerDetailsSection) customerDetailsSection.style.display = 'none';
            if (cartItemsDiv) cartItemsDiv.style.display = 'block';
            this.style.display = 'none';
        });
    }
    
    // Delivery button always active
    const deliveryBtn = document.getElementById('deliveryBtn');
    if (deliveryBtn) {
        deliveryBtn.classList.add('active');
    }
    
    // Show customer details after cart has items
    function checkAndShowDetails() {
        if (cart.length > 0 && customerDetailsSection) {
            customerDetailsSection.style.display = 'block';
            if (cartItemsDiv) cartItemsDiv.style.display = 'none';
            if (viewCartBtn) viewCartBtn.style.display = 'block';
        }
    }
    
    // Override updateCartUI to call checkAndShowDetails
    const originalUpdateCartUI = updateCartUI;
    updateCartUI = function() {
        originalUpdateCartUI.apply(this, arguments);
        checkAndShowDetails();
    };
    checkAndShowDetails();
    
    // Handle image error
    function handleImageError(img) {
        img.style.display = 'none';
        const cartBtnGroup = img.closest('.product-card')?.querySelector('.cart_btn_group');
        if (cartBtnGroup) cartBtnGroup.classList.add('top');
    }
</script>

<!-- Confetti container -->
<div class="confetti-container" id="confettiContainer"></div>

<!-- View Order Button -->
<?php
$lastOrderId = null;
if (isset($_COOKIE['lastOrderId']) && isset($_COOKIE['lastOrderUserId']) && $_COOKIE['lastOrderUserId'] == $user_id) {
    $lastOrderId = $_COOKIE['lastOrderId'];
}
?>

<div id="viewOrderBtnContainer" class="view-order-container" style="display: none;">
    <button class="btn btn-success view-order-btn enhanced-blink" onclick="viewLastOrder()">
        <i class="bi bi-eye-fill"></i> View Order
    </button>
</div>
</div>

<script>
function checkAndShowViewOrderButton() {
    const lastOrderId = localStorage.getItem('lastOrderId');
    const lastOrderUserId = localStorage.getItem('lastOrderUserId');
    const currentUserId = <?= $user_id ?>;
    const viewOrderContainer = document.getElementById('viewOrderBtnContainer');
    
    if (lastOrderId && lastOrderUserId && lastOrderUserId == currentUserId) {
        viewOrderContainer.style.display = 'block';
    } else {
        viewOrderContainer.style.display = 'none';
    }
}

function viewLastOrder() {
    const lastOrderId = localStorage.getItem('lastOrderId');
    const profileUrl = '<?= $profile_url ?>';
    if (lastOrderId) {
        window.location.href = `order_status.php?order_id=${lastOrderId}&profile_url=${profileUrl}`;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    checkAndShowViewOrderButton();
    
    const originalUpdateCartUI = updateCartUI;
    updateCartUI = function() {
        originalUpdateCartUI.apply(this, arguments);
        checkAndShowViewOrderButton();
    };
});

const originalPlaceOrder = placeOrder;
placeOrder = function() {
    localStorage.removeItem('lastOrderId');
    localStorage.removeItem('lastOrderUserId');
    checkAndShowViewOrderButton();
    originalPlaceOrder.apply(this, arguments);
};

function goBackToMenu(orderId) {
    const expires = new Date();
    expires.setTime(expires.getTime() + (24 * 60 * 60 * 1000));
    document.cookie = `lastOrderId=${orderId}; expires=${expires.toUTCString()}; path=/`;
    document.cookie = `lastOrderUserId=<?= $user_id ?>; expires=${expires.toUTCString()}; path=/`;
    localStorage.setItem('lastOrderId', orderId);
    localStorage.setItem('lastOrderUserId', '<?= $user_id ?>');
    window.location.href = 'https://deegeecard.com/<?= htmlspecialchars($back_url) ?>';
}
</script>