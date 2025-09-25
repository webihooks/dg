
<?php if ($active_subscription): ?>
    <?php if ($active_subscription['package_id'] == 1): ?>
        <style>#dinningBtn { display: none !important; }</style>
    <?php elseif ($active_subscription['package_id'] == 2): ?>
        <style>#deliveryBtn { display: none !important; }</style>
    <?php endif; ?>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const deliveryBtn = document.getElementById('deliveryBtn');
    const dinningBtn = document.getElementById('dinningBtn');

    const selectedOrderType = localStorage.getItem('selectedOrderType');
    if (selectedOrderType === 'delivery' && deliveryBtn) {
        deliveryBtn.classList.add('active');
    } else if (selectedOrderType === 'dining' && dinningBtn) {
        dinningBtn.classList.add('active');
    }
});

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
                        Subtotal: ₹<span id="cartSubtotal">0.00</span>
                    </div>

                    <!-- Discount Section -->
                    <div class="cart-discount" id="discountSection" style="display: none;">
                        Discount: -₹<span id="discountAmount">0.00</span> (
                        <span id="discountType"></span>)
                    </div>

                    <?php if ($gst_percent > 0): ?>
                        <div class="cart-gst-charges">
                            GST (
                            <?= $gst_percent ?>%): ₹<span id="gstCharges">0.00</span>
                        </div>
                    <?php endif; ?>

                    <?php if ($delivery_active && isset($delivery_charges)): ?>
                        <div class="cart-delivery-charges">
                            Delivery: <span id="deliveryChargeText">₹0.00</span>
                        </div>
                    <?php endif; ?>

                    <div class="cart-total">
                        Total: ₹<span id="cartTotal">0.00</span>
                    </div>
                </div>
                <!-- View Cart Button -->
                <button class="btn btn-outline-secondary mb-3 w-100" id="viewCartBtn" style="display: none;">
                    <i class="bi bi-cart"></i> View Cart
                </button>
            </div>

            <!-- Order Type Buttons -->
            <?php if ($delivery_active || $dining_active): ?>
                <div class="order-type-buttons mb-3">
                    <?php if ($delivery_active): ?>
                        <button class="btn btn-outline-primary w-50" id="deliveryBtn">
                            <i class="bi bi-truck"></i> Delivery
                        </button>
                    <?php endif; ?>
                            
                    <?php if ($dining_active): ?>
                        <button class="btn btn-outline-primary w-50" id="dinningBtn">
                            <i class="bi bi-cup-hot"></i> Dining
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

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
                            <input type="text" class="form-control" id="dinningName" placeholder="Your name" required>
                        </div>
                        <div class="mb-1 col-half">
                            <label for="dinningPhone" class="form-label">Phone*</label>
                            <input type="tel" class="form-control" id="dinningPhone" placeholder="Your phone number" pattern="[0-9]{10}" title="Please enter exactly 10 digits" required oninput="validatePhoneNumber(this)">
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
                        <!-- Add this inside the delivery-details div in products.php -->
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
                            <input type="text" class="form-control" id="customerName" placeholder="Your name" required>
                        </div>
                        <div class="mb-1 col-half">
                            <label for="customerPhone" class="form-label">Phone*</label>
                            <input type="tel" class="form-control" id="customerPhone" placeholder="Your phone number" pattern="[0-9]{10}" title="Please enter exactly 10 digits" required oninput="validatePhoneNumber(this)">
                        </div>
                        <div class="mb-1 col-full">
                            <label for="customerAddress" class="form-label">Address*</label>
                            <textarea class="form-control" id="customerAddress" rows="2" placeholder="Delivery address" required></textarea>
                        </div>
                        <div class="mb-1 col-full">
                            <label for="customerNotes" class="form-label">Order Notes</label>
                            <textarea class="form-control" id="customerNotes" rows="2" placeholder="Any special instructions"></textarea>
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
    <?php if (!empty($products)): ?>
        <?php foreach ($products as $product): ?>
            <div class="col-sm-12 product-item" 
 data-name="<?= htmlspecialchars(strtolower($product['product_name'])) ?>" 
 data-desc="<?= htmlspecialchars(strtolower($product['description'])) ?>"
 data-tag="<?= isset($product['tag']) ? htmlspecialchars(strtolower($product['tag'])) : '' ?>">
                <div class="card product-card">
                    <div class="card-body">
                        <h5 class="card-title"><?= htmlspecialchars($product['product_name']) ?></h5>
                        <p class="card-text">
                            <?= htmlspecialchars($product['description']) ?>
                        </p>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-primary fw-bold">₹<?= number_format($product['price']) ?></span>
                            <span class="badge bg-<?= ($product['quantity'] > 0) ? 'success' : 'danger' ?>" style="display: none;">
                                <?= ($product['quantity'] > 0) ? 'In Stock' : 'Out of Stock' ?>
                            </span>
                        </div>
                        <?php if ($product['quantity'] > 0): ?>
                            <small class="text-muted">Quantity: <?= $product['quantity'] ?></small>
                        <?php endif; ?>
                        <?php if ($product['quantity'] > 0 && ($delivery_active || $dining_active) && $is_store_open): ?>
                            <div class="mt-3 cart_btn_group <?= empty($product['image_path']) ? 'top' : '' ?>">
                                <button class="btn btn-primary w-100 add-to-cart" data-id="<?= htmlspecialchars($product['product_name']) ?>" data-name="<?= htmlspecialchars($product['product_name']) ?>" data-price="<?= $product['price'] ?>" data-max="<?= $product['quantity'] ?>" data-image="<?= htmlspecialchars($product['image_path']) ?>">
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
                            <img src="<?= htmlspecialchars($product['image_path']) ?>" class="card-img-top product-img" alt="<?= htmlspecialchars($product['product_name']) ?>" onerror="this.style.display='none'">
                        </div>
                    <?php endif; ?>

                    <script>
                        document.querySelectorAll('.product-img').forEach(img => {
                          img.addEventListener('error', function() {
                            // Find the closest parent `.product-card`, then navigate to `.card-body .cart_btn_group`
                            const productCard = this.closest('.product-card');
                            if (productCard) {
                              const cartBtnGroup = productCard.querySelector('.card-body .cart_btn_group');
                              if (cartBtnGroup) {
                                cartBtnGroup.classList.add('top'); // Add the "top" class
                              }
                            }
                          });
                        });
                    </script>
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
        <!-- Add tags filter above search -->
        <div class="tags-filter-container">
            <div class="tags-scroll">
                <button class="tag-btn active" data-tag="all">All</button>
                <?php foreach ($tags as $tag): ?>
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
            <button class="btn btn-primary cart-button" onclick="toggleCart()">
                <span class="cart-count">0 item added</span>
                <span class="small discount-message" style="display: none;"></span>
            </button>
        </div>
    <?php endif; ?>
</div>

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






// Whatsapp Msg Start
// Add this function to your existing code
function calculateSubtotal() {
    return cart.filter(item => item.id).reduce((sum, item) => sum + (item.price * item.quantity), 0);
}

function placeOrderOnWhatsApp() {
    if (cart.length === 0) {
        showToast('Your cart is empty', 'error');
        return;
    }

    <?php if ($delivery_active || $dining_active): ?>
    const isDelivery = <?= $delivery_active ? 'document.getElementById("deliveryBtn").classList.contains("active")' : 'false' ?>;
    const deliveryCharge = Number(<?= json_encode($delivery_charges['delivery_charge'] ?? 0) ?>);
    const freeDeliveryMin = Number(<?= json_encode($delivery_charges['free_delivery_minimum'] ?? 0) ?>);
    const gstPercent = Number(<?= json_encode($gst_percent ?? 0) ?>);
    
    // Validate required fields
    const phoneInput = isDelivery ? document.getElementById('customerPhone') : document.getElementById('dinningPhone');
    if (!phoneInput.value || phoneInput.value.length !== 10) {
        showToast('Please enter a valid 10-digit phone number', 'error');
        phoneInput.focus();
        return;
    }

    let customerName, orderDetails;
    
    if (isDelivery) {
        customerName = document.getElementById('customerName').value;
        const customerPhone = phoneInput.value;
        const customerAddress = document.getElementById('customerAddress').value;
        const customerNotes = document.getElementById('customerNotes').value;
        
        if (!customerName || !customerAddress) {
            showToast('Please provide your name and address', 'error');
            return;
        }
        
        orderDetails = `*Delivery Order*\nName: ${customerName}\nPhone: ${customerPhone}\nAddress: ${customerAddress}`;
        if (customerNotes) orderDetails += `\nNotes: ${customerNotes}`;
    } else {
        customerName = document.getElementById('dinningName').value;
        const customerPhone = phoneInput.value;
        const tableNumber = document.getElementById('tableNumber').value;
        const dinningNotes = document.getElementById('dinningNotes').value;
        
        if (!customerName || !tableNumber) {
            showToast('Please provide your name and table number', 'error');
            return;
        }
        
        orderDetails = `*Dining Order*\nName: ${customerName}\nPhone: ${customerPhone}\nTable No.: ${tableNumber}`;
        if (dinningNotes) orderDetails += `\nNotes: ${dinningNotes}`;
    }
    <?php else: ?>
    let customerName = 'Guest';
    let orderDetails = `*Quick Order*`;
    <?php endif; ?>

    // Get WhatsApp number safely
    const whatsappLink = <?= json_encode($social_link['whatsapp'] ?? '') ?>;
    let phoneNumber = whatsappLink.match(/wa\.me\/(\d+)/)?.[1] || <?= json_encode($user['phone'] ?? '') ?>;

    if (!phoneNumber) {
        showToast('WhatsApp number not available for this business', 'error');
        return;
    }

    // Format order date
    const orderDate = new Date().toLocaleString('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });

    // Calculate order totals correctly
    let subtotal = 0;
    cart.forEach(item => {
        if (item.id) { // Only count actual products, not coupon objects
            subtotal += item.price * item.quantity;
        }
    });

    // Get discount information from the UI
    const discountSection = document.getElementById('discountSection');
    let discountAmount = 0;
    let discountText = '';
    
    if (discountSection && discountSection.style.display !== 'none') {
        discountAmount = parseFloat(document.getElementById('discountAmount').textContent) || 0;
        discountText = document.getElementById('discountType').textContent || '';
    }

    // Calculate amount after discount
    let amountAfterDiscount = subtotal - discountAmount;
    if (amountAfterDiscount < 0) amountAfterDiscount = 0;

    // Calculate GST on amount after discount
    let gstAmount = 0;
    if (gstPercent > 0) {
        gstAmount = (amountAfterDiscount * gstPercent) / 100;
    }

    // Calculate delivery charge
    let actualDeliveryCharge = 0;
    let deliveryText = '';
    
    if (isDelivery) {
        // Check if free delivery minimum is set and applicable
        if (freeDeliveryMin > 0 && amountAfterDiscount >= freeDeliveryMin) {
            actualDeliveryCharge = 0;
            deliveryText = `Delivery:       FREE\n(Order above ₹${freeDeliveryMin.toFixed(2)})\n`;
        } else if (freeDeliveryMin > 0) {
            // Not eligible for free delivery but free delivery minimum exists
            actualDeliveryCharge = deliveryCharge;
            const neededForFree = freeDeliveryMin - amountAfterDiscount;
            deliveryText = `Delivery:       ₹${deliveryCharge.toFixed(2)}\n(Add ₹${neededForFree.toFixed(2)} more for FREE delivery)\n`;
        } else {
            // No free delivery minimum set, just charge normal delivery
            actualDeliveryCharge = deliveryCharge;
            deliveryText = `Delivery:       ₹${deliveryCharge.toFixed(2)}\n`;
        }
    }

    // Calculate total
    let total = amountAfterDiscount + gstAmount + actualDeliveryCharge;

    // Business details
    const businessName = <?= json_encode(htmlspecialchars($business_info['business_name'] ?? '')) ?>;
    const businessAddress = <?= json_encode(htmlspecialchars($business_info['business_address'] ?? '')) ?>;
    const businessPhone = <?= json_encode($user['phone'] ?? '') ?>;

    // Build WhatsApp message
    let message = `*${businessName.toUpperCase()}*\n` +
                  `${businessAddress}\n` +
                  `Phone: ${businessPhone}\n\n` +
                  `Date: ${orderDate}\n` +
                  `Order Type: ${isDelivery ? 'DELIVERY' : 'DINING'}\n` +
                  `--------------------------------------------------\n` +
                  `*ITEMS ORDERED*\n` +
                  `--------------------------------------------------\n`;

    // Add cart items
    cart.forEach(item => {
        if (item.id) { // Only show actual products, not coupon objects
            const itemTotal = (item.price * item.quantity).toFixed(2);
            message += `${item.name} x ${item.quantity}\n` +
                      `₹${item.price.toFixed(2)} x ${item.quantity} = ₹${itemTotal}\n\n`;
        }
    });

    // Add pricing summary
    message += `--------------------------------------------------\n` +
               `Subtotal:        ₹${subtotal.toFixed(2)}\n`;
    
    if (discountAmount > 0) {
        message += `Discount:       -₹${discountAmount.toFixed(2)}\n`;
        if (discountText) {
            message += `(${discountText})\n`;
        }
    }
    
    if (gstPercent > 0) {
        message += `GST (${gstPercent}%):    ₹${gstAmount.toFixed(2)}\n`;
    }
    
    // Add delivery information
    if (isDelivery) {
        message += deliveryText;
    }

    message += `--------------------------------------------------\n` +
               `*TOTAL:          ₹${total.toFixed(2)}*\n\n` +
               `*CUSTOMER DETAILS*\n` +
               `--------------------------------------------------\n` +
               `${orderDetails}\n\n` +
               `Thank you for your order. We'll process it shortly.\n\n`;

    // Add profile URL
    message += `Next time, place your order easily through this link 👉`;

    // Add website if available
    <?php if (!empty($business_info['website'])): ?>
    message += `${<?= json_encode($business_info['website']) ?>}\n` +
               `OR\n`;
    <?php endif; ?>

    message += `${window.location.origin}/<?= $profile_url ?>`;




    // Safari-compatible WhatsApp opening
    const whatsappUrl = `https://wa.me/${phoneNumber}?text=${encodeURIComponent(message)}`;
    
    // Create and click a hidden link (most reliable for Safari)
    const link = document.createElement('a');
    link.href = whatsappUrl;
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
    link.style.display = 'none';
    document.body.appendChild(link);
    
    try {
        link.click();
    } catch (e) {
        // Fallback if click fails
        window.location.href = whatsappUrl;
    }
    
    // Clean up
    setTimeout(() => {
        document.body.removeChild(link);
    }, 1000);

    // Reset coupon fields
    if (cart.coupon) {
        delete cart.coupon;
        document.getElementById('couponCode').value = '';
        document.getElementById('couponMessage').textContent = '';
        document.getElementById('couponMessage').className = 'text-success';
    }

    // Reset cart
    cart = [];
    saveCart();
    updateCartUI();
    closeCart();
    
    // Success popup
    showOrderSuccessPopup();
}
// Whatsapp Msg End




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
    
    if (!customerPhone || customerPhone.length !== 10) {
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

// Add to your fadeIn/fadeOut functions
const originalFadeIn = fadeIn;
fadeIn = function(element, callback) {
    originalFadeIn.apply(this, arguments);
    checkCartVisibility();
    if (callback) callback();
};

const originalFadeOut = fadeOut;
fadeOut = function(element, callback) {
    originalFadeOut.apply(this, arguments);
    checkCartVisibility();
    if (callback) callback();
};

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

function formatNumber(num) {
    // Convert to number if it's a string
    num = typeof num === 'string' ? parseFloat(num) : num;
    // Handle NaN cases
    if (isNaN(num)) num = 0;
    return num % 1 === 0 ? num.toString() : num.toFixed(2).replace(/\.?0+$/, '');
}

function validatePhoneNumber(input) {
    // Remove any non-digit characters
    input.value = input.value.replace(/\D/g, '');
    
    // Trim to 10 digits if longer
    if (input.value.length > 10) {
        input.value = input.value.substring(0, 10);
    }
    
    // Check validity and show error if needed
    if (input.value.length !== 10 && input.value.length > 0) {
        input.setCustomValidity('Phone number must be exactly 10 digits');
    } else {
        input.setCustomValidity('');
    }
}

// Add to cart button click handler
document.querySelectorAll('.add-to-cart').forEach(button => {
    button.addEventListener('click', function() {
        // Add this to adjust the sticky search container
        const stickySearchContainer = document.querySelector('.sticky-search-container');
        if (stickySearchContainer) {
            stickySearchContainer.style.bottom = '65px';
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
                // showToast(`${product.name} quantity increased to ${existingItem.quantity}`);
            } else {
                // showToast(`Maximum quantity reached for ${product.name}`, true);
                return;
            }
        } else {
            cart.push(product);
            // showToast(`${product.name} added to cart`);
            // Add pulse animation to cart button
            document.querySelector('.cart-button').classList.add('cart-item-added');
            setTimeout(() => {
                document.querySelector('.cart-button').classList.remove('cart-item-added');
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
    const removeCouponBtn = document.getElementById('removeCouponBtn');

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
            if (removeCouponBtn) removeCouponBtn.style.display = 'none';
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
    const isDelivery = <?= $delivery_active ? 'document.getElementById("deliveryBtn").classList.contains("active")' : 'false' ?>;
    const deliveryCharge = <?= isset($delivery_charges['delivery_charge']) ? $delivery_charges['delivery_charge'] : 0 ?>;
    const freeDeliveryMin = <?= isset($delivery_charges['free_delivery_minimum']) ? $delivery_charges['free_delivery_minimum'] : 0 ?>;
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
                    <div>₹${formatNumber(item.price)} x ${item.quantity}</div>
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
            discountType = 'Flat ₹' + formatNumber(cart.coupon.value) + ' OFF (' + couponCode + ')';
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
                discountType = 'Flat ₹' + formatNumber(applicableDiscount.discount_in_flat) + ' OFF';
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
                    discountMessageElement.innerHTML = `<i class="bi bi-tag"></i> Add ₹${formatNumber(needed)} more for discount`;
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
                nextDiscountText = `Add ₹${formatNumber(amountNeeded)} more for ${formatNumber(nextDiscount.discount_in_percent)}% discount`;
            } else if (nextDiscount.discount_in_flat) {
                nextDiscountText = `Add ₹${formatNumber(amountNeeded)} more for ₹${formatNumber(nextDiscount.discount_in_flat)} OFF`;
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
            document.getElementById('deliveryChargeText').textContent = 'FREE (Order above ₹' + formatNumber(freeDeliveryMin) + ')';
            if (cartDeliveryChargesRow) cartDeliveryChargesRow.classList.add('free');
        } else {
            // Apply normal delivery charge
            actualDeliveryCharge = parseFloat(deliveryCharge);
            if (freeDeliveryMin > 0) {
                // Show message about how much more to spend for free delivery
                const neededForFree = freeDeliveryMin - amountAfterDiscount;
                document.getElementById('deliveryChargeText').innerHTML =
                    `₹${formatNumber(deliveryCharge)} <span class="free-delivery-text"> (Add ₹${formatNumber(neededForFree)} more for FREE delivery)</span>`;
            } else {
                document.getElementById('deliveryChargeText').textContent = `₹${formatNumber(deliveryCharge)}`;
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
    document.querySelector('.cart-count').textContent = itemCount + (itemCount === 1 ? ' item added' : ' items added');

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

function placeOrder() {
    if (cart.length === 0) {
        alert('Your cart is empty');
        return;
    }

    const isDelivery = <?= $delivery_active ? 'document.getElementById("deliveryBtn").classList.contains("active")' : 'false' ?>;
    const deliveryCharge = <?= isset($delivery_charges['delivery_charge']) ? $delivery_charges['delivery_charge'] : 0 ?>;
    const freeDeliveryMin = <?= isset($delivery_charges['free_delivery_minimum']) ? $delivery_charges['free_delivery_minimum'] : 0 ?>;
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
    const phoneInput = isDelivery ? document.getElementById('customerPhone') : document.getElementById('dinningPhone');
    
    // Validate phone number first
    if (phoneInput.value.length !== 10) {
        alert('Please enter a valid 10-digit phone number');
        phoneInput.focus();
        return;
    }

    if (isDelivery) {
        customerName = document.getElementById('customerName').value;
        customerPhone = phoneInput.value;
        deliveryAddress = document.getElementById('customerAddress').value;
        orderNotes = document.getElementById('customerNotes').value;
        
        if (!customerName || !deliveryAddress) {
            alert('Please provide your name and address');
            return;
        }
    } else {
        customerName = document.getElementById('dinningName').value;
        customerPhone = phoneInput.value;
        tableNumber = document.getElementById('tableNumber').value;
        orderNotes = document.getElementById('dinningNotes').value;
        
        if (!customerName || !tableNumber) {
            alert('Please provide your name and table number');
            return;
        }
    }
    
    // Prepare order data - MAKE SURE DISCOUNT DATA IS INCLUDED
    const orderData = {
        user_id: <?= $user_id ?>,
        order_type: isDelivery ? 'delivery' : 'dining',
        customer_name: customerName,
        customer_phone: customerPhone,
        delivery_address: isDelivery ? deliveryAddress : null,
        table_number: !isDelivery ? tableNumber : null,
        order_notes: orderNotes || null,
        items: cart.filter(item => item.id).map(item => ({
            name: item.name,
            price: item.price,
            quantity: item.quantity
        })),
        discount_amount: discountAmount, // ADD THIS
        discount_type: discountType,     // ADD THIS
        gst_percent: gstPercent,
        delivery_charge: deliveryCharge,
        free_delivery_min: freeDeliveryMin,
        coupon_data: cart.coupon || null
    };
    
    // Debug: Log what's being sent to the server
    console.log('Sending order data:', orderData);
    
    // Show loading state
    const placeOrderBtn = document.getElementById('placeOrderBtn');
    const originalBtnText = placeOrderBtn.innerHTML;
    placeOrderBtn.disabled = false;
    
    // Send order data to server
    fetch('place_order.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(orderData)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Reset coupon fields
            if (cart.coupon) {
                delete cart.coupon;
                document.getElementById('couponCode').value = '';
                document.getElementById('couponMessage').textContent = '';
                document.getElementById('couponMessage').className = 'text-success';
            }

            // Reset sticky search container position
            const stickySearchContainer = document.querySelector('.sticky-search-container');
            if (stickySearchContainer) {
                stickySearchContainer.style.bottom = ''; // Reset to original value
            }
            
            // Show success popup
            showOrderSuccessPopup();

            closeCart();
            
            if (data.trigger_whatsapp) {
                // Add 3-second delay before triggering WhatsApp
                setTimeout(() => {
                    placeOrderOnWhatsApp();
                }, 3000);
            } else {
                placeOrderBtn.innerHTML = originalBtnText;
                placeOrderBtn.disabled = false;
                
                cart = [];
                saveCart();
                updateCartUI();
                closeCart();
            }
        } else {
            throw new Error(data.message || 'Failed to place order');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert(error.message || 'Failed to place order. Please try again.');
        placeOrderBtn.innerHTML = originalBtnText;
        placeOrderBtn.disabled = false;
    });
}

// Toast notification function (add this to your code if you don't have it already)
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
document.querySelector('.cart-footer button').addEventListener('click', placeOrder);

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
document.getElementById('dinningBtn').addEventListener('click', function() {
    clearCoupon();
});

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

<!-- Order Success Popup (updated with confetti) -->
<div class="order-success-popup" id="orderSuccessPopup">
    <div class="order-success-content">
        <div class="order-success-icon">
            <img src="images/success_icon.gif">
        </div>
        <h3 class="order-success-title">
            Order Received<br>
            Your food is being prepared!
        </h3>
        <p class="order-success-message">
            Thank you for your order.<br>
        </p>
        <h4 class="mb-3">Also share your order with us<br>
            on WhatsApp — just hit 'Send'.</h4>
        <button class="order-success-btn" onclick="closeOrderSuccessPopup()">OK</button>
    </div>
</div>
