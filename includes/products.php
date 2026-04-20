<script>
// Function to check if current time is within any time slot
function isWithinTimeSlots(time1Start, time1End, time2Start, time2End) {
    if ((!time1Start || !time1End) && (!time2Start || !time2End)) return true; // No time restriction
    
    const now = new Date();
    const currentTime = now.getHours() * 60 + now.getMinutes(); // Convert to minutes
    
    // Check if current time is within any active time slot
    const checkTimeSlot = (startTime, endTime) => {
        if (!startTime || !endTime) return false;
        
        const [startHour, startMinute] = startTime.split(':').map(Number);
        const [endHour, endMinute] = endTime.split(':').map(Number);
        
        const startMinutes = startHour * 60 + startMinute;
        const endMinutes = endHour * 60 + endMinute;
        
        return currentTime >= startMinutes && currentTime <= endMinutes;
    };
    
    return checkTimeSlot(time1Start, time1End) || checkTimeSlot(time2Start, time2End);
}

// Function to format time slots for display
function formatTimeSlots(time1Start, time1End, time2Start, time2End) {
    const timeSlots = [];
    
    // Format a single time slot
    const formatSlot = (start, end) => {
        if (!start || !end) return null;
        
        const formatTime = (time) => {
            const [hour, minute] = time.split(':');
            const hourNum = parseInt(hour);
            const ampm = hourNum >= 12 ? 'PM' : 'AM';
            const hour12 = hourNum % 12 || 12;
            return `${hour12}:${minute} ${ampm}`;
        };
        
        return `${formatTime(start)} - ${formatTime(end)}`;
    };
    
    const slot1 = formatSlot(time1Start, time1End);
    const slot2 = formatSlot(time2Start, time2End);
    
    if (slot1) timeSlots.push(slot1);
    if (slot2) timeSlots.push(slot2);
    
    return timeSlots.join(', ');
}

// Function to update product availability based on time slots
function updateProductAvailability() {
    // Get all products with time slots data attributes
    const productItems = document.querySelectorAll('.product-item[data-time1-start]');
    
    productItems.forEach(item => {
        const time1Start = item.dataset.time1Start;
        const time1End = item.dataset.time1End;
        const time2Start = item.dataset.time2Start;
        const time2End = item.dataset.time2End;
        const addButton = item.querySelector('.add-to-cart');
        const timeSlotDisplay = item.querySelector('.tag-time-slot');
        const productImage = item.querySelector('.product-img');
        const productCard = item.querySelector('.product-card');
        
        if (addButton && timeSlotDisplay) {
            const isAvailable = isWithinTimeSlots(time1Start, time1End, time2Start, time2End);
            
            if (isAvailable) {
                // Product is available within time slot
                addButton.disabled = false;
                addButton.classList.remove('disabled');
                timeSlotDisplay.classList.remove('text-danger');
                timeSlotDisplay.classList.add('text-success');
                addButton.innerHTML = '<i class="bi bi-cart-plus"></i> Add';
                
                // Remove grayscale from image
                if (productImage) {
                    productImage.style.filter = 'none';
                    productImage.classList.remove('grayscale');
                }
                
                // Remove overlay from product card
                if (productCard) {
                    productCard.classList.remove('not-available');
                }
            } else {
                // Product is not available now
                addButton.disabled = true;
                addButton.classList.add('disabled');
                timeSlotDisplay.classList.remove('text-success');
                timeSlotDisplay.classList.add('text-danger');
                addButton.innerHTML = '<i class="bi bi-cart-plus"></i> Add';
                
                // Apply grayscale to image
                if (productImage) {
                    productImage.style.filter = 'grayscale(100%)';
                    productImage.classList.add('grayscale');
                }
                
                // Add overlay to product card
                if (productCard) {
                    productCard.classList.add('not-available');
                }
            }
        }
    });
}
</script>
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
// Get currency info from global
$currency_symbol = $GLOBALS['currency_info']['symbol'] ?? '₹';
$currency_code = $GLOBALS['currency_info']['code'] ?? 'INR';
$user_country = $GLOBALS['currency_info']['country'] ?? 'India';

// Get customer data from global
$customer_data = $GLOBALS['customer_data'] ?? null;
$is_customer_logged_in = ($customer_data !== null);

// Get products from user-specific table with tags
$table_name = "products_" . $user_id;

// Check if the user-specific products table exists
$check_table = $conn->prepare("SHOW TABLES LIKE ?");
$check_table->execute([$table_name]);
$table_exists = $check_table->fetch(PDO::FETCH_ASSOC);

if ($table_exists) {
    // Fetch products from user-specific table with tags
    $products_sql = "SELECT p.*, t.tag 
                     FROM $table_name p 
                     LEFT JOIN tags t ON p.tag_id = t.id 
                     ORDER BY p.id ASC";
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
            #dinningBtn { display: none !important; }
            #deliveryBtn { width: 100% !important; margin: 0 !important; }
        </style>
    <?php elseif ($active_subscription['package_id'] == 2): ?>
        <style>
            #deliveryBtn { display: none !important; }
            #dinningBtn { width: 100% !important; margin: 0 !important; }
        </style>
    <?php endif; ?>
<?php endif; ?>

<script>
// Google Maps API configuration - using API key from PHP
const GOOGLE_MAPS_API_KEY = '<?= defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : '' ?>';

// Get currency symbol from PHP
const currencySymbol = '<?= $currency_symbol ?>';
const currencyCode = '<?= $currency_code ?>';

// WhatsApp integration disabled
const ENABLE_WHATSAPP_ORDER = false;

// Get user country for phone validation
const userCountry = '<?= $user_country ?>';

// Customer login status
const isCustomerLoggedIn = <?= json_encode($is_customer_logged_in) ?>;

document.addEventListener('DOMContentLoaded', function() {
    const deliveryBtn = document.getElementById('deliveryBtn');
    const dinningBtn = document.getElementById('dinningBtn');

    const selectedOrderType = localStorage.getItem('selectedOrderType');
    if (selectedOrderType === 'delivery' && deliveryBtn) {
        deliveryBtn.classList.add('active');
    } else if (selectedOrderType === 'dining' && dinningBtn) {
        dinningBtn.classList.add('active');
    }
    
    // Initialize lazy loading with fade-in effect
    initLazyLoading();
    
    // Update product availability based on time slots
    updateProductAvailability();
    
    // Update availability every minute
    setInterval(updateProductAvailability, 60000);
    
    // Add event listeners for address fields (excluding floor as it's removed)
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
    // If already loaded, call callback immediately
    if (window.google && window.google.maps) {
        callback();
        return;
    }
    
    // Check if API key is provided
    if (!GOOGLE_MAPS_API_KEY) {
        alert('Google Maps API key is not configured. Please contact support.');
        return;
    }
    
    // Create script element
    const script = document.createElement('script');
    script.src = `https://maps.googleapis.com/maps/api/js?key=${GOOGLE_MAPS_API_KEY}&libraries=places&callback=initGoogleMapsCallback`;
    script.async = true;
    script.defer = true;
    
    // Store callback globally
    window.initGoogleMapsCallback = function() {
        console.log('Google Maps API loaded successfully');
        callback();
    };
    
    // Handle loading errors
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

    // Show loading state on button
    const btn = document.getElementById('getLocationBtn');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Detecting location...';
    btn.disabled = true;

    navigator.geolocation.getCurrentPosition(
        // Success callback
        (position) => {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;

            // Load Google Maps API and reverse geocode
            loadGoogleMapsAPI(() => {
                const geocoder = new google.maps.Geocoder();
                
                geocoder.geocode(
                    { location: { lat, lng } },
                    (results, status) => {
                        // Reset button state
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
        // Error callback
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
        // Options
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        }
    );
}

// Geo Location
function fillAddressFromGeocode(geocodeResult) {
    const addressComponents = geocodeResult.address_components;
    const formattedAddress = geocodeResult.formatted_address;

    // Helper: find component by type(s)
    const findComponent = (types) => {
        for (let component of addressComponents) {
            if (types.some(type => component.types.includes(type))) {
                return component.long_name;
            }
        }
        return '';
    };

    // Extract all useful components
    const streetNumber = findComponent(['street_number']);
    const route = findComponent(['route']);
    const subpremise = findComponent(['subpremise']);          // Flat/Unit
    const premise = findComponent(['premise']);                // Building name
    const sublocality = findComponent(['sublocality', 'sublocality_level_1', 'sublocality_level_2', 'neighborhood']);
    const locality = findComponent(['locality', 'city']);
    const postalCode = findComponent(['postal_code']);
    const pointOfInterest = findComponent(['point_of_interest', 'establishment']);
    const administrativeArea2 = findComponent(['administrative_area_level_2']); // District
    const administrativeArea1 = findComponent(['administrative_area_level_1']); // State
    const country = findComponent(['country']);

    // ----- BUILDING NAME -----
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

    // ----- FLAT / UNIT -----
    const flatUnitField = document.getElementById('flatUnit');
    if (flatUnitField && subpremise) {
        flatUnitField.value = subpremise;
    }

    // ----- COMPLETE LANDMARK / AREA / CITY (detailed address) -----
    let landmarkParts = [];

    // Add street address if available (e.g., "MG Road" or "12 MG Road")
    let street = '';
    if (streetNumber && route) {
        street = `${streetNumber} ${route}`;
    } else if (route) {
        street = route;
    }
    if (street) landmarkParts.push(street);

    // Add area (sublocality)
    if (sublocality) landmarkParts.push(sublocality);

    // Add city (locality)
    if (locality && (!sublocality || sublocality !== locality)) {
        landmarkParts.push(locality);
    }

    // Add district (if available and different from city)
    if (administrativeArea2 && administrativeArea2 !== locality && administrativeArea2 !== sublocality) {
        landmarkParts.push(administrativeArea2);
    }

    // Add pincode
    if (postalCode) landmarkParts.push(postalCode);

    // Add state
    if (administrativeArea1) landmarkParts.push(administrativeArea1);

    // Add country (optional, but good for clarity)
    if (country && country !== 'India') landmarkParts.push(country); // only add if not India

    let landmark = landmarkParts.join(', ');
    
    // If we still have an empty string, fallback to the full formatted address
    if (!landmark || landmark.trim() === '') {
        landmark = formattedAddress;
    }

    const landmarkField = document.getElementById('landmark');
    if (landmarkField) {
        landmarkField.value = landmark;
    }

    // (Optional) Store extra data in hidden fields – uncomment if needed
    /*
    const stateField = document.getElementById('hiddenState');
    if (stateField) stateField.value = administrativeArea1;
    const pincodeField = document.getElementById('hiddenPincode');
    if (pincodeField) pincodeField.value = postalCode;
    const countryField = document.getElementById('hiddenCountry');
    if (countryField) countryField.value = country;
    */

    // Update address preview (hidden by CSS)
    if (typeof updateAddressPreview === 'function') {
        updateAddressPreview();
    }

    // Optional: log for debugging (remove in production)
    console.log('Landmark set to:', landmark);
}

// Lazy loading with fade-in effect implementation
function initLazyLoading() {
    const lazyImages = document.querySelectorAll('img.product-img-lazy');
    
    if ('IntersectionObserver' in window) {
        // Use IntersectionObserver for modern browsers
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    preloadImage(img);
                    imageObserver.unobserve(img);
                }
            });
        }, {
            rootMargin: '0px 0px 200px 0px', // Load images 200px before they enter viewport
            threshold: 0.01
        });

        lazyImages.forEach(img => {
            imageObserver.observe(img);
        });
    } else {
        // Fallback for older browsers - load all images at once with fade effect
        lazyImages.forEach(img => {
            preloadImage(img);
        });
    }
}

// Preload image with fade-in effect
function preloadImage(img) {
    // Show loading spinner
    const spinner = img.parentElement.querySelector('.img-loading-spinner');
    if (spinner) spinner.style.display = 'block';
    
    // Create new image to load in background
    const newImg = new Image();
    
    newImg.onload = function() {
        // Set the actual image source
        img.src = img.dataset.src;
        
        // Remove lazy class and add loaded class for fade effect
        img.classList.remove('product-img-lazy');
        img.classList.add('product-img-loaded');
        
        // Hide loading spinner
        if (spinner) spinner.style.display = 'none';
        
        // Remove placeholder if it exists
        if (img.classList.contains('product-img-placeholder')) {
            setTimeout(() => {
                img.classList.remove('product-img-placeholder');
            }, 500);
        }
    };
    
    newImg.onerror = function() {
        // Hide image on error
        img.style.display = 'none';
        
        // Hide loading spinner
        if (spinner) spinner.style.display = 'none';
        
        // Adjust button position for missing image
        const productCard = img.closest('.product-card');
        if (productCard) {
            const cartBtnGroup = productCard.querySelector('.card-body .cart_btn_group');
            if (cartBtnGroup) {
                cartBtnGroup.classList.add('top');
            }
        }
    };
    
    // Start loading the image
    newImg.src = img.dataset.src;
}

// Add this to your existing JavaScript code
document.addEventListener('DOMContentLoaded', function() {
    const productsHeading = document.querySelector('.products h6');
    const originalHeading = productsHeading ? productsHeading.textContent : 'Products';
    
    document.querySelectorAll('.tag-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const selectedTag = this.dataset.tag;
            const tagName = this.textContent;
            
            if (productsHeading) {
                if (selectedTag === 'all') {
                    productsHeading.textContent = originalHeading;
                } else {
                    productsHeading.textContent = tagName;
                }
            }
            
            // Scroll to products section with offset
            const productsSection = document.getElementById('productsContainer');
            if (productsSection) {
                const offset = 150; // Adjust this value as needed
                const targetPosition = productsSection.getBoundingClientRect().top + window.pageYOffset - offset;
                
                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });
});

// Format number function with currency support
function formatNumber(num, withSymbol = false) {
    // Convert to number if it's a string
    num = typeof num === 'string' ? parseFloat(num) : num;
    // Handle NaN cases
    if (isNaN(num)) num = 0;
    
    const formatted = num % 1 === 0 ? num.toString() : num.toFixed(2).replace(/\.?0+$/, '');
    
    if (withSymbol) {
        return currencySymbol + formatted;
    }
    return formatted;
}

// Format currency function
function formatCurrency(amount) {
    return currencySymbol + formatNumber(amount);
}

// Function to preview complete address (kept for functionality but UI is hidden)
function updateAddressPreview() {
    // This function is kept for any internal calculations
    // The UI element is hidden via CSS
    return;
}

// Function to validate delivery form
function validateDeliveryForm() {
    const building = document.getElementById('building')?.value;
    const flatUnit = document.getElementById('flatUnit')?.value;
    const customerName = document.getElementById('customerName')?.value;
    const customerPhone = document.getElementById('customerPhone')?.value;
    
    if (!customerName || !customerPhone) {
        alert('Please provide your name and phone number');
        return false;
    }
    
    if (!validatePhoneForOrder()) {
        return false;
    }
    
    if (!building || !flatUnit) {
        alert('Please provide complete address (Building and Flat/Unit No. are required)');
        return false;
    }
    
    return true;
}

// Simple toast notification function
function showToast(message, type = 'success') {
    // Create toast element if it doesn't exist
    let toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        toastContainer.style.zIndex = '9999';
        document.body.appendChild(toastContainer);
    }
    
    const toastId = 'toast-' + Date.now();
    const toastHtml = `
        <div id="${toastId}" class="toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;
    
    toastContainer.insertAdjacentHTML('beforeend', toastHtml);
    
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, { autohide: true, delay: 3000 });
    toast.show();
    
    // Remove toast after it's hidden
    toastElement.addEventListener('hidden.bs.toast', function() {
        this.remove();
    });
}
</script>

<!-- products.php -->
<div class="products">
    <h6>Products</h6>




    <?php if ($delivery_active || $dining_active): ?>
        <!-- Shopping Cart Sidebar -->
        <div class="cart-sidebar">
            <div class="cart-header">
                <h5>Your Cart</h5>
                <button class="btn-close" onclick="closeCart()"></button>
            </div>

            <!-- Cart Section (shown initially) -->
            <div class="cart_group" id="cartGroup">
                <div class="cart-items" id="cartItems"></div>
                <div class="cart-total-details">
                    <div class="cart-subtotal">
                        Subtotal: <span id="cartSubtotal">0.00</span>
                    </div>

                    <!-- Discount Section -->
                    <div class="cart-discount" id="discountSection" style="display: none;">
                        Discount: -<span id="discountAmount">0.00</span> (
                        <span id="discountType"></span>)
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
                <!-- View Cart Button -->
                <button class="btn btn-outline-secondary mb-3 w-100" id="viewCartBtn" style="display: none;">
                    <i class="bi bi-cart blink"></i> View Cart
                </button>
            </div>

            <!-- Order Type Buttons -->
            <?php if ($delivery_active || $dining_active): ?>
                <div class="order-type-buttons mb-3">
                    <div class="choose_order_type">Choose your order type</div>
                    <?php if ($delivery_active): ?>
                        <button class="btn btn-outline-primary w-50" id="deliveryBtn">
                            <i class="bi bi-truck blink"></i> Delivery
                        </button>
                    <?php endif; ?>
                            
                    <?php if ($dining_active): ?>
                        <button class="btn btn-outline-primary w-50" id="dinningBtn">
                            <i class="bi bi-cup-hot blink"></i> Dining
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div style="clear: both;"></div>

            <!-- Customer Details Section (hidden initially) -->
            <div id="customerDetailsSection" style="display: none;">
                <?php if ($dining_active): ?>
                    <div class="customer-details dinning-details" id="diningDetails" style="display: none;">
                        <h6>Dinning Information</h6>
                        <div class="mb-1 col-full">
                            <label for="tableNumber" class="form-label">Table No.*</label>
                            <select class="form-control" id="tableNumber" required>
                                <option value="">Select Table</option>
                                <?php for ($i = 1; $i <= $table_count; $i++): ?>
                                    <option value="<?= $i ?>">Table
                                        <?= $i ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="mb-1 col-half">
                            <label for="dinningName" class="form-label">Name*</label>
                            <input type="text" class="form-control" id="dinningName" 
                                   value="<?= htmlspecialchars($customer_data['name'] ?? '') ?>" 
                                   placeholder="Your name" required>
                        </div>
                        <div class="mb-1 col-half">
                            <label for="dinningPhone" class="form-label">Phone*</label>
                            <?php if ($user_country === 'UAE'): ?>
                                <input type="tel" class="form-control" id="dinningPhone" 
                                       value="<?= htmlspecialchars($customer_data['phone'] ?? '') ?>"
                                       placeholder="Your phone number" pattern="[0-9]{9}" title="Please enter exactly 9 digits" required oninput="validatePhoneNumber(this)">
                            <?php else: ?>
                                <input type="tel" class="form-control" id="dinningPhone" 
                                       value="<?= htmlspecialchars($customer_data['phone'] ?? '') ?>"
                                       placeholder="Your phone number" pattern="[0-9]{10}" title="Please enter exactly 10 digits" required oninput="validatePhoneNumber(this)">
                            <?php endif; ?>
                        </div>
                        <!-- Add Order Notes for Dining -->
                        <div class="mb-1 col-full">
                            <label for="dinningNotes" class="form-label">Order Notes</label>
                            <textarea class="form-control" id="dinningNotes" rows="2" placeholder="Any special instructions"></textarea>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($delivery_active): ?>
                    <div class="customer-details delivery-details" id="deliveryDetails" style="display: none;">
                        <!-- Coupon Section -->
                        <div class="mb-1 col-full">
                            <div class="input-group">
                                <input type="text" class="form-control" id="couponCode" placeholder="Enter coupon code">
                                <button class="btn btn-outline-secondary" type="button" id="applyCouponBtn">Apply</button>
                            </div>
                            <small id="couponMessage" class="text-success"></small>
                        </div>

                        <h6>Delivery Information</h6>
                        <div class="mb-1 col-half">
                            <label for="customerName" class="form-label">Name*</label>
                            <input type="text" class="form-control" id="customerName" 
                                   value="<?= htmlspecialchars($customer_data['name'] ?? '') ?>"
                                   placeholder="Your name" required>
                        </div>
                        <div class="mb-1 col-half">
                            <label for="customerPhone" class="form-label">Phone*</label>
                            <?php if ($user_country === 'UAE'): ?>
                                <input type="tel" class="form-control" id="customerPhone" 
                                       value="<?= htmlspecialchars($customer_data['phone'] ?? '') ?>"
                                       placeholder="Your phone number" pattern="[0-9]{9}" title="Please enter exactly 9 digits" required oninput="validatePhoneNumber(this)">
                            <?php else: ?>
                                <input type="tel" class="form-control" id="customerPhone" 
                                       value="<?= htmlspecialchars($customer_data['phone'] ?? '') ?>"
                                       placeholder="Your phone number" pattern="[0-9]{10}" title="Please enter exactly 10 digits" required oninput="validatePhoneNumber(this)">
                            <?php endif; ?>
                        </div>
                        
                        <?php 
                        $saved_address = [];
                        if ($customer_data && isset($customer_data['delivery_address'])) {
                            $saved_address = json_decode($customer_data['delivery_address'], true);
                        }
                        ?>
                        
                        <!-- Flat/Unit and Landmark on same line -->
                        <div class="row">
                            <div class="mb-1 col-6">
                                <label for="flatUnit" class="form-label">Flat/Unit No.*</label>
                                <input type="text" class="form-control" id="flatUnit" 
                                       value="<?= htmlspecialchars($saved_address['flat_unit'] ?? '') ?>"
                                       required>
                            </div>

                            <div class="mb-1 col-6">
                                <label for="building" class="form-label">Building / Society Name*</label>
                                <input type="text" class="form-control" id="building" 
                                       value="<?= htmlspecialchars($saved_address['building'] ?? '') ?>"
                                       required>
                            </div>
                        </div>

                        <!-- Address Fields -->
                        <div class="mb-1 col-full">
                            <label for="landmark" class="form-label">Landmark / Area / City</label>
                            <input type="text" class="form-control" id="landmark" 
                                   value="<?= htmlspecialchars($saved_address['landmark'] ?? '') ?>">
                        </div>

                        <!-- Auto Location Button -->
                        <div class="mb-1 col-full">
                            <button type="button" class="btn btn-outline-primary btn-sm w-100" id="getLocationBtn" onclick="getCurrentLocation()">
                                <i class="bi bi-geo-alt-fill"></i> <strong>Use My Current Location</strong>
                            </button>
                        </div>
                        
                        <!-- Complete address preview is hidden via CSS -->
                        
                        <div class="mb-1 col-full">
                            <label for="customerNotes" class="form-label">Order Notes</label>
                            <textarea class="form-control" id="customerNotes" rows="2" placeholder="Any special instructions for delivery"></textarea>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="cart-footer">
                    <button class="btn btn-success w-100" id="placeOrderBtn">Place Order</button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row" id="productsContainer">
        <?php
        // Get products from user-specific table with tags
        $table_name = "products_" . $user_id;

        // Check if the user-specific products table exists
        $check_table = $conn->prepare("SHOW TABLES LIKE ?");
        $check_table->execute([$table_name]);
        $table_exists = $check_table->fetch(PDO::FETCH_ASSOC);

        if ($table_exists) {
            // Fetch products from user-specific table with tags including time slots
            $products_sql = "SELECT p.*, t.tag, t.time1_start, t.time1_end, t.time2_start, t.time2_end 
                             FROM $table_name p 
                             LEFT JOIN tags t ON p.tag_id = t.id 
                             ORDER BY p.id ASC";
            $products_stmt = $conn->prepare($products_sql);
            $products_stmt->execute();
            $products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $products = []; // Empty array if table doesn't exist
        }
        ?>

        <?php if (!empty($products)): ?>
            <?php foreach ($products as $product): ?>
                <?php
                // Format time slots for display
                $timeSlotDisplay = '';
                $isAvailableNow = true;
                
                // Check if product has time slots
                $hasTimeSlots = false;
                $timeSlotsArray = [];
                
                // Format a single time slot
                $formatTimeSlot = function($start, $end) {
                    if (!$start || !$end) return null;
                    $startFormatted = date('g:i A', strtotime($start));
                    $endFormatted = date('g:i A', strtotime($end));
                    return $startFormatted . ' - ' . $endFormatted;
                };
                
                // Check time slot 1
                if (!empty($product['time1_start']) && !empty($product['time1_end'])) {
                    $hasTimeSlots = true;
                    $slot1 = $formatTimeSlot($product['time1_start'], $product['time1_end']);
                    if ($slot1) $timeSlotsArray[] = $slot1;
                }
                
                // Check time slot 2
                if (!empty($product['time2_start']) && !empty($product['time2_end'])) {
                    $hasTimeSlots = true;
                    $slot2 = $formatTimeSlot($product['time2_start'], $product['time2_end']);
                    if ($slot2) $timeSlotsArray[] = $slot2;
                }
                
                if ($hasTimeSlots) {
                    $timeSlotDisplay = implode(', ', $timeSlotsArray);
                    
                    // Check if current time is within any time slot
                    $currentTime = date('H:i');
                    $inSlot1 = (!empty($product['time1_start']) && !empty($product['time1_end']) && 
                               $currentTime >= $product['time1_start'] && $currentTime <= $product['time1_end']);
                    $inSlot2 = (!empty($product['time2_start']) && !empty($product['time2_end']) && 
                               $currentTime >= $product['time2_start'] && $currentTime <= $product['time2_end']);
                    
                    $isAvailableNow = $inSlot1 || $inSlot2;
                }
                ?>
                
                <div class="col-sm-12 product-item" 
                     data-name="<?= htmlspecialchars(strtolower($product['product_name'])) ?>" 
                     data-desc="<?= htmlspecialchars(strtolower($product['description'])) ?>"
                     data-tag="<?= isset($product['tag']) ? htmlspecialchars(strtolower($product['tag'])) : '' ?>"
                     <?php if ($hasTimeSlots): ?>
                     data-time1-start="<?= !empty($product['time1_start']) ? htmlspecialchars($product['time1_start']) : '' ?>"
                     data-time1-end="<?= !empty($product['time1_end']) ? htmlspecialchars($product['time1_end']) : '' ?>"
                     data-time2-start="<?= !empty($product['time2_start']) ? htmlspecialchars($product['time2_start']) : '' ?>"
                     data-time2-end="<?= !empty($product['time2_end']) ? htmlspecialchars($product['time2_end']) : '' ?>"
                     <?php endif; ?>>
                    <div class="card product-card <?= ($hasTimeSlots && !$isAvailableNow) ? 'not-available' : '' ?>">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($product['product_name']) ?></h5>
                            <p class="card-text">
                                <?= htmlspecialchars($product['description']) ?>
                            </p>
                            
                            <?php if (!empty($timeSlotDisplay)): ?>
                                <div class="tag-time-slot mb-2 <?= $isAvailableNow ? 'text-success' : 'text-danger' ?>">
                                    <small><i class="bi bi-clock"></i> Available: <?= $timeSlotDisplay ?></small>
                                    <?php if (!$isAvailableNow): ?>
                                        <br><small class="text-muted">Currently not available</small>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-primary fw-bold"><?= $currency_symbol ?><?= number_format($product['price']) ?></span>
                                <span class="badge bg-<?= ($product['quantity'] > 0) ? 'success' : 'danger' ?>" style="display: none;">
                                    <?= ($product['quantity'] > 0) ? 'In Stock' : 'Out of Stock' ?>
                                </span>
                            </div>
                            <?php if ($product['quantity'] > 0): ?>
                                <small class="text-muted">Quantity: <?= $product['quantity'] ?></small>
                            <?php endif; ?>
                            
                            <?php if ($product['quantity'] > 0 && ($delivery_active || $dining_active) && $is_store_open): ?>
                                <div class="mt-3 cart_btn_group <?= empty($product['image_path']) ? 'top' : '' ?>">
                                    <button class="btn btn-primary w-100 add-to-cart <?= ($hasTimeSlots && !$isAvailableNow) ? 'disabled' : '' ?>" 
                                            data-id="<?= htmlspecialchars($product['product_name']) ?>" 
                                            data-name="<?= htmlspecialchars($product['product_name']) ?>" 
                                            data-price="<?= $product['price'] ?>" 
                                            data-max="<?= $product['quantity'] ?>" 
                                            data-image="<?= htmlspecialchars($product['image_path']) ?>"
                                            <?= ($hasTimeSlots && !$isAvailableNow) ? 'disabled' : '' ?>>
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
                                <!-- Lazy loading with fade-in effect -->
                                <div class="aspect-ratio-box">
                                    <img 
                                        src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1 1'%3E%3C/svg%3E" 
                                        data-src="<?= htmlspecialchars($product['image_path']) ?>" 
                                        class="card-img-top product-img product-img-lazy product-img-placeholder <?= ($hasTimeSlots && !$isAvailableNow) ? 'grayscale' : '' ?>" 
                                        alt="<?= htmlspecialchars($product['product_name']) ?>" 
                                        onerror="handleImageError(this)"
                                        <?= ($hasTimeSlots && !$isAvailableNow) ? 'style="filter: grayscale(100%);"' : '' ?>>
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

    <!-- Move search to bottom and make it sticky -->
    <div class="sticky-search-container">
        <div class="tags-filter-container">
            <div class="tags-scroll">
                <?php 
                // Fetch only active tags with time slots
                $active_tags_sql = "SELECT *, 
                                    CONCAT(
                                        IF(time1_start IS NOT NULL AND time1_end IS NOT NULL, 
                                           CONCAT(DATE_FORMAT(time1_start, '%h:%i %p'), ' - ', DATE_FORMAT(time1_end, '%h:%i %p')), 
                                           ''),
                                        IF(time2_start IS NOT NULL AND time2_end IS NOT NULL, 
                                           CONCAT(', ', DATE_FORMAT(time2_start, '%h:%i %p'), ' - ', DATE_FORMAT(time2_end, '%h:%i %p')), 
                                           '')
                                    ) as time_slots_display
                                    FROM tags 
                                    WHERE user_id = :user_id 
                                    AND is_active = 1 
                                    ORDER BY position ASC";
                $active_tags_stmt = $conn->prepare($active_tags_sql);
                $active_tags_stmt->execute([':user_id' => $user_id]);
                $active_tags = $active_tags_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Only show "All" button if there are active tags
                if (!empty($active_tags)): ?>
                    <button class="tag-btn active" data-tag="all">All</button>
                <?php endif; ?>
                
                <?php foreach ($active_tags as $tag): ?>
                    <button class="tag-btn" data-tag="<?= htmlspecialchars(strtolower($tag['tag'])) ?>">
                        <?= htmlspecialchars($tag['tag']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="input-group sticky-search">
            <input type="text" id="productSearch" class="form-control" placeholder="Search products...">
            <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                <i class="bi bi-x"></i>
            </button>
        </div>
    </div>

    <?php if ($delivery_active || $dining_active): ?>
        <div class="cart-button-container" style="display: none;">
            <button class="btn btn-primary cart-button" onclick="openCartIfLoggedIn()">
                <span class="cart-count">0 item added</span>
                <span class="small discount-message" style="display: none;"></span>
                <i class="bi bi-cart blink"></i>
            </button>
        </div>
    <?php endif; ?>
    <!-- Move search to bottom and make it sticky -->

<script>
    // Initialize cart at the very top
    let cart = [];
    let discountAmount = 0;
    let discountType = '';

    // Detect store name from URL
    const storeName = window.location.pathname.split('/')[1] || 'default';
    const cartKey = `cart_${storeName}`;

    // Initialize cart from localStorage
    if (localStorage.getItem(cartKey)) {
        const savedCart = JSON.parse(localStorage.getItem(cartKey));
        cart = savedCart.items || [];
        if (savedCart.coupon) {
            cart.coupon = savedCart.coupon;
        }
    }

    // Add this to your JavaScript section
    document.getElementById('applyCouponBtn').addEventListener('click', function() {
        const couponCode = document.getElementById('couponCode').value.trim();
        const customerPhone = document.getElementById('customerPhone').value.trim(); // Get phone number
        const couponMessage = document.getElementById('couponMessage');
        
        if (!couponCode) {
            couponMessage.textContent = 'Please enter a coupon code';
            couponMessage.className = 'text-danger';
            return;
        }
        
        if (!customerPhone || (userCountry === 'UAE' ? customerPhone.length !== 9 : customerPhone.length !== 10)) {
            couponMessage.textContent = 'Please enter a valid phone number first';
            couponMessage.className = 'text-danger';
            return;
        }
        
        // Show loading state
        const applyBtn = document.getElementById('applyCouponBtn');
        applyBtn.disabled = true;
        applyBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Applying...';
        
        // Send AJAX request to validate coupon
        fetch('validate_coupon.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                user_id: <?= $user_id ?>,
                coupon_code: couponCode,
                cart_subtotal: calculateSubtotal(),
                customer_phone: customerPhone // Pass phone number
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                applyCoupon(data.discount_type, data.discount_value, data.coupon_code);
                couponMessage.textContent = data.message;
                couponMessage.className = 'text-success';
            } else {
                couponMessage.textContent = data.message;
                couponMessage.className = 'text-danger';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            couponMessage.textContent = 'Failed to validate coupon. Please try again.';
            couponMessage.className = 'text-danger';
        })
        .finally(() => {
            applyBtn.disabled = false;
            applyBtn.textContent = 'Apply';
        });
    });

    // In the applyCoupon function
    function applyCoupon(discount_type, discount_value, coupon_code) {
        // Ensure discount_value is a number
        discountAmount = Number(discount_value);
        
        // Store coupon in cart for later use
        if (!cart.coupon) {
            cart.coupon = {};
        }
        
        cart.coupon = {
            code: coupon_code || 'COUPON', // Fallback to 'COUPON' if null
            name: coupon_code || 'COUPON', // Store the coupon code name
            type: discount_type,
            value: discountAmount  // Store as number
        };
        
        saveCart();
        updateCartUI();
    }

    // For View Cart Hide and Show
    function checkCartVisibility() {
        const cartItems = document.getElementById('cartItems');
        const viewCartBtn = document.getElementById('viewCartBtn');
        
        if (cartItems && viewCartBtn) {
            if (cartItems.style.display === 'block' || 
                cartItems.classList.contains('fade-in') || 
                !cartItems.classList.contains('fade-out')) {
                viewCartBtn.style.display = 'none';
            } else {
                viewCartBtn.style.display = 'block';
            }
        }
    }

    // Call this function whenever cart visibility might change
    document.addEventListener('DOMContentLoaded', function() {
        checkCartVisibility();
        
        // Also check after cart updates
        const originalUpdateCartUI = updateCartUI;
        updateCartUI = function() {
            originalUpdateCartUI.apply(this, arguments);
            checkCartVisibility();
        };
    });

    // Fade Animation Functions
    function fadeIn(element, callback) {
        element.style.display = 'block';
        // Force reflow to enable transition
        void element.offsetHeight;
        element.classList.add('fade-in');
        element.classList.remove('fade-out');
        
        setTimeout(() => {
            if (callback) callback();
        }, 300);
    }

    function fadeOut(element, callback) {
        element.classList.add('fade-out');
        element.classList.remove('fade-in');
        
        setTimeout(() => {
            element.style.display = 'none';
            if (callback) callback();
        }, 300);
    }

    // Initialize elements with fade classes
    document.addEventListener('DOMContentLoaded', function() {
        const fadeElements = [
            document.getElementById('cartItems'),
            document.getElementById('customerDetailsSection'),
            document.getElementById('deliveryDetails'),
            document.getElementById('diningDetails')
        ].filter(el => el);
        
        fadeElements.forEach(el => {
            el.classList.add('fade-element');
            if (el.style.display !== 'none') {
                el.classList.add('fade-in');
            }
        });
    });

    // Modified Event Listeners with Fade Animation
    document.addEventListener('DOMContentLoaded', function() {
        const cartItems = document.getElementById('cartItems');
        const customerDetailsSection = document.getElementById('customerDetailsSection');
        const viewCartBtn = document.getElementById('viewCartBtn');
        
        // Initialize View Cart button as hidden (already set in HTML)
        viewCartBtn.classList.add('fade-element');
        
        <?php if ($delivery_active): ?>
        document.getElementById('deliveryBtn').addEventListener('click', function() {
            fadeOut(cartItems, function() {
                fadeIn(customerDetailsSection);
                fadeIn(document.getElementById('deliveryDetails'));
                <?php if ($dining_active): ?>
                fadeOut(document.getElementById('diningDetails'));
                <?php endif; ?>
            });
            
            // Show the View Cart button when switching to delivery
            fadeIn(viewCartBtn);
            
            this.classList.add('active');
            <?php if ($dining_active): ?>
            document.getElementById('dinningBtn').classList.remove('active');
            <?php endif; ?>
            
            localStorage.setItem('selectedOrderType', 'delivery');
        });
        <?php endif; ?>
        
        <?php if ($dining_active): ?>
        document.getElementById('dinningBtn').addEventListener('click', function() {
            fadeOut(cartItems, function() {
                fadeIn(customerDetailsSection);
                fadeIn(document.getElementById('diningDetails'));
                <?php if ($delivery_active): ?>
                fadeOut(document.getElementById('deliveryDetails'));
                <?php endif; ?>
            });
            
            // Show the View Cart button when switching to dining
            fadeIn(viewCartBtn);
            
            this.classList.add('active');
            <?php if ($delivery_active): ?>
            document.getElementById('deliveryBtn').classList.remove('active');
            <?php endif; ?>
            
            localStorage.setItem('selectedOrderType', 'dining');
        });
        <?php endif; ?>
        
        // View Cart button with fade animation
        viewCartBtn.addEventListener('click', function() {
            fadeOut(customerDetailsSection, function() {
                fadeIn(cartItems);
            });
            
            // Hide the View Cart button when viewing cart
            fadeOut(viewCartBtn);
        });
        
        // Restore selected order type
        const selectedOrderType = localStorage.getItem('selectedOrderType');
        if (selectedOrderType === 'delivery' && <?= $delivery_active ? 'true' : 'false' ?>) {
            document.getElementById('deliveryBtn').classList.add('active');
            // Show View Cart button if coming from saved delivery state
            fadeIn(viewCartBtn);
        } else if (selectedOrderType === 'dining' && <?= $dining_active ? 'true' : 'false' ?>) {
            document.getElementById('dinningBtn').classList.add('active');
            // Show View Cart button if coming from saved dining state
            fadeIn(viewCartBtn);
        }
        // No else needed since button is hidden by default
    });

    // Tag filtering functionality
    document.querySelectorAll('.tag-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault(); // Prevent default anchor behavior
            
            // Toggle active state
            document.querySelectorAll('.tag-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            const selectedTag = this.dataset.tag;
            filterProductsByTag(selectedTag);
            
            // Calculate the exact scroll position to show products title
            const productsSection = document.getElementById('productsContainer');
            if (productsSection) {
                // Get the position of the products section
                const productsPosition = productsSection.getBoundingClientRect().top;
                // Get current scroll position
                const currentPosition = window.pageYOffset || document.documentElement.scrollTop;
                // Calculate new position (adjust 100px to whatever offset you need)
                const offset = 150; // Adjust this value as needed
                const newPosition = currentPosition + productsPosition - offset;
                
                // Smooth scroll to the adjusted position
                window.scrollTo({
                    top: newPosition,
                    behavior: 'smooth'
                });
            }
        });
    });

    function openCartIfLoggedIn() {
        if (!isCustomerLoggedIn) {
            // Show login modal
            const modalElement = document.getElementById('loginStatusModal');
            if (modalElement) {
                const modal = new bootstrap.Modal(modalElement);
                modal.show();
            } else {
                alert('Please login with Google to view your cart and place orders.');
            }
            return;
        }
        // If logged in, open the cart sidebar
        toggleCart();
    }

    function filterProductsByTag(tag) {
        const productItems = document.querySelectorAll('.product-item');
        
        if (tag === 'all') {
            productItems.forEach(item => {
                item.style.display = 'block';
            });
            return;
        }
        
        productItems.forEach(item => {
            const productTag = item.dataset.tag;
            if (productTag === tag) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    }

    // Update your product search to work with tags
    document.getElementById('productSearch').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const activeTag = document.querySelector('.tag-btn.active')?.dataset.tag;
        
        const productItems = document.querySelectorAll('.product-item');
        productItems.forEach(item => {
            // Skip if hidden by tag filter
            if (activeTag && activeTag !== 'all' && item.dataset.tag !== activeTag) {
                item.style.display = 'none';
                return;
            }
            
            const productName = item.dataset.name;
            const productDesc = item.dataset.desc;
            
            if (productName.includes(searchTerm) || productDesc.includes(searchTerm)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    });

    // Clear search should also respect the active tag filter
    document.getElementById('clearSearch').addEventListener('click', function() {
        document.getElementById('productSearch').value = '';
        const activeTag = document.querySelector('.tag-btn.active')?.dataset.tag;
        
        document.querySelectorAll('.product-item').forEach(item => {
            if (activeTag && activeTag !== 'all') {
                item.style.display = item.dataset.tag === activeTag ? 'block' : 'none';
            } else {
                item.style.display = 'block';
            }
        });
        document.getElementById('productSearch').focus();
    });

    // Phone number validation – auto‑remove leading zero for India
    function validatePhoneNumber(input) {
        // Remove any non-digit characters
        input.value = input.value.replace(/\D/g, '');

        if (userCountry === 'UAE') {
            // UAE: 9 digits, can start with 0
            if (input.value.length > 9) {
                input.value = input.value.substring(0, 9);
            }
            if (input.value.length !== 9 && input.value.length > 0) {
                input.setCustomValidity('Phone number must be exactly 9 digits');
                input.reportValidity();
                return false;
            }
        } else {
            // For India (and other 10‑digit countries)
            // *** Auto‑remove leading zero for India only ***
            if (userCountry === 'India' && input.value.startsWith('0')) {
                input.value = input.value.substring(1); // strip first '0'
            }

            // Limit to 10 digits
            if (input.value.length > 10) {
                input.value = input.value.substring(0, 10);
            }

            // Validate length (exactly 10 digits if not empty)
            if (input.value.length !== 10 && input.value.length > 0) {
                input.setCustomValidity('Phone number must be exactly 10 digits');
                input.reportValidity();
                return false;
            }
        }

        // Valid phone number
        input.setCustomValidity('');
        return true;
    }

    // Enhanced validation for place order
    function validatePhoneForOrder() {
        const isDelivery = <?= $delivery_active ? 'document.getElementById("deliveryBtn") && document.getElementById("deliveryBtn").classList.contains("active")' : 'false' ?>;
        const phoneInput = isDelivery ? document.getElementById('customerPhone') : document.getElementById('dinningPhone');
        
        if (!phoneInput) return false;
        
        if (!validatePhoneNumber(phoneInput)) {
            return false;
        }
        
        if (userCountry === 'UAE') {
            if (phoneInput.value.length !== 9) {
                alert('Please enter a valid 9-digit phone number');
                phoneInput.focus();
                return false;
            }
        } else {
            if (phoneInput.value.length !== 10) {
                alert('Please enter a valid 10-digit phone number');
                phoneInput.focus();
                return false;
            }
        }
        
        return true;
    }

    // Add to cart button click handler with image animation
    document.querySelectorAll('.add-to-cart').forEach(button => {
        button.addEventListener('click', function() {
            
            // No login check here – user can add to cart even if not logged in
            // Check if product is available based on time slots
            const productItem = this.closest('.product-item');
            const time1Start = productItem.dataset.time1Start;
            const time1End = productItem.dataset.time1End;
            const time2Start = productItem.dataset.time2Start;
            const time2End = productItem.dataset.time2End;
            
            if ((time1Start || time2Start) && !isWithinTimeSlots(time1Start, time1End, time2Start, time2End)) {
                alert('This product is currently not available. Please check the available time slots.');
                return;
            }

            // Add this to adjust the sticky search container
            const stickySearchContainer = document.querySelector('.sticky-search-container');
            if (stickySearchContainer) {
                stickySearchContainer.style.bottom = '65px';
            }

            // Add this to adjust the sticky search container
            const vieworderContainer = document.querySelector('.view-order-container');
            if (vieworderContainer) {
                vieworderContainer.style.bottom = '180px';
            }

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
                    
                    // Trigger animation even for existing items
                    if (product.image_path) {
                        animateProductToCart(this, product.image_path);
                    }
                } else {
                    alert('Maximum quantity reached for this product');
                    return;
                }
            } else {
                cart.push(product);
                
                // Add image animation if product has an image
                if (product.image_path) {
                    animateProductToCart(this, product.image_path);
                }
            }
            
            // Add pulse animation to cart button
            const cartButton = document.querySelector('.cart-button');
            if (cartButton) {
                cartButton.classList.add('cart-item-added');
                setTimeout(() => {
                    cartButton.classList.remove('cart-item-added');
                }, 500);
            }

            saveCart();
            updateCartUI();
            
            // Show cart button container if it's hidden
            const cartButtonContainer = document.querySelector('.cart-button-container');
            if (cartButtonContainer && cartButtonContainer.style.display === 'none') {
                cartButtonContainer.style.display = 'block';
            }
        });
    });

    // Function to animate product image flying to cart
    function animateProductToCart(buttonElement, imageSrc) {
        // Get the product image
        const productCard = buttonElement.closest('.product-card');
        const productImage = productCard ? productCard.querySelector('.product-img') : null;
        
        if (!productImage) return;
        
        // Get cart button container position
        const cartButtonContainer = document.querySelector('.cart-button-container');
        if (!cartButtonContainer) return;
        
        const cartButtonRect = cartButtonContainer.getBoundingClientRect();
        
        // Create flying image clone
        const flyingImage = document.createElement('img');
        flyingImage.src = imageSrc;
        flyingImage.className = 'flying-image';
        
        // Set initial position and size
        const imageRect = productImage.getBoundingClientRect();
        flyingImage.style.width = `${imageRect.width}px`;
        flyingImage.style.height = `${imageRect.height}px`;
        flyingImage.style.left = `${imageRect.left}px`;
        flyingImage.style.top = `${imageRect.top}px`;
        
        // Calculate final position (center of cart button container)
        const finalX = (cartButtonRect.left + (cartButtonRect.width / 2)) - (imageRect.width / 2);
        const finalY = (cartButtonRect.top + (cartButtonRect.height / 2)) - (imageRect.height / 2);
        
        // Calculate mid position for a curved path
        const midX = (finalX + imageRect.left) / 2 - 50; // Curve to the left
        const midY = (finalY + imageRect.top) / 2 - 100; // Curve upward
        
        // Set CSS custom properties for animation path
        flyingImage.style.setProperty('--final-x', `${finalX - imageRect.left}px`);
        flyingImage.style.setProperty('--final-y', `${finalY - imageRect.top}px`);
        flyingImage.style.setProperty('--mid-x', `${midX - imageRect.left}px`);
        flyingImage.style.setProperty('--mid-y', `${midY - imageRect.top}px`);
        
        // Add to document
        document.body.appendChild(flyingImage);
        
        // Remove element after animation completes
        setTimeout(() => {
            if (flyingImage.parentNode) {
                flyingImage.parentNode.removeChild(flyingImage);
            }
        }, 1000);
    }

    function saveCart() {
        localStorage.setItem(cartKey, JSON.stringify({
            items: cart.filter(item => item.id), // Only save actual cart items
            coupon: cart.coupon || null // Save coupon if it exists
        }));
    }

    function updateCartUI() {
        const cartItemsContainer = document.getElementById('cartItems');
        const cartTotalDetails = document.querySelector('.cart-total-details');
        const dinningBtn = document.getElementById('dinningBtn');
        const dinningDetails = document.querySelector('.dinning-details');
        const deliveryDetails = document.querySelector('.delivery-details');
        const orderTypeButtons = document.querySelector('.order-type-buttons');
        const cartFooter = document.querySelector('.cart-footer');
        const cartButtonContainer = document.querySelector('.cart-button-container');
        const emptyCartMsg = document.createElement('div');
        const discountMessageElement = document.querySelector('.cart-button .discount-message');
        const discountSection = document.getElementById('discountSection');

        // Clear existing empty message if any
        const existingEmptyMsg = cartItemsContainer.querySelector('.empty-cart-message');
        if (existingEmptyMsg) {
            existingEmptyMsg.remove();
        }

        cartItemsContainer.innerHTML = '';

        // Clear discount section if no coupon or discount
        if (!cart.coupon && discountSection) {
            discountSection.style.display = 'none';
        }
        
        // Clear discount message in cart button
        if (discountMessageElement) {
            discountMessageElement.style.display = 'none';
        }

        // Handle empty cart case
        if (cart.length === 0) {
            // Clear any existing coupon
            if (cart.coupon) {
                delete cart.coupon;
            }

            // Create and show empty cart message
            emptyCartMsg.className = 'empty-cart-message text-center py-4';
            emptyCartMsg.innerHTML = `
                <i class="bi bi-cart-x fs-1 text-muted"></i>
                <p class="mt-2">Your cart is empty</p>
                <button class="btn btn-sm btn-outline-primary" onclick="closeCart()">
                    Continue Shopping
                </button>
            `;
            cartItemsContainer.appendChild(emptyCartMsg);
            
            // Hide elements that shouldn't show when cart is empty
            if (cartTotalDetails) cartTotalDetails.style.display = 'none';
            if (dinningBtn) dinningBtn.style.display = 'none';
            if (dinningDetails) dinningDetails.style.display = 'none';
            if (deliveryDetails) deliveryDetails.style.display = 'none';
            if (orderTypeButtons) orderTypeButtons.style.display = 'none';
            if (cartFooter) cartFooter.style.display = 'none';
            if (discountMessageElement) discountMessageElement.style.display = 'none';
            if (discountSection) discountSection.style.display = 'none';
            
            // Update cart count and hide cart button container
            document.querySelector('.cart-count').textContent = '0 items added';
            if (cartButtonContainer) cartButtonContainer.style.display = 'none';
            return; // Exit early since cart is empty
        }

        // Cart has items - proceed with normal display
        let subtotal = 0;
        const isDelivery = <?= $delivery_active ? 'document.getElementById("deliveryBtn") && document.getElementById("deliveryBtn").classList.contains("active")' : 'false' ?>;
        const deliveryCharge = <?= isset($delivery_charges['delivery_charge']) ? $delivery_charges['delivery_charge'] : 0 ?>;
        const freeDeliveryMin = <?= isset($delivery_charges['free_delivery_minimum']) ? $delivery_charges['free_delivery_minimum'] : 0 ?>;
        const minimumOrderAmount = <?= isset($delivery_charges['minimum_order_amount']) ? $delivery_charges['minimum_order_amount'] : 0 ?>;
        const gstPercent = <?= $gst_percent ?? 0 ?>;

        // Show order type buttons if they were hidden
        if (orderTypeButtons) orderTypeButtons.style.display = 'block';
        if (cartFooter) cartFooter.style.display = 'block';
        if (cartButtonContainer) cartButtonContainer.style.display = 'block';

        // Calculate subtotal and populate cart items
        cart.forEach((item, index) => {
            if (!item.id) return; // Skip coupon object if present
            
            subtotal += item.price * item.quantity;
            const productImage = item.image_path ? item.image_path : 'images/no-image.jpg';

            const itemElement = document.createElement('div');
            itemElement.className = 'cart-item';
            itemElement.innerHTML = `
                <div class="cart-item-info d-flex">
                    <!-- ${productImage ? `<img src="${productImage}" class="cart-item-img" alt="${item.name}" onerror="this.style.display='none'">` : ''}-->
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

        // Show cart total details
        if (cartTotalDetails) cartTotalDetails.style.display = 'block';

        // Calculate discount
        let discountAmount = 0;
        let discountType = '';

        // 1. Check for coupon discount first
        if (cart.coupon) {
            const couponCode = cart.coupon.code || 'COUPON'; // Fallback if code is null
            if (cart.coupon.type === 'percentage') {
                discountAmount = Number((subtotal * Number(cart.coupon.value)) / 100);
                discountType = cart.coupon.value + '% coupon (' + couponCode + ')';
            } else {
                discountAmount = Number(cart.coupon.value);
                discountType = 'Flat ' + currencySymbol + formatNumber(cart.coupon.value) + ' OFF (' + couponCode + ')';
            }
            
            // Show discount section
            if (discountSection) {
                discountSection.style.display = 'block';
                document.getElementById('discountAmount').textContent = formatNumber(discountAmount);
                document.getElementById('discountType').textContent = discountType;
            }
            
            // Show discount applied message in cart button
            if (discountMessageElement) {
                discountMessageElement.innerHTML = `<i class="bi bi-tag-fill"></i> ${discountType} applied!`;
                discountMessageElement.style.display = 'block';
            }
        }

        // 2. Check for automatic discounts if no coupon applied
        else if (<?php if (!empty($discounts)): ?>true<?php else: ?>false<?php endif; ?>) {
            const discounts = <?= json_encode($discounts) ?>;
            let applicableDiscount = null;
            let nextDiscount = null;

            // Sort discounts by min_cart_value ascending
            discounts.sort((a, b) => a.min_cart_value - b.min_cart_value);

            // Find applicable discount and next discount
            for (let i = 0; i < discounts.length; i++) {
                const discount = discounts[i];
                
                if (subtotal >= discount.min_cart_value) {
                    applicableDiscount = discount;
                }
                
                if (!nextDiscount && subtotal < discount.min_cart_value) {
                    nextDiscount = discount;
                }
            }

            if (applicableDiscount) {
                if (applicableDiscount.discount_in_percent !== null && applicableDiscount.discount_in_percent > 0) {
                    discountAmount = (subtotal * applicableDiscount.discount_in_percent) / 100;
                    discountType = applicableDiscount.discount_in_percent + '% discount';
                } else if (applicableDiscount.discount_in_flat !== null && applicableDiscount.discount_in_flat > 0) {
                    discountAmount = parseFloat(applicableDiscount.discount_in_flat);
                    discountType = 'Flat ' + currencySymbol + formatNumber(applicableDiscount.discount_in_flat) + ' OFF';
                }

                // Ensure discountAmount doesn't exceed subtotal
                if (discountAmount > subtotal) {
                    discountAmount = subtotal;
                }

                // Show discount section only if a discount is actually applied
                if (discountAmount > 0 && discountSection) {
                    discountSection.style.display = 'block';
                    document.getElementById('discountAmount').textContent = formatNumber(discountAmount);
                    document.getElementById('discountType').textContent = discountType;
                    
                    // Show discount applied message in cart button
                    if (discountMessageElement) {
                        discountMessageElement.innerHTML = `<i class="bi bi-tag-fill"></i> ${discountType} applied!`;
                        discountMessageElement.style.display = 'block';
                    }
                } else if (discountSection) {
                    discountSection.style.display = 'none';
                    if (discountMessageElement) discountMessageElement.style.display = 'none';
                }
            } else {
                // No discount applied but discounts available
                if (discountSection) discountSection.style.display = 'none';
                
                // Show message about how to get discount in cart button
                if (discountMessageElement && discounts.length > 0) {
                    const minDiscount = discounts[0].min_cart_value;
                    const needed = minDiscount - subtotal;
                    if (needed > 0) {
                        discountMessageElement.innerHTML = `<i class="bi bi-tag"></i> Add ${currencySymbol}${formatNumber(needed)} more for discount`;
                        discountMessageElement.style.display = 'block';
                    } else {
                        discountMessageElement.style.display = 'none';
                    }
                }
            }

            // Show next discount info if there's a higher discount available
            if (nextDiscount) {
                const amountNeeded = nextDiscount.min_cart_value - subtotal;
                let nextDiscountText = '';
                
                if (nextDiscount.discount_in_percent) {
                    nextDiscountText = `Add ${currencySymbol}${formatNumber(amountNeeded)} more for ${formatNumber(nextDiscount.discount_in_percent)}% discount`;
                } else if (nextDiscount.discount_in_flat) {
                    nextDiscountText = `Add ${currencySymbol}${formatNumber(amountNeeded)} more for ${currencySymbol}${formatNumber(nextDiscount.discount_in_flat)} OFF`;
                }
                
                // Create or update next discount info element
                if (!document.getElementById('nextDiscountInfo')) {
                    const nextDiscountElement = document.createElement('div');
                    nextDiscountElement.id = 'nextDiscountInfo';
                    nextDiscountElement.className = 'cart-next-discount text-center py-2 text-success';
                    nextDiscountElement.innerHTML = `<small><i class="bi bi-tag"></i> ${nextDiscountText}</small>`;
                    
                    // Insert after discount section or before GST section
                    const insertPoint = discountSection.nextElementSibling || 
                                       document.querySelector('.cart-gst-charges') || 
                                       document.querySelector('.cart-delivery-charges') ||
                                       document.querySelector('.cart-total');
                    insertPoint.parentNode.insertBefore(nextDiscountElement, insertPoint);
                } else {
                    document.getElementById('nextDiscountInfo').innerHTML = `<small><i class="bi bi-tag"></i> ${nextDiscountText}</small>`;
                    document.getElementById('nextDiscountInfo').style.display = 'block';
                }
            } else if (document.getElementById('nextDiscountInfo')) {
                // Hide if no next discount available
                document.getElementById('nextDiscountInfo').style.display = 'none';
            }
        }

        // Update subtotal and total
        document.getElementById('cartSubtotal').textContent = formatNumber(subtotal);

        // Calculate GST on amount after discount
        let amountAfterDiscount = subtotal - discountAmount;
        if (amountAfterDiscount < 0) {
            amountAfterDiscount = 0;
        }

        let total = amountAfterDiscount;
        if (gstPercent > 0) {
            const gstAmount = (amountAfterDiscount * gstPercent) / 100;
            document.getElementById('gstCharges').textContent = formatNumber(gstAmount);
            total += gstAmount;
        }

        // Calculate delivery charges ONLY if cart is NOT empty and delivery is active AND selected
        let actualDeliveryCharge = 0;
        const cartDeliveryChargesRow = document.querySelector('.cart-delivery-charges');
        if (isDelivery && deliveryCharge !== undefined) {
            if (freeDeliveryMin > 0 && amountAfterDiscount >= freeDeliveryMin) {
                // Free delivery because subtotal meets minimum
                actualDeliveryCharge = 0;
                document.getElementById('deliveryChargeText').textContent = 'FREE (Order above ' + currencySymbol + formatNumber(freeDeliveryMin) + ')';
                if (cartDeliveryChargesRow) cartDeliveryChargesRow.classList.add('free');
            } else {
                // Apply normal delivery charge
                actualDeliveryCharge = parseFloat(deliveryCharge);
                if (freeDeliveryMin > 0) {
                    // Show message about how much more to spend for free delivery
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

        // Handle delivery/dining button visibility
        <?php if ($delivery_active && $dining_active): ?>
            if (dinningBtn) dinningBtn.style.display = 'inline-block';
            
            // Re-apply original logic for active button display
            const deliveryBtn = document.getElementById('deliveryBtn');
            if (deliveryBtn && deliveryBtn.classList.contains('active')) {
                if (deliveryDetails) deliveryDetails.style.display = 'block';
                if (dinningDetails) dinningDetails.style.display = 'none';
            } else if (dinningBtn && dinningBtn.classList.contains('active')) {
                if (deliveryDetails) deliveryDetails.style.display = 'none';
                if (dinningDetails) dinningDetails.style.display = 'block';
            }
        <?php elseif ($delivery_active): ?>
            if (deliveryDetails) deliveryDetails.style.display = 'block';
        <?php endif; ?>
        
        // Show/hide minimum order amount warning
        const existingMinOrderMsg = document.getElementById('minimumOrderMsg');
        if (isDelivery && minimumOrderAmount > 0 && subtotal < minimumOrderAmount) {
            if (!existingMinOrderMsg) {
                const minOrderMsg = document.createElement('div');
                minOrderMsg.id = 'minimumOrderMsg';
                minOrderMsg.className = 'cart-minimum-order text-warning text-center small py-1';
                minOrderMsg.innerHTML = `<i class="bi bi-exclamation-triangle-fill"></i> Minimum order amount is ${currencySymbol}${formatNumber(minimumOrderAmount)}`;
                
                // Insert after subtotal or before delivery charges
                const insertAfter = document.querySelector('.cart-subtotal');
                if (insertAfter && insertAfter.parentNode) {
                    insertAfter.insertAdjacentElement('afterend', minOrderMsg);
                }
            } else {
                existingMinOrderMsg.style.display = 'block';
            }
        } else if (existingMinOrderMsg) {
            existingMinOrderMsg.style.display = 'none';
        }
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

    // Update quantity via input field
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
        
        // Hide cart button container if no items left
        if (cart.length === 0) {
            const cartButtonContainer = document.querySelector('.cart-button-container');
            if (cartButtonContainer) {
                cartButtonContainer.style.display = 'none';
                
                // Reset sticky search container position
                const stickySearchContainer = document.querySelector('.sticky-search-container');
                if (stickySearchContainer) {
                    stickySearchContainer.style.bottom = ''; // Reset to original value
                }

                // Reset sticky search container position
                const vieworderContainer = document.querySelector('.view-order-container');
                if (vieworderContainer) {
                    vieworderContainer.style.bottom = ''; // Reset to original value
                }
            }
        }
    }

    // Cart toggle controls
    function toggleCart() {
        document.querySelector('.cart-sidebar').classList.toggle('open');
    }

    function showCart() {
        document.querySelector('.cart-sidebar').classList.add('open');
    }

    function closeCart() {
        document.querySelector('.cart-sidebar').classList.remove('open');
    }

    // Reset sticky search container position
    const stickySearchContainer = document.querySelector('.sticky-search-container');
    if (stickySearchContainer) {
        stickySearchContainer.style.bottom = ''; // Reset to original value
    }

    const vieworderContainer = document.querySelector('.view-order-container');
    if (vieworderContainer) {
        vieworderContainer.style.bottom = ''; // Reset to original value
    }

    // Order type toggle functionality
    <?php if ($delivery_active && $dining_active): ?>
    document.getElementById('deliveryBtn').addEventListener('click', function() {
        this.classList.add('active');
        document.getElementById('dinningBtn').classList.remove('active');
        document.querySelector('.delivery-details').style.display = 'block';
        document.querySelector('.dinning-details').style.display = 'none';
        updateCartUI();
    });

    document.getElementById('dinningBtn').addEventListener('click', function() {
        this.classList.add('active');
        document.getElementById('deliveryBtn').classList.remove('active');
        document.querySelector('.dinning-details').style.display = 'block';
        document.querySelector('.delivery-details').style.display = 'none';
        updateCartUI();
    });
    <?php endif; ?>

    // Show order success popup
    function showOrderSuccessPopup() {
        createConfetti();
        const popup = document.getElementById('orderSuccessPopup');
        popup.classList.add('active');
    }

    // Close order success popup
    function closeOrderSuccessPopup() {
      const popup = document.getElementById('orderSuccessPopup');
      popup.classList.remove('active');
    }

    function calculateSubtotal() {
        return cart.filter(item => item.id).reduce((sum, item) => sum + (item.price * item.quantity), 0);
    }

    function placeOrder() {
        // Check if customer is logged in
        if (!isCustomerLoggedIn) {
            alert('Please login with Google before placing an order.');
            return;
        }

        if (cart.length === 0) {
            alert('Your cart is empty');
            return;
        }

        // Validate phone number first
        if (!validatePhoneForOrder()) {
            return;
        }

        const isDelivery = <?= $delivery_active ? 'document.getElementById("deliveryBtn") && document.getElementById("deliveryBtn").classList.contains("active")' : 'false' ?>;
        const deliveryCharge = <?= isset($delivery_charges['delivery_charge']) ? $delivery_charges['delivery_charge'] : 0 ?>;
        const freeDeliveryMin = <?= isset($delivery_charges['free_delivery_minimum']) ? $delivery_charges['free_delivery_minimum'] : 0 ?>;
        const minimumOrderAmount = <?= isset($delivery_charges['minimum_order_amount']) ? $delivery_charges['minimum_order_amount'] : 0 ?>;
        const gstPercent = <?= $gst_percent ?? 0 ?>;
        
        // Get discount information from UI
        const discountSection = document.getElementById('discountSection');
        let discountAmount = 0;
        let discountType = '';
        
        if (discountSection && discountSection.style.display !== 'none') {
            discountAmount = parseFloat(document.getElementById('discountAmount').textContent) || 0;
            discountType = document.getElementById('discountType').textContent || '';
        }
        
        // Collect customer details based on order type
        let customerName, customerPhone, deliveryAddress, tableNumber, orderNotes;
        let orderData = {};
        
        if (isDelivery) {
            // Get delivery form values
            const building = document.getElementById('building')?.value;
            const flatUnit = document.getElementById('flatUnit')?.value;
            const landmark = document.getElementById('landmark')?.value || '';
            customerName = document.getElementById('customerName')?.value;
            customerPhone = document.getElementById('customerPhone')?.value;
            
            // Validate delivery form
            if (!customerName || !customerPhone) {
                alert('Please provide your name and phone number');
                return;
            }
            
            if (!validatePhoneForOrder()) {
                return;
            }
            
            if (!building || !flatUnit) {
                alert('Please provide complete address (Building and Flat/Unit No. are required)');
                return;
            }
            
            // ===== MINIMUM ORDER AMOUNT VALIDATION =====
            const subtotal = calculateSubtotal();
            if (minimumOrderAmount > 0 && subtotal < minimumOrderAmount) {
                alert('Minimum order amount is ' + currencySymbol + formatNumber(minimumOrderAmount) + 
                      '. Your current subtotal is ' + currencySymbol + formatNumber(subtotal));
                return;
            }
            // ==========================================
            
            // Create formatted delivery address
            const addressParts = [];
            if (flatUnit) addressParts.push(`Flat/Unit: ${flatUnit}`);
            if (building) addressParts.push(building);
            if (landmark) addressParts.push(`Landmark: ${landmark}`);
            
            deliveryAddress = addressParts.join(', ');
            orderNotes = document.getElementById('customerNotes')?.value || '';
            
            // Prepare order data for delivery
            orderData = {
                user_id: <?= $user_id ?>,
                currency_symbol: currencySymbol,
                currency_code: currencyCode,
                order_type: 'delivery',
                customer_name: customerName,
                customer_phone: customerPhone,
                delivery_address: deliveryAddress,
                address_components: {
                    building: building,
                    floor: '', // Floor field removed
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
                coupon_data: cart.coupon || null
            };
        } else {
            // Get dining form values
            customerName = document.getElementById('dinningName')?.value;
            customerPhone = document.getElementById('dinningPhone')?.value;
            tableNumber = document.getElementById('tableNumber')?.value;
            orderNotes = document.getElementById('dinningNotes')?.value || '';
            
            if (!customerName || !tableNumber) {
                alert('Please provide your name and table number');
                return;
            }
            
            if (!validatePhoneForOrder()) {
                return;
            }
            
            // Prepare order data for dining
            orderData = {
                user_id: <?= $user_id ?>,
                currency_symbol: currencySymbol,
                currency_code: currencyCode,
                order_type: 'dining',
                customer_name: customerName,
                customer_phone: customerPhone,
                delivery_address: null,
                address_components: null,
                table_number: tableNumber,
                order_notes: orderNotes,
                items: cart.filter(item => item.id).map(item => ({
                    name: item.name,
                    price: item.price,
                    quantity: item.quantity
                })),
                discount_amount: discountAmount,
                discount_type: discountType,
                gst_percent: gstPercent,
                delivery_charge: 0,
                free_delivery_min: 0,
                coupon_data: cart.coupon || null
            };
        }
        
        // Show loading state
        const placeOrderBtn = document.getElementById('placeOrderBtn');
        const originalBtnText = placeOrderBtn.innerHTML;
        placeOrderBtn.disabled = true;
        placeOrderBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Order Processing...';
        
        // Use the correct path for place_order.php
        const placeOrderUrl = 'place_order.php';
        
        // Send order data to server
        fetch(placeOrderUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
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
                // After order success, update customer details (if logged in)
                if (isCustomerLoggedIn) {
                    const updateData = {
                        user_id: <?= $user_id ?>,
                        customer_id: <?= json_encode($customer_data['id'] ?? 0) ?>,
                        phone: isDelivery ? document.getElementById('customerPhone').value : document.getElementById('dinningPhone').value,
                        address: {
                            building: document.getElementById('building')?.value || '',
                            flat_unit: document.getElementById('flatUnit')?.value || '',
                            landmark: document.getElementById('landmark')?.value || ''
                        }
                    };
                    fetch('update_customer_details.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(updateData)
                    }).catch(err => console.error('Failed to update customer details:', err));
                }
                
                // Clear the cart
                cart = [];
                localStorage.removeItem(cartKey); // Also remove from localStorage
                
                // Clear all form fields
                if (document.getElementById('customerName')) document.getElementById('customerName').value = '';
                if (document.getElementById('customerPhone')) document.getElementById('customerPhone').value = '';
                if (document.getElementById('building')) document.getElementById('building').value = '';
                if (document.getElementById('flatUnit')) document.getElementById('flatUnit').value = '';
                if (document.getElementById('landmark')) document.getElementById('landmark').value = '';
                if (document.getElementById('customerNotes')) document.getElementById('customerNotes').value = '';
                if (document.getElementById('dinningName')) document.getElementById('dinningName').value = '';
                if (document.getElementById('dinningPhone')) document.getElementById('dinningPhone').value = '';
                if (document.getElementById('tableNumber')) document.getElementById('tableNumber').value = '';
                if (document.getElementById('dinningNotes')) document.getElementById('dinningNotes').value = '';
                
                updateCartUI(); // Update UI to show empty cart
                
                // Close the cart sidebar
                closeCart();
                
                // Store order ID for redirect
                const orderId = data.order_id;
                
                // Reset coupon fields
                if (cart.coupon) {
                    delete cart.coupon;
                    if (document.getElementById('couponCode')) document.getElementById('couponCode').value = '';
                    if (document.getElementById('couponMessage')) {
                        document.getElementById('couponMessage').textContent = '';
                        document.getElementById('couponMessage').className = 'text-success';
                    }
                }

                // Reset sticky search container position
                const stickySearchContainer = document.querySelector('.sticky-search-container');
                if (stickySearchContainer) {
                    stickySearchContainer.style.bottom = ''; // Reset to original value
                }

                const vieworderContainer = document.querySelector('.view-order-container');
                if (vieworderContainer) {
                    vieworderContainer.style.bottom = ''; // Reset to original value
                }
                
                // Hide cart button container
                const cartButtonContainer = document.querySelector('.cart-button-container');
                if (cartButtonContainer) {
                    cartButtonContainer.style.display = 'none';
                }
                
                // Redirect to order status page
                const profileUrl = '<?= $profile_url ?>';
                if (orderId) {
                    window.location.href = `order_status.php?order_id=${orderId}&profile_url=${profileUrl}`;
                } else {
                    // Fallback if no order ID returned
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

    // Toast notification function
    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast-notification ${type}`;
        toast.innerHTML = `
            <div class="toast-icon">
                ${type === 'success' ? '<i class="bi bi-check-circle-fill"></i>' : '<i class="bi bi-exclamation-circle-fill"></i>'}
            </div>
            <div class="toast-message">${message}</div>
            <div class="toast-close" onclick="this.parentElement.remove()">
                <i class="bi bi-x"></i>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            toast.remove();
        }, 5000);
    }

    // Add click handler to the Place Order button
    document.getElementById('placeOrderBtn').addEventListener('click', placeOrder);

    // Add this to your existing JavaScript code
    document.getElementById('viewCartBtn').addEventListener('click', function() {
        // Clear coupon from cart
        if (cart.coupon) {
            delete cart.coupon;
            saveCart();
        }
        
        // Clear coupon input field and message
        const couponCodeInput = document.getElementById('couponCode');
        if (couponCodeInput) {
            couponCodeInput.value = '';
        }
        
        const couponMessage = document.getElementById('couponMessage');
        if (couponMessage) {
            couponMessage.textContent = '';
            couponMessage.className = 'text-success';
        }
        
        // Update cart UI to reflect changes
        updateCartUI();
        
        // Continue with existing view cart functionality
        fadeOut(customerDetailsSection, function() {
            fadeIn(cartItems);
        });
        
        // Hide the View Cart button when viewing cart
        fadeOut(this);
    });

    // Clear coupon when clicking dining button
    if (document.getElementById('dinningBtn')) {
        document.getElementById('dinningBtn').addEventListener('click', function() {
            clearCoupon();
        });
    }

    // Clear coupon when clicking close button (assuming it has class .btn-close)
    document.querySelectorAll('.btn-close').forEach(closeBtn => {
        closeBtn.addEventListener('click', function() {
            clearCoupon();
        });
    });

    // Function to clear coupon from cart and UI
    function clearCoupon() {
        // Clear coupon from cart
        if (cart.coupon) {
            delete cart.coupon;
            saveCart();
        }
        
        // Clear coupon input field and message
        const couponCodeInput = document.getElementById('couponCode');
        if (couponCodeInput) {
            couponCodeInput.value = '';
        }
        
        const couponMessage = document.getElementById('couponMessage');
        if (couponMessage) {
            couponMessage.textContent = '';
            couponMessage.className = 'text-success';
        }
        
        // Update cart UI to reflect changes
        updateCartUI();
    }

    function createConfetti() {
      const confettiContainer = document.getElementById('confettiContainer');
      if (!confettiContainer) return;
      
      confettiContainer.innerHTML = '';
      confettiContainer.style.display = 'block';
      
      const colors = ['#f94144', '#f3722c', '#f8961e', '#f9c74f', '#90be6d', '#43aa8b', '#577590'];
      const confettiCount = 150;
      
      for (let i = 0; i < confettiCount; i++) {
        const confetti = document.createElement('div');
        confetti.className = 'confetti';
        
        // Random properties
        const color = colors[Math.floor(Math.random() * colors.length)];
        const size = Math.random() * 10 + 5;
        const left = Math.random() * 100;
        const animationDelay = Math.random() * 5;
        const animationDuration = Math.random() * 3 + 3;
        
        // Apply styles
        confetti.style.backgroundColor = color;
        confetti.style.width = `${size}px`;
        confetti.style.height = `${size}px`;
        confetti.style.left = `${left}%`;
        confetti.style.animationDelay = `${animationDelay}s`;
        confetti.style.animationDuration = `${animationDuration}s`;
        
        // Random shape
        if (Math.random() > 0.5) {
          confetti.style.borderRadius = '50%';
        }
        
        confettiContainer.appendChild(confetti);
      }
      
      // Hide confetti after animation completes
      setTimeout(() => {
        confettiContainer.style.display = 'none';
      }, 60000);
    }
</script>

    <!-- Add this to your HTML (before the closing body tag) -->
    <div class="confetti-container" id="confettiContainer"></div>

<!-- View Order Button -->
<?php
// Check if there's a recent order for this user
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
// Function to redirect to profile page
function redirectToProfile() {
    // Get the profile URL from PHP variable
    const profileUrl = '<?= $profile_url ?>';
    const currentDomain = window.location.hostname;
    
    // Close the popup first
    closeOrderSuccessPopup();
    
    // Determine the correct URL based on current domain
    let redirectUrl;
    
    if (currentDomain === 'goldcoinrestaurant.in') {
        redirectUrl = `https://goldcoinrestaurant.in`;
    } else if (currentDomain === 'swadishtrasoi.in') {
        redirectUrl = `https://swadishtrasoi.in`;
    } else if (currentDomain === 'tastespecial.in') {
        redirectUrl = `https://tastespecial.in`;
    } else {
        // Fallback to current domain
        redirectUrl = `${window.location.origin}/${profileUrl}`;
    }
    
    // Redirect to the profile page after a short delay for smooth UX
    setTimeout(() => {
        window.location.href = redirectUrl;
    }, 300);
}

// Function to close the order success popup
function closeOrderSuccessPopup() {
    const popup = document.getElementById('orderSuccessPopup');
    popup.classList.remove('active');
}

// Function to show the order success popup
function showOrderSuccessPopup() {
    createConfetti();
    const popup = document.getElementById('orderSuccessPopup');
    popup.classList.add('active');
}
</script>

<script>
// Function to show/hide View Order button
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

// Function to redirect to order status page
function viewLastOrder() {
    const lastOrderId = localStorage.getItem('lastOrderId');
    const profileUrl = '<?= $profile_url ?>';
    
    if (lastOrderId) {
        window.location.href = `order_status.php?order_id=${lastOrderId}&profile_url=${profileUrl}`;
    }
}

// Check on page load
document.addEventListener('DOMContentLoaded', function() {
    checkAndShowViewOrderButton();
    
    // Also check when cart is updated (in case it affects the button)
    const originalUpdateCartUI = updateCartUI;
    updateCartUI = function() {
        originalUpdateCartUI.apply(this, arguments);
        checkAndShowViewOrderButton();
    };
});

// Clear last order when placing a new order
const originalPlaceOrder = placeOrder;
placeOrder = function() {
    // Clear the last order before placing new one
    localStorage.removeItem('lastOrderId');
    localStorage.removeItem('lastOrderUserId');
    checkAndShowViewOrderButton();
    
    // Call original function
    originalPlaceOrder.apply(this, arguments);
};
</script>

<script>
function goBackToMenu(orderId) {
    // Set cookie that expires in 24 hours
    const expires = new Date();
    expires.setTime(expires.getTime() + (24 * 60 * 60 * 1000)); // 24 hours
    document.cookie = `lastOrderId=${orderId}; expires=${expires.toUTCString()}; path=/`;
    document.cookie = `lastOrderUserId=<?= $user_id ?>; expires=${expires.toUTCString()}; path=/`;
    
    // Also store in localStorage for immediate access
    localStorage.setItem('lastOrderId', orderId);
    localStorage.setItem('lastOrderUserId', '<?= $user_id ?>');
    
    // Redirect to profile page
    window.location.href = 'https://deegeecard.com/<?= htmlspecialchars($back_url) ?>';
}
</script>