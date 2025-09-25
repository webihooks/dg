
<!-- Toast Notification -->
<!-- <div class="cart_toast_notification position-fixed bottom-0 start-0" style="z-index: 999999;">
  <div id="cartToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="toast-header bg-primary text-white" style="display:none;">
      <strong class="me-auto">Cart Update</strong>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
    <div class="toast-body">
      <span id="toastMessage">Item added to cart!</span>
    </div>
  </div>
</div> -->

<!-- products.php -->
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
 
 <?php if ($delivery_active || $dining_active): ?>
 <!-- Shopping Cart Sidebar -->
 <div class="cart-sidebar">
    <div class="cart-header">
       <h5>Your Cart</h5>
       <button class="btn-close" onclick="closeCart()"></button>
    </div>
    <div class="cart-items" id="cartItems"></div>

    <div class="cart-total-details">
        <div class="cart-subtotal">
            Subtotal: ₹<span id="cartSubtotal">0.00</span>
        </div>
        
        <!-- Discount Section -->
        <div class="cart-discount" id="discountSection" style="display: none;">
            Discount: -₹<span id="discountAmount">0.00</span>
            (<span id="discountType"></span>)
        </div>
        
        <?php if ($gst_percent > 0): ?>
        <div class="cart-gst-charges">
            GST (<?= $gst_percent ?>%): ₹<span id="gstCharges">0.00</span>
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

    <!-- Order Type Buttons -->
    <?php if ($delivery_active || $dining_active): ?>
    <div class="order-type-buttons mb-3">
        <?php if ($delivery_active): ?>
        <button class="btn btn-outline-primary w-50 active" id="deliveryBtn">
            <i class="bi bi-truck"></i> Delivery
        </button>
        <?php endif; ?>
        <?php if ($dining_active): ?>
        <button class="btn btn-outline-primary w-50 <?= $delivery_active ? '' : 'active' ?>" id="dinningBtn">
            <i class="bi bi-cup-hot"></i> Dining
        </button>
        <?php endif; ?>
    </div>
    <?php endif; ?>





    <!-- Dinning Details -->
    <?php if ($dining_active): ?>
    <div class="customer-details dinning-details" style="display: <?= $delivery_active ? 'none' : 'block' ?>;">
       <h6>Dinning Information</h6>
       <div class="mb-1 col-full">
          <label for="tableNumber" class="form-label">Table No.*</label>
          <select class="form-control" id="tableNumber" required>
            <option value="">Select Table</option>
            <?php for ($i = 1; $i <= $table_count; $i++): ?>
                <option value="<?= $i ?>">Table <?= $i ?></option>
            <?php endfor; ?>
          </select>
       </div>
       <div class="mb-1 col-half">
          <label for="dinningName" class="form-label">Name*</label>
          <input type="text" class="form-control" id="dinningName" placeholder="Your name" required>
       </div>
       <div class="mb-1 col-half">
            <label for="dinningPhone" class="form-label">Phone*</label>
            <input type="tel" class="form-control" id="dinningPhone" placeholder="Your phone number" 
                   pattern="[0-9]{10}" title="Please enter exactly 10 digits" required
                   oninput="validatePhoneNumber(this)">
        </div>
       <!-- Add Order Notes for Dining -->
       <div class="mb-1 col-full">
          <label for="dinningNotes" class="form-label">Order Notes</label>
          <textarea class="form-control" id="dinningNotes" rows="2" placeholder="Any special instructions"></textarea>
       </div>
    </div>
    <?php endif; ?>





    <!-- Delivery Details -->
    <?php if ($delivery_active): ?>
    <div class="customer-details delivery-details" style="display:<?= $delivery_active ? 'block' : 'none' ?>;">
       <h6>Delivery Information</h6>
       <div class="mb-1 col-half">
          <label for="customerName" class="form-label">Name*</label>
          <input type="text" class="form-control" id="customerName" placeholder="Your name" required>
       </div>
       <div class="mb-1 col-half">
            <label for="customerPhone" class="form-label">Phone*</label>
            <input type="tel" class="form-control" id="customerPhone" placeholder="Your phone number" 
                   pattern="[0-9]{10}" title="Please enter exactly 10 digits" required
                   oninput="validatePhoneNumber(this)">
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
        <button class="btn btn-success w-100 mt-2" onclick="placeOrderOnWhatsApp()" style="display:none;">
            <i class="bi bi-whatsapp"></i> Place Order via WhatsApp
        </button>
    </div>


 </div>
 <?php endif; ?>

 <div class="row" id="productsContainer">
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
                <?php if ($product['quantity'] > 0 && ($delivery_active || $dining_active)): ?>
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
                <?php endif; ?>
            </div>

            <?php if (!empty($product['image_path'])): ?>
            <div class="img-group">
                <img src="<?= htmlspecialchars($product['image_path']) ?>" 
                class="card-img-top product-img" 
                alt="<?= htmlspecialchars($product['product_name']) ?>"
                onerror="this.style.display='none'">
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
// Detect store name from URL
const storeName = window.location.pathname.split('/')[1] || 'default';
const cartKey = `cart_${storeName}`;

let cart = [];
let discountAmount = 0;
let discountType = '';

// Initialize cart from localStorage
if (localStorage.getItem(cartKey)) {
    cart = JSON.parse(localStorage.getItem(cartKey));
    updateCartUI();
}


function formatNumber(num) {
    num = typeof num === 'string' ? parseFloat(num) : num;
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





// Function to show toast notification
function showToast(message, isError = false) {
    const toastElement = document.getElementById('cartToast');
    const toastMessage = document.getElementById('toastMessage');
    
    toastMessage.textContent = message;
    
    // Change style if it's an error message
    if (isError) {
        toastElement.querySelector('.toast-header').classList.remove('bg-primary');
        toastElement.querySelector('.toast-header').classList.add('bg-danger');
    } else {
        toastElement.querySelector('.toast-header').classList.remove('bg-danger');
        toastElement.querySelector('.toast-header').classList.add('bg-primary');
    }
    
    // Initialize and show the toast
    const toast = new bootstrap.Toast(toastElement);
    toast.show();
    
    // Auto-hide after 2 seconds
    setTimeout(() => {
        toast.hide();
    }, 2000);
}

// Save cart to localStorage
function saveCart() {
    localStorage.setItem(cartKey, JSON.stringify(cart));
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

    // Handle empty cart case
    if (cart.length === 0) {
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
        subtotal += item.price * item.quantity;
        const productImage = item.image_path ? item.image_path : 'images/no-image.jpg';

        const itemElement = document.createElement('div');
        itemElement.className = 'cart-item';
        itemElement.innerHTML = `
            <div class="cart-item-info d-flex">
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
    discountAmount = 0;
    discountType = '';
    
    <?php if (!empty($discounts)): ?>
        // Check each discount condition in order
        const discounts = <?= json_encode($discounts) ?>;
        let applicableDiscount = null;
        let nextDiscount = null;

        // Sort discounts by min_cart_value ascending
        discounts.sort((a, b) => a.min_cart_value - b.min_cart_value);

        // Find applicable discount and next discount
        for (let i = 0; i < discounts.length; i++) {
            const discount = discounts[i];
            
            // Check if this discount is applicable
            if (subtotal >= discount.min_cart_value) {
                applicableDiscount = discount;
            }
            
            // Find the next discount that's not yet applicable
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
    <?php else: ?>
        // Hide discount section if no discounts exist at all
        if (discountSection) discountSection.style.display = 'none';
        if (discountMessageElement) discountMessageElement.style.display = 'none';
        if (document.getElementById('nextDiscountInfo')) {
            document.getElementById('nextDiscountInfo').style.display = 'none';
        }
    <?php endif; ?>

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
    const itemCount = cart.reduce((sum, item) => sum + item.quantity, 0);
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




function placeOrder() {
    if (cart.length === 0) {
        alert('Your cart is empty');
        return;
    }

    const isDelivery = <?= $delivery_active ? 'document.getElementById("deliveryBtn").classList.contains("active")' : 'false' ?>;
    const deliveryCharge = <?= isset($delivery_charges['delivery_charge']) ? $delivery_charges['delivery_charge'] : 0 ?>;
    const freeDeliveryMin = <?= isset($delivery_charges['free_delivery_minimum']) ? $delivery_charges['free_delivery_minimum'] : 0 ?>;
    const gstPercent = <?= $gst_percent ?? 0 ?>;
    
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
    
    // Prepare order data
    const orderData = {
        user_id: <?= $user_id ?>,
        order_type: isDelivery ? 'delivery' : 'dining',
        customer_name: customerName,
        customer_phone: customerPhone,
        delivery_address: isDelivery ? deliveryAddress : null,
        table_number: !isDelivery ? tableNumber : null,
        order_notes: orderNotes || null,
        items: cart.map(item => ({
            name: item.name,
            price: item.price,
            quantity: item.quantity
        })),
        discount_amount: discountAmount,
        discount_type: discountType,
        gst_percent: gstPercent,
        delivery_charge: deliveryCharge,
        free_delivery_min: freeDeliveryMin
    };
    
    // Show loading state
    const placeOrderBtn = document.getElementById('placeOrderBtn');
    const originalBtnText = placeOrderBtn.innerHTML;
    placeOrderBtn.innerHTML = 'Place Order';
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
            // Only show toast if it's a dining order that will trigger WhatsApp
            if (!isDelivery && data.trigger_whatsapp) {
                showToast('Order placed successfully!', 'success');
            }

            closeCart();
            
            // If the response indicates to trigger WhatsApp
            if (data.trigger_whatsapp) {
                // Wait 1 second then trigger WhatsApp function
                setTimeout(placeOrderOnWhatsApp, 1000);
            } else {
                // For orders that don't trigger WhatsApp, reset the button immediately
                placeOrderBtn.innerHTML = originalBtnText;
                placeOrderBtn.disabled = false;
                
                // Clear cart if order was successful
                cart = [];
                saveCart();
                updateCartUI();
                closeCart();
                
                // Show success message
                showToast('Order placed successfully!', 'success');
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










// Place order on WhatsApp
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

    // Calculate order totals
    let subtotal = 0;
    cart.forEach(item => {
        subtotal += item.price * item.quantity;
    });

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
                  `--------------------------\n` +
                  `*ITEMS ORDERED*\n` +
                  `--------------------------\n`;

    // Add cart items
    cart.forEach(item => {
        const itemTotal = (item.price * item.quantity).toFixed(2);
        message += `${item.name} x ${item.quantity}\n` +
                  `₹${item.price.toFixed(2)} x ${item.quantity} = ₹${itemTotal}\n\n`;
    });

    // Add pricing summary
    message += `--------------------------\n` +
               `Subtotal:        ₹${subtotal.toFixed(2)}\n`;
    
    if (discountAmount > 0) {
        message += `Discount:       -₹${discountAmount.toFixed(2)}\n` +
                   `(Applied ${discountType})\n`;
    }
    
    if (gstPercent > 0) {
        const gstAmount = ((subtotal - discountAmount) * gstPercent / 100).toFixed(2);
        message += `GST (${gstPercent}%):    ₹${gstAmount}\n`;
    }
    
    if (isDelivery) {
        if (freeDeliveryMin > 0 && (subtotal - discountAmount) >= freeDeliveryMin) {
            message += `Delivery:       FREE\n` +
                       `(Order above ₹${freeDeliveryMin.toFixed(2)})\n`;
        } else {
            message += `Delivery:       ₹${Number(deliveryCharge).toFixed(2)}\n`;
            if (freeDeliveryMin > 0) {
                const neededForFree = freeDeliveryMin - (subtotal - discountAmount);
                message += `(Add ₹${neededForFree.toFixed(2)} more for FREE delivery)\n`;
            }
        }
    }

    // Calculate total
    let total = (subtotal - discountAmount) + (gstPercent > 0 ? ((subtotal - discountAmount) * gstPercent / 100) : 0);
    if (isDelivery && !isNaN(deliveryCharge)) total += Number(deliveryCharge);
    
    message += `--------------------------\n` +
               `*TOTAL:          ₹${total.toFixed(2)}*\n\n` +
               `*CUSTOMER DETAILS*\n` +
               `--------------------------\n` +
               `${orderDetails}\n\n` +
               `Please confirm this order.`;

    // Safari-compatible WhatsApp opening
    const whatsappUrl = `https://wa.me/${phoneNumber}?text=${encodeURIComponent(message)}`;
    
    // Solution 1: Create and click a hidden link (most reliable for Safari)
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

    // Reset cart
    cart = [];
    saveCart();
    updateCartUI();
    closeCart();
    
    // Delay toast to ensure WhatsApp opens first
    setTimeout(() => {
        showToast('Order sent via WhatsApp!', 'success');
    }, 500);
}

</script>
