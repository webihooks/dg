<style>
/* Rejection dialog styles */
.rejection-dialog {
    animation: slideInUp 0.3s ease-out;
}

@keyframes slideInDown {
    from {
        transform: translateY(-20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.rejection-option {
    padding: 10px;
    margin: 5px 0;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.rejection-option:hover {
    background: #f8f9fa;
    border-color: #dc3545;
}

.rejection-option.selected {
    background: #fff5f5;
    border-color: #dc3545;
    color: #dc3545;
}

/* Additional styles for the enhanced order popup */
@keyframes slideInUp {
    from {
        transform: translate(-50%, 100%);
        opacity: 0;
    }
    to {
        transform: translate(-50%, 0);
        opacity: 1;
    }
}

#floatingActionButtons {
    animation: slideInUp 0.3s ease-out;
}

.order-item-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 4px 0;
    border-bottom: 1px solid #f0f0f0;
}

.order-item-row:last-child {
    border-bottom: none;
}

.order-item-name {
    flex: 1;
    font-size: 13px;
}

.order-item-qty {
    font-weight: bold;
    color: #333;
    margin: 0 10px;
}

.order-item-price {
    font-weight: bold;
    color: #28a745;
}

.customer-address {
    background: #e9f7fe;
    border-radius: 5px;
    padding: 8px 10px;
    margin: 8px 0;
    border-left: 3px solid #17a2b8;
    font-size: 13px;
}

.order-total {
    background: #f8f9fa;
    border-radius: 5px;
    padding: 8px 10px;
    margin-top: 8px;
    font-weight: bold;
    text-align: right;
    border-top: 2px solid #dee2e6;
}
</style>

<script>
// Global polling configuration
const POLLING_CONFIG = {
    interval: 1000,
    active: true,
    lastOrderId: 0,
    isReloading: false,
    pageLoadTime: Math.floor(Date.now() / 1000),
    pendingOrders: new Map(),
    isSoundPlaying: false,
    audioElement: null,
    audioRetryCount: 0,
    maxAudioRetries: 3
};

// Main initialization
async function initOrderSystem() {
    console.log('Initializing order system with continuous MP3 playback...');
    
    await initAudioSystem();
    initOrderPolling();
    setupEventListeners();
    
    console.log('Order system initialized');
}

// Audio System - Focus on continuous MP3 playback
async function initAudioSystem() {
    console.log('Initializing audio system...');
    
    // Create audio element for continuous playback
    POLLING_CONFIG.audioElement = new Audio();
    POLLING_CONFIG.audioElement.src = 'assets/sounds/new_order.mp3?' + Date.now(); // Cache buster
    POLLING_CONFIG.audioElement.loop = true; // Continuous looping
    POLLING_CONFIG.audioElement.volume = 0.9; // 90% volume
    POLLING_CONFIG.audioElement.preload = 'auto';
    
    // Event listeners for audio element
    POLLING_CONFIG.audioElement.addEventListener('canplaythrough', () => {
        console.log('Audio ready for playback');
    });
    
    POLLING_CONFIG.audioElement.addEventListener('error', (e) => {
        console.error('Audio error:', e);
        retryAudioLoad();
    });
    
    POLLING_CONFIG.audioElement.addEventListener('ended', () => {
        // Should not happen with loop=true, but just in case
        if (POLLING_CONFIG.isSoundPlaying) {
            playContinuousSound();
        }
    });
    
    // Load the audio
    POLLING_CONFIG.audioElement.load();
}

function retryAudioLoad() {
    if (POLLING_CONFIG.audioRetryCount >= POLLING_CONFIG.maxAudioRetries) {
        console.error('Max audio retries reached');
        return;
    }
    
    POLLING_CONFIG.audioRetryCount++;
    console.log(`Retrying audio load (attempt ${POLLING_CONFIG.audioRetryCount})`);
    
    setTimeout(() => {
        POLLING_CONFIG.audioElement.src = 'assets/sounds/new_order.mp3?' + Date.now();
        POLLING_CONFIG.audioElement.load();
    }, 1000);
}

// Play continuous sound with aggressive retry strategy
function playContinuousSound() {
    if (POLLING_CONFIG.isSoundPlaying) return;
    
    console.log('Starting continuous sound playback');
    POLLING_CONFIG.isSoundPlaying = true;
    
    const playSound = () => {
        if (!POLLING_CONFIG.isSoundPlaying) return;
        
        try {
            POLLING_CONFIG.audioElement.currentTime = 0;
            POLLING_CONFIG.audioElement.loop = true;
            
            const playPromise = POLLING_CONFIG.audioElement.play();
            
            if (playPromise !== undefined) {
                playPromise.then(() => {
                    console.log('Continuous sound playing successfully');
                }).catch(error => {
                    console.log('Playback blocked, will retry:', error);
                    
                    // Aggressive retry strategy
                    setTimeout(() => {
                        if (POLLING_CONFIG.isSoundPlaying) {
                            playSound();
                        }
                    }, 1000);
                });
            }
        } catch (error) {
            console.error('Playback error:', error);
            // Retry after delay
            setTimeout(() => {
                if (POLLING_CONFIG.isSoundPlaying) {
                    playSound();
                }
            }, 2000);
        }
    };
    
    // Initial play attempt
    playSound();
    
    // Additional periodic play attempts to overcome browser restrictions
    const keepAliveInterval = setInterval(() => {
        if (!POLLING_CONFIG.isSoundPlaying) {
            clearInterval(keepAliveInterval);
            return;
        }
        
        // If audio is paused (might happen in background), try to resume
        if (POLLING_CONFIG.audioElement.paused) {
            console.log('Audio paused, attempting to resume...');
            playSound();
        }
    }, 3000);
}

function stopContinuousSound() {
    if (!POLLING_CONFIG.isSoundPlaying) return;
    
    console.log('Stopping continuous sound');
    POLLING_CONFIG.isSoundPlaying = false;
    
    try {
        POLLING_CONFIG.audioElement.pause();
        POLLING_CONFIG.audioElement.currentTime = 0;
        POLLING_CONFIG.audioElement.loop = false;
    } catch (error) {
        console.error('Error stopping sound:', error);
    }
}

// Enhanced notification function
function notifyNewOrder() {
    console.log('New order notification triggered');
    
    // Always try to play the continuous MP3 sound
    if (!POLLING_CONFIG.isSoundPlaying) {
        playContinuousSound();
    }
    
    // Visual notifications (optional)
    showVisualNotification();
    showOrderActionButtons();
}

// Visual notification (minimal - just the action buttons)
function showVisualNotification() {
    // Simple tab title update
    const originalTitle = document.title;
    if (!originalTitle.includes('🔔')) {
        document.title = '🔔 ' + originalTitle;
        
        // Restore title after 10 seconds
        setTimeout(() => {
            if (document.title.includes('🔔')) {
                document.title = originalTitle;
            }
        }, 10000);
    }
}

// Polling system
function initOrderPolling() {
    // Set initial lastOrderId
    const orderElements = document.querySelectorAll('[data-order-id]');
    if (orderElements.length > 0) {
        const orderIds = Array.from(orderElements)
            .map(el => parseInt(el.getAttribute('data-order-id')))
            .filter(id => !isNaN(id));
        
        if (orderIds.length > 0) {
            POLLING_CONFIG.lastOrderId = Math.max(...orderIds);
        }
    }
    
    console.log('Starting order polling');
    checkForNewOrders();
}

function setupEventListeners() {
    // Resume audio on any user interaction
    const resumeEvents = ['click', 'mousedown', 'touchstart', 'keydown', 'focus'];
    
    resumeEvents.forEach(event => {
        document.addEventListener(event, () => {
            if (POLLING_CONFIG.isSoundPlaying && POLLING_CONFIG.audioElement.paused) {
                console.log('User interaction detected, resuming audio...');
                playContinuousSound();
            }
        }, { passive: true });
    });
    
    // Handle page visibility changes
    document.addEventListener('visibilitychange', () => {
        const isVisible = !document.hidden;
        console.log('Tab visibility changed:', isVisible);
        
        if (isVisible && POLLING_CONFIG.isSoundPlaying && POLLING_CONFIG.audioElement.paused) {
            // Tab became visible - try to resume playback
            setTimeout(() => {
                playContinuousSound();
            }, 500);
        }
    });
}

function checkForNewOrders() {
    if (POLLING_CONFIG.isReloading) return;
    
    const pageLoadTime = sessionStorage.getItem('pageLoadTime') || POLLING_CONFIG.pageLoadTime;
    
    fetch(`check_new_orders.php?last_order_id=${POLLING_CONFIG.lastOrderId}&page_load_time=${pageLoadTime}&t=${Date.now()}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error('API error:', data.error);
                return;
            }
            
            if (data.new_orders && data.new_orders.length > 0) {
                handleNewOrders(data.new_orders);
            }
        })
        .catch(error => {
            console.error('Polling error:', error);
        })
        .finally(() => {
            if (POLLING_CONFIG.active && !POLLING_CONFIG.isReloading) {
                setTimeout(checkForNewOrders, POLLING_CONFIG.interval);
            }
        });
}

function handleNewOrders(newOrders) {
    const newMaxOrderId = Math.max(POLLING_CONFIG.lastOrderId, ...newOrders.map(o => o.order_id));
    
    if (newMaxOrderId > POLLING_CONFIG.lastOrderId) {
        POLLING_CONFIG.lastOrderId = newMaxOrderId;
        
        let hasNewPending = false;
        newOrders.forEach(order => {
            if (order.status === 'Pending' && !POLLING_CONFIG.pendingOrders.has(order.order_id)) {
                POLLING_CONFIG.pendingOrders.set(order.order_id, order);
                hasNewPending = true;
            }
        });
        
        if (hasNewPending) {
            console.log(`New pending orders detected: ${POLLING_CONFIG.pendingOrders.size}`);
            
            // Trigger continuous MP3 playback
            notifyNewOrder();
            
            // Show toast notification
            showToast(`New order received! Pending: ${POLLING_CONFIG.pendingOrders.size}`, 'success');
        }
    }
    
    updateUI();
}

function updateUI() {
    if (POLLING_CONFIG.pendingOrders.size > 0) {
        if (!document.getElementById('floatingActionButtons')) {
            showOrderActionButtons();
        } else {
            updateOrderActionButtons();
        }
    } else {
        hideOrderActionButtons();
        stopContinuousSound();
        // Restore original title
        document.title = document.title.replace('🔔 ', '');
    }
}

// Order Action Buttons with Order Details
function showOrderActionButtons() {
    hideOrderActionButtons();
    
    const buttonContainer = document.createElement('div');
    buttonContainer.id = 'floatingActionButtons';
    buttonContainer.style.cssText = `
        position: fixed;
        bottom: 15px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 15px;
        align-items: center;
        background: rgba(255, 255, 255, 0.98);
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 8px 30px rgba(0,0,0,0.3);
        backdrop-filter: blur(15px);
        border: 1px solid rgba(255,255,255,0.3);
        max-width: 500px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
    `;
    
    // Header
    const header = document.createElement('div');
    header.style.cssText = `
        text-align: center;
        width: 100%;
    `;
    header.innerHTML = `
        <h4 style="margin: 0; color: #333; font-weight: bold;">
            🔔 New Orders Pending (${POLLING_CONFIG.pendingOrders.size})
        </h4>
        <p style="margin: 5px 0 0 0; color: #666; font-size: 14px;">
            Action Required
        </p>
    `;
    buttonContainer.appendChild(header);
    
    // Order Details Container
    const ordersContainer = document.createElement('div');
    ordersContainer.style.cssText = `
        width: 100%;
        max-height: 300px;
        overflow-y: auto;
        border: 1px solid #e0e0e0;
        border-radius: 10px;
        padding: 15px;
        background: #f8f9fa;
        margin-bottom: 10px;
    `;
    
    // Add each order's details
    POLLING_CONFIG.pendingOrders.forEach((order, orderId) => {
        const orderElement = createOrderElement(order, orderId);
        ordersContainer.appendChild(orderElement);
    });
    
    buttonContainer.appendChild(ordersContainer);
    
    // Action Buttons Container
    const actionButtonsContainer = document.createElement('div');
    actionButtonsContainer.style.cssText = `
        display: flex;
        gap: 15px;
        justify-content: center;
        width: 100%;
        flex-wrap: wrap;
    `;
    
    // Accept Button
    const acceptButton = document.createElement('button');
    acceptButton.id = 'acceptOrderButton';
    acceptButton.innerHTML = `✅ Accept (${POLLING_CONFIG.pendingOrders.size})`;
    acceptButton.style.cssText = `
        padding: 12px 25px;
        font-size: 16px;
        font-weight: bold;
        border-radius: 25px;
        cursor: pointer;
        background-color: #28a745;
        border: 2px solid #28a745;
        color: white;
        min-width: 160px;
        text-align: center;
        transition: all 0.3s ease;
        animation: pulseGreen 2s infinite;
        flex: 1;
    `;
    
    acceptButton.addEventListener('click', acceptAllPendingOrders);
    
    // Reject Button
    const rejectButton = document.createElement('button');
    rejectButton.id = 'rejectOrderButton';
    rejectButton.innerHTML = `❌ Reject (${POLLING_CONFIG.pendingOrders.size})`;
    rejectButton.style.cssText = `
        padding: 12px 25px;
        font-size: 16px;
        font-weight: bold;
        border-radius: 25px;
        cursor: pointer;
        background-color: #dc3545;
        border: 2px solid #dc3545;
        color: white;
        min-width: 160px;
        text-align: center;
        transition: all 0.3s ease;
        animation: pulseRed 2s infinite;
        flex: 1;
    `;
    
    rejectButton.addEventListener('click', rejectAllPendingOrders);
    
    // Add hover effects
    acceptButton.addEventListener('mouseenter', () => {
        acceptButton.style.backgroundColor = '#218838';
        acceptButton.style.transform = 'scale(1.05)';
    });
    acceptButton.addEventListener('mouseleave', () => {
        acceptButton.style.backgroundColor = '#28a745';
        acceptButton.style.transform = 'scale(1)';
    });
    
    rejectButton.addEventListener('mouseenter', () => {
        rejectButton.style.backgroundColor = '#c82333';
        rejectButton.style.transform = 'scale(1.05)';
    });
    rejectButton.addEventListener('mouseleave', () => {
        rejectButton.style.backgroundColor = '#dc3545';
        rejectButton.style.transform = 'scale(1)';
    });
    
    actionButtonsContainer.appendChild(acceptButton);
    actionButtonsContainer.appendChild(rejectButton);
    buttonContainer.appendChild(actionButtonsContainer);
    
    // Close Button
    const closeButton = document.createElement('button');
    closeButton.innerHTML = '✕ Close';
    closeButton.style.cssText = `
        padding: 8px 20px;
        font-size: 14px;
        border-radius: 20px;
        cursor: pointer;
        background-color: #6c757d;
        border: 2px solid #6c757d;
        color: white;
        margin-top: 10px;
        transition: all 0.3s ease;
        display:none;
    `;
    
    closeButton.addEventListener('click', hideOrderActionButtons);
    closeButton.addEventListener('mouseenter', () => {
        closeButton.style.backgroundColor = '#5a6268';
    });
    closeButton.addEventListener('mouseleave', () => {
        closeButton.style.backgroundColor = '#6c757d';
    });
    
    buttonContainer.appendChild(closeButton);
    document.body.appendChild(buttonContainer);
}

function createOrderElement(order, orderId) {
    const orderElement = document.createElement('div');
    orderElement.style.cssText = `
        background: white;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
        border-left: 4px solid orange;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    `;
    
    // Order Header
    const orderHeader = document.createElement('div');
    orderHeader.style.cssText = `
        display: flex;
        justify-content: between;
        align-items: center;
        margin-bottom: 10px;
        padding-bottom: 8px;
        border-bottom: 1px solid #e9ecef;
    `;
    
    orderHeader.innerHTML = `
        <div style="flex: 1;">
            <strong style="color: #333;">Order #${orderId}</strong>
            <div style="font-size: 12px; color: #666;">
                ${new Date(order.created_at).toLocaleString()}
            </div>
        </div>
        <div style="text-align: right;">
            <span style="background: #ffc107; color: #000; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold;">
                ${order.status || 'Pending'}
            </span>
        </div>
    `;
    
    // Customer Info
    const customerInfo = document.createElement('div');
    customerInfo.style.cssText = `
        margin-bottom: 10px;
        font-size: 14px;
    `;
    
    customerInfo.innerHTML = `
        <div style="float:left;"><strong>👤 ${order.customer_name || 'Customer'}</strong></div>
        <div style="color: #666; float:right;">📞 ${order.customer_phone || 'No phone'}</div>
        <div style="clear:both;"></div>
    `;
    
    // Address (for delivery orders)
    let addressHtml = '';
    if (order.order_type === 'delivery' && order.delivery_address) {
        addressHtml = `
            <div style="margin-bottom: 10px; font-size: 13px;">
                <strong>📍 Delivery Address:</strong>
                <div style="color: #666; margin-top: 2px;">${order.delivery_address}</div>
            </div>
        `;
    } else if (order.order_type === 'dining' && order.table_number) {
        addressHtml = `
            <div style="margin-bottom: 10px; font-size: 13px;">
                <strong>🍽️ Table Number:</strong>
                <span style="color: #666;">${order.table_number}</span>
            </div>
        `;
    }
    
    // Order Items
    const itemsContainer = document.createElement('div');
    itemsContainer.style.cssText = `
        font-size: 13px;
    `;
    
    let itemsHtml = '<strong>🛒 Order Items:</strong><div style="margin-top: 5px;">';
    
    if (order.items && order.items.length > 0) {
        order.items.forEach(item => {
            itemsHtml += `
                <div style="display: flex; justify-content: space-between; margin-bottom: 3px; padding: 2px 0;">
                    <span>${item.product_name || 'Item'}</span>
                    <span>
                        <strong>${item.quantity || 1} × ₹${parseFloat(item.price || 0)}</strong>
                    </span>
                </div>
            `;
        });
        
        // Total
        itemsHtml += `
            <div style="border-top: 1px solid #dee2e6; margin-top: 8px; padding-top: 5px; font-weight: bold;">
                <div style="display: flex; justify-content: space-between;">
                    <span>Total:</span>
                    <span>₹${parseFloat(order.total_amount || 0)}</span>
                </div>
            </div>
        `;
    } else {
        itemsHtml += '<div style="color: #999; font-style: italic;">No items found</div>';
    }
    
    itemsHtml += '</div>';
    itemsContainer.innerHTML = itemsHtml;
    
    // Assemble order element
    orderElement.appendChild(orderHeader);
    orderElement.appendChild(customerInfo);
    
    if (addressHtml) {
        const addressElement = document.createElement('div');
        addressElement.innerHTML = addressHtml;
        orderElement.appendChild(addressElement);
    }
    
    orderElement.appendChild(itemsContainer);
    
    return orderElement;
}

function updateOrderActionButtons() {
    const container = document.getElementById('floatingActionButtons');
    if (container) {
        // Remove and recreate with updated data
        hideOrderActionButtons();
        showOrderActionButtons();
    }
}

function hideOrderActionButtons() {
    const container = document.getElementById('floatingActionButtons');
    if (container) container.remove();
}

async function acceptAllPendingOrders() {
    if (POLLING_CONFIG.pendingOrders.size === 0) return;
    
    const button = document.getElementById('acceptOrderButton');
    const originalText = button.innerHTML;
    button.innerHTML = '⏳ Processing...';
    button.disabled = true;
    
    // Also disable reject button during processing
    const rejectButton = document.getElementById('rejectOrderButton');
    if (rejectButton) rejectButton.disabled = true;
    
    try {
        const orderIds = Array.from(POLLING_CONFIG.pendingOrders.keys());
        
        // First, get business info and profile URL
        const businessData = await fetchBusinessData();
        
        const response = await fetch('accept_orders.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({order_ids: orderIds, new_status: 'Confirmed'})
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast(`Accepted ${orderIds.length} order(s)!`, 'success');
            
            // Send WhatsApp confirmation for each accepted order
            orderIds.forEach(orderId => {
                const order = POLLING_CONFIG.pendingOrders.get(orderId);
                if (order && order.customer_phone) {
                    // Send WhatsApp message with slight delay to avoid rate limiting
                    setTimeout(() => {
                        sendOrderConfirmation(
                            orderId,
                            order.customer_phone,
                            order.customer_name || 'Customer',
                            order.order_type || 'delivery',
                            businessData.businessInfo,
                            businessData.userPhone,
                            businessData.profileUrl
                        );
                    }, orderIds.indexOf(orderId) * 1000); // Stagger messages by 1 second
                }
            });
            
            // Stop the continuous sound
            stopContinuousSound();
            POLLING_CONFIG.pendingOrders.clear();
            hideOrderActionButtons();
            document.title = document.title.replace('🔔 ', '');
            
            // Redirect to orders page after a short delay
            setTimeout(() => {
                window.location.href = 'orders.php';
            }, 2000);
        } else {
            throw new Error(result.error);
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Error: ' + error.message, 'danger');
        button.innerHTML = originalText;
        button.disabled = false;
        if (rejectButton) rejectButton.disabled = false;
    }
}

// Function to fetch business data
async function fetchBusinessData() {
    try {
        const response = await fetch('get_business_data.php');
        const data = await response.json();
        
        if (data.success) {
            return {
                businessInfo: data.business_info,
                userPhone: data.user_phone,
                profileUrl: data.profile_url
            };
        } else {
            throw new Error('Failed to fetch business data');
        }
    } catch (error) {
        console.error('Error fetching business data:', error);
        // Return default values
        return {
            businessInfo: { business_name: 'Our Restaurant' },
            userPhone: '',
            profileUrl: ''
        };
    }
}

async function rejectAllPendingOrders() {
    if (POLLING_CONFIG.pendingOrders.size === 0) return;
    
    // Show rejection reason dialog
    const rejectionReason = await showRejectionReasonDialog();
    if (!rejectionReason) {
        return; // User cancelled
    }
    
    const button = document.getElementById('rejectOrderButton');
    const originalText = button.innerHTML;
    button.innerHTML = '⏳ Rejecting...';
    button.disabled = true;
    
    // Also disable accept button during processing
    const acceptButton = document.getElementById('acceptOrderButton');
    if (acceptButton) acceptButton.disabled = true;
    
    try {
        const orderIds = Array.from(POLLING_CONFIG.pendingOrders.keys());
        
        const response = await fetch('reject_orders.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                order_ids: orderIds, 
                new_status: 'Cancelled',
                rejection_reason: rejectionReason
            })
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();
        console.log('Reject response:', result);

        if (result.success) {
            showToast(result.message || `Rejected ${result.affected_rows} order(s)!`, 'warning');
            
            // Send rejection notifications for each rejected order
            if (result.orders_data && result.orders_data.length > 0) {
                console.log('Sending rejection notifications for', result.orders_data.length, 'orders');
                
                result.orders_data.forEach((order, index) => {
                    setTimeout(() => {
                        sendOrderRejection(
                            order.order_id,
                            order.customer_phone,
                            order.customer_name || 'Customer',
                            order.order_type || 'delivery',
                            order.total_amount || 0,
                            result.business_info || { business_name: 'Our Restaurant' },
                            result.user_phone || '',
                            result.profile_url || '',
                            result.rejection_reason || rejectionReason
                        );
                    }, index * 2000);
                });
            } else {
                console.log('No orders_data in response, using pending orders data');
                
                // Fallback: Use the pending orders data
                orderIds.forEach((orderId, index) => {
                    const order = POLLING_CONFIG.pendingOrders.get(orderId);
                    if (order && order.customer_phone) {
                        setTimeout(() => {
                            sendOrderRejection(
                                orderId,
                                order.customer_phone,
                                order.customer_name || 'Customer',
                                order.order_type || 'delivery',
                                order.total_amount || 0,
                                result.business_info || { business_name: 'Our Restaurant' },
                                result.user_phone || '',
                                result.profile_url || '',
                                result.rejection_reason || rejectionReason
                            );
                        }, index * 2000);
                    }
                });
            }
            
            // Stop the continuous sound
            stopContinuousSound();
            POLLING_CONFIG.pendingOrders.clear();
            hideOrderActionButtons();
            document.title = document.title.replace('🔔 ', '');
            
            // Refresh the page to show updated order status
            setTimeout(() => {
                window.location.reload();
            }, 5000);
            
        } else {
            throw new Error(result.error || 'Failed to reject orders');
        }
        
    } catch (error) {
        console.error('Rejection error:', error);
        showToast('Error rejecting orders: ' + error.message, 'danger');
        
        // Restore buttons
        button.innerHTML = originalText;
        button.disabled = false;
        if (acceptButton) acceptButton.disabled = false;
    }
}

// Function to show rejection reason dialog
function showRejectionReasonDialog() {
    return new Promise((resolve) => {
        // Create modal overlay
        const overlay = document.createElement('div');
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
        `;
        
        // Create dialog
        const dialog = document.createElement('div');
        dialog.style.cssText = `
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        `;
        
        dialog.innerHTML = `
            <h3 style="margin: 0 0 15px 0; color: #dc3545; font-size: 20px;">
                <i class="bi bi-exclamation-triangle-fill" style="margin-right: 8px;"></i>
                Reject Orders
            </h3>
            <p style="margin: 0 0 20px 0; color: #666; font-size: 14px;">
                Please select a reason for rejecting these orders:
            </p>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: bold; color: #333;">
                    Rejection Reason:
                </label>
                <select id="rejectionReasonSelect" style="
                    width: 100%;
                    padding: 10px;
                    border: 2px solid #e9ecef;
                    border-radius: 6px;
                    font-size: 14px;
                    background: white;
                ">
                    <option value="Out of stock">Items out of stock</option>
                    <option value="Restaurant closed">Restaurant is closed</option>
                    <option value="Delivery area not serviceable">Delivery area not serviceable</option>
                    <option value="Technical issue">Technical issue</option>
                    <option value="Customer request">Customer requested cancellation</option>
                    <option value="Other">Other reason</option>
                </select>
                
                <div id="customReasonContainer" style="display: none; margin-top: 10px;">
                    <textarea 
                        id="customReason" 
                        placeholder="Please specify the reason..."
                        style="
                            width: 100%;
                            padding: 10px;
                            border: 2px solid #e9ecef;
                            border-radius: 6px;
                            font-size: 14px;
                            resize: vertical;
                            min-height: 80px;
                        "
                    ></textarea>
                </div>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button id="cancelReject" style="
                    padding: 10px 20px;
                    border: 2px solid #6c757d;
                    background: white;
                    color: #6c757d;
                    border-radius: 6px;
                    cursor: pointer;
                    font-weight: bold;
                ">Cancel</button>
                <button id="confirmReject" style="
                    padding: 10px 20px;
                    border: 2px solid #dc3545;
                    background: #dc3545;
                    color: white;
                    border-radius: 6px;
                    cursor: pointer;
                    font-weight: bold;
                ">Reject Orders</button>
            </div>
        `;
        
        overlay.appendChild(dialog);
        document.body.appendChild(overlay);
        
        // Handle custom reason toggle
        const reasonSelect = dialog.querySelector('#rejectionReasonSelect');
        const customContainer = dialog.querySelector('#customReasonContainer');
        
        reasonSelect.addEventListener('change', function() {
            customContainer.style.display = this.value === 'Other' ? 'block' : 'none';
        });
        
        // Handle button clicks
        dialog.querySelector('#cancelReject').addEventListener('click', () => {
            document.body.removeChild(overlay);
            resolve(null);
        });
        
        dialog.querySelector('#confirmReject').addEventListener('click', () => {
            let reason = reasonSelect.value;
            if (reason === 'Other') {
                reason = dialog.querySelector('#customReason').value.trim();
                if (!reason) {
                    alert('Please specify the rejection reason.');
                    return;
                }
            }
            document.body.removeChild(overlay);
            resolve(reason);
        });
        
        // Close on overlay click
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                document.body.removeChild(overlay);
                resolve(null);
            }
        });
    });
}

function showToast(message, type) {
    // Your existing toast implementation
    console.log(`${type}: ${message}`);
    // You can add your toast UI here
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 5px;
        color: white;
        font-weight: bold;
        z-index: 10000;
        background-color: ${type === 'success' ? '#28a745' : type === 'warning' ? '#ffc107' : '#dc3545'};
    `;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

// Add CSS
const style = document.createElement('style');
style.textContent = `
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
    
    @keyframes pulseGreen {
        0% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7); }
        70% { box-shadow: 0 0 0 10px rgba(40, 167, 69, 0); }
        100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
    }
    
    @keyframes pulseRed {
        0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
        70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
        100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
    }
    
    #acceptOrderButton:hover {
        background-color: #218838 !important;
        transform: scale(1.05);
    }
    
    #rejectOrderButton:hover {
        background-color: #c82333 !important;
        transform: scale(1.05);
    }
`;
document.head.appendChild(style);

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', initOrderSystem);

// Additional initialization for when the page becomes visible
document.addEventListener('visibilitychange', () => {
    if (!document.hidden && POLLING_CONFIG.isSoundPlaying) {
        // Page became visible - try to resume playback
        setTimeout(() => {
            if (POLLING_CONFIG.audioElement.paused) {
                playContinuousSound();
            }
        }, 100);
    }
});

// WhatsApp notification function formatted as per sample
function sendOrderConfirmation(orderId, customerPhone, customerName, orderType, businessInfo, businessPhone, profileUrl) {
    try {
        // Validate inputs
        if (!customerPhone || customerPhone.length < 10) {
            console.warn(`Invalid phone number for order ${orderId}: ${customerPhone}`);
            return false;
        }

        // Business details
        const businessName = businessInfo?.business_name || 'Our Restaurant';
        const businessAddress = businessInfo?.business_address || '';
        const phone = businessPhone || '';

        // Format customer phone
        let formattedCustomerPhone = customerPhone.replace(/\D/g, '');
        if (formattedCustomerPhone.length === 10) {
            formattedCustomerPhone = '91' + formattedCustomerPhone;
        }

        // URLs
        const orderStatusUrl = profileUrl 
            ? `https://deegeecard.com/order_status.php?order_id=${orderId}&profile_url=${encodeURIComponent(profileUrl)}`
            : `https://deegeecard.com/order_status.php?order_id=${orderId}`;
            
        const profileOrderUrl = profileUrl 
            ? `https://deegeecard.com/${profileUrl}`
            : 'https://deegeecard.com';

        // Create confirmation message exactly as per sample
        let message = `🚀 *Next time, order faster!*\n`;
        message += `Place your order easily here:\n`;
        message += `🔗 ${profileOrderUrl}\n\n`;
        
        message += `🍽 *${businessName.toUpperCase()}*\n`;
        message += `✅ Order Confirmed #${orderId}\n\n`;
        
        message += `👋 Dear ${customerName},\n`;
        message += `Your order has been confirmed and is now being processed!\n\n`;
        
        message += `📋 *Order Details:*\n`;
        message += `•⁠  ⁠Order Type: ${orderType === 'delivery' ? '🚚 Delivery' : orderType === 'dining' ? '🍽️ Dining' : orderType}\n`;
        message += `•⁠  ⁠Order ID: #${orderId}\n\n`;
        
        message += `🔎 *Track Your Order:*\n`;
        message += `${orderStatusUrl}\n\n`;
        
        message += `❤️ *Thank you for choosing ${businessName}!*\n`;
        message += `We truly appreciate your business.`;

        // Create WhatsApp URL
        const whatsappUrl = `https://wa.me/${formattedCustomerPhone}?text=${encodeURIComponent(message)}`;
        
        // Open WhatsApp in new tab
        const newWindow = window.open(whatsappUrl, '_blank', 'noopener,noreferrer');
        
        console.log('WhatsApp confirmation sent to:', customerPhone);
        showToast(`Confirmation sent to ${customerName}`, 'success');
        return true;
        
    } catch (error) {
        console.error('Error sending WhatsApp confirmation:', error);
        showToast(`Error sending WhatsApp to ${customerName}`, 'danger');
        return false;
    }
}

// WhatsApp notification function for order rejection
function sendOrderRejection(orderId, customerPhone, customerName, orderType, totalAmount, businessInfo, businessPhone, profileUrl, rejectionReason) {
    try {
        // Validate inputs
        if (!customerPhone || customerPhone.length < 10) {
            console.warn(`Invalid phone number for order ${orderId}: ${customerPhone}`);
            return false;
        }

        // Business details
        const businessName = businessInfo?.business_name || 'Our Restaurant';
        const phone = businessPhone || '';

        // Format customer phone
        let formattedCustomerPhone = customerPhone.replace(/\D/g, '');
        if (formattedCustomerPhone.length === 10) {
            formattedCustomerPhone = '91' + formattedCustomerPhone;
        }

        // Create rejection message
        let message = `😔 *Order Cancelled* ❌\n\n`;
        message += `🍽 *${businessName.toUpperCase()}*\n`;
        message += `❌ Order Cancelled #${orderId}\n\n`;
        
        message += `👋 Dear ${customerName},\n`;
        message += `We regret to inform you that your order #${orderId} has been cancelled.\n\n`;
        
        message += `📋 *Order Details:*\n`;
        message += `•⁠  ⁠Order Type: ${orderType === 'delivery' ? '🚚 Delivery' : orderType === 'dining' ? '🍽️ Dining' : orderType}\n`;
        message += `•⁠  ⁠Order ID: #${orderId}\n`;
        message += `•⁠  ⁠Amount: ₹${parseFloat(totalAmount).toFixed(2)}\n\n`;
        
        message += `📝 *Cancellation Reason:*\n`;
        message += `${rejectionReason}\n\n`;
        
        message += `🚀 *Next time, order faster!*\n`;
        message += `Place your order easily here:\n`;
        if (profileUrl) {
            message += `🔗 https://deegeecard.com/${profileUrl}\n\n`;
        } else {
            message += `🔗 https://deegeecard.com\n\n`;
        }
        
        message += `❤️ *We apologize for any inconvenience.*\n`;
        message += `Thank you for considering ${businessName}!\n\n`;

        // Create WhatsApp URL
        const whatsappUrl = `https://wa.me/${formattedCustomerPhone}?text=${encodeURIComponent(message)}`;
        
        // Open WhatsApp in new tab
        const newWindow = window.open(whatsappUrl, '_blank', 'noopener,noreferrer');
        
        
        console.log('WhatsApp rejection sent to:', customerPhone);
        showToast(`Rejection notification sent to ${customerName}`, 'warning');
        return true;
        
    } catch (error) {
        console.error('Error sending WhatsApp rejection:', error);
        showToast(`Error sending rejection to ${customerName}`, 'danger');
        return false;
    }
}
</script>

<div class="main-nav">
   <!-- Sidebar Logo -->
   <div class="logo-box">
      <a href="javascript:void(0)" class="logo-dark">
      <img src="assets/images/logo-sm.png" class="logo-sm" alt="logo sm">
      <img src="assets/images/logo-dark.png" class="logo-lg" alt="logo dark">
      </a>
      <a href="javascript:void(0)" class="logo-light">
      <img src="assets/images/logo-sm.png" class="logo-sm" alt="logo sm">
      <img src="assets/images/logo-light.png" class="logo-lg" alt="logo light">
      </a>
   </div>
   <!-- Menu Toggle Button (sm-hover) -->
   <button type="button" class="button-sm-hover" aria-label="Show Full Sidebar">
      <iconify-icon icon="solar:double-alt-arrow-right-bold-duotone" class="button-sm-hover-icon"></iconify-icon>
   </button>
   <div class="scrollbar" data-simplebar>
      <ul class="navbar-nav" id="navbar-nav">
         <li class="nav-item">
            <a class="nav-link" href="dashboard.php">
               <span class="nav-icon">
                  <iconify-icon icon="solar:widget-5-bold-duotone"></iconify-icon>
               </span>
               <span class="nav-text">Dashboard</span>
            </a>
         </li>
         <li class="menu-title">Orders</li>
         <li class="nav-item">
            <a class="nav-link" href="orders.php">
               <span class="nav-icon">
                  <iconify-icon icon="fluent-mdl2:activate-orders"></iconify-icon>
               </span>
               <span class="nav-text">List of Orders</span>
            </a>
         </li>
         <li class="nav-item">
            <a class="nav-link" href="kot.php">
               <span class="nav-icon">
                  <iconify-icon icon="streamline-ultimate:seasoning-food"></iconify-icon>
               </span>
               <span class="nav-text">KOT</span>
            </a>
         </li>
         <li class="nav-item">
            <a class="nav-link" href="sales_report.php">
               <span class="nav-icon">
                  <iconify-icon icon="carbon:sales-ops"></iconify-icon>
               </span>
               <span class="nav-text">Sales Report</span>
            </a>
         </li>
         <li class="nav-item">
            <a class="nav-link" href="customer_data.php">
               <span class="nav-icon">
                  <iconify-icon icon="streamline:information-desk-customer"></iconify-icon>
               </span>
               <span class="nav-text">Customer Data</span>
            </a>
         </li>

         <li class="nav-item">
            <a class="nav-link" href="whatsapp_marketing.php">
               <span class="nav-icon">
                  <iconify-icon icon="ic:sharp-whatsapp"></iconify-icon>
               </span>
               <span class="nav-text">Bulk WhatsApp Marketing</span>
            </a>
         </li>

         <li class="menu-title">Products</li>
         <li class="nav-item">
            <a class="nav-link menu-arrow" href="#personal" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="personal">
               <span class="nav-icon">
                  <iconify-icon icon="mdi:card-account-details-outline"></iconify-icon>
               </span>
               <span class="nav-text"> Personal </span>
            </a>
            <div class="collapse" id="personal">
               <ul class="nav sub-navbar-nav">
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="profile_url.php">Profile URL</a>
                  </li>
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="profile.php">Profile</a>
                  </li>
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="profile-cover-photo.php">Profile & Cover Photo</a>
                  </li>
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="social.php">Social Sites</a>
                  </li>
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="theme.php">Themes</a>
                  </li>
               </ul>
            </div>
         </li>
         <li class="nav-item">
            <a class="nav-link menu-arrow" href="#services" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="services">
               <span class="nav-icon">
                  <iconify-icon icon="clarity:list-line"></iconify-icon>
               </span>
               <span class="nav-text"> Service Utilities </span>
            </a>
            <div class="collapse" id="services">
               <ul class="nav sub-navbar-nav">
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="store_timing.php">Store Timing</a>
                  </li>
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="store_on_off.php">Store ON/OFF</a>
                  </li>
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="delivery_charges.php">Delivery Charges</a>
                  </li>
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="gst_charge.php">GST</a>
                  </li>
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="discount.php">Discount</a>
                  </li>
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="coupon.php">Coupon Code</a>
                  </li>
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="products.php">Products</a>
                  </li>
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="tags.php">Tags</a>
                  </li>
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="photo-gallery.php">Photo Gallery</a>
                  </li>
               </ul>
            </div>
         </li>
         <li class="nav-item">
            <a class="nav-link menu-arrow" href="#business" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="business">
               <span class="nav-icon">
                  <iconify-icon icon="vaadin:shop"></iconify-icon>
               </span>
               <span class="nav-text"> Business </span>
            </a>
            <div class="collapse" id="business">
               <ul class="nav sub-navbar-nav">
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="business.php">Business</a>
                  </li>
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="bank-details.php">Bank Details</a>
                  </li>
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="qr-code-details.php">QR Code Details</a>
                  </li>
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="upload_apk.php">Upload APK</a>
                  </li>
               </ul>
            </div>
         </li>
         <li class="nav-item">
            <a class="nav-link" href="customer-reviews.php">
               <span class="nav-icon">
                  <iconify-icon icon="solar:bill-list-line-duotone"></iconify-icon>
               </span>
               <span class="nav-text">Customer Reviews</span>
            </a>
         </li>

         <li class="nav-item">
            <a class="nav-link menu-arrow" href="#ticket" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="ticket">
               <span class="nav-icon">
                  <iconify-icon icon="material-symbols:help-outline"></iconify-icon>
               </span>
               <span class="nav-text"> Ticket </span>
            </a>
            <div class="collapse" id="ticket">
               <ul class="nav sub-navbar-nav">
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="create_ticket.php">Create Ticket</a>
                  </li>
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="view_tickets.php">View Tickets</a>
                  </li>
               </ul>
            </div>
         </li>

         <li class="nav-item">
            <a class="nav-link" href="subscription.php">
               <span class="nav-icon">
                  <iconify-icon icon="streamline:subscription-cashflow"></iconify-icon>
               </span>
               <span class="nav-text">Subscription</span>
            </a>
         </li>
      </ul>
   </div>
</div>