
<!-- Toast Notification -->
<div class="cart_toast_notification position-fixed bottom-0 start-0" style="z-index: 999999;">
  <div id="cartToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="toast-header bg-primary text-white">
      <strong class="me-auto">Cart Update</strong>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
    <div class="toast-body">
      <span id="toastMessage">Item added to cart!</span>
    </div>
  </div>
</div>

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
          <input type="tel" class="form-control" id="dinningPhone" placeholder="Your phone number" required>
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
          <input type="tel" class="form-control" id="customerPhone" placeholder="Your phone number" required>
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
       <button class="btn btn-success w-100" onclick="placeOrderOnWhatsApp()">
       <i class="bi bi-whatsapp"></i> Place Order
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
             <?php if ($product['quantity'] > 0 && ($delivery_active || $dining_active)): ?>
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
             <?php endif; ?>
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
 
 <?php if ($delivery_active || $dining_active): ?>
 <div class="cart-button-container">
    <button class="btn btn-primary cart-button" onclick="toggleCart()">
    <i class="bi bi-cart"></i> 
    <span class="cart-count">0</span>
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
                showToast(`${product.name} quantity increased to ${existingItem.quantity}`);
            } else {
                showToast(`Maximum quantity reached for ${product.name}`, true);
                return;
            }
        } else {
            cart.push(product);
            showToast(`${product.name} added to cart`);
            // Add pulse animation to cart button
            document.querySelector('.cart-button').classList.add('cart-item-added');
            setTimeout(() => {
                document.querySelector('.cart-button').classList.remove('cart-item-added');
            }, 500);
        }

        saveCart();
        updateCartUI();
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

// Update cart UI
function updateCartUI() {
    const cartItemsContainer = document.getElementById('cartItems');
    cartItemsContainer.innerHTML = '';

    let subtotal = 0;
    const isDelivery = <?= $delivery_active ? 'document.getElementById("deliveryBtn").classList.contains("active")' : 'false' ?>;
    const deliveryCharge = <?= isset($delivery_charges['delivery_charge']) ? $delivery_charges['delivery_charge'] : 0 ?>;
    const freeDeliveryMin = <?= isset($delivery_charges['free_delivery_minimum']) ? $delivery_charges['free_delivery_minimum'] : 0 ?>;
    const gstPercent = <?= $gst_percent ?? 0 ?>;

    // Get references to delivery and dining buttons/sections
    const deliveryBtn = document.getElementById('deliveryBtn');
    const dinningBtn = document.getElementById('dinningBtn');
    const deliveryDetails = document.querySelector('.delivery-details');
    const dinningDetails = document.querySelector('.dinning-details');
    const cartDeliveryChargesRow = document.querySelector('.cart-delivery-charges');

    // Calculate subtotal and populate cart items
    cart.forEach((item, index) => {
        subtotal += item.price * item.quantity;
        const productImage = item.image_path ? item.image_path : 'images/no-image.jpg';

        const itemElement = document.createElement('div');
        itemElement.className = 'cart-item';
        itemElement.innerHTML = `
            <div class="cart-item-info d-flex">
                <img src="${productImage}" alt="${item.name}" class="cart-item-img" />
                <div class="ms-1">
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

    // Handle visibility of Delivery/Dining options based on cart contents
    if (cart.length === 0) {
        // If cart is empty, hide delivery details and potentially the delivery button
        if (deliveryDetails) deliveryDetails.style.display = 'none';
        if (cartDeliveryChargesRow) cartDeliveryChargesRow.style.display = 'none';

        // If both options exist and cart is empty, default to Dining if active
        // Or if only delivery is active, hide the entire order type buttons if cart is empty.
        <?php if ($delivery_active && $dining_active): ?>
            if (deliveryBtn) deliveryBtn.style.display = 'none';
            if (dinningBtn) dinningBtn.classList.add('active'); // Force Dining active
            if (dinningDetails) dinningDetails.style.display = 'block'; // Show Dining details
        <?php elseif ($delivery_active): ?>
            // If only delivery is active, hide the delivery section when cart is empty
            if (document.querySelector('.order-type-buttons')) {
                document.querySelector('.order-type-buttons').style.display = 'none';
            }
            if (deliveryDetails) deliveryDetails.style.display = 'none';
        <?php endif; ?>
    } else {
        // If cart has items, manage button and section visibility as usual
        <?php if ($delivery_active && $dining_active): ?>
            if (deliveryBtn) deliveryBtn.style.display = 'inline-block'; // Show delivery button
            // Re-apply original logic for active button display
            if (deliveryBtn && deliveryBtn.classList.contains('active')) {
                if (deliveryDetails) deliveryDetails.style.display = 'block';
                if (dinningDetails) dinningDetails.style.display = 'none';
            } else if (dinningBtn && dinningBtn.classList.contains('active')) {
                if (deliveryDetails) deliveryDetails.style.display = 'none';
                if (dinningDetails) dinningDetails.style.display = 'block';
            }
        <?php elseif ($delivery_active): ?>
            // If only delivery is active, ensure the order type buttons are visible
            if (document.querySelector('.order-type-buttons')) {
                document.querySelector('.order-type-buttons').style.display = 'block';
            }
            if (deliveryDetails) deliveryDetails.style.display = 'block';
        <?php endif; ?>
    }

    // Calculate discount
    discountAmount = 0;
    discountType = '';
    const discountSection = document.getElementById('discountSection');

    <?php if (!empty($discounts)): ?>
        // Check each discount condition in order
        const discounts = <?= json_encode($discounts) ?>;
        let applicableDiscount = null;

        // Iterate through discounts to find the highest applicable one
        for (const discount of discounts) {
            if (subtotal >= discount.min_cart_value) {
                applicableDiscount = discount;
            }
        }

        if (applicableDiscount) {
            if (applicableDiscount.discount_in_percent !== null && applicableDiscount.discount_in_percent > 0) {
                discountAmount = (subtotal * applicableDiscount.discount_in_percent) / 100;
                discountType = applicableDiscount.discount_in_percent + '% discount';
            } else if (applicableDiscount.discount_in_flat !== null && applicableDiscount.discount_in_flat > 0) {
                discountAmount = parseFloat(applicableDiscount.discount_in_flat);
                discountType = 'Flat OFF ₹' + discountAmount.toFixed(2);
            }

            // Ensure discountAmount doesn't exceed subtotal
            if (discountAmount > subtotal) {
                discountAmount = subtotal;
            }

            // Show discount section only if a discount is actually applied
            if (discountAmount > 0) {
                discountSection.style.display = 'block';
                document.getElementById('discountAmount').textContent = discountAmount.toFixed(2);
                document.getElementById('discountType').textContent = discountType;
            } else {
                discountSection.style.display = 'none';
            }
        } else {
            // Hide discount section if no applicable discount
            discountSection.style.display = 'none';
        }
    <?php else: ?>
        // Hide discount section if no discounts exist at all
        discountSection.style.display = 'none';
    <?php endif; ?>

    // Update subtotal and total
    document.getElementById('cartSubtotal').textContent = subtotal.toFixed(2);

    // Calculate GST on amount after discount
    let amountAfterDiscount = subtotal - discountAmount;
    if (amountAfterDiscount < 0) {
        amountAfterDiscount = 0;
    }

    let total = amountAfterDiscount;
    if (gstPercent > 0) {
        const gstAmount = (amountAfterDiscount * gstPercent) / 100;
        document.getElementById('gstCharges').textContent = gstAmount.toFixed(2);
        total += gstAmount;
    }

    // Calculate delivery charges ONLY if cart is NOT empty and delivery is active AND selected
    let actualDeliveryCharge = 0;
    if (cart.length > 0 && isDelivery && deliveryCharge !== undefined) {
        if (freeDeliveryMin > 0 && amountAfterDiscount >= freeDeliveryMin) {
            // Free delivery because subtotal meets minimum
            actualDeliveryCharge = 0;
            document.getElementById('deliveryChargeText').textContent = 'FREE (Order above ₹' + freeDeliveryMin + ')';
            if (cartDeliveryChargesRow) cartDeliveryChargesRow.classList.add('free');
        } else {
            // Apply normal delivery charge
            actualDeliveryCharge = parseFloat(deliveryCharge);
            if (freeDeliveryMin > 0) {
                // Show message about how much more to spend for free delivery
                const neededForFree = freeDeliveryMin - amountAfterDiscount;
                document.getElementById('deliveryChargeText').innerHTML =
                    `₹${deliveryCharge.toFixed(2)} <span class="free-delivery-text"> (Add ₹${neededForFree.toFixed(2)} more for FREE delivery)</span>`;
            } else {
                document.getElementById('deliveryChargeText').textContent = `₹${deliveryCharge.toFixed(2)}`;
            }
            if (cartDeliveryChargesRow) cartDeliveryChargesRow.classList.remove('free');
        }
        
        if (cartDeliveryChargesRow) cartDeliveryChargesRow.style.display = 'block';
        total += actualDeliveryCharge;
    } else {
        if (cartDeliveryChargesRow) cartDeliveryChargesRow.style.display = 'none';
    }

    document.getElementById('cartTotal').textContent = total.toFixed(2);
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

// Place order on WhatsApp
function placeOrderOnWhatsApp() {
    if (cart.length === 0) {
        alert('Your cart is empty');
        return;
    }

    <?php if ($delivery_active || $dining_active): ?>
    const isDelivery = <?= $delivery_active ? 'document.getElementById("deliveryBtn").classList.contains("active")' : 'false' ?>;
    const deliveryCharge = <?= isset($delivery_charges['delivery_charge']) ? $delivery_charges['delivery_charge'] : 0 ?>;
    const freeDeliveryMin = <?= isset($delivery_charges['free_delivery_minimum']) ? $delivery_charges['free_delivery_minimum'] : 0 ?>;
    const gstPercent = <?= $gst_percent ?? 0 ?>;
    
    let customerName, customerPhone, orderDetails;
    
    // Determine which details to capture based on active order type (Delivery or Dining)
    if (isDelivery && cart.length > 0) { // Only check delivery if items in cart
        customerName = document.getElementById('customerName').value;
        customerPhone = document.getElementById('customerPhone').value;
        const customerAddress = document.getElementById('customerAddress').value;
        const customerNotes = document.getElementById('customerNotes').value;
        
        if (!customerName || !customerPhone || !customerAddress) {
            alert('Please provide your name, phone number and address');
            return;
        }
        
        orderDetails = `*Delivery Order*\nName: ${customerName}\nPhone: ${customerPhone}\nAddress: ${customerAddress}`;
        if (customerNotes) orderDetails += `\nNotes: ${customerNotes}`;
    } else { // Default to Dining if not Delivery, or if Delivery is chosen but cart is empty
        customerName = document.getElementById('dinningName').value;
        customerPhone = document.getElementById('dinningPhone').value;
        const tableNumber = document.getElementById('tableNumber').value;
        
        if (!customerName || !customerPhone || !tableNumber) {
            alert('Please provide your name, phone number and table number');
            return;
        }
        
        orderDetails = `*Dinning Order*\nName: ${customerName}\nPhone: ${customerPhone}\nTable No.: ${tableNumber}`;
    }
    <?php else: ?>
    let customerName = 'Guest';
    let customerPhone = '';
    let orderDetails = `*Quick Order*`;
    <?php endif; ?>

    const whatsappLink = "<?= $social_link['whatsapp'] ?? '' ?>";
    let phoneNumber = whatsappLink.match(/wa\.me\/(\d+)/)?.[1] || "<?= $user['phone'] ?? '' ?>";

    if (!phoneNumber) {
        alert('WhatsApp number not available');
        return;
    }

    // Calculate subtotal
    let subtotal = 0;
    cart.forEach(item => {
        subtotal += item.price * item.quantity;
    });

    let message = `*##### NEW ORDER #####*\n\n`;
    message += `--------------------------\n*Order Details:*\n--------------------------\n`;

    cart.forEach(item => {
        message += `*${item.name}*\nPrice: ₹${item.price.toFixed(2)}\nQuantity: ${item.quantity}\nSubtotal: ₹${(item.price * item.quantity).toFixed(2)}\n\n`;
    });

    message += `--------------------------\n`;
    message += `*Subtotal: ₹${subtotal.toFixed(2)}*\n`;
    
    // Add discount line if applicable
    if (discountAmount > 0) {
        message += `*Discount (${discountType}): -₹${discountAmount.toFixed(2)}*\n`;
    }
    
    if (gstPercent > 0) {
        message += `*GST (${gstPercent}%): ₹${((subtotal - discountAmount) * gstPercent / 100).toFixed(2)}*\n`;
    }
    
    if (isDelivery && cart.length > 0) { // Only show delivery charge in message if delivery is active and cart has items
        if (freeDeliveryMin > 0 && (subtotal - discountAmount) >= freeDeliveryMin) {
            message += `*Delivery Charges: FREE (Order above ₹${freeDeliveryMin})*\n`;
        } else {
            message += `*Delivery Charges: ${deliveryCharge == 0 ? 'FREE' : '₹' + deliveryCharge.toFixed(2)}*\n`;
            if (freeDeliveryMin > 0) {
                const neededForFree = freeDeliveryMin - (subtotal - discountAmount);
                message += `(Add ₹${neededForFree.toFixed(2)} more for FREE delivery)\n`;
            }
        }
    }

    let total = (subtotal - discountAmount) + (gstPercent > 0 ? ((subtotal - discountAmount) * gstPercent / 100) : 0);
    if (isDelivery && cart.length > 0) { // Only add delivery charge to total if delivery is active and cart has items
        total += parseFloat(deliveryCharge);
    }
    
    message += `*Total Amount: ₹${total.toFixed(2)}*\n--------------------------\n\n`;
    message += `*Customer Details:*\n${orderDetails}\n\n`;
    message += `*Please confirm this order.*`;

    window.open(`https://wa.me/${phoneNumber}?text=${encodeURIComponent(message)}`, '_blank');

    // Optional: Clear cart after order
    cart = [];
    saveCart();
    updateCartUI();
    closeCart();
}
</script>