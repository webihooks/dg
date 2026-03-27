// borzo/assets/js/borzo-checkout.js

document.addEventListener('DOMContentLoaded', function() {
    const calculateBtn = document.getElementById('calculate-delivery');
    const deliveryAddress = document.getElementById('delivery_address');
    const deliveryCost = document.getElementById('delivery-cost');
    const deliveryFee = document.getElementById('delivery-fee');
    const deliveryError = document.getElementById('delivery-error');
    
    if (calculateBtn) {
        calculateBtn.addEventListener('click', calculateDelivery);
    }
    
    function calculateDelivery() {
        if (!deliveryAddress.value) {
            showError('Please enter delivery address');
            return;
        }
        
        // Show loading
        calculateBtn.disabled = true;
        calculateBtn.textContent = 'Calculating...';
        
        // Get cart data
        const cartData = getCartData();
        
        fetch('/borzo/api/calculate-delivery.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                delivery_address: deliveryAddress.value,
                customer_phone: document.querySelector('[name="phone"]')?.value || '',
                total_weight: cartData.totalWeight,
                order_total: cartData.total,
                payment_method: getPaymentMethod()
            })
        })
        .then(response => response.json())
        .then(data => {
            calculateBtn.disabled = false;
            calculateBtn.textContent = 'Calculate Delivery Cost';
            
            if (data.success) {
                deliveryFee.textContent = data.delivery_fee;
                deliveryCost.style.display = 'block';
                deliveryError.style.display = 'none';
                
                // Store delivery fee for order submission
                window.deliveryFee = data.delivery_fee;
                window.deliveryCalculationValid = true;
            } else {
                showError(data.errors ? data.errors.join(', ') : 'Failed to calculate delivery');
            }
        })
        .catch(error => {
            calculateBtn.disabled = false;
            calculateBtn.textContent = 'Calculate Delivery Cost';
            showError('Network error: ' + error.message);
        });
    }
    
    function showError(message) {
        deliveryError.textContent = message;
        deliveryError.style.display = 'block';
        deliveryCost.style.display = 'none';
        window.deliveryCalculationValid = false;
    }
    
    function getCartData() {
        // Implement based on your cart structure
        // This is a placeholder
        return {
            total: parseFloat(document.getElementById('cart-total')?.value || 0),
            totalWeight: calculateCartWeight()
        };
    }
    
    function calculateCartWeight() {
        // Calculate total weight from cart items
        // Implement based on your product data
        return 1; // Default 1kg
    }
    
    function getPaymentMethod() {
        const codRadio = document.querySelector('input[name="payment_method"][value="cod"]');
        return codRadio && codRadio.checked ? 'cod' : 'bank_card';
    }
});