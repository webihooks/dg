<!-- QR Codes Section -->
<div class="mt-6 px-4">
    <div class="bg-white rounded-2xl shadow-sm p-6">
        <!-- Section Header -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-xl font-bold text-gray-800">Payment Methods</h2>
                <p class="text-gray-600 text-sm mt-1">Scan QR codes for quick payments</p>
            </div>
            <div class="flex items-center space-x-2">
                <i class="fas fa-qrcode text-2xl primary-text"></i>
            </div>
        </div>

        <?php if (empty($qr_codes)): ?>
        <!-- No QR Codes State -->
        <div class="text-center py-12">
            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-qrcode text-gray-400 text-2xl"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-800 mb-2">No Payment Methods</h3>
            <p class="text-gray-600 text-sm mb-6">Payment QR codes will be available soon.</p>
            <div class="flex justify-center space-x-3">
                <button class="bg-gray-100 text-gray-700 px-6 py-3 rounded-xl font-semibold text-sm flex items-center transition-all duration-300 hover:bg-gray-200">
                    <i class="fas fa-phone-alt mr-2"></i>
                    Call to Pay
                </button>
                <button class="primary-bg text-white px-6 py-3 rounded-xl font-semibold text-sm flex items-center transition-all duration-300 hover:shadow-lg transform hover:-translate-y-0.5">
                    <i class="fab fa-whatsapp mr-2"></i>
                    WhatsApp Pay
                </button>
            </div>
        </div>
        <?php else: ?>
        
        <!-- QR Codes Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="qrCodesContainer">
            <?php foreach($qr_codes as $index => $qr_code): 
                $payment_type = $qr_code['payment_type'] ?? 'Other';
                $is_default = $qr_code['is_default'] ?? 0;
                $qr_code_path = null;
                
                // Get QR code path only if upload_qr_code exists
                if (!empty($qr_code['upload_qr_code'])) {
                    $qr_code_path = getQrCodePath($qr_code['upload_qr_code']);
                }
                
                error_log("Processing QR code: " . $qr_code['upload_qr_code'] . " -> " . $qr_code_path);
            ?>
            <div class="qr-code-card bg-gradient-to-br from-white to-gray-50 rounded-2xl border border-gray-200 p-6 hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1"
                 data-payment-type="<?= strtolower($payment_type) ?>">
                
                <!-- Payment Type Header -->
                <div class="flex justify-between items-center mb-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 rounded-full <?= getPaymentTypeColor($payment_type) ?> flex items-center justify-center mr-3">
                            <i class="fas <?= getPaymentTypeIcon($payment_type) ?> text-lg"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($payment_type) ?></h3>
                            <?php if ($is_default): ?>
                            <span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full">Default</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($qr_code_path): ?>
                    <button class="text-gray-400 hover:text-gray-600 transition-colors qr-action-btn"
                            onclick="shareQRCode('<?= $qr_code_path ?>', '<?= htmlspecialchars($payment_type) ?>')">
                        <i class="fas fa-share-alt"></i>
                    </button>
                    <?php endif; ?>
                </div>

                <!-- QR Code Image or UPI ID -->
                <?php if ($qr_code_path): ?>
                <div class="relative mb-4">
                    <img src="<?= $qr_code_path ?>" 
                         alt="<?= htmlspecialchars($payment_type) ?> QR Code"
                         class="w-full h-64 object-contain rounded-xl border-2 border-gray-200 bg-white p-4 shadow-sm qr-image"
                         loading="lazy"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    
                    <!-- QR Code Fallback -->
                    <div class="w-full h-64 bg-gray-100 rounded-xl border-2 border-gray-200 flex flex-col items-center justify-center hidden">
                        <i class="fas fa-qrcode text-gray-400 text-4xl mb-3"></i>
                        <span class="text-gray-500 text-sm">QR Code Not Available</span>
                    </div>

                    <!-- Scan Overlay -->
                    <div class="absolute inset-0 bg-black/0 hover:bg-black/10 transition-all duration-300 rounded-xl flex items-center justify-center opacity-0 hover:opacity-100">
                        <div class="bg-white/90 backdrop-blur-sm px-4 py-2 rounded-full text-sm font-semibold text-gray-800 flex items-center">
                            <i class="fas fa-expand-arrows-alt mr-2"></i>
                            Tap to Scan
                        </div>
                    </div>
                </div>
                <?php elseif (!empty($qr_code['upi_id'])): ?>
                <!-- Show UPI ID if no QR code but UPI ID exists -->
                <div class="mb-4 p-4 bg-gray-50 rounded-xl border border-gray-200 text-center">
                    <i class="fas fa-id-card text-gray-400 text-3xl mb-3"></i>
                    <h4 class="font-semibold text-gray-800 mb-2">UPI ID</h4>
                    <div class="bg-white p-3 rounded-lg border border-gray-300">
                        <code class="text-sm font-mono text-gray-800"><?= htmlspecialchars($qr_code['upi_id']) ?></code>
                    </div>
                    <button class="mt-3 bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-all duration-300"
                            onclick="copyToClipboard('<?= htmlspecialchars($qr_code['upi_id']) ?>')">
                        <i class="fas fa-copy mr-2"></i>
                        Copy UPI ID
                    </button>
                </div>
                <?php else: ?>
                <!-- No QR code and no UPI ID -->
                <div class="mb-4 p-4 bg-gray-50 rounded-xl border border-gray-200 text-center">
                    <i class="fas fa-exclamation-triangle text-yellow-400 text-3xl mb-3"></i>
                    <h4 class="font-semibold text-gray-800 mb-2">No QR Code Available</h4>
                    <p class="text-gray-600 text-sm">Contact hotel for payment details</p>
                </div>
                <?php endif; ?>

                <!-- Payment Details -->
                <div class="space-y-3">
                    <?php if (!empty($qr_code['mobile_number'])): ?>
                    <div class="flex items-center text-sm text-gray-700 bg-gray-50 rounded-lg px-3 py-2">
                        <i class="fas fa-phone-alt text-gray-500 mr-3"></i>
                        <span class="font-medium"><?= htmlspecialchars($qr_code['mobile_number']) ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($qr_code['upi_id'])): ?>
                    <div class="flex items-center text-sm text-gray-700 bg-gray-50 rounded-lg px-3 py-2">
                        <i class="fas fa-at text-gray-500 mr-3"></i>
                        <span class="font-mono text-xs"><?= htmlspecialchars($qr_code['upi_id']) ?></span>
                    </div>
                    <?php endif; ?>

                    <!-- Quick Actions -->
                    <?php if ($qr_code_path): ?>
                    <div class="flex space-x-2 pt-2">
                        <button class="flex-1 bg-white border border-gray-300 text-gray-700 py-3 rounded-xl font-semibold text-sm flex items-center justify-center transition-all duration-300 hover:bg-gray-50 hover:border-gray-400"
                                onclick="openQRScanner('<?= $qr_code_path ?>', '<?= htmlspecialchars($payment_type) ?>')">
                            <i class="fas fa-camera mr-2"></i>
                            Scan
                        </button>
                        <button class="flex-1 bg-white border border-gray-300 text-gray-700 py-3 rounded-xl font-semibold text-sm flex items-center justify-center transition-all duration-300 hover:bg-gray-50 hover:border-gray-400"
                                onclick="downloadQRCode('<?= $qr_code_path ?>', '<?= htmlspecialchars($payment_type) ?>')">
                            <i class="fas fa-download mr-2"></i>
                            Save
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- QR Code Modal -->
<div id="qrModal" class="fixed inset-0 bg-black/90 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-sm w-full max-h-[90vh] overflow-hidden">
        <div class="relative">
            <!-- Modal Header -->
            <div class="flex justify-between items-center p-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800" id="qrModalTitle">Scan QR Code</h3>
                <button class="text-gray-400 hover:text-gray-600 transition-colors"
                        onclick="closeQRModal()">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <!-- Modal Content -->
            <div class="p-6 text-center">
                <img id="modalQrImage" 
                     src="" 
                     alt="QR Code"
                     class="w-64 h-64 object-contain mx-auto mb-4 rounded-lg border border-gray-200">
                
                <div id="qrModalContent">
                    <!-- Content will be dynamically filled -->
                </div>
                
                <div class="flex space-x-3 mt-6">
                    <button class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 py-3 rounded-xl font-semibold text-sm transition-all duration-300"
                            onclick="closeQRModal()">
                        Close
                    </button>
                    <button id="modalActionBtn" 
                            class="flex-1 primary-bg hover:opacity-90 text-white py-3 rounded-xl font-semibold text-sm transition-all duration-300">
                        <i class="fas fa-share-alt mr-2"></i>
                        Share
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// QR Code functionality
document.addEventListener('DOMContentLoaded', function() {
    initializeQRCodes();
});

function initializeQRCodes() {
    // Add click event to QR code images
    document.querySelectorAll('.qr-image').forEach(img => {
        img.addEventListener('click', function() {
            const qrCard = this.closest('.qr-code-card');
            const paymentType = qrCard.querySelector('h3').textContent;
            openQRModal(this.src, paymentType);
        });
    });

    // Add animation to QR code cards
    const qrCards = document.querySelectorAll('.qr-code-card');
    qrCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px) scale(0.95)';
        card.style.transition = 'all 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
        
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0) scale(1)';
        }, index * 150);
    });
}

function openQRModal(qrImageSrc, paymentType) {
    const modal = document.getElementById('qrModal');
    const modalImage = document.getElementById('modalQrImage');
    const modalTitle = document.getElementById('qrModalTitle');
    const modalContent = document.getElementById('qrModalContent');
    const actionBtn = document.getElementById('modalActionBtn');
    
    modalImage.src = qrImageSrc;
    modalTitle.textContent = `${paymentType} QR Code`;
    
    modalContent.innerHTML = `
        <h4 class="font-semibold text-gray-800 mb-2">${paymentType} Payment</h4>
        <p class="text-gray-600 text-sm">Scan this QR code with your payment app to make a secure payment.</p>
    `;
    
    actionBtn.innerHTML = '<i class="fas fa-share-alt mr-2"></i> Share';
    actionBtn.onclick = () => shareQRCode(qrImageSrc, paymentType);
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
}

function closeQRModal() {
    const modal = document.getElementById('qrModal');
    modal.classList.remove('flex');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function shareQRCode(qrImageSrc, paymentType) {
    const shareData = {
        title: `${paymentType} Payment - ${document.querySelector('h1').textContent}`,
        text: `Scan this ${paymentType} QR code to make payment at ${document.querySelector('h1').textContent}`,
        url: window.location.href
    };

    if (navigator.share) {
        navigator.share(shareData)
            .then(() => console.log('QR code shared successfully'))
            .catch((error) => {
                console.log('Error sharing:', error);
                copyToClipboard(window.location.href);
                showToast('Link copied to clipboard!', 'info');
            });
    } else {
        copyToClipboard(window.location.href);
        showToast('Link copied to clipboard!', 'info');
    }
}

function openQRScanner(qrImageSrc, paymentType) {
    openQRModal(qrImageSrc, paymentType);
    
    const modalContent = document.getElementById('qrModalContent');
    const actionBtn = document.getElementById('modalActionBtn');
    
    modalContent.innerHTML = `
        <h4 class="font-semibold text-gray-800 mb-2">Scan QR Code</h4>
        <p class="text-gray-600 text-sm mb-4">Open your camera or payment app and point it at this QR code.</p>
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-left">
            <div class="flex items-start">
                <i class="fas fa-lightbulb text-yellow-500 mt-1 mr-2"></i>
                <div>
                    <p class="text-yellow-800 text-sm font-medium">Scanning Tips:</p>
                    <ul class="text-yellow-700 text-xs mt-1 list-disc list-inside">
                        <li>Ensure good lighting</li>
                        <li>Hold steady for 2-3 seconds</li>
                        <li>Keep the QR code centered</li>
                    </ul>
                </div>
            </div>
        </div>
    `;
    
    actionBtn.innerHTML = '<i class="fas fa-download mr-2"></i> Save QR';
    actionBtn.onclick = () => downloadQRCode(qrImageSrc, paymentType);
}

function downloadQRCode(qrImageSrc, paymentType) {
    // Create a temporary link for download
    const link = document.createElement('a');
    link.href = qrImageSrc;
    link.download = `${paymentType.toLowerCase().replace(' ', '_')}_qrcode_${Date.now()}.png`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    showToast(`${paymentType} QR code downloaded!`, 'success');
}

function copyToClipboard(text) {
    const tempInput = document.createElement('input');
    tempInput.value = text;
    document.body.appendChild(tempInput);
    tempInput.select();
    document.execCommand('copy');
    document.body.removeChild(tempInput);
    showToast('Copied to clipboard!', 'success');
}

// Utility functions
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 z-50 px-6 py-4 rounded-xl shadow-lg text-white font-semibold transform translate-x-full transition-transform duration-300 ${
        type === 'success' ? 'bg-green-500' : 
        type === 'error' ? 'bg-red-500' : 'bg-blue-500'
    }`;
    toast.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation' : 'info'}-circle mr-3"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.transform = 'translateX(0)';
    }, 100);
    
    setTimeout(() => {
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }, 3000);
}

// Close modal when clicking outside
document.getElementById('qrModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeQRModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeQRModal();
    }
});
</script>

<style>
.qr-code-card {
    transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

.qr-code-card:hover {
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.qr-image {
    transition: transform 0.3s ease;
    cursor: pointer;
}

.qr-image:hover {
    transform: scale(1.02);
}

.qr-action-btn {
    transition: all 0.2s ease;
}

.qr-action-btn:hover {
    transform: scale(1.1);
}

#qrModal {
    backdrop-filter: blur(8px);
}

#qrModal > div {
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* Loading animation for QR codes */
.qr-loading {
    background: linear-gradient(90deg, #f3f4f6 25%, #e5e7eb 50%, #f3f4f6 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
}

@keyframes loading {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* Pulse animation for default QR codes */
.qr-code-card:has(.bg-green-100) {
    border-color: #10b981;
    box-shadow: 0 0 0 1px rgba(16, 185, 129, 0.1);
}

.qr-code-card:has(.bg-green-100)::before {
    content: '';
    position: absolute;
    top: -2px;
    left: -2px;
    right: -2px;
    bottom: -2px;
    border: 2px solid #10b981;
    border-radius: 1rem;
    opacity: 0;
    animation: pulse-border 2s infinite;
}

@keyframes pulse-border {
    0%, 100% { opacity: 0; transform: scale(1); }
    50% { opacity: 1; transform: scale(1.02); }
}
</style>